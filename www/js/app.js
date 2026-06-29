/* MenuBar Tasks — Frontend v2.0 */

const API  = `${location.origin}/api/tasks`;
let tasks           = [];
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
    lun:1, lunes:1, monday:1, mon:1,
    mar:2, martes:2, tuesday:2, tue:2,
    mié:3, miercoles:3, miércoles:3, wednesday:3, wed:3,
    jue:4, jueves:4, thursday:4, thu:4,
    vie:5, viernes:5, friday:5, fri:5,
    sáb:6, sabado:6, sábado:6, saturday:6, sat:6,
    dom:0, domingo:0, sunday:0, sun:0,
};

function addDays(n) {
    const d = new Date();
    d.setDate(d.getDate() + n);
    return d.toISOString().slice(0, 10);
}

function nextWeekday(word) {
    const w = word.toLowerCase().replace(/á/g,'a').replace(/é/g,'e').replace(/ó/g,'o');
    const key = Object.keys(WEEKDAYS).find(k => w.startsWith(k));
    if (key === undefined) return null;
    const target = WEEKDAYS[key];
    const now = new Date();
    const diff = ((target - now.getDay()) + 7) % 7 || 7;
    return addDays(diff);
}

const NL_PATTERNS = [
    [/\b(hoy|today)\b/i,                     () => addDays(0)],
    [/\b(mañana|manana|tomorrow)\b/i,         () => addDays(1)],
    [/\b(pasado mañana|day after tomorrow)\b/i, () => addDays(2)],
    [/\ben\s+(\d+)\s+d[ií]as?\b/i,            m  => addDays(+m[1])],
    [/\b(\d+)\s+d[ií]as?\b/i,                 m  => addDays(+m[1])],
    [/\bpr[oó]xim[ao]\s+(\w+)\b/i,            m  => nextWeekday(m[1])],
    [/\b(lunes|martes|mi[eé]rcoles|jueves|viernes|s[aá]bado|domingo|monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i, m => nextWeekday(m[1])],
];

function extractNL(title) {
    for (const [re, fn] of NL_PATTERNS) {
        const m = title.match(re);
        if (m) {
            const date = fn(m);
            if (date) return { date, clean: title.replace(re, '').replace(/\s+/g, ' ').trim() };
        }
    }
    return { date: null, clean: title };
}

// ── Date helpers ──────────────────────────────────────────

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
    if (diff === 0)  return 'Hoy';
    if (diff === 1)  return 'Mañana';
    if (diff === -1) return 'Ayer';
    if (diff < 0)    return `Hace ${-diff}d`;
    if (diff < 7)    return `En ${diff}d`;
    return due.toLocaleDateString('es-DE', { day: 'numeric', month: 'short' });
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

    // Clean btn visibility
    const doneCount = tasks.filter(t => t.done).length;
    document.getElementById('footer-actions').style.display = doneCount > 0 ? '' : 'none';

    if (visible.length === 0) {
        emptyMsg.textContent = searchQuery ? 'Sin resultados' : filter === 'done' ? 'Nada completado aún' : 'Todo al día';
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
    el.className = `task-item${task.done ? ' done' : ''}`;
    el.dataset.id = task.id;
    el.draggable  = !task.done;

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
        badge.textContent = formatDueDate(task.due_date);
        meta.appendChild(badge);
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
    expandBtn.title = 'Notas y subtareas';
    expandBtn.innerHTML = `<svg width="12" height="12" viewBox="0 0 12 12" fill="none">
        <path d="M2 4h8M2 6h5M2 8h6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
    </svg>`;
    expandBtn.addEventListener('click', () => {
        if (expandedIds.has(task.id)) expandedIds.delete(task.id);
        else expandedIds.add(task.id);
        render();
    });

    const delBtn = document.createElement('button');
    delBtn.className = 'task-action-btn delete';
    delBtn.title = 'Eliminar';
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
    notes.placeholder = 'Agregar notas…';
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
    addSubBtn.textContent = '+ Agregar subtarea';
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
    el.focus();
    const range = document.createRange();
    range.selectNodeContents(el);
    getSelection().removeAllRanges();
    getSelection().addRange(range);

    const save = async () => {
        el.contentEditable = 'false';
        const title = el.textContent.trim();
        if (title && title !== task.title) {
            task.title = title;
            await apiFetch(`/${id}`, { method: 'PUT', body: JSON.stringify({ title }) });
        } else {
            el.textContent = task.title;
        }
    };

    el.addEventListener('blur',    save, { once: true });
    el.addEventListener('keydown', e => {
        if (e.key === 'Enter')  { e.preventDefault(); el.blur(); }
        if (e.key === 'Escape') { el.textContent = task.title; el.blur(); }
    }, { once: true });
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
    task.subtasks = [...(task.subtasks || []), { title: 'Nueva subtarea', done: false }];
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

    const { date: nlDate, clean } = extractNL(rawTitle);
    const title   = clean;
    const dueDate = nlDate || selectedDueDate || null;

    input.value = '';
    document.getElementById('nl-hint').classList.add('hidden');
    resetDueDate();

    const task = await apiFetch('', {
        method: 'POST',
        body: JSON.stringify({ title, priority: selectedPriority, due_date: dueDate }),
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

    let md = '# Tareas\n\n';
    if (pending.length) {
        md += '## Pendientes\n';
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
        md += '## Completadas\n';
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
        streak.textContent = s.streak > 0 ? `🔥 ${s.streak}d racha` : '🔥 sin racha';
        week.textContent   = `✓ ${s.week} esta semana`;
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

// ── Load ──────────────────────────────────────────────────

async function load() {
    tasks = await apiFetch('') || [];
    render();
    loadStats();
}

// ── Event bindings ────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    // Load tasks
    load();

    // Add task
    const addBtn   = document.getElementById('add-btn');
    const taskInput = document.getElementById('new-task-input');

    addBtn.addEventListener('click', addTask);
    taskInput.addEventListener('keydown', e => { if (e.key === 'Enter') addTask(); });

    // Natural language hints
    taskInput.addEventListener('input', () => {
        const val  = taskInput.value;
        const hint = document.getElementById('nl-hint');
        const { date } = extractNL(val);
        if (date) {
            hint.textContent = `📅 ${formatDueDate(date)}`;
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
});
