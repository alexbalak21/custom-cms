<?php
// Core classes: storage, pages, DB/RPC, SEO, renderer, media
const JF = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

/* ── Storage ─────────────────────────────────────────────── */
class Store {
    static function dir(string $kind): string {
        $d = ROOT . "/storage/data/$kind";
        if (!is_dir($d)) { mkdir($d, 0775, true); file_put_contents(ROOT . '/storage/data/.htaccess', "Require all denied\n"); }
        return $d;
    }
    static function id(string $s): string { return preg_replace('/[^a-z0-9_-]/', '', strtolower($s)); }
    static function all(string $kind): array {
        self::seed(); $o = [];
        foreach (glob(self::dir($kind) . '/*.json') as $f) $o[] = json_decode(file_get_contents($f), true);
        usort($o, fn($a, $b) => strcmp($a['title'] ?? $a['label'] ?? '', $b['title'] ?? $b['label'] ?? ''));
        return $o;
    }
    static function get(string $kind, string $id): ?array { $f = self::dir($kind) . '/' . self::id($id) . '.json'; return is_file($f) ? json_decode(file_get_contents($f), true) : null; }
    static function save(string $kind, array $d): array {
        $d['id'] = self::id($d['id'] ?? '') ?: 'untitled';
        file_put_contents(self::dir($kind) . "/{$d['id']}.json", json_encode($d, JF)); return $d;
    }
    static function delete(string $kind, string $id): void { $f = self::dir($kind) . '/' . self::id($id) . '.json'; if (is_file($f)) unlink($f); }
    static function json(string $name, $def = []) { $f = ROOT . "/storage/data/$name.json"; return is_file($f) ? json_decode(file_get_contents($f), true) : $def; }
    static function put(string $name, $d): void { self::dir('blocks'); file_put_contents(ROOT . "/storage/data/$name.json", json_encode($d, JF)); }

    // Layout blocks for nesting (created once; safe to edit or delete afterwards)
    static function ensureNesting(): void {
        static $r = false; if ($r) return; $r = true;
        if (self::json('nesting_defaults', false)) return;
        $defs = [
          ['id'=>'row','label'=>'Row (columns)','category'=>'Layout','container'=>true,'layout'=>'row','fields'=>[],'template'=>'<div class="row g-4">{{{children}}}</div>','css'=>'','sample'=>new stdClass],
          ['id'=>'stack','label'=>'Stack (vertical)','category'=>'Layout','container'=>true,'layout'=>'stack','fields'=>[],'template'=>'{{{children}}}','css'=>'','sample'=>new stdClass],
          ['id'=>'card','label'=>'Card','category'=>'Content','fields'=>[['name'=>'title','type'=>'text','label'=>'Title'],['name'=>'text','type'=>'textarea','label'=>'Text'],['name'=>'link','type'=>'link','label'=>'Button (optional)']],
           'template'=>"<div class=\"card h-100\"><div class=\"card-body\"><h5 class=\"card-title\">{{title}}</h5><p class=\"card-text\">{{text}}</p>{{#if link.label}}<a class=\"btn btn-outline-primary btn-sm\" href=\"{{link.url}}\">{{link.label}}</a>{{/if}}</div></div>",
           'css'=>'','sample'=>['title'=>'Card title','text'=>'Card text.','link'=>['label'=>'','url'=>'']]],
        ];
        foreach ($defs as $b) if (!self::get('blocks', $b['id'])) self::save('blocks', $b);
        self::put('nesting_defaults', true);
    }

