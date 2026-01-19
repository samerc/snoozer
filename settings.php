<?php
session_start();
require_once 'src/User.php';
require_once 'src/Utils.php';

// Auth Check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userRepo = new User();
$user = $userRepo->findByEmail($_SESSION['user_email']);

if (!$user) {
    die("User session invalid.");
}

// Sync Timezone & Theme
if (!empty($user['timezone'])) {
    date_default_timezone_set($user['timezone']);
    $_SESSION['user_timezone'] = $user['timezone'];
}
$_SESSION['user_theme'] = $user['theme'] ?? 'dark';

$message = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Utils::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please try again.";
    } else {
        $timezone = $_POST['timezone'] ?? 'UTC';
        $password = $_POST['password'] ?? '';

        if (!in_array($timezone, timezone_identifiers_list())) {
            $error = "Invalid Timezone";
        } else {
            $userRepo->update($user['ID'], $user['name'], $user['email'], !empty($password) ? $password : null, $user['role'], $timezone);
            $message = "Settings updated successfully.";

            // Refresh
            $user = $userRepo->findByEmail($_SESSION['user_email']);
            $newTz = $user['timezone'] ?? $timezone ?? 'UTC';
            date_default_timezone_set($newTz);
            $_SESSION['user_timezone'] = $newTz;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $_SESSION['user_theme']; ?>">

<head>
    <meta charset="UTF-8">
    <title>Settings - Snoozer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
</head>

<body>
    <nav class="navbar navbar-expand-lg mb-4 shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand font-weight-bold" href="dashboard.php" style="letter-spacing: 2px;">SNOOZER</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="kanban.php">Kanban</a></li>
                    <li class="nav-item active"><a class="nav-link" href="#">Settings</a></li>
                    <?php if (($_SESSION['user_role'] ?? 'user') === 'admin'): ?>
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

    <div class="container">
        <h1 class="h3 font-weight-bold mb-4">User Settings</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div> <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div> <?php endif; ?>

        <div class="glass-panel p-4">
            <form method="POST">
                <?php echo Utils::csrfField(); ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Timezone</label>
                            <select name="timezone" class="form-control rounded-pill px-3">
                                <?php
                                $commonTimezones = ['UTC', 'Asia/Beirut', 'Europe/London', 'America/New_York', 'Asia/Dubai', 'Europe/Paris'];
                                $currentTimezone = $user['timezone'] ?? 'UTC';
                                if (!in_array($currentTimezone, $commonTimezones))
                                    $commonTimezones[] = $currentTimezone;
                                foreach ($commonTimezones as $tz): ?>
                                    <option value="<?php echo $tz; ?>" <?php echo $currentTimezone === $tz ? 'selected' : ''; ?>>
                                        <?php echo $tz; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mt-3 p-3 glass-panel" style="background: rgba(0,0,0,0.05); border-radius: 15px;">
                            <small class="text-muted d-block uppercase tracking-wider font-weight-bold mb-1">Current
                                Preview</small>
                            <div class="h4 font-weight-bold mb-0"><?php echo date('Y-m-d H:i:s'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Change Password</label>
                            <input type="password" name="password" class="form-control rounded-pill px-3"
                                placeholder="New password (keep blank to skip)">
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-premium btn-block">Apply Changes</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleSwitch = document.querySelector('.theme-switch input[type="checkbox"]');
            async function switchTheme(e) {
                const theme = e.target.checked ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', theme);
                e.target.closest('.theme-switch-wrapper').querySelector('span').textContent = theme.charAt(0).toUpperCase() + theme.slice(1);
                await fetch('api/update_theme.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ theme })
                });
            }
            toggleSwitch.addEventListener('change', switchTheme, false);
        });
    </script>
</body>

</html>