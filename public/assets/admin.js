const $ = (s, r = document) => r.querySelector(s);
const el = (tag, cls = '', html = '') => { const e = document.createElement(tag); e.className = cls; e.innerHTML = html; return e; };
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const slug = s => s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
const debounce = (f, ms = 250) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => f(...a), ms); }; };
const tools = $('#tools'), app = $('#app');
const pageUrl = p => BASE + '/' + [p.category, p.slug].filter(Boolean).join('/');

function flash(msg, type = 'success') {
  const t = el('div', `toast show position-fixed bottom-0 end-0 m-3 p-2 px-3 text-bg-${type}`, esc(msg)); t.style.zIndex = 2000;
  document.body.append(t); setTimeout(() => t.remove(), 2600);
}
async function call(a, body) {
  const r = await fetch(BASE + '/admin/api/' + a, { method: body !== undefined ? 'POST' : 'GET', headers: { 'X-CSRF': CSRF, 'Content-Type': 'application/json' }, body: body !== undefined ? JSON.stringify(body) : undefined });
  if (r.status === 401) location.reload();
  const j = await r.json(); if (j && j.error) { flash(j.error, 'danger'); throw j; } return j;
}
async function preview(frame, body) {
  const r = await fetch(BASE + '/admin/api/render', { method: 'POST', headers: { 'X-CSRF': CSRF }, body: JSON.stringify(body) });
  frame.srcdoc = await r.text();
}
const btn = (txt, on, cls = 'btn-outline-secondary') => { const b = el('button', 'btn btn-sm ' + cls, txt); b.type = 'button'; b.onclick = on; return b; };
function select(opts, val, on, cls = '') {
  const s = el('select', 'form-select form-select-sm ' + cls);
  opts.forEach(o => { const [v, l] = Array.isArray(o) ? o : [o, o]; s.add(new Option(l, v, false, v === val)); });
  s.onchange = () => on(s.value); return s;
}
function input(type, val, on, ph = '') { const i = el('input', 'form-control form-control-sm'); i.type = type; i.value = val ?? ''; i.placeholder = ph; i.oninput = () => on(i.value); return i; }
function field(label, node) { const w = el('div', 'mb-2', `<label class="form-label small mb-1">${label}</label>`); w.append(node); return w; }

/* ───────── Media picker (modal) ───────── */
async function mediaPicker(cb) {
  const items = (await call('media')).filter(m => m.type.startsWith('image/'));
  const box = el('div', 'modal fade', `<div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Choose image</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input class="form-control mb-3" placeholder="Search…"><div class="row g-3"></div></div></div></div>`);
  const grid = $('.row', box), q = $('input', box);
  const m = new bootstrap.Modal(box); box.addEventListener('hidden.bs.modal', () => box.remove());
  const draw = () => {
    grid.innerHTML = '';
    items.filter(i => (i.filename + i.alt + i.folder).toLowerCase().includes(q.value.toLowerCase())).forEach(i => {
      const c = el('div', 'col-6 col-md-3 col-lg-2', `<div class="card" style="cursor:pointer"><img class="thumb card-img-top" src="${esc(i.url)}"><div class="card-body p-2 small text-truncate">${esc(i.filename)}</div></div>`);
      c.onclick = () => { cb(i.url, i); m.hide(); }; grid.append(c);
    });
    if (!items.length) grid.innerHTML = '<p class="text-muted">No images yet. Upload some in the Media section.</p>';
  };
  q.oninput = draw; draw(); document.body.append(box); m.show();
}

/* ───────── Form generator from block "fields" (recursive for repeaters) ───────── */
function renderFields(fields, obj, box, ch) {
  fields.forEach(f => {
    const w = el('div', 'mb-2', `<label class="form-label small mb-1">${esc(f.label || f.name)}</label>`), n = f.name, t = f.type;
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
    } else if (t === 'form') {
      w.append(select(window.FORM_IDS || [['contact', 'contact']], obj[n] || 'contact', v => { obj[n] = v; ch(); }));
    } else if (t === 'textarea' || t === 'richtext') {
      const x = el('textarea', 'form-control form-control-sm'); x.rows = t === 'richtext' ? 5 : 3; x.value = obj[n] ?? ''; x.oninput = () => { obj[n] = x.value; ch(); }; w.append(x);
    } else if (t === 'select') w.append(select(f.options || [], obj[n], v => { obj[n] = v; ch(); }));
    else if (t === 'checkbox') { const c = el('input', 'form-check-input ms-2'); c.type = 'checkbox'; c.checked = !!obj[n]; c.onchange = () => { obj[n] = c.checked; ch(); }; w.append(c); }
    else if (t === 'image') {
      const g = el('div', 'input-group input-group-sm'), i = input('text', obj[n], v => { obj[n] = v; ch(); }, 'Image URL');
      g.append(i, btn('Pick', () => mediaPicker(u => { i.value = u; obj[n] = u; ch(); }))); w.append(g);
    } else w.append(input(t === 'color' ? 'color' : 'text', obj[n], v => { obj[n] = v; ch(); }));
    box.append(w);
  });
}

