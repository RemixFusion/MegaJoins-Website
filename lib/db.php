<?php
require_once __DIR__ . '/../config.php';
date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'UTC');

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    return $pdo;
}

function parse_date($str, $default) {
    if (!$str) return $default;
    $ts = strtotime($str);
    return $ts ? $ts : $default;
}

function filter_params() {
    $host = isset($_GET['host']) ? trim($_GET['host']) : '';
    $uuid = isset($_GET['uuid']) ? preg_replace('/[^a-f0-9]/', '', strtolower($_GET['uuid'])) : '';
    $player = isset($_GET['player']) ? trim($_GET['player']) : '';

    $now = time();
    $start = isset($_GET['start']) ? parse_date($_GET['start'], $now - 30*24*3600) : ($now - 30*24*3600);
    $end   = isset($_GET['end']) ? parse_date($_GET['end'], $now) : $now;

    // Inclusive end-of-day if user typed date only
    if (isset($_GET['end']) && strlen($_GET['end']) <= 10) $end = strtotime('tomorrow', $end) - 1;

    return [$host, $uuid, $player, $start, $end];
}

function where_clauses($params) {
    list($host, $uuid, $player, $start, $end) = $params;
    $where = ['ts BETWEEN :start AND :end'];
    $bind = [':start' => $start, ':end' => $end];

    if ($host !== '') {
        $where[] = 'hostname = :host';
        $bind[':host'] = $host;
    }
    if ($uuid !== '') {
        $where[] = 'uuid = :uuid';
        $bind[':uuid'] = $uuid;
    }
    if ($player !== '') {
        $where[] = 'player_name LIKE :player';
        $bind[':player'] = '%' . $player . '%';
    }
    return ['WHERE ' . implode(' AND ', $where), $bind];
}

// Derive root from hostname using last two labels (domain.tld).
function sql_root_expr($alias='root') {
    return "SUBSTRING_INDEX(hostname,'.',-2) AS `$alias`";
}
