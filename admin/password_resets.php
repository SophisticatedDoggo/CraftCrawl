<?php
require_once __DIR__ . '/../lib/admin_auth.php';
craftcrawl_require_admin();

header('Location: accounts.php');
exit();
