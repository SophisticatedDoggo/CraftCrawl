<?php
include 'db.php';

$message = null;
$success = null;

$email = "";
$first_name = "";
$last_name = "";
$password = "";
$verify_password = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $business_name = $_POST['business_name'];
    $password = $_POST['password'];
    $verify_password = $_POST['verify_password'];
    $date = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if(!$user) {
        if(!empty($password) && ($password === $verify_password)) {
            if (strlen($password) < 10) {
                $message = "Your Password Must Contain At Least 10 Characters!";
            }
            elseif(!preg_match("#[0-9]+#",$password)) {
                $message = "Your Password Must Contain At Least 1 Number!";
            }
            elseif(!preg_match('/[!@#$%^&*]+/',$password)) {
                $message = "Your Password Must Contain At Least 1 Symbol (!@#$%^&*)!";
            }
            elseif(!preg_match("#[A-Z]+#",$password)) {
                $message = "Your Password Must Contain At Least 1 Capital Letter!";
            }
            elseif(!preg_match("#[a-z]+#",$password)) {
                $message = "Your Password Must Contain At Least 1 Lowercase Letter!";
            }
            else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (fName, lName, email, password_hash, createdAt) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $first_name, $last_name, $email, $hash, $date);
                $stmt->execute();
                $message = "You have successfully created an account! Redirecting...";
                $success = true;
            }
        } else {
            if($password !== $verify_password) {
                $message = "Your passwords do not match!";
            }
        }
    } else {
        $message = "An account already exists with that Email";
    }


}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | User Account Creation</title>
    <link rel="stylesheet" href="css/style.css">
    <?php if ($success) : ?>
        <meta http-equiv="refresh" content="3;url=user_login.php">
    <?php endif; ?>
</head>
<body>
    <div>
        <h1>Create An Account</h1>
        <form id="account_creation_form" action="" method="POST">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required value="<?php echo $email ?>"><br><br>
            <label for="business_name">Business Name:</label>
            <input type="text" id="business_name" name="business_name" required value="<?php echo $business_name ?>"><br><br>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required value="<?php echo $password ?>"><br><br>
            <label for="verify_password">Verify Password:</label>
            <input type="password" id="verify_password" name="verify_password" required value="<?php echo $verify_password ?>"><br><br>
            <div id="pswd_validation_msg"></div>
            <input type="submit" value="Create Account">
            <div>
                <?php if (isset($message)) : ?>
                    <p><?php echo $message ?></p>
                <?php endif; ?>
            </div>
        </form>
    </div>
</body>
</html>