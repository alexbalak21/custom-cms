<?php
// Storage + renderer + compiler. No dependencies.

class Store {
    static function dir(string $kind): string {
        $d = __DIR__ . "/data/$kind";
        if (!is_dir($d)) mkdir($d, 0775, true);
        return $d;
    }
    static function id(string $s): string { return preg_replace('/[^a-z0-9_-]/', '', strtolower($s)); }
    static function all(string $kind): array {
        self::seed();
        $out = [];
        foreach (glob(self::dir($kind) . '/*.json') as $f) $out[] = json_decode(file_get_contents($f), true);
        return $out;
    }
    static function get(string $kind, string $id): ?array {
        $f = self::dir($kind) . '/' . self::id($id) . '.json';
        return is_file($f) ? json_decode(file_get_contents($f), true) : null;
    }
    static function save(string $kind, array $d): array {
        $d['id'] = self::id($d['id'] ?? '') ?: 'untitled';
        file_put_contents(self::dir($kind) . "/{$d['id']}.json", json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $d;
    }
    static function delete(string $kind, string $id): void {
        $f = self::dir($kind) . '/' . self::id($id) . '.json';
        if (is_file($f)) unlink($f);
    }
    static function seed(): void {
        static $done = false; if ($done) return; $done = true;
        if (glob(self::dir('blocks') . '/*.json')) return;
        $b = [
          ['id'=>'hero','label'=>'Hero','category'=>'Marketing',
           'fields'=>[['name'=>'eyebrow','type'=>'text','label'=>'Eyebrow'],['name'=>'title','type'=>'text','label'=>'Title'],['name'=>'description','type'=>'textarea','label'=>'Description'],['name'=>'cta','type'=>'link','label'=>'Button']],
           'template'=>"<span class=\"text-uppercase small opacity-75\">{{eyebrow}}</span>\n<h1 class=\"display-4 fw-bold\">{{title}}</h1>\n<p class=\"lead\">{{description}}</p>\n<a class=\"btn btn-primary btn-lg\" href=\"{{cta.url}}\">{{cta.label}}</a>",
           'css'=>"& h1 { letter-spacing: -.02em; }\n& .btn { border-radius: 999px; }",
           'sample'=>['eyebrow'=>'Industrial Excellence','title'=>'Custom Wiring Solutions','description'=>'Built for demanding environments.','cta'=>['label'=>'Get a quote','url'=>'#contact']]],
          ['id'=>'cards','label'=>'Cards','category'=>'Content',
           'fields'=>[['name'=>'heading','type'=>'text','label'=>'Heading'],['name'=>'items','type'=>'repeater','label'=>'Cards','fields'=>[['name'=>'title','type'=>'text','label'=>'Title'],['name'=>'text','type'=>'textarea','label'=>'Text']]]],
           'template'=>"<h2 class=\"mb-4 text-center\">{{heading}}</h2>\n<div class=\"row g-4\">\n{{#each items}}\n<div class=\"col-md-4\"><div class=\"card h-100\"><div class=\"card-body\"><h5 class=\"card-title\">{{title}}</h5><p class=\"card-text\">{{text}}</p></div></div></div>\n{{/each}}\n</div>",
           'css'=>"& .card { transition: transform .2s; }\n& .card:hover { transform: translateY(-4px); }",
           'sample'=>['heading'=>'Why choose us','items'=>[['title'=>'Durable','text'=>'Tested in harsh conditions.'],['title'=>'Custom','text'=>'Designed to your spec.'],['title'=>'Fast','text'=>'Short lead times.']]]],
          ['id'=>'cta','label'=>'Call to action','category'=>'Marketing',
           'fields'=>[['name'=>'title','type'=>'text','label'=>'Title'],['name'=>'button','type'=>'link','label'=>'Button']],
           'template'=>"<div class=\"text-center\"><h2>{{title}}</h2><a class=\"btn btn-light btn-lg mt-3\" href=\"{{button.url}}\">{{button.label}}</a></div>",
           'css'=>'','sample'=>['title'=>'Ready to start?','button'=>['label'=>'Contact us','url'=>'#']]],
        ];
        foreach ($b as $x) self::save('blocks', $x);
        self::save('pages', ['id'=>'home','title'=>'Home','slug'=>'','blocks'=>[
            ['block'=>'hero','content'=>$b[0]['sample'],'settings'=>['theme'=>'dark','spacing'=>'xl','container'=>'boxed']],
            ['block'=>'cards','content'=>$b[1]['sample'],'settings'=>['theme'=>'default','spacing'=>'large','container'=>'boxed']],
            ['block'=>'cta','content'=>$b[2]['sample'],'settings'=>['theme'=>'primary','spacing'=>'large','container'=>'narrow']],
        ]]);
    }
}

class Renderer {
    // Semantic settings -> Bootstrap classes. Swap these maps to change framework.
    const THEME   = ['default'=>'', 'light'=>'bg-light', 'dark'=>'bg-dark text-white', 'primary'=>'bg-primary text-white', 'secondary'=>'bg-secondary text-white'];
    const SPACING = ['none'=>'py-0', 'small'=>'py-3', 'medium'=>'py-4', 'large'=>'py-5', 'xl'=>'py-5 my-5'];
    const CONTAINER = ['narrow'=>'container" style="max-width:720px', 'boxed'=>'container', 'wide'=>'container-xl', 'full'=>'container-fluid'];

