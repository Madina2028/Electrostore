<?php
require_once __DIR__ . '/security.php';
secureSessionStart();


session_regenerate_id(true);
$redirectTarget = authRedirectTarget();
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($redirectTarget ?? '../index.php'));
    exit();
}

require_once '../database_conn.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rejectInvalidCsrf();
    $identifier = trim($_POST['identifier']);
    $password = $_POST['password'];

    if (empty($identifier) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $database = new Database();
        $db = $database->connect();

        if (loginIsLocked($db, $identifier)) {
            $error = "Too many login attempts. Please try again in 15 minutes.";
        } else {

        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
        $query = $isEmail
            ? "SELECT id, username, email, password_hash FROM users WHERE email = :identifier"
            : "SELECT id, username, email, password_hash FROM users WHERE username = :identifier";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':identifier', $identifier);
        $stmt->execute();

        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $user['password_hash'])) {
                clearLoginFailures($db, $identifier);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header('Location: ' . ($redirectTarget ?? '../index.php'));
                exit();
            }
        }
        recordLoginFailure($db, $identifier);

        $error = "Invalid username/email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ElectroStore</title>

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">

    <!-- External CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

    <!-- Centered login form -->
    <div class="login-container">
        <!-- Updated form heading with link -->
        <div class="form-heading">
            <a href="../index.php" style="text-decoration: none; color: #333;">Login to <strong style="color: #007bff;">ElectroStore</strong></a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message" style="color: red; margin-bottom: 1rem;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form onsubmit="return validateLogin()" method="POST" action="login.php">
            <?= csrfField() ?>
            <?php if ($redirectTarget !== null): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectTarget, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            <label for="identifier" class="visually-hidden">Email or Username</label>
            <input type="text" id="identifier" name="identifier" placeholder="Email or Username" required>

            <label for="password" class="visually-hidden">Password</label>
            <input type="password" id="password" name="password" placeholder="Password" required>

            <button type="submit">Login</button>
        </form>

        <p>Don't have an account? <a href="signup.php<?= $redirectTarget !== null ? '?redirect=' . rawurlencode($redirectTarget) : '' ?>">Sign up here</a></p>
    </div>

    <script src="../assets/js/validation.js"></script>
</body>
</html>
