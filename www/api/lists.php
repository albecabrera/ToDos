<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

function db(): PDO
{
    $home    = getenv('HOME') ?: posix_getpwuid(posix_getuid())['dir'];
    $dataDir = $home . '/Library/Application Support/MenuBarTasks';
    if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);

    $pdo = new PDO('sqlite:' . $dataDir . '/tasks.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lists (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT    NOT NULL,
            color      TEXT    NOT NULL DEFAULT '#007AFF',
            position   INTEGER NOT NULL DEFAULT 0,
            created_at TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now'))
        )
    ");

    return $pdo;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$id     = null;
if (preg_match('#^/api/lists/(\d+)#', $uri, $m)) $id = (int)$m[1];

$pdo = db();

switch ($method) {

    case 'GET':
        $rows = $pdo->query('SELECT * FROM lists ORDER BY position ASC, id ASC')->fetchAll();
        echo json_encode(array_values($rows));
        break;

    case 'POST':
        $b    = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim($b['name'] ?? '');
        if (!$name) { http_response_code(400); echo json_encode(['error' => 'name required']); break; }
        $color  = $b['color'] ?? '#007AFF';
        $maxPos = (int) $pdo->query('SELECT COALESCE(MAX(position)+1,0) FROM lists')->fetchColumn();
        $pdo->prepare('INSERT INTO lists (name, color, position) VALUES (?,?,?)')->execute([$name, $color, $maxPos]);
        $list = $pdo->query("SELECT * FROM lists WHERE id={$pdo->lastInsertId()}")->fetch();
        http_response_code(201);
        echo json_encode($list);
        break;

    case 'PUT':
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'id required']); break; }
        $b      = json_decode(file_get_contents('php://input'), true) ?? [];
        $fields = []; $params = [];
        if (isset($b['name'])  && trim($b['name']))  { $fields[] = 'name=?';  $params[] = trim($b['name']); }
        if (isset($b['color']) && $b['color'])        { $fields[] = 'color=?'; $params[] = $b['color']; }
        if (!$fields) { http_response_code(400); echo json_encode(['error' => 'nothing to update']); break; }
        $params[] = $id;
        $pdo->prepare('UPDATE lists SET '.implode(',',$fields).' WHERE id=?')->execute($params);
        echo json_encode($pdo->query("SELECT * FROM lists WHERE id=$id")->fetch());
        break;

    case 'DELETE':
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'id required']); break; }
        $pdo->prepare('UPDATE tasks SET list_id=NULL WHERE list_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM lists WHERE id=?')->execute([$id]);
        http_response_code(204);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'method not allowed']);
}
