<?php
require_once 'src/Session.php';
require_once 'src/User.php';
require_once 'src/Utils.php';
require_once 'src/AuditLog.php';

Session::start();

$userRepo = new User();
$auditLog = new AuditLog();

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$error = '';
$success = false;

// Validate token
$user = null;
if ($token) {
    $user = $userRepo->findByPasswordSetupToken($token);
}

if (!$user) {
    $error = "The password setup link is invalid or has expired.";
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    if (!Utils::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please try again.";
    } else {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } elseif ($password !== $confirmPassword) {
            $error = "Passwords do not match.";
        } else {
            // Success - set password and log in
            if ($userRepo->setPasswordWithToken($token, $password)) {
                // Log successful setup
                $auditLog->log(
                    'password_setup_completed',
                    $user['ID'],
                    $user['email'],
                    $user['ID'],
                    'user',
                    ['ip' => Utils::getClientIp()]
                );

                // Auto-login
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['ID'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_timezone'] = $user['timezone'] ?? 'UTC';
                $_SESSION['user_theme'] = $user['theme'] ?? 'dark';

                // Redirect to dashboard
                header('Location: dashboard.php');
                exit;
            } else {
                $error = "Failed to set password. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <title>Set Up Password - Snoozer</title>
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
        }

        .login-card {
            width: 100%;
            max-width: 450px;
            padding: 40px;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .brand-logo {
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: 4px;
            text-align: center;
            margin-bottom: 30px;
            background: linear-gradient(45deg, #d291ff, #7d3c98);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>

<body>
    <div class="glass-panel login-card">
        <div class="brand-logo">SNOOZER</div>

        <?php if ($error && !$user): ?>
            <div class="text-center">
                <div class="alert alert-danger border-0 rounded-pill px-3 py-2 small mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <p class="text-muted small">Please contact your administrator for a new setup link.</p>
                <a href="login.php" class="btn btn-premium btn-block py-3 mt-4">Back to Login</a>
            </div>
        <?php else: ?>
            <h5 class="text-center font-weight-bold mb-2">Welcome,
                <?php echo htmlspecialchars($user['name']); ?>!
            </h5>
            <p class="text-center text-muted small mb-4">Please set up a secure password for your new account.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger border-0 rounded-pill px-3 py-2 small text-center mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?php echo Utils::csrfField(); ?>
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <div class="form-group mb-4">
                    <label class="small font-weight-bold ml-2 text-uppercase">New Password</label>
                    <input type="password" name="password" class="form-control rounded-pill px-4 py-4 border-0"
                        placeholder="Min. 8 characters" required autofocus
                        style="background: rgba(255,255,255,0.1); color: #fff;">
                </div>

                <div class="form-group mb-4">
                    <label class="small font-weight-bold ml-2 text-uppercase">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control rounded-pill px-4 py-4 border-0"
                        placeholder="Confirm your password" required
                        style="background: rgba(255,255,255,0.1); color: #fff;">
                </div>

                <button type="submit" class="btn btn-premium btn-block py-3 mt-2">Complete Setup</button>
            </form>
        <?php endif; ?>

        <div class="text-center mt-4">
            <small class="text-muted">Modern Productivity for Teams</small>
        </div>
    </div>
</body>

</html>