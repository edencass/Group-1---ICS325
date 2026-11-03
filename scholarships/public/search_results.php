<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scholarship_row_cleaner.php'; 

$pdo = db();

$stateCode = trim((string)($_GET['state'] ?? ''));
$grade     = (int)($_GET['grade'] ?? 0);
$fieldId   = (int)($_GET['field'] ?? 0);
$keyword   = trim((string)($_GET['q'] ?? ''));
$showExpired = isset($_GET['show_expired']) && $_GET['show_expired'] == '1';

// --- ANONYMOUS SEARCH LOGGING ---
if (isset($_GET['state']) || isset($_GET['grade']) || isset($_GET['field']) || isset($_GET['q'])) {
    try {
        $log = $pdo->prepare('INSERT INTO anonymous_search_log (state_code, grade, field_id) VALUES (?, ?, ?)');
        $log->execute([$stateCode ?: null, $grade ?: null, $fieldId ?: null]);
    } catch (PDOException $e) {
        error_log('Search logging failed: ' . $e->getMessage());
    }
}

$where  = ["s.scholarship_id IS NOT NULL"];
$params = [];

// 1. Hide expired scholarships by default
if (!$showExpired) {
    $where[] = "(s.deadline >= CURRENT_DATE OR s.deadline IS NULL)";
}

// 2. Filter by State
if ($stateCode !== '') {
    $where[] = "(s.state_code = :state OR s.is_national = 1)";
    $params['state'] = $stateCode;
}

// 3. Filter by Grade
if ($grade >= 6 && $grade <= 12) {
    $where[] = "EXISTS (
        SELECT 1 FROM scholarship_grades sg
        JOIN grade_levels gl ON gl.grade_level_id = sg.grade_level_id
        WHERE sg.scholarship_id = s.scholarship_id AND gl.grade_name = :grade_name
    )";
    $params['grade_name'] = 'Grade ' . $grade;
}

// 4. Filter by Field of Interest
if ($fieldId > 0) {
    $where[] = "EXISTS (
        SELECT 1 FROM scholarship_fields sf
        WHERE sf.scholarship_id = s.scholarship_id AND sf.field_id = :field_id
    )";
    $params['field_id'] = $fieldId;
}

// 5. Keyword Search (FIXED: unique parameter names for each column)
if ($keyword !== '') {
    $where[] = '(s.title LIKE :kw_title OR p.provider_name LIKE :kw_provider OR s.description LIKE :kw_desc)';
    $params['kw_title'] = '%' . $keyword . '%';
    $params['kw_provider'] = '%' . $keyword . '%';
    $params['kw_desc'] = '%' . $keyword . '%';
}

$sql = "SELECT s.scholarship_id, s.title, s.amount, s.deadline, s.description,
               s.official_url, s.state_code, s.is_national, s.verification_date,
               st.state_name,
               p.provider_name
          FROM scholarships s
          JOIN providers p ON p.provider_id = s.provider_id
          LEFT JOIN states st ON st.state_id = s.state_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY s.deadline IS NULL, s.deadline ASC
         LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<p class="text-muted small mb-3"><?= count($rows) ?> result<?= count($rows) === 1 ? '' : 's' ?> shown (max 100).</p>

<?php include __DIR__ . '/responsive.php'; ?>
