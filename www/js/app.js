/* MenuBar Tasks — Frontend v2.0 */

const API  = `${location.origin}/api/tasks`;
let tasks           = [];
let lists           = [];
let currentListId   = null;   // null = todas, integer = lista específica
let filter          = 'all';
let searchQuery     = '';
let selectedPriority = 2;
let selectedDueDate  = '';
let expandedIds     = new Set();
let undoTimer       = null;
let undoPending     = null;   // { task, onConfirm }
let dragSrcEl       = null;

// ── Bridge helpers ────────────────────────────────────────

function bridge(msg) {
    if (window.webkit?.messageHandlers?.bridge)
        window.webkit.messageHandlers.bridge.postMessage(msg);
}
const haptic = () => bridge({ type: 'haptic' });
const sound  = (name = 'Pop') => bridge({ type: 'sound', name });

// ── API ───────────────────────────────────────────────────

async function apiFetch(path, opts = {}) {
    const r = await fetch(API + path, {
        headers: { 'Content-Type': 'application/json' },
        ...opts,
    });
    if (r.status === 204) return null;
    return r.json();
}

// ── Natural language date parser ──────────────────────────

const WEEKDAYS = {
    // Deutsch (longest first to avoid prefix collisions)
    donnerstag:4, dienstag:2, mittwoch:3, montag:1, freitag:5, samstag:6, sonntag:0,
    mo:1, di:2, mi:3, do:4, fr:5, sa:6, so:0,
    // Español
    lunes:1, martes:2, miércoles:3, miercoles:3, jueves:4, viernes:5, sábado:6, sabado:6, domingo:0,
    lun:1, mar:2, mié:3, jue:4, vie:5, sáb:6, dom:0,
    // English
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
    const target = WEEKDAYS[key];
    const now = new Date();
    const diff = ((target - now.getDay()) + 7) % 7 || 7;
    return addDays(diff);
}

