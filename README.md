# MenuBar Tasks

A premium macOS menubar task manager with a Liquid Glass interface, local PHP backend, and zero cloud dependency.

![macOS 13+](https://img.shields.io/badge/macOS-13%2B-black?style=flat-square)
![Swift](https://img.shields.io/badge/Swift-5.9-F05138?style=flat-square&logo=swift)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&logo=php)
![SQLite](https://img.shields.io/badge/SQLite-local-003B57?style=flat-square&logo=sqlite)

## Features

- **Liquid Glass UI** — NSVisualEffectView + transparent WKWebView, adapts to light/dark mode
- **Global hotkey** `⌘⇧T` — open from anywhere, no Accessibility permission required
- **Subtasks** — nested checklist per task, stored as JSON
- **Notes** — expandable text area per task
- **Natural language dates** — type "reunión mañana" or "entrega viernes" to auto-set due date
- **Search** — real-time filter across titles and notes
- **Undo delete** — 5-second toast to restore any deleted task
- **Badge** — pending count in menubar icon, turns red when overdue tasks exist
- **Completion sound + haptic** — native macOS feedback on task/subtask completion
- **Copy as Markdown** — export all tasks to clipboard in Markdown format
- **Auto-clean** — one-tap button to archive all completed tasks
- **Streak & stats** — consecutive-day completion streak and weekly count in header
- **Drag & drop reorder** — HTML5 drag to reprioritize pending tasks
- **Priority system** — Low / Medium / High with color indicators
- **Due dates** — per-task date picker with relative labels (Hoy, Mañana, En 3d…)
- **Login item** — optional launch at login via right-click context menu
- **No Xcode required** — builds with `swiftc` directly

## Stack

| Layer    | Technology                                      |
|----------|-------------------------------------------------|
| App host | Swift + AppKit (NSStatusItem, NSPopover)        |
| UI       | WKWebView → PHP-served HTML/CSS/JS              |
| Backend  | PHP 8 built-in server on port 8742              |
| Database | SQLite via PDO at `~/Library/Application Support/MenuBarTasks/tasks.db` |
| Hotkey   | Carbon HIToolbox (no Accessibility permission)  |

## Requirements

- macOS 13 Ventura or later
- Xcode Command Line Tools (`xcode-select --install`)
- PHP 8.1+ (`brew install php`)
- Swift compiler (`swiftc`)

## Build & Run

```bash
# Clone
git clone https://github.com/albecabrera/MenuBarTasks
cd MenuBarTasks

# Build
make build

# Run
make run

# Stop
make stop

# Clean build artifacts
make clean
```

`make run` kills any previous instance before launching.

## Architecture

```
MenuBarTasks.app/
├── MacOS/
│   └── MenuBarTasks          # Swift binary
└── Resources/
    ├── Info.plist
    ├── router.php            # PHP router (entry point for built-in server)
    └── www/
        ├── index.php         # App HTML shell
        ├── api/
        │   └── tasks.php     # REST API + SQLite (GET/POST/PUT/DELETE)
        ├── css/style.css     # Liquid Glass design tokens + components
        └── js/app.js         # Frontend logic (no framework)
```

### Swift → PHP bridge

At launch, `PHPServerManager` spawns PHP's built-in server on `127.0.0.1:8742` using the bundled `router.php`. The WKWebView loads `http://127.0.0.1:8742/`. All task operations go through `fetch()` calls to the local API.

### JS → Swift bridge

`window.webkit.messageHandlers.bridge.postMessage(msg)` is used for native actions:

| `msg.type` | Effect |
|------------|--------|
| `badge`    | Updates menubar icon count and color (red if overdue) |
| `haptic`   | NSHapticFeedbackManager trigger |
| `sound`    | NSSound by name (Pop, Funk, etc.) |
| `copy`     | Copy text to NSPasteboard |

### Data persistence

SQLite database lives at `~/Library/Application Support/MenuBarTasks/tasks.db` — survives rebuilds and app updates. Schema migrations use safe `ALTER TABLE ADD COLUMN` inside try/catch.

## API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/tasks` | List all tasks |
| POST | `/api/tasks` | Create task |
| PUT | `/api/tasks/:id` | Update task (title, done, priority, due_date, notes, subtasks) |
| DELETE | `/api/tasks/:id` | Delete task |
| POST | `/api/tasks/reorder` | Reorder by `{ids: [...]}` |
| DELETE | `/api/tasks/done` | Clean all completed tasks |
| GET | `/api/stats` | Streak + weekly + total stats |

## Natural Language Dates

Detected at task input time. The date keyword is stripped from the title.

| Input | Result |
|-------|--------|
| `reunión hoy` | Today |
| `entrega mañana` | Tomorrow |
| `informe viernes` | Next Friday |
| `revisión en 3 días` | In 3 days |
| `pasado mañana` | Day after tomorrow |

English keywords (`today`, `tomorrow`, weekday names) also supported.

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `⌘⇧T` | Toggle menubar popover (global) |
| `Enter` | Add task / save edit |
| `Escape` | Cancel inline edit |
| `Double-click` task title | Inline edit |

## License

MIT