    static function seed(): void {
        static $done = false; if ($done) return; $done = true;
        $fresh = !glob(self::dir('blocks') . '/*.json');     // decide BEFORE defaults create any block
        Form::ensureDefaults(); self::ensureNesting();
        if (!$fresh) return;
        $blocks = [
          ['id'=>'hero','label'=>'Hero','category'=>'Marketing',
           'fields'=>[['name'=>'eyebrow','type'=>'text','label'=>'Eyebrow'],['name'=>'title','type'=>'text','label'=>'Title'],['name'=>'description','type'=>'textarea','label'=>'Description'],['name'=>'cta','type'=>'link','label'=>'Button'],['name'=>'image','type'=>'image','label'=>'Image']],
           'template'=>"<span class=\"text-uppercase small opacity-75\">{{eyebrow}}</span>\n<h1 class=\"display-4 fw-bold\">{{title}}</h1>\n<p class=\"lead\">{{description}}</p>\n<a class=\"btn btn-primary btn-lg\" href=\"{{cta.url}}\">{{cta.label}}</a>",
           'css'=>"& h1 { letter-spacing: -.02em; }\n& .btn { border-radius: 999px; }",
           'sample'=>['eyebrow'=>'Industrial Excellence','title'=>'Custom Solutions','description'=>'Built for demanding environments.','cta'=>['label'=>'Get a quote','url'=>'/contact']]],
          ['id'=>'cards','label'=>'Cards','category'=>'Content',
           'fields'=>[['name'=>'heading','type'=>'text','label'=>'Heading'],['name'=>'items','type'=>'repeater','label'=>'Cards','fields'=>[['name'=>'title','type'=>'text','label'=>'Title'],['name'=>'text','type'=>'textarea','label'=>'Text']]]],
           'template'=>"<h2 class=\"mb-4 text-center\">{{heading}}</h2>\n<div class=\"row g-4\">\n{{#each items}}\n<div class=\"col-md-4\"><div class=\"card h-100\"><div class=\"card-body\"><h5 class=\"card-title\">{{title}}</h5><p class=\"card-text\">{{text}}</p></div></div></div>\n{{/each}}\n</div>",
           'css'=>"& .card { transition: transform .2s; }\n& .card:hover { transform: translateY(-4px); }",
           'sample'=>['heading'=>'Why choose us','items'=>[['title'=>'Durable','text'=>'Tested in harsh conditions.'],['title'=>'Custom','text'=>'Designed to your spec.'],['title'=>'Fast','text'=>'Short lead times.']]]],
          ['id'=>'cta','label'=>'Call to action','category'=>'Marketing',
           'fields'=>[['name'=>'title','type'=>'text','label'=>'Title'],['name'=>'button','type'=>'link','label'=>'Button']],
           'template'=>"<div class=\"text-center\"><h2>{{title}}</h2><a class=\"btn btn-light btn-lg mt-3\" href=\"{{button.url}}\">{{button.label}}</a></div>",
           'css'=>'','sample'=>['title'=>'Ready to start?','button'=>['label'=>'Contact us','url'=>'/contact']]],
          ['id'=>'text','label'=>'Text','category'=>'Content',
           'fields'=>[['name'=>'title','type'=>'text','label'=>'Title'],['name'=>'body','type'=>'textarea','label'=>'Body']],
           'template'=>"<h2>{{title}}</h2>\n<p style=\"white-space:pre-line\">{{body}}</p>",'css'=>'','sample'=>['title'=>'Contact','body'=>'Write to us at info@example.com']],
          ['id'=>'product-list','label'=>'Product list (MySQL)','category'=>'Commerce',
           'fields'=>[['name'=>'heading','type'=>'text','label'=>'Heading'],['name'=>'limit','type'=>'text','label'=>'Max products']],
           'rpc'=>['call'=>'products.list','params'=>['limit'=>'@content.limit'],'as'=>'products'],
           'template'=>"<h2 class=\"mb-4\">{{heading}}</h2>\n<div class=\"row g-4\">\n{{#each products}}\n<div class=\"col-md-4\"><div class=\"card h-100\"><div class=\"card-body\"><div class=\"text-muted small\">{{reference}}</div><h5>{{title}}</h5><p class=\"text-muted\">{{size}}</p><p class=\"fw-bold\">€{{price}}</p></div><div class=\"card-footer bg-transparent border-0\"><a href=\"{{url}}\" class=\"btn btn-outline-primary btn-sm\">View</a></div></div></div>\n{{/each}}\n</div>",
           'css'=>'','sample'=>['heading'=>'Products','limit'=>'6']],
          ['id'=>'product-detail','label'=>'Product detail (MySQL)','category'=>'Commerce',
           'fields'=>[['name'=>'buy','type'=>'link','label'=>'Button']],
           'rpc'=>['call'=>'products.get','params'=>['reference'=>'@page.vars.reference'],'as'=>'product'],
           'template'=>"<div class=\"row align-items-center\"><div class=\"col-lg-7\"><div class=\"text-muted\">{{product.reference}}</div><h1>{{product.title}}</h1><p class=\"lead\">{{product.size}}</p><p>{{product.description}}</p></div><div class=\"col-lg-5 text-lg-end\"><div class=\"display-6 fw-bold\">€{{product.price}}</div><a class=\"btn btn-primary btn-lg mt-2\" href=\"{{buy.url}}\">{{buy.label}}</a></div></div>",
           'css'=>'','sample'=>['buy'=>['label'=>'Request a quote','url'=>'/contact']]],
        ];
        foreach ($blocks as $b) self::save('blocks', $b);
        self::put('categories', [['slug'=>'convenient-assay-kits','title'=>'Convenient Assay Kits','description'=>'Ready-to-use assay kits for research.']]);
        self::put('site', ['name'=>cfg('site_name'),'base_url'=>cfg('base_url')]);
        $S = fn($b, $s = 'large', $t = 'default', $c = 'boxed') => ['block'=>$b['id'],'content'=>$b['sample'],'settings'=>['theme'=>$t,'spacing'=>$s,'container'=>$c]];
        $ld = <<<'LD'
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"Product","name":"PRECICE® ADK Assay Kit","sku":"K0507-01","brand":{"@type":"Brand","name":"NOVOCIB"},"offers":{"@type":"Offer","priceCurrency":"EUR","price":"530.00","availability":"https://schema.org/InStock"}}
</script>
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"FAQPage","mainEntity":[{"@type":"Question","name":"Is the ADK Assay Kit radioactive?","acceptedAnswer":{"@type":"Answer","text":"No. It is a non-radioactive, homogeneous assay monitored at 340 nm."}}]}
</script>
LD;
        $card = fn($t, $x) => ['block'=>'card','content'=>['title'=>$t,'text'=>$x,'link'=>['label'=>'','url'=>'']],'settings'=>['theme'=>'default','spacing'=>'none','width'=>'4']];
        $rowDemo = ['block'=>'row','content'=>new stdClass,'settings'=>['theme'=>'default','spacing'=>'large','container'=>'boxed'],'children'=>[$card('Durable','Tested in harsh conditions.'),$card('Custom','Designed to your spec.'),$card('Fast','Short lead times.')]];
        Pages::save(['id'=>'home','title'=>'Home','slug'=>'','category'=>'','status'=>'published','nav'=>false,'vars'=>new stdClass,'seo'=>['title'=>'Custom CMS - Home','description'=>'Welcome to Custom CMS.'],
            'blocks'=>[$S($blocks[0],'xl','dark'), $S($blocks[4]), $S($blocks[1],'large','light'), $rowDemo, $S($blocks[2],'large','primary','narrow')]]);
        Pages::save(['id'=>'contact','title'=>'Contact','slug'=>'contact','category'=>'','status'=>'published','nav'=>true,'vars'=>new stdClass,'seo'=>['description'=>'Contact us.'],'blocks'=>[$S($blocks[3]), ['block'=>'contact-form','content'=>['heading'=>'Send us a message','intro'=>'We usually reply within one business day.','form'=>'contact'],'settings'=>['theme'=>'light','spacing'=>'large','container'=>'boxed']]]]);
        Pages::save(['id'=>'adk-assay-kit','title'=>'PRECICE® ADK Assay Kit','slug'=>'adk-assay-kit','category'=>'convenient-assay-kits','status'=>'published','nav'=>false,'vars'=>['reference'=>'K0507-01'],
            'seo'=>['title'=>'PRECICE® ADK Assay Kit - Human Adenosine Kinase Activity Assay','description'=>"Non-radioactive, HTS-ready assay kit to measure human adenosine kinase (ADK) activity and screen ADK inhibitors. 96-well plate format, Z'-factor 0.68.",'keywords'=>'ADK assay kit, adenosine kinase assay, adenosine kinase inhibitor screening, HTS ADK assay','og_title'=>'PRECICE® ADK Assay Kit','og_type'=>'product','og_image'=>'','jsonld_raw'=>$ld],
            'blocks'=>[$S($blocks[5]), $S($blocks[2],'large','primary','narrow')]]);
    }
}

