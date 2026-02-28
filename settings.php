<?php
require_once 'src/Session.php';
require_once 'src/User.php';
require_once 'src/Utils.php';
require_once 'src/GroupRepository.php';

Session::start();
Session::requireAuth();

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

$groupRepo  = new GroupRepository();
$userGroups = $groupRepo->getForUser((int)$user['ID']);

$message = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Utils::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please try again.";
    } else {
        $timezone           = $_POST['timezone'] ?? 'UTC';
        $password           = $_POST['password'] ?? '';
        $threadReminders    = isset($_POST['thread_reminders']) ? 1 : 0;
        $defaultReminderHour = (int) ($_POST['default_reminder_hour'] ?? 17);

        if (!in_array($timezone, timezone_identifiers_list())) {
            $error = "Invalid Timezone";
        } elseif ($defaultReminderHour < 0 || $defaultReminderHour > 23) {
            $error = "Invalid reminder hour (must be 0–23).";
        } else {
            $userRepo->update($user['ID'], $user['name'], $user['email'], !empty($password) ? $password : null, $user['role'], $timezone, null, $threadReminders);
            $userRepo->updateDefaultReminderTime($user['email'], (string) $defaultReminderHour);
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
    <?php echo Utils::csrfMeta(); ?>
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
                                $currentTimezone = $user['timezone'] ?? 'UTC';
                                $grouped = [];
                                foreach (timezone_identifiers_list() as $tz) {
                                    $parts = explode('/', $tz, 2);
                                    $continent = count($parts) > 1 ? $parts[0] : 'Other';
                                    $grouped[$continent][] = $tz;
                                }
                                ksort($grouped);
                                foreach ($grouped as $continent => $zones): ?>
                                    <optgroup label="<?php echo htmlspecialchars($continent); ?>">
                                        <?php foreach ($zones as $tz): ?>
                                            <option value="<?php echo $tz; ?>" <?php echo $currentTimezone === $tz ? 'selected' : ''; ?>>
                                                <?php echo $tz; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mt-3">
                            <label class="font-weight-bold">Default Reminder Hour</label>
                            <input type="number" name="default_reminder_hour" min="0" max="23"
                                class="form-control rounded-pill px-3"
                                value="<?php echo (int) ($user['DefaultReminderTime'] ?? 17); ?>">
                            <small class="text-muted d-block mt-1">
                                Hour (0–23) used for <code>eod</code> and <code>eow</code> expressions.
                                Default is 17 (5:00 PM).
                            </small>
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
                        <div class="form-group mt-3">
                            <label class="font-weight-bold d-block">Reminder Emails</label>
                            <div class="custom-control custom-switch mt-2">
                                <input type="checkbox" class="custom-control-input" id="threadReminders"
                                    name="thread_reminders" <?php echo ($user['thread_reminders'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="custom-control-label" for="threadReminders">
                                    Thread reminders to the original email
                                </label>
                            </div>
                            <small class="text-muted d-block mt-1">
                                When enabled, reminder emails arrive as a reply in the same thread as your original email.
                            </small>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-premium btn-block">Apply Changes</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <!-- Groups -->
        <div class="glass-panel p-4 mt-4">
            <h5 class="font-weight-bold mb-1">Reminder Groups</h5>
            <p class="text-muted small mb-3">Assign multiple group labels to reminders from the dashboard and kanban.</p>

            <div id="groupsList">
                <?php foreach ($userGroups as $g): ?>
                <div class="group-row d-flex align-items-center mb-2" data-id="<?php echo $g['ID']; ?>" data-name="<?php echo htmlspecialchars($g['name'], ENT_QUOTES); ?>" data-color="<?php echo $g['color']; ?>">
                    <span class="group-dot mr-2" style="display:inline-block;width:12px;height:12px;border-radius:50%;background:<?php echo $g['color']; ?>;flex-shrink:0;"></span>
                    <span class="group-name-label flex-grow-1 font-weight-600"><?php echo htmlspecialchars($g['name']); ?></span>
                    <button class="btn btn-sm btn-link text-muted py-0" onclick="startEditGroup(this)">Edit</button>
                    <button class="btn btn-sm btn-link text-danger py-0" onclick="deleteGroup(this)">Delete</button>
                </div>
                <?php endforeach; ?>
            </div>

            <div id="groupFormArea" class="mt-3"></div>

            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 mt-2" id="showAddGroupBtn" onclick="showAddGroupForm()">+ New Group</button>
        </div>
    </div>

    <style>
        .color-swatch {
            width: 22px; height: 22px; border-radius: 50%; cursor: pointer;
            border: 2px solid transparent; transition: border-color 0.15s, transform 0.15s;
            flex-shrink: 0;
        }
        .color-swatch.selected, .color-swatch:hover { border-color: #fff; transform: scale(1.2); }
        .group-name-label { font-size: 0.9rem; }
    </style>

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
            toggleSwitch.addEventListener('change', switchTheme, false);
        });

        // ── Group management ─────────────────────────────────────────
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const GROUP_COLORS = ['#7d3c98','#3498db','#27ae60','#1abc9c','#e67e22','#e74c3c','#e91e63','#795548'];

        function colorSwatches(selected) {
            return GROUP_COLORS.map(c =>
                `<span class="color-swatch${c === selected ? ' selected' : ''}" data-color="${c}"
                       style="background:${c};" onclick="selectSwatch(this)"></span>`
            ).join('');
        }

        function selectSwatch(el) {
            el.closest('.swatch-row').querySelectorAll('.color-swatch').forEach(s => s.classList.remove('selected'));
            el.classList.add('selected');
        }

        function getSelectedColor(container) {
            const sel = container.querySelector('.color-swatch.selected');
            return sel ? sel.dataset.color : '#7d3c98';
        }

        function showAddGroupForm() {
            document.getElementById('showAddGroupBtn').style.display = 'none';
            document.getElementById('groupFormArea').innerHTML = `
                <div class="d-flex align-items-center flex-wrap mt-1" style="gap:8px;" id="addGroupForm">
                    <input type="text" id="newGroupName" class="form-control form-control-sm rounded-pill px-3"
                           placeholder="Group name" style="max-width:180px;" maxlength="100">
                    <div class="swatch-row d-flex" style="gap:4px;">${colorSwatches('#7d3c98')}</div>
                    <button class="btn btn-premium btn-sm rounded-pill px-3" onclick="createGroup()">Add</button>
                    <button class="btn btn-link btn-sm text-muted" onclick="cancelGroupForm()">Cancel</button>
                </div>`;
            document.getElementById('newGroupName').focus();
        }

        function cancelGroupForm() {
            document.getElementById('groupFormArea').innerHTML = '';
            document.getElementById('showAddGroupBtn').style.display = '';
        }

        async function createGroup() {
            const name  = document.getElementById('newGroupName').value.trim();
            const color = getSelectedColor(document.getElementById('addGroupForm'));
            if (!name) return;
            const res  = await fetch('api/manage_group.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ action: 'create', name, color })
            });
            const data = await res.json();
            if (!res.ok || !data.success) { alert(data.error || 'Error'); return; }

            appendGroupRow(data.id, data.name, data.color);
            cancelGroupForm();
        }

        function appendGroupRow(id, name, color) {
            const row = document.createElement('div');
            row.className = 'group-row d-flex align-items-center mb-2';
            row.dataset.id    = id;
            row.dataset.name  = name;
            row.dataset.color = color;
            row.innerHTML = `
                <span class="group-dot mr-2" style="display:inline-block;width:12px;height:12px;border-radius:50%;background:${color};flex-shrink:0;"></span>
                <span class="group-name-label flex-grow-1 font-weight-600">${escapeHtml(name)}</span>
                <button class="btn btn-sm btn-link text-muted py-0" onclick="startEditGroup(this)">Edit</button>
                <button class="btn btn-sm btn-link text-danger py-0" onclick="deleteGroup(this)">Delete</button>`;
            document.getElementById('groupsList').appendChild(row);
        }

        function startEditGroup(btn) {
            const row   = btn.closest('.group-row');
            const id    = row.dataset.id;
            const name  = row.dataset.name;
            const color = row.dataset.color;

            row.innerHTML = `
                <div class="d-flex align-items-center flex-wrap" style="gap:8px;width:100%;" data-edit-row="${id}">
                    <input type="text" class="form-control form-control-sm rounded-pill px-3 edit-group-name"
                           value="${escapeAttr(name)}" style="max-width:180px;" maxlength="100">
                    <div class="swatch-row d-flex" style="gap:4px;">${colorSwatches(color)}</div>
                    <button class="btn btn-premium btn-sm rounded-pill px-3" onclick="saveEditGroup(this, ${id})">Save</button>
                    <button class="btn btn-link btn-sm text-muted" onclick="cancelEditGroup(this, ${id}, '${escapeAttr(name)}', '${color}')">Cancel</button>
                </div>`;
        }

        async function saveEditGroup(btn, id) {
            const container = btn.closest('[data-edit-row]');
            const name  = container.querySelector('.edit-group-name').value.trim();
            const color = getSelectedColor(container);
            if (!name) return;
            const res  = await fetch('api/manage_group.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ action: 'update', id, name, color })
            });
            const data = await res.json();
            if (!res.ok || !data.success) { alert(data.error || 'Error'); return; }

            const row = container.closest('.group-row');
            row.dataset.name  = name;
            row.dataset.color = color;
            row.innerHTML = `
                <span class="group-dot mr-2" style="display:inline-block;width:12px;height:12px;border-radius:50%;background:${color};flex-shrink:0;"></span>
                <span class="group-name-label flex-grow-1 font-weight-600">${escapeHtml(name)}</span>
                <button class="btn btn-sm btn-link text-muted py-0" onclick="startEditGroup(this)">Edit</button>
                <button class="btn btn-sm btn-link text-danger py-0" onclick="deleteGroup(this)">Delete</button>`;
        }

        function cancelEditGroup(btn, id, name, color) {
            const row = btn.closest('.group-row');
            row.dataset.name  = name;
            row.dataset.color = color;
            row.innerHTML = `
                <span class="group-dot mr-2" style="display:inline-block;width:12px;height:12px;border-radius:50%;background:${color};flex-shrink:0;"></span>
                <span class="group-name-label flex-grow-1 font-weight-600">${escapeHtml(name)}</span>
                <button class="btn btn-sm btn-link text-muted py-0" onclick="startEditGroup(this)">Edit</button>
                <button class="btn btn-sm btn-link text-danger py-0" onclick="deleteGroup(this)">Delete</button>`;
        }

        async function deleteGroup(btn) {
            const row  = btn.closest('.group-row');
            const name = row.dataset.name;
            if (!confirm('Delete group "' + name + '"? It will be removed from all reminders.')) return;
            const id   = row.dataset.id;
            const res  = await fetch('api/manage_group.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ action: 'delete', id: parseInt(id) })
            });
            const data = await res.json();
            if (!res.ok || !data.success) { alert(data.error || 'Error'); return; }
            row.remove();
        }

        function escapeHtml(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
        function escapeAttr(s) {
            return String(s).replace(/'/g,"\\'").replace(/"/g,'&quot;');
        }
    </script>
</body>

</html>