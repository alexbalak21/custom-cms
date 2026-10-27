<?php
// Dependency-free mailer. MAIL_DRIVER: log (writes storage/mail.log) | smtp | mail (PHP mail())
class Mailer {
    static function driver(): string { return env('MAIL_DRIVER', 'log'); }
    private static function clean(string $s): string { return trim(preg_replace('/[\r\n]+/', ' ', $s)); }
    private static function enc(string $s): string { return '=?UTF-8?B?' . base64_encode($s) . '?='; }
    private static function host(): string { return parse_url(cfg('base_url'), PHP_URL_HOST) ?: 'localhost'; }

    /** @return string|null error message, or null on success */
    static function send($to, string $subject, string $body, array $o = []): ?string {
        $to = array_values(array_filter(array_map('trim', is_array($to) ? $to : explode(',', $to))));
        if (!$to) return 'No recipient';
        $fa = env('MAIL_FROM_ADDRESS', 'no-reply@example.com'); $fn = self::clean((string)env('MAIL_FROM_NAME', cfg('site_name')));
        $subject = self::clean($subject);
        $h = ['Date: ' . date('r'), 'From: ' . ($fn ? self::enc($fn) . ' ' : '') . "<$fa>", 'To: ' . implode(', ', $to)];
        if (!empty($o['reply_to']) && filter_var($o['reply_to'], FILTER_VALIDATE_EMAIL)) $h[] = 'Reply-To: ' . (($rn = self::clean($o['reply_name'] ?? '')) ? self::enc($rn) . ' ' : '') . '<' . $o['reply_to'] . '>';
        $h[] = 'Subject: ' . self::enc($subject); $h[] = 'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . self::host() . '>';
        array_push($h, 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: base64');
        $payload = chunk_split(base64_encode(str_replace(["\r\n", "\r"], "\n", $body)), 76, "\r\n");
        try {
            return match (self::driver()) {
                'smtp' => self::smtp($fa, $to, implode("\r\n", $h) . "\r\n\r\n" . $payload),
                'mail' => @mail(implode(', ', $to), self::enc($subject), $payload, implode("\r\n", array_filter($h, fn($l) => !str_starts_with($l, 'To:') && !str_starts_with($l, 'Subject:')))) ? null : 'mail() failed (is sendmail/SMTP configured in php.ini?)',
                default => self::log($to, $subject, $body),
            };
        } catch (Throwable $e) { return $e->getMessage(); }
    }
    private static function log(array $to, string $subject, string $body): ?string {
        $d = ROOT . '/storage'; if (!is_dir($d)) mkdir($d, 0775, true);
        file_put_contents("$d/mail.log", '---- ' . date('c') . "\nTo: " . implode(', ', $to) . "\nSubject: $subject\n\n$body\n\n", FILE_APPEND);
        return null;
    }
    private static function smtp(string $from, array $to, string $raw): ?string {
        $host = env('MAIL_HOST'); $port = (int)env('MAIL_PORT', 587); $enc = env('MAIL_ENCRYPTION', 'tls');
        if (!$host) return 'MAIL_HOST is not set';
        $fp = @stream_socket_client(($enc === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$port", $en, $es, 15);
        if (!$fp) return "SMTP connect failed: $es";
        stream_set_timeout($fp, 15);
        $cmd = function (string $c, string $ok) use ($fp) {
            if ($c !== '') fwrite($fp, $c . "\r\n");
            $r = ''; while (($l = fgets($fp, 515)) !== false) { $r .= $l; if (isset($l[3]) && $l[3] === ' ') break; }
            if (!str_starts_with($r, $ok)) throw new RuntimeException('SMTP: ' . trim($r));
        };
        try {
            $cmd('', '220'); $cmd('EHLO ' . self::host(), '250');
            if ($enc === 'tls') {
                $cmd('STARTTLS', '220');
                $m = STREAM_CRYPTO_METHOD_TLS_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT : 0) | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0);
                if (!stream_socket_enable_crypto($fp, true, $m)) throw new RuntimeException('SMTP: TLS negotiation failed');
                $cmd('EHLO ' . self::host(), '250');
            }
            if (env('MAIL_USERNAME')) { $cmd('AUTH LOGIN', '334'); $cmd(base64_encode(env('MAIL_USERNAME')), '334'); $cmd(base64_encode((string)env('MAIL_PASSWORD', '')), '235'); }
            $cmd("MAIL FROM:<$from>", '250');
            foreach ($to as $t) $cmd("RCPT TO:<$t>", '25');
            $cmd('DATA', '354'); fwrite($fp, $raw . "\r\n.\r\n"); $cmd('', '250');
            fwrite($fp, "QUIT\r\n"); fclose($fp); return null;
        } catch (Throwable $e) { @fclose($fp); return $e->getMessage(); }
    }
}