const NL_PATTERNS = [
    [/\b(heute|hoy|today)\b/i,                                    () => addDays(0)],
    [/\b(morgen|mañana|manana|tomorrow)\b/i,                      () => addDays(1)],
    [/\b(übermorgen|pasado mañana|day after tomorrow)\b/i,        () => addDays(2)],
    [/\bin\s+(\d+)\s+tagen?\b/i,                                  m  => addDays(+m[1])],
    [/\ben\s+(\d+)\s+d[ií]as?\b/i,                               m  => addDays(+m[1])],
    [/\b(\d+)\s+tagen?\b/i,                                       m  => addDays(+m[1])],
    [/\b(\d+)\s+d[ií]as?\b/i,                                    m  => addDays(+m[1])],
    [/\bn[aä]chsten?\s+(\w+)\b/i,                                 m  => nextWeekday(m[1])],
    [/\bpr[oó]xim[ao]\s+(\w+)\b/i,                               m  => nextWeekday(m[1])],
    [/\b(montag|dienstag|mittwoch|donnerstag|freitag|samstag|sonntag|lunes|martes|mi[eé]rcoles|jueves|viernes|s[aá]bado|domingo|monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i, m => nextWeekday(m[1])],
];

const TIME_RE = /\b([01]\d|2[0-3])([0-5]\d)\b/;

const REMIND_RE = /\br:(\d+)\b/i;

function extractNL(title) {
    // Extraer r:X (recordatorio en minutos) primero
    const rm = title.match(REMIND_RE);
    const remindMin = rm ? Math.max(1, parseInt(rm[1], 10)) : null;
    let src = remindMin !== null ? title.replace(REMIND_RE, '').replace(/\s+/g, ' ').trim() : title;

    // 1. YYYYMMDD + HHMM combinados
    const COMBINED = /\b(20\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\s+([01]\d|2[0-3])([0-5]\d)\b/;
    const cm = src.match(COMBINED);
    if (cm) {
        return {
            date:       `${cm[1]}-${cm[2]}-${cm[3]}`,
            time:       `${cm[4]}:${cm[5]}`,
            remind_min: remindMin,
            clean:      src.replace(COMBINED, '').replace(/\s+/g, ' ').trim(),
        };
    }

    // 2. YYYYMMDD solo (con hora opcional después)
    const DATE8 = /\b(20\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\b/;
    const dm = src.match(DATE8);
    if (dm) {
        const rest = src.replace(DATE8, '').replace(/\s+/g, ' ').trim();
        const tm   = rest.match(TIME_RE);
        return {
            date:       `${dm[1]}-${dm[2]}-${dm[3]}`,
            time:       tm ? `${tm[1]}:${tm[2]}` : null,
            remind_min: remindMin,
            clean:      tm ? rest.replace(TIME_RE, '').replace(/\s+/g, ' ').trim() : rest,
        };
    }

    // 3. Texto natural (heute/morgen/…) con hora opcional
    for (const [re, fn] of NL_PATTERNS) {
        const m = src.match(re);
        if (m) {
            const date = fn(m);
            if (!date) continue;
            const rest = src.replace(re, '').replace(/\s+/g, ' ').trim();
            const tm   = rest.match(TIME_RE);
            return {
                date,
                time:       tm ? `${tm[1]}:${tm[2]}` : null,
                remind_min: remindMin,
                clean:      tm ? rest.replace(TIME_RE, '').replace(/\s+/g, ' ').trim() : rest,
            };
        }
    }

    return { date: null, time: null, remind_min: remindMin, clean: src };
}

// ── Date helpers ──────────────────────────────────────────

function formatDueBadge(dateStr, timeStr) {
    if (!dateStr) return '';
    const label = formatDueDate(dateStr);
    return timeStr ? `${label} ${timeStr}` : label;
}

function isOverdue(dateStr) {
    if (!dateStr) return false;
    return dateStr < new Date().toISOString().slice(0, 10);
}

function formatDueDate(dateStr) {
    if (!dateStr) return '';
    const [y, mo, d] = dateStr.split('-').map(Number);
    const today = new Date();
    const due   = new Date(y, mo - 1, d);
    const diff  = Math.round((due - new Date(today.getFullYear(), today.getMonth(), today.getDate())) / 86400000);
    if (diff === 0)  return 'Heute';
    if (diff === 1)  return 'Morgen';
    if (diff === -1) return 'Gestern';
    if (diff < 0)    return `Vor ${-diff}d`;
    if (diff < 7)    return `In ${diff}d`;
    return due.toLocaleDateString('de-DE', { day: 'numeric', month: 'short' });
}

// ── Render ────────────────────────────────────────────────

function getVisible() {
    let list = tasks;
    if (searchQuery) {
        const q = searchQuery.toLowerCase();
        list = list.filter(t => t.title.toLowerCase().includes(q) ||
                                (t.notes || '').toLowerCase().includes(q));
    } else {
        if (filter === 'pending') list = list.filter(t => !t.done);
        if (filter === 'done')    list = list.filter(t =>  t.done);
    }
    return list;
}

function getListName(listId) {
    if (!listId) return null;
    return lists.find(l => l.id === listId)?.name ?? null;
}

function render() {
    const visible = getVisible();
    const list    = document.getElementById('task-list');
    const empty   = document.getElementById('empty-state');
    const emptyMsg = document.getElementById('empty-msg');
    const badge   = document.getElementById('badge');

    list.innerHTML = '';

    const pending   = tasks.filter(t => !t.done);
    const overdueN  = pending.filter(t => isOverdue(t.due_date)).length;
    const badgeN    = pending.length;

    badge.textContent = badgeN;
    badge.classList.toggle('overdue', overdueN > 0);
    bridge({ type: 'badge', count: badgeN, hasOverdue: overdueN > 0 });
    updateListCounts();

    // Clean btn visibility
    const doneCount = tasks.filter(t => t.done).length;
    document.getElementById('footer-actions').style.display = doneCount > 0 ? '' : 'none';

    if (visible.length === 0) {
        emptyMsg.textContent = searchQuery ? 'Keine Ergebnisse' : filter === 'done' ? 'Noch nichts erledigt' : 'Alles erledigt';
        empty.classList.remove('hidden');
        return;
    }
    empty.classList.add('hidden');

    visible.forEach(task => {
        const el = buildTaskEl(task);
        list.appendChild(el);
    });
}

function buildTaskEl(task) {
    const overdue   = isOverdue(task.due_date);
    const subtasks  = task.subtasks || [];
    const doneCount = subtasks.filter(s => s.done).length;
    const isExpanded = expandedIds.has(task.id);

    const el = document.createElement('div');
    el.className = `task-item${task.done ? ' done' : ''}${selectedTaskId === task.id ? ' selected' : ''}`;
    el.dataset.id = task.id;
    el.draggable  = !task.done;

    el.addEventListener('click', () => { selectedTaskId = task.id; updateSelection(); });
    el.addEventListener('contextmenu', e => { e.preventDefault(); selectedTaskId = task.id; updateSelection(); showTaskContextMenu(task, e.clientX, e.clientY); });

    // ── Main row ──
    const main = document.createElement('div');
    main.className = 'task-main';

    // Checkbox
    const chk = document.createElement('div');
    chk.className = 'task-check';
    chk.innerHTML = `<div class="check-inner">✓</div>`;
    chk.addEventListener('click', () => toggleDone(task.id));

    // Priority bar
    const pbar = document.createElement('div');
    pbar.className = `priority-bar p${task.priority}`;

    // Body
    const body = document.createElement('div');
    body.className = 'task-body';

    const titleEl = document.createElement('div');
    titleEl.className = 'task-title';
    titleEl.textContent = task.title;
    titleEl.addEventListener('dblclick', () => startEdit(task.id, titleEl));

    const meta = document.createElement('div');
    meta.className = 'task-meta';

    if (task.due_date) {
        const badge = document.createElement('span');
        badge.className = `due-badge${overdue ? ' overdue' : ''}`;
        badge.textContent = formatDueBadge(task.due_date, task.due_time);
        meta.appendChild(badge);
    }

    if (task.remind_min) {
        const rb = document.createElement('span');
        rb.className = 'remind-badge';
        rb.title = `Erinnerung ${task.remind_min} Minuten vorher`;
        rb.textContent = `🔔 ${task.remind_min} Min.`;
        meta.appendChild(rb);
    }

    if (lists.length > 0) {
        if (task.list_id) {
            const listColor = lists.find(l => l.id === task.list_id)?.color ?? '#007AFF';
            const listBadge = document.createElement('button');
            listBadge.className = 'list-badge list-badge-btn';
            listBadge.style.setProperty('--lc', listColor);
            listBadge.textContent = getListName(task.list_id) ?? '';
            listBadge.title = 'Liste ändern';
            listBadge.addEventListener('click', e => { e.stopPropagation(); showListPicker(task.id, listBadge); });
            meta.appendChild(listBadge);
        } else {
            const assignBtn = document.createElement('button');
            assignBtn.className = 'list-assign-btn';
            assignBtn.title = 'Liste zuweisen';
            assignBtn.textContent = '+ Liste';
            assignBtn.addEventListener('click', e => { e.stopPropagation(); showListPicker(task.id, assignBtn); });
            meta.appendChild(assignBtn);
        }
    }

    if (subtasks.length > 0) {
        const pill = document.createElement('span');
        pill.className = 'subtask-pill';
        pill.textContent = `${doneCount}/${subtasks.length}`;
        meta.appendChild(pill);
    }

    body.appendChild(titleEl);
    if (meta.children.length) body.appendChild(meta);

    // Actions
    const actions = document.createElement('div');
    actions.className = 'task-actions';

    const expandBtn = document.createElement('button');
    expandBtn.className = `task-action-btn${isExpanded ? ' expanded' : ''}`;
    expandBtn.title = 'Notizen & Unteraufgaben';
    expandBtn.innerHTML = `<svg width="12" height="12" viewBox="0 0 12 12" fill="none">
        <path d="M2 4h8M2 6h5M2 8h6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
    </svg>`;
    expandBtn.addEventListener('click', () => {
        if (expandedIds.has(task.id)) expandedIds.delete(task.id);
        else expandedIds.add(task.id);
        render();
    });

    if (lists.length > 0) {
        const listBtn = document.createElement('button');
        listBtn.className = 'task-action-btn';
        listBtn.title = 'Liste zuweisen';
        listBtn.innerHTML = `<svg width="12" height="12" viewBox="0 0 12 12" fill="none">
            <circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.3"/>
            <path d="M4 6h4M6 4v4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
        </svg>`;
        listBtn.addEventListener('click', e => { e.stopPropagation(); showListPicker(task.id, listBtn); });
        actions.appendChild(listBtn);
    }

    const delBtn = document.createElement('button');
    delBtn.className = 'task-action-btn delete';
    delBtn.title = 'Löschen';
    delBtn.innerHTML = `<svg width="11" height="11" viewBox="0 0 11 11" fill="none">
        <path d="M1 1l9 9M10 1L1 10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
    </svg>`;
    delBtn.addEventListener('click', () => deleteTask(task));

    actions.appendChild(expandBtn);
    actions.appendChild(delBtn);

    main.appendChild(chk);
    main.appendChild(pbar);
    main.appendChild(body);
    main.appendChild(actions);
    el.appendChild(main);

    // ── Expand area ──
    if (isExpanded) {
        const expand = buildExpandEl(task);
        el.appendChild(expand);
    }

    // ── Drag ──
    bindDrag(el, task);

    return el;
}

function buildExpandEl(task) {
    const expand = document.createElement('div');
    expand.className = 'task-expand';

    // Notes
    const notes = document.createElement('textarea');
    notes.className = 'notes-area';
    notes.placeholder = 'Notizen hinzufügen…';
    notes.value = task.notes || '';
    notes.rows = 2;

    let notesTimer;
    notes.addEventListener('input', () => {
        clearTimeout(notesTimer);
        notesTimer = setTimeout(() => saveNotes(task.id, notes.value), 600);
    });

    // Subtasks
    const subtaskSection = document.createElement('div');
    subtaskSection.className = 'subtask-list';

    (task.subtasks || []).forEach((sub, idx) => {
        subtaskSection.appendChild(buildSubtaskEl(task, sub, idx));
    });

    const addSubBtn = document.createElement('button');
    addSubBtn.className = 'add-subtask-btn';
    addSubBtn.textContent = '+ Unteraufgabe hinzufügen';
    addSubBtn.addEventListener('click', () => addSubtask(task.id));

    expand.appendChild(notes);
    expand.appendChild(subtaskSection);
    expand.appendChild(addSubBtn);

    return expand;
}

function buildSubtaskEl(task, sub, idx) {
    const row = document.createElement('div');
    row.className = 'subtask-item';

    const chk = document.createElement('div');
    chk.className = `subtask-check${sub.done ? ' checked' : ''}`;
    chk.textContent = sub.done ? '✓' : '';
    chk.addEventListener('click', () => toggleSubtask(task.id, idx));

    const title = document.createElement('span');
    title.className = `subtask-title${sub.done ? ' done-text' : ''}`;
    title.contentEditable = 'true';
    title.textContent = sub.title;
    title.addEventListener('blur', () => renameSubtask(task.id, idx, title.textContent));
    title.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); title.blur(); }
    });

    const del = document.createElement('button');
    del.className = 'subtask-del';
    del.textContent = '✕';
    del.addEventListener('click', () => deleteSubtask(task.id, idx));

    row.appendChild(chk);
    row.appendChild(title);
    row.appendChild(del);
    return row;
}

