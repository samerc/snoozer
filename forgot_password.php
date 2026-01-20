<?php
require_once 'src/Session.php';
require_once 'src/User.php';
require_once 'src/Utils.php';

Session::start();

$message = '';
$error = '';
$newPassword = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Utils::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please try again.";
    } else {
        $email = $_POST['email'] ?? '';
        $userRepo = new User();
        $user = $userRepo->findByEmail($email);

        if ($user) {
            $newPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 10);
            $userRepo->updatePassword($user['ID'], $newPassword);
            $message = "Your password has been reset.";
        } else {
            $error = "No account found with that email address.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <title>Forgot Password - Snoozer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            background-color: #121212;
            color: #ffffff;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 40px;
            background: rgba(255, 255, 255, 0.05);
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
        }

        .brand-logo {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: 4px;
            text-align: center;
            margin-bottom: 30px;
            background: linear-gradient(45deg, #d291ff, #7d3c98);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: none;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            box-shadow: none;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="brand-logo">SNOOZER</div>

        <?php if ($newPassword): ?>
            <div class="alert alert-success text-center">
                <p class="mb-2">
                    <?php echo $message; ?>
                </p>
                <div class="bg-white text-dark p-3 rounded font-weight-bold h4">
                    <?php echo htmlspecialchars($newPassword); ?>
                </div>
                <p class="small mt-2 mb-0">Please copy this password and <a href="login.php" class="font-weight-bold">login
                        immediately</a>.</p>
            </div>
        <?php else: ?>
            <h5 class="text-center font-weight-bold mb-4">Reset Password</h5>

            <?php if ($error): ?>
                <div class="alert alert-danger border-0 rounded-pill px-3 py-2 small text-center mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?php echo Utils::csrfField(); ?>
                <div class="form-group mb-4">
                    <label class="small font-weight-bold ml-2 text-uppercase">Email Address</label>
                    <input type="email" name="email" class="form-control rounded-pill px-4 py-4"
                        placeholder="name@company.com" required autofocus>
                </div>
                <button type="submit" class="btn btn-premium btn-block py-3 mt-2">Reset Password</button>
            </form>

            <div class="text-center mt-4">
                <a href="login.php" class="small text-muted">Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>