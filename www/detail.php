<?php
$scheme = htmlspecialchars($_GET['scheme'] ?? 'light');
$taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : null;
$listId = isset($_GET['list_id']) ? (int)$_GET['list_id'] : null;
$mode   = $taskId !== null ? 'task' : 'list';
?>
<!DOCTYPE html>
<html lang="de" data-scheme="<?= $scheme ?>">
<head>
<meta charset="UTF-8">
<title>Aufgabe</title>
<style>
:root {
  --radius-sm: 6px; --radius-md: 10px; --radius-lg: 14px;
  --gap-sm: 8px; --gap-md: 12px; --gap-lg: 20px;
  --font: -apple-system, BlinkMacSystemFont, 'SF Pro Text', system-ui, sans-serif;
  --ease-spring: cubic-bezier(.34,1.56,.64,1);
  --glass-bg: rgba(255,255,255,0.82);
  --glass-border: rgba(0,0,0,0.09);
  --glass-input: rgba(0,0,0,0.04);
  --glass-task: rgba(255,255,255,0.6);
  --color-text: rgba(0,0,0,0.85);
  --color-sub: rgba(0,0,0,0.45);
  --color-placeholder: rgba(0,0,0,0.3);
  --color-sep: rgba(0,0,0,0.07);
  --color-done: rgba(0,0,0,0.35);
  --color-accent: #007AFF;
  --color-overdue: #FF3B30;
  --shadow-card: 0 2px 16px rgba(0,0,0,0.1);
  --blur-bg: blur(20px) saturate(160%);
}
[data-scheme="dark"] {
  --glass-bg: rgba(28,28,30,0.88);
  --glass-border: rgba(255,255,255,0.08);
  --glass-input: rgba(255,255,255,0.07);
  --glass-task: rgba(255,255,255,0.06);
  --color-text: rgba(255,255,255,0.90);
  --color-sub: rgba(255,255,255,0.45);
  --color-placeholder: rgba(255,255,255,0.28);
  --color-sep: rgba(255,255,255,0.07);
  --color-done: rgba(255,255,255,0.32);
  --color-accent: #0A84FF;
  --color-overdue: #FF453A;
  --shadow-card: 0 2px 16px rgba(0,0,0,0.4);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
  width: 100%; height: 100%; overflow: hidden;
  background: var(--glass-bg);
  backdrop-filter: var(--blur-bg); -webkit-backdrop-filter: var(--blur-bg);
  font-family: var(--font); font-size: 15px;
  color: var(--color-text);
  -webkit-font-smoothing: antialiased;
}
.hidden { display: none !important; }
.app { display: flex; flex-direction: column; height: 100%; }

/* ── Toolbar ── */
.toolbar {
  display: flex; align-items: center; gap: var(--gap-md);
  padding: 12px var(--gap-lg) 10px;
  border-bottom: 1px solid var(--color-sep);
  flex-shrink: 0;
  -webkit-app-region: drag;
}
.toolbar-title {
  font-size: 15px; font-weight: 600; flex: 1;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  -webkit-app-region: no-drag;
}
.done-btn {
  flex-shrink: 0;
  background: var(--color-accent); color: #fff;
  border: none; border-radius: var(--radius-md);
  font-family: var(--font); font-size: 13px; font-weight: 600;
  padding: 6px 14px; cursor: pointer;
  transition: opacity .15s;
  -webkit-app-region: no-drag;
}
.done-btn:hover { opacity: .85; }
.done-btn.is-done { background: rgba(52,199,89,0.9); }

/* ── Content ── */
.content {
  flex: 1; overflow-y: auto; overflow-x: hidden;
  padding: var(--gap-lg) calc(var(--gap-lg) * 2);
  display: flex; flex-direction: column; gap: var(--gap-lg);
}
.content::-webkit-scrollbar { width: 5px; }
.content::-webkit-scrollbar-thumb { background: var(--glass-border); border-radius: 3px; }