/* ───────── Dashboard ───────── */
async function dashboard() {
  const [me, pages, media, cats] = await Promise.all([call('me'), call('pages'), call('media'), call('categories')]);
  const box = el('div', 'scroll');
  box.innerHTML = `
  <div class="row g-3 mb-4">${[['Pages', pages.length], ['Published', pages.filter(p => p.status === 'published').length], ['Categories', cats.length], ['Media', media.length]].map(([l, n]) => `<div class="col-6 col-md-3"><div class="card p-3"><div class="text-muted small">${l}</div><div class="display-6">${n}</div></div></div>`).join('')}</div>
  <p class="text-muted">MySQL: ${me.db ? '<span class="text-success">connected</span>' : 'not configured — product blocks use sample data (see config.php)'}<br>Procedures: <code>${me.procedures.join(', ')}</code></p>
  <div id="cmp"></div>`;
  box.append(btn('Compile whole site → dist/', async () => { const r = await call('compile_all', {}); $('#cmp', box).innerHTML = `<div class="alert alert-success mt-3">Compiled ${r.length} files:<br><code>${r.join('<br>')}</code></div>`; }, 'btn-primary'));
  app.append(box);
}

/* ───────── Pages list ───────── */
async function pagesList() {
  const pages = await call('pages'), box = el('div', 'scroll');
  tools.append(btn('+ New page', () => location.href = BASE + '/admin/pages/create', 'btn-primary'));
  box.innerHTML = `<table class="table align-middle"><thead><tr><th>Title</th><th>URL</th><th>Status</th><th></th></tr></thead><tbody>${pages.map(p => `<tr><td>${esc(p.title)}</td><td><code>${esc(pageUrl(p))}</code></td><td><span class="badge text-bg-${p.status === 'published' ? 'success' : 'secondary'}">${p.status}</span></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="${BASE}/admin/pages/${p.id}/edit">Edit</a> <a class="btn btn-sm btn-outline-secondary" target="_blank" href="${pageUrl(p)}?preview=1">View</a> <button class="btn btn-sm btn-outline-danger" data-del="${p.id}">Delete</button></td></tr>`).join('')}</tbody></table>`;
  box.querySelectorAll('[data-del]').forEach(b => b.onclick = async () => { if (confirm('Delete this page?')) { await call('delete_page', { id: b.dataset.del }); location.reload(); } });
  app.append(box);
}

