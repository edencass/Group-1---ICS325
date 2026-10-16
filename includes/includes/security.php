```php
<?php

session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

$success = '';
$error = '';

$name = '';
$email = '';
$subject = '';
$message = '';

// Process the form when the user submits it.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Check the CSRF token.
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {

        $error = 'Invalid request. Please refresh the page and try again.';

    } else {

        // Read and clean the form values.
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        // Validate the form on the server.
        if (
            $name === '' ||
            $email === '' ||
            $subject === '' ||
            $message === ''
        ) {
            $error = 'Please fill in all fields.';

        } elseif (
            strlen($name) > 100 ||
            strlen($email) > 255 ||
            strlen($subject) > 150
        ) {
            $error = 'One or more fields are too long.';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error = 'Please enter a valid email address.';

        } elseif (strlen($message) > 10000) {

            $error = 'Your message is too long.';

        } else {

            try {

                // Save the message using a PDO prepared statement.
                $sql = "INSERT INTO contact_messages
                        (name, email, subject, message)
                        VALUES (:name, :email, :subject, :message)";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':subject' => $subject,
                    ':message' => $message
                ]);

                $success = 'Your message was saved successfully.';

                // Clear the form.
                $name = '';
                $email = '';
                $subject = '';
                $message = '';

                // Create a new CSRF token.
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            } catch (PDOException $e) {

                // Keep database error details out of the webpage.
                error_log($e->getMessage());

                $error = 'We could not save your message. Please try again.';
            }
        }
    }
}

// Create a token if one does not exist.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Contact - Scholarship Finder</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body>

<nav class="navbar navbar-expand-lg bg-dark navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="index.html">Scholarship Finder</a>

        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="index.html">Home</a>
            <a class="nav-link" href="scholarships.php">Scholarships</a>
            <a class="nav-link" href="about.php">About</a>
            <a class="nav-link" href="faq.php">FAQ</a>
            <a class="nav-link active" href="contact.php">Contact</a>
        </div>
    </div>
</nav>

<header class="bg-light py-5">
    <div class="container text-center">
        <h1>Contact Us</h1>
        <p class="lead">Have a question? Send us a message.</p>
    </div>
</header>

<main class="container py-5">

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">

            <?php if ($success !== ''): ?>
                <div class="alert alert-success" role="alert">
                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="contact.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
                >

                <div class="mb-3">
                    <label for="name" class="form-label">Your Name</label>

                    <input
                        type="text"
                        class="form-control"
                        id="name"
                        name="name"
                        maxlength="100"
                        required
                        value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>

                    <input
                        type="email"
                        class="form-control"
                        id="email"
                        name="email"
                        maxlength="255"
                        required
                        value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div class="mb-3">
                    <label for="subject" class="form-label">Subject</label>

                    <input
                        type="text"
                        class="form-control"
                        id="subject"
                        name="subject"
                        maxlength="150"
                        required
                        value="<?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div class="mb-3">
                    <label for="message" class="form-label">Your Message</label>

                    <textarea
                        class="form-control"
                        id="message"
                        name="message"
                        rows="5"
                        maxlength="10000"
                        required
                    ><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    Send Message
                </button>

            </form>

        </div>
    </div>

</main>

<footer class="bg-dark text-white text-center py-3">
    <p class="mb-0">Scholarship Finder</p>
</footer>

</body>
</html>
```
