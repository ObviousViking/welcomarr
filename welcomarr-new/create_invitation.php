<?php
require_once 'config.php';

// Require login
require_login();

$page_title = 'Create Invitation';
$data = load_data();
$settings = $data['settings'];
$libraries = $data['libraries'] ?? [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $expires_days = intval($_POST['expires_days'] ?? 7);
    $usage_limit = intval($_POST['usage_limit'] ?? 1);
    $selected_libraries = $_POST['libraries'] ?? [];
    $invitation_name = $_POST['invitation_name'] ?? 'New Invitation';
    
    // Generate invitation code
    $code = generate_invitation_code();
    
    // Calculate expiration date (0 means never expires)
    $expires = null;
    if ($expires_days > 0) {
        $expires = date('Y-m-d H:i:s', strtotime("+{$expires_days} days"));
    }
    
    // Create invitation
    $invitation = [
        'code' => $code,
        'name' => $invitation_name,
        'email' => $email,
        'created' => date('Y-m-d H:i:s'),
        'expires' => $expires,
        'usage_limit' => $usage_limit,
        'usage_count' => 0,
        'libraries' => $selected_libraries
    ];
    
    // Add invitation to data
    $data['invitations'][] = $invitation;
    save_data($data);
    
    // Set flash message
    set_flash_message('Invitation created successfully', 'success');
    
    // Redirect to admin page
    header('Location: admin.php');
    exit;
}

// Get Plex libraries if token is set
$plex_libraries = [];
if (!empty($settings['plex_token'])) {
    $plex_libraries = get_plex_library_sections($settings['plex_token']);
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
                        <a class="nav-link" href="settings.php">Settings</a>
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

    <!-- Create Invitation Form -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h1>Create Invitation</h1>
                <p class="text-muted">Generate a new invitation code for a user</p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-body">
                        <form method="post" action="create_invitation.php">
                            <div class="mb-3">
                                <label for="invitation_name" class="form-label">Invitation Name</label>
                                <input type="text" class="form-control" id="invitation_name" name="invitation_name" placeholder="Family Movie Night" value="New Invitation">
                                <div class="form-text">A name to help you identify this invitation</div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email (Optional)</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="user@example.com">
                                <div class="form-text">The email of the person you're inviting (for your reference only)</div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="expires_days" class="form-label">Expires After (Days)</label>
                                        <select class="form-select" id="expires_days" name="expires_days">
                                            <option value="1">1 day</option>
                                            <option value="3">3 days</option>
                                            <option value="7" selected>7 days</option>
                                            <option value="14">14 days</option>
                                            <option value="30">30 days</option>
                                            <option value="0">Never expires</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="usage_limit" class="form-label">Usage Limit</label>
                                        <select class="form-select" id="usage_limit" name="usage_limit">
                                            <option value="1" selected>1 use</option>
                                            <option value="5">5 uses</option>
                                            <option value="10">10 uses</option>
                                            <option value="25">25 uses</option>
                                            <option value="50">50 uses</option>
                                            <option value="100">100 uses</option>
                                            <option value="0">Unlimited</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Libraries to Share</label>
                                <?php if (empty($plex_libraries)): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i> No Plex libraries found. Please configure your Plex token in settings.
                                </div>
                                <div class="form-text mb-2">Using default libraries instead:</div>
                                <?php foreach ($libraries as $library): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="libraries[]" value="<?php echo $library['id']; ?>" id="library_<?php echo $library['id']; ?>" checked>
                                    <label class="form-check-label" for="library_<?php echo $library['id']; ?>">
                                        <?php echo $library['name']; ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <?php foreach ($plex_libraries as $library): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="libraries[]" value="<?php echo $library['id']; ?>" id="library_<?php echo $library['id']; ?>" checked>
                                    <label class="form-check-label" for="library_<?php echo $library['id']; ?>">
                                        <?php echo $library['title']; ?> (<?php echo $library['type']; ?>)
                                    </label>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Generate Invitation</button>
                                <a href="admin.php" class="btn btn-outline-secondary">Cancel</a>
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