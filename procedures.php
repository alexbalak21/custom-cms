<?php
// Whitelisted remote procedures callable by blocks (and /rpc/<name> when 'public' => true).
// 'run' receives (PDO, params). 'sample' is returned when no database is configured / query fails.
return [
    'products.list' => [
        'public' => true,
        'run' => function (PDO $db, array $p) {
            $n = max(1, min(100, (int)($p['limit'] ?? 12))); $c = (string)($p['category'] ?? '');
            return Db::all($db, "SELECT sku, name, price, image, summary, url FROM products WHERE (:c1 = '' OR category = :c2) ORDER BY name LIMIT $n", [':c1' => $c, ':c2' => $c]);
        },
        'sample' => [
            ['sku' => 'K0507-01', 'name' => 'PRECICE® ADK Assay Kit', 'price' => '530.00', 'summary' => 'Human adenosine kinase activity and inhibitor screening.', 'url' => '/convenient-assay-kits/adk-assay-kit'],
            ['sku' => 'K0000-02', 'name' => 'Sample Kinase Kit', 'price' => '480.00', 'summary' => 'Placeholder product from the sample dataset.', 'url' => '#'],
            ['sku' => 'K0000-03', 'name' => 'Sample Metabolism Kit', 'price' => '410.00', 'summary' => 'Placeholder product from the sample dataset.', 'url' => '#'],
        ],
    ],
    'products.get' => [
        'public' => true,
        'run' => fn(PDO $db, array $p) => (Db::all($db, 'SELECT sku, name, price, image, summary, description FROM products WHERE sku = :s LIMIT 1', [':s' => (string)($p['sku'] ?? '')])[0] ?? null),
        'sample' => ['sku' => 'K0507-01', 'name' => 'PRECICE® ADK Assay Kit', 'price' => '530.00', 'summary' => 'Non-radioactive, HTS-ready assay kit for human adenosine kinase (ADK).', 'description' => 'Measures ADK activity via a coupled IMPDH reaction (NADH, 340 nm). 96-well format, Z\'-factor 0.68.'],
    ],
];
