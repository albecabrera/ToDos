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
        </div>
        <div class="filter-tabs" role="tablist">
            <button class="filter-tab active" data-filter="all"     role="tab">Todas</button>
            <button class="filter-tab"         data-filter="pending" role="tab">Pendientes</button>
            <button class="filter-tab"         data-filter="done"   role="tab">Listas</button>
        </div>
    </header>

    <!-- ── Task List ── -->
    <main class="tasks-wrapper" id="tasks-wrapper">
        <div class="task-list" id="task-list" role="list"></div>
        <div class="empty-state hidden" id="empty-state" aria-live="polite">
            <div class="empty-glyph">◎</div>
            <p>Todo al día</p>
        </div>
    </main>

    <!-- ── Footer ── -->
    <footer class="app-footer">
        <div class="add-row">
            <input
                type="text"
                id="new-task-input"
                class="task-input"
                placeholder="Nueva tarea…"
                maxlength="120"
                autocomplete="off"
                spellcheck="false"
                aria-label="Nueva tarea"
            >
            <div class="priority-picker" id="priority-picker" title="Prioridad" aria-label="Prioridad">
                <span class="priority-dot p2" id="prio-dot"></span>
            </div>
            <button class="add-btn" id="add-btn" aria-label="Agregar">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M7 1v12M1 7h12" stroke="currentColor" stroke-width="2"
                          stroke-linecap="round"/>
                </svg>
            </button>
        </div>
        <div class="priority-menu hidden" id="priority-menu">
            <button class="prio-option" data-prio="1"><span class="priority-dot p1"></span>Baja</button>
            <button class="prio-option" data-prio="2"><span class="priority-dot p2"></span>Media</button>
            <button class="prio-option" data-prio="3"><span class="priority-dot p3"></span>Alta</button>
        </div>
    </footer>

</div>

<script src="/js/app.js"></script>
</body>
</html>
