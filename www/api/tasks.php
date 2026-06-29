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
    $home    = getenv('HOME') ?: posix_getpwuid(posix_getuid())['dir'];
    $dataDir = $home . '/Library/Application Support/MenuBarTasks';
    if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);

    $pdo = new PDO('sqlite:' . $dataDir . '/tasks.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tasks (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            title      TEXT    NOT NULL,
            done       INTEGER NOT NULL DEFAULT 0,
            priority   INTEGER NOT NULL DEFAULT 2,
            position   INTEGER NOT NULL DEFAULT 0,
            due_date   TEXT    NULL,
            created_at TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now')),
            updated_at TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now'))
        )
    ");

    // Safe migrations for existing DBs (idempotent)
    foreach (['position INTEGER NOT NULL DEFAULT 0', 'due_date TEXT NULL'] as $col) {
        try { $pdo->exec("ALTER TABLE tasks ADD COLUMN $col"); } catch (PDOException) {}
    }

    return $pdo;
}

// ── Routing ───────────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Special: reorder endpoint
if ($method === 'POST' && preg_match('#/api/tasks/reorder#', $uri)) {
    $pdo  = db();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $ids  = array_values(array_map('intval', $body['ids'] ?? []));

    $stmt = $pdo->prepare('UPDATE tasks SET position = ?, updated_at = strftime(\'%Y-%m-%dT%H:%M:%SZ\',\'now\') WHERE id = ?');
    foreach ($ids as $pos => $id) {
        $stmt->execute([$pos, $id]);
    }
    echo json_encode(['ok' => true, 'count' => count($ids)]);
    exit;
}

$id = null;
if (preg_match('#/api/tasks/(\d+)#', $uri, $m)) {
    $id = (int) $m[1];
}

$pdo = db();

switch ($method) {

    // GET /api/tasks — sorted: pending by position, then done
    case 'GET':
        $rows = $pdo
            ->query('SELECT * FROM tasks ORDER BY done ASC, position ASC, created_at ASC')
            ->fetchAll();
        echo json_encode(array_values($rows));
        break;

    // POST /api/tasks
    case 'POST':
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $title    = trim($body['title'] ?? '');
        $prio     = max(1, min(3, (int)($body['priority'] ?? 2)));
        $dueDate  = isset($body['due_date']) && $body['due_date'] !== '' ? $body['due_date'] : null;

        if ($title === '') {
            http_response_code(400);
            echo json_encode(['error' => 'title required']);
            break;
        }

        // Append to end of pending list
        $maxPos = (int) $pdo->query('SELECT COALESCE(MAX(position),0) FROM tasks WHERE done=0')->fetchColumn();

        $stmt = $pdo->prepare('INSERT INTO tasks (title, priority, position, due_date) VALUES (?,?,?,?)');
        $stmt->execute([$title, $prio, $maxPos + 1, $dueDate]);
        $newId = (int) $pdo->lastInsertId();
        $task  = $pdo->query("SELECT * FROM tasks WHERE id=$newId")->fetch();

        http_response_code(201);
        echo json_encode($task);
        break;

    // PUT /api/tasks/:id
    case 'PUT':
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'id required']); break; }

        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $fields = [];
        $params = [];

        if (array_key_exists('done', $body)) {
            $fields[] = 'done = ?';
            $params[]  = (int)(bool)$body['done'];
        }
        if (array_key_exists('title', $body)) {
            $t = trim($body['title']);
            if ($t !== '') { $fields[] = 'title = ?'; $params[] = $t; }
        }
        if (array_key_exists('priority', $body)) {
            $fields[] = 'priority = ?';
            $params[]  = max(1, min(3, (int)$body['priority']));
        }
        if (array_key_exists('due_date', $body)) {
            $fields[] = 'due_date = ?';
            $params[]  = $body['due_date'] !== '' ? $body['due_date'] : null;
        }

        if (empty($fields)) { http_response_code(400); echo json_encode(['error' => 'nothing to update']); break; }

        $fields[] = "updated_at = strftime('%Y-%m-%dT%H:%M:%SZ','now')";
        $params[]  = $id;

        $pdo->prepare('UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
        echo json_encode($pdo->query("SELECT * FROM tasks WHERE id=$id")->fetch() ?: ['error' => 'not found']);
        break;

    // DELETE /api/tasks/:id
    case 'DELETE':
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'id required']); break; }
        $pdo->prepare('DELETE FROM tasks WHERE id = ?')->execute([$id]);
        http_response_code(204);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'method not allowed']);
}
