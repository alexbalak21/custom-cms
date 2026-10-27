<?php
// Configurable forms (stored as JSON in storage/data/forms), submissions stored in MySQL + emailed.
class Form {
    const TYPES = ['text', 'email', 'tel', 'textarea', 'select', 'checkbox'];
    static function key(): string { return env('APP_KEY') ?: hash('sha256', 'cms|' . ROOT . '|' . (cfg('db')['password'] ?? '')); }
    static function get(string $id): ?array { return Store::get('forms', $id); }

    static function ensureDefaults(): void {            // runs once: default "contact" form + "Contact form" block
        static $ran = false; if ($ran) return; $ran = true;
        if (Store::json('form_defaults', false)) return;
        Store::save('forms', ['id' => 'contact', 'title' => 'Contact form', 'to' => env('MAIL_TO', 'contact@example.com'), 'subject' => 'New message from {{name}}',
            'success_message' => 'Thank you! Your message has been sent.', 'submit_label' => 'Send message', 'enabled' => true, 'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'placeholder' => '', 'options' => []],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'placeholder' => '', 'options' => []],
                ['name' => 'request', 'label' => 'Need or request', 'type' => 'select', 'required' => true, 'placeholder' => '', 'options' => ['General question', 'Quote request', 'Technical support', 'Other']],
                ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true, 'placeholder' => '', 'options' => []]]]);
        if (!Store::get('blocks', 'contact-form')) Store::save('blocks', ['id' => 'contact-form', 'label' => 'Contact form', 'category' => 'Forms', 'component' => 'form',
            'fields' => [['name' => 'heading', 'type' => 'text', 'label' => 'Heading'], ['name' => 'intro', 'type' => 'textarea', 'label' => 'Intro'], ['name' => 'form', 'type' => 'form', 'label' => 'Form']],
            'template' => "<div class=\"row justify-content-center\"><div class=\"col-lg-7\">\n<h2>{{heading}}</h2>\n<p class=\"text-muted\">{{intro}}</p>\n{{{form_html}}}\n</div></div>", 'css' => '',
            'sample' => ['heading' => 'Send us a message', 'intro' => 'We usually reply within one business day.', 'form' => 'contact']]);
        Store::put('form_defaults', true);
    }

    static function saveDef(array $f): array {
        $f['id'] = Store::id($f['id'] ?? '') ?: throw new RuntimeException('Form id required');
        $f['title'] = trim($f['title'] ?? '') ?: $f['id'];
        $to = array_values(array_filter(array_map('trim', explode(',', $f['to'] ?? ''))));
        if (!$to) throw new RuntimeException('Recipient email required');
        foreach ($to as $t) if (!filter_var($t, FILTER_VALIDATE_EMAIL)) throw new RuntimeException("Invalid recipient: $t");
        $f['to'] = implode(', ', $to); $f['enabled'] = !empty($f['enabled']);
        $f['subject'] = trim($f['subject'] ?? '') ?: 'New message'; $f['submit_label'] = trim($f['submit_label'] ?? '') ?: 'Send';
        $f['success_message'] = trim($f['success_message'] ?? '') ?: 'Thank you!';
        $seen = []; $out = [];
        foreach ($f['fields'] ?? [] as $x) {
            $n = str_replace('-', '_', slugify($x['name'] ?? '')); $t = $x['type'] ?? 'text';
            if ($n === '' || in_array($n, ['website', '_t']) || isset($seen[$n])) throw new RuntimeException("Invalid or duplicate field name: \"$n\"");
            if (!in_array($t, self::TYPES)) throw new RuntimeException("Unknown field type: $t");
            $seen[$n] = 1;
            $out[] = ['name' => $n, 'label' => trim($x['label'] ?? '') ?: $n, 'type' => $t, 'required' => !empty($x['required']), 'placeholder' => trim($x['placeholder'] ?? ''),
                'options' => $t === 'select' ? array_values(array_filter(array_map('trim', (array)($x['options'] ?? [])))) : []];
        }
        if (!$out) throw new RuntimeException('Add at least one field');
        $f['fields'] = $out; return Store::save('forms', $f);
    }

    static function html(?array $f): string {
        if (!$f) return '<div class="alert alert-warning">Form not found.</div>';
        if (empty($f['enabled'])) return '<div class="alert alert-secondary">This form is currently disabled.</div>';
        $id = e($f['id']); $h = "<div id=\"form-$id\">";
        if (isset($_GET['sent'])) $h .= '<div class="alert alert-success">' . e($f['success_message']) . '</div>';
        elseif (!empty($_GET['err'])) $h .= '<div class="alert alert-danger">' . e((string)$_GET['err']) . '</div>';
        $ts = time();
        $h .= '<div data-msg></div><form method="post" data-cms-form action="' . e(url('forms/' . $f['id'])) . '">';
        $h .= '<input type="hidden" name="_t" value="' . $ts . '.' . hash_hmac('sha256', $f['id'] . "|$ts", self::key()) . '">';
        $h .= '<div style="position:absolute;left:-9999px" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>';
        foreach ($f['fields'] as $x) {
            $n = e($x['name']); $fid = "ff-$id-$n"; $req = !empty($x['required']) ? ' required' : ''; $ph = ' placeholder="' . e($x['placeholder'] ?? '') . '"';
            if ($x['type'] === 'checkbox') { $h .= "<div class=\"form-check mb-3\"><input class=\"form-check-input\" type=\"checkbox\" name=\"$n\" value=\"1\" id=\"$fid\"$req><label class=\"form-check-label\" for=\"$fid\">" . e($x['label']) . '</label></div>'; continue; }
            $h .= "<div class=\"mb-3\"><label class=\"form-label\" for=\"$fid\">" . e($x['label']) . (!empty($x['required']) ? ' *' : '') . '</label>';
            if ($x['type'] === 'textarea') $h .= "<textarea class=\"form-control\" rows=\"5\" name=\"$n\" id=\"$fid\" maxlength=\"5000\"$ph$req></textarea>";
            elseif ($x['type'] === 'select') { $h .= "<select class=\"form-select\" name=\"$n\" id=\"$fid\"$req><option value=\"\">Choose…</option>"; foreach ($x['options'] as $o) $h .= '<option>' . e($o) . '</option>'; $h .= '</select>'; }
            else $h .= "<input class=\"form-control\" type=\"{$x['type']}\" name=\"$n\" id=\"$fid\" maxlength=\"255\"$ph$req>";
            $h .= '</div>';
        }
        $h .= '<button class="btn btn-primary">' . e($f['submit_label']) . '</button></form>';
        $h .= <<<'JS'
<script>document.querySelectorAll('form[data-cms-form]').forEach(f=>{if(f.dataset.b)return;f.dataset.b=1;f.addEventListener('submit',async e=>{e.preventDefault();const m=f.parentNode.querySelector('[data-msg]'),b=f.querySelector('button');b.disabled=true;try{const r=await fetch(f.action,{method:'POST',headers:{'X-Requested-With':'fetch'},body:new FormData(f)});const j=await r.json();m.innerHTML='';const d=document.createElement('div');d.className='alert alert-'+(j.ok?'success':'danger');d.textContent=j.message;m.append(d);if(j.ok)f.reset();}catch(x){m.textContent='Network error, please retry.';}b.disabled=false;});});</script>
JS;
        return $h . '</div>';
    }

    static function submit(string $id, array $post, array $c): array {
        $bad = fn(string $m) => ['ok' => false, 'message' => $m];
        $f = self::get($id); if (!$f || empty($f['enabled'])) return $bad('This form is not available.');
        if (!empty($post['website'])) return ['ok' => true, 'message' => $f['success_message']];                    // honeypot: pretend success
        [$ts, $sig] = array_pad(explode('.', (string)($post['_t'] ?? ''), 2), 2, ''); $age = time() - (int)$ts;
        if (!hash_equals(hash_hmac('sha256', "$id|$ts", self::key()), $sig) || $age > 7200) return $bad('The form expired. Please reload the page and try again.');
        if ($age < 3) return $bad('Please wait a moment and try again.');
        $db = Db::pdo(); if (!$db) return $bad('Your message could not be saved. Please try again later.');
        $now = gmdate('Y-m-d H:i:s');
        if ((int)Db::all($db, 'SELECT COUNT(*) n FROM form_submissions WHERE ip = ? AND created_at > ?', [$c['ip'], gmdate('Y-m-d H:i:s', time() - 3600)])[0]['n'] >= (int)env('FORM_RATE_LIMIT', 5)) return $bad('Too many messages. Please try again later.');
        $data = []; $err = []; $email = null; $name = '';
        foreach ($f['fields'] as $x) {
            $v = $x['type'] === 'checkbox' ? (isset($post[$x['name']]) ? 'Yes' : 'No') : trim((string)($post[$x['name']] ?? ''));
            $v = $x['type'] === 'textarea' ? preg_replace('/[^\P{C}\n\r\t]/u', '', $v) : preg_replace('/\p{C}/u', '', $v);
            $L = $x['label'];
            if (!empty($x['required']) && ($v === '' || ($x['type'] === 'checkbox' && $v === 'No'))) $err[] = "$L is required.";
            elseif ($v !== '' && $x['type'] === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) $err[] = 'Enter a valid email address.';
            elseif ($v !== '' && $x['type'] === 'select' && !in_array($v, $x['options'], true)) $err[] = "Invalid choice for $L.";
            elseif (mb_strlen($v) > ($x['type'] === 'textarea' ? 5000 : 255)) $err[] = "$L is too long.";
            if ($x['type'] === 'email' && $email === null && $v !== '') $email = $v;
            if ($x['name'] === 'name') $name = $v;
            $data[] = ['name' => $x['name'], 'label' => $L, 'value' => $v];
        }
        if ($err) return $bad(implode(' ', $err));
        $db->prepare('INSERT INTO form_submissions (form_id, email, data, ip, user_agent, created_at) VALUES (?,?,?,?,?,?)')->execute([$id, $email, json_encode($data, JSON_UNESCAPED_UNICODE), $c['ip'], $c['ua'], $now]);
        $sid = (int)$db->lastInsertId();
        $vals = array_column($data, 'value', 'name');
        $subject = preg_replace_callback('/\{\{(\w+)\}\}/', fn($m) => $vals[$m[1]] ?? '', $f['subject']);
        $body = "New submission from the \"{$f['title']}\" form\n\n";
        foreach ($data as $d) $body .= $d['label'] . ":\n" . $d['value'] . "\n\n";
        $body .= "---\nIP: {$c['ip']} · {$c['browser']} / {$c['os']}\nTime: $now UTC\n";
        $mErr = Mailer::send($f['to'], $subject, $body, ['reply_to' => $email, 'reply_name' => $name]);
        $db->prepare('UPDATE form_submissions SET mail_status = ?, mail_error = ? WHERE id = ?')->execute([$mErr ? 'failed' : (Mailer::driver() === 'log' ? 'logged' : 'sent'), $mErr ? substr($mErr, 0, 255) : null, $sid]);
        if ($mErr) error_log("Form mail failed (#$sid): $mErr");
        return ['ok' => true, 'message' => $f['success_message']];   // stored safely even if the email failed
    }

    private static function db(): PDO { return Db::pdo() ?? throw new RuntimeException('Database not configured'); }
    static function messages(): array {
        return array_map(fn($r) => $r + ['fields' => json_decode($r['data'], true) ?: []], Db::all(self::db(), 'SELECT * FROM form_submissions ORDER BY id DESC LIMIT 300'));
    }
    static function markRead(int $id): void { self::db()->prepare('UPDATE form_submissions SET read_at = COALESCE(read_at, ?) WHERE id = ?')->execute([gmdate('Y-m-d H:i:s'), $id]); }
    static function deleteMessage(int $id): void { self::db()->prepare('DELETE FROM form_submissions WHERE id = ?')->execute([$id]); }
}
