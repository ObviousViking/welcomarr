<?php
require_once 'config_sqlite.php';

// Require login
if (!is_logged_in()) {
    set_flash_message('Please login to access this page', 'warning');
    header('Location: login.php');
    exit;
}

$page_title = 'Settings';
$settings = get_settings();
$admin = get_admin();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update settings
    $settings['site_name'] = $_POST['site_name'] ?? 'Welcomarr';
    $settings['welcome_message'] = $_POST['welcome_message'] ?? '';
    $settings['plex_server'] = $_POST['plex_server'] ?? '';
    $settings['plex_token'] = $_POST['plex_token'] ?? '';
    $settings['plex_url'] = $_POST['plex_url'] ?? '';
    $settings['theme_color'] = $_POST['theme_color'] ?? '#e5a00d';
    
    // Update settings in database
    update_settings($settings);
    
    // Update admin credentials
    if (!empty($_POST['new_password']) && $_POST['new_password'] === $_POST['confirm_password']) {
        $admin['password'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $admin['email'] = $_POST['admin_email'] ?? $admin['email'];
        update_admin($admin);
        $password_updated = true;
    } else if (isset($_POST['admin_email']) && $_POST['admin_email'] !== $admin['email']) {
        $admin['email'] = $_POST['admin_email'];
        update_admin($admin);
    }
    
    // Sync Plex libraries if token is set
    if (!empty($settings['plex_token'])) {
        sync_plex_libraries();
    }
    
    // Set flash message
    if (isset($password_updated)) {
        set_flash_message('Settings and password updated successfully', 'success');
    } else {
        set_flash_message('Settings updated successfully', 'success');
    }
    
    // Redirect to refresh the page
    header('Location: settings.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ? $page_title . ' - ' . $settings['site_name'] : $settings['site_name']; ?></title>
    <link rel="icon" href="img/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="img/logo.png" alt="<?php echo $settings['site_name']; ?>" onerror="this.src='img/logo-placeholder.png'">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="settings.php">Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <?php $flash = get_flash_message(); ?>
    <?php if ($flash): ?>
    <div class="container mt-3">
        <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
            <?php echo $flash['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Settings Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h1>Settings</h1>
                <p class="text-muted">Configure your Welcomarr installation</p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <form method="post" action="settings.php">
                            <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">General</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="plex-tab" data-bs-toggle="tab" data-bs-target="#plex" type="button" role="tab">Plex Settings</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="admin-tab" data-bs-toggle="tab" data-bs-target="#admin" type="button" role="tab">Admin Account</button>
                                </li>
                            </ul>
                            
                            <div class="tab-content p-3" id="settingsTabsContent">
                                <!-- General Settings -->
                                <div class="tab-pane fade show active" id="general" role="tabpanel">
                                    <div class="mb-3">
                                        <label for="site_name" class="form-label">Site Name</label>
                                        <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="welcome_message" class="form-label">Welcome Message</label>
                                        <textarea class="form-control" id="welcome_message" name="welcome_message" rows="3"><?php echo htmlspecialchars($settings['welcome_message']); ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="theme_color" class="form-label">Theme Color</label>
                                        <input type="color" class="form-control form-control-color" id="theme_color" name="theme_color" value="<?php echo htmlspecialchars($settings['theme_color']); ?>">
                                    </div>
                                </div>
                                
                                <!-- Plex Settings -->
                                <div class="tab-pane fade" id="plex" role="tabpanel">
                                    <div class="mb-3">
                                        <label for="plex_server" class="form-label">Plex Server Name</label>
                                        <input type="text" class="form-control" id="plex_server" name="plex_server" value="<?php echo htmlspecialchars($settings['plex_server']); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="plex_url" class="form-label">Plex URL</label>
                                        <input type="url" class="form-control" id="plex_url" name="plex_url" value="<?php echo htmlspecialchars($settings['plex_url']); ?>" placeholder="https://app.plex.tv">
                                    </div>
                                    <div class="mb-3">
                                        <label for="plex_token" class="form-label">Plex Token</label>
                                        <input type="password" class="form-control" id="plex_token" name="plex_token" value="<?php echo htmlspecialchars($settings['plex_token']); ?>">
                                        <div class="form-text">Used for automatic user invitation and library synchronization</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h5>Libraries</h5>
                                        <?php 
                                        $libraries = get_libraries();
                                        if (empty($libraries)): 
                                        ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i> No libraries found. Save your Plex token to sync libraries.
                                        </div>
                                        <?php else: ?>
                                        <div class="alert alert-success">
                                            <i class="fas fa-check-circle me-2"></i> <?php echo count($libraries); ?> libraries found.
                                            <ul class="mb-0 mt-2">
                                                <?php foreach ($libraries as $library): ?>
                                                <li><?php echo htmlspecialchars($library['name']); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Admin Account -->
                                <div class="tab-pane fade" id="admin" role="tabpanel">
                                    <div class="mb-3">
                                        <label for="admin_username" class="form-label">Admin Username</label>
                                        <input type="text" class="form-control" id="admin_username" value="<?php echo htmlspecialchars($admin['username']); ?>" disabled>
                                    </div>
                                    <div class="mb-3">
                                        <label for="admin_email" class="form-label">Admin Email</label>
                                        <input type="email" class="form-control" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($admin['email']); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password">
                                        <div class="form-text">Leave blank to keep current password</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                                <button type="submit" class="btn btn-primary">Save Settings</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer mt-5">
        <div class="container text-center">
            <p>&copy; <?php echo date('Y'); ?> <?php echo $settings['site_name']; ?> - A Simple Plex Onboarding System</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>