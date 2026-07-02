<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── DB ────────────────────────────────────────────────────

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

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tasks (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            title      TEXT    NOT NULL,
            done       INTEGER NOT NULL DEFAULT 0,
            priority   INTEGER NOT NULL DEFAULT 2,
            position   INTEGER NOT NULL DEFAULT 0,
            due_date   TEXT    NULL,
            notes      TEXT    NULL,
            subtasks   TEXT    NOT NULL DEFAULT '[]',
            list_id    INTEGER NULL REFERENCES lists(id),
            created_at TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now')),
            updated_at TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now'))
        )
    ");

    foreach ([
        'position   INTEGER NOT NULL DEFAULT 0',
        'due_date   TEXT NULL',
        'due_time   TEXT NULL',
        'notes      TEXT NULL',
        "subtasks   TEXT NOT NULL DEFAULT '[]'",
        'list_id    INTEGER NULL',
        'remind_min INTEGER NULL',
        'reminded   INTEGER NOT NULL DEFAULT 0',
    ] as $col) {
        try { $pdo->exec("ALTER TABLE tasks ADD COLUMN $col"); } catch (PDOException) {}
    }

    return $pdo;
}

function formatTask(array $row): array
{
    $row['done']       = (int) $row['done'];
    $row['priority']   = (int) $row['priority'];
    $row['position']   = (int) $row['position'];
    $row['list_id']    = $row['list_id']    !== null ? (int)$row['list_id']    : null;
    $row['remind_min'] = $row['remind_min'] !== null ? (int)$row['remind_min'] : null;
    $row['reminded']   = (int)($row['reminded'] ?? 0);
    $row['subtasks']   = json_decode($row['subtasks'] ?? '[]', true) ?: [];
    $row['due_time']   = $row['due_time'] ?? null;
    return $row;
}

