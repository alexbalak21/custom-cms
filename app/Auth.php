<?php
class Auth {
    private static $cache = false;
    static function name(): string { return env('SESSION_NAME', 'custom_cms'); }
    static function https(): bool { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'; }
    static function start(): void {
        if (session_status() !== PHP_SESSION_NONE) return;
        session_name(self::name()); ini_set('session.use_strict_mode', '1');
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => self::https(), 'path' => '/']);
        session_start();
    }
    static function csrf(): string { self::start(); return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }

    /* Device fingerprint: IP, OS, browser. (X-Forwarded-For is deliberately NOT trusted.) */
    static function client(): array {
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $b = match (true) { str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera', str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS') => 'Chrome', str_contains($ua, 'Safari/') => 'Safari', default => 'Other' };
        $o = match (true) { str_contains($ua, 'Windows') => 'Windows', str_contains($ua, 'Android') => 'Android', (bool)preg_match('/iPhone|iPad|iOS/', $ua) => 'iOS',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS', str_contains($ua, 'Linux') => 'Linux', default => 'Other' };
        return ['ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'ua' => $ua, 'browser' => $b, 'os' => $o];
    }
    private static function net(string $ip): string { $b = @inet_pton($ip); return $b === false ? $ip : bin2hex(substr($b, 0, strlen($b) === 4 ? 3 : 8)); }
    private static function ipOk(string $a, string $b): bool {
        return match (env('SESSION_IP_CHECK', 'subnet')) { 'off' => true, 'strict' => $a === $b, default => self::net($a) === self::net($b) };
    }
    private static function ts(string $d): int { return strtotime($d . ' UTC'); }

    /** Current user or null. A stolen cookie fails here: IP/OS/browser must match the login, and the session must not be revoked/expired. */
    static function user(): ?array {
        if (self::$cache !== false) return self::$cache;
        if (!isset($_COOKIE[self::name()]) || !($db = Db::pdo())) return self::$cache = null;
        self::start(); $tok = $_SESSION['tok'] ?? null; if (!$tok) return self::$cache = null;
        try {
            $r = Db::all($db, 'SELECT s.id sid, s.ip, s.browser, s.os, s.created_at, s.last_activity, u.id, u.name, u.username, u.email, u.role
                               FROM user_sessions s JOIN users u ON u.id = s.user_id WHERE s.token_hash = ? AND s.revoked_at IS NULL', [hash('sha256', $tok)])[0] ?? null;
        } catch (Throwable $e) { return self::$cache = null; }
        if (!$r) { self::destroy(); return self::$cache = null; }
        $c = self::client(); $now = time();
        $ok = $r['browser'] === $c['browser'] && $r['os'] === $c['os'] && self::ipOk($r['ip'], $c['ip'])
            && $now - self::ts($r['last_activity']) < (int)env('SESSION_IDLE_MINUTES', 120) * 60 && $now - self::ts($r['created_at']) < (int)env('SESSION_LIFETIME_HOURS', 12) * 3600;
        if (!$ok) { error_log("Session rejected for {$r['username']} from {$c['ip']} ({$c['browser']}/{$c['os']})"); $db->prepare('UPDATE user_sessions SET revoked_at = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s'), $r['sid']]); self::destroy(); return self::$cache = null; }
        if ($now - self::ts($r['last_activity']) > 60) $db->prepare('UPDATE user_sessions SET last_activity = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s'), $r['sid']]);
        $_SESSION['sid'] = $r['sid'];
        return self::$cache = ['id' => $r['id'], 'name' => $r['name'], 'username' => $r['username'], 'email' => $r['email'], 'role' => $r['role']];
    }
    private static function destroy(): void { self::start(); $_SESSION = []; if (session_status() === PHP_SESSION_ACTIVE) session_destroy(); }

    /** Returns an error message, or null on success. */
    static function login(string $username, string $pass): ?string {
        $db = Db::pdo(); if (!$db) throw new RuntimeException('Database not configured. Check .env, then run: php cms migrate');
        $c = self::client(); $now = gmdate('Y-m-d H:i:s');
        if ((int)Db::all($db, 'SELECT COUNT(*) n FROM login_attempts WHERE ip = ? AND attempted_at > ?', [$c['ip'], gmdate('Y-m-d H:i:s', time() - 900)])[0]['n'] >= 5) return 'Too many attempts. Try again in 15 minutes.';
        $u = Db::all($db, 'SELECT * FROM users WHERE username = ? LIMIT 1', [$username])[0] ?? null;
        if (!$u || !password_verify($pass, $u['password'])) { $db->prepare('INSERT INTO login_attempts (ip, username, attempted_at) VALUES (?,?,?)')->execute([$c['ip'], substr($username, 0, 100), $now]); return 'Invalid credentials'; }
        self::start(); session_regenerate_id(true);
        $tok = bin2hex(random_bytes(32)); $_SESSION['tok'] = $tok; $_SESSION['csrf'] = bin2hex(random_bytes(16));
        $db->prepare('INSERT INTO user_sessions (user_id, token_hash, ip, user_agent, browser, os, created_at, last_activity) VALUES (?,?,?,?,?,?,?,?)')->execute([$u['id'], hash('sha256', $tok), $c['ip'], $c['ua'], $c['browser'], $c['os'], $now, $now]);
        $db->prepare('UPDATE users SET last_login_at = ?, last_login_ip = ?, last_login_os = ?, last_login_browser = ? WHERE id = ?')->execute([$now, $c['ip'], $c['os'], $c['browser'], $u['id']]);
        $db->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$c['ip']]);
        self::$cache = false; return null;
    }
    static function logout(): void {
        if ($db = Db::pdo()) if (isset($_SESSION['tok'])) $db->prepare('UPDATE user_sessions SET revoked_at = ? WHERE token_hash = ?')->execute([gmdate('Y-m-d H:i:s'), hash('sha256', $_SESSION['tok'])]);
        self::destroy(); self::$cache = null;
    }
    static function createUser(string $name, string $username, string $email, string $password, string $role = 'admin'): int {
        $db = Db::pdo(); if (!$db) throw new RuntimeException('Database not configured');
        if (!preg_match('/^[a-z0-9._-]{3,40}$/i', $username)) throw new RuntimeException('Username: 3-40 chars (letters, digits . _ -)');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid email');
        if (strlen($password) < 10) throw new RuntimeException('Password must be at least 10 characters');
        if (Db::all($db, 'SELECT id FROM users WHERE username = ?', [$username])) throw new RuntimeException('Username already taken');
        $now = gmdate('Y-m-d H:i:s');
        $db->prepare('INSERT INTO users (name, username, email, password, role, created_at, updated_at) VALUES (?,?,?,?,?,?,?)')->execute([$name ?: $username, $username, $email ?: null, password_hash($password, PASSWORD_DEFAULT), $role, $now, $now]);
        return (int)$db->lastInsertId();
    }
    static function setPassword(int $userId, string $new, bool $keepCurrent = false): void {
        if (strlen($new) < 10) throw new RuntimeException('Password must be at least 10 characters');
        $db = Db::pdo(); $db->prepare('UPDATE users SET password = ?, updated_at = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), gmdate('Y-m-d H:i:s'), $userId]);
        $q = 'UPDATE user_sessions SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL' . ($keepCurrent ? ' AND id <> ?' : '');
        $db->prepare($q)->execute($keepCurrent ? [gmdate('Y-m-d H:i:s'), $userId, $_SESSION['sid'] ?? 0] : [gmdate('Y-m-d H:i:s'), $userId]);
    }
}
