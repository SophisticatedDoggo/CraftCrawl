<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/appearance.php';

$contact_email = craftcrawl_env('CRAFTCRAWL_SUPPORT_EMAIL', 'support@craftcrawl.site');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Privacy Policy</title>
    <script src="js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <?php require_once __DIR__ . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body>
    <main class="settings-page legal-page">
        <header class="settings-header legal-header">
            <div>
                <img class="site-logo" src="<?php echo craftcrawl_theme_logo_src('images/'); ?>" alt="CraftCrawl logo">
                <div>
                    <h1>Privacy Policy</h1>
                    <p>Last updated June 23, 2026</p>
                </div>
            </div>
            <a href="index.php" data-back-link>Back</a>
        </header>

        <section class="settings-panel legal-panel">
            <h2>Overview</h2>
            <p>CraftCrawl helps users discover local breweries, wineries, cideries, distilleries, meaderies, and events. This policy explains what information we collect, how we use it, and the choices available to you.</p>

            <h2>Age Requirements</h2>
            <p>CraftCrawl is intended for users who are 21 years of age or older. By using CraftCrawl, you confirm that you meet this age requirement. CraftCrawl is not directed to children under 13 and we do not knowingly collect personal information from children under 13. If we learn that we have collected data from a child under 13, we will delete it promptly.</p>

            <h2>Information We Collect</h2>
            <p>We collect account information such as your name, email address, password hash, account type, verification status, and login/session data. Business accounts may provide business profile details, hours, address, phone number, website, events, and uploaded photos.</p>
            <p>When you use CraftCrawl features, we may store check-ins, XP, badges, reviews, review photos, liked businesses, friend requests, friendships, recommendations, feed posts, comments, replies, reactions, event interest, notification preferences, and related timestamps.</p>

            <h2>Location Data</h2>
            <p>CraftCrawl uses browser location permission to find nearby eligible check-ins and to verify whether you are close enough to a location to earn visit XP. We do not access your location unless you grant permission through your browser or device.</p>

            <h2>Photos and Uploaded Content</h2>
            <p>If you upload photos or post reviews, comments, reactions, or recommendations, that content may be visible to other users, businesses, or administrators depending on the feature and your privacy settings. Photos may be processed and stored through Cloudinary.</p>

            <h2>Content Reporting</h2>
            <p>CraftCrawl allows users to report content they believe is inappropriate, misleading, or otherwise violates community standards. When you submit a report, we collect the content reference, report type, optional details you provide, and your account identity. Reports are reviewed by administrators and used for moderation purposes.</p>

            <h2>Notifications and Email</h2>
            <p>We use email for account verification, password reset, and important account messages. We may use OneSignal for browser push notifications, including friend invites, accepted invites, comments, replies, and reactions. You can manage social notification preferences in user settings and can disable browser notifications through your browser.</p>

            <h2>Service Providers</h2>
            <p>CraftCrawl uses third-party providers to operate the service, including hosting, database services, Cloudflare DNS/security, Mailgun email, OneSignal notifications, Cloudinary photos, Mapbox maps, Google Analytics usage measurement, and reCAPTCHA bot protection. These providers process data as needed to provide their services.</p>

            <h2>Business Analytics</h2>
            <p>Business accounts may see analytics related to check-ins at their business, including first-time visits, daily activity, and related aggregate engagement. These analytics are intended to help businesses understand CraftCrawl activity at their location.</p>

            <h2>Your Choices and Rights</h2>
            <p>You can update privacy settings, notification preferences, profile visibility-related settings, and account preferences from your account settings.</p>
            <p>You have the right to request access to the personal information we hold about you, to request correction of inaccurate information, and to request deletion of your data. To exercise these rights, email us at <a class="text-link" href="mailto:<?php echo escape_output($contact_email); ?>"><?php echo escape_output($contact_email); ?></a>. We will respond to requests within 30 days.</p>

            <h2>Account Deletion</h2>
            <p>You can delete your account at any time from your account settings. Deleting your account permanently removes your login access, profile identity, social connections, uploaded photos, comments, reactions, and feed visibility. Anonymous aggregate activity needed for statistics is retained without keeping your account publicly visible. You may also email us at <a class="text-link" href="mailto:<?php echo escape_output($contact_email); ?>"><?php echo escape_output($contact_email); ?></a> to request account deletion.</p>

            <h2>Data Retention</h2>
            <p>We retain your personal information for as long as your account is active or as needed to provide the service. If you delete your account, personally identifiable information is removed or anonymized. If you disable your account, it is locked and login is blocked, but your data is not removed. Aggregate statistics that do not identify you may be retained indefinitely.</p>

            <h2>Security</h2>
            <p>We use reasonable safeguards such as password hashing, CSRF protection, email verification, reCAPTCHA, secure session practices, and access controls. No online service can guarantee perfect security.</p>

            <h2>Contact</h2>
            <p>Questions about this policy can be sent to <a class="text-link" href="mailto:<?php echo escape_output($contact_email); ?>"><?php echo escape_output($contact_email); ?></a>.</p>
        </section>
    </main>
</body>
</html>