/* ───────── Page editor (vertical split) ───────── */
async function pageEditor(id) {
  const [blocks, cats, pages, forms] = await Promise.all([call('blocks'), call('categories'), call('pages'), call('forms')]);
  window.FORM_IDS = forms.map(f => [f.id, f.title]);
  const def = i => blocks.find(b => b.id === i);
  let P = pages.find(p => p.id === id) || { id: 'p' + Date.now().toString(36), title: '', slug: '', category: '', status: 'draft', nav: false, vars: {}, seo: {}, blocks: [], _new: true };
  if (Array.isArray(P.vars)) P.vars = {};
  let tab = 'blocks';
  const strip = l => l.map(({ _open, ...b }) => ({ ...b, ...(b.children ? { children: strip(b.children) } : {}) }));
  const clean = () => { const { _new, ...r } = P; return { ...r, blocks: strip(P.blocks) }; };
  const side = el('div', 'side'), pv = el('div', 'pv'), frame = el('iframe'); pv.append(frame); app.append(side, pv);
  const upd = debounce(() => preview(frame, { page: clean() }));
  const view = el('a', 'btn btn-sm btn-outline-secondary', 'View live'); view.target = '_blank';
  const setView = () => { view.href = pageUrl(P) + '?preview=1'; view.style.display = P._new ? 'none' : ''; };
  tools.append(btn('← Pages', () => location.href = BASE + '/admin/pages'), view,
    btn('Save', async () => { Object.assign(P, await call('save_page', clean())); delete P._new; history.replaceState(null, '', BASE + '/admin/pages/' + P.id + '/edit'); setView(); drawSide(); flash('Saved'); }, 'btn-success'));
  const SET = { theme: ['default', 'light', 'dark', 'primary', 'secondary'], spacing: ['none', 'small', 'medium', 'large', 'xl'], container: ['narrow', 'boxed', 'wide', 'full'] };
  const DEF = { theme: 'default', spacing: 'medium', container: 'boxed' };

  function drawSide() {
    side.innerHTML = ''; setView();
    const tabs = el('ul', 'nav nav-pills nav-fill mb-3');
    [['blocks', 'Blocks'], ['page', 'Page'], ['seo', 'SEO']].forEach(([k, l]) => { const li = el('li', 'nav-item', `<a href="#" class="nav-link py-1 ${tab === k ? 'active' : ''}">${l}</a>`); li.onclick = e => { e.preventDefault(); tab = k; drawSide(); }; tabs.append(li); });
    side.append(tabs);
    ({ blocks: tabBlocks, page: tabPage, seo: tabSeo })[tab]();
    upd();
  }
  const WIDTHS = ['auto', ...Array.from({ length: 12 }, (_, k) => String(k + 1))], NESTED = { theme: 'default', spacing: 'none', width: 'auto' };
  // Recursive block tree (max 4 levels). Children of a "row" container get a column width.
  function blockList(list, box, depth, parent) {
    list.forEach((it, i) => {
      const d = def(it.block); if (!d) return;
      const card = el('div', 'card mb-2'), h = el('div', 'card-header d-flex align-items-center gap-1 py-1');
      const title = el('span', 'flex-fill fw-semibold', esc(d.label) + (d.container ? ` <span class="badge text-bg-secondary">${d.layout === 'row' ? 'row' : 'container'}</span>` : '') + (it.children?.length ? ` <span class="text-muted small">(${it.children.length})</span>` : ''));
      title.style.cursor = 'pointer'; title.onclick = () => { it._open = !it._open; drawSide(); };
      const mv = k => () => { const j = i + k; if (j < 0 || j >= list.length) return; [list[i], list[j]] = [list[j], list[i]]; drawSide(); };
      h.append(title, btn('↑', mv(-1)), btn('↓', mv(1)), btn('✕', () => { list.splice(i, 1); drawSide(); }, 'btn-outline-danger')); card.append(h);
      if (it._open) {
        const body = el('div', 'card-body p-2'), row = el('div', 'd-flex gap-1 mb-2'); it.settings = it.settings || {}; it.content = it.content || {};
        const S = depth === 1 ? SET : { theme: SET.theme, spacing: SET.spacing, ...(parent?.layout === 'row' ? { width: WIDTHS } : {}) }, D = depth === 1 ? DEF : NESTED;
        Object.entries(S).forEach(([k, o]) => row.append(select(o, String(it.settings[k] ?? D[k]), v => { it.settings[k] = v; upd(); })));
        body.append(row); renderFields(d.fields || [], it.content, body, upd);
        if (d.rpc) body.append(el('div', 'small text-info', `Data: <code>${esc(d.rpc.call)}</code> → <code>${esc(d.rpc.as)}</code>`));
        if (d.container) {
          it.children = it.children || [];
          const kids = el('div', 'border-start border-2 ps-2 mt-2'); kids.append(el('div', 'small text-muted mb-1', `Children · level ${depth + 1} of 4${d.layout === 'row' ? ' · set each child\'s width (1–12)' : ''}`));
          blockList(it.children, kids, depth + 1, d);
          kids.append(depth < 4 ? addRow(it.children, depth + 1) : el('div', 'small text-warning', 'Maximum nesting depth reached'));
          body.append(kids);
        }
        card.append(body);
      }
      box.append(card);
    });
  }
  function addRow(list, depth) {
    const add = select(blocks.map(b => [b.id, `${b.label} · ${b.category || ''}`]), blocks[0]?.id, () => {}), r = el('div', 'd-flex gap-1 mt-2');
    r.append(add, btn('+ Add', () => {
      const d = def(add.value);
      list.push({ block: d.id, content: JSON.parse(JSON.stringify(d.sample || {})), settings: depth === 1 ? { ...DEF } : { ...NESTED }, ...(d.container ? { children: [] } : {}), _open: true }); drawSide();
    }, 'btn-primary'));
    return r;
  }
  function tabBlocks() { blockList(P.blocks, side, 1, null); side.append(addRow(P.blocks, 1)); }
  function tabPage() {
    let slugIn;
    const t = input('text', P.title, v => { P.title = v; if (P._new && !P._touched) { P.slug = slug(v); slugIn.value = P.slug; } upd(); }, 'Title');
    slugIn = input('text', P.slug, v => { P._touched = true; P.slug = slug(v); }, 'slug (empty = home)');
    const url = el('div', 'small text-muted mb-2');
    side.append(field('Title', t), field('Category', select([['', '— none (simple page) —'], ...cats.map(c => [c.slug, c.title])], P.category, v => { P.category = v; })), field('Slug', slugIn),
      field('Status', select(['draft', 'published'], P.status, v => { P.status = v; })));
    const nav = el('div', 'form-check mb-3', '<input class="form-check-input" type="checkbox" id="nv"><label class="form-check-label small" for="nv">Show in top navigation (simple pages)</label>');
    $('input', nav).checked = !!P.nav; $('input', nav).onchange = e => P.nav = e.target.checked; side.append(nav);
    const vars = el('textarea', 'form-control mono'); vars.rows = 4; vars.value = JSON.stringify(P.vars, null, 2);
    vars.oninput = () => { try { P.vars = JSON.parse(vars.value); vars.classList.remove('bad'); upd(); } catch { vars.classList.add('bad'); } };
    side.append(field('Page variables (JSON) — usable by blocks as <code>@page.vars.x</code>', vars));
    if (P._new === undefined) side.append(btn('Delete page', async () => { if (confirm('Delete?')) { await call('delete_page', { id: P.id }); location.href = BASE + '/admin/pages'; } }, 'btn-outline-danger'));
  }
  function tabSeo() {
    const S = P.seo = P.seo || {}, set = k => v => { S[k] = v; };
    const area = (k, rows, cls = '') => { const x = el('textarea', 'form-control form-control-sm ' + cls); x.rows = rows; x.value = S[k] || ''; x.oninput = () => S[k] = x.value; return x; };
    const img = el('div', 'input-group input-group-sm'), ii = input('text', S.og_image, set('og_image'), '/uploads/…');
    img.append(ii, btn('Pick', () => mediaPicker(u => { ii.value = u; S.og_image = u; })));
    side.append(field('Meta title', input('text', S.title, set('title'), 'Defaults to "Title - Site"')), field('Meta description', area('description', 3)),
      field('Keywords', input('text', S.keywords, set('keywords'))), field('OG title', input('text', S.og_title, set('og_title'))),
      field('OG image', img), field('OG type', select(['website', 'article', 'product'], S.og_type || 'website', set('og_type'))),
      field('Canonical (blank = automatic)', input('text', S.canonical, set('canonical'))),
      field('JSON-LD (paste raw JSON or &lt;script type="application/ld+json"&gt; blocks)', area('jsonld_raw', 10, 'mono')));
    const ni = el('div', 'form-check mb-3', '<input class="form-check-input" type="checkbox" id="ni"><label class="form-check-label small" for="ni">noindex</label>');
    $('input', ni).checked = !!S.noindex; $('input', ni).onchange = e => S.noindex = e.target.checked; side.append(ni);
    const imp = el('textarea', 'form-control form-control-sm mono mb-1'); imp.rows = 4; imp.placeholder = 'Paste an existing <head> snippet (meta tags + JSON-LD scripts)…';
    side.append(field('Import from HTML head snippet', imp), btn('Import', () => {
      const d = new DOMParser().parseFromString(imp.value, 'text/html'), m = (s, a = 'content') => d.querySelector(s)?.getAttribute(a) || '';
      Object.assign(S, { description: m('meta[name=description]') || S.description, keywords: m('meta[name=keywords]') || S.keywords, og_title: m('meta[property="og:title"]') || S.og_title,
        og_image: m('meta[property="og:image"]') || S.og_image, og_type: m('meta[property="og:type"]') || S.og_type, canonical: m('link[rel=canonical]', 'href') || S.canonical });
      const ld = [...d.querySelectorAll('script[type="application/ld+json"]')].map(s => `<script type="application/ld+json">\n${s.textContent.trim()}\n</script>`).join('\n');
      if (ld) S.jsonld_raw = ld; drawSide(); flash('Imported — review and save');
    }, 'btn-outline-info'));
  }
  drawSide();
}

