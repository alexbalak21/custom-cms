<?php
return new class {
    function up(PDO $db): void {
        $db->exec("CREATE TABLE login_attempts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            username VARCHAR(100) NOT NULL,
            attempted_at DATETIME NOT NULL,
            KEY idx_ip_time (ip, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    function down(PDO $db): void {
        $db->exec("DROP TABLE IF EXISTS login_attempts");
    }
};
