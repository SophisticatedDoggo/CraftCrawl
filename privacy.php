<?php
require_once __DIR__ . '/config.php';

$contact_email = craftcrawl_env('CRAFTCRAWL_SUPPORT_EMAIL', 'support@craftcrawl.site');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Privacy Policy</title>
    <script src="js/theme_init.js"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <main class="settings-page legal-page">
        <header class="settings-header legal-header">
            <div>
                <img class="site-logo" src="images/Logo.webp" alt="CraftCrawl logo">
                <div>
                    <h1>Privacy Policy</h1>
                    <p>Last updated May 12, 2026</p>
                </div>
            </div>
            <a href="index.php">Back to CraftCrawl</a>
        </header>

        <section class="settings-panel legal-panel">
            <h2>Overview</h2>
            <p>CraftCrawl helps users discover local breweries, wineries, cideries, distilleries, meaderies, and events. This policy explains what information we collect, how we use it, and the choices available to you.</p>

            <h2>Information We Collect</h2>
            <p>We collect account information such as your name, email address, password hash, account type, verification status, and login/session data. Business accounts may provide business profile details, hours, address, phone number, website, events, and uploaded photos.</p>
            <p>When you use CraftCrawl features, we may store check-ins, XP, badges, reviews, review photos, liked businesses, friend requests, friendships, recommendations, feed posts, comments, replies, reactions, event interest, notification preferences, and related timestamps.</p>

            <h2>Location Data</h2>
            <p>CraftCrawl uses browser location permission to find nearby eligible check-ins and to verify whether you are close enough to a location to earn visit XP. We do not access your location unless you grant permission through your browser or device.</p>

            <h2>Photos and Uploaded Content</h2>
            <p>If you upload photos or post reviews, comments, reactions, or recommendations, that content may be visible to other users, businesses, or administrators depending on the feature and your privacy settings. Photos may be processed and stored through Cloudinary.</p>

            <h2>Notifications and Email</h2>
            <p>We use email for account verification, password reset, and important account messages. We may use OneSignal for browser push notifications, including friend invites, accepted invites, comments, replies, and reactions. You can manage social notification preferences in user settings and can disable browser notifications through your browser.</p>

            <h2>Service Providers</h2>
            <p>CraftCrawl uses third-party providers to operate the service, including hosting, database services, Cloudflare DNS/security, Mailgun email, OneSignal notifications, Cloudinary photos, Mapbox maps, and hCaptcha bot protection. These providers process data as needed to provide their services.</p>

            <h2>Business Analytics</h2>
            <p>Business accounts may see analytics related to check-ins at their business, including first-time visits, daily activity, and related aggregate engagement. These analytics are intended to help businesses understand CraftCrawl activity at their location.</p>

            <h2>Your Choices</h2>
            <p>You can update privacy settings, notification preferences, profile visibility-related settings, and account preferences from your account settings. You may also contact us to request help with account access, correction, or deletion.</p>

            <h2>Security</h2>
            <p>We use reasonable safeguards such as password hashing, CSRF protection, email verification, hCaptcha, secure session practices, and access controls. No online service can guarantee perfect security.</p>

            <h2>Contact</h2>
            <p>Questions about this policy can be sent to <a class="text-link" href="mailto:<?php echo escape_output($contact_email); ?>"><?php echo escape_output($contact_email); ?></a>.</p>
        </section>
    </main>
</body>
</html>
