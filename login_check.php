<?php
session_start();

if (!isset($_SESSION["user_id"]) && !isset($_SESSION["business_id"])) {
    header("Location: portal.php");
    exit();
}
?>
