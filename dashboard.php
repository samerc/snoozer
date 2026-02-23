<?php
require_once 'src/Session.php';
require_once 'src/User.php';
require_once 'src/EmailRepository.php';
require_once 'src/Utils.php';

Session::start();
Session::requireAuth();

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

// Pagination settings
$perPage     = 20;
$page        = max(1, intval($_GET['page'] ?? 1));
$searchQuery = trim($_GET['q'] ?? '');
$view        = in_array($_GET['view'] ?? '', ['upcoming', 'history']) ? $_GET['view'] : 'upcoming';

$emailRepo = new EmailRepository();

if ($view === 'history') {
    $totalEmails = $emailRepo->countHistoryForUser($currentUserEmail, $searchQuery ?: null);
    $totalPages  = max(1, ceil($totalEmails / $perPage));
    $page        = min($page, $totalPages);
    $offset      = ($page - 1) * $perPage;
    $emails      = $emailRepo->getHistoryForUser($currentUserEmail, $perPage, $offset, $searchQuery ?: null);
} else {
    $totalEmails = $emailRepo->countUpcomingForUser($currentUserEmail, $searchQuery ?: null);
    $totalPages  = max(1, ceil($totalEmails / $perPage));
    $page        = min($page, $totalPages);
    $offset      = ($page - 1) * $perPage;
    $emails      = $emailRepo->getUpcomingForUser($currentUserEmail, $perPage, $offset, $searchQuery ?: null);
}

$statToday   = $emailRepo->countDueTodayForUser($currentUserEmail);
$statWeek    = $emailRepo->countDueThisWeekForUser($currentUserEmail);
$statOverdue = $emailRepo->countOverdueForUser($currentUserEmail);

// Cron health (admin only)
$cronLastRun = null;
$cronWarning = false;
if (($_SESSION['user_role'] ?? 'user') === 'admin') {
    require_once 'src/Database.php';
    $db      = Database::getInstance();
    $cronRow = $db->fetchAll("SELECT value FROM system_settings WHERE `key` = 'last_cron_run' LIMIT 1");
    if (!empty($cronRow)) {
        $cronLastRun = $cronRow[0]['value'];
        $cronWarning = (time() - strtotime($cronLastRun)) > 300;
    } else {
        $cronWarning = true;
    }
}

