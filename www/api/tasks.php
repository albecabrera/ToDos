<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Database ──────────────────────────────────────────────

function db(): PDO
{
    // Store outside the app bundle so data survives rebuilds
    $home    = getenv('HOME') ?: posix_getpwuid(posix_getuid())['dir'];
    $dataDir = $home . '/Library/Application Support/MenuBarTasks';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . $dataDir . '/tasks.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tasks (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            title      TEXT    NOT NULL,
            done       INTEGER NOT NULL DEFAULT 0,
            priority   INTEGER NOT NULL DEFAULT 2,
            created_at TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now')),
            updated_at TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now'))
        )
    ");

    return $pdo;
}

// ── Routing ───────────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$id     = null;

if (preg_match('#/api/tasks/(\d+)#', $uri, $m)) {
    $id = (int) $m[1];
}

$pdo = db();

switch ($method) {
    // GET /api/tasks — list all, sorted: pending first, then by priority desc
    case 'GET':
        $rows = $pdo
            ->query('SELECT * FROM tasks ORDER BY done ASC, priority DESC, created_at ASC')
            ->fetchAll();
        echo json_encode(array_values($rows));
        break;

    // POST /api/tasks — create
    case 'POST':
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $title = trim($body['title'] ?? '');
        $prio  = max(1, min(3, (int)($body['priority'] ?? 2)));

        if ($title === '') {
            http_response_code(400);
            echo json_encode(['error' => 'title is required']);
            break;
        }

        $stmt = $pdo->prepare('INSERT INTO tasks (title, priority) VALUES (?, ?)');
        $stmt->execute([$title, $prio]);
        $newId = (int) $pdo->lastInsertId();
        $task  = $pdo->query("SELECT * FROM tasks WHERE id = $newId")->fetch();

        http_response_code(201);
        echo json_encode($task);
        break;

    // PUT /api/tasks/:id — update
    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'id required']);
            break;
        }

        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $fields = [];
        $params = [];

        if (array_key_exists('done', $body)) {
            $fields[] = 'done = ?';
            $params[]  = (int)(bool)$body['done'];
        }
        if (array_key_exists('title', $body)) {
            $title = trim($body['title']);
            if ($title !== '') {
                $fields[] = 'title = ?';
                $params[]  = $title;
            }
        }
        if (array_key_exists('priority', $body)) {
            $fields[] = 'priority = ?';
            $params[]  = max(1, min(3, (int)$body['priority']));
        }

        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'nothing to update']);
            break;
        }

        $fields[] = "updated_at = strftime('%Y-%m-%dT%H:%M:%SZ','now')";
        $params[]  = $id;

        $pdo->prepare('UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = ?')
            ->execute($params);

        $task = $pdo->query("SELECT * FROM tasks WHERE id = $id")->fetch();
        echo json_encode($task ?: ['error' => 'not found']);
        break;

    // DELETE /api/tasks/:id
    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'id required']);
            break;
        }

        $pdo->prepare('DELETE FROM tasks WHERE id = ?')->execute([$id]);
        http_response_code(204);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'method not allowed']);
}
