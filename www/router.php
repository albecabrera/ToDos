<?php
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('#^/api/#', $uri)) {
    require $_SERVER['DOCUMENT_ROOT'] . '/api/tasks.php';
    return;
}

return false;
