<?php
$page_title = 'FAQ';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Frequently Asked Questions</h1>

<h2>What is this site?</h2>
<p>A free search tool that helps students and parents find scholarships for
   middle and high school students in the United States.</p>

<h2>How do I find scholarships?</h2>
<p>Use the search on the <a href="<?= e(APP_URL) ?>/public/index.php">home page</a>
   or go to the <a href="<?= e(APP_URL) ?>/public/search.php">search page</a>.
   Filter by state, grade level, and field of study.</p>

<h2>Can everyone apply to every scholarship?</h2>
<p>No. Each scholarship has its own eligibility rules. Read the requirements
   on the detail page and on the provider's official site before applying.</p>

<h2>Does this site give out scholarships?</h2>
<p>No. We only help you find them. The providers decide who receives awards.</p>

<h2>How do I contact the team?</h2>
<p>Use the <a href="<?= e(APP_URL) ?>/public/contact.php">contact form</a>.</p>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>