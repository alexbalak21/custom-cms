<?php
// Whitelisted remote procedures callable by blocks (and /rpc/<name> when 'public' => true).
// 'run' receives (PDO, params). 'sample' is returned when the DB is not configured or the query fails.
return [
    'products.list' => [
        'public' => true,
        'run' => function (PDO $db, array $p) {
            $n = max(1, min(100, (int)($p['limit'] ?? 12)));
            $rows = Db::all($db, "SELECT ID AS id, reference, title, size, price, description FROM products ORDER BY title LIMIT $n");
            $urls = Pages::byVar('reference');          // page whose variable "reference" matches the product
            foreach ($rows as &$r) $r['url'] = $urls[$r['reference']] ?? '#';
            return $rows;
        },
        'sample' => [
            ['reference' => 'K0507-01', 'title' => 'PRECICE® ADK Assay Kit', 'size' => '1 plate, 96 assays', 'price' => 530, 'url' => '/convenient-assay-kits/adk-assay-kit'],
            ['reference' => 'K0000-02', 'title' => 'Sample Kinase Kit', 'size' => '1 plate, 96 assays', 'price' => 480, 'url' => '#'],
            ['reference' => 'K0000-03', 'title' => 'Sample Metabolism Kit', 'size' => '2 plates', 'price' => 410, 'url' => '#'],
        ],
    ],
    'products.get' => [
        'public' => true,
        'run' => fn(PDO $db, array $p) => (Db::all($db, 'SELECT ID AS id, reference, title, size, price, description FROM products WHERE reference = :r LIMIT 1', [':r' => (string)($p['reference'] ?? '')])[0] ?? null),
        'sample' => ['reference' => 'K0507-01', 'title' => 'PRECICE® ADK Assay Kit', 'size' => '1 plate, 96 assays', 'price' => 530, 'description' => 'Non-radioactive, HTS-ready assay kit for human adenosine kinase (ADK). Z\'-factor 0.68.'],
    ],
    'search.query' => [
        'public' => true,
        'run' => function (PDO $db, array $p) {
            $q = trim((string)($p['q'] ?? '')); $n = max(1, min(50, (int)($p['limit'] ?? 20)));
            if (mb_strlen($q) < 2) return [];
            $like = '%' . addcslashes($q, '%_\\') . '%';
            return Db::all($db, "SELECT title, url, SUBSTRING(content, 1, 220) AS excerpt,
                    MATCH(title, content, keywords) AGAINST(:q1 IN NATURAL LANGUAGE MODE) AS score
                FROM search_items
                WHERE MATCH(title, content, keywords) AGAINST(:q2 IN NATURAL LANGUAGE MODE) OR title LIKE :l1 OR keywords LIKE :l2
                ORDER BY score DESC, title LIMIT $n", [':q1' => $q, ':q2' => $q, ':l1' => $like, ':l2' => $like]);
        },
        'sample' => [],
    ],
];
