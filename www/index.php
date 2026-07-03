<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1c1c1e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Aufgaben</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<div class="app">

    <!-- ── Header ── -->
    <header class="app-header">
        <div class="header-top">
            <div class="title-row">
                <span class="app-icon">✦</span>
                <h1 class="app-title">Aufgaben</h1>
                <span class="badge" id="badge">0</span>
            </div>
            <div class="header-actions">
                <button class="hdr-btn" id="search-toggle-btn" title="Suchen">
                    <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                        <circle cx="5.5" cy="5.5" r="4" stroke="currentColor" stroke-width="1.3"/>
                        <path d="M9 9l2.5 2.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                    </svg>
                </button>
                <button class="hdr-btn" id="copy-btn" title="Als Markdown kopieren">
                    <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                        <rect x="4" y="1" width="8" height="9" rx="1.5" stroke="currentColor" stroke-width="1.2"/>
                        <path d="M1 4v7a1.5 1.5 0 001.5 1.5H9" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Search bar -->
        <div class="search-row hidden" id="search-row">
            <input type="search" id="search-input" class="search-input"
                   placeholder="Aufgabe suchen…" autocomplete="off" spellcheck="false">
            <button class="hdr-btn" id="search-close-btn">✕</button>
        </div>

        <!-- Filter tabs -->
        <div class="filter-tabs" id="filter-tabs" role="tablist">
            <button class="filter-tab active" data-filter="all"     role="tab">Alle</button>
            <button class="filter-tab"         data-filter="pending" role="tab">Offen</button>
            <button class="filter-tab"         data-filter="done"   role="tab">Erledigt</button>
        </div>

        <!-- Stats bar -->
        <div class="stats-bar" id="stats-bar">
            <span class="stat" id="stat-streak" title="Tages-Serie">🔥 —</span>
            <span class="stat-sep">·</span>
            <span class="stat" id="stat-week" title="Diese Woche erledigt">✓ — diese Woche</span>
        </div>
    </header>

    <!-- ── Body: sidebar + tasks ── -->
    <div class="app-body">

        <!-- Lists sidebar -->
        <nav class="lists-bar" id="lists-bar"></nav>

        <!-- Tasks area -->
        <main class="tasks-wrapper">
            <div class="task-list" id="task-list" role="list"></div>
            <div class="empty-state hidden" id="empty-state">
                <div class="empty-glyph">◎</div>
                <p id="empty-msg">Alles erledigt</p>
            </div>
        </main>

    </div>

    <!-- ── Undo Toast ── -->
    <div class="undo-toast hidden" id="undo-toast">
        <span class="undo-msg">Aufgabe gelöscht</span>
        <button class="undo-btn" id="undo-btn">Rückgängig</button>
    </div>

    <!-- ── Footer ── -->
    <footer class="app-footer">
        <div class="nl-hint hidden" id="nl-hint"></div>

        <div class="date-row hidden" id="date-row">
            <input type="date" id="due-date-input" class="date-input" aria-label="Fälligkeitsdatum">
            <button class="clear-date-btn" id="clear-date-btn">✕</button>
        </div>

        <div class="add-row">
            <input type="text" id="new-task-input" class="task-input"
                   placeholder="Neue Aufgabe… (oder 'morgen Meeting')"
                   maxlength="120" autocomplete="off" spellcheck="false">
            <button class="icon-btn calendar-btn" id="calendar-btn" title="Fälligkeitsdatum">
                <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                    <rect x="1" y="2" width="11" height="10" rx="2" stroke="currentColor" stroke-width="1.2"/>
                    <path d="M4 1v2M9 1v2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                    <path d="M1 5h11" stroke="currentColor" stroke-width="1.2"/>
                </svg>
            </button>
            <div class="priority-picker" id="priority-picker" title="Priorität">
                <span class="priority-dot p2" id="prio-dot"></span>
            </div>
            <button class="add-btn" id="add-btn" aria-label="Hinzufügen">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M7 1v12M1 7h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <div class="priority-menu hidden" id="priority-menu">
            <button class="prio-option" data-prio="1"><span class="priority-dot p1"></span>Niedrig</button>
            <button class="prio-option" data-prio="2"><span class="priority-dot p2"></span>Mittel</button>
            <button class="prio-option" data-prio="3"><span class="priority-dot p3"></span>Hoch</button>
        </div>

        <div class="footer-actions" id="footer-actions" style="display:none">
            <button class="clean-btn" id="clean-btn">Erledigte löschen</button>
        </div>
    </footer>

</div>
<script src="/js/app.js"></script>
</body>
</html>
