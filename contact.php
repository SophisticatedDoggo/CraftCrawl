<?php
require_once __DIR__ . '/config.php';

$support_email = craftcrawl_env('CRAFTCRAWL_SUPPORT_EMAIL', 'support@craftcrawl.site');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Contact</title>
    <script src="js/theme_init.js"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <main class="settings-page legal-page">
        <header class="settings-header legal-header">
            <div>
                <img class="site-logo" src="images/Logo.webp" alt="CraftCrawl logo">
                <div>
                    <h1>Contact CraftCrawl</h1>
                    <p>Support, business questions, account help, and safety reports.</p>
                </div>
            </div>
            <a href="index.php" data-back-link>Back</a>
        </header>

        <section class="settings-panel legal-panel">
            <h2>Email</h2>
            <p>For support, account help, business profile questions, content concerns, or privacy requests, email us at:</p>
            <p><a class="text-link legal-contact-link" href="mailto:<?php echo escape_output($support_email); ?>"><?php echo escape_output($support_email); ?></a></p>

            <h2>Helpful Details</h2>
            <p>When contacting support, include the email address on your account, the business or event involved, screenshots if helpful, and a short description of what happened.</p>

            <h2>Urgent Safety or Legal Issues</h2>
            <p>If your request involves illegal activity, immediate safety concerns, or sensitive legal matters, include a clear subject line so it can be reviewed appropriately.</p>
        </section>
    </main>
</body>
</html>
