<?php
$page_title = 'About';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>About <?= e(APP_NAME) ?></h1>

<h2>Our purpose</h2>
<p>Pathways to Scholarships helps middle and high school students and their
   parents find scholarships based on field of study, grade level, and state.</p>

<h2>What we do</h2>
<p>We aggregate scholarship opportunities from providers across the country,
   clean and de-duplicate them, and let families search and save the ones
   that match their profile.</p>

<h2>Where the data comes from</h2>
<p>Scholarship records are collected by the Data Science class of the Pathways
   to Scholarships project and verified against each provider's official page.
   Always confirm details at the provider's official URL before applying.</p>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>