<?php
require_once 'src/Session.php';
require_once 'src/User.php';
require_once 'src/Utils.php';
require_once 'src/Mailer.php';
require_once 'src/AuditLog.php';

Session::start();

// If already logged in, go to dashboard
if (Session::isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Utils::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please try again.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = Utils::sanitizeEmail($_POST['email'] ?? '');

        if (empty($name) || empty($email)) {
            $error = "Please fill in all fields.";
        } elseif (!Utils::isValidEmail($email)) {
            $error = "Please enter a valid email address.";
        } else {
            $userRepo = new User();
            if ($userRepo->findByEmail($email)) {
                $error = "An account with this email already exists.";
            } else {
                // Create user
                if ($userRepo->create($name, $email, 'user')) {
                    $newUser = $userRepo->findByEmail($email);
                    if ($newUser) {
                        // Generate setup token
                        $token = $userRepo->generatePasswordSetupToken($newUser['ID']);

                        // Send setup email
                        $mailer = new Mailer();
                        if ($mailer->sendPasswordSetupEmail($email, $name, $token)) {
                            $success = "Account created! Please check your email ($email) to set your password and complete registration.";

                            // Log audit event
                            $auditLog = new AuditLog();
                            $auditLog->log(
                                AuditLog::USER_CREATED,
                                $newUser['ID'],
                                $email,
                                $newUser['ID'],
                                'user',
                                ['method' => 'public_registration', 'ip' => Utils::getClientIp()]
                            );
                        } else {
                            $error = "Account created, but we couldn't send the setup email. Please contact support.";
                        }
                    }
                } else {
                    $error = "Failed to create account. Please try again later.";
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
    <title>Get Started - Snoozer</title>
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

        <?php if ($success): ?>
            <div class="text-center">
                <div class="alert alert-success border-0 rounded-pill px-3 py-2 small mb-4">
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <p class="text-muted small">The link in your email will expire in 48 hours.</p>
                <a href="login.php" class="btn btn-premium btn-block py-3 mt-4">Go to Login</a>
            </div>
        <?php else: ?>
            <h5 class="text-center font-weight-bold mb-4">Create Your Account</h5>

            <?php if ($error): ?>
                <div class="alert alert-danger border-0 rounded-pill px-3 py-2 small text-center mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?php echo Utils::csrfField(); ?>

                <div class="form-group mb-4">
                    <label class="small font-weight-bold ml-2 text-uppercase">Full Name</label>
                    <input type="text" name="name" class="form-control rounded-pill px-4 py-4 border-0"
                        placeholder="John Doe" required autofocus style="background: rgba(255,255,255,0.1); color: #fff;"
                        value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>

                <div class="form-group mb-4">
                    <label class="small font-weight-bold ml-2 text-uppercase">Email Address</label>
                    <input type="email" name="email" class="form-control rounded-pill px-4 py-4 border-0"
                        placeholder="name@company.com" required style="background: rgba(255,255,255,0.1); color: #fff;"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <button type="submit" class="btn btn-premium btn-block py-3 mt-2">Create Account</button>
            </form>

            <div class="text-center mt-4">
                <p class="small text-muted">Already have an account? <a href="login.php" class="font-weight-bold"
                        style="color: #d291ff;">Login</a></p>
            </div>
        <?php endif; ?>

        <div class="text-center mt-4">
            <small class="text-muted">Modern Productivity for Teams</small>
        </div>
    </div>
</body>

</html>