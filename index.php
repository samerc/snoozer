<?php
require_once 'src/Session.php';
require_once 'src/Utils.php';

Session::start();

// If already logged in, go to dashboard
if (Session::isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Snoozer - Zero Inbox Mastery</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #d291ff 0%, #7d3c98 100%);
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at top right, #1a0b2e, #0a0a0a);
            min-height: 100vh;
            color: #fff;
            overflow-x: hidden;
        }

        .navbar {
            padding: 2rem 0;
            background: transparent !important;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.8rem;
            letter-spacing: 3px;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-section {
            padding: 100px 0;
            position: relative;
        }

        .hero-title {
            font-size: 4rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 2rem;
            background: linear-gradient(to bottom, #fff 0%, #aaa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 1.4rem;
            color: #888;
            margin-bottom: 3rem;
            max-width: 600px;
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
            padding: 40px;
            transition: transform 0.3s ease;
        }

        .glass-card:hover {
            transform: translateY(-10px);
            border-color: rgba(210, 145, 255, 0.3);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .btn-custom {
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }

        .btn-primary-custom {
            background: var(--primary-gradient);
            border: none;
            color: #fff;
            box-shadow: 0 10px 30px rgba(125, 60, 152, 0.3);
        }

        .btn-primary-custom:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 40px rgba(125, 60, 152, 0.5);
            color: #fff;
        }

        .btn-outline-custom {
            background: transparent;
            border: 1px solid var(--glass-border);
            color: #fff;
        }

        .btn-outline-custom:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: #fff;
            color: #fff;
        }

        .floating-bg {
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(125, 60, 152, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            z-index: -1;
            filter: blur(80px);
        }

        .orb-1 {
            top: -100px;
            right: -100px;
        }

        .orb-2 {
            bottom: -200px;
            left: -100px;
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }

            .hero-subtitle {
                font-size: 1.1rem;
            }
        }
    </style>
</head>

<body>
    <div class="floating-bg orb-1"></div>
    <div class="floating-bg orb-2"></div>

    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">SNOOZER</a>
            <div class="ml-auto">
                <a href="login.php" class="btn btn-outline-custom btn-custom px-4 mr-2">Login</a>
                <a href="register.php" class="btn btn-primary-custom btn-custom px-4">Get Started</a>
            </div>
        </div>
    </nav>

    <main class="container hero-section">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="hero-title">Reach & Maintain <br>Zero Inbox Status.</h1>
                <p class="hero-subtitle">
                    The modern productivity tool that turns your email into a powerful task management system.
                    Stop drowning in noise and start focusing on what matters.
                </p>
                <div class="d-flex flex-wrap">
                    <a href="register.php" class="btn btn-primary-custom btn-custom mr-3 mb-3">Start for Free</a>
                    <a href="#features" class="btn btn-outline-custom btn-custom mb-3">See how it works</a>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block">
                <div class="glass-card text-center p-5 mx-auto" style="max-width: 500px; border-radius: 50px;">
                    <div class="brand-logo mb-4" style="font-size: 3rem;">SNOOZER</div>
                    <p class="text-muted">Your intelligent inbox assistant</p>
                    <div class="mt-4 p-3 rounded-pill bg-dark d-inline-block px-4">
                        <code style="color: #d291ff;">tomorrow@snoozer.cloud</code>
                    </div>
                    <p class="small text-muted mt-3">Simple commands, powerful results.</p>
                </div>
            </div>
        </div>

        <div id="features" class="row mt-5 pt-5">
            <div class="col-md-4 mb-4">
                <div class="glass-card h-100">
                    <div class="feature-icon">🚀</div>
                    <h4 class="font-weight-bold">Seamless Integration</h4>
                    <p class="text-muted small">Works with your existing email. No new apps to install or tabs to
                        manage.</p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="glass-card h-100">
                    <div class="feature-icon">⚡</div>
                    <h4 class="font-weight-bold">Dynamic Reminders</h4>
                    <p class="text-muted small">Schedule follow-ups by simply emailing or BCCing Snoozer specialized
                        addresses.</p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="glass-card h-100">
                    <div class="feature-icon">📊</div>
                    <h4 class="font-weight-bold">Visual Kanban</h4>
                    <p class="text-muted small">Organize your priorities with a built-in Kanban board that syncs with
                        your inbox flow.</p>
                </div>
            </div>
        </div>
    </main>

    <footer class="container py-5 mt-5 border-top border-secondary text-center">
        <p class="text-muted small">&copy; <?php echo date('Y'); ?> Snoozer. Modern Productivity for Teams.</p>
    </footer>
</body>

</html>