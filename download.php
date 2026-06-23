<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/appearance.php';

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
    <title>CraftCrawl | Download</title>
    <script src="js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <?php require_once __DIR__ . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body class="portal-body">
    <header class="download-header">
        <div>
            <img class="site-logo" src="<?php echo craftcrawl_theme_logo_src('images/'); ?>" alt="CraftCrawl logo">
            <div>
                <h1>Download</h1>
                <p>Get CraftCrawl on your iPhone, iPad, or Android device.</p>
            </div>
        </div>
        <a href="index.php" data-back-link>Back</a>
    </header>

    <main class="download-main">
        <section class="download-platform-card download-platform-primary" aria-label="iOS download">
            <div class="download-platform-heading">
                <h2>iOS</h2>
                <span class="download-platform-label">iPhone &amp; iPad</span>
            </div>
            <p>CraftCrawl for iOS is available through Apple TestFlight, Apple's official beta testing platform. TestFlight is free and lets you install CraftCrawl directly on your device.</p>

            <div class="download-steps-group">
                <h3>How to Install</h3>
                <ol>
                    <li>Tap the button below from your iPhone or iPad.</li>
                    <li>If you don't have TestFlight yet, you'll be taken to the App Store to download it (it's free).</li>
                    <li>Once TestFlight is installed, tap the button below again to join the CraftCrawl beta.</li>
                    <li>Tap "Accept" and then "Install" inside TestFlight.</li>
                </ol>
            </div>

            <div class="download-steps-group">
                <h3>Requirements</h3>
                <ul>
                    <li>iPhone or iPad running iOS 16 or later</li>
                    <li>Apple TestFlight app (free from the App Store)</li>
                </ul>
            </div>

            <a class="button-link" href="<?php echo escape_output($testflight_url); ?>" target="_blank" rel="noopener">Join TestFlight Beta</a>
        </section>

        <section class="download-platform-card" aria-label="Android download">
            <div class="download-platform-heading">
                <h2>Android</h2>
                <span class="download-platform-label"><?php echo $apk_available ? 'Direct APK Download' : 'Coming Soon'; ?></span>
            </div>

            <?php if ($apk_available): ?>
                <p>CraftCrawl for Android is available as a direct APK download. Since it's not on the Play Store yet, you'll install it manually — it only takes a minute.</p>

                <div class="download-steps-group">
                    <h3>How to Install</h3>
                    <ol>
                        <li>Tap the download button below from your Android device.</li>
                        <li>When the download finishes, tap the notification or find the file in your Downloads folder.</li>
                        <li>Android will ask you to allow installs from your browser — tap "Settings" and enable it, then go back.</li>
                        <li>Tap "Install" to finish. CraftCrawl will appear in your app drawer.</li>
                    </ol>
                </div>

                <div class="download-steps-group">
                    <h3>Requirements</h3>
                    <ul>
                        <li>Android 8.0 (Oreo) or later</li>
                        <li>Permission to install from unknown sources (Android will prompt you)</li>
                    </ul>
                </div>

                <a class="button-link" href="<?php echo escape_output($apk_url); ?>">Download APK</a>
                <p class="download-meta">Last updated: <?php echo escape_output($apk_updated); ?></p>
            <?php else: ?>
                <p>The Android APK is not available yet. Check back after the next release build.</p>
            <?php endif; ?>
        </section>

        <div class="download-info-grid">
            <section class="download-info-panel">
                <h3>Staying Up to Date</h3>
                <p>On iOS, TestFlight will notify you when updates are available — just open TestFlight and tap "Update." On Android, come back to this page to download the latest APK.</p>
            </section>

            <section class="download-info-panel">
                <h3>Troubleshooting</h3>
                <p><strong>TestFlight says the beta is full.</strong> Space is limited during early testing. Check back soon — we expand access regularly.</p>
                <p><strong>Android blocks the install.</strong> Your browser needs permission to install apps. When prompted, tap "Settings," enable the toggle, then go back and try again.</p>
                <p><strong>"App not installed" on Android.</strong> Uninstall any older version of CraftCrawl first, then download and install again.</p>
            </section>

            <section class="download-info-panel">
                <h3>Safety</h3>
                <p>Only download CraftCrawl from this page or from Apple TestFlight. Do not install APK files shared through other websites, messaging apps, or social media.</p>
            </section>
        </div>
    </main>
</body>
</html>
