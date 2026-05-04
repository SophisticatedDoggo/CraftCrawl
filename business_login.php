<?php
session_start();
include 'db.php';

$login_error = false;
$email = "";

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password'] ?? '');

    $stmt = $conn->prepare("SELECT id, password_hash FROM businesses WHERE bEmail=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $business = $result->fetch_assoc();

    if($business && password_verify($password, $business['password_hash'])) {
        $_SESSION['business_id'] = $business['id'];
        header("Location: business/business_portal.php");
        exit();
    } else {
        $login_error = true;
    }

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | Business Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div>
        <h1>Business Login</h1>
        <form action="" method="POST">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required value="<?php echo escape_output($email) ?>"><br><br>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required><br><br>
            <input type="submit" value="Login">
            <div>
                <?php if ($login_error) : ?>
                    <p>Incorrect Email or Password</p>
                <?php endif; ?>
            </div>
        </form>
        <a href="business_account_creation.php">Create An Account</a>
    </div>
</body>
</html>
