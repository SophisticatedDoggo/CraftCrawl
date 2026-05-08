<?php
require_once __DIR__ . '/lib/security.php';
craftcrawl_secure_session_start();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['business_id'])) {
    craftcrawl_redirect('index.php');
}
?>
