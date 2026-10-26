<?php
return new class {
    function up(PDO $db): void {
        $db->exec("CREATE TABLE products (
            ID INT NOT NULL AUTO_INCREMENT,
            reference VARCHAR(80) NOT NULL,
            title VARCHAR(255) NOT NULL,
            size VARCHAR(255) NOT NULL,
            price INT NOT NULL,
            description TEXT,
            updated_on DATE DEFAULT (CURDATE()),
            PRIMARY KEY (ID)
        ) ENGINE=InnoDB");
    }
    function down(PDO $db): void {
        $db->exec("DROP TABLE IF EXISTS products");
    }
};
