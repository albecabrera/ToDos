/**
 * MenuBar Tasks — Frontend v1.1
 * Features: inline edit, drag & drop, due dates, badge, login item
 */

const API = `${location.origin}/api/tasks`;
let tasks  = [];
let filter = 'all';
let selectedPriority = 2;
let selectedDueDate  = '';
let dragSrcEl = null;

// ── API ──────────────────────────────────────────────────

async function apiFetch(path, opts = {}) {
    const res = await fetch(API + path, {
        headers: { 'Content-Type': 'application/json' },
        ...opts,
    });
    if (res.status === 204) return null;
    return res.json();
}

async function loadTasks() {
    try {
        tasks = await apiFetch('');
        render();
    } catch (e) {
        console.error('Load failed:', e);
    }
}

// ── Render ───────────────────────────────────────────────

function filtered() {
    switch (filter) {
        case 'pending': return tasks.filter(t => !parseInt(t.done));
        case 'done':    return tasks.filter(t =>  parseInt(t.done));
        default:        return tasks;
    }
}

function render() {
    const list  = document.getElementById('task-list');
    const empty = document.getElementById('empty-state');
    const badge = document.getElementById('badge');

    const items   = filtered();
    const pending = tasks.filter(t => !parseInt(t.done)).length;

    badge.textContent = pending;
    badge.style.opacity = pending > 0 ? '1' : '0.45';

    // Send count to Swift for menubar badge
    sendBadge(pending);

    if (items.length === 0) {
        list.innerHTML = '';
        empty.classList.remove('hidden');
        return;
    }
    empty.classList.add('hidden');

    const pendingItems = items.filter(t => !parseInt(t.done));
    const doneItems    = items.filter(t =>  parseInt(t.done));
    const allOrdered   = [...pendingItems, ...doneItems];

    const existing = new Map([...list.querySelectorAll('[data-id]')].map(el => [el.dataset.id, el]));
    const seen     = new Set();

    allOrdered.forEach((task, idx) => {
        const key = String(task.id);
        seen.add(key);
        if (existing.has(key)) {
            syncEl(existing.get(key), task);
        } else {
            const el = buildEl(task);
            el.style.animationDelay = `${idx * 25}ms`;
            list.appendChild(el);
        }
    });

    existing.forEach((el, key) => {
        if (!seen.has(key)) animateOut(el);
    });

    insertDivider(list, pendingItems.length, doneItems.length);
}

function insertDivider(list, pendingCount, doneCount) {
    list.querySelectorAll('.section-divider').forEach(d => d.remove());
    if (filter !== 'all' || pendingCount === 0 || doneCount === 0) return;
    const firstDone = list.querySelector('.task-item.done');
    if (!firstDone) return;
    const div = document.createElement('div');
    div.className   = 'section-divider';
    div.textContent = 'Completadas';
    list.insertBefore(div, firstDone);
}

// ── Task DOM ─────────────────────────────────────────────

function buildEl(task) {
    const div = document.createElement('div');
    div.className  = `task-item${parseInt(task.done) ? ' done' : ''} entering`;
    div.dataset.id = task.id;
    div.setAttribute('role', 'listitem');
    div.setAttribute('draggable', 'true');
    div.innerHTML  = elHTML(task);
    bindEl(div, task);
    bindDrag(div);
    return div;
}

function syncEl(el, task) {
    const wasDone = el.classList.contains('done');
    const isDone  = !!parseInt(task.done);
    el.classList.toggle('done', isDone);
    el.querySelector('.task-title').textContent = task.title;
    el.querySelector('.priority-dot').className = `priority-dot p${task.priority}`;

    // Sync due badge
    const existingDue = el.querySelector('.due-badge');
    if (existingDue) existingDue.remove();
    if (task.due_date) {
        const titleEl = el.querySelector('.task-title');
        titleEl.insertAdjacentHTML('afterend', dueBadgeHTML(task.due_date));
    }

    if (wasDone !== isDone) {
        el.classList.add('entering');
        el.addEventListener('animationend', () => el.classList.remove('entering'), { once: true });
    }
}

function elHTML(task) {
    return `
        <div class="priority-dot p${task.priority}"></div>
        <span class="task-title">${esc(task.title)}</span>
        ${task.due_date ? dueBadgeHTML(task.due_date) : ''}
        <div class="task-check" data-action="toggle"
             role="checkbox" aria-checked="${parseInt(task.done) ? 'true' : 'false'}">
            <svg class="check-icon" width="9" height="7" viewBox="0 0 9 7" fill="none">
                <path d="M1 3.5l2.5 2.5L8 1" stroke="white" stroke-width="1.6"
                      stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <button class="delete-btn" data-action="delete" aria-label="Eliminar">
            <svg width="8" height="8" viewBox="0 0 8 8" fill="none">
                <path d="M1 1l6 6M7 1L1 7" stroke="currentColor"
                      stroke-width="1.5" stroke-linecap="round"/>
            </svg>
        </button>
    `;
}

