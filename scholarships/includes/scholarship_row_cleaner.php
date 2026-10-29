<?php
declare(strict_types=1);
require_once __DIR__ . '/url_normalizer.php';

function clean_import_text(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    if (!is_scalar($value) && !($value instanceof Stringable)) {
        return '';
    }
    $text = (string)$value;
    $text = preg_replace('/\x{00A0}/u', ' ', $text) ?? $text;
    $text = preg_replace('/[\p{Z}\s]+/u', ' ', $text) ?? $text;
    return trim($text);
}

function clean_scholarship_row(array $raw, array $states): array
{
    $issues = [];
    $hasError = false;
    $get = static fn(string $key): string => clean_import_text($raw[$key] ?? '');

    $c = [
        'name' => $get('Scholarship name'),
        'provider_name' => $get('Scholarship provider/organization'),
        'provider_type' => $get('Provider type'),
        'official_url' => $get('Official scholarship URL'),
        'state_region' => $get('State code') !== '' ? $get('State code') : $get('State/region'),
        'scope' => $get('National vs. state/local'),
        'eligible_grades' => $get('Eligible grade levels'),
        'school_level' => $get('Middle school / high school / both'),
        'category' => $get('Scholarship category'),
        'field_of_interest' => $get('Field/area of interest'),
        'award_raw' => $get('Award amount'),
        'number_of_awards' => $get('Number of awards (if known)'),
        'renewable' => $get('Renewable (Yes/No/Unknown)'),
        'maximum_value_raw' => $get('Maximum potential value'),
        'open_date_raw' => $get('Application open date (if known)'),
        'deadline_raw' => $get('Application deadline'),
        'residency' => $get('Residency requirement'),
        'minimum_gpa' => $get('Minimum GPA (if any)'),
        'financial_need' => $get('Financial need required (Yes/No/Unknown)'),
        'essay' => $get('Essay required'),
        'recommendation' => $get('Recommendation required'),
        'transcript_test' => $get('Transcript/test score required'),
        'community_service' => $get('Community service requirement'),
        'leadership' => $get('Leadership requirement'),
        'other_requirements' => $get('Other major eligibility requirements'),
        'application_method' => $get('Application method'),
        'verification_date_raw' => $get('Verification date'),
        'notes' => $get('Notes'),
        'discovery_source' => $get('Discovery source'),
    ];

    foreach ([
        'name' => 'Scholarship name',
        'provider_name' => 'Scholarship provider/organization',
        'provider_type' => 'Provider type',
    ] as $key => $label) {
        if ($c[$key] === '') {
            $issues[] = $label . ' is required.';
            $hasError = true;
        }
    }

    $c['normalized_url'] = normalize_url($c['official_url']);
    if ($c['normalized_url'] !== null && !preg_match('~^https?://~i', $c['official_url'])) {
        $c['official_url'] = 'https://' . $c['official_url'];
    }
    if ($c['official_url'] === '') {
        $issues[] = 'Official scholarship URL is missing.';
        $hasError = true;
    } elseif ($c['normalized_url'] === null) {
        $issues[] = 'Official scholarship URL is not a valid HTTP or HTTPS URL.';
        $hasError = true;
    }

    $region = mb_strtolower($c['state_region'], 'UTF-8');
    $scopeText = mb_strtolower($c['scope'], 'UTF-8');
    $isNational = str_contains($scopeText, 'national')
        || str_contains($scopeText, 'nationwide')
        || str_contains($scopeText, 'federal')
        || str_contains($scopeText, 'all states')
        || str_contains($region, 'national')
        || in_array($region, ['usa', 'u.s.', 'u.s.a.', 'united states', 'nationwide'], true);
    $c['is_national'] = $isNational ? 1 : 0;
    $c['scope'] = $isNational ? 'National' : 'State/Local';
    $c['state_id'] = null;
    $c['state_code'] = null;
    $c['state_display'] = $isNational ? 'National' : '';

    if (!$isNational) {
        $stateCandidate = preg_replace('/^\s*state of\s+/i', '', $c['state_region']);
        $stateCandidate = preg_replace('/\s*,?\s*(?:u\.?s\.?a?\.?|united states)$/i', '', (string)$stateCandidate);
        $stateCandidate = preg_replace('/\s+state$/i', '', (string)$stateCandidate);
        foreach ($states as $state) {
            $stateName = clean_import_text($state['state_name'] ?? '');
            $stateCode = clean_import_text($state['state_code'] ?? '');
            if (strcasecmp((string)$stateCandidate, $stateCode) === 0
                || strcasecmp((string)$stateCandidate, $stateName) === 0) {
                $c['state_id'] = (int)$state['state_id'];
                $c['state_code'] = $stateCode;
                $c['state_display'] = $stateCode;
                $c['state_region'] = $stateCode;
                break;
            }
        }
        if ($c['state_id'] === null) {
            $issues[] = 'State/region "' . $c['state_region'] . '" does not match a U.S. state.';
            $hasError = true;
        }
    }

    foreach ([
        'renewable', 'financial_need', 'essay', 'recommendation',
        'transcript_test', 'community_service', 'leadership',
    ] as $field) {
        $c[$field] = normalize_yes_no($c[$field]);
    }

    $c['amount'] = parse_money($c['award_raw']);
    if ($c['amount'] === null && $c['award_raw'] !== ''
        && !preg_match('/\b(vary|varies|unknown|not stated)\b/i', $c['award_raw'])) {
        $issues[] = 'Award amount "' . $c['award_raw'] . '" could not be parsed.';
    }
    $c['maximum_value'] = parse_money($c['maximum_value_raw']);
    if ($c['maximum_value'] === null && $c['maximum_value_raw'] !== '') {
        $issues[] = 'Maximum potential value "' . $c['maximum_value_raw'] . '" could not be parsed.';
    }

    $c['deadline'] = parse_date_or_null($c['deadline_raw']);
    $c['open_date'] = parse_date_or_null($c['open_date_raw']);
    $c['verification_date'] = parse_date_or_null($c['verification_date_raw']);
    if ($c['deadline'] === null && $c['deadline_raw'] !== ''
        && !preg_match('/\b(rolling|not posted|tbd|none)\b/i', $c['deadline_raw'])) {
        $issues[] = 'Deadline "' . $c['deadline_raw'] . '" is not a recognizable date.';
    }
    foreach ([
        'open_date' => ['raw' => 'open_date_raw', 'label' => 'Application open date'],
        'verification_date' => ['raw' => 'verification_date_raw', 'label' => 'Verification date'],
    ] as $dateField => $dateInfo) {
        if ($c[$dateField] === null && $c[$dateInfo['raw']] !== '') {
            $issues[] = $dateInfo['label'] . ' "' . $c[$dateInfo['raw']] . '" is not a recognizable date.';
        }
    }

    $c['grade_ids'] = parse_grade_range($c['eligible_grades']);

    return [
        'cleaned' => $c,
        'issues' => $issues,
        'has_error' => $hasError,
        'normalized_url' => $c['normalized_url'],
    ];
}

