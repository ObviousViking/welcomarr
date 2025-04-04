<?php
require_once 'config_sqlite.php';

$page_title = 'Invitation';
$settings = get_settings();

// Get invitation code from URL
$code = $_GET['code'] ?? '';

// Find invitation by code
$invitation = get_invitation_by_code($code);

// Check if invitation is valid
$is_valid = is_invitation_valid($invitation);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_valid) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $plex_username = $_POST['plex_username'] ?? '';
    
    // Validate Plex username
    if (empty($plex_username)) {
        set_flash_message('Plex username is required', 'danger');
        header('Location: invitation.php?code=' . $code);
        exit;
    }
    
    // Get libraries associated with this invitation
    $invitation_libraries = get_invitation_libraries($invitation['id']);
    $library_ids = array_column($invitation_libraries, 'library_id');
    
    // Add user to Plex server first
    $plex_result = add_user_to_plex($plex_username, $library_ids);
    
    // Create user record
    $user_data = [
        'name' => $name,
        'email' => $email,
        'plex_username' => $plex_username,
        'joined' => date('Y-m-d H:i:s'),
        'invitation_code' => $code,
        'plex_status' => $plex_result['success'] ? 'added' : 'failed',
        'plex_message' => $plex_result['message'] ?? ''
    ];
    
    // Add user to database
    $user_id = add_user($user_data);
    
    // Update invitation usage count
    update_invitation_usage($code, $plex_username);
    
    // Set flash message
    if ($plex_result['success']) {
        set_flash_message('Your information has been submitted successfully! You have been added to the Plex server.', 'success');
    } else {
        set_flash_message('Your information has been submitted successfully! However, there was an issue adding you to the Plex server: ' . $plex_result['message'], 'warning');
    }
    
    // Redirect to success page
    header('Location: success.php?code=' . $code);
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

    <!-- Invitation Content -->
    <div class="container mt-4">
        <?php if (!$code): ?>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body text-center">
                        <h2 class="card-title">No Invitation Code Provided</h2>
                        <p class="card-text">Please enter an invitation code to continue.</p>
                        <a href="index.php" class="btn btn-primary">Go Back Home</a>
                    </div>
                </div>
            </div>
        </div>
        <?php elseif (!$invitation): ?>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body text-center">
                        <h2 class="card-title">Invalid Invitation Code</h2>
                        <p class="card-text">The invitation code you provided is not valid.</p>
                        <a href="index.php" class="btn btn-primary">Go Back Home</a>
                    </div>
                </div>
            </div>
        </div>
        <?php elseif (isset($invitation['usage_limit']) && isset($invitation['usage_count']) && $invitation['usage_limit'] > 0 && $invitation['usage_count'] >= $invitation['usage_limit']): ?>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body text-center">
                        <h2 class="card-title">Invitation Fully Used</h2>
                        <p class="card-text">This invitation has reached its maximum number of uses.</p>
                        <a href="index.php" class="btn btn-primary">Go Back Home</a>
                    </div>
                </div>
            </div>
        </div>
        <?php elseif (isset($invitation['expires']) && $invitation['expires'] && strtotime($invitation['expires']) < time()): ?>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body text-center">
                        <h2 class="card-title">Invitation Expired</h2>
                        <p class="card-text">This invitation has expired.</p>
                        <a href="index.php" class="btn btn-primary">Go Back Home</a>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h2 class="mb-0">Welcome to <?php echo $settings['site_name']; ?></h2>
                    </div>
                    <div class="card-body">
                        <p class="lead"><?php echo $settings['welcome_message']; ?></p>
                        
                        <div class="alert alert-info">
                            <p class="mb-0"><strong>Invitation Code:</strong> <span class="invitation-code"><?php echo $code; ?></span></p>
                        </div>
                        
                        <form method="post" action="invitation.php?code=<?php echo $code; ?>">
                            <div class="mb-3">
                                <label for="name" class="form-label">Your Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Your Email (Optional)</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($invitation['email'] ?? ''); ?>">
                                <div class="form-text">Your email is optional but helpful for communication</div>
                            </div>
                            <div class="mb-3">
                                <label for="plex_username" class="form-label">Plex Username</label>
                                <input type="text" class="form-control" id="plex_username" name="plex_username" required>
                                <div class="form-text">Your Plex username is required to add you to the server</div>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Submit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
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