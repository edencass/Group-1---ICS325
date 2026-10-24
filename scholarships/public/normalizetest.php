<?php
require_once __DIR__ . '/../includes/url_normalizer.php';
header('Content-Type: text/plain');
var_dump(normalize_url('HTTPS://www.Example.org/Scholarship/?utm_source=x'));
var_dump(normalize_url('https://example.org/stem/'));
var_dump(normalize_url('ftp://example.org/x'));