/* ── Pages ───────────────────────────────────────────────── */
class Pages {
    const RESERVED = ['admin', 'uploads', 'rpc', 'dist', 'data', 'sitemap-xml', 'robots-txt'];
    static function path(array $p): string { return trim(($p['category'] ?? '') . '/' . ($p['slug'] ?? ''), '/'); }
    static function url(array $p): string { return url(self::path($p)); }
    static function find(string $cat, string $slug, bool $drafts = false): ?array {
        foreach (Store::all('pages') as $p)
            if (($p['slug'] ?? '') === $slug && ($p['category'] ?? '') === $cat && ($drafts || ($p['status'] ?? '') === 'published')) return $p;
        return null;
    }
    // map of var value => page url (e.g. product reference => /category/page)
    static function byVar(string $k): array { $o = []; foreach (Store::all('pages') as $p) if (($p['status'] ?? '') === 'published' && !empty($p['vars'][$k])) $o[$p['vars'][$k]] = self::url($p); return $o; }
    static function depth(array $blocks): int { $m = 0; foreach ($blocks as $b) $m = max($m, 1 + self::depth($b['children'] ?? [])); return $m; }
    static function save(array $p): array {
        $p['title'] = trim($p['title'] ?? '') ?: 'Untitled';
        $p['slug'] = slugify($p['slug'] ?? ''); $p['category'] = slugify($p['category'] ?? '');
        $p['status'] = in_array($p['status'] ?? '', ['draft', 'published']) ? $p['status'] : 'draft';
        $p['vars'] = $p['vars'] ?? new stdClass;
        if (self::depth($p['blocks'] ?? []) > Renderer::MAX_DEPTH) throw new RuntimeException('Blocks are nested too deeply (maximum 4 levels)');
        if ($p['slug'] === '' && $p['category'] !== '') throw new RuntimeException('A page in a category needs a slug');
        if ($p['category'] === '' && in_array($p['slug'], self::RESERVED)) throw new RuntimeException('Reserved slug');
        if ($p['category'] === '' && $p['slug'] !== '') foreach (Store::json('categories', []) as $c) if ($c['slug'] === $p['slug']) throw new RuntimeException('Slug is used by a category');
        foreach (Store::all('pages') as $o)
            if ($o['id'] !== Store::id($p['id'] ?? '') && $o['slug'] === $p['slug'] && ($o['category'] ?? '') === $p['category']) throw new RuntimeException('URL already used by "' . $o['title'] . '"');
        if (!empty($p['seo']['jsonld_raw']) && Seo::ld($p['seo']['jsonld_raw']) === null) throw new RuntimeException('JSON-LD is not valid JSON');
        $p['updated_at'] = gmdate('c');
        return Store::save('pages', $p);
    }
}