// ── Task actions ──────────────────────────────────────────

async function toggleDone(id) {
    haptic();
    const task = tasks.find(t => t.id === id);
    if (!task) return;

    const newDone = !task.done;
    task.done = newDone;
    render();

    await apiFetch(`/${id}`, { method: 'PUT', body: JSON.stringify({ done: newDone }) });

    if (newDone) {
        sound('Pop');
        // Update stats after completing
        loadStats();
    }
}

function deleteTask(task) {
    // Optimistic remove + undo toast
    tasks = tasks.filter(t => t.id !== task.id);
    expandedIds.delete(task.id);
    render();

    if (undoTimer) {
        clearTimeout(undoTimer);
        if (undoPending) undoPending.onConfirm();
    }

    showUndoToast(task);
}

function showUndoToast(task) {
    const toast   = document.getElementById('undo-toast');
    const undoBtn = document.getElementById('undo-btn');

    toast.classList.remove('hidden');

    undoPending = {
        task,
        onConfirm: () => apiFetch(`/${task.id}`, { method: 'DELETE' }),
    };

    const cleanup = () => {
        toast.classList.add('hidden');
        undoPending = null;
        undoTimer   = null;
    };

    undoBtn.onclick = () => {
        clearTimeout(undoTimer);
        // Restore
        tasks = [...tasks, task].sort((a, b) => a.position - b.position || a.id - b.id);
        render();
        cleanup();
    };

    undoTimer = setTimeout(() => {
        if (undoPending) undoPending.onConfirm();
        cleanup();
    }, 5000);
}

// ── Inline title edit ─────────────────────────────────────