function bindEl(el, task) {
    el.addEventListener('click', e => {
        const action = e.target.closest('[data-action]')?.dataset.action;
        if (action === 'toggle') toggleTask(task.id, el);
        if (action === 'delete') deleteTask(task.id, el);
    });
    // Double-click title → inline edit
    el.addEventListener('dblclick', e => {
        if (e.target.classList.contains('task-title')) {
            startEdit(task.id, e.target);
        }
    });
}

function animateOut(el) {
    el.classList.add('leaving');
    el.addEventListener('animationend', () => el.remove(), { once: true });
}

// ── Inline Edit ──────────────────────────────────────────

function startEdit(id, el) {
    if (el.contentEditable === 'true') return;
    const original = el.textContent;

    el.contentEditable = 'true';
    el.classList.add('editing');
    el.focus();

    // Select all
    const range = document.createRange();
    range.selectNodeContents(el);
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);

    let saved = false;

    const save = async (commit) => {
        if (saved) return;
        saved = true;
        el.contentEditable = 'false';
        el.classList.remove('editing');

        const newTitle = el.textContent.trim();
        if (!commit || !newTitle || newTitle === original) {
            el.textContent = original;
            return;
        }

        const task = tasks.find(t => t.id == id);
        if (task) task.title = newTitle;

        try {
            await apiFetch(`/${id}`, {
                method: 'PUT',
                body: JSON.stringify({ title: newTitle }),
            });
        } catch {
            el.textContent = original;
            if (task) task.title = original;
        }
    };

    el.addEventListener('blur',    ()  => save(true),  { once: true });
    el.addEventListener('keydown', (e) => {
        if (e.key === 'Enter')  { e.preventDefault(); save(true); }
        if (e.key === 'Escape') { el.textContent = original; save(false); }
    });
}

// ── Drag & Drop ──────────────────────────────────────────

function bindDrag(el) {
    el.addEventListener('dragstart', e => {
        dragSrcEl = el;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', el.dataset.id);
        // Small delay so the drag image renders before we apply opacity
        requestAnimationFrame(() => el.classList.add('dragging'));
    });

    el.addEventListener('dragend', () => {
        el.classList.remove('dragging');
        document.querySelectorAll('.task-item').forEach(i => i.classList.remove('drag-over'));
        dragSrcEl = null;
        saveOrder();
    });

    el.addEventListener('dragover', e => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        if (el !== dragSrcEl) el.classList.add('drag-over');
    });

    el.addEventListener('dragleave', () => el.classList.remove('drag-over'));

    el.addEventListener('drop', e => {
        e.preventDefault();
        el.classList.remove('drag-over');
        if (!dragSrcEl || el === dragSrcEl) return;

        const list   = document.getElementById('task-list');
        const items  = [...list.querySelectorAll('.task-item:not(.leaving)')];
        const srcIdx = items.indexOf(dragSrcEl);
        const dstIdx = items.indexOf(el);

        if (srcIdx < dstIdx) el.after(dragSrcEl);
        else                  el.before(dragSrcEl);
    });
}

async function saveOrder() {
    const list = document.getElementById('task-list');
    const ids  = [...list.querySelectorAll('.task-item[data-id]')]
        .map(el => parseInt(el.dataset.id));

    // Sync tasks array to visual order
    const map = new Map(tasks.map(t => [t.id, t]));
    tasks = ids.map(id => map.get(id)).filter(Boolean);

    try {
        await apiFetch('/reorder', {
            method: 'POST',
            body: JSON.stringify({ ids }),
        });
    } catch (e) {
        console.error('Reorder failed:', e);
    }
}

// ── Due Date ─────────────────────────────────────────────

function dueBadgeHTML(dateStr) {
    const overdue = isOverdue(dateStr);
    return `<span class="due-badge${overdue ? ' overdue' : ''}">${formatDueDate(dateStr)}</span>`;
}

function isOverdue(dateStr) {
    if (!dateStr) return false;
    const due   = new Date(dateStr + 'T23:59:59');
    return due < new Date();
}

