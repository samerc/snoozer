<?php
require_once 'src/Session.php';
require_once 'src/User.php';
require_once 'src/EmailRepository.php';
require_once 'src/Utils.php';

Session::start();
Session::requireAuth();

$currentUserEmail = $_SESSION['user_email'];

$userRepo = new User();
$user = $userRepo->findByEmail($currentUserEmail);

if (!$user) {
    die("User not found ($currentUserEmail).");
}

// Sync Timezone & Theme
if (!empty($user['timezone'])) {
    date_default_timezone_set($user['timezone']);
    $_SESSION['user_timezone'] = $user['timezone'];
}
$_SESSION['user_theme'] = $user['theme'] ?? 'dark';

$emailRepo = new EmailRepository();
$emails = $emailRepo->getUpcomingForUser($currentUserEmail);

$today = [];
$week = [];
$later = [];

$now = time();
$oneDay = $now + (24 * 60 * 60);
$oneWeek = $now + (7 * 24 * 60 * 60);

foreach ($emails as $email) {
    $t = $email['actiontimestamp'];
    if ($t <= $oneDay) {
        $today[] = $email;
    } elseif ($t <= $oneWeek) {
        $week[] = $email;
    } else {
        $later[] = $email;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $_SESSION['user_theme']; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo Utils::csrfMeta(); ?>
    <title>Snoozer Kanban</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        .kanban-container {
            padding: 40px 20px;
        }

        .kanban-col {
            min-height: 80vh;
            padding: 24px;
            margin-bottom: 30px;
        }

        .column-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--glass-border);
        }

        .column-header h4 {
            font-weight: 700;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin: 0;
            color: var(--primary-purple-light);
        }

        .column-count {
            background: var(--primary-purple);
            color: #fff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .card {
            padding: 20px;
            margin-bottom: 18px;
            cursor: grab;
        }

        .card-subject {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .card-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .badge-time {
            background: var(--primary-purple);
            color: #fff;
            padding: 4px 10px;
            border-radius: 8px;
            font-weight: 600;
        }

        .sortable-ghost {
            opacity: 0.1;
            transform: scale(0.9);
        }

        #statusToast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            background: var(--primary-purple);
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            display: none;
            font-weight: 600;
            animation: slideIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        @keyframes slideIn {
            from {
                transform: translateY(100px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
</head>

<body>
    <nav class="navbar navbar-expand-lg mb-2 shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand font-weight-bold" href="dashboard.php" style="letter-spacing: 2px;">SNOOZER</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item active"><a class="nav-link" href="kanban.php">Kanban</a></li>
                    <li class="nav-item"><a class="nav-link" href="settings.php">Settings</a></li>
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="users.php">Users</a></li>
                        <li class="nav-item"><a class="nav-link" href="admin_templates.php">Templates</a></li>
                    <?php endif; ?>
                </ul>
                <div class="theme-switch-wrapper mr-4">
                    <label class="theme-switch" for="checkbox">
                        <input type="checkbox" id="checkbox" <?php echo $_SESSION['user_theme'] === 'light' ? 'checked' : ''; ?> />
                        <div class="slider round"></div>
                    </label>
                    <span
                        class="ml-2 small"><?php echo $_SESSION['user_theme'] === 'light' ? 'Light' : 'Dark'; ?></span>
                </div>
                <span class="navbar-text mr-3 small">
                    Local Time: <strong><?php echo date('H:i'); ?></strong> (<?php echo $_SESSION['user_timezone']; ?>)
                </span>
                <span class="navbar-text mr-3 small">Welcome,
                    <strong><?php echo htmlspecialchars($user['name']); ?></strong></span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-4">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid kanban-container">
        <div class="row">
            <!-- Today Column -->
            <div class="col-md-4">
                <div class="glass-panel kanban-col" id="col-today">
                    <div class="column-header">
                        <h4>Today</h4>
                        <span class="column-count"><?php echo count($today); ?></span>
                    </div>
                    <div class="kanban-list" id="list-today" data-col="today">
                        <?php foreach ($today as $email): ?>
                            <div class="card" data-id="<?php echo $email['ID']; ?>">
                                <div class="card-subject"><?php echo htmlspecialchars($email['subject']); ?></div>
                                <div class="card-meta">
                                    <span class="badge-time"><?php echo date('H:i', $email['actiontimestamp']); ?></span>
                                    <span><?php echo Utils::time_elapsed_string('@' . $email['actiontimestamp']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- This Week Column -->
            <div class="col-md-4">
                <div class="glass-panel kanban-col" id="col-week">
                    <div class="column-header">
                        <h4>This Week</h4>
                        <span class="column-count"><?php echo count($week); ?></span>
                    </div>
                    <div class="kanban-list" id="list-week" data-col="week">
                        <?php foreach ($week as $email): ?>
                            <div class="card" data-id="<?php echo $email['ID']; ?>">
                                <div class="card-subject"><?php echo htmlspecialchars($email['subject']); ?></div>
                                <div class="card-meta">
                                    <span class="badge-time"><?php echo date('M d', $email['actiontimestamp']); ?></span>
                                    <span><?php echo Utils::time_elapsed_string('@' . $email['actiontimestamp']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Upcoming Column -->
            <div class="col-md-4">
                <div class="glass-panel kanban-col" id="col-later">
                    <div class="column-header">
                        <h4>Upcoming</h4>
                        <span class="column-count"><?php echo count($later); ?></span>
                    </div>
                    <div class="kanban-list" id="list-later" data-col="later">
                        <?php foreach ($later as $email): ?>
                            <div class="card" data-id="<?php echo $email['ID']; ?>">
                                <div class="card-subject"><?php echo htmlspecialchars($email['subject']); ?></div>
                                <div class="card-meta">
                                    <span class="badge-time"><?php echo date('M d', $email['actiontimestamp']); ?></span>
                                    <span><?php echo Utils::time_elapsed_string('@' . $email['actiontimestamp']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="statusToast">Updated!</div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // Theme Switcher
            const toggleSwitch = document.querySelector('.theme-switch input[type="checkbox"]');
            async function switchTheme(e) {
                const theme = e.target.checked ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', theme);
                e.target.closest('.theme-switch-wrapper').querySelector('span').textContent = theme.charAt(0).toUpperCase() + theme.slice(1);
                await fetch('api/update_theme.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({ theme })
                });
            }
            toggleSwitch.addEventListener('change', switchTheme, false);

            // Sortable
            const lists = document.querySelectorAll('.kanban-list');
            lists.forEach(el => {
                new Sortable(el, {
                    group: 'kanban',
                    animation: 250,
                    easing: "cubic-bezier(1, 0, 0, 1)",
                    ghostClass: 'sortable-ghost',
                    onEnd: function (evt) {
                        if (evt.from !== evt.to) {
                            const emailId = evt.item.getAttribute('data-id');
                            const targetCol = evt.to.getAttribute('data-col');
                            updateReminder(emailId, targetCol, evt.item);
                            const fromCount = evt.from.closest('.kanban-col').querySelector('.column-count');
                            const toCount = evt.to.closest('.kanban-col').querySelector('.column-count');
                            if (fromCount) fromCount.textContent = Math.max(0, parseInt(fromCount.textContent) - 1);
                            if (toCount) toCount.textContent = parseInt(toCount.textContent) + 1;
                        }
                    }
                });
            });

            async function updateReminder(id, column, cardElement) {
                try {
                    const response = await fetch('api/update_reminder.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                        body: JSON.stringify({ id, column })
                    });
                    const result = await response.json();
                    if (result.success) {
                        showToast('Moved to ' + column);
                    } else { alert('Error: ' + result.error); }
                } catch (error) { console.error('Update failed:', error); }
            }

            function showToast(msg) {
                const toast = document.getElementById('statusToast');
                toast.textContent = msg;
                toast.style.display = 'block';
                setTimeout(() => { toast.style.display = 'none'; }, 2500);
            }
        });
    </script>
</body>

</html>