function startEdit(id, el) {
    const task = tasks.find(t => t.id === id);
    if (!task) return;

    el.contentEditable = 'true';
    el.classList.add('is-editing');
    el.focus();
    const range = document.createRange();
    range.selectNodeContents(el);
    getSelection().removeAllRanges();
    getSelection().addRange(range);

    const hint = document.createElement('div');
    hint.className = 'task-edit-hint hidden';
    el.after(hint);

    const updateHint = () => {
        const { date, time, remind_min } = extractNL(el.textContent);
        if (date || remind_min) {
            const parts = [];
            if (date) parts.push(`📅 ${formatDueDate(date)}${time ? ` · ${time} Uhr` : ''}`);
            if (remind_min) parts.push(`🔔 ${remind_min} Min. vorher`);
            hint.textContent = parts.join('  ');
            hint.classList.remove('hidden');
        } else {
            hint.classList.add('hidden');
        }
    };

    el.addEventListener('input', updateHint);

    let saved = false;

    const cleanup = () => {
        hint.remove();
        el.classList.remove('is-editing');
        el.removeEventListener('input', updateHint);
    };

    const save = async () => {
        if (saved) return;
        saved = true;
        el.contentEditable = 'false';
        cleanup();
        const raw = el.textContent.trim();
        if (!raw) { el.textContent = task.title; return; }

        const { date: nlDate, time: nlTime, remind_min: nlRemind, clean } = extractNL(raw);
        const newTitle = clean || raw;

        if (newTitle === task.title && !nlDate && !nlTime && nlRemind === null) return;

        const updates = {};
        if (newTitle !== task.title) updates.title      = newTitle;
        if (nlDate)                  updates.due_date   = nlDate;
        if (nlTime)                  updates.due_time   = nlTime;
        if (nlRemind !== null)       updates.remind_min = nlRemind;

        if (!Object.keys(updates).length) return;

        if (updates.title)      task.title      = updates.title;
        if (updates.due_date)   task.due_date   = updates.due_date;
        if (updates.due_time)   task.due_time   = updates.due_time;
        if (updates.remind_min) task.remind_min = updates.remind_min;

        el.textContent = task.title;
        await apiFetch(`/${id}`, { method: 'PUT', body: JSON.stringify(updates) });
        render();
    };

    el.addEventListener('blur', save);

    el.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            el.blur();
        }
        if (e.key === 'Escape') {
            saved = true;
            el.contentEditable = 'false';
            el.textContent = task.title;
            cleanup();
            el.blur();
        }
    });
}

// ── Notes ─────────────────────────────────────────────────

async function saveNotes(id, notes) {
    const task = tasks.find(t => t.id === id);
    if (!task) return;
    task.notes = notes;
    await apiFetch(`/${id}`, { method: 'PUT', body: JSON.stringify({ notes }) });
}

// ── Subtasks ──────────────────────────────────────────────

async function addSubtask(taskId) {
    const task = tasks.find(t => t.id === taskId);
    if (!task) return;
    task.subtasks = [...(task.subtasks || []), { title: 'Neue Unteraufgabe', done: false }];
    await apiFetch(`/${taskId}`, { method: 'PUT', body: JSON.stringify({ subtasks: task.subtasks }) });
    render();
}

async function toggleSubtask(taskId, idx) {
    haptic();
    const task = tasks.find(t => t.id === taskId);
    if (!task) return;
    task.subtasks[idx].done = !task.subtasks[idx].done;
    if (task.subtasks[idx].done) sound('Pop');
    await apiFetch(`/${taskId}`, { method: 'PUT', body: JSON.stringify({ subtasks: task.subtasks }) });
    render();
}

async function renameSubtask(taskId, idx, title) {
    const task = tasks.find(t => t.id === taskId);
    if (!task || !title.trim()) return;
    task.subtasks[idx].title = title.trim();
    await apiFetch(`/${taskId}`, { method: 'PUT', body: JSON.stringify({ subtasks: task.subtasks }) });
}

async function deleteSubtask(taskId, idx) {
    const task = tasks.find(t => t.id === taskId);
    if (!task) return;
    task.subtasks.splice(idx, 1);
    await apiFetch(`/${taskId}`, { method: 'PUT', body: JSON.stringify({ subtasks: task.subtasks }) });
    render();
}

// ── Add task ──────────────────────────────────────────────

async function addTask() {
    const input = document.getElementById('new-task-input');
    let rawTitle = input.value.trim();
    if (!rawTitle) return;

    const { date: nlDate, time: nlTime, remind_min: nlRemind, clean } = extractNL(rawTitle);
    const title     = clean;
    const dueDate   = nlDate || selectedDueDate || null;
    const dueTime   = nlTime || null;
    const remindMin = nlRemind || null;

    input.value = '';
    document.getElementById('nl-hint').classList.add('hidden');
    resetDueDate();

    const task = await apiFetch('', {
        method: 'POST',
        body: JSON.stringify({ title, priority: selectedPriority, due_date: dueDate, due_time: dueTime, remind_min: remindMin, list_id: currentListId }),
    });
    if (!task) return;
    tasks.unshift(task);
    tasks = tasks.sort((a, b) => a.done - b.done || a.position - b.position);
    render();
    haptic();
}

// ── Clean completed ───────────────────────────────────────

async function cleanCompleted() {
    const r = await apiFetch('/done', { method: 'DELETE' });
    if (!r) return;
    tasks = tasks.filter(t => !t.done);
    render();
    sound('Funk');
    loadStats();
}

// ── Copy to clipboard ─────────────────────────────────────

