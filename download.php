<?php
require_once __DIR__ . '/config.php';

$apk_path = __DIR__ . '/downloads/craftcrawl-prod.apk';
$apk_url = 'downloads/craftcrawl-prod.apk';
$apk_available = is_file($apk_path);
$apk_updated = $apk_available ? date('F j, Y g:i A T', filemtime($apk_path)) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Android Download</title>
    <script src="js/theme_init.js"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <main class="settings-page legal-page">
        <header class="settings-header legal-header">
            <div>
                <img class="site-logo" src="images/Logo.webp" alt="CraftCrawl logo">
                <div>
                    <h1>CraftCrawl Android</h1>
                    <p>Install the current CraftCrawl Android release.</p>
                </div>
            </div>
            <a href="index.php">Back to CraftCrawl</a>
        </header>

        <section class="settings-panel legal-panel">
            <?php if ($apk_available): ?>
                <h2>Download</h2>
                <p>Open this page on your Android device, download the APK, and approve installation when Android prompts you.</p>
                <p><a class="btn primary-btn" href="<?php echo escape_output($apk_url); ?>">Download Android APK</a></p>
                <p>Last updated: <?php echo escape_output($apk_updated); ?></p>
            <?php else: ?>
                <h2>Coming Soon</h2>
                <p>The Android APK has not been published yet. Check back after the next release build.</p>
            <?php endif; ?>

            <h2>Install Note</h2>
            <p>Android may ask you to allow installs from your browser. Only install the APK from this official CraftCrawl page.</p>
        </section>
    </main>
</body>
</html>
