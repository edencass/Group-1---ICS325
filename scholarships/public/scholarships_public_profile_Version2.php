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

$students = [];
$studentsById = [];
$profiles = [];
$profileById = [];

if ($isParent) {
    $studentsStmt = $pdo->prepare(
        "SELECT u.user_id AS student_user_id,
                u.email,
                psr.relationship_type,
                p.profile_id,
                p.first_name,
                p.last_name,
                p.grade_level,
                p.gpa,
                p.state_id
           FROM parent_student_relationships psr
           JOIN users u
             ON u.user_id = psr.student_user_id
           JOIN roles r
             ON r.role_id = u.role_id
           LEFT JOIN profiles p
             ON p.profile_id = (
                 SELECT MIN(p2.profile_id)
                   FROM profiles p2
                  WHERE p2.user_id = u.user_id
             )
          WHERE psr.parent_user_id = ?
            AND u.is_active = 1
            AND r.role_name = 'Student'
          ORDER BY p.first_name, p.last_name, u.email"
    );
    $studentsStmt->execute([$userId]);
    $students = $studentsStmt->fetchAll();
    foreach ($students as $student) {
        $studentsById[(int)$student['student_user_id']] = $student;
    }
} else {
    $profilesStmt = $pdo->prepare(
        'SELECT profile_id, user_id, first_name, last_name, grade_level, gpa, state_id
           FROM profiles
          WHERE user_id = ?
          ORDER BY profile_id'
    );
    $profilesStmt->execute([$userId]);
    $profiles = $profilesStmt->fetchAll();
    foreach ($profiles as $savedProfile) {
        $profileById[(int)$savedProfile['profile_id']] = $savedProfile;
    }
}

$requestedProfileId = filter_var($_GET['profile_id'] ?? null, FILTER_VALIDATE_INT);
$selectedProfileId = $requestedProfileId !== false && $requestedProfileId !== null
    ? (int)$requestedProfileId
    : null;
$requestedStudentUserId = filter_var($_GET['student_user_id'] ?? null, FILTER_VALIDATE_INT);
$selectedStudentUserId = $requestedStudentUserId !== false && $requestedStudentUserId !== null
    ? (int)$requestedStudentUserId
    : null;
$profile = null;
$errors = [];

if ($isParent && $selectedStudentUserId !== null) {
    $profile = $studentsById[$selectedStudentUserId] ?? null;
    if (!$profile) {
        http_response_code(404);
        exit('Linked student account not found.');
    }
    $selectedProfileId = $profile['profile_id'] !== null
        ? (int)$profile['profile_id']
        : null;
} elseif ($isParent && $students) {
    $profile = $students[0];
    $selectedStudentUserId = (int)$profile['student_user_id'];
    $selectedProfileId = $profile['profile_id'] !== null
        ? (int)$profile['profile_id']
        : null;
} elseif (!$isParent && $profiles) {
    $profile = $profiles[0];
    $selectedProfileId = (int)$profile['profile_id'];
}