/* ───────── Block builder (horizontal split, flip top/bottom) ───────── */
async function blockBuilder(id) {
  let list = await call('blocks'), B = list.find(b => b.id === id) || list[0], flipped = false;
  const me = await call('me');
  const pick = select(list.map(b => [b.id, b.label]), B.id, v => { B = list.find(b => b.id === v); load(); }, 'w-auto');
  const refresh = () => { pick.innerHTML = ''; list.forEach(b => pick.add(new Option(b.label, b.id, false, b.id === B.id))); };
  tools.append(pick,
    btn('New', () => { const nid = slug(prompt('Block id') || ''); if (!nid) return; B = { id: nid, label: nid, category: 'Custom', fields: [], template: '<h2>{{title}}</h2>', css: '', sample: { title: 'Hello' } }; list.push(B); refresh(); load(); }),
    btn('Save', async () => { B = await call('save_block', B); list = await call('blocks'); refresh(); flash('Saved'); }, 'btn-success'),
    btn('Delete', async () => { if (confirm('Delete block?')) { await call('delete_block', { id: B.id }); location.href = BASE + '/admin/blocks'; } }, 'btn-outline-danger'),
    btn('⇅ Preview top/bottom', () => { flipped = !flipped; split.classList.toggle('rev', flipped); }));
  const split = el('div', 'split'), pv = el('div', 'pane preview'), ed = el('div', 'pane editor d-flex flex-column'), frame = el('iframe'); pv.append(frame);
  const upd = debounce(() => preview(frame, { block: B })), tabs = el('ul', 'nav nav-tabs mb-2'), areas = {}, wrap = el('div', 'flex-fill'); wrap.style.minHeight = 0;
  const lab = input('text', '', v => B.label = v, 'Label'), cat = input('text', '', v => B.category = v, 'Category'), meta = el('div', 'd-flex gap-2 mb-2'); const kind = select([['', 'Normal block'], ['stack', 'Container: stack (use {{{children}}})'], ['row', 'Container: row of columns (use {{{children}}})']], '', v => { if (v) { B.container = true; B.layout = v; } else { delete B.container; delete B.layout; } upd(); }); meta.append(lab, cat, kind);
  [['template', 'HTML template (Bootstrap)'], ['css', 'Custom CSS'], ['fields', 'Fields (JSON)'], ['sample', 'Sample content (JSON)'], ['rpc', 'Data / RPC (JSON)']].forEach(([k, label], i) => {
    const li = el('li', 'nav-item', `<a class="nav-link ${i ? '' : 'active'}" href="#">${label}</a>`), ta = el('textarea', 'form-control'); ta.style.display = i ? 'none' : ''; areas[k] = ta; wrap.append(ta);
    if (k === 'rpc') ta.placeholder = `{ "call": "${me.procedures[0]}", "params": { "category": "@content.category" }, "as": "products" }\n\nAvailable: ${me.procedures.join(', ')}\nParams: @content.x (block field) · @page.vars.x (page variable)`;
    li.onclick = e => { e.preventDefault(); tabs.querySelectorAll('a').forEach(a => a.classList.remove('active')); li.firstChild.classList.add('active'); Object.values(areas).forEach(x => x.style.display = 'none'); ta.style.display = ''; };
    ta.oninput = () => {
      if (['fields', 'sample', 'rpc'].includes(k)) { try { if (k === 'rpc' && !ta.value.trim()) delete B.rpc; else B[k] = JSON.parse(ta.value); ta.classList.remove('bad'); } catch { ta.classList.add('bad'); return; } } else B[k] = ta.value;
      upd();
    };
    tabs.append(li);
  });
  ed.append(meta, tabs, wrap); split.append(pv, ed); app.append(split);
  function load() {
    kind.value = B.container ? (B.layout || 'stack') : ''; lab.value = B.label || ''; cat.value = B.category || ''; areas.template.value = B.template || ''; areas.css.value = B.css || '';
    areas.fields.value = JSON.stringify(B.fields || [], null, 2); areas.sample.value = JSON.stringify(B.sample || {}, null, 2); areas.rpc.value = B.rpc ? JSON.stringify(B.rpc, null, 2) : '';
    refresh(); pick.value = B.id; upd();
  }
  load();
}

