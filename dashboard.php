<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'src/User.php';
require_once 'src/EmailRepository.php';
require_once 'src/Utils.php';

$currentUserEmail = $_SESSION['user_email'];

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

$emailRepo = new EmailRepository();
$emails = $emailRepo->getUpcomingForUser($currentUserEmail);

?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $_SESSION['user_theme']; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Snoozer Dashboard</title>
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
                    <li class="nav-item active"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="kanban.php">Kanban</a></li>
                    <li class="nav-item"><a class="nav-link" href="settings.php">Settings</a></li>
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
                <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 font-weight-bold">Upcoming Reminders</h1>
            <span class="badge badge-primary px-3 py-2 rounded-pill"><?php echo count($emails); ?> Scheduled</span>
        </div>

        <div class="glass-panel p-0 overflow-hidden">
            <table class="table table-borderless table-hover mb-0">
                <thead class="bg-dark text-white">
                    <tr>
                        <th class="pl-4">#</th>
                        <th>Subject</th>
                        <th>Original Date</th>
                        <th>Reminder Time</th>
                        <th class="pr-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($emails)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <p class="text-muted mb-0">No upcoming reminders! Relax.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($emails as $index => $email): ?>
                            <tr>
                                <td class="pl-4 align-middle"><?php echo $index + 1; ?></td>
                                <td class="align-middle font-weight-bold"><?php echo htmlspecialchars($email['subject']); ?>
                                </td>
                                <td class="align-middle small text-muted"><?php echo date('M d, Y', $email['timestamp']); ?>
                                </td>
                                <td class="align-middle">
                                    <div class="text-primary font-weight-bold">
                                        <?php echo date('M d, H:i', $email['actiontimestamp']); ?>
                                    </div>
                                    <small
                                        class="text-muted"><?php echo Utils::time_elapsed_string('@' . $email['actiontimestamp']); ?></small>
                                </td>
                                <td class="pr-4 align-middle">
                                    <?php
                                    $releaseUrl = Utils::getActionUrl($email['ID'], $email['message_id'], 's', "today.midnight", $email['sslkey']);
                                    $cancelUrl = Utils::getActionUrl($email['ID'], $email['message_id'], 'c', "00", $email['sslkey']);
                                    ?>
                                    <a href="<?php echo $releaseUrl; ?>"
                                        class="btn btn-sm btn-success rounded-pill px-3 mr-2">Release</a>
                                    <a href="<?php echo $cancelUrl; ?>"
                                        class="btn btn-sm btn-outline-danger rounded-pill px-3">Cancel</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
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
    </script>
</body>

</html>