if ($profile === null) {
    $profile = [
        'user_id' => $userId,
        'student_user_id' => null,
        'email' => '',
        'relationship_type' => '',
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
    $postedStudentUserId = filter_var($_POST['student_user_id'] ?? null, FILTER_VALIDATE_INT);
    $postedStudentUserId = $postedStudentUserId !== false && $postedStudentUserId !== null
        ? (int)$postedStudentUserId
        : null;

    if ($isParent && $action === 'create_student') {
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $studentEmail = strtolower(trim((string)($_POST['student_email'] ?? '')));
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $passwordConfirmation = is_string($_POST['password_confirmation'] ?? null)
            ? $_POST['password_confirmation']
            : '';
        $relationshipType = is_string($_POST['relationship_type'] ?? null)
            ? $_POST['relationship_type']
            : '';

        if (!preg_match('/\A.{1,100}\z/us', $firstName)) {
            $errors[] = 'First name is required and must be 100 characters or fewer.';
        }
        if (!preg_match('/\A.{1,100}\z/us', $lastName)) {
            $errors[] = 'Last name is required and must be 100 characters or fewer.';
        }
        if (strlen($studentEmail) > 255 || !filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid student email address (255 characters or fewer).';
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            $errors[] = 'Student password must be between 8 and 72 bytes.';
        }
        if ($password !== $passwordConfirmation) {
            $errors[] = 'Student password confirmation does not match.';
        }
        if (!in_array($relationshipType, ['Parent', 'Guardian', 'Grandparent', 'Other'], true)) {
            $errors[] = 'Choose a valid relationship to the student.';
        }

        if (!$errors) {
            try {
                $roleQuery = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = 'Student'");
                $roleQuery->execute();
                $studentRoleId = $roleQuery->fetchColumn();
                if ($studentRoleId === false) {
                    throw new RuntimeException('The Student role must exist in the roles table.');
                }

                $pdo->beginTransaction();
                $createStudent = $pdo->prepare(
                    'INSERT INTO users (role_id, email, password_hash) VALUES (?, ?, ?)'
                );
                $createStudent->execute([
                    (int)$studentRoleId,
                    $studentEmail,
                    password_hash($password, PASSWORD_DEFAULT),
                ]);
                $studentUserId = (int)$pdo->lastInsertId();

                $createProfile = $pdo->prepare(
                    'INSERT INTO profiles (user_id, first_name, last_name) VALUES (?, ?, ?)'
                );
                $createProfile->execute([$studentUserId, $firstName, $lastName]);

                $linkStudent = $pdo->prepare(
                    'INSERT INTO parent_student_relationships
                        (parent_user_id, student_user_id, relationship_type)
                     VALUES (?, ?, ?)'
                );
                $linkStudent->execute([$userId, $studentUserId, $relationshipType]);
                $pdo->commit();

                $_SESSION['profile_success'] = 'Student account created and linked to your parent account.';
                header(
                    'Location: ' . APP_URL . '/public/profile.php?student_user_id=' . $studentUserId,
                    true,
                    303
                );
                exit;
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if (($exception->errorInfo[1] ?? null) === 1062) {
                    $errors[] = 'An account with that email address already exists.';
                } else {
                    throw $exception;
                }
            }
        }
    } elseif ($isParent && $action === 'link_student') {
        $studentEmail = strtolower(trim((string)($_POST['student_email'] ?? '')));
        $relationshipType = is_string($_POST['relationship_type'] ?? null)
            ? $_POST['relationship_type']
            : '';
        if (strlen($studentEmail) > 255 || !filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid student account email address.';
        }
        if (!in_array($relationshipType, ['Parent', 'Guardian', 'Grandparent', 'Other'], true)) {
            $errors[] = 'Choose a valid relationship to the student.';
        }

        if (!$errors) {
            $studentAccount = $pdo->prepare(
                "SELECT u.user_id
                   FROM users u
                   JOIN roles r ON r.role_id = u.role_id
                  WHERE u.email = ?
                    AND u.is_active = 1
                    AND r.role_name = 'Student'"
            );
            $studentAccount->execute([$studentEmail]);
            $studentUserId = $studentAccount->fetchColumn();
            if ($studentUserId === false) {
                $errors[] = 'No active student account with that email address was found.';
            } else {
                try {
                    $linkStudent = $pdo->prepare(
                        'INSERT INTO parent_student_relationships
                            (parent_user_id, student_user_id, relationship_type)
                         VALUES (?, ?, ?)'
                    );
                    $linkStudent->execute([$userId, (int)$studentUserId, $relationshipType]);
                    $_SESSION['profile_success'] = 'Student account linked to your parent account.';
                    header(
                        'Location: ' . APP_URL . '/public/profile.php?student_user_id=' . (int)$studentUserId,
                        true,
                        303
                    );
                    exit;
                } catch (PDOException $exception) {
                    if (($exception->errorInfo[1] ?? null) === 1062) {
                        $errors[] = 'That student account is already linked to your parent account.';
                    } else {
                        throw $exception;
                    }
                }
            }
        }
    } elseif ($isParent && $action === 'unlink_student') {
        if ($postedStudentUserId === null || !isset($studentsById[$postedStudentUserId])) {
            http_response_code(403);
            exit('You do not have permission to unlink this student account.');
        }

        $unlinkStudent = $pdo->prepare(
            'DELETE FROM parent_student_relationships
              WHERE parent_user_id = ? AND student_user_id = ?'
        );
        $unlinkStudent->execute([$userId, $postedStudentUserId]);
        if ($unlinkStudent->rowCount() !== 1) {
            throw new RuntimeException('The student account relationship was not removed.');
        }
        $_SESSION['profile_success'] = 'Student account unlinked. Their account and profile were not deleted.';
        header('Location: ' . APP_URL . '/public/profile.php', true, 303);
        exit;
    } elseif ($action === 'delete') {
        http_response_code(403);
        exit('Student profiles cannot be deleted from a parent account. Unlink the student instead.');
    } elseif (!in_array($action, ['save'], true)) {
        $errors[] = 'Choose a valid profile action.';
    }

    if ($action === 'save') {
        if ($isParent) {
            $profile = $postedStudentUserId !== null
                ? ($studentsById[$postedStudentUserId] ?? null)
                : null;
            if (!$profile) {
                $errors[] = 'Choose a student account linked to your parent account.';
            } elseif (
                $postedProfileId !== null
                && (int)($profile['profile_id'] ?? 0) !== $postedProfileId
            ) {
                $errors[] = 'That student profile could not be found.';
            } else {
                $selectedStudentUserId = (int)$profile['student_user_id'];
            }
        } else {
            $profile = $profiles[0] ?? [
                'user_id' => $userId,
                'profile_id' => null,
                'first_name' => '',
                'last_name' => '',
                'grade_level' => '',
                'gpa' => '',
                'state_id' => null,
            ];
        }
        $profile ??= [
            'user_id' => $userId,
            'student_user_id' => null,
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
                        $isParent ? (int)$profile['student_user_id'] : $userId,
                    ]);
                    $profileId = (int)$profile['profile_id'];
                } else {
                    $saveProfile = $pdo->prepare(
                        'INSERT INTO profiles (user_id, first_name, last_name, grade_level, gpa, state_id)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $saveProfile->execute([
                        $isParent ? (int)$profile['student_user_id'] : $userId,
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
                    $redirect .= '?student_user_id=' . (int)$profile['student_user_id'];
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
          ? 'Create student accounts or link existing accounts, then manage their scholarship profiles.'
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
    <div class="card-header">
      <h2 class="h5 mb-0" id="student-profiles-heading">Students you manage</h2>
    </div>
    <?php if ($students): ?>
      <div class="list-group list-group-flush">
        <?php foreach ($students as $student): ?>
          <?php
            $studentUserId = (int)$student['student_user_id'];
            $studentName = trim((string)($student['first_name'] ?? '') . ' ' . (string)($student['last_name'] ?? ''));
            if ($studentName === '') {
                $studentName = (string)$student['email'];
            }
          ?>
          <div class="list-group-item d-flex flex-wrap align-items-center gap-2">
            <a class="flex-grow-1 text-decoration-none<?= $selectedStudentUserId === $studentUserId ? ' fw-bold' : '' ?>"
               href="<?= e(APP_URL) ?>/public/profile.php?student_user_id=<?= $studentUserId ?>">
              <?= e($studentName) ?>
              <span class="ms-2 small text-muted">
                <?= e((string)$student['relationship_type']) ?> · <?= e((string)$student['email']) ?>
              </span>
              <?php if ($student['grade_level']): ?>
                <span class="ms-2 small text-muted"><?= e((string)$student['grade_level']) ?></span>
              <?php endif; ?>
              <?php if ($student['profile_id'] === null): ?>
                <span class="badge text-bg-warning ms-2">Profile needed</span>
              <?php endif; ?>
            </a>
            <form method="post" action="<?= e(APP_URL) ?>/public/profile.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="unlink_student">
              <input type="hidden" name="student_user_id" value="<?= $studentUserId ?>">
              <button class="btn btn-sm btn-outline-danger" type="submit"
                      onclick="return confirm('Unlink this student? Their account and profile will remain intact.');">
                Unlink
              </button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="card-body text-muted">No student accounts are linked yet.</div>
    <?php endif; ?>
    <div class="card-body border-top">
      <h3 class="h5">Create a student account</h3>
      <p class="text-muted">Create a separate sign-in for a student you are responsible for. They can sign in with the email and password you set here.</p>
      <form method="post" action="<?= e(APP_URL) ?>/public/profile.php" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_student">
        <div class="col-md-6">
          <label class="form-label" for="new_student_first_name">Student first name</label>
          <input class="form-control" id="new_student_first_name" name="first_name"
                 required maxlength="100" autocomplete="given-name">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="new_student_last_name">Student last name</label>
          <input class="form-control" id="new_student_last_name" name="last_name"
                 required maxlength="100" autocomplete="family-name">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="new_student_email">Student email</label>
          <input class="form-control" type="email" id="new_student_email" name="student_email"
                 required maxlength="255" autocomplete="email">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="new_student_relationship">Your relationship</label>
          <select class="form-select" id="new_student_relationship" name="relationship_type" required>
            <option value="Parent">Parent</option>
            <option value="Guardian">Guardian</option>
            <option value="Grandparent">Grandparent</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="new_student_password">Student password</label>
          <input class="form-control" type="password" id="new_student_password" name="password"
                 required minlength="8" maxlength="72" autocomplete="new-password">
          <div class="form-text">Use 8–72 bytes. Share the password securely with the student.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="new_student_password_confirmation">Confirm student password</label>
          <input class="form-control" type="password" id="new_student_password_confirmation"
                 name="password_confirmation" required minlength="8" maxlength="72"
                 autocomplete="new-password">
        </div>
        <div class="col-12">
          <button class="btn btn-primary" type="submit">Create student account</button>
        </div>
      </form>
    </div>
    <div class="card-body border-top">
      <h3 class="h6">Link an existing student account</h3>
      <form method="post" action="<?= e(APP_URL) ?>/public/profile.php" class="row g-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="link_student">
        <div class="col-md-6">
          <label class="form-label" for="existing_student_email">Student account email</label>
          <input class="form-control" type="email" id="existing_student_email" name="student_email"
                 required maxlength="255" autocomplete="email">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="existing_student_relationship">Your relationship</label>
          <select class="form-select" id="existing_student_relationship" name="relationship_type" required>
            <option value="Parent">Parent</option>
            <option value="Guardian">Guardian</option>
            <option value="Grandparent">Grandparent</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-outline-primary" type="submit">Link account</button>
        </div>
      </form>
    </div>
  </section>
<?php endif; ?>

<?php if (!$isParent || $selectedStudentUserId !== null): ?>
<form method="post" action="<?= e(APP_URL) ?>/public/profile.php<?= $isParent ? '?student_user_id=' . $selectedStudentUserId : '' ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <?php if ($isParent): ?>
    <input type="hidden" name="student_user_id" value="<?= $selectedStudentUserId ?>">
  <?php endif; ?>
  <?php if ($selectedProfileId): ?>
    <input type="hidden" name="profile_id" value="<?= $selectedProfileId ?>">
  <?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-header"><h2 class="h5 mb-0">Student information</h2></div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label for="email" class="form-label">Email</label>
          <input type="email" class="form-control" id="email"
                 value="<?= e($isParent ? (string)$profile['email'] : (string)$user['email']) ?>" readonly>
          <div class="form-text">
            <?= $isParent
                ? 'Student account email is managed by the student.'
                : 'Contact an administrator if you need to change your email.' ?>
          </div>
        </div>
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
    <button type="submit" class="btn btn-primary">Save profile</button>
  </div>
</form>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
