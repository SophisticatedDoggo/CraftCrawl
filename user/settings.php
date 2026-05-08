<?php
require '../login_check.php';
include '../db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../user_login.php');
    exit();
}

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | User Settings</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <main class="settings-page">
        <header class="settings-header">
            <div>
                <h1>Settings</h1>
                <p>Choose how CraftCrawl looks in this browser.</p>
            </div>
            <div class="business-header-actions">
                <a href="portal.php">Back to Map</a>
                <form action="../logout.php" method="POST">
                    <?php echo craftcrawl_csrf_input(); ?>
                    <button type="submit">Logout</button>
                </form>
            </div>
        </header>

        <section class="settings-panel">
            <h2>Display Theme</h2>
            <div class="palette-switcher palette-switcher-settings" aria-label="Design palette">
                <button type="button" data-palette-option="trail-map">Trail</button>
                <button type="button" data-palette-option="trail-dark">Trail Dark</button>
                <button type="button" data-palette-option="ember">Ember</button>
                <button type="button" data-palette-option="ember-dark">Ember Dark</button>
            </div>
            <p class="form-help">This setting is saved in your browser for now.</p>
        </section>
    </main>
    <script src="../js/palette_switcher.js"></script>
</body>
</html>