/* ── Database + remote procedures ────────────────────────── */
class Db {
    static function pdo(): ?PDO {
        static $pdo = false; if ($pdo !== false) return $pdo;
        $c = cfg('db', []);
        try { $pdo = !empty($c['database']) ? new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['database']};charset=utf8mb4", $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]) : null; }
        catch (Throwable $e) { error_log('DB: ' . $e->getMessage()); $pdo = null; }
        return $pdo;
    }
    static function all(PDO $db, string $sql, array $a = []): array { $s = $db->prepare($sql); $s->execute($a); return $s->fetchAll(PDO::FETCH_ASSOC); }
}
class Rpc {
    static function procs(): array { return require ROOT . '/procedures.php'; }
    static function call(string $name, array $params) {
        $p = self::procs()[$name] ?? null; if (!$p) return null;
        $db = Db::pdo(); if (!$db) return $p['sample'] ?? null;
        try { return $p['run']($db, $params); } catch (Throwable $e) { error_log("RPC $name: " . $e->getMessage()); return $p['sample'] ?? null; }
    }
}

/* ── SEO ─────────────────────────────────────────────────── */
class Seo {
    static function ld(string $raw): ?array {
        $raw = trim($raw); if ($raw === '') return [];
        preg_match_all('#<script[^>]*>(.*?)</script>#is', $raw, $m);
        $out = [];
        foreach ($m[1] ?: [$raw] as $c) { $d = json_decode(trim($c), true); if (!is_array($d)) return null; $out = array_merge($out, array_is_list($d) ? $d : [$d]); }
        return $out;
    }
    static function abs(string $u, array $site): string { return preg_match('#^https?://#', $u) || $u === '' ? $u : rtrim($site['base_url'] ?? cfg('base_url'), '/') . '/' . ltrim($u, '/'); }
    static function head(array $p, array $site, string $path): string {
        $s = $p['seo'] ?? []; $name = $site['name'] ?? cfg('site_name');
        $title = ($s['title'] ?? '') ?: (($p['title'] ?? '') . ' - ' . $name);
        $desc = $s['description'] ?? '';
        $h = '<title>' . e($title) . "</title>\n";
        if (!empty($s['keywords'])) $h .= '<meta name="keywords" content="' . e($s['keywords']) . "\">\n";
        if ($desc) $h .= '<meta name="description" content="' . e($desc) . "\">\n";
        if (!empty($s['noindex'])) $h .= "<meta name=\"robots\" content=\"noindex,nofollow\">\n";
        $h .= '<meta property="og:title" content="' . e(($s['og_title'] ?? '') ?: $title) . "\">\n";
        if ($desc || !empty($s['og_description'])) $h .= '<meta property="og:description" content="' . e(($s['og_description'] ?? '') ?: $desc) . "\">\n";
        if (!empty($s['og_image'])) $h .= '<meta property="og:image" content="' . e(self::abs($s['og_image'], $site)) . "\">\n";
        $h .= '<meta property="og:type" content="' . e(($s['og_type'] ?? '') ?: 'website') . "\">\n";
        $h .= '<meta property="og:site_name" content="' . e($name) . "\">\n";
        $h .= '<link rel="canonical" href="' . e(($s['canonical'] ?? '') ?: rtrim($site['base_url'] ?? cfg('base_url'), '/') . '/' . $path) . "\">\n";
        foreach (self::ld($s['jsonld_raw'] ?? '') ?? [] as $d)
            $h .= '<script type="application/ld+json">' . json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . "</script>\n";
        return $h;
    }
}

