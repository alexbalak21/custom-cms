<?php
return new class {
    function up(PDO $db): void {
        $db->exec("CREATE TABLE search_items (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            keywords VARCHAR(500) NOT NULL DEFAULT '',
            url VARCHAR(255) NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_url (url),
            FULLTEXT KEY ft_search (title, content, keywords)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    function down(PDO $db): void {
        $db->exec("DROP TABLE IF EXISTS search_items");
    }
};
