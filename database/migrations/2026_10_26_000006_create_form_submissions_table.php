<?php
return new class {
    function up(PDO $db): void {
        $db->exec("CREATE TABLE form_submissions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            form_id VARCHAR(60) NOT NULL,
            email VARCHAR(190) NULL,
            data MEDIUMTEXT NOT NULL,
            ip VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) NOT NULL,
            mail_status VARCHAR(20) NOT NULL DEFAULT 'pending',
            mail_error VARCHAR(255) NULL,
            read_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            KEY idx_form (form_id, created_at),
            KEY idx_ip (ip, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    function down(PDO $db): void {
        $db->exec("DROP TABLE IF EXISTS form_submissions");
    }
};
