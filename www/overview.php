<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1c1c1e">
    <title>Deine Aufgaben</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="stylesheet" href="/css/style.css">
    <style>
        html, body {
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            background: transparent;
        }
        .ov {
            display: flex;
            flex-direction: column;
            height: 100vh;
            width: 100vw;
            padding: 6vh 8vw;
            background: var(--glass-bg);
            backdrop-filter: var(--blur-bg);
            -webkit-backdrop-filter: var(--blur-bg);
            animation: ov-in .45s var(--ease-spring);
        }
        @keyframes ov-in {
            from { opacity: 0; transform: scale(.98); }
            to   { opacity: 1; transform: scale(1); }
        }
        .ov-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            flex-shrink: 0;
            margin-bottom: 3vh;
        }
        .ov-title {
            font-size: clamp(28px, 4vw, 52px);
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--color-text);
        }
        .ov-date {
            font-size: clamp(14px, 1.4vw, 20px);
            color: var(--color-sub);
            text-transform: capitalize;
        }
        .ov-hint {
            position: fixed;
            bottom: 3vh;
            left: 0; right: 0;
            text-align: center;
            font-size: 13px;
            color: var(--color-sub);
            opacity: .7;
        }
        .ov-hint kbd {
            font-family: var(--font-ui);
            background: var(--glass-input);
            border-radius: var(--radius-sm);
            padding: 2px 8px;
            font-size: 12px;
        }
        .ov-close {
            position: fixed;
            top: 3vh; right: 3vw;
            width: 44px; height: 44px;
            border-radius: 50%;
            border: none;
            background: var(--glass-input);
            color: var(--color-text);
            font-size: 20px;
            cursor: pointer;
            transition: background .15s, transform .15s var(--ease-spring);
        }
        .ov-close:hover { background: var(--glass-task-h); transform: scale(1.08); }

        .ov-scroll {
            flex: 1;
            min-height: 0;          /* erlaubt Schrumpfen → internes Scrollen statt Überlauf */
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
            padding-bottom: 8vh;    /* letzte Aufgabe nicht hinter dem Hinweis verstecken */
            display: flex;
            flex-direction: column;
            gap: 3vh;
        }
        .ov-scroll::-webkit-scrollbar { width: 6px; }
        .ov-scroll::-webkit-scrollbar-thumb {
            background: var(--color-sep); border-radius: 3px;
        }
        .ov-group-title {
            font-size: clamp(13px, 1.2vw, 16px);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--color-sub);
            margin-bottom: 1.4vh;
        }
        .ov-group.overdue .ov-group-title { color: var(--color-overdue); }
        .ov-item {
            display: flex;
            align-items: center;
            gap: var(--gap-md);
            padding: 1.6vh 1.4vw;
            border-radius: var(--radius-lg);
            background: var(--glass-task);
            box-shadow: var(--shadow-task);
            margin-bottom: 1.2vh;
        }
        .ov-dot {
            flex-shrink: 0;
            width: 12px; height: 12px;
            border-radius: 50%;
            background: var(--color-sub);
        }
        .ov-dot.p1 { background: #34C759; }
        .ov-dot.p2 { background: #FF9F0A; }
        .ov-dot.p3 { background: var(--color-overdue); }
        .ov-item-title {
            flex: 1;
            font-size: clamp(17px, 1.8vw, 26px);
            font-weight: 500;
            color: var(--color-text);
        }
        .ov-item-due {
            flex-shrink: 0;
            font-size: clamp(13px, 1.3vw, 18px);
            color: var(--color-sub);
            font-variant-numeric: tabular-nums;
        }
        .ov-group.overdue .ov-item-due { color: var(--color-overdue); }
        .ov-item-list {
            flex-shrink: 0;
            font-size: clamp(11px, 1vw, 14px);
            color: var(--color-sub);
            background: var(--glass-input);
            padding: 3px 10px;
            border-radius: 999px;
        }
        .ov-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2vh;
            color: var(--color-sub);
        }
        .ov-empty-glyph { font-size: clamp(60px, 8vw, 120px); }
        .ov-empty-msg { font-size: clamp(20px, 2.4vw, 34px); font-weight: 600; color: var(--color-text); }

        /* ── Bearbeiten-Modus ── */
        .ov-mode {
            display: none;
            font-size: clamp(13px, 1.3vw, 18px);
            font-weight: 600;
            color: var(--color-accent);
            margin-left: 14px;
        }
        body.edit .ov-mode { display: inline; }

        .ov-check {
            display: none;
            flex-shrink: 0;
            width: 22px; height: 22px;
            border-radius: 50%;
            border: 2px solid var(--color-sub);
            cursor: pointer;
            transition: border-color .15s, background .15s, transform .15s var(--ease-spring);
        }
        .ov-check:hover { border-color: var(--color-accent); transform: scale(1.12); }
        body.edit .ov-check { display: block; }
        body.edit .ov-dot   { display: none; }

        body.edit .ov-item-title {
            cursor: text;
            border-radius: var(--radius-sm);
            padding: 2px 8px;
            margin: -2px -8px;
            transition: background .15s, box-shadow .15s;
        }
        body.edit .ov-item-title:focus {
            outline: none;
            background: var(--glass-input);
            box-shadow: 0 0 0 2px var(--color-accent);
        }

        /* Bearbeiten-Modus: undurchsichtiger Vollbild-Hintergrund (kein Durchscheinen des Desktops) */
        body.edit .ov {
            background: #1c1c1e;
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
        }
        [data-scheme="light"] body.edit .ov {
            background: #f2f2f7;
        }

        .ov-item-edit-due {
            display: inline-flex;
            gap: 6px;
            flex-shrink: 0;
        }
        .ov-date-input, .ov-time-input {
            font-family: var(--font-ui);
            font-size: clamp(12px, 1.1vw, 16px);
            color: var(--color-text);
            background: var(--glass-input);
            border: 1px solid var(--color-sep);
            border-radius: var(--radius-sm);
            padding: 4px 8px;
            color-scheme: light dark;
        }
        .ov-date-input:focus, .ov-time-input:focus {
            outline: none;
            box-shadow: 0 0 0 2px var(--color-accent);
        }

        .ov-dot-edit {
            display: none;
            width: 16px; height: 16px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: transform .15s var(--ease-spring);
        }
        body.edit .ov-dot-edit {
            display: block;
            flex-shrink: 0;
        }
        body.edit .ov-dot-edit:hover { transform: scale(1.15); }

        .ov-item-delete {
            display: none;
            flex-shrink: 0;
            width: 26px; height: 26px;
            border-radius: 50%;
            border: none;
            background: var(--glass-input);
            color: var(--color-sub);
            font-size: 13px;
            line-height: 1;
            cursor: pointer;
            transition: background .15s, color .15s, transform .15s var(--ease-spring);
        }
        body.edit .ov-item-delete { display: block; }
        .ov-item-delete:hover { background: var(--color-overdue); color: #fff; transform: scale(1.1); }
    </style>
</head>
<body>
<div class="ov">
    <div class="ov-head">
        <div class="ov-title" id="ov-title">Deine Aufgaben<span class="ov-mode">· Bearbeiten</span></div>
        <div class="ov-date" id="ov-date"></div>
    </div>
    <div class="ov-scroll" id="ov-scroll"></div>
</div>
<button class="ov-close" id="ov-close" title="Schließen">✕</button>
<div class="ov-hint" id="ov-hint"><kbd>Esc</kbd> zum Bearbeiten · Klick auf ✕ zum Schließen</div>

<script>
const $ = (s) => document.querySelector(s);

// Scheme aus der System-Appearance (WKWebView folgt NSApp.effectiveAppearance)
function applyScheme() {
    const dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.dataset.scheme = dark ? 'dark' : 'light';
}
applyScheme();
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applyScheme);

function closeOverview() {
    try { window.webkit.messageHandlers.bridge.postMessage({ type: 'closeOverview' }); } catch (_) {}
}
$('#ov-close').addEventListener('click', closeOverview);

// Esc → Bearbeiten-Modus umschalten (schließt NICHT — dafür ist ✕)
let editMode = false;
function toggleEdit() {
    editMode = !editMode;
    document.body.classList.toggle('edit', editMode);
    $('#ov-hint').innerHTML = editMode
        ? '<kbd>Esc</kbd> zum Beenden · Titel, Priorität, Datum & Löschen bearbeitbar'
        : '<kbd>Esc</kbd> zum Bearbeiten · Klick auf ✕ zum Schließen';
    render(allTasks);
}
document.addEventListener('keydown', (e) => {
    // Beim Editieren eines Titels: Enter bestätigt, Esc bricht Feld ab (nicht Modus)
    if (e.target.classList && e.target.classList.contains('ov-item-title') && editMode) {
        if (e.key === 'Enter') { e.preventDefault(); e.target.blur(); }
        if (e.key === 'Escape') { e.preventDefault(); e.target.blur(); }
        return;
    }
    if (e.key === 'Escape') { e.preventDefault(); toggleEdit(); }
});

const WD = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
const MO = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
(function setDate() {
    const d = new Date();
    $('#ov-date').textContent = `${WD[d.getDay()]}, ${d.getDate()}. ${MO[d.getMonth()]}`;
})();

function dueDateTime(t) {
    if (!t.due_date) return null;
    const time = t.due_time || '23:59';
    return new Date(`${t.due_date}T${time}:00`);
}

function fmtDue(t) {
    const dt = dueDateTime(t);
    if (!dt) return '';
    const time = t.due_time ? ` ${t.due_time}` : '';
    const d = dt, now = new Date();
    const sameDay = d.toDateString() === now.toDateString();
    if (sameDay) return `Heute${time}`;
    return `${d.getDate()}.${d.getMonth() + 1}.${time}`;
}

let allTasks = [];

function render(tasks) {
    allTasks = tasks;
    const pending = tasks.filter((t) => !t.done);
    const scroll = $('#ov-scroll');

    if (pending.length === 0) {
        scroll.innerHTML = `
            <div class="ov-empty">
                <div class="ov-empty-glyph">✓</div>
                <div class="ov-empty-msg">Alles erledigt — genieß den Tag!</div>
            </div>`;
        return;
    }

    const now = new Date();
    const endToday = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59);
    const groups = { overdue: [], today: [], upcoming: [], someday: [] };

    for (const t of pending) {
        const dt = dueDateTime(t);
        if (!dt) groups.someday.push(t);
        else if (dt < now) groups.overdue.push(t);
        else if (dt <= endToday) groups.today.push(t);
        else groups.upcoming.push(t);
    }

    const prioRank = (t) => -(t.priority || 0);
    const byDue = (a, b) => (dueDateTime(a) || 0) - (dueDateTime(b) || 0);
    groups.overdue.sort(byDue);
    groups.today.sort(byDue);
    groups.upcoming.sort(byDue);
    groups.someday.sort((a, b) => prioRank(a) - prioRank(b));

    const sections = [
        ['overdue',  '⚠︎ Überfällig', groups.overdue],
        ['today',    'Heute',          groups.today],
        ['upcoming', 'Demnächst',      groups.upcoming],
        ['someday',  'Ohne Datum',     groups.someday],
    ];

    scroll.innerHTML = sections
        .filter(([, , items]) => items.length)
        .map(([cls, label, items]) => `
            <div class="ov-group ${cls}">
                <div class="ov-group-title">${label} · ${items.length}</div>
                ${items.map((t) => `
                    <div class="ov-item" data-id="${t.id}">
                        <span class="ov-check" data-id="${t.id}" title="Als erledigt markieren"></span>
                        <span class="ov-dot p${t.priority || 2}"></span>
                        <span class="ov-dot-edit ov-dot p${t.priority || 2}" data-id="${t.id}" data-prio="${t.priority || 2}" title="Priorität ändern"></span>
                        <span class="ov-item-title" data-id="${t.id}"${editMode ? ' contenteditable="true" spellcheck="false"' : ''}>${esc(t.title)}</span>
                        ${editMode
                            ? `<span class="ov-item-edit-due">
                                   <input type="date" class="ov-date-input" data-id="${t.id}" value="${t.due_date || ''}">
                                   <input type="time" class="ov-time-input" data-id="${t.id}" value="${t.due_time || ''}">
                               </span>
                               <button class="ov-item-delete" data-id="${t.id}" title="Löschen">✕</button>`
                            : (fmtDue(t) ? `<span class="ov-item-due">${fmtDue(t)}</span>` : '')}
                    </div>`).join('')}
            </div>`).join('');
}

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function loadTasks() {
    return fetch('/api/tasks')
        .then((r) => r.json())
        .then(render)
        .catch(() => { $('#ov-scroll').innerHTML = '<div class="ov-empty"><div class="ov-empty-msg">Keine Verbindung</div></div>'; });
}

