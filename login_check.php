<?php
session_start();

if (!isset($_SESSION["user_id"]) && !isset($_SESSION["business_id"])) {
    if(isset($_SESSION["user_id"])) {
        header("Location: user/portal.php");    
    }
    if(isset($_SESSION["business_id"])) {
        header("Location: business/business_portal.php");    
    }
    header("Location: index.php");
    exit();
}
?>
