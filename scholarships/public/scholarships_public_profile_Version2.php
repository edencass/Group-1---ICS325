<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

$userId = current_user_id();
if (!$userId) {
    http_response_code(401);
    exit('Please sign in to view your profile.');
}

$pdo = db();
$isParent = normalized_role(current_role()) === 'parent';
$page_title = $isParent ? 'Manage Student Profiles' : 'My Profile';
$states = $pdo->query(
    'SELECT state_id, state_name FROM states ORDER BY state_name'
)->fetchAll();
$grades = $pdo->query(
    'SELECT grade_name FROM grade_levels ORDER BY grade_level_id'
)->fetchAll();
$fields = $pdo->query(
    'SELECT field_id, field_name FROM fields_of_study ORDER BY field_name'
)->fetchAll();

$userStmt = $pdo->prepare('SELECT email FROM users WHERE user_id = ?');
$userStmt->execute([$userId]);
$user = $userStmt->fetch();
if (!$user) {
    http_response_code(404);
    exit('User account not found.');
}

$profilesStmt = $pdo->prepare(
    'SELECT profile_id, first_name, last_name, grade_level, gpa, state_id
       FROM profiles
      WHERE user_id = ?
      ORDER BY profile_id'
);
$profilesStmt->execute([$userId]);
$profiles = $profilesStmt->fetchAll();
$profileById = [];
foreach ($profiles as $savedProfile) {
    $profileById[(int)$savedProfile['profile_id']] = $savedProfile;
}

$requestedProfileId = filter_var($_GET['profile_id'] ?? null, FILTER_VALIDATE_INT);
$isNewProfile = $isParent && isset($_GET['new']) && $_GET['new'] === '1';
$selectedProfileId = $requestedProfileId !== false && $requestedProfileId !== null
    ? (int)$requestedProfileId
    : null;
$profile = null;
$errors = [];

if ($isParent && $selectedProfileId !== null) {
    $profile = $profileById[$selectedProfileId] ?? null;
    if (!$profile) {
        http_response_code(404);
        exit('Student profile not found.');
    }
} elseif (!$isNewProfile && $profiles) {
    $profile = $profiles[0];
    $selectedProfileId = (int)$profile['profile_id'];
}

if ($profile === null) {
    $profile = [
        'profile_id' => null,
        'first_name' => '',
        'last_name' => '',
        'grade_level' => '',
        'gpa' => '',
        'state_id' => null,
    ];
}

$selectedFields = [];
if ($profile['profile_id']) {
    $interestStmt = $pdo->prepare(
        'SELECT field_id FROM profile_interests WHERE profile_id = ?'
    );
    $interestStmt->execute([$profile['profile_id']]);
    $selectedFields = array_map('intval', $interestStmt->fetchAll(PDO::FETCH_COLUMN));
}