function updateTask(id, body) {
    return fetch(`/api/tasks/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
}

// ── Edit-Interaktionen (nur im Bearbeiten-Modus, via Delegation) ──
$('#ov-scroll').addEventListener('click', (e) => {
    if (!editMode) return;

    const check = e.target.closest('.ov-check');
    if (check) {
        updateTask(+check.dataset.id, { done: 1 }).then(loadTasks);
        return;
    }

    const dot = e.target.closest('.ov-dot-edit');
    if (dot) {
        const next = (+dot.dataset.prio % 3) + 1;
        updateTask(+dot.dataset.id, { priority: next }).then(loadTasks);
        return;
    }

    const del = e.target.closest('.ov-item-delete');
    if (del) {
        fetch(`/api/tasks/${del.dataset.id}`, { method: 'DELETE' }).then(loadTasks);
        return;
    }
});

$('#ov-scroll').addEventListener('focusout', (e) => {
    const el = e.target;
    if (!el.classList || !el.classList.contains('ov-item-title')) return;
    const id = +el.dataset.id;
    const task = allTasks.find((t) => t.id === id);
    const next = el.textContent.trim();
    if (!task || !next || next === task.title) { el.textContent = task ? task.title : next; return; }
    updateTask(id, { title: next }).then(loadTasks);
});

// Fälligkeitsdatum / -zeit im Bearbeiten-Modus ändern
$('#ov-scroll').addEventListener('change', (e) => {
    const el = e.target;
    if (el.classList.contains('ov-date-input')) {
        updateTask(+el.dataset.id, { due_date: el.value || null }).then(loadTasks);
    } else if (el.classList.contains('ov-time-input')) {
        updateTask(+el.dataset.id, { due_time: el.value || null }).then(loadTasks);
    }
});

loadTasks();
</script>
</body>
</html>