/* ───────── Media library ───────── */
async function mediaView() {
  let items = await call('media'); const box = el('div', 'scroll'), grid = el('div', 'row g-3');
  const q = el('input', 'form-control form-control-sm w-auto'); q.placeholder = 'Search name / alt / folder…';
  const file = el('input'); file.type = 'file'; file.multiple = true; file.hidden = true; file.accept = '.jpg,.jpeg,.png,.webp,.gif,.pdf';
  file.onchange = async () => {
    const fd = new FormData(); [...file.files].forEach(f => fd.append('files[]', f));
    const r = await fetch(BASE + '/admin/api/upload', { method: 'POST', headers: { 'X-CSRF': CSRF }, body: fd }), j = await r.json();
    if (j.error) return flash(j.error, 'danger'); flash(`Uploaded ${j.length} file(s)`); items = await call('media'); draw();
  };
  tools.append(q, btn('Upload', () => file.click(), 'btn-primary'), file);
  const draw = () => {
    grid.innerHTML = '';
    items.filter(i => (i.filename + i.alt + i.folder).toLowerCase().includes(q.value.toLowerCase())).forEach(i => {
      const c = el('div', 'col-6 col-md-3 col-xl-2', `<div class="card h-100">${i.type.startsWith('image/') ? `<img class="thumb card-img-top" src="${esc(i.url)}">` : '<div class="thumb d-flex align-items-center justify-content-center">PDF</div>'}<div class="card-body p-2 small"><div class="text-truncate fw-semibold">${esc(i.filename)}</div><div class="text-muted">${i.width ? i.width + '×' + i.height + ' · ' : ''}${Math.round(i.size / 1024)} KB</div><div class="text-muted text-truncate">${esc(i.folder) || '—'} · alt: ${esc(i.alt) || '—'}</div><span class="badge text-bg-${i.usage ? 'info' : 'warning'}">${i.usage ? 'used ' + i.usage + '×' : 'unused'}</span></div><div class="card-footer d-flex gap-1 p-1"></div></div>`);
      const f = $('.card-footer', c);
      f.append(btn('Copy URL', () => { navigator.clipboard.writeText(i.url); flash('URL copied'); }),
        btn('Edit', async () => { const alt = prompt('Alt text', i.alt); if (alt === null) return; const folder = prompt('Folder', i.folder); if (folder === null) return; await call('media_update', { id: i.id, alt, folder }); items = await call('media'); draw(); }),
        btn('✕', async () => { if (confirm(i.usage ? `Used ${i.usage}× — delete anyway?` : 'Delete file?')) { await call('media_delete', { id: i.id }); items = await call('media'); draw(); } }, 'btn-outline-danger'));
      grid.append(c);
    });
    if (!items.length) grid.innerHTML = '<p class="text-muted">No files yet.</p>';
  };
  q.oninput = draw; draw(); box.append(grid); app.append(box);
}

