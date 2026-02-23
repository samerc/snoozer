<?php
require_once 'src/Session.php';
require_once 'src/User.php';
require_once 'src/AuditLog.php';
require_once 'src/Utils.php';

Session::start();
Session::requireAdmin();

$userRepo  = new User();
$adminUser = $userRepo->findByEmail($_SESSION['user_email']);

if (!$adminUser) {
    die("Admin session invalid.");
}

if (!empty($adminUser['timezone'])) {
    date_default_timezone_set($adminUser['timezone']);
    $_SESSION['user_timezone'] = $adminUser['timezone'];
}
$_SESSION['user_theme'] = $adminUser['theme'] ?? 'dark';

$auditLog = new AuditLog();

$perPage     = 30;
$page        = max(1, (int) ($_GET['page'] ?? 1));
$filterAction = trim($_GET['action'] ?? '');
$filterEmail  = trim($_GET['email'] ?? '');

$filters = [];
if ($filterAction !== '') $filters['action']      = $filterAction;
if ($filterEmail  !== '') $filters['actor_email'] = $filterEmail;

$total     = $auditLog->countLogs($filters);
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$logs = $auditLog->getLogs($filters, $perPage, $offset);

$allActions = [
    AuditLog::USER_CREATED,
    AuditLog::USER_UPDATED,
    AuditLog::USER_DELETED,
    AuditLog::PASSWORD_RESET,
    AuditLog::PASSWORD_CHANGED,
    AuditLog::TEMPLATE_UPDATED,
    AuditLog::LOGIN_SUCCESS,
    AuditLog::LOGIN_FAILED,
    AuditLog::SETTINGS_CHANGED,
    AuditLog::REMINDER_SNOOZED,
    AuditLog::REMINDER_CANCELLED,
];

