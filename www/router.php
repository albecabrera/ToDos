<?php
/**
 * PHP built-in server router.
 * Routes /api/tasks* to the API handler; everything else served normally.
 */
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('#^/api/tasks#', $uri)) {
    require $_SERVER['DOCUMENT_ROOT'] . '/api/tasks.php';
    return;
}

// Let the built-in server handle static files + index.php
return false;