/* ───────── Categories / Settings ───────── */
async function categoriesView() {
  let cats = await call('categories'); const box = el('div', 'scroll'), rows = el('div');
  const draw = () => {
    rows.innerHTML = '';
    cats.forEach((c, i) => {
      const r = el('div', 'row g-2 mb-2'); const col = (n, node) => { const d = el('div', n); d.append(node); r.append(d); };
      col('col-md-3', input('text', c.slug, v => c.slug = slug(v), 'slug')); col('col-md-3', input('text', c.title, v => c.title = v, 'Title')); col('col-md-5', input('text', c.description, v => c.description = v, 'Description'));
      col('col-md-1', btn('✕', () => { cats.splice(i, 1); draw(); }, 'btn-outline-danger')); rows.append(r);
    });
  };
  draw();
  box.append(el('p', 'text-muted', 'Each category creates <code>/slug</code> (auto listing) and holds pages at <code>/slug/page-slug</code>.'), rows,
    btn('+ Add category', () => { cats.push({ slug: '', title: '', description: '' }); draw(); }), el('span', '', ' '),
    btn('Save', async () => { cats = await call('save_categories', cats); draw(); flash('Saved'); }, 'btn-success'));
  app.append(box);
}
async function settingsView() {
  const s = await call('site'), box = el('div', 'scroll'); box.style.maxWidth = '520px';
  box.append(el('h5', '', 'Site'), field('Site name', input('text', s.name, v => s.name = v)), field('Base URL (canonical, sitemap, og:image)', input('text', s.base_url, v => s.base_url = v)),
    btn('Save site', async () => { await call('save_site', s); flash('Saved'); }, 'btn-success'), el('hr'), el('h5', '', 'Change password'));
  let pw = ''; box.append(field('New password (min 8)', input('password', '', v => pw = v)), btn('Change password', async () => { await call('password', { password: pw }); flash('Password changed'); }, 'btn-primary'));
  app.append(box);
}

