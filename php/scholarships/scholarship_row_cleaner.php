<?php
declare(strict_types=1);
require_once __DIR__ . '/url_normalizer.php';

function clean_scholarship_row(array $raw, array $states): array
{
    $issues    = [];
    $has_error = false;
    $get = static fn(string $k): string =>
        trim(preg_replace('/\x{00A0}/u', ' ', (string)($raw[$k] ?? '')));

    $c = [];
    $c['name']          = $get('Scholarship name');
    $c['provider_name'] = $get('Scholarship provider/organization');
    $c['provider_type'] = $get('Provider type');
    $c['official_url']  = $get('Official scholarship URL');
    $c['state_region']  = $get('State/region');
    $c['scope']         = $get('National vs. state/local');
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

    // Required
    if ($c['name'] === '')          { $issues[] = 'Scholarship name is required.'; $has_error = true; }
    if ($c['provider_name'] === '') { $issues[] = 'Scholarship provider/organization is required.'; $has_error = true; }
    if ($c['provider_type'] === '') { $issues[] = 'Provider type is required.'; $has_error = true; }

    // URL
    $c['normalized_url'] = normalize_url($c['official_url']);
    if ($c['official_url'] === '') {
        $issues[] = 'Official scholarship URL is missing.';
        $has_error = true;
    } elseif ($c['normalized_url'] === null) {
        $issues[] = 'Official scholarship URL is not a valid http(s) URL.';
        $has_error = true;
    }

    // National vs state/local
    $isNational = stripos($c['scope'], 'national') !== false
               || stripos($c['state_region'], 'national') !== false;
    $c['is_national'] = $isNational ? 1 : 0;
    $c['state_id'] = null;
    $c['state_display'] = '';
    if ($isNational) {
        $c['state_display'] = 'NA';
    } else {
        foreach ($states as $s) {
            if (strcasecmp($c['state_region'], $s['state_code']) === 0
                || stripos($c['state_region'], $s['state_name']) === 0) {
                $c['state_id']      = (int)$s['state_id'];
                $c['state_display'] = $s['state_code'];
                break;
            }
        }
        if ($c['state_id'] === null) {
            $issues[] = 'State/region "' . $c['state_region'] . '" does not match a U.S. state.';
            $has_error = true;
        }
    }

    // Yes/No/Unknown
    foreach (['renewable','financial_need','essay','recommendation','transcript_test',
              'community_service','leadership'] as $f) {
        $c[$f] = normalize_yes_no($c[$f]);
    }

    // Money
    $c['amount'] = parse_money($c['award_raw']);
    if ($c['amount'] === null && $c['award_raw'] !== '' && stripos($c['award_raw'], 'vary') === false) {
        $issues[] = 'Award amount "' . $c['award_raw'] . '" is not numeric.';
    }
    $c['maximum_value'] = parse_money($c['maximum_value_raw']);

    // Dates
    $c['deadline']          = parse_date_or_null($c['deadline_raw']);
    $c['open_date']         = parse_date_or_null($c['open_date_raw']);
    $c['verification_date'] = parse_date_or_null($c['verification_date_raw']);
    if ($c['deadline'] === null && $c['deadline_raw'] !== ''
        && stripos($c['deadline_raw'], 'rolling') === false
        && stripos($c['deadline_raw'], 'not posted') === false) {
        $issues[] = 'Deadline "' . $c['deadline_raw'] . '" is not a recognizable date.';
    }

    // Grades
    $c['grade_ids'] = parse_grade_range($c['eligible_grades']);

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