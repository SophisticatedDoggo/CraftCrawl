<?php
require_once __DIR__ . '/lib/env.php';

$db_host = getenv('CRAFTCRAWL_DB_HOST') ?: 'localhost';
$db_user = getenv('CRAFTCRAWL_DB_USER') ?: 'craft_crawl';
$db_password = getenv('CRAFTCRAWL_DB_PASSWORD') ?: '';
$db_name = getenv('CRAFTCRAWL_DB_NAME') ?: 'craft_crawl';

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($db_host, $db_user, $db_password, $db_name);

if ($conn->connect_errno) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    exit("Database connection failed.");
}

$conn->set_charset('utf8mb4');
?>