/* ───────── Forms (configurable fields, recipient, messages) ───────── */
async function formsView(id) {
  let list = await call('forms'), F = list.find(f => f.id === id) || list[0];
  const blank = n => ({ id: n, title: n, to: 'contact@example.com', subject: 'New message from {{name}}', success_message: 'Thank you! Your message has been sent.', submit_label: 'Send message', enabled: true,
    fields: [{ name: 'name', label: 'Name', type: 'text', required: true }, { name: 'email', label: 'Email', type: 'email', required: true }, { name: 'message', label: 'Message', type: 'textarea', required: true }] });
  if (!F) { F = blank('contact'); list.push(F); }
  const pick = select(list.map(f => [f.id, f.title]), F.id, v => { F = list.find(f => f.id === v); history.replaceState(null, '', BASE + '/admin/forms/' + v); draw(); }, 'w-auto');
  const refresh = () => { pick.innerHTML = ''; list.forEach(f => pick.add(new Option(f.title, f.id, false, f.id === F.id))); };
  tools.append(pick,
    btn('New', () => { const n = slug(prompt('Form id') || ''); if (!n) return; F = blank(n); list.push(F); refresh(); draw(); }),
    btn('Save', async () => { await call('save_form', F); list = await call('forms'); F = list.find(f => f.id === F.id); refresh(); draw(); flash('Saved'); }, 'btn-success'),
    btn('Send test email', async () => { const r = await call('mail_test', { id: F.id }); flash(`Test sent to ${r.to} (driver: ${r.driver})`); }, 'btn-outline-info'),
    btn('Delete', async () => { if (confirm('Delete this form?')) { await call('delete_form', { id: F.id }); location.href = BASE + '/admin/forms'; } }, 'btn-outline-danger'));
  const side = el('div', 'side'), pv = el('div', 'pv'), frame = el('iframe'); pv.append(frame); app.append(side, pv);
  const upd = debounce(() => preview(frame, { form: F }));
  function draw() {
    side.innerHTML = '';
    const chk = (label, val, on) => { const d = el('div', 'form-check mb-2', `<input class="form-check-input" type="checkbox"><label class="form-check-label small">${label}</label>`); $('input', d).checked = !!val; $('input', d).onchange = e => { on(e.target.checked); upd(); }; return d; };
    side.append(field('Title', input('text', F.title, v => F.title = v)),
      field('Send to (comma separated)', input('text', F.to, v => F.to = v, 'contact@example.com')),
      field('Email subject — use <code>{{fieldname}}</code>', input('text', F.subject, v => F.subject = v)),
      field('Success message', input('text', F.success_message, v => { F.success_message = v; upd(); })),
      field('Button label', input('text', F.submit_label, v => { F.submit_label = v; upd(); })),
      chk('Form enabled', F.enabled, v => F.enabled = v), el('hr'), el('h6', '', 'Fields'));
    F.fields.forEach((x, i) => {
      const c = el('div', 'border rounded p-2 mb-2'), r1 = el('div', 'd-flex gap-1 mb-1'), r2 = el('div', 'd-flex gap-1 align-items-center mb-1');
      const ni = input('text', x.name, () => {}, 'field_name'); ni.oninput = null; ni.onchange = () => { x.name = slug(ni.value).replace(/-/g, '_'); ni.value = x.name; upd(); };
      r1.append(input('text', x.label, v => { x.label = v; upd(); }, 'Label'), ni);
      const req = el('label', 'small text-nowrap', '<input type="checkbox"> required'); $('input', req).checked = !!x.required; $('input', req).onchange = e => { x.required = e.target.checked; upd(); };
      const mv = k => () => { const j = i + k; if (j < 0 || j >= F.fields.length) return; [F.fields[i], F.fields[j]] = [F.fields[j], F.fields[i]]; draw(); upd(); };
      r2.append(select(['text', 'email', 'tel', 'textarea', 'select', 'checkbox'], x.type, v => { x.type = v; draw(); upd(); }), req, btn('↑', mv(-1)), btn('↓', mv(1)), btn('✕', () => { F.fields.splice(i, 1); draw(); upd(); }, 'btn-outline-danger'));
      c.append(r1, r2);
      if (x.type === 'select') { const t = el('textarea', 'form-control form-control-sm'); t.rows = 4; t.placeholder = 'One option per line'; t.value = (x.options || []).join('\n'); t.oninput = () => { x.options = t.value.split('\n').map(s => s.trim()).filter(Boolean); upd(); }; c.append(t); }
      else if (x.type !== 'checkbox') c.append(input('text', x.placeholder, v => { x.placeholder = v; upd(); }, 'Placeholder'));
      side.append(c);
    });
    side.append(btn('+ Add field', () => { F.fields.push({ name: 'field_' + (F.fields.length + 1), label: 'New field', type: 'text', required: false }); draw(); upd(); }, 'btn-primary'));
    upd();
  }
  draw();
}

