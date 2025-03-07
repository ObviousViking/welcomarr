<?php
require_once 'config.php';

$page_title = 'Success';
$data = load_data();
$settings = $data['settings'];

// Get invitation code from URL
$code = $_GET['code'] ?? '';

// Find invitation by code
$invitation_data = get_invitation_by_code($code);
$invitation = $invitation_data ? $invitation_data['invitation'] : null;

// Find user by invitation code
$user = null;
foreach ($data['users'] as $u) {
    if (isset($u['invitation_code']) && $u['invitation_code'] === $code) {
        $user = $u;
        break;
    }
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
                    <?php if (is_logged_in()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Admin Login</a>
                    </li>
                    <?php endif; ?>
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

    <!-- Success Content -->
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle success-checkmark"></i>
                        <h1 class="card-title">Success!</h1>
                        
                        <?php if ($user): ?>
                        <p class="lead">Thank you, <?php echo htmlspecialchars($user['name']); ?>!</p>
                        <p class="card-text">Your information has been submitted successfully. The server administrator will add your Plex account to the server soon.</p>
                        <?php else: ?>
                        <p class="card-text">Your information has been submitted successfully. The server administrator will add your Plex account to the server soon.</p>
                        <?php endif; ?>
                        
                        <?php if (!empty($settings['plex_url'])): ?>
                        <div class="mt-4">
                            <a href="<?php echo htmlspecialchars($settings['plex_url']); ?>" class="btn btn-primary" target="_blank">
                                <i class="fas fa-external-link-alt me-2"></i> Go to Plex
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <a href="index.php" class="btn btn-outline-secondary">Return to Home</a>
                        </div>
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