function copyMarkdown() {
    const pending = tasks.filter(t => !t.done);
    const done    = tasks.filter(t =>  t.done);

    let md = '# Aufgaben\n\n';
    if (pending.length) {
        md += '## Offen\n';
        pending.forEach(t => {
            md += `- [ ] ${t.title}`;
            if (t.due_date) md += ` _(${formatDueDate(t.due_date)})_`;
            md += '\n';
            (t.subtasks || []).forEach(s => {
                md += `  - [${s.done ? 'x' : ' '}] ${s.title}\n`;
            });
        });
        md += '\n';
    }
    if (done.length) {
        md += '## Erledigt\n';
        done.forEach(t => { md += `- [x] ${t.title}\n`; });
    }

    bridge({ type: 'copy', text: md });

    // Visual feedback
    const btn = document.getElementById('copy-btn');
    btn.style.color = 'var(--color-accent)';
    setTimeout(() => { btn.style.color = ''; }, 1200);
}

// ── Stats ─────────────────────────────────────────────────

async function loadStats() {
    try {
        const s = await fetch(`${location.origin}/api/stats`).then(r => r.json());
        const streak = document.getElementById('stat-streak');
        const week   = document.getElementById('stat-week');
        streak.textContent = s.streak > 0 ? `🔥 ${s.streak}d Serie` : '🔥 keine Serie';
        week.textContent   = `✓ ${s.week} diese Woche`;
    } catch (_) {}
}

// ── Search ────────────────────────────────────────────────

function openSearch() {
    searchQuery = '';
    document.getElementById('search-row').classList.remove('hidden');
    document.getElementById('filter-tabs').classList.add('hidden');
    document.getElementById('search-toggle-btn').classList.add('active');
    const input = document.getElementById('search-input');
    input.value = '';
    input.focus();
}

function closeSearch() {
    searchQuery = '';
    document.getElementById('search-row').classList.add('hidden');
    document.getElementById('filter-tabs').classList.remove('hidden');
    document.getElementById('search-toggle-btn').classList.remove('active');
    render();
}

// ── Due date UI ───────────────────────────────────────────

function updateDateBtn() {
    const btn = document.getElementById('calendar-btn');
    btn.classList.toggle('has-date', !!selectedDueDate);
}

function resetDueDate() {
    selectedDueDate = '';
    document.getElementById('due-date-input').value = '';
    document.getElementById('date-row').classList.add('hidden');
    document.getElementById('calendar-btn').classList.remove('has-date');
}

// ── Drag & drop ───────────────────────────────────────────

function bindDrag(el, task) {
    if (task.done) return;

    el.addEventListener('dragstart', e => {
        dragSrcEl = el;
        el.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', task.id);
    });

    el.addEventListener('dragend', () => {
        el.classList.remove('dragging');
        document.querySelectorAll('.task-item').forEach(i => i.classList.remove('drag-over'));
        saveOrder();
    });

    el.addEventListener('dragover', e => {
        e.preventDefault();
        if (dragSrcEl && dragSrcEl !== el && !task.done) {
            document.querySelectorAll('.task-item').forEach(i => i.classList.remove('drag-over'));
            el.classList.add('drag-over');
            const list = document.getElementById('task-list');
            const items = [...list.querySelectorAll('.task-item:not(.done)')];
            const srcIdx = items.indexOf(dragSrcEl);
            const dstIdx = items.indexOf(el);
            if (srcIdx >= 0 && dstIdx >= 0 && srcIdx !== dstIdx) {
                if (srcIdx < dstIdx) list.insertBefore(dragSrcEl, el.nextSibling);
                else                 list.insertBefore(dragSrcEl, el);
            }
        }
    });

    el.addEventListener('drop', e => { e.preventDefault(); });
}

async function saveOrder() {
    const ids = [...document.querySelectorAll('.task-item:not(.done)')]
        .map(el => +el.dataset.id);
    ids.forEach((id, pos) => {
        const t = tasks.find(t => t.id === id);
        if (t) t.position = pos;
    });
    await apiFetch('/reorder', { method: 'POST', body: JSON.stringify({ ids }) });
}

// ── Selection ─────────────────────────────────────────────

let selectedTaskId = null;

function updateSelection() {
    document.querySelectorAll('.task-item').forEach(el => {
        el.classList.toggle('selected', +el.dataset.id === selectedTaskId);
    });
}

// ── Context menu ──────────────────────────────────────────

function showTaskContextMenu(task, x, y) {
    document.querySelector('.task-ctx-menu')?.remove();

    const menu = document.createElement('div');
    menu.className = 'task-ctx-menu';

    const opt = (label, icon, fn, cls = '') => {
        const btn = document.createElement('button');
        btn.className = `ctx-opt${cls ? ' ' + cls : ''}`;
        btn.innerHTML = `<span class="ctx-icon">${icon}</span><span>${label}</span>`;
        btn.addEventListener('click', () => { menu.remove(); fn(); });
        return btn;
    };

    menu.appendChild(opt('Vollbild öffnen', '⤢', () => openDetailWindow(task.id)));
    menu.appendChild(opt('Titel bearbeiten', '✎', () => {
        const titleEl = document.querySelector(`.task-item[data-id="${task.id}"] .task-title`);
        if (titleEl) startEdit(task.id, titleEl);
    }));

    if (lists.length > 0) {
        const sep = document.createElement('div');
        sep.className = 'ctx-sep';
        menu.appendChild(sep);

        const hdr = document.createElement('div');
        hdr.className = 'ctx-hdr';
        hdr.textContent = 'Liste zuweisen';
        menu.appendChild(hdr);

        const noList = document.createElement('button');
        noList.className = `ctx-opt ctx-list-opt${!task.list_id ? ' active' : ''}`;
        noList.innerHTML = `<span class="ctx-icon">○</span><span>Ohne Liste</span>`;
        noList.addEventListener('click', () => { menu.remove(); assignList(task.id, null); });
        menu.appendChild(noList);

        lists.forEach(l => {
            const btn = document.createElement('button');
            btn.className = `ctx-opt ctx-list-opt${task.list_id === l.id ? ' active' : ''}`;
            btn.innerHTML = `<span class="ctx-icon list-picker-dot" style="background:${l.color};width:8px;height:8px;border-radius:50%;display:inline-block"></span><span>${l.name}</span>`;
            btn.addEventListener('click', () => { menu.remove(); assignList(task.id, l.id); });
            menu.appendChild(btn);
        });
    }

    const sep2 = document.createElement('div');
    sep2.className = 'ctx-sep';
    menu.appendChild(sep2);
    menu.appendChild(opt('Löschen', '✕', () => deleteTask(task), 'ctx-danger'));

    document.body.appendChild(menu);

    const mw = 180, mh = menu.offsetHeight || 200;
    menu.style.left = `${Math.min(x, window.innerWidth  - mw - 4)}px`;
    menu.style.top  = `${Math.min(y, window.innerHeight - mh - 4)}px`;

    const close = e => { if (!menu.contains(e.target)) { menu.remove(); document.removeEventListener('click', close, true); } };
    setTimeout(() => document.addEventListener('click', close, true), 0);
}