async function messagesView() {
  let items = await call('messages'); const box = el('div', 'scroll');
  const draw = () => {
    box.innerHTML = '';
    if (!items.length) return box.append(el('p', 'text-muted', 'No messages yet.'));
    const t = el('table', 'table align-middle', '<thead><tr><th>Date (UTC)</th><th>Form</th><th>From</th><th>Mail</th><th></th></tr></thead>'), tb = el('tbody'); t.append(tb);
    items.forEach(m => {
      const get = n => (m.fields.find(f => f.name === n) || {}).value || '';
      const tr = el('tr', m.read_at ? '' : 'fw-bold', `<td>${esc(m.created_at)}</td><td>${esc(m.form_id)}</td><td>${esc(get('name'))} ${m.email ? '&lt;' + esc(m.email) + '&gt;' : ''}</td><td><span class="badge text-bg-${m.mail_status === 'failed' ? 'danger' : m.mail_status === 'sent' ? 'success' : 'secondary'}" title="${esc(m.mail_error || '')}">${esc(m.mail_status)}</span></td><td class="text-end"></td>`);
      const dt = el('tr', '', `<td colspan="5" class="bg-body-tertiary">${m.fields.map(f => `<div class="mb-2"><div class="small text-muted">${esc(f.label)}</div><div style="white-space:pre-wrap">${esc(f.value)}</div></div>`).join('')}<div class="small text-muted">IP ${esc(m.ip)} · ${esc(m.user_agent)}${m.mail_error ? '<br>Mail error: ' + esc(m.mail_error) : ''}</div></td>`);
      dt.hidden = true;
      $('td:last-child', tr).append(btn('Open', async () => { dt.hidden = !dt.hidden; if (!dt.hidden && !m.read_at) { await call('message_read', { id: m.id }); m.read_at = 'now'; tr.classList.remove('fw-bold'); } }),
        document.createTextNode(' '), btn('Delete', async () => { if (confirm('Delete this message?')) { await call('message_delete', { id: m.id }); items = items.filter(x => x.id !== m.id); draw(); } }, 'btn-outline-danger'));
      tb.append(tr, dt);
    });
    box.append(t);
  };
  draw(); app.append(box);
}

/* ───────── Boot (the server-side router decides which view; PAGE comes from the PHP layout) ───────── */
(async () => {
  const id = PAGE.id;
  const views = { dashboard, pages: pagesList, page: () => pageEditor(id), blocks: () => blockBuilder(id), media: mediaView, forms: () => formsView(id), messages: messagesView, categories: categoriesView, settings: settingsView };
  try { await (views[PAGE.view] || dashboard)(); } catch (e) { if (!e.error) console.error(e); }
})();
