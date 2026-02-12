<?php
require_once 'src/Session.php';
require_once 'src/User.php';
require_once 'src/Utils.php';
require_once 'src/AuditLog.php';

Session::start();
Session::requireAdmin();

$auditLog = new AuditLog();

$userRepo = new User();
$adminUser = $userRepo->findByEmail($_SESSION['user_email']);

if (!$adminUser) {
    die("Admin session invalid.");
}

// Sync Timezone & Theme
if (!empty($adminUser['timezone'])) {
    date_default_timezone_set($adminUser['timezone']);
    $_SESSION['user_timezone'] = $adminUser['timezone'];
}
$_SESSION['user_theme'] = $adminUser['theme'] ?? 'dark';

$message = '';
$error = '';

// Handle Form Submission (Update or Create)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Utils::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';
        $userId = $_POST['user_id'] ?? '';

        if ($action === 'create') {
            if ($userRepo->create($name, $email, $role)) {
                $newUser = $userRepo->findByEmail($email);

                if ($newUser) {
                    // Generate password setup token
                    require_once 'src/Mailer.php';
                    $token = $userRepo->generatePasswordSetupToken($newUser['ID']);

                    // Send password setup email
                    $mailer = new Mailer();
                    $emailSent = $mailer->sendPasswordSetupEmail($email, $name, $token);

                    // Log user creation
                    $auditLog->logFromSession(
                        AuditLog::USER_CREATED,
                        $newUser['ID'],
                        'user',
                        ['name' => $name, 'email' => $email, 'role' => $role, 'email_sent' => $emailSent]
                    );

                    if ($emailSent) {
                        $message = "User created successfully. A password setup email has been sent to {$email}.";
                    } else {
                        $message = "User created, but failed to send password setup email. Please check email configuration.";
                    }
                } else {
                    $error = "User created but could not retrieve user data.";
                }
            } else {
                $error = "Failed to create user (Email might exist).";
            }
        } elseif ($action === 'update') {
            $targetUser = $userRepo->getById($userId);
            $userRepo->update($userId, $name, $email, !empty($password) ? $password : null, $role);
            // Log user update
            $changes = ['name' => $name, 'email' => $email, 'role' => $role];
            if (!empty($password)) {
                $changes['password_changed'] = true;
            }
            $auditLog->logFromSession(
                AuditLog::USER_UPDATED,
                $userId,
                'user',
                ['changes' => $changes, 'previous_email' => $targetUser['email'] ?? null]
            );
            $message = "User updated successfully.";
        } elseif ($action === 'reset_password') {
            $userId = $_POST['user_id'] ?? '';
            if ($userId) {
                $targetUser = $userRepo->getById($userId);
                $newPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 10);
                $userRepo->updatePassword($userId, $newPassword);
                // Log password reset
                $auditLog->logFromSession(
                    AuditLog::PASSWORD_RESET,
                    $userId,
                    'user',
                    ['target_email' => $targetUser['email'] ?? null]
                );
                $message = "Password reset successfully. The new password is: <strong>" . htmlspecialchars($newPassword) . "</strong>";
            }
        }
    }
}

$users = $userRepo->getAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $_SESSION['user_theme']; ?>">

<head>
    <meta charset="UTF-8">
    <title>User Management - Snoozer</title>
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
                    <li class="nav-item active"><a class="nav-link" href="users.php">Users</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin_templates.php">Templates</a></li>
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
                    <strong><?php echo htmlspecialchars($adminUser['name']); ?></strong></span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-4">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div> <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div> <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 font-weight-bold">User Management</h1>
            <button class="btn btn-premium" onclick="prepareModal('create')">Add User</button>
        </div>

        <div class="glass-panel p-0 overflow-hidden">
            <table class="table table-borderless table-hover mb-0">
                <thead class="bg-dark text-white">
                    <tr>
                        <th class="pl-4">ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th class="pr-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="pl-4 align-middle"><?php echo $u['ID']; ?></td>
                            <td class="align-middle font-weight-bold"><?php echo htmlspecialchars($u['name']); ?></td>
                            <td class="align-middle text-muted"><?php echo htmlspecialchars($u['email']); ?></td>
                            <td class="align-middle">
                                <span
                                    class="badge badge-<?php echo ($u['role'] === 'admin' ? 'danger' : 'info'); ?> rounded-pill px-3">
                                    <?php echo htmlspecialchars($u['role']); ?>
                                </span>
                            </td>
                            <td class="pr-4 align-middle">
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3"
                                    onclick='prepareModal("update", <?php echo json_encode($u); ?>)'>Edit</button>
                                <form method="POST" style="display:inline;"
                                    onsubmit="return confirm('Are you sure you want to reset the password for this user?');">
                                    <?php echo Utils::csrfField(); ?>
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?php echo $u['ID']; ?>">
                                    <button type="submit"
                                        class="btn btn-sm btn-outline-warning rounded-pill px-3 ml-1">Reset
                                        Password</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="userModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content glass-panel" style="border-radius: 20px; color: #333;">
                <form method="POST">
                    <?php echo Utils::csrfField(); ?>
                    <div class="modal-header border-0">
                        <h5 class="modal-title font-weight-bold" id="modalTitle">User</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" id="action">
                        <input type="hidden" name="user_id" id="user_id">
                        <div class="form-group">
                            <label class="font-weight-bold">Name</label>
                            <input type="text" name="name" id="name" class="form-control rounded-pill px-3" required>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Email</label>
                            <input type="email" name="email" id="email" class="form-control rounded-pill px-3" required>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Role</label>
                            <select name="role" id="role" class="form-control rounded-pill px-3">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Password <small class="text-muted">(Optional - new users
                                    will receive setup email)</small></label>
                            <input type="password" name="password" id="password" class="form-control rounded-pill px-3">
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-link text-muted" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-premium">Save Changes</button>
                    </div>
                </form>
            </div>
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

        function prepareModal(mode, user = null) {
            document.getElementById('action').value = mode;
            if (mode === 'create') {
                document.getElementById('modalTitle').innerText = 'Add New User';
                document.getElementById('user_id').value = '';
                document.getElementById('name').value = '';
                document.getElementById('email').value = '';
                document.getElementById('role').value = 'user';
                document.getElementById('password').required = false; // Password is optional - user will receive setup email
            } else {
                document.getElementById('modalTitle').innerText = 'Edit User';
                document.getElementById('user_id').value = user.ID;
                document.getElementById('name').value = user.name;
                document.getElementById('email').value = user.email;
                document.getElementById('role').value = user.role || 'user';
                document.getElementById('password').value = '';
                document.getElementById('password').required = false;
            }
            $('#userModal').modal('show');
        }
    </script>
</body>

</html>