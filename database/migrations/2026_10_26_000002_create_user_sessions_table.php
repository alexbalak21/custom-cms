<?php
return new class {
    function up(PDO $db): void {
        $db->exec("CREATE TABLE user_sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            ip VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) NOT NULL,
            browser VARCHAR(60) NOT NULL,
            os VARCHAR(60) NOT NULL,
            created_at DATETIME NOT NULL,
            last_activity DATETIME NOT NULL,
            revoked_at DATETIME NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    function down(PDO $db): void {
        $db->exec("DROP TABLE IF EXISTS user_sessions");
    }
};