/* ── Title ── */
.task-title-wrap { display: flex; align-items: flex-start; gap: var(--gap-md); }
.check-circle {
  flex-shrink: 0; width: 26px; height: 26px; border-radius: 50%;
  border: 2px solid var(--glass-border);
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; color: transparent; cursor: pointer;
  margin-top: 5px;
  transition: all .2s;
}
.check-circle:hover { border-color: var(--color-accent); }
.check-circle.done { background: var(--color-accent); border-color: var(--color-accent); color: #fff; }
.task-title-input {
  flex: 1; font-size: 26px; font-weight: 700; line-height: 1.25;
  color: var(--color-text); border: none; background: transparent;
  font-family: var(--font); outline: none; resize: none;
  padding: 0; overflow: hidden;
  min-height: 36px;
}
.task-title-input.done-text { text-decoration: line-through; color: var(--color-done); }
.task-title-input::placeholder { color: var(--color-placeholder); }

/* ── Meta chips ── */
.meta-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.meta-chip {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 12px; font-weight: 500; padding: 4px 10px;
  border-radius: 20px;
  background: var(--glass-input); border: 1px solid var(--glass-border);
  color: var(--color-sub); cursor: pointer;
  transition: background .15s;
}
.meta-chip:hover { background: var(--glass-task); color: var(--color-text); }
.meta-chip.overdue { background: rgba(255,59,48,.12); color: var(--color-overdue); border-color: transparent; }
.meta-chip.prio-1 { background: rgba(52,199,89,.12); color: #34C759; border-color: transparent; }
.meta-chip.prio-2 { background: rgba(255,159,10,.12); color: #FF9F0A; border-color: transparent; }
.meta-chip.prio-3 { background: rgba(255,69,58,.12); color: #FF453A; border-color: transparent; }

/* ── Section ── */
.section { display: flex; flex-direction: column; gap: 8px; }
.section-label {
  font-size: 11px; font-weight: 600; text-transform: uppercase;
  letter-spacing: .5px; color: var(--color-sub);
}
.notes-input {
  width: 100%; min-height: 120px;
  background: var(--glass-input); border: 1px solid var(--glass-border);
  border-radius: var(--radius-md); color: var(--color-text);
  font-family: var(--font); font-size: 14px; line-height: 1.6;
  padding: var(--gap-md); resize: none; outline: none;
  transition: border-color .15s;
}
.notes-input:focus { border-color: var(--color-accent); }
.notes-input::placeholder { color: var(--color-placeholder); }

/* ── Subtasks ── */
.subtask-item {
  display: flex; align-items: center; gap: var(--gap-md);
  padding: 6px 0; border-bottom: 1px solid var(--color-sep);
}
.sub-check {
  flex-shrink: 0; width: 18px; height: 18px; border-radius: 50%;
  border: 1.5px solid var(--glass-border);
  display: flex; align-items: center; justify-content: center;
  font-size: 9px; color: transparent; cursor: pointer;
  transition: all .15s;
}
.sub-check:hover { border-color: var(--color-accent); }
.sub-check.done { background: var(--color-accent); border-color: var(--color-accent); color: #fff; }
.sub-label {
  flex: 1; font-size: 14px; outline: none; cursor: text;
  color: var(--color-text);
}
.sub-label.done-text { text-decoration: line-through; color: var(--color-done); }
.sub-del {
  background: transparent; border: none; cursor: pointer;
  color: var(--color-sub); font-size: 12px; opacity: 0;
  padding: 0 4px; transition: opacity .15s;
}
.subtask-item:hover .sub-del { opacity: 1; }
.add-sub-btn {
  background: transparent; border: 1px dashed var(--glass-border);
  border-radius: var(--radius-sm); color: var(--color-sub);
  font-size: 13px; font-family: var(--font);
  padding: 6px var(--gap-md); cursor: pointer; width: 100%;
  text-align: left; transition: border-color .15s, color .15s;
}
.add-sub-btn:hover { border-color: var(--color-accent); color: var(--color-accent); }

/* ── List task rows ── */
.list-task-row {
  display: flex; align-items: center; gap: var(--gap-md);
  padding: 10px 0; border-bottom: 1px solid var(--color-sep);
  font-size: 15px; color: var(--color-text);
}
.list-task-row.done-row { color: var(--color-done); text-decoration: line-through; }
.list-mini-check {
  flex-shrink: 0; width: 20px; height: 20px; border-radius: 50%;
  border: 1.5px solid var(--glass-border);
  display: flex; align-items: center; justify-content: center;
  font-size: 10px; color: transparent; cursor: pointer;
  transition: all .15s;
}
.list-mini-check:hover { border-color: var(--color-accent); }
.list-mini-check.done { background: var(--color-accent); border-color: var(--color-accent); color: #fff; }

/* ── Loading / empty ── */
.state-msg {
  display: flex; align-items: center; justify-content: center;
  height: 100%; font-size: 14px; color: var(--color-sub);
}

/* ── Inline pickers ── */
.inline-picker {
  position: fixed; z-index: 200;
  background: var(--glass-bg);
  backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-md);
  padding: 4px; min-width: 160px;
  box-shadow: var(--shadow-card);
  animation: pop .12s var(--ease-spring);
}
@keyframes pop { from { opacity:0; transform:scale(.95); } to { opacity:1; transform:scale(1); } }
.picker-opt {
  display: flex; align-items: center; gap: var(--gap-md);
  background: transparent; border: none; cursor: pointer;
  font-family: var(--font); font-size: 14px; color: var(--color-text);
  padding: 7px var(--gap-md); border-radius: var(--radius-sm);
  width: 100%; text-align: left; transition: background .12s;
}
.picker-opt:hover { background: var(--glass-task); }
.picker-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
</style>
</head>
<body>
<div class="app" id="app">
  <div class="state-msg" id="loading">Wird geladen…</div>
</div>

<script>
const API    = `${location.origin}/api/tasks`;
const LAPI   = `${location.origin}/api/lists`;
const TASK_ID = <?= json_encode($taskId) ?>;
const LIST_ID = <?= json_encode($listId) ?>;
const MODE    = <?= json_encode($mode) ?>;

let task  = null;
let lists = [];
let activePicker = null;

const PRIO_LABELS = { 1: 'Niedrig', 2: 'Mittel', 3: 'Hoch' };
const PRIO_CLASS  = { 1: 'prio-1', 2: 'prio-2', 3: 'prio-3' };

function isOverdue(d) { return d && d < new Date().toISOString().slice(0,10); }
function fmtDate(d) {
  if (!d) return '';
  const [y,m,day] = d.split('-').map(Number);
  const today = new Date();
  const due   = new Date(y, m-1, day);
  const diff  = Math.round((due - new Date(today.getFullYear(), today.getMonth(), today.getDate())) / 86400000);
  if (diff === 0)  return 'Heute';
  if (diff === 1)  return 'Morgen';
  if (diff === -1) return 'Gestern';
  if (diff < 0)   return `Vor ${-diff}d`;
  if (diff < 7)   return `In ${diff}d`;
  return due.toLocaleDateString('de-DE', { day:'numeric', month:'short' });
}

async function api(path, opts = {}) {
  const r = await fetch(API + path, { headers: {'Content-Type':'application/json'}, ...opts });
  if (r.status === 204) return null;
  return r.json();
}
async function listApi(path, opts = {}) {
  const r = await fetch(LAPI + path, { headers: {'Content-Type':'application/json'}, ...opts });
  if (r.status === 204) return null;
  return r.json();
}

function closePickerOnOutsideClick(picker) {
  const close = e => { if (!picker.contains(e.target)) { picker.remove(); activePicker = null; document.removeEventListener('click', close, true); } };
  setTimeout(() => document.addEventListener('click', close, true), 0);
}

// ── Task detail ─────────────────────────────────────────────────────────

function buildTaskDetail(t) {
  const app = document.getElementById('app');
  app.innerHTML = '';

  // Toolbar
  const toolbar = document.createElement('div');
  toolbar.className = 'toolbar';

  const toolTitle = document.createElement('span');
  toolTitle.className = 'toolbar-title';
  toolTitle.textContent = 'Aufgabe bearbeiten';
  toolbar.appendChild(toolTitle);

  const doneBtn = document.createElement('button');
  doneBtn.className = `done-btn${t.done ? ' is-done' : ''}`;
  doneBtn.textContent = t.done ? '✓ Erledigt' : 'Als erledigt markieren';
  doneBtn.addEventListener('click', async () => {
    t.done = !t.done;
    await api(`/${t.id}`, { method:'PUT', body: JSON.stringify({ done: t.done }) });
    doneBtn.className = `done-btn${t.done ? ' is-done' : ''}`;
    doneBtn.textContent = t.done ? '✓ Erledigt' : 'Als erledigt markieren';
    titleInput.className = `task-title-input${t.done ? ' done-text' : ''}`;
    checkCircle.className = `check-circle${t.done ? ' done' : ''}`;
    checkCircle.textContent = t.done ? '✓' : '';
  });
  toolbar.appendChild(doneBtn);
  app.appendChild(toolbar);

  // Content
  const content = document.createElement('div');
  content.className = 'content';

  // Title + check
  const titleWrap = document.createElement('div');
  titleWrap.className = 'task-title-wrap';

  const checkCircle = document.createElement('div');
  checkCircle.className = `check-circle${t.done ? ' done' : ''}`;
  checkCircle.textContent = t.done ? '✓' : '';
  checkCircle.addEventListener('click', () => doneBtn.click());
  titleWrap.appendChild(checkCircle);

  const titleInput = document.createElement('textarea');
  titleInput.className = `task-title-input${t.done ? ' done-text' : ''}`;
  titleInput.value = t.title;
  titleInput.rows = 1;
  titleInput.placeholder = 'Aufgabentitel';
  titleInput.addEventListener('input', () => {
    titleInput.style.height = 'auto';
    titleInput.style.height = titleInput.scrollHeight + 'px';
  });
  let titleTimer;
  titleInput.addEventListener('input', () => {
    clearTimeout(titleTimer);
    titleTimer = setTimeout(async () => {
      const v = titleInput.value.trim();
      if (v && v !== t.title) { t.title = v; await api(`/${t.id}`, { method:'PUT', body: JSON.stringify({ title: v }) }); }
    }, 600);
  });
  titleWrap.appendChild(titleInput);
  content.appendChild(titleWrap);

  // Auto-resize textarea on load
  requestAnimationFrame(() => {
    titleInput.style.height = 'auto';
    titleInput.style.height = titleInput.scrollHeight + 'px';
  });

  // Meta chips
  const metaRow = document.createElement('div');
  metaRow.className = 'meta-row';

  // Priority chip
  const prioChip = document.createElement('button');
  prioChip.className = `meta-chip ${PRIO_CLASS[t.priority] ?? ''}`;
  prioChip.textContent = `● ${PRIO_LABELS[t.priority] ?? 'Mittel'}`;
  prioChip.addEventListener('click', e => { e.stopPropagation(); showPrioPicker(t, prioChip, metaRow); });
  metaRow.appendChild(prioChip);

  // Due date chip
  const dateChip = document.createElement('button');
  dateChip.className = `meta-chip${isOverdue(t.due_date) ? ' overdue' : ''}`;
  dateChip.textContent = t.due_date ? `📅 ${fmtDate(t.due_date)}` : '📅 Datum';
  dateChip.addEventListener('click', e => { e.stopPropagation(); showDatePicker(t, dateChip, metaRow); });
  metaRow.appendChild(dateChip);

  // List chip
  if (lists.length > 0) {
    const listObj = lists.find(l => l.id === t.list_id);
    const listChip = document.createElement('button');
    listChip.className = 'meta-chip';
    if (listObj) { listChip.style.setProperty('--lc', listObj.color); }
    listChip.textContent = listObj ? `◉ ${listObj.name}` : '◎ Liste';
    listChip.addEventListener('click', e => { e.stopPropagation(); showListPicker(t, listChip, metaRow); });
    metaRow.appendChild(listChip);
  }

  content.appendChild(metaRow);

  // Notes section
  const notesSection = document.createElement('div');
  notesSection.className = 'section';
  const notesLabel = document.createElement('div');
  notesLabel.className = 'section-label';
  notesLabel.textContent = 'Notizen';
  notesSection.appendChild(notesLabel);
  const notesInput = document.createElement('textarea');
  notesInput.className = 'notes-input';
  notesInput.placeholder = 'Notizen hinzufügen…';
  notesInput.value = t.notes || '';
  let notesTimer;
  notesInput.addEventListener('input', () => {
    clearTimeout(notesTimer);
    notesTimer = setTimeout(async () => {
      t.notes = notesInput.value;
      await api(`/${t.id}`, { method:'PUT', body: JSON.stringify({ notes: t.notes }) });
    }, 600);
  });
  notesSection.appendChild(notesInput);
  content.appendChild(notesSection);

  // Subtasks section
  const subSection = document.createElement('div');
  subSection.className = 'section';
  const subLabel = document.createElement('div');
  subLabel.className = 'section-label';
  subLabel.textContent = 'Unteraufgaben';
  subSection.appendChild(subLabel);

  const subList = document.createElement('div');
  subList.id = 'sub-list';
  renderSubtasks(t, subList);
  subSection.appendChild(subList);

  const addSubBtn = document.createElement('button');
  addSubBtn.className = 'add-sub-btn';
  addSubBtn.textContent = '+ Unteraufgabe hinzufügen';
  addSubBtn.addEventListener('click', async () => {
    t.subtasks = [...(t.subtasks || []), { title: 'Neue Unteraufgabe', done: false }];
    await api(`/${t.id}`, { method:'PUT', body: JSON.stringify({ subtasks: t.subtasks }) });
    renderSubtasks(t, subList);
  });
  subSection.appendChild(addSubBtn);
  content.appendChild(subSection);

  app.appendChild(content);
}

function renderSubtasks(t, container) {
  container.innerHTML = '';
  (t.subtasks || []).forEach((sub, idx) => {
    const row = document.createElement('div');
    row.className = 'subtask-item';

    const chk = document.createElement('div');
    chk.className = `sub-check${sub.done ? ' done' : ''}`;
    chk.textContent = sub.done ? '✓' : '';
    chk.addEventListener('click', async () => {
      t.subtasks[idx].done = !t.subtasks[idx].done;
      await api(`/${t.id}`, { method:'PUT', body: JSON.stringify({ subtasks: t.subtasks }) });
      renderSubtasks(t, container);
    });

    const lbl = document.createElement('span');
    lbl.className = `sub-label${sub.done ? ' done-text' : ''}`;
    lbl.contentEditable = 'true';
    lbl.textContent = sub.title;
    lbl.addEventListener('blur', async () => {
      const v = lbl.textContent.trim();
      if (v && v !== sub.title) { t.subtasks[idx].title = v; await api(`/${t.id}`, { method:'PUT', body: JSON.stringify({ subtasks: t.subtasks }) }); }
    });
    lbl.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); lbl.blur(); } });

    const del = document.createElement('button');
    del.className = 'sub-del';
    del.textContent = '✕';
    del.addEventListener('click', async () => {
      t.subtasks.splice(idx, 1);
      await api(`/${t.id}`, { method:'PUT', body: JSON.stringify({ subtasks: t.subtasks }) });
      renderSubtasks(t, container);
    });

    row.appendChild(chk);
    row.appendChild(lbl);
    row.appendChild(del);
    container.appendChild(row);
  });
}

// ── Pickers ────────────────────────────────────────────────────────────

function showPrioPicker(t, anchor, metaRow) {
  activePicker?.remove();
  const p = document.createElement('div'); p.className = 'inline-picker'; activePicker = p;
  const opts = [[1,'Niedrig','#34C759'],[2,'Mittel','#FF9F0A'],[3,'Hoch','#FF453A']];
  opts.forEach(([val, label, color]) => {
    const btn = document.createElement('button'); btn.className = 'picker-opt';
    const dot = document.createElement('span'); dot.className = 'picker-dot'; dot.style.background = color;
    btn.appendChild(dot); btn.appendChild(document.createTextNode(label));
    btn.addEventListener('click', async () => {
      t.priority = val;
      await api(`/${t.id}`, { method:'PUT', body: JSON.stringify({ priority: val }) });
      anchor.className = `meta-chip ${PRIO_CLASS[val]}`;
      anchor.textContent = `● ${label}`;
      p.remove(); activePicker = null;
    });
    p.appendChild(btn);
  });
  document.body.appendChild(p);
  positionPicker(p, anchor);
  closePickerOnOutsideClick(p);
}

function showDatePicker(t, anchor, metaRow) {
  activePicker?.remove();
  const p = document.createElement('div'); p.className = 'inline-picker'; activePicker = p;

  const today     = new Date().toISOString().slice(0,10);
  const tomorrow  = new Date(Date.now()+86400000).toISOString().slice(0,10);
  const nextWeek  = new Date(Date.now()+7*86400000).toISOString().slice(0,10);

  const quick = [['Heute', today], ['Morgen', tomorrow], ['+7 Tage', nextWeek]];
  quick.forEach(([label, val]) => {
    const btn = document.createElement('button'); btn.className = 'picker-opt';
    btn.textContent = label;
    btn.addEventListener('click', async () => {
      t.due_date = val;
      await api(`/${t.id}`, { method:'PUT', body: JSON.stringify({ due_date: val }) });
      anchor.className = `meta-chip${isOverdue(val) ? ' overdue' : ''}`;
      anchor.textContent = `📅 ${fmtDate(val)}`;
      p.remove(); activePicker = null;
    });
    p.appendChild(btn);
  });

  const sep = document.createElement('div'); sep.style.cssText = 'height:1px;background:var(--color-sep);margin:3px 0';
  p.appendChild(sep);

  const inp = document.createElement('input'); inp.type = 'date'; inp.value = t.due_date || '';
  inp.style.cssText = 'width:100%;padding:7px var(--gap-md);font-family:var(--font);font-size:13px;color:var(--color-text);background:transparent;border:none;outline:none;cursor:pointer';
  inp.addEventListener('change', async () => {
    t.due_date = inp.value || null;
    await api(`/${t.id}`, { method:'PUT', body: JSON.stringify({ due_date: t.due_date }) });
    anchor.className = `meta-chip${isOverdue(t.due_date) ? ' overdue' : ''}`;
    anchor.textContent = t.due_date ? `📅 ${fmtDate(t.due_date)}` : '📅 Datum';
    p.remove(); activePicker = null;
  });
  p.appendChild(inp);

  if (t.due_date) {
    const clr = document.createElement('button'); clr.className = 'picker-opt';
    clr.style.color = 'var(--color-overdue)'; clr.textContent = 'Datum entfernen';
    clr.addEventListener('click', async () => {
      t.due_date = null;
      await api(`/${t.id}`, { method:'PUT', body: JSON.stringify({ due_date: null }) });
      anchor.className = 'meta-chip'; anchor.textContent = '📅 Datum';
      p.remove(); activePicker = null;
    });
    p.appendChild(clr);
  }

  document.body.appendChild(p);
  positionPicker(p, anchor);
  closePickerOnOutsideClick(p);
}

function showListPicker(t, anchor, metaRow) {
  activePicker?.remove();
  const p = document.createElement('div'); p.className = 'inline-picker'; activePicker = p;

  const noOpt = document.createElement('button'); noOpt.className = 'picker-opt';
  noOpt.textContent = 'Ohne Liste';
  noOpt.addEventListener('click', async () => {
    t.list_id = null;
    await api(`/${t.id}`, { method:'PUT', body: JSON.stringify({ list_id: null }) });
    anchor.textContent = '◎ Liste'; anchor.style.removeProperty('--lc');
    p.remove(); activePicker = null;
  });
  p.appendChild(noOpt);

  lists.forEach(l => {
    const btn = document.createElement('button'); btn.className = 'picker-opt';
    const dot = document.createElement('span'); dot.className = 'picker-dot'; dot.style.background = l.color;
    btn.appendChild(dot); btn.appendChild(document.createTextNode(l.name));
    btn.addEventListener('click', async () => {
      t.list_id = l.id;
      await api(`/${t.id}`, { method:'PUT', body: JSON.stringify({ list_id: l.id }) });
      anchor.style.setProperty('--lc', l.color);
      anchor.textContent = `◉ ${l.name}`;
      p.remove(); activePicker = null;
    });
    p.appendChild(btn);
  });

  document.body.appendChild(p);
  positionPicker(p, anchor);
  closePickerOnOutsideClick(p);
}

function positionPicker(p, anchor) {
  const r  = anchor.getBoundingClientRect();
  const pw = p.offsetWidth  || 200;
  const ph = p.offsetHeight || 200;
  let left = r.left, top = r.bottom + 6;
  if (left + pw > window.innerWidth)  left = r.right - pw;
  if (top  + ph > window.innerHeight) top  = r.top  - ph - 6;
  p.style.left = `${Math.max(4, left)}px`;
  p.style.top  = `${Math.max(4, top)}px`;
}

// ── List view ──────────────────────────────────────────────────────────

async function buildListView() {
  const app = document.getElementById('app');
  app.innerHTML = '';

  let listName = 'Alle Aufgaben';
  let listTasks = [];

  const qs = LIST_ID !== null ? `?list_id=${LIST_ID}` : '';
  const allTasks = await fetch(`${location.origin}/api/tasks${qs}`).then(r => r.json()).catch(() => []);

  if (LIST_ID !== null) {
    const listObj = lists.find(l => l.id === LIST_ID);
    listName = listObj?.name ?? 'Liste';
  }

  listTasks = allTasks;

  // Toolbar
  const toolbar = document.createElement('div');
  toolbar.className = 'toolbar';
  const toolTitle = document.createElement('span');
  toolTitle.className = 'toolbar-title';
  const pending = listTasks.filter(t => !t.done).length;
  toolTitle.textContent = `${listName}  —  ${pending} offen`;
  toolbar.appendChild(toolTitle);
  app.appendChild(toolbar);

  // Content
  const content = document.createElement('div');
  content.className = 'content';

  const openTasks = listTasks.filter(t => !t.done);
  const doneTasks = listTasks.filter(t =>  t.done);

  const section = (title, arr) => {
    if (!arr.length) return;
    const lbl = document.createElement('div'); lbl.className = 'section-label'; lbl.textContent = title;
    content.appendChild(lbl);
    arr.forEach(t => {
      const row = document.createElement('div');
      row.className = `list-task-row${t.done ? ' done-row' : ''}`;

      const chk = document.createElement('div');
      chk.className = `list-mini-check${t.done ? ' done' : ''}`;
      chk.textContent = t.done ? '✓' : '';
      chk.addEventListener('click', async () => {
        t.done = !t.done;
        await fetch(`${location.origin}/api/tasks/${t.id}`, { method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ done: t.done }) });
        row.className = `list-task-row${t.done ? ' done-row' : ''}`;
        chk.className = `list-mini-check${t.done ? ' done' : ''}`;
        chk.textContent = t.done ? '✓' : '';
      });

      const lbl2 = document.createElement('span');
      lbl2.textContent = t.title;
      row.appendChild(chk); row.appendChild(lbl2);
      content.appendChild(row);
    });
  };

  section(`Offen (${openTasks.length})`, openTasks);
  if (doneTasks.length) section(`Erledigt (${doneTasks.length})`, doneTasks);

  if (!listTasks.length) {
    const empty = document.createElement('div'); empty.className = 'state-msg'; empty.textContent = 'Keine Aufgaben';
    content.appendChild(empty);
  }

  app.appendChild(content);
}

// ── Init ───────────────────────────────────────────────────────────────

async function init() {
  lists = await fetch(`${location.origin}/api/lists`).then(r => r.json()).catch(() => []);

  if (MODE === 'task' && TASK_ID !== null) {
    const r = await fetch(`${location.origin}/api/tasks/${TASK_ID}`).catch(() => null);
    if (r && r.ok) {
      task = await r.json();
      task.done     = !!task.done;
      task.priority = +task.priority;
      task.subtasks = task.subtasks || [];
      if (task.list_id !== undefined && task.list_id !== null) task.list_id = +task.list_id;
      buildTaskDetail(task);
    } else {
      document.getElementById('loading').textContent = 'Aufgabe nicht gefunden.';
    }
  } else {
    await buildListView();
  }
}

init();
</script>
</body>
</html>