$success = $_SESSION['profile_success'] ?? null;
unset($_SESSION['profile_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : 'save';
    $postedProfileId = filter_var($_POST['profile_id'] ?? null, FILTER_VALIDATE_INT);
    $postedProfileId = $postedProfileId !== false && $postedProfileId !== null
        ? (int)$postedProfileId
        : null;

    if ($action === 'delete') {
        if (!$isParent || !$postedProfileId || !isset($profileById[$postedProfileId])) {
            http_response_code(403);
            exit('You do not have permission to delete this profile.');
        }

        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM profile_interests WHERE profile_id = ?')
                ->execute([$postedProfileId]);
            $deleteProfile = $pdo->prepare(
                'DELETE FROM profiles WHERE profile_id = ? AND user_id = ?'
            );
            $deleteProfile->execute([$postedProfileId, $userId]);
            if ($deleteProfile->rowCount() !== 1) {
                throw new RuntimeException('The student profile was not deleted.');
            }
            $pdo->commit();
            $_SESSION['profile_success'] = 'Student profile deleted.';
            header('Location: ' . APP_URL . '/public/profile.php', true, 303);
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Student profile deletion failed: ' . $exception->getMessage());
            $errors[] = 'The student profile could not be deleted. Please try again.';
        }
    } elseif ($action !== 'save') {
        $errors[] = 'Choose a valid profile action.';
    } else {
        if ($isParent) {
            if ($postedProfileId !== null) {
                $profile = $profileById[$postedProfileId] ?? null;
                if (!$profile) {
                    $errors[] = 'That student profile could not be found.';
                }
            } else {
                $profile = [
                    'profile_id' => null,
                    'first_name' => '',
                    'last_name' => '',
                    'grade_level' => '',
                    'gpa' => '',
                    'state_id' => null,
                ];
            }
        } else {
            $profile = $profiles[0] ?? [
                'profile_id' => null,
                'first_name' => '',
                'last_name' => '',
                'grade_level' => '',
                'gpa' => '',
                'state_id' => null,
            ];
        }
        $profile ??= [
            'profile_id' => null,
            'first_name' => '',
            'last_name' => '',
            'grade_level' => '',
            'gpa' => '',
            'state_id' => null,
        ];
        $selectedProfileId = $profile['profile_id'] ? (int)$profile['profile_id'] : null;

        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $gradeLevel = trim((string)($_POST['grade_level'] ?? ''));
        $gpaInput = trim((string)($_POST['gpa'] ?? ''));
        $stateIdInput = trim((string)($_POST['state_id'] ?? ''));
        $postedFields = $_POST['field_ids'] ?? [];
        if (!is_array($postedFields)) {
            $postedFields = [];
        }
        $selectedFields = array_values(array_unique(array_filter(
            array_map('intval', $postedFields),
            static fn(int $id): bool => $id > 0
        )));

        if (mb_strlen($firstName) > 100) {
            $errors[] = 'First name must be 100 characters or fewer.';
        }
        if (mb_strlen($lastName) > 100) {
            $errors[] = 'Last name must be 100 characters or fewer.';
        }
        $validGradeNames = array_column($grades, 'grade_name');
        if ($gradeLevel !== '' && !in_array($gradeLevel, $validGradeNames, true)) {
            $errors[] = 'Choose a valid grade level.';
        }

        $gpa = null;
        if ($gpaInput !== '') {
            if (!is_numeric($gpaInput) || (float)$gpaInput < 0 || (float)$gpaInput > 4.00) {
                $errors[] = 'GPA must be between 0.00 and 4.00.';
            } else {
                $gpa = number_format((float)$gpaInput, 2, '.', '');
            }
        }

        $stateId = null;
        if ($stateIdInput !== '') {
            $stateId = filter_var($stateIdInput, FILTER_VALIDATE_INT);
            $validStateIds = array_map('intval', array_column($states, 'state_id'));
            if (!$stateId || !in_array((int)$stateId, $validStateIds, true)) {
                $errors[] = 'Choose a valid state.';
                $stateId = null;
            } else {
                $stateId = (int)$stateId;
            }
        }

        $validFieldIds = array_map('intval', array_column($fields, 'field_id'));
        foreach ($selectedFields as $fieldId) {
            if (!in_array($fieldId, $validFieldIds, true)) {
                $errors[] = 'One or more selected fields of study are invalid.';
                break;
            }
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                if ($profile['profile_id']) {
                    $saveProfile = $pdo->prepare(
                        'UPDATE profiles
                            SET first_name = ?, last_name = ?, grade_level = ?, gpa = ?, state_id = ?
                          WHERE profile_id = ? AND user_id = ?'
                    );
                    $saveProfile->execute([
                        $firstName !== '' ? $firstName : null,
                        $lastName !== '' ? $lastName : null,
                        $gradeLevel !== '' ? $gradeLevel : null,
                        $gpa,
                        $stateId,
                        (int)$profile['profile_id'],
                        $userId,
                    ]);
                    $profileId = (int)$profile['profile_id'];
                } else {
                    $saveProfile = $pdo->prepare(
                        'INSERT INTO profiles (user_id, first_name, last_name, grade_level, gpa, state_id)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $saveProfile->execute([
                        $userId,
                        $firstName !== '' ? $firstName : null,
                        $lastName !== '' ? $lastName : null,
                        $gradeLevel !== '' ? $gradeLevel : null,
                        $gpa,
                        $stateId,
                    ]);
                    $profileId = (int)$pdo->lastInsertId();
                }

                $pdo->prepare('DELETE FROM profile_interests WHERE profile_id = ?')
                    ->execute([$profileId]);
                if ($selectedFields) {
                    $insertInterest = $pdo->prepare(
                        'INSERT INTO profile_interests (profile_id, field_id) VALUES (?, ?)'
                    );
                    foreach ($selectedFields as $fieldId) {
                        $insertInterest->execute([$profileId, $fieldId]);
                    }
                }
                $pdo->commit();
                $_SESSION['profile_success'] = 'Student profile saved.';
                $redirect = APP_URL . '/public/profile.php';
                if ($isParent) {
                    $redirect .= '?profile_id=' . $profileId;
                }
                header('Location: ' . $redirect, true, 303);
                exit;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Profile save failed: ' . $exception->getMessage());
                $errors[] = 'The profile could not be saved. Please try again.';
            }
        }

        $profile['first_name'] = $firstName;
        $profile['last_name'] = $lastName;
        $profile['grade_level'] = $gradeLevel;
        $profile['gpa'] = $gpaInput;
        $profile['state_id'] = $stateIdInput;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="h3 mb-1"><?= $isParent ? 'Manage Student Profiles' : 'My Profile' ?></h1>
    <p class="text-muted mb-0">
      <?= $isParent
          ? 'Create and manage profiles for each student in your family.'
          : 'Manage the details used to personalize scholarship matches.' ?>
    </p>
  </div>
  <?php if (!$isParent): ?>
    <a class="btn btn-outline-primary" href="<?= e(APP_URL) ?>/public/change_password.php">
      Change password
    </a>
  <?php endif; ?>
</div>

