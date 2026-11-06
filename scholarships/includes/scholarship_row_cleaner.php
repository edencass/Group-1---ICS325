<?php
declare(strict_types=1);
require_once __DIR__ . '/url_normalizer.php';

function clean_scholarship_row(array $raw, array $states): array
{
    $issues    = [];
    $has_error = false;
    
    $get = static function(string $k, string $alt = '') use ($raw): string {
        $val = $raw[$k] ?? ($alt !== '' ? ($raw[$alt] ?? '') : '');
        return trim(preg_replace('/\x{00A0}/u', ' ', (string)$val));
    };

    $c = [];
    $c['name']          = $get('Scholarship name');
    $c['provider_name'] = $get('Scholarship provider/organization');
    $c['provider_type'] = $get('Provider type');
    $c['official_url']  = $get('Official scholarship URL');
    $c['state_region']  = $get('State/region', 'State code');
    $c['scope']         = $get('National vs. state/local', 'National vs. State/Local');
    $c['eligible_grades'] = $get('Eligible grade levels');
    $c['school_level']  = $get('Middle school / high school / both');
    $c['category']      = $get('Scholarship category');
    $c['field_of_interest'] = $get('Field/area of interest');
    $c['award_raw']     = $get('Award amount');
    $c['number_of_awards'] = $get('Number of awards (if known)');
    $c['renewable']     = $get('Renewable (Yes/No/Unknown)');
    $c['maximum_value_raw'] = $get('Maximum potential value');
    $c['open_date_raw'] = $get('Application open date (if known)');
    $c['deadline_raw']  = $get('Application deadline');
    $c['residency']     = $get('Residency requirement');
    $c['minimum_gpa']   = $get('Minimum GPA (if any)');
    $c['financial_need'] = $get('Financial need required (Yes/No/Unknown)');
    $c['essay']         = $get('Essay required');
    $c['recommendation'] = $get('Recommendation required');
    $c['transcript_test'] = $get('Transcript/test score required');
    $c['community_service'] = $get('Community service requirement');
    $c['leadership']    = $get('Leadership requirement');
    $c['other_requirements'] = $get('Other major eligibility requirements');
    $c['application_method'] = $get('Application method');
    $c['verification_date_raw'] = $get('Verification date');
    $c['notes']         = $get('Notes');
    $c['discovery_source'] = $get('Discovery source');

    if ($c['name'] === '')          { $issues[] = 'Scholarship name is required.'; $has_error = true; }
    if ($c['provider_name'] === '') { $issues[] = 'Scholarship provider/organization is required.'; $has_error = true; }
    if ($c['provider_type'] === '') { $issues[] = 'Provider type is required.'; $has_error = true; }

    $c['normalized_url'] = normalize_url($c['official_url']);
    if ($c['official_url'] === '') {
        $issues[] = 'Official scholarship URL is missing.';
        $has_error = true;
    } elseif ($c['normalized_url'] === null) {
        $issues[] = 'Official scholarship URL is not a valid http(s) URL.';
        $has_error = true;
    }

    // ----- FIXED STATE LOGIC -----
    $c['state_id'] = null;
    $c['state_display'] = '';
    $isNational = 0;

    if ($c['state_region'] !== '' && stripos($c['state_region'], 'national') === false) {
        foreach ($states as $s) {
            if (strcasecmp($c['state_region'], $s['state_code']) === 0) {
                $c['state_id']      = (int)$s['state_id'];
                $c['state_display'] = $s['state_code'];
                break;
            }
        }
    }

    if ($c['state_id'] !== null) {
        $isNational = 0;
        $c['state_display'] = $c['state_region'];
    } else {
        $isNational = 1;
        $c['state_display'] = 'NA';
    }

    $c['is_national'] = $isNational;
    $c['scope'] = $isNational ? 'National' : 'State/Local';

    foreach (['renewable','financial_need','essay','recommendation','transcript_test',
              'community_service','leadership'] as $f) {
        $c[$f] = normalize_yes_no($c[$f]);
    }

    $c['amount'] = parse_money($c['award_raw']);
    $c['maximum_value'] = parse_money($c['maximum_value_raw']);

    $c['deadline']          = parse_date_or_null($c['deadline_raw']);
    $c['open_date']         = parse_date_or_null($c['open_date_raw']);
    $c['verification_date'] = parse_date_or_null($c['verification_date_raw']);

    // ----- FIXED GRADE LOGIC -----
    // If the CSV says "HS/College" or "High School", we force it to map to grades 9-12.
    // If it says "All", we map it to 6-12.
    $gradeInput = strtolower($c['eligible_grades']);
    $grade_ids = [];
    if (str_contains($gradeInput, 'all') || str_contains($gradeInput, 'both')) {
        $grade_ids = [6,7,8,9,10,11,12];
    } elseif (str_contains($gradeInput, 'hs') || str_contains($gradeInput, 'high')) {
        $grade_ids = [9,10,11,12];
    } else {
        $grade_ids = parse_grade_range($c['eligible_grades']);
    }
    $c['grade_ids'] = $grade_ids;

    // ----- FIXED FIELD LOGIC -----
    // If the CSV says "General" or "Any", we map it to "Any / Undecided"
    $fieldStr = $c['field_of_interest'];
    if (strcasecmp($fieldStr, 'General') === 0 || strcasecmp($fieldStr, 'Any') === 0) {
        $c['field_of_interest'] = 'Any / Undecided';
    }

    return [
        'cleaned'        => $c,
        'issues'         => $issues,
        'has_error'      => $has_error,
        'normalized_url' => $c['normalized_url'],
    ];
}