$actionColors = [
    'user_created'       => 'success',
    'user_updated'       => 'primary',
    'user_deleted'       => 'danger',
    'password_reset'     => 'warning',
    'password_changed'   => 'warning',
    'template_updated'   => 'info',
    'login_success'      => 'secondary',
    'login_failed'       => 'danger',
    'settings_changed'   => 'primary',
    'reminder_snoozed'   => 'info',
    'reminder_cancelled' => 'secondary',
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $_SESSION['user_theme']; ?>">

<head>
    <meta charset="UTF-8">
    <?php echo Utils::csrfMeta(); ?>
    <title>Audit Log - Snoozer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.6/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/js/bootstrap.min.js"></script>
</head>

<body>
    <nav class="navbar navbar-expand-lg mb-4 shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand font-weight-bold" href="dashboard.php" style="letter-spacing: 2px;">SNOOZER</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="kanban.php">Kanban</a></li>
                    <li class="nav-item"><a class="nav-link" href="settings.php">Settings</a></li>
                    <li class="nav-item"><a class="nav-link" href="users.php">Users</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin_templates.php">Templates</a></li>
                    <li class="nav-item active"><a class="nav-link" href="admin_audit.php">Audit Log</a></li>
                </ul>
                <div class="theme-switch-wrapper mr-4">
                    <label class="theme-switch" for="checkbox">
                        <input type="checkbox" id="checkbox" <?php echo $_SESSION['user_theme'] === 'light' ? 'checked' : ''; ?> />
                        <div class="slider round"></div>
                    </label>
                    <span class="ml-2 small"><?php echo $_SESSION['user_theme'] === 'light' ? 'Light' : 'Dark'; ?></span>
                </div>
                <span class="navbar-text mr-3 small">
                    Local Time: <strong><?php echo date('H:i'); ?></strong> (<?php echo $_SESSION['user_timezone']; ?>)
                </span>
                <span class="navbar-text mr-3 small">Welcome, <strong><?php echo htmlspecialchars($adminUser['name']); ?></strong></span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-4">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 font-weight-bold">Audit Log</h1>
            <span class="badge badge-secondary px-3 py-2 rounded-pill"><?php echo $total; ?> entries</span>
        </div>

        <!-- Filters -->
        <form method="GET" class="glass-panel p-3 mb-4 d-flex align-items-end" style="gap:12px; flex-wrap:wrap;">
            <div>
                <label class="small font-weight-bold d-block mb-1">Action</label>
                <select name="action" class="form-control form-control-sm rounded-pill px-3" style="min-width:180px;">
                    <option value="">All actions</option>
                    <?php foreach ($allActions as $a): ?>
                        <option value="<?php echo $a; ?>" <?php echo $filterAction === $a ? 'selected' : ''; ?>>
                            <?php echo str_replace('_', ' ', ucfirst($a)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="small font-weight-bold d-block mb-1">Actor email</label>
                <input type="text" name="email" class="form-control form-control-sm rounded-pill px-3"
                    placeholder="user@example.com" value="<?php echo htmlspecialchars($filterEmail); ?>" style="min-width:220px;">
            </div>
            <button type="submit" class="btn btn-premium btn-sm rounded-pill px-4">Filter</button>
            <?php if ($filterAction || $filterEmail): ?>
                <a href="admin_audit.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">Clear</a>
            <?php endif; ?>
        </form>

        <!-- Log Table -->
        <div class="glass-panel p-0 overflow-hidden">
            <table class="table table-borderless table-hover mb-0" style="font-size:0.875rem;">
                <thead class="bg-dark text-white">
                    <tr>
                        <th class="pl-4" style="width:160px;">Date / Time</th>
                        <th style="width:160px;">Action</th>
                        <th>Actor</th>
                        <th style="width:140px;">Target</th>
                        <th>Details</th>
                        <th class="pr-4" style="width:120px;">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No audit log entries found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $color   = $actionColors[$log['action']] ?? 'secondary';
                            $details = $log['details'] ? json_decode($log['details'], true) : [];
                            $detailStr = '';
                            if (is_array($details)) {
                                $parts = [];
                                foreach ($details as $k => $v) {
                                    if (is_bool($v)) {
                                        $parts[] = $k . ': ' . ($v ? 'yes' : 'no');
                                    } elseif (!is_array($v)) {
                                        $parts[] = $k . ': ' . $v;
                                    }
                                }
                                $detailStr = implode(' · ', $parts);
                            }
                            ?>
                            <tr>
                                <td class="pl-4 align-middle text-muted small"><?php echo htmlspecialchars($log['created_at']); ?></td>
                                <td class="align-middle">
                                    <span class="badge badge-<?php echo $color; ?> rounded-pill px-2">
                                        <?php echo htmlspecialchars(str_replace('_', ' ', $log['action'])); ?>
                                    </span>
                                </td>
                                <td class="align-middle"><?php echo htmlspecialchars($log['actor_email'] ?? '—'); ?></td>
                                <td class="align-middle text-muted small">
                                    <?php if ($log['target_type']): ?>
                                        <?php echo htmlspecialchars($log['target_type']); ?>
                                        <?php echo $log['target_id'] ? '#' . $log['target_id'] : ''; ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="align-middle text-muted small"><?php echo htmlspecialchars($detailStr ?: '—'); ?></td>
                                <td class="pr-4 align-middle text-muted small"><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1):
            $qParam = http_build_query(array_filter(['action' => $filterAction, 'email' => $filterEmail]));
            $qParam = $qParam ? '&' . $qParam : '';
        ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1 . $qParam; ?>">&laquo;</a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i . $qParam; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1 . $qParam; ?>">&raquo;</a>
                </li>
            </ul>
            <p class="text-center text-muted small">
                Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $total); ?> of <?php echo $total; ?> entries
            </p>
        </nav>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
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
            if (toggleSwitch) toggleSwitch.addEventListener('change', switchTheme, false);
        });
    </script>
</body>
</html>
