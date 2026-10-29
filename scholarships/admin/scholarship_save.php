<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scholarship_repository.php';
require_role(['Administrator', 'Super-admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/admin/scholarships.php');
    exit;
}
csrf_verify();

$pdo = db();
$id = filter_input(INPUT_POST, 'scholarship_id', FILTER_VALIDATE_INT) ?: 0;
$name = clean_import_text($_POST['name'] ?? '');
$provider = clean_import_text($_POST['provider_name'] ?? '');
$providerType = clean_import_text($_POST['provider_type'] ?? '');
$officialUrl = clean_import_text($_POST['official_url'] ?? '');
$scope = clean_import_text($_POST['scope'] ?? '');
$stateId = filter_input(INPUT_POST, 'state_id', FILTER_VALIDATE_INT);
$issues = [];

foreach ([
    [$name, 'Scholarship name'],
    [$provider, 'Provider/organization'],
    [$providerType, 'Provider type'],
] as [$value, $label]) {
    if ($value === '') {
        $issues[] = $label . ' is required.';
    }
}
if (mb_strlen($name, 'UTF-8') > 255 || mb_strlen($provider, 'UTF-8') > 255 || mb_strlen($providerType, 'UTF-8') > 100) {
    $issues[] = 'Scholarship name, provider, or provider type exceeds its allowed length.';
}

$normalizedUrl = normalize_url($officialUrl);
if ($normalizedUrl === null || strlen($officialUrl) > 2048) {
    $issues[] = 'Enter a valid official HTTP or HTTPS scholarship URL of 2,048 characters or fewer.';
} elseif (!preg_match('~^https?://~i', $officialUrl)) {
    $officialUrl = 'https://' . $officialUrl;
}
if (!in_array($scope, ['National', 'State/Local'], true)) {
    $issues[] = 'Select National or State/Local classification.';
}

$state = null;
if ($scope === 'State/Local') {
    if (!$stateId || $stateId < 1) {
        $issues[] = 'Select a state for a State/Local scholarship.';
    } else {
        $stateStatement = $pdo->prepare('SELECT state_id, state_code FROM states WHERE state_id = ?');
        $stateStatement->execute([$stateId]);
        $state = $stateStatement->fetch();
        if (!$state) {
            $issues[] = 'The selected state is not valid.';
        }
    }
}

$amountRaw = clean_import_text($_POST['amount'] ?? '');
$amount = parse_money($amountRaw);
if ($amountRaw !== '' && $amount === null) {
    $issues[] = 'Award amount could not be parsed as money.';
}
$maximumRaw = clean_import_text($_POST['maximum_value'] ?? '');
$maximumValue = parse_money($maximumRaw);
if ($maximumRaw !== '' && $maximumValue === null) {
    $issues[] = 'Maximum potential value could not be parsed as money.';
}

$dates = [];
foreach ([
    'open_date' => 'Application open date',
    'deadline' => 'Application deadline',
    'verification_date' => 'Verification date',
] as $field => $label) {
    $rawDate = clean_import_text($_POST[$field] ?? '');
    $dates[$field] = parse_date_or_null($rawDate);
    if ($rawDate !== '' && $dates[$field] === null) {
        $issues[] = $label . ' is not a valid date.';
    }
}

$gradeInput = clean_import_text($_POST['eligible_grades'] ?? '');
$gradeIds = parse_grade_range($gradeInput);
if ($gradeInput !== '' && $gradeIds === []) {
    $issues[] = 'Eligible grades must contain grade levels from 6 through 12.';
}

if ($issues !== []) {
    $_SESSION['flash_error'] = implode(' ', $issues);
    header('Location: ' . APP_URL . '/admin/scholarship_form.php' . ($id ? '?id=' . $id : ''));
    exit;
}

$cleaned = [
    'name' => $name,
    'provider_name' => $provider,
    'provider_type' => $providerType,
    'official_url' => $officialUrl,
    'normalized_url' => $normalizedUrl,
    'state_region' => $state['state_code'] ?? '',
    'state_code' => $state['state_code'] ?? null,
    'state_display' => $state['state_code'] ?? 'National',
    'state_id' => $scope === 'National' ? null : (int)$state['state_id'],
    'scope' => $scope,
    'is_national' => $scope === 'National' ? 1 : 0,
    'amount' => $amount,
    'award_raw' => $amountRaw,
    'maximum_value' => $maximumValue,
    'maximum_value_raw' => $maximumRaw,
    'deadline' => $dates['deadline'],
    'deadline_raw' => clean_import_text($_POST['deadline'] ?? ''),
    'open_date' => $dates['open_date'],
    'open_date_raw' => clean_import_text($_POST['open_date'] ?? ''),
    'verification_date' => $dates['verification_date'],
    'verification_date_raw' => clean_import_text($_POST['verification_date'] ?? ''),
    'eligible_grades' => $gradeInput,
    'grade_ids' => $gradeIds,
    'category' => clean_import_text($_POST['category'] ?? ''),
    'field_of_interest' => clean_import_text($_POST['field_of_interest'] ?? ''),
    'number_of_awards' => clean_import_text($_POST['number_of_awards'] ?? ''),
    'renewable' => normalize_yes_no(clean_import_text($_POST['renewable'] ?? 'Unknown')),
    'financial_need' => normalize_yes_no(clean_import_text($_POST['financial_need'] ?? 'Unknown')),
    'essay' => normalize_yes_no(clean_import_text($_POST['essay'] ?? 'Unknown')),
    'recommendation' => normalize_yes_no(clean_import_text($_POST['recommendation'] ?? 'Unknown')),
    'transcript_test' => normalize_yes_no(clean_import_text($_POST['transcript_test'] ?? 'Unknown')),
    'community_service' => normalize_yes_no(clean_import_text($_POST['community_service'] ?? 'Unknown')),
    'leadership' => normalize_yes_no(clean_import_text($_POST['leadership'] ?? 'Unknown')),
    'school_level' => clean_import_text($_POST['school_level'] ?? ''),
    'minimum_gpa' => clean_import_text($_POST['minimum_gpa'] ?? ''),
    'residency' => clean_import_text($_POST['residency'] ?? ''),
    'application_method' => clean_import_text($_POST['application_method'] ?? ''),
    'discovery_source' => clean_import_text($_POST['discovery_source'] ?? ''),
    'other_requirements' => clean_import_text($_POST['other_requirements'] ?? ''),
    'notes' => clean_import_text($_POST['notes'] ?? ''),
];

try {
    $pdo->beginTransaction();
    persist_scholarship($pdo, $cleaned, $id > 0 ? $id : null, $issues);
    $pdo->commit();
    $_SESSION['flash_success'] = $id > 0 ? 'Scholarship updated.' : 'Scholarship created.';
    header('Location: ' . APP_URL . '/admin/scholarships.php');
    exit;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Scholarship save failed: ' . $exception->getMessage());
    $_SESSION['flash_error'] = 'The scholarship could not be saved. Please check the database and try again.';
    header('Location: ' . APP_URL . '/admin/scholarship_form.php' . ($id ? '?id=' . $id : ''));
    exit;
}
