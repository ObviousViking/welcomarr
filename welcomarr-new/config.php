<?php
// Include database functions
require_once __DIR__ . '/db.php';

// Application settings
define('APP_NAME', 'Welcomarr');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost:56112'); // Change this to your domain

// Start session
session_start();

// Helper functions
function get_settings() {
    $db = get_db_connection();
    $settings = [];
    
    $stmt = $db->prepare('SELECT key, value FROM settings');
    $result = $stmt->execute();
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $settings[$row['key']] = $row['value'];
    }
    
    return $settings;
}

function update_settings($settings) {
    $db = get_db_connection();
    $stmt = $db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :value)');
    
    foreach ($settings as $key => $value) {
        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        $stmt->bindValue(':value', $value, SQLITE3_TEXT);
        $stmt->execute();
        $stmt->reset();
    }
    
    return true;
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
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        set_flash_message('Please login to access this page', 'warning');
        header('Location: login.php');
        exit;
    }
}

function login($username, $password) {
    $db = get_db_connection();
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = :key');
    
    // Get admin username
    $stmt->bindValue(':key', 'admin_username', SQLITE3_TEXT);
    $result = $stmt->execute();
    $admin_username = $result->fetchArray(SQLITE3_ASSOC)['value'] ?? 'admin';
    
    // Get admin password hash
    $stmt->reset();
    $stmt->bindValue(':key', 'admin_password', SQLITE3_TEXT);
    $result = $stmt->execute();
    $admin_password = $result->fetchArray(SQLITE3_ASSOC)['value'] ?? '';
    
    if ($username === $admin_username && password_verify($password, $admin_password)) {
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = $admin_username;
        return true;
    }
    
    return false;
}

function logout() {
    unset($_SESSION['user_id']);
    unset($_SESSION['username']);
    session_destroy();
}

// Generate a unique invitation code
function generate_invitation_code($length = 8) {
    $db = get_db_connection();
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    
    do {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        // Check if code already exists
        $stmt = $db->prepare('SELECT COUNT(*) FROM invitations WHERE code = :code');
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $result = $stmt->execute();
        $count = $result->fetchArray(SQLITE3_NUM)[0];
        
    } while ($count > 0);
    
    return $code;
}

// Format date
function format_date($date) {
    return date('F j, Y', strtotime($date));
}