/* ── Renderer / compiler ─────────────────────────────────── */
class Renderer {
    // Semantic settings -> Bootstrap classes. Swap these maps to change framework.
    const THEME = ['default'=>'', 'light'=>'bg-light', 'dark'=>'bg-dark text-white', 'primary'=>'bg-primary text-white', 'secondary'=>'bg-secondary text-white'];
    const SPACING = ['none'=>'py-0', 'small'=>'py-3', 'medium'=>'py-4', 'large'=>'py-5', 'xl'=>'py-5 my-5'];
    const CONTAINER = ['narrow'=>'container" style="max-width:720px', 'boxed'=>'container', 'wide'=>'container-xl', 'full'=>'container-fluid'];

    static function get($d, string $p) { foreach (explode('.', $p) as $k) { if (!is_array($d) || !array_key_exists($k, $d)) return null; $d = $d[$k]; } return $d; }
    static function path(array $d, string $p): string { $v = self::get($d, $p); return is_scalar($v) ? (string)$v : ''; }
    // {{x}} escaped · {{{x}}} raw · {{a.b}} · {{#each list}}…{{/each}} · {{#if x}}…{{/if}} · {{this}}
    static function tpl(string $t, array $d): string {
        $t = preg_replace_callback('/\{\{#each ([\w.]+)\}\}(.*?)\{\{\/each\}\}/s', function ($m) use ($d) {
            $o = ''; foreach ((array)(self::get($d, $m[1]) ?? []) as $it) $o .= self::tpl($m[2], is_array($it) ? $it : ['this' => $it]); return $o;
        }, $t);
        $t = preg_replace_callback('/\{\{#if ([\w.]+)\}\}(.*?)\{\{\/if\}\}/s', fn($m) => !empty(self::get($d, $m[1])) ? $m[2] : '', $t);
        return preg_replace_callback('/\{\{\{([\w.]+)\}\}\}|\{\{([\w.]+)\}\}/',
            fn($m) => isset($m[2]) ? str_replace(['{', '}'], ['&#123;', '&#125;'], e(self::path($d, $m[2]))) : self::path($d, $m[1]), $t);
    }
    // "@content.x" / "@page.vars.sku" / literal
    static function resolve($v, array $content, array $ctx) {
        if (!is_string($v) || !str_starts_with($v, '@')) return $v;
        [$root, $rest] = array_pad(explode('.', substr($v, 1), 2), 2, '');
        $src = $root === 'page' ? ($ctx['page'] ?? []) : $content;
        return $rest === '' ? $src : self::get($src, $rest);
    }
    const MAX_DEPTH = 4;
    static array $cssBag = [];
    static function instance(array $i, array $ctx, int $depth): string {
        if ($depth > self::MAX_DEPTH || !($b = Store::get('blocks', $i['block'] ?? ''))) return '';
        return self::section($b, $i['content'] ?? [], $i['settings'] ?? [], $ctx, $i['children'] ?? [], $depth);
    }
    static function section(array $b, array $content, array $s, array $ctx = [], array $children = [], int $depth = 1): string {
        self::$cssBag[$b['id'] ?? 'x'] = self::css($b);
        $data = $content; $data['page'] = $ctx['page'] ?? [];
        if (($b['component'] ?? '') === 'form') $data['form_html'] = Form::html(Form::get($content['form'] ?? 'contact'));
        if (!empty($b['rpc']['call'])) {
            $r = $b['rpc']; $params = [];
            foreach ($r['params'] ?? [] as $k => $v) $params[$k] = self::resolve($v, $content, $ctx);
            $data[$r['as'] ?? 'data'] = Rpc::call($r['call'], $params) ?? [];
        }
        if (!empty($b['container'])) {                       // nested blocks: render children, expose as {{{children}}}
            $kids = '';
            foreach ($children as $ch) {
                $html = self::instance($ch, $ctx, $depth + 1); if ($html === '') continue;
                if (($b['layout'] ?? 'stack') === 'row') { $w = (int)($ch['settings']['width'] ?? 0); $html = '<div class="' . ($w >= 1 && $w <= 12 ? "col-12 col-md-$w" : 'col-12 col-md') . "\">$html</div>"; }
                $kids .= $html;
            }
            $data['children'] = $kids;
        }
        $cls = trim('blk blk-' . Store::id($b['id'] ?? 'x') . ' ' . (self::THEME[$s['theme'] ?? 'default'] ?? '') . ' ' . (self::SPACING[$s['spacing'] ?? ($depth > 1 ? 'none' : 'medium')] ?? ''));
        $inner = self::tpl($b['template'] ?? '', $data);
        if ($depth > 1) return "<div class=\"$cls\">$inner</div>\n";                      // nested: no <section>/container wrapper
        $con = self::CONTAINER[$s['container'] ?? 'boxed'] ?? 'container';
        return "<section class=\"$cls\"><div class=\"$con\">$inner</div></section>\n";
    }
    // Custom CSS scoped via native CSS nesting: & = the block
    static function css(array $b): string { $c = trim($b['css'] ?? ''); return $c === '' ? '' : '.blk-' . Store::id($b['id']) . " {\n$c\n}\n"; }
    static function document(string $head, string $body, string $css): string {
        return "<!doctype html>\n<html lang=\"en\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">\n$head<link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css\" rel=\"stylesheet\">\n<style>\n$css</style></head>\n<body>\n$body<script src=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js\"></script>\n</body></html>";
    }
    static function block(array $b): string {
        self::$cssBag = [];
        return self::document('<title>' . e($b['label'] ?? 'Block') . "</title>\n", self::section($b, $b['sample'] ?? [], ['theme'=>'default','spacing'=>'medium','container'=>'boxed']), implode("\n", self::$cssBag));
    }
    static function chrome(string $main, array $site): string {
        $links = '<li class="nav-item"><a class="nav-link" href="' . url() . '">Home</a></li>';
        foreach (Store::json('categories', []) as $c) $links .= '<li class="nav-item"><a class="nav-link" href="' . url($c['slug']) . '">' . e($c['title']) . '</a></li>';
        foreach (Store::all('pages') as $p) if (!empty($p['nav']) && ($p['status'] ?? '') === 'published' && ($p['category'] ?? '') === '' && ($p['slug'] ?? '') !== '')
            $links .= '<li class="nav-item"><a class="nav-link" href="' . Pages::url($p) . '">' . e($p['title']) . '</a></li>';
        return '<nav class="navbar navbar-expand bg-body-tertiary"><div class="container"><a class="navbar-brand" href="' . url() . '">' . e($site['name'] ?? '') . "</a><ul class=\"navbar-nav me-auto\">$links</ul><form class=\"d-flex\" action=\"" . url('search') . "\"><input class=\"form-control form-control-sm\" type=\"search\" name=\"q\" placeholder=\"Search\" aria-label=\"Search\"></form></div></nav>\n<main>$main</main>\n<footer class=\"py-4 text-center text-muted small\">© " . date('Y') . ' ' . e($site['name'] ?? '') . "</footer>\n";
    }
    static function page(array $p): string {
        $site = Store::json('site', []); $body = ''; $ctx = ['page' => $p]; self::$cssBag = [];
        foreach ($p['blocks'] ?? [] as $i) $body .= self::instance($i, $ctx, 1);
        return self::document(Seo::head($p, $site, Pages::path($p)), self::chrome($body, $site), implode("\n", self::$cssBag));
    }
    static function category(array $c): string {
        $site = Store::json('site', []); $cards = '';
        foreach (Store::all('pages') as $p) if (($p['category'] ?? '') === $c['slug'] && ($p['status'] ?? '') === 'published')
            $cards .= '<div class="col-md-4"><div class="card h-100"><div class="card-body"><h5>' . e($p['title']) . '</h5><p class="text-muted">' . e($p['seo']['description'] ?? '') . '</p><a href="' . Pages::url($p) . '" class="stretched-link">View</a></div></div></div>';
        $body = '<section class="container py-5"><h1 class="mb-2">' . e($c['title']) . '</h1><p class="lead mb-4">' . e($c['description'] ?? '') . "</p><div class=\"row g-4\">$cards</div></section>";
        $fake = ['title' => $c['title'], 'seo' => ['description' => $c['description'] ?? '']];
        return self::document(Seo::head($fake, $site, $c['slug']), self::chrome($body, $site), '');
    }
    static function search(string $q, array $rows): string {
        $site = Store::json('site', []); $items = '';
        foreach ($rows as $r) { $u = preg_match('#^https?://#', $r['url']) ? $r['url'] : url($r['url']); $items .= '<a class="list-group-item list-group-item-action" href="' . e($u) . '"><h5 class="mb-1">' . e($r['title']) . '</h5><p class="mb-0 text-muted">' . e($r['excerpt'] ?? '') . '</p></a>'; }
        if (!$rows && $q !== '') $items = '<p class="text-muted">' . (mb_strlen($q) < 2 ? 'Type at least 2 characters.' : 'No results for “' . e($q) . '”.') . '</p>';
        $body = '<section class="container py-5"><h1 class="mb-4">Search</h1><form class="mb-4" action="' . url('search') . '"><div class="input-group"><input class="form-control" name="q" value="' . e($q) . '" aria-label="Search"><button class="btn btn-primary">Search</button></div></form><div class="list-group">' . $items . '</div></section>';
        return self::document(Seo::head(['title' => 'Search', 'seo' => ['noindex' => true]], $site, 'search'), self::chrome($body, $site), '');
    }
    static function notFound(): string {
        $site = Store::json('site', []);
        return self::document('<title>404</title>', self::chrome('<section class="container py-5 text-center"><h1>404</h1><p>Page not found.</p></section>', $site), '');
    }
    // Static export: dist/<path>/index.html (host the folder anywhere)
    static function compileAll(): array {
        $out = [];
        $w = function (string $path, string $html) use (&$out) {
            $f = ROOT . '/dist/' . ($path === '' ? '' : $path . '/') . 'index.html';
            if (!is_dir(dirname($f))) mkdir(dirname($f), 0775, true);
            file_put_contents($f, $html); $out[] = 'dist/' . ($path === '' ? '' : $path . '/') . 'index.html';
        };
        foreach (Store::all('pages') as $p) if (($p['status'] ?? '') === 'published') $w(Pages::path($p), self::page($p));
        foreach (Store::json('categories', []) as $c) $w($c['slug'], self::category($c));
        return $out;
    }
}

