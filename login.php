<?php
session_start();
require_once 'src/User.php';
require_once 'src/Utils.php';
require_once 'src/RateLimiter.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!Utils::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please try again.";
    } else {
        $email = Utils::sanitizeEmail($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validate email format
        if (!Utils::isValidEmail($email)) {
            $error = "Please enter a valid email address.";
        } else {
            // Rate limiting: 5 attempts per 15 minutes per IP
            $rateLimiter = new RateLimiter(5, 15);
            $rateLimitKey = RateLimiter::getClientIp();

            if ($rateLimiter->tooManyAttempts($rateLimitKey)) {
                $remainingSeconds = $rateLimiter->availableIn($rateLimitKey);
                $remainingMinutes = ceil($remainingSeconds / 60);
                $error = "Too many login attempts. Please try again in {$remainingMinutes} minute(s).";
            } else {
                $userRepo = new User();
                $user = $userRepo->login($email, $password);

                if ($user) {
                    // Clear rate limit on successful login
                    $rateLimiter->clear($rateLimitKey);

                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['ID'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_timezone'] = $user['timezone'] ?? 'UTC';
                    $_SESSION['user_theme'] = $user['theme'] ?? 'dark';
                    header('Location: dashboard.php');
                    exit;
                } else {
                    // Record failed attempt
                    $rateLimiter->hit($rateLimitKey);
                    $error = "Invalid email or password";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <title>Login - Snoozer</title>
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
            max-width: 400px;
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

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 rounded-pill px-3 py-2 small text-center mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?php echo Utils::csrfField(); ?>
            <div class="form-group mb-4">
                <label class="small font-weight-bold ml-2">EMAIL ADDRESS</label>
                <input type="email" name="email" class="form-control rounded-pill px-4 py-4 border-0"
                    placeholder="name@company.com" required autofocus
                    style="background: rgba(255,255,255,0.1); color: #fff;">
            </div>
            <div class="form-group mb-4">
                <label class="small font-weight-bold ml-2">PASSWORD</label>
                <input type="password" name="password" class="form-control rounded-pill px-4 py-4 border-0"
                    placeholder="••••••••" required style="background: rgba(255,255,255,0.1); color: #fff;">
            </div>
            <button type="submit" class="btn btn-premium btn-block py-3 mt-2">Sign In</button>
            <div class="text-center mt-3">
                <a href="forgot_password.php" class="small text-muted" style="text-decoration: none;">Forgot
                    Password?</a>
            </div>
        </form>

        <div class="text-center mt-4">
            <small class="text-muted">Modern Productivity for Teams</small>
        </div>
    </div>
</body>

</html>