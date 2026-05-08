<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../lib/env.php';

$db_host = getenv('CRAFTCRAWL_DB_HOST') ?: 'localhost';
$db_user = getenv('CRAFTCRAWL_DB_USER') ?: 'craft_crawl';
$db_password = getenv('CRAFTCRAWL_DB_PASSWORD') ?: '';
$db_name = getenv('CRAFTCRAWL_DB_NAME') ?: 'craft_crawl';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
$conn->set_charset('utf8mb4');

function migration_log($message) {
    echo $message . PHP_EOL;
}

function scalar_query($conn, $sql) {
    $result = $conn->query($sql);
    $row = $result->fetch_row();

    return (int) ($row[0] ?? 0);
}

function duplicate_count($conn, $table, $column) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);

    return scalar_query($conn, "
        SELECT COUNT(*)
        FROM (
            SELECT {$column}
            FROM {$table}
            GROUP BY {$column}
            HAVING COUNT(*) > 1
        ) duplicates
    ");
}

function index_exists($conn, $table, $index) {
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND index_name = ?
    ");
    $stmt->bind_param('ss', $table, $index);
    $stmt->execute();

    return (int) $stmt->get_result()->fetch_row()[0] > 0;
}

function add_unique_index($conn, $table, $column, $index) {
    if (index_exists($conn, $table, $index)) {
        migration_log("Already present: {$index}");
        return;
    }

    $duplicates = duplicate_count($conn, $table, $column);

    if ($duplicates > 0) {
        throw new RuntimeException("Cannot add {$index}: {$table}.{$column} has duplicate values.");
    }

    $conn->query("ALTER TABLE {$table} ADD UNIQUE KEY {$index} ({$column})");
    migration_log("Added: {$index}");
}

add_unique_index($conn, 'users', 'email', 'unique_user_email');
add_unique_index($conn, 'businesses', 'bEmail', 'unique_business_email');

migration_log('Database migration complete.');

?>
