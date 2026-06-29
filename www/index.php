<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tareas</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<div class="app">

    <!-- ── Header ── -->
    <header class="app-header">
        <div class="header-top">
            <div class="title-row">
                <span class="app-icon">✦</span>
                <h1 class="app-title">Tareas</h1>
                <span class="badge" id="badge">0</span>
            </div>
            <div class="header-actions">
                <button class="hdr-btn" id="search-toggle-btn" title="Buscar">
                    <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                        <circle cx="5.5" cy="5.5" r="4" stroke="currentColor" stroke-width="1.3"/>
                        <path d="M9 9l2.5 2.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                    </svg>
                </button>
                <button class="hdr-btn" id="copy-btn" title="Copiar como Markdown">
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
                   placeholder="Buscar tarea…" autocomplete="off" spellcheck="false">
            <button class="hdr-btn" id="search-close-btn">✕</button>
        </div>

        <!-- Filter tabs (hidden when searching) -->
        <div class="filter-tabs" id="filter-tabs" role="tablist">
            <button class="filter-tab active" data-filter="all"     role="tab">Todas</button>
            <button class="filter-tab"         data-filter="pending" role="tab">Pendientes</button>
            <button class="filter-tab"         data-filter="done"   role="tab">Listas</button>
        </div>

        <!-- Stats bar -->
        <div class="stats-bar" id="stats-bar">
            <span class="stat" id="stat-streak" title="Racha de días completando tareas">🔥 —</span>
            <span class="stat-sep">·</span>
            <span class="stat" id="stat-week"   title="Completadas esta semana">✓ — esta semana</span>
        </div>
    </header>

    <!-- ── Task List ── -->
    <main class="tasks-wrapper">
        <div class="task-list" id="task-list" role="list"></div>
        <div class="empty-state hidden" id="empty-state">
            <div class="empty-glyph">◎</div>
            <p id="empty-msg">Todo al día</p>
        </div>
    </main>

    <!-- ── Undo Toast ── -->
    <div class="undo-toast hidden" id="undo-toast">
        <span class="undo-msg">Tarea eliminada</span>
        <button class="undo-btn" id="undo-btn">Deshacer</button>
    </div>

    <!-- ── Footer ── -->
    <footer class="app-footer">
        <!-- Natural language hint -->
        <div class="nl-hint hidden" id="nl-hint"></div>

        <!-- Date picker row -->
        <div class="date-row hidden" id="date-row">
            <input type="date" id="due-date-input" class="date-input" aria-label="Fecha límite">
            <button class="clear-date-btn" id="clear-date-btn">✕</button>
        </div>

        <div class="add-row">
            <input type="text" id="new-task-input" class="task-input"
                   placeholder="Nueva tarea… (o 'reunion mañana')"
                   maxlength="120" autocomplete="off" spellcheck="false">
            <button class="icon-btn calendar-btn" id="calendar-btn" title="Fecha límite">
                <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                    <rect x="1" y="2" width="11" height="10" rx="2" stroke="currentColor" stroke-width="1.2"/>
                    <path d="M4 1v2M9 1v2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                    <path d="M1 5h11" stroke="currentColor" stroke-width="1.2"/>
                </svg>
            </button>
            <div class="priority-picker" id="priority-picker" title="Prioridad">
                <span class="priority-dot p2" id="prio-dot"></span>
            </div>
            <button class="add-btn" id="add-btn" aria-label="Agregar">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M7 1v12M1 7h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <!-- Priority menu -->
        <div class="priority-menu hidden" id="priority-menu">
            <button class="prio-option" data-prio="1"><span class="priority-dot p1"></span>Baja</button>
            <button class="prio-option" data-prio="2"><span class="priority-dot p2"></span>Media</button>
            <button class="prio-option" data-prio="3"><span class="priority-dot p3"></span>Alta</button>
        </div>

        <!-- Clean completed -->
        <div class="footer-actions" id="footer-actions" style="display:none">
            <button class="clean-btn" id="clean-btn">Limpiar completadas</button>
        </div>
    </footer>

</div>
<script src="/js/app.js"></script>
</body>
</html>
