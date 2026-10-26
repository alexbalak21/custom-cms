const $ = (s, r = document) => r.querySelector(s);
const el = (tag, cls = '', html = '') => { const e = document.createElement(tag); e.className = cls; e.innerHTML = html; return e; };
const api = (a, body) => fetch('api.php?action=' + a, body ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {});
const J = (a, b) => api(a, b).then(r => r.json());
const debounce = (f, ms = 250) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => f(...a), ms); }; };
const btn = (txt, on, cls = 'btn-outline-secondary') => { const b = el('button', 'btn btn-sm ' + cls, txt); b.type = 'button'; b.onclick = on; return b; };
const tools = $('#tools'), app = $('#app');
const preview = async (iframe, body) => { iframe.srcdoc = await (await api('render', body)).text(); };

function select(opts, val, on) {
  const s = el('select', 'form-select form-select-sm');
  opts.forEach(o => { const [v, l] = Array.isArray(o) ? o : [o, o]; s.add(new Option(l, v, false, v === val)); });
  s.onchange = () => on(s.value); return s;
}
function input(type, val, on, ph = '') {
  const i = el('input', 'form-control form-control-sm'); i.type = type; i.value = val ?? ''; i.placeholder = ph;
  i.oninput = () => on(i.value); return i;
}

// Generates a form from a block's "fields" definition (recursive for repeaters)
function renderFields(fields, obj, box, ch) {
  fields.forEach(f => {
    const w = el('div', 'mb-2', `<label class="form-label small mb-1">${f.label || f.name}</label>`), n = f.name, t = f.type;
    if (t === 'repeater') {
      obj[n] = obj[n] || []; const list = el('div');
      const draw = () => {
        list.innerHTML = '';
        obj[n].forEach((it, i) => {
          const c = el('div', 'border rounded p-2 mb-2'); renderFields(f.fields || [], it, c, ch);
          c.append(btn('Remove item', () => { obj[n].splice(i, 1); draw(); ch(); }, 'btn-outline-danger')); list.append(c);
        });
      };
      draw(); w.append(list, btn('+ Add item', () => { obj[n].push({}); draw(); ch(); }));
    } else if (t === 'link') {
      obj[n] = obj[n] || {};
      ['label', 'url'].forEach(k => { const i = input('text', obj[n][k], v => { obj[n][k] = v; ch(); }, k); i.classList.add('mb-1'); w.append(i); });
    } else if (t === 'textarea' || t === 'richtext') {
      const x = el('textarea', 'form-control form-control-sm'); x.rows = t === 'richtext' ? 5 : 3; x.value = obj[n] ?? '';
      x.oninput = () => { obj[n] = x.value; ch(); }; w.append(x);
    } else if (t === 'select') w.append(select(f.options || [], obj[n], v => { obj[n] = v; ch(); }));
    else if (t === 'checkbox') { const c = el('input', 'form-check-input ms-2'); c.type = 'checkbox'; c.checked = !!obj[n]; c.onchange = () => { obj[n] = c.checked; ch(); }; w.append(c); }
    else w.append(input(t === 'color' ? 'color' : 'text', obj[n], v => { obj[n] = v; ch(); }, t === 'image' ? 'Image URL' : ''));
    box.append(w);
  });
}

/* ───────────────────────── BLOCK BUILDER ───────────────────────── */
async function blockBuilder() {
  let list = await J('blocks'), B = list[0], flipped = false;
  const pick = el('select', 'form-select form-select-sm w-auto');
  const refreshPick = () => { pick.innerHTML = ''; list.forEach(b => pick.add(new Option(b.label, b.id, false, b.id === B.id))); };
  pick.onchange = () => { B = list.find(b => b.id === pick.value); load(); };
  const flip = btn('⇅ Preview top/bottom', () => { flipped = !flipped; split.classList.toggle('rev', flipped); });
  tools.append(pick,
    btn('New', () => { const id = prompt('Block id (a-z, 0-9, -)'); if (!id) return; B = { id, label: id, category: 'Custom', fields: [], template: '<h2>{{title}}</h2>', css: '', sample: { title: 'Hello' } }; list.push(B); refreshPick(); load(); }),
    btn('Save', async () => { B = await J('save_block', B); list = await J('blocks'); refreshPick(); flash('Saved'); }, 'btn-success'),
    btn('Delete', async () => { if (confirm('Delete block?')) { await J('delete_block', { id: B.id }); location.reload(); } }, 'btn-outline-danger'), flip);

  const split = el('div', 'split'), pv = el('div', 'pane preview'), ed = el('div', 'pane editor d-flex flex-column');
  const frame = el('iframe'); pv.append(frame);
  const tabs = el('ul', 'nav nav-tabs mb-2'), areas = {};
  const meta = el('div', 'd-flex gap-2 mb-2');
  const lab = input('text', '', v => { B.label = v; }, 'Label'), cat = input('text', '', v => { B.category = v; }, 'Category');
  meta.append(lab, cat);
  const upd = debounce(() => preview(frame, { block: B }));
  const defs = [['template', 'HTML template (Bootstrap)'], ['css', 'Custom CSS'], ['fields', 'Fields (JSON)'], ['sample', 'Sample content (JSON)']];
  const wrap = el('div', 'flex-fill', ''); wrap.style.minHeight = 0;
  defs.forEach(([k, label], i) => {
    const li = el('li', 'nav-item', `<a class="nav-link ${i ? '' : 'active'}" href="#">${label}</a>`), ta = el('textarea', 'form-control h-100');
    ta.style.display = i ? 'none' : ''; areas[k] = ta; wrap.append(ta);
    li.onclick = e => { e.preventDefault(); tabs.querySelectorAll('a').forEach(a => a.classList.remove('active')); li.firstChild.classList.add('active'); Object.values(areas).forEach(x => x.style.display = 'none'); ta.style.display = ''; };
    ta.oninput = () => {
      if (k === 'fields' || k === 'sample') { try { B[k] = JSON.parse(ta.value); ta.classList.remove('bad'); } catch { ta.classList.add('bad'); return; } }
      else B[k] = ta.value;
      upd();
    };
    tabs.append(li);
  });
  ed.append(meta, tabs, wrap); split.append(pv, ed); app.append(split);
  function load() {
    lab.value = B.label || ''; cat.value = B.category || '';
    areas.template.value = B.template || ''; areas.css.value = B.css || '';
    areas.fields.value = JSON.stringify(B.fields || [], null, 2); areas.sample.value = JSON.stringify(B.sample || {}, null, 2);
    refreshPick(); pick.value = B.id; upd();
  }
  load();
}

