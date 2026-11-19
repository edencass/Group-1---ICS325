<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scholarship_row_cleaner.php';

$isJsonRequest =
    str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
    || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
    || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');

$respondJson = static function (array $body, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
};

$userId = current_user_id();

if ($userId === null) {
    if ($isJsonRequest) {
        $respondJson(['error' => 'Sign in is required.'], 401);
    }

    header('Location: ' . APP_URL . '/public/login.php', true, 302);
    exit;
}

$role = normalized_role(current_role());
if (!in_array($role, ['student', 'parent'], true)) {
    if ($isJsonRequest) {
        $respondJson(['error' => 'You do not have permission to access saved scholarships.'], 403);
    }

    http_response_code(403);
    exit('You do not have permission to access saved scholarships.');
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (str_contains($contentType, 'application/json')) {
        $requestBody = json_decode(
            file_get_contents('php://input') ?: '',
            true
        );

        if (!is_array($requestBody)) {
            if ($isJsonRequest) {
                $respondJson(['error' => 'Invalid JSON request.'], 400);
            }

            http_response_code(400);
            exit('Invalid request.');
        }

        $_POST = $requestBody;

        $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (is_string($csrfHeader) && $csrfHeader !== '') {
            $_POST['csrf_token'] = $csrfHeader;
        }
    }

    csrf_verify();

    $action = $_POST['action'] ?? '';
    if (!is_string($action)) {
        $action = '';
    }

    if ($action === 'save') {
        $scholarshipId = filter_var(
            $_POST['scholarship_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($scholarshipId === false || $scholarshipId < 1) {
            if ($isJsonRequest) {
                $respondJson(['error' => 'Choose a valid scholarship.'], 400);
            }

            $_SESSION['saved_error'] = 'Choose a valid scholarship.';
            header('Location: ' . APP_URL . '/public/saved_scholarships.php');
            exit;
        }

        $scholarshipCheck = $pdo->prepare(
            'SELECT scholarship_id FROM scholarships WHERE scholarship_id = ?'
        );
        $scholarshipCheck->execute([$scholarshipId]);

        if ($scholarshipCheck->fetchColumn() === false) {
            if ($isJsonRequest) {
                $respondJson(['error' => 'Scholarship not found.'], 404);
            }

            $_SESSION['saved_error'] = 'Scholarship not found.';
            header('Location: ' . APP_URL . '/public/saved_scholarships.php');
            exit;
        }

        try {
            // The owner ID comes only from the authenticated session.
            $save = $pdo->prepare(
                'INSERT IGNORE INTO saved_scholarships (user_id, scholarship_id)
                 VALUES (?, ?)'
            );
            $save->execute([$userId, $scholarshipId]);

            if ($isJsonRequest) {
                $respondJson([
                    'saved' => true,
                    'already_saved' => $save->rowCount() === 0,
                ]);
            }

            $_SESSION['saved_success'] = 'Scholarship saved.';
            header('Location: ' . APP_URL . '/public/saved_scholarships.php');
            exit;
        } catch (Throwable $exception) {
            error_log('Saving scholarship failed: ' . $exception->getMessage());

            if ($isJsonRequest) {
                $respondJson(['error' => 'The scholarship could not be saved.'], 500);
            }

            $_SESSION['saved_error'] = 'The scholarship could not be saved.';
            header('Location: ' . APP_URL . '/public/saved_scholarships.php');
            exit;
        }
    }

    if ($action === 'remove') {
        $savedId = filter_var(
            $_POST['saved_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($savedId === false || $savedId < 1) {
            if ($isJsonRequest) {
                $respondJson(['error' => 'Choose a valid saved item.'], 400);
            }

            $_SESSION['saved_error'] = 'Choose a valid saved item.';
            header('Location: ' . APP_URL . '/public/saved_scholarships.php');
            exit;
        }

        // Requiring both IDs prevents deleting another user's saved item,
        // even if its saved_id is guessed or changed in a request.
        $remove = $pdo->prepare(
            'DELETE FROM saved_scholarships
              WHERE saved_id = ?
                AND user_id = ?'
        );
        $remove->execute([$savedId, $userId]);

        if ($remove->rowCount() !== 1) {
            if ($isJsonRequest) {
                // Use the same response for missing and non-owned IDs so the
                // endpoint does not reveal whether another user's item exists.
                $respondJson(['error' => 'Saved item not found.'], 404);
            }

            $_SESSION['saved_error'] = 'Saved item not found.';
            header('Location: ' . APP_URL . '/public/saved_scholarships.php');
            exit;
        }

        if ($isJsonRequest) {
            $respondJson(['removed' => true]);
        }

        $_SESSION['saved_success'] = 'Saved scholarship removed.';
        header('Location: ' . APP_URL . '/public/saved_scholarships.php');
        exit;
    }

    if ($isJsonRequest) {
        $respondJson(['error' => 'Unsupported action.'], 400);
    }

    http_response_code(400);
    exit('Unsupported action.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');

    if ($isJsonRequest) {
        $respondJson(['error' => 'Method not allowed.'], 405);
    }

    http_response_code(405);
    exit('Method not allowed.');
}

// A user_id query parameter is deliberately ignored. The signed-in session
// determines whose saved scholarships can be returned.
$list = $pdo->prepare(
    "SELECT ss.saved_id,
            ss.saved_at,
            s.scholarship_id,
            s.title,
            s.amount,
            s.deadline,
            p.provider_name
       FROM saved_scholarships ss
       JOIN scholarships s
         ON s.scholarship_id = ss.scholarship_id
       JOIN providers p
         ON p.provider_id = s.provider_id
      WHERE ss.user_id = ?
      ORDER BY
        CASE WHEN s.deadline IS NULL THEN 1 ELSE 0 END,
        s.deadline ASC,
        s.title ASC"
);
$list->execute([$userId]);
$rows = $list->fetchAll(PDO::FETCH_ASSOC);

if ($isJsonRequest) {
    $respondJson(['saved_scholarships' => $rows]);
}

$page_title = 'My Saved Scholarships';
$success = $_SESSION['saved_success'] ?? null;
$error = $_SESSION['saved_error'] ?? null;
unset($_SESSION['saved_success'], $_SESSION['saved_error']);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h3 mb-1">My Saved Scholarships</h1>
  <p class="text-muted mb-0">Scholarships saved to your account.</p>
</div>

<?php if ($success): ?>
  <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<?php if (!$rows): ?>
  <div class="alert alert-info">
    You have not saved any scholarships yet.
  </div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
      <thead>
        <tr>
          <th>Scholarship</th>
          <th>Provider</th>
          <th>Award</th>
          <th>Deadline</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= e($row['title']) ?></td>
            <td><?= e($row['provider_name']) ?></td>
            <td>
              <?= $row['amount'] !== null
                ? '$' . number_format((float)$row['amount'])
                : 'Varies' ?>
            </td>
            <td>
              <?= $row['deadline']
                ? e(format_date_us((string)$row['deadline']))
                : 'Rolling' ?>
            </td>
            <td>
              <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="saved_id" value="<?= (int)$row['saved_id'] ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit">
                  Remove
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>