/* ── Media ───────────────────────────────────────────────── */
class Media {
    static function list(): array {
        $hay = ''; foreach (array_merge(glob(ROOT . '/storage/data/pages/*.json'), glob(ROOT . '/storage/data/blocks/*.json')) as $f) $hay .= file_get_contents($f);
        return array_map(fn($m) => $m + ['usage' => substr_count($hay, $m['url'])], Store::json('media', []));
    }
    static function upload(array $f): array {
        $ok = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','gif'=>'image/gif','pdf'=>'application/pdf'];
        $db = Store::json('media', []); $added = []; $dir = ROOT . '/public/uploads'; if (!is_dir($dir)) mkdir($dir, 0775, true);
        foreach ((array)$f['name'] as $i => $name) {
            if ($f['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION)); $tmp = $f['tmp_name'][$i];
            if (!isset($ok[$ext]) || $f['size'][$i] > 8 * 1048576) throw new RuntimeException("Rejected: $name (type or >8MB)");
            if (finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmp) !== $ok[$ext]) throw new RuntimeException("Rejected: $name (content does not match extension)");
            $fn = (slugify(pathinfo($name, PATHINFO_FILENAME)) ?: 'file') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
            move_uploaded_file($tmp, "$dir/$fn");
            $w = $h = null; if ($ext !== 'pdf' && ($s = @getimagesize("$dir/$fn"))) [$w, $h] = $s;
            $m = ['id'=>'m' . bin2hex(random_bytes(4)),'filename'=>$fn,'url'=>url("uploads/$fn"),'alt'=>'','folder'=>'','width'=>$w,'height'=>$h,'size'=>$f['size'][$i],'type'=>$ok[$ext],'created'=>gmdate('c')];
            array_unshift($db, $m); $added[] = $m;
        }
        Store::put('media', $db); return $added;
    }
    static function update(string $id, array $d): array {
        $db = Store::json('media', []);
        foreach ($db as &$m) if ($m['id'] === $id) { $m['alt'] = (string)($d['alt'] ?? $m['alt']); $m['folder'] = (string)($d['folder'] ?? $m['folder']); }
        Store::put('media', $db); return $db;
    }
    static function delete(string $id): void {
        $db = Store::json('media', []);
        foreach ($db as $m) if ($m['id'] === $id) @unlink(ROOT . '/public/uploads/' . basename($m['filename']));
        Store::put('media', array_values(array_filter($db, fn($m) => $m['id'] !== $id)));
    }
}
