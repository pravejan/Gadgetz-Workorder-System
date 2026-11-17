<?php
/**
 * User Login Page
 */
require_once 'config.php';

// Redirect to respective panels if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: dashboard.php");
        exit;
    } else {
        header("Location: technician.php");
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password']; // Don't sanitize password, it affects the hash check

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: dashboard.php");
                } else {
                    header("Location: technician.php");
                }
                exit;
            } else {
                $error = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GADGETZ.LK</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="login-body">
    <div class="login-card">
        <div class="login-logo">
            <div class="logo-inner">🛠️</div>
        </div>
        <div class="login-header">
            <h2>GADGETZ.LK</h2>
            <p>Sales & Repair Work Order System</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="username" class="form-label">Username</label>
                <input type="text" name="username" id="username" class="form-control" placeholder="Enter username (e.g. admin or tech)" required autofocus>
            </div>
            
            <div class="form-group" style="margin-bottom: 30px;">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Enter password (e.g. admin123 or tech123)" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 14px;">
                Sign In
            </button>
        </form>
    </div>
</body>
</html>