$now      = time();
$hour     = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $_SESSION['user_theme']; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo Utils::csrfMeta(); ?>
    <title>Snoozer Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        /* ── Stat Cards ─────────────────────────────────────── */
        .stat-card {
            border-radius: 16px;
            padding: 20px 22px 0;
            overflow: hidden;
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 14px;
        }
        .stat-icon svg { width: 20px; height: 20px; }
        .stat-value {
            font-size: 2rem; font-weight: 800;
            line-height: 1; margin-bottom: 4px; letter-spacing: -1px;
        }
        .stat-label {
            font-size: 0.72rem; font-weight: 600;
            text-transform: uppercase; letter-spacing: 1px;
            color: var(--text-muted); margin-bottom: 18px;
        }
        .stat-bar { height: 3px; margin: 0 -22px; opacity: 0.6; }

        /* ── Table ──────────────────────────────────────────── */
        .dash-table thead th {
            font-size: 0.68rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1.2px;
            color: var(--text-muted);
            border-bottom: 1px solid var(--glass-border) !important;
            border-top: none !important;
            padding: 14px 12px; background: transparent; white-space: nowrap;
        }
        .dash-table tbody td {
            padding: 14px 12px; vertical-align: middle;
            border-top: 1px solid var(--glass-border) !important;
            font-size: 0.875rem;
        }
        .dash-table tbody tr:hover td { background: var(--card-hover-bg); }
        .dash-table .subject-cell {
            max-width: 320px; overflow: hidden;
            text-overflow: ellipsis; white-space: nowrap; font-weight: 600;
        }
        .dash-table tr.row-overdue > td:first-child {
            border-left: 3px solid #e74c3c; padding-left: 9px;
        }

        /* ── Urgency Badges ─────────────────────────────────── */
        .badge-urgency {
            display: inline-block; font-size: 0.65rem; font-weight: 700;
            padding: 3px 9px; border-radius: 20px; letter-spacing: 0.4px;
        }
        .badge-overdue { background: rgba(231,76,60,0.15);   color: #e74c3c; }
        .badge-today   { background: rgba(52,152,219,0.15);  color: #3498db; }
        .badge-soon    { background: rgba(46,204,113,0.15);  color: #27ae60; }
        .badge-later   { background: rgba(149,165,166,0.15); color: #7f8c8d; }

        /* ── Toolbar ─────────────────────────────────────────── */
        .dash-toolbar {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; flex-wrap: wrap; padding: 12px 16px; margin-bottom: 16px;
        }
        .dash-toolbar .nav-pills .nav-link {
            font-size: 0.8rem; font-weight: 600; padding: 6px 18px;
        }

        /* ── Page heading ────────────────────────────────────── */
        .dash-header { padding: 8px 0 20px; }
        .dash-header h4 { font-weight: 700; font-size: 1.3rem; margin-bottom: 2px; }
        .dash-header .sub { font-size: 0.78rem; color: var(--text-muted); }

        /* ── Empty state ─────────────────────────────────────── */
        .empty-state { padding: 60px 20px; text-align: center; }
        .empty-state svg { opacity: 0.25; margin-bottom: 16px; display: block; margin-left: auto; margin-right: auto; }
        .empty-state p { color: var(--text-muted); font-size: 0.9rem; margin: 0; }

        /* ── Cron status ─────────────────────────────────────── */
        .cron-badge {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 0.72rem; font-weight: 600;
            padding: 5px 14px; border-radius: 20px; margin-bottom: 18px;
        }
        .cron-badge.ok  { background: rgba(46,204,113,0.12); color: #27ae60; border: 1px solid rgba(46,204,113,0.3); }
        .cron-badge.bad { background: rgba(231,76,60,0.12);  color: #e74c3c; border: 1px solid rgba(231,76,60,0.3); }
        .cron-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
        .cron-badge.ok  .cron-dot { background: #27ae60; }
        .cron-badge.bad .cron-dot { background: #e74c3c; animation: blink 1.2s infinite; }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }

        /* ── Icon Action Buttons ──────────────────────────────── */
        .action-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border-radius: 8px;
            border: 1px solid var(--glass-border); background: transparent;
            color: var(--text-muted); cursor: pointer;
            transition: all 0.15s; text-decoration: none;
        }
        .action-btn svg { width: 14px; height: 14px; }
        .action-btn:hover { color: var(--text-main); border-color: var(--primary-purple); }
        .action-btn.release:hover    { background: rgba(46,204,113,0.15); border-color: #27ae60; color: #27ae60; }
        .action-btn.reschedule:hover { background: rgba(125,60,152,0.15); border-color: var(--primary-purple); color: var(--primary-purple-light); }
        .action-btn.cancel:hover     { background: rgba(231,76,60,0.15);  border-color: #e74c3c; color: #e74c3c; }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg mb-0 shadow-sm">
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
                        <li class="nav-item"><a class="nav-link" href="admin_audit.php">Audit Log</a></li>
                    <?php endif; ?>
                </ul>
                <div class="theme-switch-wrapper mr-4">
                    <label class="theme-switch" for="checkbox">
                        <input type="checkbox" id="checkbox" <?php echo $_SESSION['user_theme'] === 'light' ? 'checked' : ''; ?> />
                        <div class="slider round"></div>
                    </label>
                    <span class="ml-2 small"><?php echo $_SESSION['user_theme'] === 'light' ? 'Light' : 'Dark'; ?></span>
                </div>
                <span class="navbar-text mr-3 small">
                    <strong><?php echo date('H:i'); ?></strong>
                    <span class="text-muted">&nbsp;<?php echo $_SESSION['user_timezone']; ?></span>
                </span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">

        <!-- Page heading -->
        <div class="dash-header d-flex justify-content-between align-items-start">
            <div>
                <h4><?php echo $greeting; ?>, <?php echo htmlspecialchars($user['name']); ?></h4>
                <span class="sub"><?php echo date('l, F j, Y'); ?> &mdash; <?php echo $totalEmails; ?> reminder<?php echo $totalEmails !== 1 ? 's' : ''; ?> pending</span>
            </div>
            <?php if ($view === 'upcoming'): ?>
                <button class="btn btn-premium btn-sm px-4" onclick="openCreateModal()">+ New Reminder</button>
            <?php endif; ?>
        </div>

        <!-- Cron status (admin) -->
        <?php if (($_SESSION['user_role'] ?? 'user') === 'admin'): ?>
            <?php if ($cronWarning): ?>
                <div class="cron-badge bad">
                    <span class="cron-dot"></span>
                    Cron warning &mdash;
                    <?php echo $cronLastRun
                        ? 'Last run: ' . htmlspecialchars($cronLastRun) . '. More than 5 minutes ago.'
                        : 'Cron has never run or system_settings table is missing.'; ?>
                    Run <code>php cron.php</code>
                </div>
            <?php else: ?>
                <div class="cron-badge ok">
                    <span class="cron-dot"></span>
                    Cron healthy &mdash; last run <?php echo htmlspecialchars($cronLastRun); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Stat cards -->
        <div class="row mb-4">
            <div class="col-6 col-md-3 mb-3 mb-md-0">
                <div class="glass-panel stat-card">
                    <div class="stat-icon" style="background:rgba(125,60,152,0.15);color:#9b59b6;">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
                    </div>
                    <div class="stat-value" style="color:var(--primary-purple-light);"><?php echo $totalEmails; ?></div>
                    <div class="stat-label">Total Pending</div>
                    <div class="stat-bar" style="background:var(--primary-purple);"></div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-3 mb-md-0">
                <div class="glass-panel stat-card">
                    <div class="stat-icon" style="background:rgba(52,152,219,0.15);color:#3498db;">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 3h-1V1h-2v2H7V1H5v2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H4V8h16v13z"/><path d="M9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm-8 4H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg>
                    </div>
                    <div class="stat-value" style="color:#3498db;"><?php echo $statToday; ?></div>
                    <div class="stat-label">Due Today</div>
                    <div class="stat-bar" style="background:#3498db;"></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="glass-panel stat-card">
                    <div class="stat-icon" style="background:rgba(26,188,156,0.15);color:#1abc9c;">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                    </div>
                    <div class="stat-value" style="color:#1abc9c;"><?php echo $statWeek; ?></div>
                    <div class="stat-label">Due This Week</div>
                    <div class="stat-bar" style="background:#1abc9c;"></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="glass-panel stat-card">
                    <?php $overdueColor = $statOverdue > 0 ? '#e74c3c' : '#27ae60'; ?>
                    <div class="stat-icon" style="background:<?php echo $statOverdue > 0 ? 'rgba(231,76,60,0.15)' : 'rgba(46,204,113,0.15)'; ?>;color:<?php echo $overdueColor; ?>;">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    </div>
                    <div class="stat-value" style="color:<?php echo $overdueColor; ?>;"><?php echo $statOverdue; ?></div>
                    <div class="stat-label">Overdue</div>
                    <div class="stat-bar" style="background:<?php echo $overdueColor; ?>;"></div>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="glass-panel dash-toolbar">
            <ul class="nav nav-pills mb-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo $view === 'upcoming' ? 'active' : ''; ?> rounded-pill"
                        href="dashboard.php<?php echo $searchQuery ? '?q=' . urlencode($searchQuery) : ''; ?>">
                        Upcoming
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $view === 'history' ? 'active' : ''; ?> rounded-pill"
                        href="dashboard.php?view=history<?php echo $searchQuery ? '&q=' . urlencode($searchQuery) : ''; ?>">
                        History
                    </a>
                </li>
            </ul>
            <div class="d-flex align-items-center" style="gap:8px;">
                <form method="GET" class="d-flex align-items-center mb-0" style="gap:8px;">
                    <?php if ($view === 'history'): ?><input type="hidden" name="view" value="history"><?php endif; ?>
                    <div class="input-group input-group-sm" style="min-width:220px;">
                        <input type="text" name="q" class="form-control px-3"
                            placeholder="Search subjects…"
                            value="<?php echo htmlspecialchars($searchQuery); ?>"
                            style="border-radius:20px 0 0 20px;">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="submit" style="border-radius:0 20px 20px 0;">Search</button>
                        </div>
                    </div>
                    <?php if ($searchQuery): ?>
                        <a href="dashboard.php<?php echo $view === 'history' ? '?view=history' : ''; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3" title="Clear filter">&#x2715;</a>
                    <?php endif; ?>
                </form>
                <?php if ($searchQuery): ?>
                    <span class="text-muted small"><?php echo $totalEmails; ?> result<?php echo $totalEmails !== 1 ? 's' : ''; ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Table -->
        <div class="glass-panel p-0 overflow-hidden">
            <?php if ($view === 'upcoming'): ?>
            <table class="table table-borderless table-hover mb-0 dash-table">
                <thead>
                    <tr>
                        <th class="pl-4" style="width:40px;">#</th>
                        <th>Subject</th>
                        <th>Received</th>
                        <th>Reminder</th>
                        <th class="pr-4 text-right" style="width:110px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($emails)): ?>
                        <tr><td colspan="5">
                            <div class="empty-state">
                                <svg width="56" height="56" viewBox="0 0 24 24" fill="currentColor"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
                                <p><?php echo $searchQuery ? 'No reminders match your search.' : 'No upcoming reminders — inbox zero achieved!'; ?></p>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($emails as $index => $email): ?>
                            <?php
                            $isOverdue = $email['actiontimestamp'] < $now;
                            $diff      = $email['actiontimestamp'] - $now;
                            if ($isOverdue) {
                                $urgencyClass = 'badge-overdue';
                                $urgencyLabel = 'Overdue';
                            } elseif ($diff < 86400) {
                                $urgencyClass = 'badge-today';
                                $urgencyLabel = 'Today';
                            } elseif ($diff < 604800) {
                                $urgencyClass = 'badge-soon';
                                $urgencyLabel = 'This week';
                            } else {
                                $urgencyClass = 'badge-later';
                                $urgencyLabel = 'Upcoming';
                            }
                            $releaseUrl = Utils::getActionUrl($email['ID'], $email['message_id'], 's', "today.midnight", $email['sslkey']);
                            $cancelUrl  = Utils::getActionUrl($email['ID'], $email['message_id'], 'c', "00", $email['sslkey']);
                            ?>
                            <tr class="<?php echo $isOverdue ? 'row-overdue' : ''; ?>">
                                <td class="pl-4 text-muted small"><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <div class="subject-cell" title="<?php echo htmlspecialchars($email['subject']); ?>">
                                        <?php echo htmlspecialchars($email['subject']); ?>
                                    </div>
                                    <?php if (!empty($email['recurrence'])): ?>
                                        <span class="badge badge-secondary" style="font-size:0.6rem;margin-top:3px;">&#8635; <?php echo htmlspecialchars($email['recurrence']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?php echo date('M d, Y', $email['timestamp']); ?></td>
                                <td>
                                    <div class="small font-weight-bold"><?php echo date('M d, H:i', $email['actiontimestamp']); ?></div>
                                    <div class="mt-1">
                                        <span class="badge-urgency <?php echo $urgencyClass; ?>"><?php echo $urgencyLabel; ?></span>
                                        <span class="text-muted" style="font-size:0.7rem;"> <?php echo Utils::time_elapsed_string('@' . $email['actiontimestamp']); ?></span>
                                    </div>
                                </td>
                                <td class="pr-4 text-right">
                                    <div class="d-flex justify-content-end" style="gap:5px;">
                                        <a href="<?php echo $releaseUrl; ?>" class="action-btn release" title="Release now">
                                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                                        </a>
                                        <button class="action-btn reschedule" title="Reschedule"
                                            onclick="openRescheduleModal(<?php echo $email['ID']; ?>, <?php echo $email['actiontimestamp']; ?>)">
                                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
                                        </button>
                                        <a href="<?php echo $cancelUrl; ?>" class="action-btn cancel" title="Cancel reminder">
                                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php else: // History view ?>
            <table class="table table-borderless table-hover mb-0 dash-table">
                <thead>
                    <tr>
                        <th class="pl-4" style="width:40px;">#</th>
                        <th>Subject</th>
                        <th>Received</th>
                        <th>Fired / Cancelled</th>
                        <th class="pr-4" style="width:110px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($emails)): ?>
                        <tr><td colspan="5">
                            <div class="empty-state">
                                <svg width="56" height="56" viewBox="0 0 24 24" fill="currentColor"><path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg>
                                <p><?php echo $searchQuery ? 'No history matches your search.' : 'No history yet — sent reminders will appear here.'; ?></p>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($emails as $index => $email): ?>
                            <?php $isCancelled = ((int) $email['processed']) === -2; ?>
                            <tr>
                                <td class="pl-4 text-muted small"><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <div class="subject-cell" title="<?php echo htmlspecialchars($email['subject']); ?>">
                                        <?php echo htmlspecialchars($email['subject']); ?>
                                    </div>
                                </td>
                                <td class="small text-muted"><?php echo date('M d, Y', $email['timestamp']); ?></td>
                                <td class="small text-muted"><?php echo date('M d, Y H:i', $email['actiontimestamp']); ?></td>
                                <td class="pr-4">
                                    <span class="badge-urgency <?php echo $isCancelled ? 'badge-later' : 'badge-soon'; ?>">
                                        <?php echo $isCancelled ? 'Cancelled' : 'Reminded'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1):
            $qParam = $searchQuery ? '&q=' . urlencode($searchQuery) : '';
            $vParam = $view === 'history' ? '&view=history' : '';
            $qParam = $vParam . $qParam;
        ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1 . $qParam; ?>">&laquo;</a>
                </li>
                <?php
                $startPage = max(1, $page - 2);
                $endPage   = min($totalPages, $page + 2);
                if ($startPage > 1): ?>
                    <li class="page-item"><a class="page-link" href="?page=1<?php echo $qParam; ?>">1</a></li>
                    <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i . $qParam; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <li class="page-item"><a class="page-link" href="?page=<?php echo $totalPages . $qParam; ?>"><?php echo $totalPages; ?></a></li>
                <?php endif; ?>
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1 . $qParam; ?>">&raquo;</a>
                </li>
            </ul>
            <p class="text-center small" style="color:var(--text-muted);">
                Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalEmails); ?> of <?php echo $totalEmails; ?>
            </p>
        </nav>
        <?php endif; ?>
    </div>

    <!-- Create Reminder Modal -->
    <div class="modal fade" id="createModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content glass-panel" style="border-radius:20px;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title font-weight-bold">New Reminder</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body p-4">
                    <div id="createError" class="alert alert-danger d-none"></div>
                    <div class="form-group">
                        <label class="font-weight-bold small text-uppercase" style="letter-spacing:0.8px;font-size:0.7rem;">Subject</label>
                        <input type="text" id="newSubject" class="form-control rounded-pill px-3" placeholder="What do you want to be reminded about?" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold small text-uppercase" style="letter-spacing:0.8px;font-size:0.7rem;">Remind me at</label>
                        <input type="datetime-local" id="newDatetime" class="form-control rounded-pill px-3" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-link text-muted" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-premium px-4" onclick="submitCreateReminder()">Create Reminder</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div class="modal fade" id="rescheduleModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content glass-panel" style="border-radius:20px;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title font-weight-bold">Reschedule</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body p-4">
                    <div id="rescheduleError" class="alert alert-danger d-none"></div>
                    <input type="hidden" id="rescheduleId">
                    <div class="form-group mb-0">
                        <label class="font-weight-bold small text-uppercase" style="letter-spacing:0.8px;font-size:0.7rem;">New reminder time</label>
                        <input type="datetime-local" id="rescheduleDatetime" class="form-control rounded-pill px-3" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-link text-muted" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-premium px-4" onclick="submitReschedule()">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.6/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/js/bootstrap.min.js"></script>
    <script>
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
        toggleSwitch.addEventListener('change', switchTheme, false);

        function openCreateModal() {
            document.getElementById('newSubject').value = '';
            document.getElementById('newDatetime').value = '';
            document.getElementById('createError').classList.add('d-none');
            $('#createModal').modal('show');
        }

        async function submitCreateReminder() {
            const subject     = document.getElementById('newSubject').value.trim();
            const datetimeVal = document.getElementById('newDatetime').value;
            const errEl       = document.getElementById('createError');

            if (!subject || !datetimeVal) {
                errEl.textContent = 'Please fill in all fields.';
                errEl.classList.remove('d-none');
                return;
            }

            const actiontimestamp = Math.floor(new Date(datetimeVal).getTime() / 1000);
            const res  = await fetch('api/create_reminder.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ subject, actiontimestamp })
            });
            const data = await res.json();

            if (!res.ok) {
                errEl.textContent = data.error || 'Failed to create reminder.';
                errEl.classList.remove('d-none');
                return;
            }

            $('#createModal').modal('hide');
            location.reload();
        }

        function openRescheduleModal(id, currentTs) {
            document.getElementById('rescheduleId').value = id;
            document.getElementById('rescheduleError').classList.add('d-none');
            const dt    = new Date(currentTs * 1000);
            const local = new Date(dt.getTime() - dt.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            document.getElementById('rescheduleDatetime').value = local;
            $('#rescheduleModal').modal('show');
        }

        async function submitReschedule() {
            const id          = document.getElementById('rescheduleId').value;
            const datetimeVal = document.getElementById('rescheduleDatetime').value;
            const errEl       = document.getElementById('rescheduleError');

            if (!datetimeVal) {
                errEl.textContent = 'Please select a new time.';
                errEl.classList.remove('d-none');
                return;
            }

            const actiontimestamp = Math.floor(new Date(datetimeVal).getTime() / 1000);
            const res  = await fetch('api/reschedule_reminder.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ id: parseInt(id), actiontimestamp })
            });
            const data = await res.json();

            if (!res.ok) {
                errEl.textContent = data.error || 'Failed to reschedule.';
                errEl.classList.remove('d-none');
                return;
            }

            $('#rescheduleModal').modal('hide');
            location.reload();
        }
    </script>
</body>

</html>
