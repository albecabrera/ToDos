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

        /* ── Composer (⌘N → neue Erinnerung) ── */
        .ov-composer {
            position: fixed;
            inset: 0;
            display: none;
            align-items: flex-start;
            justify-content: center;
            padding-top: 20vh;
            background: rgba(0, 0, 0, .35);
            z-index: 50;
            animation: ov-in .2s var(--ease-spring);
        }
        .ov-composer.open { display: flex; }
        .ov-composer-box {
            width: min(680px, 84vw);
            background: #2c2c2e;
            border: 1px solid var(--color-sep);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-task);
            padding: 18px 20px;
        }
        [data-scheme="light"] .ov-composer-box { background: #fff; }
        .ov-composer-input {
            width: 100%;
            font-family: var(--font-ui);
            font-size: clamp(18px, 2vw, 24px);
            font-weight: 500;
            color: var(--color-text);
            background: transparent;
            border: none;
            outline: none;
        }
        .ov-composer-input::placeholder { color: var(--color-sub); font-weight: 400; }
        .ov-composer-preview {
            margin-top: 10px;
            font-size: 13px;
            color: var(--color-sub);
            min-height: 1.2em;
        }
        .ov-composer-preview b { color: var(--color-accent); font-weight: 600; }
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
<div class="ov-hint" id="ov-hint"><kbd>⌘N</kbd> neue Erinnerung · <kbd>Esc</kbd> zum Bearbeiten · Klick auf ✕ zum Schließen</div>

<div class="ov-composer" id="ov-composer">
    <div class="ov-composer-box">
        <input class="ov-composer-input" id="ov-composer-input" type="text"
               placeholder="Neue Erinnerung… z. B. „Anrufen morgen 1400 r:15“" autocomplete="off">
        <div class="ov-composer-preview" id="ov-composer-preview"></div>
    </div>
</div>

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
    // ⌘N → Composer für neue Erinnerung öffnen
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'n') {
        e.preventDefault();
        openComposer();
        return;
    }
    // Composer offen → eigene Tasten (Enter/Esc) im Composer-Handler
    if (composerOpen) return;
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
    if (!t.due_date && !t.due_time) return null;
    const datePart = t.due_date || new Date().toISOString().slice(0, 10);
    const time = t.due_time || '23:59';
    return new Date(`${datePart}T${time}:00`);
}

