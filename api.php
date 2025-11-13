<?php
// api.php - JSON endpoints for the dashboard
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/lib/db.php';

$action = $_GET['action'] ?? 'summary';

try {
    switch ($action) {
        case 'summary':
            echo json_encode(summary());
            break;
        case 'timeseries':
            echo json_encode(timeseries());
            break;
        case 'top-hosts-root':
            echo json_encode(top_hosts_root());
            break;
        case 'subdomains':
            echo json_encode(subdomains());
            break;
        case 'recent':
            echo json_encode(recent());
            break;
        case 'distinct':
            echo json_encode(distinct_values());
            break;
        case 'player-stats':
            echo json_encode(player_stats());
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function summary() {
    $p = filter_params();
    list($where, $bind) = where_clauses($p);
    $pdo = db();

    $total = $pdo->prepare("SELECT COUNT(*) c FROM joins $where");
    $total->execute($bind);
    $allJoins = (int)$total->fetchColumn();

    $uniq = $pdo->prepare("SELECT COUNT(DISTINCT uuid) c FROM joins $where");
    $uniq->execute($bind);
    $uniqueJoins = (int)$uniq->fetchColumn();

    $distinctHosts = $pdo->prepare("SELECT COUNT(DISTINCT hostname) c FROM joins $where");
    $distinctHosts->execute($bind);
    $hostsCount = (int)$distinctHosts->fetchColumn();

    return [
        'filters' => [
            'host'=>$p[0],'uuid'=>$p[1],'player'=>$p[2],'start'=>$p[3],'end'=>$p[4]
        ],
        'totals' => [
            'all' => $allJoins,
            'unique' => $uniqueJoins,
            'distinct_hosts' => $hostsCount
        ]
    ];
}

function pick_interval($start, $end) {
    $range = $end - $start;
    if ($range <= 36*3600) return 'minute';
    if ($range <= 45*24*3600) return 'hour';
    return 'day';
}

function timeseries() {
    $p = filter_params();
    list($where, $bind) = where_clauses($p);
    $pdo = db();

    $interval = $_GET['interval'] ?? pick_interval($p[3], $p[4]);
    if (!in_array($interval, ['minute', 'hour', 'day'], true)) {
        $interval = 'day';
    }

    $bucket = bucket_expr($interval, 'ts');
    $sql = "SELECT $bucket AS d, COUNT(*) c
            FROM joins
            $where
            GROUP BY d
            ORDER BY d ASC";
    $st = $pdo->prepare($sql);
    $st->execute($bind);
    $rows = $st->fetchAll();

    $index = [];
    foreach ($rows as $i => $row) {
        $rows[$i]['c'] = (int)$row['c'];
        $rows[$i]['u'] = 0;
        $index[$row['d']] = $i;
    }

    $uniqBucket = bucket_expr($interval, 'first_ts');
    $uniqSql = "SELECT $uniqBucket AS d, COUNT(*) u
                FROM (
                    SELECT MIN(ts) AS first_ts
                    FROM joins
                    $where
                    GROUP BY uuid
                ) firsts
                GROUP BY d
                ORDER BY d ASC";
    $uniqStmt = $pdo->prepare($uniqSql);
    $uniqStmt->execute($bind);
    while ($row = $uniqStmt->fetch()) {
        $d = $row['d'];
        $u = (int)$row['u'];
        if (isset($index[$d])) {
            $rows[$index[$d]]['u'] = $u;
        } else {
            $rows[] = ['d' => $d, 'c' => 0, 'u' => $u];
        }
    }

    if (count($rows) > 1) {
        usort($rows, function ($a, $b) {
            return strcmp($a['d'], $b['d']);
        });
    }

    return ['interval' => $interval, 'rows' => $rows];
}

function bucket_expr($interval, $column = 'ts') {
    switch ($interval) {
        case 'minute':
            return "DATE_FORMAT(FROM_UNIXTIME($column),'%Y-%m-%d %H:%i')";
        case 'hour':
            return "DATE_FORMAT(FROM_UNIXTIME($column),'%Y-%m-%d %H:00')";
        default:
            return "DATE(FROM_UNIXTIME($column))";
    }
}

function top_hosts_root() {
    $p = filter_params();
    list($where, $bind) = where_clauses($p);
    $pdo = db();

    $rootExpr = sql_root_expr('root');
    $sql = "SELECT $rootExpr, COUNT(*) c, COUNT(DISTINCT uuid) u
            FROM joins
            $where
            GROUP BY root
            ORDER BY c DESC, root ASC
            LIMIT 50";
    $st = $pdo->prepare($sql);
    $st->execute($bind);
    return ['rows'=>$st->fetchAll()];
}

function subdomains() {
    $root = $_GET['root'] ?? '';
    if ($root === '') return ['rows'=>[]];
    $p = filter_params();
    list($where, $bind) = where_clauses($p);
    $pdo = db();

    $where .= " AND hostname LIKE :like";
    $bind[':like'] = '%.' . $root;

    $sql = "SELECT hostname, COUNT(*) c, COUNT(DISTINCT uuid) u
            FROM joins
            $where
            GROUP BY hostname
            ORDER BY c DESC, hostname ASC
            LIMIT 200";
    $st = $pdo->prepare($sql);
    $st->execute($bind);
    return ['root'=>$root, 'rows'=>$st->fetchAll()];
}

function recent() {
    $p = filter_params();
    list($where, $bind) = where_clauses($p);
    $pdo = db();

    $page = max(1, (int)($_GET['page'] ?? 1));
    $per  = min(100, max(10, (int)($_GET['per'] ?? 25)));
    $off  = ($page - 1) * $per;

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM joins $where");
    $cnt->execute($bind);
    $total = (int)$cnt->fetchColumn();

    $sql = "SELECT id, hostname, uuid, player_name, ts
            FROM joins
            $where
            ORDER BY ts DESC, id DESC
            LIMIT :per OFFSET :off";
    $st = $pdo->prepare($sql);
    foreach ($bind as $k=>$v) $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $st->bindValue(':per', $per, PDO::PARAM_INT);
    $st->bindValue(':off', $off, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll();

    return ['rows' => $rows, 'page' => $page, 'per' => $per, 'total' => $total];
}

function distinct_values() {
    $pdo = db();
    $hosts = $pdo->query("SELECT hostname, COUNT(*) c FROM joins GROUP BY hostname ORDER BY c DESC, hostname ASC LIMIT 200")->fetchAll();
    $players = $pdo->query("SELECT player_name, COUNT(*) c FROM joins GROUP BY player_name ORDER BY c DESC, player_name ASC LIMIT 200")->fetchAll();
    return ['hosts' => $hosts, 'players' => $players];
}

function player_stats() {
    $player = $_GET['player'] ?? '';
    if ($player === '') return ['rows'=>[]];
    $p = filter_params();
    // force player filter
    $p[2] = $player;
    list($where, $bind) = where_clauses($p);
    $pdo = db();

    $rootExpr = sql_root_expr('root');
    $sql = "SELECT $rootExpr, hostname, COUNT(*) c, COUNT(DISTINCT uuid) u
            FROM joins
            $where
            GROUP BY root, hostname
            ORDER BY root ASC, c DESC, hostname ASC";
    $st = $pdo->prepare($sql);
    $st->execute($bind);
    return ['player'=>$player, 'rows'=>$st->fetchAll()];
}