    static function path(array $d, string $p) {
        foreach (explode('.', $p) as $k) { if (!is_array($d) || !array_key_exists($k, $d)) return ''; $d = $d[$k]; }
        return is_scalar($d) ? (string)$d : '';
    }
    // Tiny template engine: {{x}} escaped, {{{x}}} raw, {{a.b}}, {{#each list}}..{{/each}}, {{this}}
    static function tpl(string $t, array $d): string {
        $t = preg_replace_callback('/\{\{#each ([\w.]+)\}\}(.*?)\{\{\/each\}\}/s', function ($m) use ($d) {
            $list = $d; foreach (explode('.', $m[1]) as $k) $list = is_array($list) ? ($list[$k] ?? []) : [];
            $o = ''; foreach ((array)$list as $it) $o .= self::tpl($m[2], is_array($it) ? $it : ['this' => $it]);
            return $o;
        }, $t);
        $t = preg_replace_callback('/\{\{\{([\w.]+)\}\}\}/', fn($m) => self::path($d, $m[1]), $t);
        return preg_replace_callback('/\{\{([\w.]+)\}\}/', fn($m) => htmlspecialchars(self::path($d, $m[1]), ENT_QUOTES), $t);
    }
    static function section(array $block, array $content, array $s): string {
        $id = Store::id($block['id'] ?? 'x');
        $cls = trim('blk blk-' . $id . ' ' . (self::THEME[$s['theme'] ?? 'default'] ?? '') . ' ' . (self::SPACING[$s['spacing'] ?? 'medium'] ?? ''));
        $con = self::CONTAINER[$s['container'] ?? 'boxed'] ?? 'container';
        return "<section class=\"$cls\"><div class=\"$con\">" . self::tpl($block['template'] ?? '', $content) . "</div></section>\n";
    }
    // Custom CSS is scoped with native CSS nesting: .blk-ID { ...your css, & = the block... }
    static function css(array $block): string {
        $c = trim($block['css'] ?? '');
        return $c === '' ? '' : '.blk-' . Store::id($block['id']) . " {\n$c\n}\n";
    }
    static function document(string $title, string $body, string $css): string {
        return "<!doctype html>\n<html lang=\"en\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">\n<title>" . htmlspecialchars($title) . "</title>\n<link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css\" rel=\"stylesheet\">\n<style>\n$css</style></head>\n<body>\n$body<script src=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js\"></script>\n</body></html>";
    }
    static function block(array $b): string {
        $s = ['theme'=>'default','spacing'=>'medium','container'=>'boxed'];
        return self::document($b['label'] ?? 'Block', self::section($b, $b['sample'] ?? [], $s), self::css($b));
    }
    static function page(array $p): string {
        $body = ''; $css = []; 
        foreach ($p['blocks'] ?? [] as $i) {
            $b = Store::get('blocks', $i['block'] ?? ''); if (!$b) continue;
            $body .= self::section($b, $i['content'] ?? [], $i['settings'] ?? []);
            $css[$b['id']] = self::css($b);
        }
        return self::document($p['title'] ?? 'Page', $body, implode("\n", $css));
    }
    static function compile(array $p): string {
        $dir = __DIR__ . '/dist'; if (!is_dir($dir)) mkdir($dir, 0775, true);
        $name = (Store::id($p['slug'] ?? '') ?: 'index') . '.html';
        file_put_contents("$dir/$name", self::page($p));
        return "dist/$name";
    }
}