<?php if ($success): ?>
  <div class="alert alert-success"><?= e((string)$success) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $error): ?>
        <li><?= e($error) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($isParent): ?>
  <section class="card shadow-sm mb-4" aria-labelledby="student-profiles-heading">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h2 class="h5 mb-0" id="student-profiles-heading">Student profiles</h2>
      <a class="btn btn-sm btn-primary" href="<?= e(APP_URL) ?>/public/profile.php?new=1">
        Add student
      </a>
    </div>
    <?php if ($profiles): ?>
      <div class="list-group list-group-flush">
        <?php foreach ($profiles as $savedProfile): ?>
          <?php
            $studentName = trim((string)($savedProfile['first_name'] ?? '') . ' ' . (string)($savedProfile['last_name'] ?? ''));
            if ($studentName === '') {
                $studentName = 'Student profile ' . (int)$savedProfile['profile_id'];
            }
          ?>
          <a class="list-group-item list-group-item-action<?= $selectedProfileId === (int)$savedProfile['profile_id'] ? ' active' : '' ?>"
             href="<?= e(APP_URL) ?>/public/profile.php?profile_id=<?= (int)$savedProfile['profile_id'] ?>">
            <?= e($studentName) ?>
            <?php if ($savedProfile['grade_level']): ?>
              <span class="ms-2 small"><?= e((string)$savedProfile['grade_level']) ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="card-body text-muted">No student profiles yet. Add one to get started.</div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<form method="post" action="<?= e(APP_URL) ?>/public/profile.php<?= $isParent && $selectedProfileId ? '?profile_id=' . $selectedProfileId : ($isParent ? '?new=1' : '') ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <?php if ($selectedProfileId): ?>
    <input type="hidden" name="profile_id" value="<?= $selectedProfileId ?>">
  <?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-header"><h2 class="h5 mb-0">Student information</h2></div>
    <div class="card-body">
      <div class="row g-3">
        <?php if (!$isParent): ?>
          <div class="col-md-6">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" value="<?= e((string)$user['email']) ?>" readonly>
            <div class="form-text">Contact an administrator if you need to change your email.</div>
          </div>
        <?php endif; ?>
        <div class="col-md-6">
          <label for="first_name" class="form-label">First name</label>
          <input type="text" class="form-control" id="first_name" name="first_name" maxlength="100"
                 value="<?= e((string)$profile['first_name']) ?>">
        </div>
        <div class="col-md-6">
          <label for="last_name" class="form-label">Last name</label>
          <input type="text" class="form-control" id="last_name" name="last_name" maxlength="100"
                 value="<?= e((string)$profile['last_name']) ?>">
        </div>
        <div class="col-md-6">
          <label for="grade_level" class="form-label">Grade level</label>
          <select class="form-select" id="grade_level" name="grade_level">
            <option value="">Select a grade level</option>
            <?php foreach ($grades as $grade): ?>
              <option value="<?= e((string)$grade['grade_name']) ?>"
                <?= (string)$profile['grade_level'] === (string)$grade['grade_name'] ? 'selected' : '' ?>>
                <?= e((string)$grade['grade_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label for="gpa" class="form-label">GPA</label>
          <input type="number" class="form-control" id="gpa" name="gpa" min="0" max="4" step="0.01"
                 value="<?= e((string)$profile['gpa']) ?>">
          <div class="form-text">Enter a value from 0.00 to 4.00.</div>
        </div>
        <div class="col-md-6">
          <label for="state_id" class="form-label">State</label>
          <select class="form-select" id="state_id" name="state_id">
            <option value="">Select a state</option>
            <?php foreach ($states as $state): ?>
              <option value="<?= (int)$state['state_id'] ?>"
                <?= (string)$profile['state_id'] === (string)$state['state_id'] ? 'selected' : '' ?>>
                <?= e((string)$state['state_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-header"><h2 class="h5 mb-0">Interests</h2></div>
    <div class="card-body">
      <?php if ($fields): ?>
        <div class="row g-2">
          <?php foreach ($fields as $field): ?>
            <div class="col-sm-6 col-lg-4">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="field_ids[]"
                       id="field_<?= (int)$field['field_id'] ?>" value="<?= (int)$field['field_id'] ?>"
                       <?= in_array((int)$field['field_id'], $selectedFields, true) ? 'checked' : '' ?>>
                <label class="form-check-label" for="field_<?= (int)$field['field_id'] ?>">
                  <?= e((string)$field['field_name']) ?>
                </label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="text-muted mb-0">No fields of study are available yet.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="d-flex flex-wrap justify-content-end gap-2 mb-4">
    <?php if ($selectedProfileId): ?>
      <a class="btn btn-outline-primary me-auto"
         href="<?= e(APP_URL) ?>/public/my_matches.php?profile_id=<?= $selectedProfileId ?>">View matches</a>
    <?php endif; ?>
    <?php if ($isParent && $selectedProfileId): ?>
      <button class="btn btn-outline-danger" type="submit" form="deleteProfileForm">Delete student</button>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary">Save profile</button>
  </div>
</form>

<?php if ($isParent && $selectedProfileId): ?>
  <form id="deleteProfileForm" method="post"
        action="<?= e(APP_URL) ?>/public/profile.php?profile_id=<?= $selectedProfileId ?>"
        onsubmit="return confirm('Delete this student profile? This cannot be undone.');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="profile_id" value="<?= $selectedProfileId ?>">
  </form>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
