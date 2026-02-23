<?php
require_once 'src/Session.php';
require_once 'src/Database.php';
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

// Load categories
$db = Database::getInstance();
$catRows = $db->fetchAll("SELECT ID, Name FROM emailCategory ORDER BY ID");
$categories = [];
foreach ($catRows as $cat) {
    $categories[(int)$cat['ID']] = $cat['Name'];
}
$catColors = [1 => '#e67e22', 2 => '#5dade2', 3 => '#2ecc71', 4 => '#95a5a6'];

$emailRepo = new EmailRepository();

$q = trim($_GET['q'] ?? '');
$totalCount = $emailRepo->countUpcomingForUser($currentUserEmail, $q ?: null);
$emails = $emailRepo->getUpcomingForUser($currentUserEmail, null, 0, $q ?: null);

$today = [];
$week  = [];
$later = [];

$now     = time();
$oneDay  = $now + (24 * 60 * 60);
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

function renderCard($email, $categories, $catColors) {
    $catId    = $email['catID'] !== null ? (int)$email['catID'] : null;
    $catName  = ($catId && isset($categories[$catId])) ? $categories[$catId] : null;
    $catColor = ($catId && isset($catColors[$catId]))  ? $catColors[$catId]  : null;
    $chipStyle = $catColor
        ? "background:{$catColor}22;color:{$catColor};border-color:{$catColor};"
        : "background:rgba(255,255,255,0.06);color:var(--text-muted);border-color:var(--glass-border);";
    $chipLabel = $catName ? htmlspecialchars($catName) : '+ Tag';
    $dataCat   = $catId !== null ? $catId : '';
    $ts        = $email['actiontimestamp'];
    global $now;
    ?>
    <div class="card" data-id="<?php echo $email['ID']; ?>" data-cat="<?php echo $dataCat; ?>">
        <div class="card-subject">
            <?php echo htmlspecialchars($email['subject']); ?>
            <?php if (!empty($email['recurrence'])): ?>
                <div class="mt-1">
                    <span class="badge badge-secondary" style="font-size:0.65rem;" title="Recurring">
                        &#8635; <?php echo htmlspecialchars($email['recurrence']); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer-row">
            <span class="cat-chip"
                  data-cat-id="<?php echo $dataCat; ?>"
                  style="<?php echo $chipStyle; ?>"
                  onclick="openCatPicker(this, <?php echo $email['ID']; ?>)">
                <?php echo $chipLabel; ?>
            </span>
            <div class="card-meta">
                <span class="badge-time">
                    <?php echo date($ts <= $now + 86400 ? 'H:i' : 'M d', $ts); ?>
                </span>
                <span><?php echo Utils::time_elapsed_string('@' . $ts); ?></span>
            </div>
        </div>
    </div>
    <?php
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
        .kanban-container { padding: 20px 20px 40px; }

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

        .card { padding: 16px 18px; margin-bottom: 14px; cursor: grab; }
        .card:active { cursor: grabbing; }

        .card-subject {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .card-footer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }

        .card-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.72rem;
            color: var(--text-muted);
            white-space: nowrap;
        }

        .badge-time {
            background: var(--primary-purple);
            color: #fff;
            padding: 3px 9px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.72rem;
        }

        /* Category chip */
        .cat-chip {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            border: 1.5px solid;
            font-size: 0.68rem;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: 0.4px;
            transition: opacity 0.15s;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .cat-chip:hover { opacity: 0.7; }

        /* Category filter bar */
        .cat-filter-bar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }

        .cat-filter-btn {
            padding: 4px 14px;
            border-radius: 20px;
            border: 1.5px solid var(--glass-border);
            background: transparent;
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .cat-filter-btn:hover {
            background: rgba(125,60,152,0.15);
            border-color: var(--primary-purple-light);
            color: var(--text-main);
        }
        .cat-filter-btn.active             { background: var(--primary-purple); border-color: var(--primary-purple); color: #fff; }
        .cat-filter-btn[data-filter="1"].active { background:#e67e22; border-color:#e67e22; color:#fff; }
        .cat-filter-btn[data-filter="2"].active { background:#5dade2; border-color:#5dade2; color:#fff; }
        .cat-filter-btn[data-filter="3"].active { background:#2ecc71; border-color:#2ecc71; color:#fff; }
        .cat-filter-btn[data-filter="4"].active { background:#95a5a6; border-color:#95a5a6; color:#fff; }
        .cat-filter-btn[data-filter="untagged"].active { background:#555; border-color:#555; color:#fff; }

        /* Floating category picker */
        #catPicker {
            position: fixed;
            z-index: 9999;
            background: var(--glass-bg, rgba(20,15,40,0.95));
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            padding: 8px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.4);
            min-width: 145px;
            display: none;
        }
        .cat-picker-item {
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s;
        }
        .cat-picker-item:hover { background: rgba(255,255,255,0.1); }

        .sortable-ghost { opacity: 0.1; transform: scale(0.9); }

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
            from { transform: translateY(100px); opacity: 0; }
            to   { transform: translateY(0);     opacity: 1; }
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
                    Local Time: <strong><?php echo date('H:i'); ?></strong> (<?php echo $_SESSION['user_timezone']; ?>)
                </span>
                <span class="navbar-text mr-3 small">Welcome,
                    <strong><?php echo htmlspecialchars($user['name']); ?></strong></span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-4">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Toolbar: search + category filter -->
    <div class="container-fluid px-4 mb-2">
        <div class="d-flex align-items-center flex-wrap" style="gap:10px;">
            <form method="GET" class="d-flex align-items-center" style="gap:6px; margin:0;">
                <input type="text" name="q" class="form-control rounded-pill px-3"
                    placeholder="Filter by subject..."
                    value="<?php echo htmlspecialchars($q); ?>"
                    style="max-width:250px; height:32px; font-size:0.83rem;">
                <button type="submit" class="btn btn-premium rounded-pill px-3"
                    style="height:32px; font-size:0.83rem; line-height:1;">Search</button>
                <?php if ($q): ?>
                    <a href="kanban.php" class="btn btn-outline-secondary rounded-pill px-3"
                        style="height:32px; font-size:0.83rem; line-height:1.6;">Clear</a>
                    <span class="text-muted small">
                        <?php echo $totalCount; ?> result<?php echo $totalCount !== 1 ? 's' : ''; ?>
                        for &ldquo;<?php echo htmlspecialchars($q); ?>&rdquo;
                    </span>
                <?php endif; ?>
            </form>

            <div class="cat-filter-bar">
                <button class="cat-filter-btn active" data-filter="">All</button>
                <?php foreach ($categories as $cid => $cname): ?>
                    <button class="cat-filter-btn" data-filter="<?php echo $cid; ?>">
                        <?php echo htmlspecialchars($cname); ?>
                    </button>
                <?php endforeach; ?>
                <button class="cat-filter-btn" data-filter="untagged">Untagged</button>
            </div>
        </div>
    </div>

    <div class="container-fluid kanban-container">
        <div class="row">
            <div class="col-md-4">
                <div class="glass-panel kanban-col">
                    <div class="column-header">
                        <h4>Today</h4>
                        <span class="column-count" id="count-today"><?php echo count($today); ?></span>
                    </div>
                    <div class="kanban-list" id="list-today" data-col="today">
                        <?php foreach ($today as $email) renderCard($email, $categories, $catColors); ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="glass-panel kanban-col">
                    <div class="column-header">
                        <h4>This Week</h4>
                        <span class="column-count" id="count-week"><?php echo count($week); ?></span>
                    </div>
                    <div class="kanban-list" id="list-week" data-col="week">
                        <?php foreach ($week as $email) renderCard($email, $categories, $catColors); ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="glass-panel kanban-col">
                    <div class="column-header">
                        <h4>Upcoming</h4>
                        <span class="column-count" id="count-later"><?php echo count($later); ?></span>
                    </div>
                    <div class="kanban-list" id="list-later" data-col="later">
                        <?php foreach ($later as $email) renderCard($email, $categories, $catColors); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating category picker -->
    <div id="catPicker"></div>

    <div id="statusToast"></div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // ── Theme Switcher ──────────────────────────────────────────────────
        const toggleSwitch = document.querySelector('.theme-switch input[type="checkbox"]');
        toggleSwitch.addEventListener('change', async function (e) {
            const theme = e.target.checked ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', theme);
            e.target.closest('.theme-switch-wrapper').querySelector('span').textContent =
                theme.charAt(0).toUpperCase() + theme.slice(1);
            await fetch('api/update_theme.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ theme })
            });
        });

        // ── Drag-to-reschedule (Sortable) ───────────────────────────────────
        document.querySelectorAll('.kanban-list').forEach(el => {
            new Sortable(el, {
                group: 'kanban',
                animation: 250,
                easing: 'cubic-bezier(1, 0, 0, 1)',
                ghostClass: 'sortable-ghost',
                onEnd: function (evt) {
                    if (evt.from !== evt.to) {
                        rescheduleByColumn(
                            evt.item.getAttribute('data-id'),
                            evt.to.getAttribute('data-col')
                        );
                        recountList(evt.from);
                        recountList(evt.to);
                    }
                }
            });
        });

        async function rescheduleByColumn(id, column) {
            try {
                const res = await fetch('api/update_reminder.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({ id, column })
                });
                const r = await res.json();
                if (r.success) showToast('Moved to ' + column);
                else alert('Error: ' + r.error);
            } catch (e) { console.error(e); }
        }

        function recountList(listEl) {
            const countEl = listEl.closest('.kanban-col')?.querySelector('.column-count');
            if (countEl) {
                countEl.textContent = listEl.querySelectorAll('.card:not([style*="display: none"])').length;
            }
        }

        // ── Category filter ─────────────────────────────────────────────────
        document.querySelectorAll('.cat-filter-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.cat-filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const filter = this.dataset.filter;

                document.querySelectorAll('.card').forEach(card => {
                    const cardCat = card.getAttribute('data-cat');
                    let show = true;
                    if (filter === 'untagged')  show = cardCat === '';
                    else if (filter !== '')      show = cardCat === filter;
                    card.style.display = show ? '' : 'none';
                });

                document.querySelectorAll('.kanban-list').forEach(recountList);
            });
        });

        // ── Category picker ─────────────────────────────────────────────────
        const CAT_DEFS = {
            null: { name: '✕ Remove tag', color: '' },
            1:    { name: 'Delayed',      color: '#e67e22' },
            2:    { name: 'Delegated',    color: '#5dade2' },
            3:    { name: 'Doing',        color: '#2ecc71' },
            4:    { name: 'Dusted',       color: '#95a5a6' }
        };

        const picker = document.getElementById('catPicker');
        let pickerClose = null;

        window.openCatPicker = function (chipEl, emailId) {
            // Remove previous outside-click listener
            if (pickerClose) { document.removeEventListener('click', pickerClose); pickerClose = null; }

            picker.innerHTML = '';
            for (const [key, def] of Object.entries(CAT_DEFS)) {
                const item = document.createElement('div');
                item.className  = 'cat-picker-item';
                item.textContent = def.name;
                item.style.color = def.color || 'var(--text-muted)';
                item.onclick = (e) => {
                    e.stopPropagation();
                    applyCategory(emailId, key === 'null' ? null : parseInt(key), chipEl);
                    picker.style.display = 'none';
                };
                picker.appendChild(item);
            }

            // Position
            const rect = chipEl.getBoundingClientRect();
            picker.style.display = 'block';
            const ph = picker.offsetHeight;
            const spaceBelow = window.innerHeight - rect.bottom;
            picker.style.top  = (spaceBelow >= ph + 8
                ? rect.bottom + window.scrollY + 5
                : rect.top + window.scrollY - ph - 5) + 'px';
            picker.style.left = rect.left + 'px';

            setTimeout(() => {
                pickerClose = function (e) {
                    if (!picker.contains(e.target)) {
                        picker.style.display = 'none';
                        document.removeEventListener('click', pickerClose);
                        pickerClose = null;
                    }
                };
                document.addEventListener('click', pickerClose);
            }, 0);
        };

        async function applyCategory(emailId, catId, chipEl) {
            try {
                const res = await fetch('api/update_category.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({ id: emailId, catId: catId })
                });
                const r = await res.json();
                if (!r.success) { alert('Error: ' + r.error); return; }

                const def = catId !== null ? CAT_DEFS[catId] : CAT_DEFS['null'];
                if (catId !== null && def) {
                    chipEl.textContent       = def.name;
                    chipEl.style.background  = def.color + '22';
                    chipEl.style.color       = def.color;
                    chipEl.style.borderColor = def.color;
                    chipEl.setAttribute('data-cat-id', catId);
                    chipEl.closest('.card').setAttribute('data-cat', catId);
                    showToast('Tagged: ' + def.name);
                } else {
                    chipEl.textContent       = '+ Tag';
                    chipEl.style.background  = 'rgba(255,255,255,0.06)';
                    chipEl.style.color       = 'var(--text-muted)';
                    chipEl.style.borderColor = 'var(--glass-border)';
                    chipEl.setAttribute('data-cat-id', '');
                    chipEl.closest('.card').setAttribute('data-cat', '');
                    showToast('Tag removed');
                }

                // Re-apply active filter so the card hides/shows correctly
                const activeFilter = document.querySelector('.cat-filter-btn.active');
                if (activeFilter) activeFilter.click();

            } catch (e) { console.error(e); }
        }

        // ── Toast ───────────────────────────────────────────────────────────
        let toastTimer = null;
        function showToast(msg) {
            const toast = document.getElementById('statusToast');
            toast.textContent = msg;
            toast.style.display = 'block';
            if (toastTimer) clearTimeout(toastTimer);
            toastTimer = setTimeout(() => { toast.style.display = 'none'; }, 2500);
        }
    });
    </script>
</body>

</html>
