<?php
// Database configuration (using JSON file for simplicity)
define('DATA_FILE', __DIR__ . '/data/data.json');
define('DATA_DIR', __DIR__ . '/data');

// Application settings
define('APP_NAME', 'Welcomarr');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost:56112'); // Change this to your domain

// Start session
session_start();

// Create data directory if it doesn't exist
if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// Initialize data file if it doesn't exist
if (!file_exists(DATA_FILE)) {
    $default_data = [
        'admin' => [
            'username' => 'admin',
            'password' => password_hash('admin', PASSWORD_DEFAULT), // Hashed password
            'email' => 'admin@example.com'
        ],
        'settings' => [
            'plex_server' => '',
            'plex_token' => '',
            'plex_url' => '',
            'welcome_message' => 'Welcome to our Plex server! Follow the steps below to get started.',
            'site_name' => 'Welcomarr',
            'theme_color' => '#e5a00d'
        ],
        'invitations' => [],
        'users' => []
    ];
    
    file_put_contents(DATA_FILE, json_encode($default_data, JSON_PRETTY_PRINT));
}

// Helper functions
function load_data() {
    if (file_exists(DATA_FILE)) {
        return json_decode(file_get_contents(DATA_FILE), true);
    }
    return null;
}

function save_data($data) {
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

// Flash message functions
function set_flash_message($message, $type = 'success') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

// Authentication functions
function is_logged_in() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function require_login() {
    if (!is_logged_in()) {
        set_flash_message('Please login to access this page', 'warning');
        header('Location: login.php');
        exit;
    }
}

// Generate a unique invitation code
function generate_invitation_code($length = 8) {
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $code;
}

// Format date
function format_date($date) {
    return date('F j, Y', strtotime($date));
}

// Get invitation by code
function get_invitation_by_code($code) {
    $data = load_data();
    foreach ($data['invitations'] as $key => $invitation) {
        if ($invitation['code'] === $code) {
            return [
                'invitation' => $invitation,
                'index' => $key
            ];
        }
    }
    return null;
}

// Check if invitation is valid
function is_invitation_valid($invitation) {
    if (!$invitation) {
        return false;
    }
    
    // Check if invitation is used
    if (isset($invitation['used']) && $invitation['used']) {
        return false;
    }
    
    // Check if invitation is expired
    if (isset($invitation['expires']) && strtotime($invitation['expires']) < time()) {
        return false;
    }
    
    return true;
}