<?php
// Installed beside backend/bootstrap/app.php by the release packager.
// Runtime secrets live outside public_html and outside the Git repository.
$runtime = dirname(__DIR__, 3).'/buziness-runtime.php';
$initial = dirname(__DIR__).'/deploy-config.php';
if (!is_file($runtime) && is_file($initial)) {
    if (!rename($initial, $runtime)) { http_response_code(503); exit('Runtime configuration could not be secured.'); }
    chmod($runtime, 0600);
}
if (!is_file($runtime)) { http_response_code(503); exit('Runtime configuration missing.'); }
foreach (require $runtime as $key => $value) {
    putenv($key.'='.$value); $_ENV[$key]=$value; $_SERVER[$key]=$value;
}