// Get invitation by code
function get_invitation_by_code($code) {
    if (empty($code)) {
        return null;
    }
    
    $db = get_db_connection();
    $stmt = $db->prepare('SELECT * FROM invitations WHERE code = :code LIMIT 1');
    $stmt->bindValue(':code', $code, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    $invitation = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($invitation) {
        // Get libraries for this invitation
        $libraries_stmt = $db->prepare('
            SELECT l.library_id 
            FROM invitation_libraries il
            JOIN libraries l ON il.library_id = l.id
            WHERE il.invitation_id = :invitation_id
        ');
        $libraries_stmt->bindValue(':invitation_id', $invitation['id'], SQLITE3_INTEGER);
        $libraries_result = $libraries_stmt->execute();
        
        $libraries = [];
        while ($lib = $libraries_result->fetchArray(SQLITE3_ASSOC)) {
            $libraries[] = $lib['library_id'];
        }
        
        $invitation['libraries'] = $libraries;
    }
    
    return $invitation;
}

// Check if invitation is valid
function is_invitation_valid($invitation) {
    if (!$invitation) {
        return false;
    }
    
    // Check if invitation has expired
    if (!empty($invitation['expires']) && strtotime($invitation['expires']) < time()) {
        return false;
    }
    
    // Check if invitation has reached usage limit
    if (isset($invitation['usage_limit']) && $invitation['usage_limit'] > 0 && 
        isset($invitation['usage_count']) && $invitation['usage_count'] >= $invitation['usage_limit']) {
        return false;
    }
    
    return true;
}

// Update invitation usage count
function update_invitation_usage($code, $username = null) {
    $db = get_db_connection();
    $stmt = $db->prepare('
        UPDATE invitations 
        SET usage_count = usage_count + 1,
            last_used_at = :last_used_at,
            last_used_by = :last_used_by
        WHERE code = :code
    ');
    
    $stmt->bindValue(':code', $code, SQLITE3_TEXT);
    $stmt->bindValue(':last_used_at', date('Y-m-d H:i:s'), SQLITE3_TEXT);
    $stmt->bindValue(':last_used_by', $username, SQLITE3_TEXT);
    
    return $stmt->execute();
}

// Get all invitations
function get_invitations() {
    $db = get_db_connection();
    $stmt = $db->prepare('SELECT * FROM invitations ORDER BY created DESC');
    $result = $stmt->execute();
    
    $invitations = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Get libraries for this invitation
        $libraries_stmt = $db->prepare('
            SELECT l.library_id, l.name
            FROM invitation_libraries il
            JOIN libraries l ON il.library_id = l.id
            WHERE il.invitation_id = :invitation_id
        ');
        $libraries_stmt->bindValue(':invitation_id', $row['id'], SQLITE3_INTEGER);
        $libraries_result = $libraries_stmt->execute();
        
        $libraries = [];
        while ($lib = $libraries_result->fetchArray(SQLITE3_ASSOC)) {
            $libraries[] = $lib;
        }
        
        $row['libraries'] = $libraries;
        $invitations[] = $row;
    }
    
    return $invitations;
}

// Create a new invitation
function create_invitation($invitation_data) {
    $db = get_db_connection();
    
    // Generate a unique code if not provided
    if (empty($invitation_data['code'])) {
        $invitation_data['code'] = generate_invitation_code();
    }
    
    // Set created date if not provided
    if (empty($invitation_data['created'])) {
        $invitation_data['created'] = date('Y-m-d H:i:s');
    }
    
    $stmt = $db->prepare('
        INSERT INTO invitations (
            code, name, email, created, expires, usage_limit, usage_count
        ) VALUES (
            :code, :name, :email, :created, :expires, :usage_limit, :usage_count
        )
    ');
    
    $stmt->bindValue(':code', $invitation_data['code'], SQLITE3_TEXT);
    $stmt->bindValue(':name', $invitation_data['name'], SQLITE3_TEXT);
    $stmt->bindValue(':email', $invitation_data['email'] ?? null, SQLITE3_TEXT);
    $stmt->bindValue(':created', $invitation_data['created'], SQLITE3_TEXT);
    $stmt->bindValue(':expires', $invitation_data['expires'] ?? null, SQLITE3_TEXT);
    $stmt->bindValue(':usage_limit', $invitation_data['usage_limit'] ?? 1, SQLITE3_INTEGER);
    $stmt->bindValue(':usage_count', 0, SQLITE3_INTEGER);
    
    $result = $stmt->execute();
    
    if ($result) {
        $invitation_id = $db->lastInsertRowID();
        
        // Add libraries if provided
        if (!empty($invitation_data['libraries'])) {
            $lib_stmt = $db->prepare('
                INSERT INTO invitation_libraries (invitation_id, library_id)
                SELECT :invitation_id, id FROM libraries WHERE library_id = :library_id
            ');
            
            foreach ($invitation_data['libraries'] as $library_id) {
                $lib_stmt->bindValue(':invitation_id', $invitation_id, SQLITE3_INTEGER);
                $lib_stmt->bindValue(':library_id', $library_id, SQLITE3_TEXT);
                $lib_stmt->execute();
                $lib_stmt->reset();
            }
        }
        
        return true;
    }
    
    return false;
}

// Delete an invitation
function delete_invitation($code) {
    $db = get_db_connection();
    $stmt = $db->prepare('DELETE FROM invitations WHERE code = :code');
    $stmt->bindValue(':code', $code, SQLITE3_TEXT);
    return $stmt->execute();
}

// Add user to Plex server
function add_user_to_plex($plex_username, $libraries = []) {
    $db = get_db_connection();
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = :key');
    
    // Get Plex token
    $stmt->bindValue(':key', 'plex_token', SQLITE3_TEXT);
    $result = $stmt->execute();
    $plex_token = $result->fetchArray(SQLITE3_ASSOC)['value'] ?? '';
    
    // Check if Plex token is set
    if (empty($plex_token)) {
        return [
            'success' => false,
            'message' => 'Plex token is not configured'
        ];
    }
    
    // Prepare API request to share libraries with the user
    $url = 'https://plex.tv/api/v2/shared_servers';
    $headers = [
        'X-Plex-Token: ' . $plex_token,
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    // Get the server ID (machine identifier)
    $server_id = get_plex_server_id($plex_token);
    if (!$server_id) {
        return [
            'success' => false,
            'message' => 'Could not retrieve Plex server ID'
        ];
    }
    
    // Get the user ID from the username
    $user_id = get_plex_user_id($plex_username, $plex_token);
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
        $sections = get_plex_library_sections($plex_token);
        
        // Filter sections based on selected libraries
        foreach ($sections as $section) {
            if (in_array($section['id'], $libraries)) {
                $section_ids[] = (int)$section['id'];
            }
        }
    }
    
    // If no libraries were selected, share all libraries
    if (empty($section_ids)) {
        $sections = get_plex_library_sections($plex_token);
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

// Get Plex library sections with improved connection methods
function get_plex_library_sections($token) {
    $server_id = get_plex_server_id($token);
    if (!$server_id) {
        return [];
    }
    
    $libraries = [];
    
    // Method 1: Try the Plex.tv API
    $url = 'https://plex.tv/api/v2/servers/' . $server_id . '/libraries?X-Plex-Token=' . $token;
    $headers = [
        'Accept: application/json'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 second timeout
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response && $http_code >= 200 && $http_code < 300) {
        $data = json_decode($response, true);
        if (!empty($data)) {
            return $data;
        }
    }
    
    // Method 2: Try direct server connection if URL is configured
    $db = get_db_connection();
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = :key');
    $stmt->bindValue(':key', 'plex_url', SQLITE3_TEXT);
    $result = $stmt->execute();
    $plex_url = $result->fetchArray(SQLITE3_ASSOC)['value'] ?? '';
    
    if (!empty($plex_url)) {
        // Try multiple endpoints
        $endpoints = [
            '/library/sections',
            '/library/sections/',
            '/library/sections/all'
        ];
        
        foreach ($endpoints as $endpoint) {
            $url = rtrim($plex_url, '/') . $endpoint . '?X-Plex-Token=' . $token;
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 second timeout
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($response && $http_code >= 200 && $http_code < 300) {
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
    }
    
    // Method 3: Try to discover Plex server on local network
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = :key');
    $stmt->bindValue(':key', 'plex_server_discovery', SQLITE3_TEXT);
    $result = $stmt->execute();
    $discovery_enabled = $result->fetchArray(SQLITE3_ASSOC)['value'] ?? 'false';
    
    if ($discovery_enabled === 'true') {
        // Try common local addresses
        $local_addresses = [
            'http://localhost:32400',
            'http://127.0.0.1:32400',
            'https://localhost:32400',
            'https://127.0.0.1:32400'
        ];
        
        foreach ($local_addresses as $address) {
            $url = $address . '/library/sections?X-Plex-Token=' . $token;
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Short timeout for local discovery
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Don't verify SSL for local connections
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($response && $http_code >= 200 && $http_code < 300) {
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
                    
                    // Save this working URL for future use
                    $stmt = $db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :value)');
                    $stmt->bindValue(':key', 'plex_url', SQLITE3_TEXT);
                    $stmt->bindValue(':value', $address, SQLITE3_TEXT);
                    $stmt->execute();
                    
                    return $libraries;
                }
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