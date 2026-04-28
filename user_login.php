<?php
session_start();
include 'db.php';

$login_error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password_hash FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        header("Location: index.php");
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
    <title>CraftCrawl | User Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div>
        <h1>Login</h1>
        <form id="login_form" action="" method="POST">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required><br><br>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required><br><br>
            <input type="submit" value="Login">
            <div>
                <?php if ($login_error) : ?>
                    <p>Incorrect Email or Password</p>
                <?php endif; ?>
            </div>
        </form>
        <a href="user_account_creation.php">Create An Account</a>
    </div>
</body>
</html>