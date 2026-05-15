<?php
require_once __DIR__ . '/config.php';

$apk_path = __DIR__ . '/downloads/craftcrawl-prod.apk';
$apk_url = 'downloads/craftcrawl-prod.apk';
$testflight_url = 'https://testflight.apple.com/join/5KTPZ86n';
$apk_available = is_file($apk_path);
$apk_updated = $apk_available ? date('F j, Y g:i A T', filemtime($apk_path)) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Craft Crawl Download</title>
    <script src="js/theme_init.js"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <main class="settings-page legal-page download-page">
        <header class="settings-header legal-header download-header">
            <div>
                <img class="site-logo" src="images/craft-crawl-logo-trail.png" alt="CraftCrawl logo">
                <div>
                    <h1>Craft Crawl Download</h1>
                    <p>Install CraftCrawl on iOS or Android.</p>
                </div>
            </div>
            <a href="index.php" data-back-link>Back</a>
        </header>

        <section class="download-options" aria-label="Download options">
            <article class="settings-panel legal-panel download-option-card">
                <div>
                    <h2>iOS TestFlight</h2>
                    <p>Join the CraftCrawl TestFlight beta from your iPhone or iPad.</p>
                </div>
                <a class="button-link" href="<?php echo escape_output($testflight_url); ?>" target="_blank" rel="noopener">Open TestFlight</a>
            </article>

            <article class="settings-panel legal-panel download-option-card">
                <div>
                    <h2>Android APK</h2>
                    <?php if ($apk_available): ?>
                        <p>Download the current Android APK and approve installation when Android prompts you.</p>
                    <?php else: ?>
                        <p>The Android APK has not been published yet. Check back after the next release build.</p>
                    <?php endif; ?>
                </div>
                <?php if ($apk_available): ?>
                    <a class="button-link" href="<?php echo escape_output($apk_url); ?>">Download APK</a>
                    <p class="download-updated">Last updated: <?php echo escape_output($apk_updated); ?></p>
                <?php else: ?>
                    <span class="download-status-pill">Coming Soon</span>
                <?php endif; ?>
            </article>
        </section>

        <section class="settings-panel legal-panel download-note-panel">
            <h2>Install Notes</h2>
            <p>iOS testing uses Apple TestFlight. Android may ask you to allow installs from your browser. Only install the APK from this official CraftCrawl page.</p>
        </section>
    </main>
</body>
</html>
