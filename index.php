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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
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

        * { margin: 0; padding: 0; box-sizing: border-box; scroll-behavior: smooth; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            overflow-x: hidden;
            line-height: 1.6;
        }

        h1, h2, h3, .nav-logo { font-family: 'Outfit', sans-serif; }

        /* --- Background Orbs --- */
        .orb {
            position: fixed;
            width: 500px; height: 500px;
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1; opacity: 0.15;
            animation: float 20s infinite alternate ease-in-out;
        }
        .orb-1 { background: var(--primary); top: -100px; right: -100px; }
        .orb-2 { background: #3498db; bottom: -100px; left: -100px; animation-delay: -5s; }

        @keyframes float {
            0%   { transform: translate(0, 0) scale(1); }
            100% { transform: translate(50px, 100px) scale(1.1); }
        }

        /* --- Navigation --- */
        nav {
            position: fixed; top: 0; width: 100%;
            padding: 1.5rem 10%;
            display: flex; justify-content: space-between; align-items: center;
            z-index: 1000;
            background: rgba(11, 11, 15, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
        }
        .nav-logo {
            font-size: 1.8rem; font-weight: 800; letter-spacing: 2px;
            color: #fff; text-decoration: none;
            background: linear-gradient(135deg, #fff 0%, var(--primary-light) 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .nav-links { display: flex; gap: 2.5rem; align-items: center; }
        .nav-links a { text-decoration: none; color: var(--text-dim); font-weight: 500; transition: 0.3s; font-size: 0.95rem; }
        .nav-links a:hover { color: #fff; }

        .btn-auth { padding: 0.7rem 1.8rem; border-radius: 50px; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: 0.3s; }
        .btn-login { color: #fff; margin-right: 1rem; }
        .btn-register { background: var(--primary); color: #fff; border: 1px solid var(--primary); }
        .btn-register:hover { background: var(--primary-light); box-shadow: 0 0 20px rgba(210, 145, 255, 0.4); }
        .btn-dashboard { background: rgba(255, 255, 255, 0.1); color: #fff; border: 1px solid var(--border); }

        /* --- Hero Section --- */
        .hero {
            height: 100vh;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            text-align: center; padding: 0 10%;
        }
        .hero h1 { font-size: 5rem; margin-bottom: 1.5rem; line-height: 1.1; }
        .gradient-text {
            background: linear-gradient(135deg, #fff 30%, var(--primary-light) 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .hero p { font-size: 1.25rem; color: var(--text-dim); max-width: 700px; margin-bottom: 2.5rem; }
        .hero-btns { display: flex; gap: 1.5rem; }

        /* --- Sections --- */
        .section { padding: 100px 10%; }
        .section-header { text-align: center; margin-bottom: 4rem; }
        .section-header h2 { font-size: 2.5rem; margin-bottom: 1rem; }

        /* --- How It Works --- */
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 0;
            position: relative;
        }
        .step {
            text-align: center;
            padding: 2.5rem 2rem;
            position: relative;
        }
        .step-number {
            width: 60px; height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; font-weight: 800;
            margin: 0 auto 1.5rem;
            font-family: 'Outfit', sans-serif;
        }
        .step h3 { font-size: 1.2rem; margin-bottom: 0.75rem; }
        .step p { color: var(--text-dim); font-size: 0.95rem; }
        .step-connector {
            position: absolute; top: 80px; right: -30px;
            color: var(--primary-light); font-size: 1.5rem; opacity: 0.5;
        }

        /* --- Email Address Examples --- */
        .examples-grid {
            display: flex; flex-wrap: wrap; gap: 1rem;
            justify-content: center; margin-top: 2rem;
        }
        .example-tag {
            background: rgba(125, 60, 152, 0.15);
            border: 1px solid rgba(210, 145, 255, 0.3);
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: var(--primary-light);
            transition: 0.3s;
        }
        .example-tag:hover {
            background: rgba(125, 60, 152, 0.3);
            border-color: var(--primary-light);
        }

        /* --- Features Grid --- */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        .feature-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            padding: 2.5rem 2rem;
            border-radius: 24px;
            transition: 0.4s;
            position: relative; overflow: hidden;
        }
        .feature-card:hover {
            transform: translateY(-8px);
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--primary-light);
        }
        .feature-card .icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            background: rgba(125, 60, 152, 0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; color: var(--primary-light);
            margin-bottom: 1.5rem;
        }
        .feature-card h3 { font-size: 1.2rem; margin-bottom: 0.75rem; }
        .feature-card p { color: var(--text-dim); font-size: 0.9rem; line-height: 1.7; }

        /* --- Security badges --- */
        .security-badges {
            display: flex; flex-wrap: wrap; gap: 1rem;
            justify-content: center; margin-top: 2rem;
        }
        .security-badge {
            display: flex; align-items: center; gap: 0.5rem;
            background: var(--card-bg);
            border: 1px solid var(--border);
            padding: 0.6rem 1.2rem;
            border-radius: 50px;
            font-size: 0.85rem; color: var(--text-dim);
        }
        .security-badge i { color: #2ecc71; }

        /* --- Plans --- */
        .plans-grid { display: flex; justify-content: center; gap: 2rem; flex-wrap: wrap; }
        .plan-card {
            background: var(--card-bg); border: 1px solid var(--border);
            padding: 3rem; border-radius: 30px; width: 320px; text-align: center; transition: 0.4s;
        }
        .plan-card.featured { border: 2px solid var(--primary); transform: scale(1.05); background: rgba(125, 60, 152, 0.05); }
        .plan-card h3 { font-size: 1.1rem; margin-bottom: 1rem; color: var(--text-dim); letter-spacing: 2px; }
        .plan-price { font-size: 2.8rem; font-weight: 800; margin-bottom: 2rem; }
        .plan-price span { font-size: 1rem; color: var(--text-dim); }
        .plan-features { list-style: none; margin-bottom: 2.5rem; text-align: left; }
        .plan-features li { margin-bottom: 1rem; color: var(--text-dim); display: flex; align-items: flex-start; gap: 10px; font-size: 0.9rem; }
        .plan-features li i { color: #2ecc71; margin-top: 3px; flex-shrink: 0; }

        /* --- Animations --- */
        .reveal { opacity: 0; transform: translateY(30px); transition: 1s all ease; }
        .reveal.active { opacity: 1; transform: translateY(0); }

        /* --- Responsive --- */
        @media (max-width: 900px) {
            .hero h1 { font-size: 3rem; }
            .nav-links { display: none; }
            .step-connector { display: none; }
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
            <a href="#how-it-works">How It Works</a>
            <a href="#features">Features</a>
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

    <!-- Hero -->
    <section class="hero" id="home">
        <h1 class="reveal">Email once.<br><span class="gradient-text" id="typewriter"></span></h1>
        <p class="reveal">Send an email to a time address — <code style="color: var(--primary-light)">tomorrow@yourdomain.com</code> — and Snoozer brings it back when you're ready. No apps, no friction, just your inbox at zero.</p>
        <div class="hero-btns reveal">
            <?php if (!$isLoggedIn): ?>
                <a href="register.php" class="btn-auth btn-register" style="padding: 1rem 2.5rem; font-size: 1.1rem;">Start Free</a>
                <a href="#how-it-works" class="btn-auth btn-dashboard" style="padding: 1rem 2.5rem; font-size: 1.1rem;">See How It Works</a>
            <?php else: ?>
                <a href="dashboard.php" class="btn-auth btn-register" style="padding: 1rem 2.5rem; font-size: 1.1rem;">Go to Dashboard</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- How It Works -->
    <section class="section" id="how-it-works">
        <div class="section-header reveal">
            <h2>Three steps to Inbox Zero</h2>
            <p style="color: var(--text-dim)">No new tools to learn. Works inside the email client you already use.</p>
        </div>
        <div class="steps-grid reveal">
            <div class="step">
                <div class="step-number">1</div>
                <h3>Forward or CC</h3>
                <p>Forward any email — or CC Snoozer when sending — to a time address like <code style="color: var(--primary-light)">friday@yourdomain.com</code>.</p>
                <span class="step-connector"><i class="fas fa-arrow-right"></i></span>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <h3>Archive &amp; Forget</h3>
                <p>Archive the email. Your inbox is clean. Snoozer holds onto it until the right moment.</p>
                <span class="step-connector"><i class="fas fa-arrow-right"></i></span>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <h3>Reminder Arrives</h3>
                <p>At the scheduled time, Snoozer replies in the same thread with one-click snooze and cancel buttons.</p>
            </div>
        </div>

        <div class="section-header reveal" style="margin-top: 5rem; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.8rem;">Any time expression you can think of</h2>
            <p style="color: var(--text-dim)">All of these work as the local-part of the email address.</p>
        </div>
        <div class="examples-grid reveal">
            <span class="example-tag">tomorrow</span>
            <span class="example-tag">2hours</span>
            <span class="example-tag">monday</span>
            <span class="example-tag">next-friday</span>
            <span class="example-tag">1week</span>
            <span class="example-tag">3months</span>
            <span class="example-tag">eod</span>
            <span class="example-tag">eow</span>
            <span class="example-tag">31dec</span>
            <span class="example-tag">8am</span>
            <span class="example-tag">evening</span>
            <span class="example-tag">daily</span>
            <span class="example-tag">weekly</span>
            <span class="example-tag">monthly</span>
            <span class="example-tag">weekdays</span>
            <span class="example-tag">upcoming</span>
        </div>
    </section>

    <!-- Features -->
    <section class="section" id="features">
        <div class="section-header reveal">
            <h2>Everything you need, nothing you don't</h2>
            <p style="color: var(--text-dim)">Built for professionals who live in their inbox.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card reveal">
                <div class="icon"><i class="fas fa-envelope-open-text"></i></div>
                <h3>Email-Triggered Reminders</h3>
                <p>No app required. Forward any email to a time address and Snoozer schedules the reminder automatically. Works from any email client, anywhere.</p>
            </div>
            <div class="feature-card reveal">
                <div class="icon"><i class="fas fa-reply"></i></div>
                <h3>Reply-to-Snooze</h3>
                <p>Already snoozed something and changed your mind? Reply to the reminder email with a new time address and it reschedules instantly — without opening the dashboard.</p>
            </div>
            <div class="feature-card reveal">
                <div class="icon"><i class="fas fa-sync-alt"></i></div>
                <h3>Recurring Reminders</h3>
                <p>Email <code style="color: var(--primary-light)">daily@</code>, <code style="color: var(--primary-light)">weekly@</code>, <code style="color: var(--primary-light)">monthly@</code>, or <code style="color: var(--primary-light)">weekdays@</code> to create reminders that automatically reschedule after each firing.</p>
            </div>
            <div class="feature-card reveal">
                <div class="icon"><i class="fas fa-columns"></i></div>
                <h3>Kanban Board</h3>
                <p>Visualise all upcoming reminders as draggable cards across Today, This Week, and Upcoming columns. Drag a card to reschedule it — no date picker needed.</p>
            </div>
            <div class="feature-card reveal">
                <div class="icon"><i class="fas fa-tachometer-alt"></i></div>
                <h3>Dashboard &amp; Stats</h3>
                <p>Live stat cards for pending, due today, due this week, and overdue reminders. Search by subject, paginate, create reminders directly from the web, and review history.</p>
            </div>
            <div class="feature-card reveal">
                <div class="icon"><i class="fas fa-comments"></i></div>
                <h3>Threaded Reminders</h3>
                <p>Reminder emails arrive as a reply inside the same thread as your original email, so all context is in one place. Toggle this per account in settings.</p>
            </div>
            <div class="feature-card reveal">
                <div class="icon"><i class="fas fa-clock"></i></div>
                <h3>Smart Time Parsing</h3>
                <p>Understands natural language: <code style="color: var(--primary-light)">next-tuesday</code>, <code style="color: var(--primary-light)">31dec</code>, <code style="color: var(--primary-light)">eod</code> (end of day), <code style="color: var(--primary-light)">eow</code> (end of week). Respects your timezone and configurable default hour.</p>
            </div>
            <div class="feature-card reveal">
                <div class="icon"><i class="fas fa-search"></i></div>
                <h3>Search &amp; Filter</h3>
                <p>Full subject search on both the Dashboard and Kanban views. Quickly locate any reminder across your backlog without leaving the current view.</p>
            </div>
            <div class="feature-card reveal">
                <div class="icon"><i class="fas fa-shield-alt"></i></div>
                <h3>Secure by Design</h3>
                <p>AES-256 encrypted action URLs, bcrypt passwords, CSRF protection on every form and API call, rate-limited login, and a full admin audit log.</p>
            </div>
        </div>

        <div style="margin-top: 4rem; text-align: center;" class="reveal">
            <p style="color: var(--text-dim); margin-bottom: 1.5rem; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 2px;">Security highlights</p>
            <div class="security-badges">
                <span class="security-badge"><i class="fas fa-check"></i> AES-256 action tokens</span>
                <span class="security-badge"><i class="fas fa-check"></i> bcrypt password hashing</span>
                <span class="security-badge"><i class="fas fa-check"></i> CSRF protection</span>
                <span class="security-badge"><i class="fas fa-check"></i> Rate-limited login</span>
                <span class="security-badge"><i class="fas fa-check"></i> Audit log</span>
                <span class="security-badge"><i class="fas fa-check"></i> Secure session management</span>
            </div>
        </div>
    </section>

    <!-- Plans -->
    <section class="section" id="plans">
        <div class="section-header reveal">
            <h2>Self-hosted &amp; free</h2>
            <p style="color: var(--text-dim)">Snoozer runs on your own server. You own your data.</p>
        </div>
        <div class="plans-grid">
            <div class="plan-card reveal">
                <h3>SELF-HOSTED</h3>
                <div class="plan-price">Free<span> forever</span></div>
                <ul class="plan-features">
                    <li><i class="fas fa-check"></i> Unlimited reminders</li>
                    <li><i class="fas fa-check"></i> All time expressions</li>
                    <li><i class="fas fa-check"></i> Recurring reminders</li>
                    <li><i class="fas fa-check"></i> Reply-to-snooze</li>
                    <li><i class="fas fa-check"></i> Kanban board</li>
                    <li><i class="fas fa-check"></i> Multi-user with admin panel</li>
                    <li><i class="fas fa-check"></i> IMAP &amp; Mailgun ingestion</li>
                </ul>
                <a href="register.php" class="btn-auth btn-register" style="display: block;">Get Started</a>
            </div>
            <div class="plan-card featured reveal">
                <h3>COMING SOON</h3>
                <div class="plan-price" style="font-size: 1.8rem; padding-top: 0.5rem;">Hosted Cloud</div>
                <ul class="plan-features" style="margin-top: 1.5rem;">
                    <li><i class="fas fa-check"></i> Zero server setup</li>
                    <li><i class="fas fa-check"></i> Managed updates &amp; backups</li>
                    <li><i class="fas fa-check"></i> Custom domain support</li>
                    <li><i class="fas fa-check"></i> Everything in self-hosted</li>
                </ul>
                <a href="#" class="btn-auth btn-dashboard" style="display: block; cursor: default; opacity: 0.5;">Notify Me</a>
            </div>
        </div>
    </section>

    <footer style="padding: 50px 10%; border-top: 1px solid var(--border); text-align: center; color: var(--text-dim)">
        <p>&copy; <?php echo date('Y'); ?> SNOOZER. All rights reserved.</p>
    </footer>

    <script>
        // --- Typewriter Effect ---
        const textElement = document.getElementById('typewriter');
        const phrases = ['Remind me later.', 'Inbox Zero.', 'Stay focused.', 'Never forget.'];
        let phraseIndex = 0, charIndex = 0, isDeleting = false;

        function type() {
            const cur = phrases[phraseIndex];
            textElement.textContent = cur.substring(0, isDeleting ? charIndex - 1 : charIndex + 1);
            isDeleting ? charIndex-- : charIndex++;
            if (!isDeleting && charIndex === cur.length) {
                isDeleting = true; setTimeout(type, 2200);
            } else if (isDeleting && charIndex === 0) {
                isDeleting = false;
                phraseIndex = (phraseIndex + 1) % phrases.length;
                setTimeout(type, 500);
            } else {
                setTimeout(type, isDeleting ? 45 : 130);
            }
        }
        type();

        // --- Reveal on Scroll ---
        function reveal() {
            document.querySelectorAll('.reveal').forEach(el => {
                if (el.getBoundingClientRect().top < window.innerHeight - 120) {
                    el.classList.add('active');
                }
            });
        }
        window.addEventListener('scroll', reveal);
        reveal();
    </script>
</body>

</html>
