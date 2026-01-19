<?php
session_start();
require_once 'src/User.php';
require_once 'src/Database.php';
require_once 'src/Utils.php';

// Auth & RBAC Check
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? 'user') !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();
$message = '';
$error = '';

// Handle Template Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['slug'])) {
    if (!Utils::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please try again.";
    } else {
        $slug = $_POST['slug'];
        $subject = $_POST['subject'] ?? '';
        $body = $_POST['body'] ?? '';

        try {
            $db->query(
                "UPDATE email_templates SET subject = ?, body = ? WHERE slug = ?",
                [$subject, $body, $slug]
            );
            $message = "Template '$slug' updated successfully.";
        } catch (Exception $e) {
            $error = "Failed to update template: " . $e->getMessage();
        }
    }
}

$templates = $db->fetchAll("SELECT * FROM email_templates");
$userRepo = new User();
$adminUser = $userRepo->findByEmail($_SESSION['user_email']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $_SESSION['user_theme'] ?? 'dark'; ?>">

<head>
    <meta charset="UTF-8">
    <title>Email Templates - Snoozer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        .editor-container { min-height: 400px; }
        .template-card { cursor: pointer; transition: all 0.2s; }
        .template-card:hover { transform: scale(1.02); border-color: var(--primary-purple); }
        .template-active { border-left: 5px solid var(--primary-purple) !important; background: var(--glass-bg); }
        textarea.body-editor { 
            font-family: 'Courier New', Courier, monospace; 
            font-size: 14px; 
            min-height: 450px; 
            background: var(--glass-bg);
            color: var(--text-color);
            border: 1px solid rgba(125, 60, 152, 0.2);
            transition: all 0.3s;
        }
        textarea.body-editor:focus {
            background: rgba(125, 60, 152, 0.05);
            border-color: var(--primary-purple);
            box-shadow: 0 0 15px rgba(125, 60, 152, 0.1);
            outline: none;
            color: var(--text-color);
        }
        [data-theme="light"] textarea.body-editor {
            background: #fff;
            color: #333;
        }
    </style>
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
                    <li class="nav-item active"><a class="nav-link" href="#">Templates</a></li>
                </ul>
                <div class="theme-switch-wrapper mr-4">
                    <label class="theme-switch" for="checkbox">
                        <input type="checkbox" id="checkbox" <?php echo ($_SESSION['user_theme'] ?? 'dark') === 'light' ? 'checked' : ''; ?> />
                        <div class="slider round"></div>
                    </label>
                    <span class="ml-2 small"><?php echo ($_SESSION['user_theme'] ?? 'dark') === 'light' ? 'Light' : 'Dark'; ?></span>
                </div>
                <span class="navbar-text mr-3 small">Welcome, <strong><?php echo htmlspecialchars($adminUser['name']); ?></strong></span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-4">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-5">
        <h1 class="h3 font-weight-bold mb-4">Email Templates</h1>

        <?php if ($message): ?> <div class="alert alert-success"><?php echo $message; ?></div> <?php endif; ?>
        <?php if ($error): ?> <div class="alert alert-danger"><?php echo $error; ?></div> <?php endif; ?>

        <div class="row">
            <!-- Sidebar: Template List -->
            <div class="col-md-3">
                <div class="glass-panel p-3">
                    <h5 class="font-weight-bold mb-3 small opacity-75">TEMPLATES</h5>
                    <?php foreach ($templates as $t): ?>
                        <div class="card p-3 mb-2 template-card <?php echo (isset($_GET['slug']) && $_GET['slug'] === $t['slug']) ? 'template-active' : ''; ?>"
                            onclick="location.href='?slug=<?php echo $t['slug']; ?>'">
                            <div class="font-weight-bold text-uppercase small"><?php echo $t['slug']; ?></div>
                            <div class="small text-muted">Variables: <?php echo $t['variables']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Main: Editor -->
            <div class="col-md-9">
                <?php
                $slug = $_GET['slug'] ?? null;
                $activeTemplate = null;
                if ($slug) {
                    foreach ($templates as $t) if ($t['slug'] === $slug) $activeTemplate = $t;
                }
                ?>

                <?php if ($activeTemplate): ?>
                    <form method="POST">
                        <?php echo Utils::csrfField(); ?>
                        <input type="hidden" name="slug" value="<?php echo $activeTemplate['slug']; ?>">
                        <div class="glass-panel p-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="font-weight-bold m-0"><?php echo strtoupper($activeTemplate['slug']); ?> Template</h4>
                                <div>
                                    <a href="preview_email.php" target="_blank" class="btn btn-link text-muted mr-2">Live Preview</a>
                                    <button type="submit" class="btn btn-premium px-5">Save Changes</button>
                                </div>
                            </div>

                            <div class="form-group mb-4">
                                <label class="small font-weight-bold">Email Subject</label>
                                <input type="text" name="subject" class="form-control rounded-pill px-3" 
                                    value="<?php echo htmlspecialchars($activeTemplate['subject']); ?>" 
                                    <?php echo $activeTemplate['slug'] === 'wrapper' ? 'disabled placeholder="Global Wrapper has no subject"' : ''; ?>>
                            </div>

                            <div class="form-group">
                                <label class="small font-weight-bold">HTML Body</label>
                                <textarea name="body" class="form-control body-editor glass-panel p-3"><?php echo htmlspecialchars($activeTemplate['body']); ?></textarea>
                            </div>
                            
                            <div class="mt-3">
                                <small class="text-muted">
                                    <strong>Available Variables:</strong> <?php echo $activeTemplate['variables']; ?>
                                </small>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="glass-panel p-5 text-center">
                        <div class="h5 text-muted">Select a template from the sidebar to edit</div>
                    </div>
                <?php endif; ?>
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
            if (toggleSwitch) toggleSwitch.addEventListener('change', switchTheme, false);
        });
    </script>
</body>
</html>
