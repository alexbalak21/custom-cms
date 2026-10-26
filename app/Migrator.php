<?php
class Migrator {
    function __construct(private PDO $db) {
        $db->exec('CREATE TABLE IF NOT EXISTS migrations (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255) NOT NULL, batch INT NOT NULL) ENGINE=InnoDB');
    }
    function files(): array { $o = []; foreach (glob(ROOT . '/database/migrations/*.php') as $f) $o[basename($f, '.php')] = $f; ksort($o); return $o; }
    function ran(): array { return array_column(Db::all($this->db, 'SELECT migration, batch FROM migrations'), 'batch', 'migration'); }
    function status(): array { $r = $this->ran(); $o = []; foreach ($this->files() as $n => $f) $o[$n] = $r[$n] ?? null; return $o; }
    function up(): array {
        $ran = $this->ran(); $batch = ($ran ? max($ran) : 0) + 1; $done = [];
        foreach ($this->files() as $n => $f) if (!isset($ran[$n])) {
            (require $f)->up($this->db);
            $this->db->prepare('INSERT INTO migrations (migration, batch) VALUES (?,?)')->execute([$n, $batch]); $done[] = $n;
        }
        return $done;
    }
    function rollback(): array {
        $ran = $this->ran(); if (!$ran) return []; $last = max($ran); $files = $this->files(); $done = [];
        foreach (array_reverse(array_keys(array_filter($ran, fn($b) => $b == $last))) as $n) if (isset($files[$n])) {
            (require $files[$n])->down($this->db);
            $this->db->prepare('DELETE FROM migrations WHERE migration = ?')->execute([$n]); $done[] = $n;
        }
        return $done;
    }
}
