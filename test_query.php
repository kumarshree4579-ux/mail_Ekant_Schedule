<?php
require 'app/Core/Database.php';

$db = new PDO('sqlite:' . __DIR__ . '/storage/database.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $db->query("
    SELECT custom_id, COUNT(*) as c 
    FROM email_sends 
    WHERE custom_id IS NOT NULL AND custom_id != ''
    GROUP BY custom_id 
    HAVING c > 1 
    LIMIT 5
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

print_r($rows);

foreach ($rows as $row) {
    echo "Custom ID: " . $row['custom_id'] . "\n";
    $s = $db->prepare("SELECT id, subject, sent_at, tracking_id FROM email_sends WHERE custom_id = ? ORDER BY sent_at ASC");
    $s->execute([$row['custom_id']]);
    $emails = $s->fetchAll(PDO::FETCH_ASSOC);
    print_r($emails);
}
