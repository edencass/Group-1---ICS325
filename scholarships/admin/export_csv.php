<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['Administrator', 'Super-admin']);

$pdo = db();

// Fetch all scholarships (you can add WHERE clauses here if you want to filter later)
$stmt = $pdo->query(
    "SELECT s.*, p.provider_name, st.state_code AS state_region, st.state_name
       FROM scholarships s
       JOIN providers p ON p.provider_id = s.provider_id
       LEFT JOIN states st ON st.state_id = s.state_id
      ORDER BY s.scholarship_id ASC"
);
$rows = $stmt->fetchAll();

// Set headers to force download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="scholarships_export_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

// 1. Write the EXACT 29 template headers
fputcsv($output, [
    'Scholarship name',
    'Scholarship provider/organization',
    'Provider type',
    'Official scholarship URL',
    'Discovery source',
    'State/region',
    'National vs. state/local',
    'Eligible grade levels',
    'Middle school / high school / both',
    'Scholarship category',
    'Field/area of interest',
    'Award amount',
    'Number of awards (if known)',
    'Renewable (Yes/No/Unknown)',
    'Maximum potential value',
    'Application open date (if known)',
    'Application deadline',
    'Residency requirement',
    'Minimum GPA (if any)',
    'Financial need required (Yes/No/Unknown)',
    'Essay required',
    'Recommendation required',
    'Transcript/test score required',
    'Community service requirement',
    'Leadership requirement',
    'Other major eligibility requirements',
    'Application method',
    'Verification date',
    'Notes'
]);

// 2. Loop through database rows and write them to the CSV
foreach ($rows as $r) {
    // Decode the JSON blob that was stored during import
    $d = json_decode((string)$r['description'], true) ?: [];

    // Get grades and fields (since they are many-to-many, we'll just pull them from the JSON for the export)
    $grades = $d['eligible_grades'] ?? '';
    $fields = $d['field_of_interest'] ?? '';

    fputcsv($output, [
        $r['title'],
        $r['provider_name'],
        $d['provider_type'] ?? '',
        $r['official_url'],
        $d['discovery_source'] ?? '',
        $r['is_national'] ? 'National' : ($r['state_region'] ?? ''),
        $r['is_national'] ? 'National' : 'State/local',
        $grades,
        $d['school_level'] ?? '',
        $d['category'] ?? '',
        $fields,
        $r['amount'],
        $d['number_of_awards'] ?? '',
        $d['renewable'] ?? 'Unknown',
        $d['maximum_value_raw'] ?? '',
        $r['open_date'],
        $r['deadline'],
        $d['residency'] ?? '',
        $d['minimum_gpa'] ?? '',
        $d['financial_need'] ?? 'Unknown',
        $d['essay'] ?? 'Unknown',
        $d['recommendation'] ?? 'Unknown',
        $d['transcript_test'] ?? 'Unknown',
        $d['community_service'] ?? 'Unknown',
        $d['leadership'] ?? 'Unknown',
        $d['other_requirements'] ?? '',
        $d['application_method'] ?? '',
        $r['verification_date'],
        $d['notes'] ?? ''
    ]);
}

fclose($output);
exit;