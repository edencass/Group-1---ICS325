<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

$errors = [];
$sent   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name    = trim((string)($_POST['name'] ?? ''));
    $email   = trim((string)($_POST['email'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    if ($name === '')    $errors[] = 'Your name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($subject === '') $errors[] = 'Subject is required.';
    if ($message === '') $errors[] = 'Message is required.';

    if (!$errors) {
        $stmt = db()->prepare(
            'INSERT INTO contact_messages (user_id, name, email, subject, message)
             VALUES (NULL, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $subject, $message]);
        $sent = true;
    }
}

$page_title = 'Contact';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Contact Us</h1>
<p class="text-muted">Questions, corrections, or a scholarship we should add?
   Send us a message and we'll get back to you.</p>

<?php if ($sent): ?>
  <div class="alert alert-success">Thanks — your message was received.</div>
<?php endif; ?>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="post" class="row g-3">
  <?= csrf_field() ?>
  <div class="col-md-6">
    <label class="form-label" for="name">Your name</label>
    <input class="form-control" id="name" name="name" required maxlength="128">
  </div>
  <div class="col-md-6">
    <label class="form-label" for="email">Email</label>
    <input type="email" class="form-control" id="email" name="email" required maxlength="255">
  </div>
  <div class="col-12">
    <label class="form-label" for="subject">Subject</label>
    <input class="form-control" id="subject" name="subject" required maxlength="255">
  </div>
  <div class="col-12">
    <label class="form-label" for="message">Message</label>
    <textarea class="form-control" id="message" name="message" rows="6" required></textarea>
  </div>
  <div class="col-12">
    <button class="btn btn-primary" type="submit">Send Message</button>
  </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>