// ── Detail window via NSPanel (⌘L) ───────────────────────

function openDetailWindow(taskId) {
    const task   = taskId ? tasks.find(t => t.id === taskId) : null;
    const listId = task?.list_id ?? (currentListId ?? null);
    bridge({ type: 'openDetail', taskId: taskId ?? null, listId });
}

function openFullscreen_UNUSED(taskId) {
    document.querySelector('.fs-overlay')?.remove();

    const task = taskId ? tasks.find(t => t.id === taskId) : null;
    const overlay = document.createElement('div');
    overlay.className = 'fs-overlay';

    // Header bar
    const hdr = document.createElement('div');
    hdr.className = 'fs-hdr';

    const backBtn = document.createElement('button');
    backBtn.className = 'fs-back';
    backBtn.innerHTML = '← Volver';
    backBtn.addEventListener('click', () => overlay.remove());
    hdr.appendChild(backBtn);

    const titleHdr = document.createElement('span');
    titleHdr.className = 'fs-hdr-title';
    titleHdr.textContent = task ? (task.title.length > 30 ? task.title.slice(0,28) + '…' : task.title) : (lists.find(l => l.id === currentListId)?.name ?? 'Todas las tareas');
    hdr.appendChild(titleHdr);
    overlay.appendChild(hdr);

    // Content
    const content = document.createElement('div');
    content.className = 'fs-content';

    if (task) {
        // ── Task detail ──
        const chkRow = document.createElement('div');
        chkRow.className = 'fs-check-row';

        const chk = document.createElement('div');
        chk.className = `fs-check${task.done ? ' done' : ''}`;
        chk.innerHTML = task.done ? '✓' : '';
        chk.addEventListener('click', async () => { await toggleDone(task.id); overlay.remove(); });
        chkRow.appendChild(chk);

        const titleEl = document.createElement('div');
        titleEl.className = `fs-title${task.done ? ' done' : ''}`;
        titleEl.contentEditable = 'true';
        titleEl.textContent = task.title;
        titleEl.addEventListener('blur', async () => {
            const t = titleEl.textContent.trim();
            if (t && t !== task.title) { task.title = t; await apiFetch(`/${task.id}`, { method: 'PUT', body: JSON.stringify({ title: t }) }); render(); }
        });
        titleEl.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); titleEl.blur(); } });
        chkRow.appendChild(titleEl);
        content.appendChild(chkRow);

        // Meta row
        const metaRow = document.createElement('div');
        metaRow.className = 'fs-meta';

        if (task.due_date) {
            const overdue = isOverdue(task.due_date);
            const db = document.createElement('span');
            db.className = `due-badge${overdue ? ' overdue' : ''}`;
            db.textContent = formatDueDate(task.due_date);
            metaRow.appendChild(db);
        }

        if (task.list_id) {
            const lc = lists.find(l => l.id === task.list_id)?.color ?? '#007AFF';
            const lb = document.createElement('span');
            lb.className = 'list-badge';
            lb.style.setProperty('--lc', lc);
            lb.textContent = getListName(task.list_id) ?? '';
            metaRow.appendChild(lb);
        }

        if (metaRow.children.length) content.appendChild(metaRow);

        // Notes
        const notesLabel = document.createElement('div');
        notesLabel.className = 'fs-label';
        notesLabel.textContent = 'Notas';
        content.appendChild(notesLabel);

        const notes = document.createElement('textarea');
        notes.className = 'fs-notes';
        notes.placeholder = 'Notizen hinzufügen…';
        notes.value = task.notes || '';
        let notesTimer;
        notes.addEventListener('input', () => {
            clearTimeout(notesTimer);
            notesTimer = setTimeout(() => saveNotes(task.id, notes.value), 600);
        });
        content.appendChild(notes);

        // Subtasks
        if ((task.subtasks || []).length > 0 || true) {
            const subLabel = document.createElement('div');
            subLabel.className = 'fs-label';
            subLabel.textContent = 'Subtareas';
            content.appendChild(subLabel);

            const subList = document.createElement('div');
            subList.className = 'subtask-list';
            (task.subtasks || []).forEach((sub, idx) => subList.appendChild(buildSubtaskEl(task, sub, idx)));
            content.appendChild(subList);

            const addSubBtn = document.createElement('button');
            addSubBtn.className = 'add-subtask-btn';
            addSubBtn.textContent = '+ Unteraufgabe hinzufügen';
            addSubBtn.addEventListener('click', async () => { await addSubtask(task.id); overlay.remove(); });
            content.appendChild(addSubBtn);
        }
    } else {
        // ── List / all tasks detail ──
        const listTasks = currentListId !== null
            ? tasks.filter(t => t.list_id === currentListId)
            : tasks;

        const pending   = listTasks.filter(t => !t.done);
        const done      = listTasks.filter(t =>  t.done);

        const renderSection = (title, arr) => {
            if (!arr.length) return;
            const lbl = document.createElement('div');
            lbl.className = 'fs-label';
            lbl.textContent = title;
            content.appendChild(lbl);
            arr.forEach(t => {
                const row = document.createElement('div');
                row.className = `fs-task-row${t.done ? ' done' : ''}`;
                const chk = document.createElement('div');
                chk.className = `fs-mini-check${t.done ? ' done' : ''}`;
                chk.textContent = t.done ? '✓' : '';
                chk.addEventListener('click', async () => { await toggleDone(t.id); overlay.remove(); });
                const lbl2 = document.createElement('span');
                lbl2.textContent = t.title;
                row.appendChild(chk);
                row.appendChild(lbl2);
                content.appendChild(row);
            });
        };

        renderSection(`Pendientes (${pending.length})`, pending);
        renderSection(`Completadas (${done.length})`, done);

        if (!listTasks.length) {
            const empty = document.createElement('div');
            empty.className = 'fs-empty';
            empty.textContent = 'Sin tareas';
            content.appendChild(empty);
        }
    }

    overlay.appendChild(content);
    document.querySelector('.app').appendChild(overlay);

    const keyHandler = e => {
        if (e.key === 'Escape' || (e.metaKey && e.key === 'l')) {
            e.preventDefault();
            overlay.remove();
            document.removeEventListener('keydown', keyHandler);
        }
    };
    document.addEventListener('keydown', keyHandler);
}