/* ───────────────────────── PAGE BUILDER ───────────────────────── */
async function pageBuilder() {
  const blocks = await J('blocks'), def = id => blocks.find(b => b.id === id);
  let pages = await J('pages'), P = pages[0];
  const pick = el('select', 'form-select form-select-sm w-auto');
  const refreshPick = () => { pick.innerHTML = ''; pages.forEach(p => pick.add(new Option(p.title, p.id, false, p.id === P.id))); };
  pick.onchange = () => { P = pages.find(p => p.id === pick.value); draw(); };
  const link = el('a', 'small', ''); link.target = '_blank';
  const clean = () => ({ ...P, blocks: P.blocks.map(({ _open, ...r }) => r) });
  tools.append(pick,
    btn('New', () => { const id = prompt('Page id (a-z, 0-9, -)'); if (!id) return; P = { id, title: id, slug: id, blocks: [] }; pages.push(P); refreshPick(); draw(); }),
    btn('Save', async () => { await J('save_page', clean()); pages = await J('pages'); P = pages.find(p => p.id === P.id); refreshPick(); flash('Saved'); }, 'btn-success'),
    btn('Compile → HTML', async () => { const r = await J('compile', { page: clean() }); link.href = r.file; link.textContent = r.file; flash('Compiled to ' + r.file); }, 'btn-primary'), link);

  const side = el('div', 'side'), pv = el('div', 'pv'), frame = el('iframe'); pv.append(frame); app.append(side, pv);
  const upd = debounce(() => preview(frame, { page: clean() }));
  const SET = { theme: ['default', 'light', 'dark', 'primary', 'secondary'], spacing: ['none', 'small', 'medium', 'large', 'xl'], container: ['narrow', 'boxed', 'wide', 'full'] };

  function draw() {
    side.innerHTML = '';
    const head = el('div', 'mb-3');
    head.append(input('text', P.title, v => { P.title = v; upd(); }, 'Title'), el('div', 'mb-1'), input('text', P.slug, v => P.slug = v, 'slug (empty = index)'));
    side.append(head);
    P.blocks.forEach((it, i) => {
      const d = def(it.block); if (!d) return;
      const card = el('div', 'card mb-2'), h = el('div', 'card-header d-flex align-items-center gap-1 py-1');
      const title = el('span', 'flex-fill fw-semibold', d.label); title.style.cursor = 'pointer';
      title.onclick = () => { it._open = !it._open; draw(); };
      const mv = k => () => { const j = i + k; if (j < 0 || j >= P.blocks.length) return; [P.blocks[i], P.blocks[j]] = [P.blocks[j], P.blocks[i]]; draw(); upd(); };
      h.append(title, btn('↑', mv(-1)), btn('↓', mv(1)), btn('✕', () => { P.blocks.splice(i, 1); draw(); upd(); }, 'btn-outline-danger'));
      card.append(h);
      if (it._open) {
        const body = el('div', 'card-body p-2'), row = el('div', 'd-flex gap-1 mb-2');
        it.settings = it.settings || {};
        Object.entries(SET).forEach(([k, o]) => row.append(select(o, it.settings[k] || (k === 'container' ? 'boxed' : k === 'spacing' ? 'medium' : 'default'), v => { it.settings[k] = v; upd(); })));
        it.content = it.content || {};
        body.append(row); renderFields(d.fields || [], it.content, body, upd); card.append(body);
      }
      side.append(card);
    });
    const add = select(blocks.map(b => [b.id, b.label + ' · ' + (b.category || '')]), blocks[0]?.id, () => {});
    const add_row = el('div', 'd-flex gap-1 mt-3');
    add_row.append(add, btn('+ Add block', () => { const d = def(add.value); P.blocks.push({ block: d.id, content: JSON.parse(JSON.stringify(d.sample || {})), settings: { theme: 'default', spacing: 'medium', container: 'boxed' }, _open: true }); draw(); upd(); }, 'btn-primary'));
    side.append(add_row);
    upd();
  }
  refreshPick(); draw();
}

function flash(msg) {
  const t = el('div', 'toast show position-fixed bottom-0 end-0 m-3 p-2 px-3 text-bg-success', msg); document.body.append(t); setTimeout(() => t.remove(), 2000);
}
VIEW === 'blocks' ? blockBuilder() : pageBuilder();