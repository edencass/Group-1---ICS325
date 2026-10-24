<?php
require_once __DIR__ . '/../includes/scholarship_row_cleaner.php';
header('Content-Type: text/plain');

$states = [
    ['state_id' => 1, 'state_code' => 'TN', 'state_name' => 'Tennessee'],
    ['state_id' => 2, 'state_code' => 'CA', 'state_name' => 'California'],
];

// Test row 1: clean — should be Ready
$row1 = [
    'Scholarship name'                => 'Tennessee Promise',
    'Scholarship provider/organization' => 'THEC',
    'Provider type'                   => 'Government agency',
    'Official scholarship URL'        => 'https://www.tn.gov/tnpromise.html',
    'State/region'                    => 'Tennessee',
    'National vs. state/local'        => 'State/local',
    'Eligible grade levels'           => '12',
    'Award amount'                    => '$1,000',
    'Application deadline'            => '2026-11-02',
];

// Test row 2: broken — missing name and bad URL — should be Error
$row2 = [
    'Scholarship name'                => '',
    'Scholarship provider/organization' => 'Some Provider',
    'Provider type'                   => 'Foundation/nonprofit',
    'Official scholarship URL'        => 'not-a-url',
    'State/region'                    => 'CA',
    'National vs. state/local'        => 'State/local',
    'Application deadline'            => '2026-12-01',
];

// Test row 3: soft issues — missing deadline and non-numeric amount — should be Warning
$row3 = [
    'Scholarship name'                => 'Fuzzy Scholarship',
    'Scholarship provider/organization' => 'Some Foundation',
    'Provider type'                   => 'Foundation/nonprofit',
    'Official scholarship URL'        => 'https://example.org/fuzzy',
    'State/region'                    => 'CA',
    'National vs. state/local'        => 'State/local',
    'Award amount'                    => 'Varies',
    'Application deadline'            => 'TBD',
];

foreach ([$row1, $row2, $row3] as $i => $row) {
    $res = clean_scholarship_row($row, $states);
    echo "=== Row " . ($i + 1) . " ===\n";
    echo "has_error: " . ($res['has_error'] ? 'true' : 'false') . "\n";
    echo "issues: " . implode(' | ', $res['issues']) . "\n";
    echo "normalized_url: " . ($res['normalized_url'] ?? 'NULL') . "\n";
    echo "\n";
}