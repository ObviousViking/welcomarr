<?php
require_once 'config.php';

// Require login
require_login();

$page_title = 'Admin Dashboard';
$data = load_data();
$settings = $data['settings'];
$invitations = $data['invitations'] ?? [];
$users = $data['users'] ?? [];

// Count active invitations
$active_invitations = 0;
$used_invitations = 0;
$expired_invitations = 0;

foreach ($invitations as $invitation) {
    if (isset($invitation['used']) && $invitation['used']) {
        $used_invitations++;
    } elseif (isset($invitation['expires']) && strtotime($invitation['expires']) < time()) {
        $expired_invitations++;
    } else {
        $active_invitations++;
    }
}

// Handle invitation deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $code = $_GET['delete'];
    $found = false;
    
    foreach ($invitations as $key => $invitation) {
        if ($invitation['code'] === $code) {
            unset($data['invitations'][$key]);
            $found = true;
            break;
        }
    }
    
    if ($found) {
        // Reindex the array
        $data['invitations'] = array_values($data['invitations']);
        save_data($data);
        set_flash_message('Invitation deleted successfully', 'success');
    } else {
        set_flash_message('Invitation not found', 'danger');
    }
    
    header('Location: admin.php');
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
                        <a class="nav-link active" href="admin.php">Dashboard</a>
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

    <!-- Dashboard Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Admin Dashboard</h1>
                    <a href="create_invitation.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Invitation
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="stats-number"><?php echo count($users); ?></div>
                        <div class="stats-label">Total Users</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="stats-number"><?php echo $active_invitations; ?></div>
                        <div class="stats-label">Active Invitations</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="stats-number"><?php echo $used_invitations; ?></div>
                        <div class="stats-label">Used Invitations</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="stats-number"><?php echo $expired_invitations; ?></div>
                        <div class="stats-label">Expired Invitations</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invitations Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Invitations</h3>
            </div>
            <div class="card-body">
                <?php if (empty($invitations)): ?>
                <div class="text-center py-4">
                    <p class="mb-0">No invitations found. Create your first invitation!</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Email</th>
                                <th>Created</th>
                                <th>Expires</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invitations as $invitation): ?>
                            <tr>
                                <td><code><?php echo $invitation['code']; ?></code></td>
                                <td><?php echo $invitation['email'] ?? 'N/A'; ?></td>
                                <td><?php echo isset($invitation['created']) ? format_date($invitation['created']) : 'N/A'; ?></td>
                                <td><?php echo isset($invitation['expires']) ? format_date($invitation['expires']) : 'Never'; ?></td>
                                <td>
                                    <?php if (isset($invitation['used']) && $invitation['used']): ?>
                                    <span class="badge bg-success">Used</span>
                                    <?php elseif (isset($invitation['expires']) && strtotime($invitation['expires']) < time()): ?>
                                    <span class="badge bg-danger">Expired</span>
                                    <?php else: ?>
                                    <span class="badge bg-primary">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="invitation.php?code=<?php echo $invitation['code']; ?>" class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="admin.php?delete=<?php echo $invitation['code']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this invitation?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card mt-4">
            <div class="card-header">
                <h3 class="mb-0">Users</h3>
            </div>
            <div class="card-body">
                <?php if (empty($users)): ?>
                <div class="text-center py-4">
                    <p class="mb-0">No users have joined yet.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Plex Username</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['name']; ?></td>
                                <td><?php echo $user['email']; ?></td>
                                <td><?php echo $user['plex_username'] ?? 'N/A'; ?></td>
                                <td><?php echo isset($user['joined']) ? format_date($user['joined']) : 'N/A'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
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