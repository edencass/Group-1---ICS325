<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scholarship_row_cleaner.php';

$userId = current_user_id();
if (!$userId) {
    header('Location: ' . APP_URL . '/public/login.php', true, 302);
    exit;
}

$pdo = db();
$isParent = normalized_role(current_role()) === 'parent';
$profilesStmt = $pdo->prepare(
    'SELECT profile_id, first_name, last_name, grade_level, state_id
       FROM profiles
      WHERE user_id = ?
      ORDER BY profile_id'
);
$profilesStmt->execute([$userId]);
$profiles = $profilesStmt->fetchAll();

$requestedProfileId = filter_var($_GET['profile_id'] ?? null, FILTER_VALIDATE_INT);
$selectedProfileId = $requestedProfileId !== false && $requestedProfileId !== null
    ? (int)$requestedProfileId
    : null;
$profile = null;
if ($isParent && $selectedProfileId !== null) {
    foreach ($profiles as $candidate) {
        if ((int)$candidate['profile_id'] === $selectedProfileId) {
            $profile = $candidate;
            break;
        }
    }
    if (!$profile) {
        http_response_code(404);
        exit('Student profile not found.');
    }
} elseif ($profiles) {
    $profile = $profiles[0];
    $selectedProfileId = (int)$profile['profile_id'];
}

$rows = [];
$appliedFilters = [];
if ($profile) {
    $where = [
        's.scholarship_id IS NOT NULL',
        '(s.deadline >= CURRENT_DATE OR s.deadline IS NULL)',
    ];
    $params = [];

    if (!empty($profile['grade_level'])) {
        $where[] = 'EXISTS (
            SELECT 1
              FROM scholarship_grades sg
              JOIN grade_levels gl ON gl.grade_level_id = sg.grade_level_id
             WHERE sg.scholarship_id = s.scholarship_id
               AND gl.grade_name = :grade_name
        )';
        $params['grade_name'] = $profile['grade_level'];
        $appliedFilters[] = (string)$profile['grade_level'];
    }

    if (!empty($profile['state_id'])) {
        $stateStmt = $pdo->prepare('SELECT state_name, state_code FROM states WHERE state_id = ?');
        $stateStmt->execute([(int)$profile['state_id']]);
        $state = $stateStmt->fetch();
        if ($state) {
            $where[] = '(s.state_id = :state_id OR s.state_code = :state_code OR s.is_national = 1)';
            $params['state_id'] = (int)$profile['state_id'];
            $params['state_code'] = $state['state_code'];
            $appliedFilters[] = (string)$state['state_name'];
        }
    }

    $interestStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM profile_interests WHERE profile_id = ?'
    );
    $interestStmt->execute([(int)$profile['profile_id']]);
    $hasInterests = (int)$interestStmt->fetchColumn() > 0;
    if ($hasInterests) {
        $where[] = 'EXISTS (
            SELECT 1
              FROM scholarship_fields sf
              JOIN profile_interests pi ON pi.field_id = sf.field_id
             WHERE sf.scholarship_id = s.scholarship_id
               AND pi.profile_id = :profile_id
        )';
        $params['profile_id'] = (int)$profile['profile_id'];
        $appliedFilters[] = 'selected interests';
    }

    if ($appliedFilters) {
        $sql = 'SELECT s.scholarship_id, s.title, s.amount, s.deadline, s.description,
                       s.official_url, s.state_code, s.is_national, s.verification_date,
                       st.state_name, p.provider_name
                  FROM scholarships s
                  JOIN providers p ON p.provider_id = s.provider_id
                  LEFT JOIN states st ON st.state_id = s.state_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY s.deadline IS NULL, s.deadline ASC
                 LIMIT 100';
        $matchesStmt = $pdo->prepare($sql);
        $matchesStmt->execute($params);
        $rows = $matchesStmt->fetchAll();
    }
}

$page_title = 'My Matches';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="h3 mb-1">My Matches</h1>
    <p class="text-muted mb-0">Scholarships matched to a student profile.</p>
  </div>
  <a class="btn btn-outline-primary" href="<?= e(APP_URL) ?>/public/profile.php">Manage profiles</a>
</div>

<?php if (!$profiles): ?>
  <div class="alert alert-info">
    Add a student profile with a grade, state, or interests to see matching scholarships.
    <a href="<?= e(APP_URL) ?>/public/profile.php">Create a profile</a>.
  </div>
<?php else: ?>
  <?php if ($isParent): ?>
    <div class="mb-3">
      <label class="form-label" for="match-profile">Student</label>
      <select class="form-select" id="match-profile" onchange="if (this.value) window.location.href = this.value;">
        <?php foreach ($profiles as $candidate): ?>
          <?php
            $candidateName = trim((string)($candidate['first_name'] ?? '') . ' ' . (string)($candidate['last_name'] ?? ''));
            if ($candidateName === '') {
                $candidateName = 'Student profile ' . (int)$candidate['profile_id'];
            }
          ?>
          <option value="<?= e(APP_URL) ?>/public/my_matches.php?profile_id=<?= (int)$candidate['profile_id'] ?>"
            <?= $selectedProfileId === (int)$candidate['profile_id'] ? 'selected' : '' ?>>
            <?= e($candidateName) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <?php if ($profile): ?>
    <?php
      $studentName = trim((string)($profile['first_name'] ?? '') . ' ' . (string)($profile['last_name'] ?? ''));
      if ($studentName === '') {
          $studentName = 'this student';
      }
    ?>
    <p class="mb-3">
      Showing matches for <strong><?= e($studentName) ?></strong>
      <?php if ($appliedFilters): ?>
        using <?= e(implode(', ', $appliedFilters)) ?>.
      <?php else: ?>
        &mdash; Add grade, state, or interests to the profile to see relevant results.
      <?php endif; ?>
    </p>
    <?php if (!$appliedFilters): ?>
      <div class="alert alert-info">Add a grade, state, or interest to this profile to see relevant matches.</div>
    <?php elseif (!$rows): ?>
      <div class="alert alert-info">No current scholarships match this profile. Update the profile or check back later.</div>
    <?php else: ?>
      <?php include __DIR__ . '/responsive.php'; ?>
    <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