// ── List picker ───────────────────────────────────────────

let activePicker = null;

function showListPicker(taskId, anchor) {
    if (activePicker) { activePicker.remove(); activePicker = null; }

    const picker = document.createElement('div');
    picker.className = 'list-picker';
    activePicker = picker;

    const makeOpt = (label, id, color) => {
        const opt = document.createElement('button');
        opt.className = 'list-picker-opt';
        if (color) {
            const dot = document.createElement('span');
            dot.className = 'list-picker-dot';
            dot.style.background = color;
            opt.appendChild(dot);
        }
        const lbl = document.createElement('span');
        lbl.textContent = label;
        opt.appendChild(lbl);
        opt.addEventListener('click', () => { assignList(taskId, id); picker.remove(); activePicker = null; });
        return opt;
    };

    picker.appendChild(makeOpt('Ohne Liste', null, null));
    lists.forEach(l => picker.appendChild(makeOpt(l.name, l.id, l.color)));

    document.body.appendChild(picker);

    const rect = anchor.getBoundingClientRect();
    picker.style.top  = `${rect.bottom + 4}px`;
    picker.style.left = `${rect.left}px`;

    const close = e => {
        if (!picker.contains(e.target)) { picker.remove(); activePicker = null; document.removeEventListener('click', close, true); }
    };
    setTimeout(() => document.addEventListener('click', close, true), 0);
}

async function assignList(taskId, listId) {
    const task = tasks.find(t => t.id === taskId);
    if (!task) return;
    task.list_id = listId;
    await apiFetch(`/${taskId}`, { method: 'PUT', body: JSON.stringify({ list_id: listId }) });
    if (currentListId !== null && task.list_id !== currentListId) {
        tasks = tasks.filter(t => t.id !== taskId);
    }
    render();
}

// ── Lists ─────────────────────────────────────────────────

const LIST_COLORS = ['#007AFF','#34C759','#FF9F0A','#FF453A','#AF52DE','#FF2D55','#5AC8FA','#30B0C7'];

function getPendingCount(listId) {
    if (listId === null) return tasks.filter(t => !t.done).length;
    return tasks.filter(t => !t.done && t.list_id === listId).length;
}

function updateListCounts() {
    document.querySelectorAll('.list-chip[data-list-id]').forEach(chip => {
        const raw = chip.dataset.listId;
        const id  = raw === 'null' ? null : +raw;
        const el  = chip.querySelector('.list-count');
        if (!el) return;
        const n = getPendingCount(id);
        el.textContent    = n;
        el.style.display  = n > 0 ? '' : 'none';
    });
}

async function loadLists() {
    try {
        lists = await fetch(`${location.origin}/api/lists`).then(r => r.json());
    } catch (_) { lists = []; }
    renderLists();
}

function renderLists() {
    const bar = document.getElementById('lists-bar');
    bar.innerHTML = '';

    const section = document.createElement('div');
    section.className = 'sidebar-section';
    section.textContent = 'Listen';
    bar.appendChild(section);

    bar.appendChild(makeListChip('Alle', null, null));

    if (lists.length > 0) {
        const sep = document.createElement('div');
        sep.className = 'sidebar-sep';
        bar.appendChild(sep);
        lists.forEach(list => bar.appendChild(makeListChip(list.name, list.id, list.color)));
    }

    const spacer = document.createElement('div');
    spacer.className = 'sidebar-spacer';
    bar.appendChild(spacer);

    const addBtn = document.createElement('button');
    addBtn.className = 'list-add-btn';
    addBtn.title = 'Neue Liste erstellen';
    addBtn.innerHTML = '+ Neue Liste';
    addBtn.addEventListener('click', () => startCreateList(bar, addBtn));
    bar.appendChild(addBtn);
}

