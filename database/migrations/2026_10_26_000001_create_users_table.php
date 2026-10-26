<?php
return new class {
    function up(PDO $db): void {
        $db->exec("CREATE TABLE users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            username VARCHAR(40) NOT NULL UNIQUE,
            email VARCHAR(190) NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'admin',
            last_login_at DATETIME NULL,
            last_login_ip VARCHAR(45) NULL,
            last_login_os VARCHAR(60) NULL,
            last_login_browser VARCHAR(60) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    function down(PDO $db): void {
        $db->exec("DROP TABLE IF EXISTS users");
    }
};
