<?php
require_once __DIR__ . '/lib/db.php';

list($host, $uuid, $player, $start, $end) = filter_params();
list($where, $bind) = where_clauses([$host, $uuid, $player, $start, $end]);
$pdo = db();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="megajoins_export.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['id','hostname','uuid','player_name','ts','timestamp_iso']);

$sql = "SELECT id, hostname, uuid, player_name, ts FROM joins $where ORDER BY ts DESC, id DESC LIMIT 50000";
$st = $pdo->prepare($sql);
$st->execute($bind);
while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $row['timestamp_iso'] = gmdate('c', $row['ts']);
    fputcsv($out, $row);
}
fclose($out);