function makeListChip(name, id, color) {
    const chip = document.createElement('div');
    chip.className = `list-chip${currentListId === id ? ' active' : ''}`;
    chip.dataset.listId = id === null ? 'null' : id;

    if (color && id !== null) {
        const dot = document.createElement('span');
        dot.className = 'list-chip-dot';
        dot.style.background = color;
        chip.appendChild(dot);
    }

    const label = document.createElement('span');
    label.className = 'list-chip-label';
    label.textContent = name;
    chip.appendChild(label);

    const countEl = document.createElement('span');
    countEl.className = 'list-count';
    const n = getPendingCount(id);
    countEl.textContent   = n;
    countEl.style.display = n > 0 ? '' : 'none';
    chip.appendChild(countEl);

    if (id !== null) {
        const del = document.createElement('button');
        del.className = 'list-chip-del';
        del.innerHTML = '✕';
        del.title = 'Liste löschen';
        del.addEventListener('click', e => { e.stopPropagation(); deleteList(id); });
        chip.appendChild(del);

        label.addEventListener('dblclick', e => { e.stopPropagation(); startRenameList(id, chip, label); });
    }

    chip.addEventListener('click', () => selectList(id));
    return chip;
}

function selectList(id) {
    currentListId = id;
    renderLists();
    load();
}

async function deleteList(id) {
    await fetch(`${location.origin}/api/lists/${id}`, { method: 'DELETE' });
    lists = lists.filter(l => l.id !== id);
    if (currentListId === id) currentListId = null;
    renderLists();
    load();
}

function startCreateList(bar, addBtn) {
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'list-name-input';
    input.placeholder = 'Listenname…';
    input.maxLength = 30;
    bar.insertBefore(input, addBtn);
    input.focus();

    const commit = async () => {
        const name = input.value.trim();
        input.remove();
        if (!name) { renderLists(); return; }
        const color = LIST_COLORS[lists.length % LIST_COLORS.length];
        const res = await fetch(`${location.origin}/api/lists`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ name, color }),
        }).then(r => r.json());
        lists.push(res);
        currentListId = res.id;
        renderLists();
        load();
    };

    input.addEventListener('blur', commit);
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter')  { e.preventDefault(); input.blur(); }
        if (e.key === 'Escape') { input.value = ''; input.blur(); }
    });
}

function startRenameList(id, chip, labelEl) {
    const list = lists.find(l => l.id === id);
    if (!list) return;

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'list-name-input';
    input.value = list.name;
    input.maxLength = 30;
    chip.replaceWith(input);
    input.focus();
    input.select();

    const commit = async () => {
        const name = input.value.trim() || list.name;
        list.name  = name;
        await fetch(`${location.origin}/api/lists/${id}`, {
            method:  'PUT',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ name }),
        });
        renderLists();
    };

    input.addEventListener('blur', commit);
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter')  { e.preventDefault(); input.blur(); }
        if (e.key === 'Escape') { input.value = list.name; input.blur(); }
    });
}

// ── Load ──────────────────────────────────────────────────

async function load() {
    const qs = currentListId !== null ? `?list_id=${currentListId}` : '';
    tasks = await apiFetch(qs) || [];
    render();
    loadStats();
}

// ── Event bindings ────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    // Load tasks + lists
    load();
    loadLists();

    // Add task
    const addBtn   = document.getElementById('add-btn');
    const taskInput = document.getElementById('new-task-input');

    addBtn.addEventListener('click', addTask);
    taskInput.addEventListener('keydown', e => { if (e.key === 'Enter') addTask(); });

    // Natural language hints
    taskInput.addEventListener('input', () => {
        const val  = taskInput.value;
        const hint = document.getElementById('nl-hint');
        const { date, time, remind_min } = extractNL(val);
        if (date || remind_min) {
            const parts = [];
            if (date) parts.push(`📅 ${formatDueDate(date)}${time ? ` · ${time} Uhr` : ''}`);
            if (remind_min) parts.push(`🔔 ${remind_min} Min. vorher`);
            hint.textContent = parts.join('  ');
            hint.classList.remove('hidden');
        } else {
            hint.classList.add('hidden');
        }
    });

    // Search
    document.getElementById('search-toggle-btn').addEventListener('click', openSearch);
    document.getElementById('search-close-btn').addEventListener('click', closeSearch);
    document.getElementById('search-input').addEventListener('input', e => {
        searchQuery = e.target.value;
        render();
    });

    // Filter tabs
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            filter = tab.dataset.filter;
            document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            render();
        });
    });

    // Copy
    document.getElementById('copy-btn').addEventListener('click', copyMarkdown);

    // Clean
    document.getElementById('clean-btn').addEventListener('click', cleanCompleted);

    // Calendar
    const calBtn    = document.getElementById('calendar-btn');
    const dateRow   = document.getElementById('date-row');
    const dateInput = document.getElementById('due-date-input');
    const clearDate = document.getElementById('clear-date-btn');

    calBtn.addEventListener('click', () => dateRow.classList.toggle('hidden'));
    dateInput.addEventListener('change', () => {
        selectedDueDate = dateInput.value;
        updateDateBtn();
    });
    clearDate.addEventListener('click', resetDueDate);

    // Priority picker
    const picker  = document.getElementById('priority-picker');
    const priMenu = document.getElementById('priority-menu');
    const priDot  = document.getElementById('prio-dot');

    picker.addEventListener('click', e => {
        e.stopPropagation();
        priMenu.classList.toggle('hidden');
    });

    document.querySelectorAll('.prio-option').forEach(btn => {
        btn.addEventListener('click', () => {
            selectedPriority = +btn.dataset.prio;
            priDot.className = `priority-dot p${selectedPriority}`;
            priMenu.classList.add('hidden');
        });
    });

    document.addEventListener('click', () => priMenu.classList.add('hidden'));

    // Cmd+L → detail panel
    document.addEventListener('keydown', e => {
        if (e.metaKey && e.key === 'l') {
            e.preventDefault();
            openDetailWindow(selectedTaskId);
        }
    });
});
