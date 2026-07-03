<?php
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($uri === '/manifest.webmanifest') {
    header('Content-Type: application/manifest+json; charset=utf-8');
    readfile($_SERVER['DOCUMENT_ROOT'] . '/manifest.webmanifest');
    return;
}

if (preg_match('#^/detail#', $uri)) {
    require $_SERVER['DOCUMENT_ROOT'] . '/detail.php';
    return;
}

if (preg_match('#^/overview#', $uri)) {
    require $_SERVER['DOCUMENT_ROOT'] . '/overview.php';
    return;
}

if (preg_match('#^/api/lists#', $uri)) {
    require $_SERVER['DOCUMENT_ROOT'] . '/api/lists.php';
    return;
}

if (preg_match('#^/api/#', $uri)) {
    require $_SERVER['DOCUMENT_ROOT'] . '/api/tasks.php';
    return;
}

return false;