function normalize_yes_no(string $value): string
{
    $value = mb_strtolower(clean_import_text($value), 'UTF-8');
    if (in_array($value, ['yes', 'y', 'true', 'required', '1'], true)) {
        return 'Yes';
    }
    if (in_array($value, ['no', 'n', 'false', 'not required', '0'], true)) {
        return 'No';
    }
    return 'Unknown';
}

function parse_money(string $value): ?string
{
    $value = clean_import_text($value);
    if ($value === '') {
        return null;
    }
    if (!preg_match('/(?<![\d,.])\$?\s*((?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d{1,2})?)\s*([kmb])?(?![\d,.])/i', $value, $matches)) {
        return null;
    }
    $amount = (float)str_replace(',', '', $matches[1]);
    $multiplier = strtolower($matches[2] ?? '');
    $amount *= ['k' => 1000, 'm' => 1000000, 'b' => 1000000000][$multiplier] ?? 1;
    return $amount <= 9999999999.99 ? number_format($amount, 2, '.', '') : null;
}

function parse_date_or_null(string $value): ?string
{
    $value = clean_import_text($value);
    if ($value === '') {
        return null;
    }
    foreach ([
        '!Y-m-d', '!Y-m-d H:i:s', '!m/d/Y', '!n/j/Y', '!m/d/y', '!n/j/y',
        '!F j, Y', '!M j, Y', '!F j Y', '!M j Y',
    ] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $date->format('Y-m-d');
        }
    }
    return null;
}

function parse_grade_range(string $value): array
{
    $value = mb_strtolower(clean_import_text($value), 'UTF-8');
    if ($value === '') {
        return [];
    }

    $grades = [];
    if (str_contains($value, 'middle')) {
        $grades = array_merge($grades, [6, 7, 8]);
    }
    if (str_contains($value, 'high school') || preg_match('/\bhigh\b/', $value)) {
        $grades = array_merge($grades, [9, 10, 11, 12]);
    }
    if (preg_match_all('/\b(\d{1,2})(?:st|nd|rd|th)?\s*(?:-|to|through)\s*(?:grade\s*)?(\d{1,2})(?:st|nd|rd|th)?\b/i', $value, $ranges, PREG_SET_ORDER)) {
        foreach ($ranges as $range) {
            $start = (int)$range[1];
            $end = (int)$range[2];
            if ($start <= $end && $end - $start <= 12) {
                $grades = array_merge($grades, range($start, $end));
            }
        }
    }
    if (preg_match_all('/\b(\d{1,2})(?:st|nd|rd|th)?\b/i', $value, $singles)) {
        foreach ($singles[1] as $grade) {
            $grades[] = (int)$grade;
        }
    }

    $grades = array_filter($grades, static fn(int $grade): bool => $grade >= 6 && $grade <= 12);
    sort($grades);
    return array_values(array_unique($grades));
}
