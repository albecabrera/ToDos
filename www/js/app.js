/**
 * MenuBar Tasks — Frontend
 * Pure vanilla JS, no build step.
 */

const API    = `${location.origin}/api/tasks`;
let   tasks  = [];
let   filter = 'all';
let   selectedPriority = 2;

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
        case 'pending': return tasks.filter(t => !t.done);
        case 'done':    return tasks.filter(t =>  t.done);
        default:        return tasks;
    }
}

function render() {
    const list  = document.getElementById('task-list');
    const empty = document.getElementById('empty-state');
    const badge = document.getElementById('badge');

    const items   = filtered();
    const pending = tasks.filter(t => !t.done).length;

    badge.textContent = pending;
    badge.style.opacity = pending > 0 ? '1' : '0.5';

    if (items.length === 0) {
        list.innerHTML = '';
        empty.classList.remove('hidden');
        return;
    }
    empty.classList.add('hidden');

    // Split pending / done with a divider when showing "all"
    const pending_items = items.filter(t => !t.done);
    const done_items    = items.filter(t =>  t.done);

    // Diff existing DOM nodes
    const existing = new Map([...list.querySelectorAll('[data-id]')].map(el => [el.dataset.id, el]));
    const seen     = new Set();

    const allOrdered = [...pending_items, ...done_items];

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

    // Remove stale nodes
    existing.forEach((el, key) => {
        if (!seen.has(key)) animateOut(el);
    });

    // Insert done-section divider if showing all and both sections non-empty
    insertDividerIfNeeded(list, pending_items.length, done_items.length);
}

function insertDividerIfNeeded(list, pendingCount, doneCount) {
    list.querySelectorAll('.section-divider').forEach(d => d.remove());
    if (filter !== 'all' || pendingCount === 0 || doneCount === 0) return;

    // Find the first .done item and insert divider before it
    const firstDone = list.querySelector('.task-item.done');
    if (!firstDone) return;

    const divider = document.createElement('div');
    divider.className = 'section-divider';
    divider.textContent = 'Completadas';
    list.insertBefore(divider, firstDone);
}

// ── DOM builders ─────────────────────────────────────────

function buildEl(task) {
    const div = document.createElement('div');
    div.className = `task-item${task.done ? ' done' : ''} entering`;
    div.dataset.id = task.id;
    div.setAttribute('role', 'listitem');
    div.innerHTML = elHTML(task);
    bindEl(div, task);
    return div;
}

function syncEl(el, task) {
    const wasDone = el.classList.contains('done');
    const isDone  = !!parseInt(task.done);

    el.classList.toggle('done', isDone);
    el.querySelector('.task-title').textContent = task.title;
    el.querySelector('.priority-dot').className = `priority-dot p${task.priority}`;

    if (wasDone !== isDone) {
        el.classList.add('entering');
        el.addEventListener('animationend', () => el.classList.remove('entering'), { once: true });
    }
}

function elHTML(task) {
    return `
        <div class="priority-dot p${task.priority}"></div>
        <span class="task-title">${esc(task.title)}</span>
        <div class="task-check" data-action="toggle" role="checkbox" aria-checked="${task.done ? 'true' : 'false'}">
            <svg class="check-icon" width="9" height="7" viewBox="0 0 9 7" fill="none">
                <path d="M1 3.5l2.5 2.5L8 1" stroke="white" stroke-width="1.6"
                      stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <button class="delete-btn" data-action="delete" aria-label="Eliminar">
            <svg width="8" height="8" viewBox="0 0 8 8" fill="none">
                <path d="M1 1l6 6M7 1L1 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
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
}

function animateOut(el) {
    el.classList.add('leaving');
    el.addEventListener('animationend', () => el.remove(), { once: true });
}

// ── Actions ──────────────────────────────────────────────

async function toggleTask(id, el) {
    const task = tasks.find(t => t.id == id);
    if (!task) return;

    // Optimistic
    task.done = task.done ? 0 : 1;
    syncEl(el, task);

    try {
        const updated = await apiFetch(`/${id}`, {
            method: 'PUT',
            body: JSON.stringify({ done: !!task.done }),
        });
        if (updated) Object.assign(task, updated);
        render();
    } catch {
        // Rollback
        task.done = task.done ? 0 : 1;
        syncEl(el, task);
    }
}

async function deleteTask(id, el) {
    // Optimistic removal
    tasks = tasks.filter(t => t.id != id);
    animateOut(el);
    render();

    try {
        await apiFetch(`/${id}`, { method: 'DELETE' });
    } catch {
        loadTasks(); // re-sync on error
    }
}

async function addTask() {
    const input = document.getElementById('new-task-input');
    const title = input.value.trim();
    if (!title) { input.focus(); return; }

    input.value = '';
    input.focus();

    try {
        const task = await apiFetch('', {
            method: 'POST',
            body: JSON.stringify({ title, priority: selectedPriority }),
        });
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
    const dot = document.getElementById('prio-dot');
    dot.className = `priority-dot p${p}`;
    closePrioMenu();
}

function togglePrioMenu() {
    document.getElementById('priority-menu').classList.toggle('hidden');
}

function closePrioMenu() {
    document.getElementById('priority-menu').classList.add('hidden');
}

// ── Utils ────────────────────────────────────────────────

function esc(str) {
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
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
        if (e.key === 'Escape') {
            input.blur();
            closePrioMenu();
        }
    });

    // Priority picker
    document.getElementById('priority-picker').addEventListener('click', e => {
        e.stopPropagation();
        togglePrioMenu();
    });

    document.querySelectorAll('.prio-option').forEach(btn => {
        btn.addEventListener('click', () => setPriority(parseInt(btn.dataset.prio)));
    });

    // Close priority menu on outside click
    document.addEventListener('click', closePrioMenu);

    // Auto-refresh every 60s (keeps it in sync if used from another device/tab)
    setInterval(loadTasks, 60_000);
});
