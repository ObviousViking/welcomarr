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
        'libraries' => [
            ['id' => 'movies', 'name' => 'Movies'],
            ['id' => 'tvshows', 'name' => 'TV Shows'],
            ['id' => 'music', 'name' => 'Music']
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
    
    // Check if invitation has reached its usage limit
    if (isset($invitation['usage_limit']) && isset($invitation['usage_count']) && $invitation['usage_count'] >= $invitation['usage_limit']) {
        return false;
    }
    
    // Check if invitation is expired
    if (isset($invitation['expires']) && strtotime($invitation['expires']) < time()) {
        return false;
    }
    
    return true;
}

// Add user to Plex server
function add_user_to_plex($plex_username, $libraries = []) {
    $data = load_data();
    $settings = $data['settings'];
    
    // Check if Plex token is set
    if (empty($settings['plex_token'])) {
        return [
            'success' => false,
            'message' => 'Plex token is not configured'
        ];
    }
    
    // Prepare API request to share libraries with the user
    $url = 'https://plex.tv/api/v2/shared_servers';
    $headers = [
        'X-Plex-Token: ' . $settings['plex_token'],
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    // Get the server ID (machine identifier)
    $server_id = get_plex_server_id($settings['plex_token']);
    if (!$server_id) {
        return [
            'success' => false,
            'message' => 'Could not retrieve Plex server ID'
        ];
    }
    
    // Get the user ID from the username
    $user_id = get_plex_user_id($plex_username, $settings['plex_token']);
    if (!$user_id) {
        return [
            'success' => false,
            'message' => 'Could not find Plex user: ' . $plex_username
        ];
    }
    
    // Prepare the library section IDs
    $section_ids = [];
    if (!empty($libraries)) {
        // Get all library sections
        $sections = get_plex_library_sections($settings['plex_token']);
        
        // Filter sections based on selected libraries
        foreach ($sections as $section) {
            if (in_array($section['id'], $libraries)) {
                $section_ids[] = (int)$section['id'];
            }
        }
    }
    
    // If no libraries were selected, share all libraries
    if (empty($section_ids)) {
        $sections = get_plex_library_sections($settings['plex_token']);
        foreach ($sections as $section) {
            $section_ids[] = (int)$section['id'];
        }
    }
    
    // Prepare the request data
    $post_data = [
        'machineIdentifier' => $server_id,
        'invitedId' => $user_id,
        'librarySectionIds' => $section_ids
    ];
    
    // Initialize cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    
    // Execute the request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Check if the request was successful
    if ($http_code >= 200 && $http_code < 300) {
        return [
            'success' => true,
            'message' => 'User added to Plex server successfully'
        ];
    } else {
        $error_message = 'Failed to add user to Plex server. HTTP Code: ' . $http_code;
        if (!empty($error)) {
            $error_message .= ' - cURL Error: ' . $error;
        }
        if (!empty($response)) {
            $error_message .= ' - Response: ' . $response;
        }
        
        return [
            'success' => false,
            'message' => $error_message
        ];
    }
}

// Get Plex server ID (machine identifier)
function get_plex_server_id($token) {
    $url = 'https://plex.tv/api/v2/resources?includeHttps=1&X-Plex-Token=' . $token;
    $headers = [
        'Accept: application/json'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        foreach ($data as $resource) {
            if ($resource['provides'] == 'server') {
                return $resource['clientIdentifier'];
            }
        }
    }
    
    return null;
}

// Get Plex user ID from username
function get_plex_user_id($username, $token) {
    $url = 'https://plex.tv/api/v2/users?X-Plex-Token=' . $token;
    $headers = [
        'Accept: application/json'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        foreach ($data as $user) {
            if ($user['username'] == $username || $user['email'] == $username) {
                return $user['id'];
            }
        }
    }
    
    return null;
}

// Get Plex library sections
function get_plex_library_sections($token) {
    $server_id = get_plex_server_id($token);
    if (!$server_id) {
        return [];
    }
    
    // First try the Plex.tv API
    $url = 'https://plex.tv/api/v2/servers/' . $server_id . '/libraries?X-Plex-Token=' . $token;
    $headers = [
        'Accept: application/json'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if (!empty($data)) {
            return $data;
        }
    }
    
    // If that fails, try direct server connection if URL is configured
    $data = load_data();
    if (!empty($data['settings']['plex_url'])) {
        $url = rtrim($data['settings']['plex_url'], '/') . '/library/sections?X-Plex-Token=' . $token;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['MediaContainer']) && isset($data['MediaContainer']['Directory'])) {
                $libraries = [];
                foreach ($data['MediaContainer']['Directory'] as $dir) {
                    $libraries[] = [
                        'id' => $dir['key'],
                        'title' => $dir['title'],
                        'type' => $dir['type']
                    ];
                }
                return $libraries;
            }
        }
    }
    
    // If all else fails, return empty array
    return [];
}

// Get base URL
function get_base_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = dirname($_SERVER['SCRIPT_NAME']);
    $base_url = $protocol . '://' . $host . $script;
    if (substr($base_url, -1) !== '/') {
        $base_url .= '/';
    }
    return $base_url . 'index.php';
}