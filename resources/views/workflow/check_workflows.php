<?php
$pdo = new PDO('mysql:host=mysql;dbname=laravel', 'sail', 'password');
$stmt = $pdo->query("SELECT id, type, name, CASE WHEN workflow_json IS NULL THEN 'null' WHEN workflow_json LIKE '%class_type%' THEN 'real' ELSE 'skeleton' END as json_status FROM workflows ORDER BY id");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo $r['id'] . ' | ' . str_pad($r['type'], 16) . ' | ' . str_pad($r['json_status'], 8) . ' | ' . $r['name'] . PHP_EOL;
}
