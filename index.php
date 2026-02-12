<?php
require_once 'src/Session.php';
require_once 'src/Utils.php';

Session::start();
$isLoggedIn = Session::isLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Snoozer - Achieve Inbox Zero, Effortlessly</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Inter:wght@300;400;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #7d3c98;
            --primary-light: #d291ff;
            --bg: #0b0b0f;
            --card-bg: rgba(255, 255, 255, 0.03);
            --border: rgba(255, 255, 255, 0.1);
            --text-main: #ffffff;
            --text-dim: rgba(255, 255, 255, 0.7);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            overflow-x: hidden;
            line-height: 1.6;
        }

        h1,
        h2,
        h3,
        .nav-logo {
            font-family: 'Outfit', sans-serif;
        }

        /* --- Background Orbs --- */
        .orb {
            position: fixed;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
            opacity: 0.15;
            animation: float 20s infinite alternate ease-in-out;
        }

        .orb-1 {
            background: var(--primary);
            top: -100px;
            right: -100px;
        }

        .orb-2 {
            background: #3498db;
            bottom: -100px;
            left: -100px;
            animation-delay: -5s;
        }

        @keyframes float {
            0% {
                transform: translate(0, 0) scale(1);
            }

            100% {
                transform: translate(50px, 100px) scale(1.1);
            }
        }

        /* --- Navigation --- */
        nav {
            position: fixed;
            top: 0;
            width: 100%;
            padding: 1.5rem 10%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            background: rgba(11, 11, 15, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
        }

        .nav-logo {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: 2px;
            color: #fff;
            text-decoration: none;
            background: linear-gradient(135deg, #fff 0%, var(--primary-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-links {
            display: flex;
            gap: 2.5rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-dim);
            font-weight: 500;
            transition: 0.3s;
            font-size: 0.95rem;
        }

        .nav-links a:hover {
            color: #fff;
        }

        .btn-auth {
            padding: 0.7rem 1.8rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: 0.3s;
        }

        .btn-login {
            color: #fff;
            margin-right: 1rem;
        }

        .btn-register {
            background: var(--primary);
            color: #fff;
            border: 1px solid var(--primary);
        }

        .btn-register:hover {
            background: var(--primary-light);
            box-shadow: 0 0 20px rgba(210, 145, 255, 0.4);
        }

        .btn-dashboard {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid var(--border);
        }

        /* --- Hero Section --- */
        .hero {
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 0 10%;
        }

        .hero h1 {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            line-height: 1.1;
        }

        .gradient-text {
            background: linear-gradient(135deg, #fff 30%, var(--primary-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero p {
            font-size: 1.25rem;
            color: var(--text-dim);
            max-width: 700px;
            margin-bottom: 2.5rem;
        }

        .hero-btns {
            display: flex;
            gap: 1.5rem;
        }

        /* --- Features Section --- */
        .section {
            padding: 100px 10%;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-header h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            padding: 3rem 2rem;
            border-radius: 24px;
            transition: 0.4s;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--primary-light);
        }

        .feature-card i {
            font-size: 2.5rem;
            color: var(--primary-light);
            margin-bottom: 1.5rem;
        }

        .feature-card h3 {
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        /* --- About Section --- */
        .about-content {
            display: flex;
            align-items: center;
            gap: 4rem;
        }

        .about-text {
            flex: 1;
        }

        .about-image {
            flex: 1;
            background: var(--card-bg);
            border: 1px solid var(--border);
            height: 400px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 5rem;
            color: var(--primary-light);
            position: relative;
        }

        /* --- Plans Section --- */
        .plans-grid {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .plan-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            padding: 3rem;
            border-radius: 30px;
            width: 350px;
            text-align: center;
            transition: 0.4s;
        }

        .plan-card.featured {
            border: 2px solid var(--primary);
            transform: scale(1.05);
            background: rgba(125, 60, 152, 0.05);
        }

        .plan-card h3 {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: var(--text-dim);
        }

        .plan-price {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 2rem;
        }

        .plan-price span {
            font-size: 1rem;
            color: var(--text-dim);
        }

        .plan-features {
            list-style: none;
            margin-bottom: 2.5rem;
            text-align: left;
        }

        .plan-features li {
            margin-bottom: 1rem;
            color: var(--text-dim);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .plan-features li i {
            color: #2ecc71;
        }

        /* --- Animations --- */
        .reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: 1s all ease;
        }

        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }

        /* --- Responsive --- */
        @media (max-width: 900px) {
            .hero h1 {
                font-size: 3.5rem;
            }

            .nav-links {
                display: none;
            }

            .about-content {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <nav>
        <a href="#" class="nav-logo">SNOOZER</a>
        <div class="nav-links">
            <a href="#home">Home</a>
            <a href="#features">Features</a>
            <a href="#about">About</a>
            <a href="#plans">Plans</a>
        </div>
        <div class="nav-actions">
            <?php if ($isLoggedIn): ?>
                <a href="dashboard.php" class="btn-auth btn-dashboard">Go to Dashboard</a>
            <?php else: ?>
                <a href="login.php" class="btn-auth btn-login">Login</a>
                <a href="register.php" class="btn-auth btn-register">Get Started</a>
            <?php endif; ?>
        </div>
    </nav>

    <section class="hero" id="home">
        <h1 class="reveal">The Future of <br><span class="gradient-text" id="typewriter"></span></h1>
        <p class="reveal">Stop drowning in notifications. Snoozer intelligently automates your inbox, letting you focus
            on what actually matters.</p>
        <div class="hero-btns reveal">
            <?php if (!$isLoggedIn): ?>
                <a href="register.php" class="btn-auth btn-register" style="padding: 1rem 2.5rem; font-size: 1.1rem;">Start
                    Free Trial</a>
            <?php else: ?>
                <a href="dashboard.php" class="btn-auth btn-register"
                    style="padding: 1rem 2.5rem; font-size: 1.1rem;">Manage Reminders</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="section" id="features">
        <div class="section-header reveal">
            <h2>Workflow Redefined</h2>
            <p style="color: var(--text-dim)">Built for professionals who value their time.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card reveal">
                <i class="fas fa-magic"></i>
                <h3>Smart Snoozing</h3>
                <p>Intelligent algorithms predict when you'll be ready to handle specific tasks.</p>
            </div>
            <div class="feature-card reveal">
                <i class="fas fa-bolt"></i>
                <h3>Instant Actions</h3>
                <p>Execute complex workflows directly from your email notifications.</p>
            </div>
            <div class="feature-card reveal">
                <i class="fas fa-shield-alt"></i>
                <h3>Secure by Design</h3>
                <p>End-to-end encryption for all your communication and task data.</p>
            </div>
        </div>
    </section>

    <section class="section" id="about">
        <div class="about-content">
            <div class="about-image reveal">
                <i class="fas fa-infinity"></i>
            </div>
            <div class="about-text reveal">
                <h2>Our Mission</h2>
                <p style="margin-bottom: 1.5rem; color: var(--text-dim)">At Snoozer, we believe that your inbox should
                    work for you, not against you. Born from the frustration of endless notification loops, we've built
                    the most intuitive reminder and automation system for the modern web.</p>
                <p style="color: var(--text-dim)">We're not just a tool; we're a philosophy of "Inbox Zero" and
                    cognitive ease. By delaying the unimportant, we make space for the essential.</p>
            </div>
        </div>
    </section>

    <section class="section" id="plans">
        <div class="section-header reveal">
            <h2>Choose Your Pace</h2>
            <p style="color: var(--text-dim)">Transparent pricing for every stage of growth.</p>
        </div>
        <div class="plans-grid">
            <div class="plan-card reveal">
                <h3>ESSENTIAL</h3>
                <div class="plan-price">$0<span>/mo</span></div>
                <ul class="plan-features">
                    <li><i class="fas fa-check"></i> Basic Email Reminders</li>
                    <li><i class="fas fa-check"></i> Up to 50 active tasks</li>
                    <li><i class="fas fa-check"></i> 48-hour data retention</li>
                </ul>
                <a href="register.php" class="btn-auth btn-dashboard" style="display: block;">Start Free</a>
            </div>
            <div class="plan-card featured reveal">
                <h3>PROFESSIONAL</h3>
                <div class="plan-price">$12<span>/mo</span></div>
                <ul class="plan-features">
                    <li><i class="fas fa-check"></i> Unlimited Smart Snoozes</li>
                    <li><i class="fas fa-check"></i> Kanban Visualizer</li>
                    <li><i class="fas fa-check"></i> Custom Webhooks</li>
                    <li><i class="fas fa-check"></i> Priority Support</li>
                </ul>
                <a href="register.php" class="btn-auth btn-register" style="display: block;">Upgrade Now</a>
            </div>
        </div>
    </section>

    <footer style="padding: 50px 10%; border-top: 1px solid var(--border); text-align: center; color: var(--text-dim)">
        <p>&copy; 2024 SNOOZER App. All rights reserved.</p>
    </footer>

    <script>
        // --- Typewriter Effect ---
        const textElement = document.getElementById('typewriter');
        const phrases = ['Productivity', 'Efficiency', 'Inbox Zero', 'Deep Work'];
        let phraseIndex = 0;
        let charIndex = 0;
        let isDeleting = false;

        function type() {
            const currentPhrase = phrases[phraseIndex];
            if (isDeleting) {
                textElement.textContent = currentPhrase.substring(0, charIndex - 1);
                charIndex--;
            } else {
                textElement.textContent = currentPhrase.substring(0, charIndex + 1);
                charIndex++;
            }

            if (!isDeleting && charIndex === currentPhrase.length) {
                isDeleting = true;
                setTimeout(type, 2000);
            } else if (isDeleting && charIndex === 0) {
                isDeleting = false;
                phraseIndex = (phraseIndex + 1) % phrases.length;
                setTimeout(type, 500);
            } else {
                setTimeout(type, isDeleting ? 50 : 150);
            }
        }
        type();

        // --- Reveal on Scroll ---
        function reveal() {
            var reveals = document.querySelectorAll(".reveal");
            for (var i = 0; i < reveals.length; i++) {
                var windowHeight = window.innerHeight;
                var elementTop = reveals[i].getBoundingClientRect().top;
                var elementVisible = 150;
                if (elementTop < windowHeight - elementVisible) {
                    reveals[i].classList.add("active");
                }
            }
        }
        window.addEventListener("scroll", reveal);
        // Initial call
        reveal();
    </script>
</body>

</html>