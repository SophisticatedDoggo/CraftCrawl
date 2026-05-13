<?php
require_once __DIR__ . '/config.php';

$contact_email = craftcrawl_env('CRAFTCRAWL_SUPPORT_EMAIL', 'support@craftcrawl.site');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Terms of Service</title>
    <script src="js/theme_init.js"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <main class="settings-page legal-page">
        <header class="settings-header legal-header">
            <div>
                <img class="site-logo" src="images/Logo.webp" alt="CraftCrawl logo">
                <div>
                    <h1>Terms of Service</h1>
                    <p>Last updated May 12, 2026</p>
                </div>
            </div>
            <a href="index.php">Back to CraftCrawl</a>
        </header>

        <section class="settings-panel legal-panel">
            <h2>Using CraftCrawl</h2>
            <p>CraftCrawl is a discovery, check-in, review, event, and social activity service for craft beverage locations. By using CraftCrawl, you agree to use it lawfully, respectfully, and in a way that does not harm other users, businesses, or the service.</p>

            <h2>Accounts</h2>
            <p>You are responsible for keeping your login information secure and for the activity that happens through your account. You must provide accurate account information and may not impersonate another person or business.</p>

            <h2>Check-ins, XP, Badges, and Reviews</h2>
            <p>CraftCrawl may award XP, badges, levels, or other progress indicators for eligible activity. We may adjust, deny, or remove XP, badges, reviews, check-ins, or other activity if we believe the activity is inaccurate, abusive, fraudulent, automated, or otherwise inconsistent with the intended use of the service.</p>

            <h2>User Content</h2>
            <p>You are responsible for reviews, photos, comments, replies, reactions, recommendations, and other content you submit. Do not submit unlawful, misleading, abusive, harassing, hateful, spammy, or infringing content. You grant CraftCrawl permission to host, display, process, and use your submitted content to operate and improve the service.</p>

            <h2>Business Content</h2>
            <p>Business users are responsible for keeping business information, hours, addresses, event details, and media accurate. CraftCrawl may review, edit, hide, or remove business content that appears inaccurate, harmful, or inappropriate.</p>

            <h2>Location Features</h2>
            <p>Location-based check-ins depend on device GPS, browser permissions, network conditions, business hours, and server-side checks. Availability and accuracy are not guaranteed.</p>

            <h2>Acceptable Use</h2>
            <p>You may not attempt to break, scrape, overload, reverse engineer, bypass security, abuse notifications, harvest data, spam users, manipulate reviews, or farm XP through fake or automated activity.</p>

            <h2>Availability</h2>
            <p>CraftCrawl may change, pause, or discontinue features at any time. We aim to keep the service available, but we do not guarantee uninterrupted access.</p>

            <h2>Disclaimers</h2>
            <p>CraftCrawl is provided as-is. Information about businesses, events, hours, locations, and availability may be incomplete or outdated. Always confirm important details directly with the business.</p>

            <h2>Contact</h2>
            <p>Questions about these terms can be sent to <a class="text-link" href="mailto:<?php echo escape_output($contact_email); ?>"><?php echo escape_output($contact_email); ?></a>.</p>
        </section>
    </main>
</body>
</html>