function fmtDue(t) {
    const dt = dueDateTime(t);
    if (!dt) return '';
    if (!t.due_date) return t.due_time;   // solo hora, sin fecha → mostrar la hora sola
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

// ── Natural-Language-Parser (portiert aus js/app.js) ──────
const WEEKDAYS = {
    donnerstag:4, dienstag:2, mittwoch:3, montag:1, freitag:5, samstag:6, sonntag:0,
    mo:1, di:2, mi:3, do:4, fr:5, sa:6, so:0,
    lunes:1, martes:2, miércoles:3, miercoles:3, jueves:4, viernes:5, sábado:6, sabado:6, domingo:0,
    lun:1, mar:2, mié:3, jue:4, vie:5, sáb:6, dom:0,
    monday:1, tuesday:2, wednesday:3, thursday:4, friday:5, saturday:6, sunday:0,
    mon:1, tue:2, wed:3, thu:4, fri:5, sat:6, sun:0,
};
function addDays(n) {
    const d = new Date();
    d.setDate(d.getDate() + n);
    return d.toISOString().slice(0, 10);
}
function nextWeekday(word) {
    const w = word.toLowerCase()
        .replace(/[üú]/g,'u').replace(/[äá]/g,'a').replace(/[öó]/g,'o').replace(/é/g,'e');
    const key = Object.keys(WEEKDAYS).sort((a,b) => b.length - a.length).find(k => w.startsWith(k));
    if (key === undefined) return null;
    const diff = ((WEEKDAYS[key] - new Date().getDay()) + 7) % 7 || 7;
    return addDays(diff);
}
const NL_PATTERNS = [
    [/\b(heute|hoy|today)\b/i,                             () => addDays(0)],
    [/\b(morgen|mañana|manana|tomorrow)\b/i,               () => addDays(1)],
    [/\b(übermorgen|pasado mañana|day after tomorrow)\b/i, () => addDays(2)],
    [/\bin\s+(\d+)\s+tagen?\b/i,                           m  => addDays(+m[1])],
    [/\ben\s+(\d+)\s+d[ií]as?\b/i,                        m  => addDays(+m[1])],
    [/\b(\d+)\s+tagen?\b/i,                                m  => addDays(+m[1])],
    [/\b(\d+)\s+d[ií]as?\b/i,                             m  => addDays(+m[1])],
    [/\bn[aä]chsten?\s+(\w+)\b/i,                          m  => nextWeekday(m[1])],
    [/\bpr[oó]xim[ao]\s+(\w+)\b/i,                        m  => nextWeekday(m[1])],
    [/\b(montag|dienstag|mittwoch|donnerstag|freitag|samstag|sonntag|lunes|martes|mi[eé]rcoles|jueves|viernes|s[aá]bado|domingo|monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i, m => nextWeekday(m[1])],
];
const TIME_RE   = /\b([01]\d|2[0-3])([0-5]\d)\b/;
const REMIND_RE = /\br:(\d+)\b/i;
function extractNL(title) {
    const rm = title.match(REMIND_RE);
    const remindMin = rm ? Math.max(1, parseInt(rm[1], 10)) : null;
    let src = remindMin !== null ? title.replace(REMIND_RE, '').replace(/\s+/g, ' ').trim() : title;

    const COMBINED = /\b(20\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\s+([01]\d|2[0-3])([0-5]\d)\b/;
    const cm = src.match(COMBINED);
    if (cm) return { date: `${cm[1]}-${cm[2]}-${cm[3]}`, time: `${cm[4]}:${cm[5]}`, remind_min: remindMin, clean: src.replace(COMBINED, '').replace(/\s+/g, ' ').trim() };

    const DATE8 = /\b(20\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\b/;
    const dm = src.match(DATE8);
    if (dm) {
        const rest = src.replace(DATE8, '').replace(/\s+/g, ' ').trim();
        const tm   = rest.match(TIME_RE);
        return { date: `${dm[1]}-${dm[2]}-${dm[3]}`, time: tm ? `${tm[1]}:${tm[2]}` : null, remind_min: remindMin, clean: tm ? rest.replace(TIME_RE, '').replace(/\s+/g, ' ').trim() : rest };
    }

    for (const [re, fn] of NL_PATTERNS) {
        const m = src.match(re);
        if (m) {
            const date = fn(m);
            if (!date) continue;
            const rest = src.replace(re, '').replace(/\s+/g, ' ').trim();
            const tm   = rest.match(TIME_RE);
            return { date, time: tm ? `${tm[1]}:${tm[2]}` : null, remind_min: remindMin, clean: tm ? rest.replace(TIME_RE, '').replace(/\s+/g, ' ').trim() : rest };
        }
    }
    return { date: null, time: null, remind_min: remindMin, clean: src };
}

// ── Composer (⌘N) ────────────────────────────────────────
let composerOpen = false;
const composer      = $('#ov-composer');
const composerInput = $('#ov-composer-input');
const composerPrev  = $('#ov-composer-preview');

function openComposer() {
    if (composerOpen) return;
    composerOpen = true;
    composer.classList.add('open');
    composerInput.value = '';
    composerPrev.innerHTML = '';
    composerInput.focus();
    // WKWebView: Fokus nach evaluateJavaScript ist unzuverlässig → nachfassen
    setTimeout(() => composerInput.focus(), 30);
}
function closeComposer() {
    composerOpen = false;
    composer.classList.remove('open');
    composerInput.blur();
}
function updateComposerPreview() {
    const raw = composerInput.value.trim();
    if (!raw) { composerPrev.innerHTML = ''; return; }
    const { date, time, remind_min, clean } = extractNL(raw);
    const bits = [];
    if (date) bits.push(`📅 <b>${date}</b>`);
    if (time) bits.push(`🕑 <b>${time}</b>`);
    if (remind_min) bits.push(`🔔 <b>${remind_min} Min. vorher</b>`);
    composerPrev.innerHTML = bits.length ? `${clean || '…'} — ${bits.join(' · ')}` : '';
}
async function submitComposer() {
    if (!composerOpen) return;              // Guard: kein Doppel-Submit (blur nach Enter)
    const raw = composerInput.value.trim();
    closeComposer();                        // setzt composerOpen = false → Re-Entry blockiert
    if (!raw) return;
    const { date, time, remind_min, clean } = extractNL(raw);
    const title = clean || raw;
    await fetch('/api/tasks', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, priority: 1, due_date: date || null, due_time: time || null, remind_min: remind_min || null }),
    });
    loadTasks();
}
composerInput.addEventListener('input', updateComposerPreview);
composerInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter')  { e.preventDefault(); submitComposer(); }
    if (e.key === 'Escape') { e.preventDefault(); closeComposer(); }  // Escape verwirft
});
// Fokus verloren (Klick daneben, Fenster wechselt) → direkt speichern
composerInput.addEventListener('blur', () => { if (composerOpen) submitComposer(); });
composer.addEventListener('click', (e) => { if (e.target === composer) submitComposer(); });

loadTasks();
</script>
</body>
</html>