function normalize_yes_no(string $v): string
{
    $v = strtolower(trim($v));
    if (in_array($v, ['yes','y','true','required'], true))     return 'Yes';
    if (in_array($v, ['no','n','false','not required'], true)) return 'No';
    return 'Unknown';
}

function parse_money(string $v): ?string
{
    if ($v === '') return null;
    if (!preg_match('/\$?\s*([\d,]+(?:\.\d{1,2})?)/', $v, $m)) return null;
    return str_replace(',', '', $m[1]);
}

function parse_date_or_null(string $v): ?string
{
    if ($v === '') return null;
    if (preg_match('/^(\d{3,4})-(\d{2})-(\d{2})$/', $v, $m)) {
        $year = (int)$m[1];
        if ($year < 1000) {
            $year += 2000;
            $v = sprintf('%04d-%02d-%02d', $year, (int)$m[2], (int)$m[3]);
        }
    } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/', $v, $m)) {
        $year = (int)$m[3];
        $year += ($year < 50) ? 2000 : 1900;
        $v = sprintf('%04d-%02d-%02d', $year, (int)$m[1], (int)$m[2]);
    }
    foreach (['!Y-m-d','!m/d/Y','!n/j/Y','!Y-m-d H:i:s'] as $fmt) {
        $d = DateTimeImmutable::createFromFormat($fmt, $v);
        if ($d) return $d->format('Y-m-d');
    }
    return null;
}

function parse_grade_range(string $v): array
{
    if ($v === '') return [];
    $ids = [];
    foreach (preg_split('/[,\s]+/', $v) as $tok) {
        if ($tok === '') continue;
        if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $tok, $m)) {
            for ($g = (int)$m[1]; $g <= (int)$m[2]; $g++) $ids[] = $g;
        } elseif (preg_match('/^\d{1,2}$/', $tok)) {
            $ids[] = (int)$tok;
        } elseif (stripos($tok, 'middle') !== false) {
            $ids = array_merge($ids, [6,7,8]);
        } elseif (stripos($tok, 'high') !== false) {
            $ids = array_merge($ids, [9,10,11,12]);
        }
    }
    return array_values(array_unique(array_filter($ids, fn($g) => $g >= 6 && $g <= 12)));
}

function format_date_us(?string $date): string
{
    if (!$date || $date === '0000-00-00') return '';
    return date('M j, Y', strtotime($date));
}