// ── Routing ───────────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ── GET /api/stats ────────────────────────────────────────
if ($method === 'GET' && preg_match('#^/api/stats#', $uri)) {
    $pdo = db();

    $days = $pdo->query("
        SELECT DATE(updated_at) as d FROM tasks
        WHERE done=1 GROUP BY d ORDER BY d DESC
    ")->fetchAll(PDO::FETCH_COLUMN);

    $streak = 0;
    if ($days) {
        $today     = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $start     = in_array($today, $days) ? $today : (in_array($yesterday, $days) ? $yesterday : null);
        if ($start) {
            $cur = new DateTime($start);
            foreach ($days as $d) {
                if ($d === $cur->format('Y-m-d')) { $streak++; $cur->modify('-1 day'); }
                else break;
            }
        }
    }

    $week    = (int) $pdo->query("SELECT COUNT(*) FROM tasks WHERE done=1 AND updated_at >= datetime('now','-7 days')")->fetchColumn();
    $total   = (int) $pdo->query("SELECT COUNT(*) FROM tasks WHERE done=1")->fetchColumn();
    $pending = (int) $pdo->query("SELECT COUNT(*) FROM tasks WHERE done=0")->fetchColumn();
    $overdue = (int) $pdo->query("SELECT COUNT(*) FROM tasks WHERE done=0 AND due_date IS NOT NULL AND due_date < date('now')")->fetchColumn();

    echo json_encode(compact('streak', 'week', 'total', 'pending', 'overdue'));
    exit;
}

// ── DELETE /api/tasks/done — limpiar completadas ──────────
if ($method === 'DELETE' && preg_match('#^/api/tasks/done#', $uri)) {
    $pdo     = db();
    $deleted = $pdo->exec("DELETE FROM tasks WHERE done=1");
    echo json_encode(['deleted' => $deleted]);
    exit;
}

// ── GET /api/tasks/reminders ─────────────────────────────
if ($method === 'GET' && preg_match('#^/api/tasks/reminders#', $uri)) {
    $pdo  = db();
    $rows = $pdo->query("
        SELECT * FROM tasks
        WHERE done        = 0
          AND reminded    = 0
          AND remind_min  IS NOT NULL
          AND due_date    IS NOT NULL
          AND due_time    IS NOT NULL
          AND datetime(due_date || ' ' || due_time)
              <= datetime('now', 'localtime', '+' || remind_min || ' minutes')
          AND datetime(due_date || ' ' || due_time)
              >= datetime('now', 'localtime', '-3 minutes')
    ")->fetchAll();
    echo json_encode(array_map('formatTask', $rows));
    exit;
}

// ── POST /api/tasks/reorder ───────────────────────────────
if ($method === 'POST' && preg_match('#^/api/tasks/reorder#', $uri)) {
    $pdo  = db();
    $ids  = array_values(array_map('intval', (json_decode(file_get_contents('php://input'), true) ?? [])['ids'] ?? []));
    $stmt = $pdo->prepare("UPDATE tasks SET position=?, updated_at=strftime('%Y-%m-%dT%H:%M:%SZ','now') WHERE id=?");
    foreach ($ids as $pos => $id) $stmt->execute([$pos, $id]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── /api/tasks/:id ────────────────────────────────────────
$id = null;
if (preg_match('#^/api/tasks/(\d+)#', $uri, $m)) $id = (int)$m[1];

$pdo = db();

switch ($method) {

    case 'GET':
        if ($id) {
            $row = $pdo->prepare("SELECT * FROM tasks WHERE id=?");
            $row->execute([$id]);
            $task = $row->fetch();
            if (!$task) { http_response_code(404); echo json_encode(['error' => 'not found']); break; }
            echo json_encode(formatTask($task));
            break;
        }
        $listId = $_GET['list_id'] ?? null;
        $where  = '';
        $params = [];
        if ($listId !== null) {
            $where    = 'WHERE list_id=?';
            $params[] = (int)$listId;
        }
        $stmt = $pdo->prepare("SELECT * FROM tasks $where ORDER BY done ASC, position ASC, created_at ASC");
        $stmt->execute($params);
        echo json_encode(array_map('formatTask', $stmt->fetchAll()));
        break;

    case 'POST':
        $b        = json_decode(file_get_contents('php://input'), true) ?? [];
        $title    = trim($b['title'] ?? '');
        if (!$title) { http_response_code(400); echo json_encode(['error' => 'title required']); break; }

        $prio      = max(1, min(3, (int)($b['priority'] ?? 2)));
        $dueDate   = ($b['due_date']   ?? '') ?: null;
        $dueTime   = ($b['due_time']   ?? '') ?: null;
        $notes     = ($b['notes']      ?? '') ?: null;
        $subtasks  = json_encode($b['subtasks'] ?? []);
        $listId    = array_key_exists('list_id', $b) && $b['list_id'] ? (int)$b['list_id'] : null;
        $remindMin = array_key_exists('remind_min', $b) && $b['remind_min'] !== null ? (int)$b['remind_min'] : null;
        $maxPos    = (int) $pdo->query('SELECT COALESCE(MAX(position)+1,0) FROM tasks WHERE done=0')->fetchColumn();

        $pdo->prepare('INSERT INTO tasks (title,priority,position,due_date,due_time,notes,subtasks,list_id,remind_min) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$title, $prio, $maxPos, $dueDate, $dueTime, $notes, $subtasks, $listId, $remindMin]);
        $task = formatTask($pdo->query("SELECT * FROM tasks WHERE id={$pdo->lastInsertId()}")->fetch());
        http_response_code(201);
        echo json_encode($task);
        break;

    case 'PUT':
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'id required']); break; }
        $b      = json_decode(file_get_contents('php://input'), true) ?? [];
        $fields = []; $params = [];

        if (array_key_exists('done',     $b)) { $fields[] = 'done=?';     $params[] = (int)(bool)$b['done']; }
        if (array_key_exists('title',    $b) && trim($b['title'])) { $fields[] = 'title=?';    $params[] = trim($b['title']); }
        if (array_key_exists('priority', $b)) { $fields[] = 'priority=?'; $params[] = max(1,min(3,(int)$b['priority'])); }
        if (array_key_exists('due_date', $b)) { $fields[] = 'due_date=?'; $params[] = $b['due_date'] ?: null; }
        if (array_key_exists('due_time',   $b)) { $fields[] = 'due_time=?';   $params[] = $b['due_time'] ?: null; }
        if (array_key_exists('notes',      $b)) { $fields[] = 'notes=?';      $params[] = $b['notes'] ?: null; }
        if (array_key_exists('subtasks',   $b)) { $fields[] = 'subtasks=?';   $params[] = json_encode($b['subtasks']); }
        if (array_key_exists('list_id',    $b)) { $fields[] = 'list_id=?';    $params[] = $b['list_id'] ? (int)$b['list_id'] : null; }
        if (array_key_exists('remind_min', $b)) { $fields[] = 'remind_min=?'; $params[] = $b['remind_min'] !== null ? (int)$b['remind_min'] : null; }
        if (array_key_exists('reminded',   $b)) { $fields[] = 'reminded=?';   $params[] = (int)(bool)$b['reminded']; }
        // reset reminded when scheduling changes
        if (array_key_exists('due_date', $b) || array_key_exists('due_time', $b) || array_key_exists('remind_min', $b)) {
            if (!array_key_exists('reminded', $b)) { $fields[] = 'reminded=?'; $params[] = 0; }
        }

        if (!$fields) { http_response_code(400); echo json_encode(['error' => 'nothing to update']); break; }
        $fields[] = "updated_at=strftime('%Y-%m-%dT%H:%M:%SZ','now')";
        $params[] = $id;
        $pdo->prepare('UPDATE tasks SET '.implode(',',$fields).' WHERE id=?')->execute($params);
        echo json_encode(formatTask($pdo->query("SELECT * FROM tasks WHERE id=$id")->fetch()));
        break;

    case 'DELETE':
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'id required']); break; }
        $pdo->prepare('DELETE FROM tasks WHERE id=?')->execute([$id]);
        http_response_code(204);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'method not allowed']);
}
