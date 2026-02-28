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
$view        = in_array($_GET['view'] ?? '', ['upcoming', 'history', 'related']) ? $_GET['view'] : 'upcoming';

$emailRepo = new EmailRepository();

// ── Related topics: load all upcoming, cluster by shared keywords ──────────
$relatedGroups = [];
if ($view === 'related') {
    $allUpcoming = $emailRepo->getUpcomingForUser($currentUserEmail);

    // Stop-words to ignore when extracting keywords
    $stopWords = ['re', 'fwd', 'fw', 'the', 'and', 'for', 'with', 'from', 'this',
                  'that', 'have', 'are', 'was', 'will', 'your', 'you', 'our', 'its',
                  'can', 'not', 'but', 'had', 'has', 'how', 'all', 'any', 'been',
                  'per', 'via'];

    // Extract keywords per email (words ≥4 chars, not stop-words)
    $emailKeywords = [];
    foreach ($allUpcoming as $em) {
        $clean = preg_replace('/[^a-z0-9\s]/i', ' ', mb_strtolower($em['subject']));
        $words = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        $keys  = array_unique(array_filter($words, function ($w) use ($stopWords) { return strlen($w) >= 4 && !in_array($w, $stopWords); }));
        $emailKeywords[$em['ID']] = array_values($keys);
    }

    // Group: assign each email to clusters sharing a keyword
    $assigned = [];
    $groups   = [];
    foreach ($allUpcoming as $em) {
        if (isset($assigned[$em['ID']])) continue;
        $group = [$em];
        $assigned[$em['ID']] = true;
        foreach ($allUpcoming as $other) {
            if ($other['ID'] === $em['ID'] || isset($assigned[$other['ID']])) continue;
            $shared = array_intersect($emailKeywords[$em['ID']], $emailKeywords[$other['ID']]);
            if (!empty($shared)) {
                $group[] = $other;
                $assigned[$other['ID']] = true;
            }
        }
        $groups[] = $group;
    }
    // Sort: multi-email groups first, then singles
    usort($groups, function ($a, $b) { return count($b) - count($a); });
    $relatedGroups = $groups;
}

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
        .action-btn.note:hover       { background: rgba(52,152,219,0.15); border-color: #3498db; color: #3498db; }
        .action-btn.note.has-note    { border-color: #3498db; color: #3498db; }
        .action-btn:disabled         { opacity: 0.4; cursor: not-allowed; pointer-events: none; }

        /* ── Toast ───────────────────────────────────────────── */
        .snoozer-toast {
            position: fixed; bottom: 24px; right: 24px; z-index: 9999;
            display: flex; align-items: center; gap: 10px;
            background: var(--glass-bg); backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border); border-radius: 12px;
            padding: 12px 20px; box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            font-size: 0.875rem; font-weight: 600; min-width: 220px;
            opacity: 0; transform: translateY(16px);
            transition: opacity 0.25s, transform 0.25s;
            pointer-events: none;
        }
        .snoozer-toast.show          { opacity: 1; transform: translateY(0); pointer-events: auto; }
        .snoozer-toast.toast-success { border-color: #27ae60; }
        .snoozer-toast.toast-error   { border-color: #e74c3c; }
        .toast-icon                  { font-size: 1.1rem; flex-shrink: 0; }

        /* ── Related Topics ─────────────────────────────────── */
        .related-group { border-radius: 14px; }
        .related-cluster { background: rgba(125,60,152,0.05); border: 1px solid rgba(125,60,152,0.15); padding: 10px; }
        .related-group-label {
            font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 1px; color: var(--primary-purple-light);
            padding: 0 4px 8px; opacity: 0.8;
        }
        .related-card {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; padding: 10px 14px; margin-bottom: 6px; border-radius: 10px;
        }
        .related-card:last-child { margin-bottom: 0; }
        .related-card-body { flex: 1; min-width: 0; }
        .related-subject {
            font-weight: 600; font-size: 0.875rem;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .related-meta { margin-top: 4px; }
        .related-card-actions { display: flex; gap: 5px; flex-shrink: 0; }

        /* ── Responsive ──────────────────────────────────────── */
        .table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        @media (max-width: 767px) {
            .navbar-toggler { border-color: var(--glass-border); padding: 4px 8px; }
            .navbar-toggler-icon {
                background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3e%3cpath stroke='rgba(200,200,200,0.8)' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
            }
            .stat-value { font-size: 1.6rem; }
            .stat-card  { padding: 14px 14px 0; }
            .subject-cell { max-width: 160px; }
            .dash-toolbar { flex-direction: column; align-items: stretch; gap: 8px; }
            .dash-toolbar .nav-pills { justify-content: center; }
            .dash-toolbar form { width: 100%; }
            .dash-toolbar .input-group { min-width: 100% !important; }
            .dash-header { flex-direction: column; gap: 10px; }
            .snoozer-toast { left: 16px; right: 16px; bottom: 16px; min-width: 0; }
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg mb-0 shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand font-weight-bold" href="dashboard.php" style="letter-spacing: 2px;">SNOOZER</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse"
                    aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarCollapse">
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
                <li class="nav-item">
                    <a class="nav-link <?php echo $view === 'related' ? 'active' : ''; ?> rounded-pill"
                        href="dashboard.php?view=related">
                        Related
                    </a>
                </li>
            </ul>
            <div class="d-flex align-items-center" style="gap:8px;">
                <form method="GET" class="d-flex align-items-center mb-0" style="gap:8px;">
                    <?php if ($view === 'history'): ?><input type="hidden" name="view" value="history"><?php endif; ?>
                    <div class="input-group input-group-sm" style="min-width:220px;">
                        <input type="text" id="searchInput" name="q" class="form-control px-3"
                            placeholder="Search subjects…"
                            value="<?php echo htmlspecialchars($searchQuery); ?>"
                            style="border-radius:20px; border-right:none;"
                            autocomplete="off">
                        <?php if ($searchQuery): ?>
                        <div class="input-group-append">
                            <a href="dashboard.php<?php echo $view === 'history' ? '?view=history' : ''; ?>" class="btn btn-outline-secondary" style="border-radius:0 20px 20px 0;" title="Clear">&#x2715;</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
                <?php if ($searchQuery): ?>
                    <span class="text-muted small"><?php echo $totalEmails; ?> result<?php echo $totalEmails !== 1 ? 's' : ''; ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Table -->
        <div class="glass-panel p-0 overflow-hidden">
        <div class="table-scroll">
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
                            $isOverdue    = $email['actiontimestamp'] < $now;
                            $actionDate   = date('Y-m-d', $email['actiontimestamp']);
                            $todayDate    = date('Y-m-d', $now);
                            $tomorrowDate = date('Y-m-d', strtotime('+1 day', $now));
                            $endOfWeek    = date('Y-m-d', strtotime('next sunday', $now));
                            if ($isOverdue) {
                                $urgencyClass = 'badge-overdue';
                                $urgencyLabel = 'Overdue';
                            } elseif ($actionDate === $todayDate) {
                                $urgencyClass = 'badge-today';
                                $urgencyLabel = 'Today';
                            } elseif ($actionDate === $tomorrowDate) {
                                $urgencyClass = 'badge-soon';
                                $urgencyLabel = 'Tomorrow';
                            } elseif ($actionDate <= $endOfWeek) {
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
                                        <button class="action-btn note<?php echo !empty($email['notes']) ? ' has-note' : ''; ?>" title="<?php echo !empty($email['notes']) ? 'Edit note' : 'Add note'; ?>"
                                                data-id="<?php echo $email['ID']; ?>"
                                                data-notes="<?php echo htmlspecialchars($email['notes'] ?? '', ENT_QUOTES); ?>"
                                                onclick="openNoteModal(this)">
                                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                        </button>
                                        <a href="<?php echo $releaseUrl; ?>" class="action-btn release" title="Release now">
                                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                                        </a>
                                        <button class="action-btn reschedule" title="Reschedule"
                                            onclick="openRescheduleModal(<?php echo $email['ID']; ?>, <?php echo $email['actiontimestamp']; ?>)">
                                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
                                        </button>
                                        <button class="action-btn cancel" title="Cancel reminder"
                                                data-id="<?php echo $email['ID']; ?>"
                                                data-subject="<?php echo htmlspecialchars($email['subject'], ENT_QUOTES); ?>"
                                                onclick="cancelReminder(this)">
                                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                                        </button>
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
            <?php elseif ($view === 'related'): ?>
            <?php if (empty($relatedGroups)): ?>
                <div class="empty-state">
                    <svg width="56" height="56" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    <p>No upcoming reminders to group.</p>
                </div>
            <?php else: ?>
                <div class="p-3">
                <?php foreach ($relatedGroups as $gi => $group): ?>
                    <?php $isCluster = count($group) > 1; ?>
                    <div class="related-group <?php echo $isCluster ? 'related-cluster' : ''; ?> mb-3">
                        <?php if ($isCluster): ?>
                            <div class="related-group-label">
                                <?php echo count($group); ?> related reminders
                            </div>
                        <?php endif; ?>
                        <?php foreach ($group as $em): ?>
                            <?php
                            $emOverdue  = $em['actiontimestamp'] < $now;
                            $emActDate  = date('Y-m-d', $em['actiontimestamp']);
                            $emToday    = $emActDate === date('Y-m-d', $now);
                            $emTomorrow = $emActDate === date('Y-m-d', strtotime('+1 day', $now));
                            if ($emOverdue)        { $emClass = 'badge-overdue'; $emLabel = 'Overdue'; }
                            elseif ($emToday)      { $emClass = 'badge-today';   $emLabel = 'Today'; }
                            elseif ($emTomorrow)   { $emClass = 'badge-soon';    $emLabel = 'Tomorrow'; }
                            else                   { $emClass = 'badge-later';   $emLabel = date('M d', $em['actiontimestamp']); }
                            $emRelease = Utils::getActionUrl($em['ID'], $em['message_id'], 's', "today.midnight", $em['sslkey']);
                            ?>
                            <div class="related-card glass-panel">
                                <div class="related-card-body">
                                    <div class="related-subject" title="<?php echo htmlspecialchars($em['subject']); ?>">
                                        <?php echo htmlspecialchars($em['subject']); ?>
                                        <?php if (!empty($em['notes'])): ?>
                                            <span title="Has note" style="color:#3498db;margin-left:4px;">&#9998;</span>
                                        <?php endif; ?>
                                        <?php if (!empty($em['recurrence'])): ?>
                                            <span class="badge badge-secondary" style="font-size:0.6rem;margin-left:4px;">&#8635; <?php echo htmlspecialchars($em['recurrence']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="related-meta">
                                        <span class="badge-urgency <?php echo $emClass; ?>"><?php echo $emLabel; ?></span>
                                        <span class="text-muted" style="font-size:0.75rem;"> <?php echo date('H:i', $em['actiontimestamp']); ?></span>
                                    </div>
                                </div>
                                <div class="related-card-actions">
                                    <button class="action-btn note<?php echo !empty($em['notes']) ? ' has-note' : ''; ?>" title="Note"
                                            data-id="<?php echo $em['ID']; ?>"
                                            data-notes="<?php echo htmlspecialchars($em['notes'] ?? '', ENT_QUOTES); ?>"
                                            onclick="openNoteModal(this)">
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                    </button>
                                    <button class="action-btn cancel" title="Cancel"
                                            data-id="<?php echo $em['ID']; ?>"
                                            data-subject="<?php echo htmlspecialchars($em['subject'], ENT_QUOTES); ?>"
                                            onclick="cancelReminder(this)">
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php endif; ?>
        </div><!-- /.table-scroll -->
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

    <!-- Note Modal -->
    <div class="modal fade" id="noteModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content glass-panel" style="border-radius:20px;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title font-weight-bold">Note</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body p-4">
                    <div id="noteError" class="alert alert-danger d-none"></div>
                    <input type="hidden" id="noteReminderId">
                    <div class="form-group mb-0">
                        <label class="font-weight-bold small text-uppercase" style="letter-spacing:0.8px;font-size:0.7rem;">
                            Note <span class="text-muted font-weight-normal">(included in reminder email)</span>
                        </label>
                        <textarea id="noteText" class="form-control" rows="5"
                            placeholder="Add a note to this reminder…"
                            style="border-radius:12px; resize:vertical;"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-link text-muted" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-link text-danger mr-auto" onclick="clearNote()">Clear note</button>
                    <button type="button" class="btn btn-premium px-4" onclick="saveNote()">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="snoozerToast" class="snoozer-toast" role="alert" aria-live="polite">
        <span class="toast-icon" id="toastIcon">✓</span>
        <span id="toastMsg"></span>
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

        // ── Toast ────────────────────────────────────────────────────
        let toastTimer;
        function showToast(msg, type = 'success') {
            const toast = document.getElementById('snoozerToast');
            document.getElementById('toastMsg').textContent  = msg;
            document.getElementById('toastIcon').textContent = type === 'success' ? '✓' : '✕';
            toast.className = 'snoozer-toast toast-' + type + ' show';
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => toast.classList.remove('show'), 3500);
        }

        // ── Cancel reminder ──────────────────────────────────────────
        async function cancelReminder(btn) {
            const id      = parseInt(btn.dataset.id, 10);
            const subject = btn.dataset.subject;
            if (!confirm('Cancel this reminder?\n\n"' + subject + '"')) return;

            btn.disabled = true;
            try {
                const res  = await fetch('api/cancel_reminder.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body:    JSON.stringify({ id })
                });
                let data = {};
                try { data = await res.json(); } catch (_) {}
                if (!res.ok) throw new Error(data.error || 'Failed to cancel. Please refresh the page.');

                // Fade out and remove the row (table row or related card)
                const row = btn.closest('tr') || btn.closest('.related-card');
                row.style.transition = 'opacity 0.3s, transform 0.3s';
                row.style.opacity    = '0';
                row.style.transform  = 'translateX(20px)';
                setTimeout(() => row.remove(), 320);

                showToast('Reminder cancelled.', 'success');
            } catch (err) {
                btn.disabled = false;
                showToast(err.message, 'error');
            }
        }

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

        // ── Note modal ───────────────────────────────────────────────
        function openNoteModal(btn) {
            document.getElementById('noteReminderId').value = btn.dataset.id;
            document.getElementById('noteText').value       = btn.dataset.notes || '';
            document.getElementById('noteError').classList.add('d-none');
            $('#noteModal').modal('show');
        }

        async function saveNote() {
            const id    = parseInt(document.getElementById('noteReminderId').value, 10);
            const notes = document.getElementById('noteText').value.trim();
            await submitNote(id, notes);
        }

        async function clearNote() {
            const id = parseInt(document.getElementById('noteReminderId').value, 10);
            await submitNote(id, '');
        }

        async function submitNote(id, notes) {
            const errEl = document.getElementById('noteError');
            const res   = await fetch('api/update_note.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body:    JSON.stringify({ id, notes })
            });
            let data = {};
            try { data = await res.json(); } catch (_) {}
            if (!res.ok) {
                errEl.textContent = data.error || 'Failed to save note.';
                errEl.classList.remove('d-none');
                return;
            }
            $('#noteModal').modal('hide');
            // Update the button state in-place
            const btn = document.querySelector('.action-btn.note[data-id="' + id + '"]');
            if (btn) {
                btn.dataset.notes = notes;
                btn.classList.toggle('has-note', notes.length > 0);
                btn.title = notes.length > 0 ? 'Edit note' : 'Add note';
            }
            showToast(notes ? 'Note saved.' : 'Note cleared.', 'success');
        }

        // ── Auto timezone detect (first login only) ──────────────────
        <?php if (empty($user['timezone']) || $user['timezone'] === 'UTC'): ?>
        (function () {
            try {
                const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                if (!tz || tz === 'UTC') return;
                fetch('api/auto_set_timezone.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body:    JSON.stringify({ timezone: tz })
                }).then(r => r.json()).then(d => { if (d.updated) location.reload(); });
            } catch (_) {}
        })();
        <?php endif; ?>

        // ── Instant search ───────────────────────────────────────────
        (function () {
            const input = document.getElementById('searchInput');
            if (!input) return;
            let timer;
            const form = input.closest('form');
            input.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () { form.submit(); }, 350);
            });
        })();

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