function formatDueDate(dateStr) {
    const due   = new Date(dateStr + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const diff  = Math.round((due - today) / 86_400_000);

    if (diff === 0)  return 'Hoy';
    if (diff === 1)  return 'Mañana';
    if (diff === -1) return 'Ayer';
    if (diff < 0)    return `Hace ${Math.abs(diff)}d`;
    if (diff <= 7)   return `En ${diff}d`;
    return due.toLocaleDateString('es', { day: 'numeric', month: 'short' });
}

// ── Actions ──────────────────────────────────────────────

async function toggleTask(id, el) {
    const task = tasks.find(t => t.id == id);
    if (!task) return;
    task.done = parseInt(task.done) ? 0 : 1;
    syncEl(el, task);
    render();

    try {
        const updated = await apiFetch(`/${id}`, {
            method: 'PUT',
            body: JSON.stringify({ done: !!parseInt(task.done) }),
        });
        if (updated) Object.assign(task, updated);
        render();
    } catch {
        task.done = task.done ? 0 : 1;
        render();
    }
}

async function deleteTask(id, el) {
    tasks = tasks.filter(t => t.id != id);
    animateOut(el);
    render();
    try {
        await apiFetch(`/${id}`, { method: 'DELETE' });
    } catch {
        loadTasks();
    }
}

async function addTask() {
    const input = document.getElementById('new-task-input');
    const title = input.value.trim();
    if (!title) { input.focus(); return; }

    input.value = '';
    closeDatePicker();

    try {
        const task = await apiFetch('', {
            method: 'POST',
            body: JSON.stringify({
                title,
                priority: selectedPriority,
                due_date: selectedDueDate || '',
            }),
        });
        selectedDueDate = '';
        updateDateBtn();

        if (task) {
            tasks.unshift(task);
            if (filter === 'done') setFilter('all');
            render();
        }
    } catch (e) {
        console.error('Add failed:', e);
    }
}

// ── Filters ──────────────────────────────────────────────

function setFilter(f) {
    filter = f;
    document.querySelectorAll('.filter-tab').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.filter === f);
    });
    render();
}

// ── Priority picker ──────────────────────────────────────

function setPriority(p) {
    selectedPriority = p;
    document.getElementById('prio-dot').className = `priority-dot p${p}`;
    closePrioMenu();
}

function closePrioMenu() {
    document.getElementById('priority-menu').classList.add('hidden');
}

// ── Date picker ──────────────────────────────────────────

function toggleDatePicker() {
    const row = document.getElementById('date-row');
    row.classList.toggle('hidden');
    if (!row.classList.contains('hidden')) {
        document.getElementById('due-date-input').focus();
    }
}

function closeDatePicker() {
    document.getElementById('date-row').classList.add('hidden');
}

function updateDateBtn() {
    const btn = document.getElementById('calendar-btn');
    if (selectedDueDate) {
        btn.classList.add('has-date');
        btn.title = `Fecha: ${formatDueDate(selectedDueDate)}`;
    } else {
        btn.classList.remove('has-date');
        btn.title = 'Agregar fecha límite';
    }
}

// ── Swift Bridge ─────────────────────────────────────────

function sendBadge(count) {
    try {
        window.webkit?.messageHandlers?.bridge?.postMessage({ type: 'badge', count });
    } catch (_) {}
}

// ── Utils ────────────────────────────────────────────────

function esc(str) {
    return str
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ── Boot ─────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    loadTasks();

    // Filter tabs
    document.querySelectorAll('.filter-tab').forEach(btn => {
        btn.addEventListener('click', () => setFilter(btn.dataset.filter));
    });

    // Add task
    const input  = document.getElementById('new-task-input');
    const addBtn = document.getElementById('add-btn');
    addBtn.addEventListener('click', addTask);
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter')  addTask();
        if (e.key === 'Escape') { input.blur(); closePrioMenu(); closeDatePicker(); }
    });

    // Priority picker
    document.getElementById('priority-picker').addEventListener('click', e => {
        e.stopPropagation();
        document.getElementById('priority-menu').classList.toggle('hidden');
    });
    document.querySelectorAll('.prio-option').forEach(btn => {
        btn.addEventListener('click', () => setPriority(parseInt(btn.dataset.prio)));
    });

    // Date picker
    document.getElementById('calendar-btn').addEventListener('click', e => {
        e.stopPropagation();
        toggleDatePicker();
    });
    document.getElementById('due-date-input').addEventListener('change', e => {
        selectedDueDate = e.target.value;
        updateDateBtn();
        closeDatePicker();
    });
    document.getElementById('clear-date-btn').addEventListener('click', () => {
        selectedDueDate = '';
        document.getElementById('due-date-input').value = '';
        updateDateBtn();
        closeDatePicker();
    });

    // Close menus on outside click
    document.addEventListener('click', () => {
        closePrioMenu();
        closeDatePicker();
    });

    setInterval(loadTasks, 60_000);
});
