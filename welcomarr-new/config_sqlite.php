<?php
// Database configuration (using SQLite)
define('DB_FILE', __DIR__ . '/data/welcomarr.db');
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

// Initialize database connection
function get_db_connection() {
    static $db = null;
    
    if ($db === null) {
        $db = new SQLite3(DB_FILE);
        $db->exec('PRAGMA foreign_keys = ON');
    }
    
    return $db;
}

// Initialize database tables if they don't exist
function init_database() {
    $db = get_db_connection();
    
    // Create admin table
    $db->exec('CREATE TABLE IF NOT EXISTS admin (
        id INTEGER PRIMARY KEY,
        username TEXT NOT NULL,
        password TEXT NOT NULL,
        email TEXT
    )');
    
    // Create settings table
    $db->exec('CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY,
        plex_server TEXT,
        plex_token TEXT,
        plex_url TEXT,
        welcome_message TEXT,
        site_name TEXT,
        theme_color TEXT
    )');
    
    // Create libraries table
    $db->exec('CREATE TABLE IF NOT EXISTS libraries (
        id INTEGER PRIMARY KEY,
        library_id TEXT NOT NULL,
        name TEXT NOT NULL
    )');
    
    // Create invitations table
    $db->exec('CREATE TABLE IF NOT EXISTS invitations (
        id INTEGER PRIMARY KEY,
        code TEXT NOT NULL UNIQUE,
        name TEXT,
        email TEXT,
        created DATETIME NOT NULL,
        expires DATETIME,
        usage_count INTEGER DEFAULT 0,
        usage_limit INTEGER DEFAULT 1,
        last_used_by TEXT,
        last_used_at DATETIME
    )');
    
    // Create invitation_libraries table (for many-to-many relationship)
    $db->exec('CREATE TABLE IF NOT EXISTS invitation_libraries (
        invitation_id INTEGER,
        library_id INTEGER,
        PRIMARY KEY (invitation_id, library_id),
        FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE,
        FOREIGN KEY (library_id) REFERENCES libraries(id) ON DELETE CASCADE
    )');
    
    // Create users table
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        email TEXT,
        plex_username TEXT NOT NULL,
        joined DATETIME NOT NULL,
        invitation_code TEXT,
        FOREIGN KEY (invitation_code) REFERENCES invitations(code)
    )');
    
    // Create user_libraries table (for many-to-many relationship)
    $db->exec('CREATE TABLE IF NOT EXISTS user_libraries (
        user_id INTEGER,
        library_id INTEGER,
        PRIMARY KEY (user_id, library_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (library_id) REFERENCES libraries(id) ON DELETE CASCADE
    )');
    
    // Insert default admin if not exists
    $stmt = $db->prepare('SELECT COUNT(*) FROM admin');
    $result = $stmt->execute();
    $count = $result->fetchArray(SQLITE3_NUM)[0];
    
    if ($count == 0) {
        $stmt = $db->prepare('INSERT INTO admin (username, password, email) VALUES (:username, :password, :email)');
        $stmt->bindValue(':username', 'admin', SQLITE3_TEXT);
        $stmt->bindValue(':password', password_hash('admin', PASSWORD_DEFAULT), SQLITE3_TEXT);
        $stmt->bindValue(':email', 'admin@example.com', SQLITE3_TEXT);
        $stmt->execute();
    }
    
    // Insert default settings if not exists
    $stmt = $db->prepare('SELECT COUNT(*) FROM settings');
    $result = $stmt->execute();
    $count = $result->fetchArray(SQLITE3_NUM)[0];
    
    if ($count == 0) {
        $stmt = $db->prepare('INSERT INTO settings (plex_server, plex_token, plex_url, welcome_message, site_name, theme_color) 
                             VALUES (:plex_server, :plex_token, :plex_url, :welcome_message, :site_name, :theme_color)');
        $stmt->bindValue(':plex_server', '', SQLITE3_TEXT);
        $stmt->bindValue(':plex_token', '', SQLITE3_TEXT);
        $stmt->bindValue(':plex_url', '', SQLITE3_TEXT);
        $stmt->bindValue(':welcome_message', 'Welcome to our Plex server! Follow the steps below to get started.', SQLITE3_TEXT);
        $stmt->bindValue(':site_name', 'Welcomarr', SQLITE3_TEXT);
        $stmt->bindValue(':theme_color', '#e5a00d', SQLITE3_TEXT);
        $stmt->execute();
    }
}

// Initialize database
init_database();

// Helper functions
function get_settings() {
    $db = get_db_connection();
    $stmt = $db->prepare('SELECT * FROM settings LIMIT 1');
    $result = $stmt->execute();
    return $result->fetchArray(SQLITE3_ASSOC);
}

function update_settings($settings) {
    $db = get_db_connection();
    $stmt = $db->prepare('UPDATE settings SET 
                         plex_server = :plex_server,
                         plex_token = :plex_token,
                         plex_url = :plex_url,
                         welcome_message = :welcome_message,
                         site_name = :site_name,
                         theme_color = :theme_color
                         WHERE id = 1');
    
    $stmt->bindValue(':plex_server', $settings['plex_server'], SQLITE3_TEXT);
    $stmt->bindValue(':plex_token', $settings['plex_token'], SQLITE3_TEXT);
    $stmt->bindValue(':plex_url', $settings['plex_url'], SQLITE3_TEXT);
    $stmt->bindValue(':welcome_message', $settings['welcome_message'], SQLITE3_TEXT);
    $stmt->bindValue(':site_name', $settings['site_name'], SQLITE3_TEXT);
    $stmt->bindValue(':theme_color', $settings['theme_color'], SQLITE3_TEXT);
    
    return $stmt->execute();
}

function get_admin() {
    $db = get_db_connection();
    $stmt = $db->prepare('SELECT * FROM admin LIMIT 1');
    $result = $stmt->execute();
    return $result->fetchArray(SQLITE3_ASSOC);
}

function update_admin($admin) {
    $db = get_db_connection();
    $stmt = $db->prepare('UPDATE admin SET 
                         username = :username,
                         password = :password,
                         email = :email
                         WHERE id = 1');
    
    $stmt->bindValue(':username', $admin['username'], SQLITE3_TEXT);
    $stmt->bindValue(':password', $admin['password'], SQLITE3_TEXT);
    $stmt->bindValue(':email', $admin['email'], SQLITE3_TEXT);
    
    return $stmt->execute();
}

function get_libraries() {
    $db = get_db_connection();
    $stmt = $db->prepare('SELECT * FROM libraries ORDER BY name');
    $result = $stmt->execute();
    
    $libraries = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $libraries[] = $row;
    }
    
    return $libraries;
}

function update_libraries($libraries) {
    $db = get_db_connection();
    
    // Clear existing libraries
    $db->exec('DELETE FROM libraries');
    
    // Insert new libraries
    $stmt = $db->prepare('INSERT INTO libraries (library_id, name) VALUES (:library_id, :name)');
    
    foreach ($libraries as $library) {
        $stmt->bindValue(':library_id', $library['library_id'], SQLITE3_TEXT);
        $stmt->bindValue(':name', $library['name'], SQLITE3_TEXT);
        $stmt->execute();
        $stmt->reset();
    }
    
    return true;
}

function get_invitations() {
    $db = get_db_connection();
    $stmt = $db->prepare('SELECT * FROM invitations ORDER BY created DESC');
    $result = $stmt->execute();
    
    $invitations = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Get libraries for this invitation
        $libraries_stmt = $db->prepare('
            SELECT l.library_id 
            FROM invitation_libraries il
            JOIN libraries l ON il.library_id = l.id
            WHERE il.invitation_id = :invitation_id
        ');
        $libraries_stmt->bindValue(':invitation_id', $row['id'], SQLITE3_INTEGER);
        $libraries_result = $libraries_stmt->execute();
        
        $libraries = [];
        while ($lib = $libraries_result->fetchArray(SQLITE3_ASSOC)) {
            $libraries[] = $lib['library_id'];
        }
        
        $row['libraries'] = $libraries;
        $invitations[] = $row;
    }
    
    return $invitations;
}

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

function create_invitation($invitation_data) {
    $db = get_db_connection();
    
    // Generate a unique code if not provided
    if (empty($invitation_data['code'])) {
        $invitation_data['code'] = generate_unique_code();
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
    $stmt->bindValue(':name', $invitation_data['name'] ?? null, SQLITE3_TEXT);
    $stmt->bindValue(':email', $invitation_data['email'] ?? null, SQLITE3_TEXT);
    $stmt->bindValue(':created', $invitation_data['created'], SQLITE3_TEXT);
    $stmt->bindValue(':expires', $invitation_data['expires'] ?? null, SQLITE3_TEXT);
    $stmt->bindValue(':usage_limit', $invitation_data['usage_limit'] ?? 1, SQLITE3_INTEGER);
    $stmt->bindValue(':usage_count', $invitation_data['usage_count'] ?? 0, SQLITE3_INTEGER);
    
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

function delete_invitation($code) {
    $db = get_db_connection();
    $stmt = $db->prepare('DELETE FROM invitations WHERE code = :code');
    $stmt->bindValue(':code', $code, SQLITE3_TEXT);
    return $stmt->execute();
}

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
    
    $result = $stmt->execute();
    
    if ($result) {
        error_log("Updated invitation usage for code: $code, used by: $username");
        return true;
    } else {
        error_log("Failed to update invitation usage for code: $code");
        return false;
    }
}

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

function add_user($user_data) {
    $db = get_db_connection();
    
    $stmt = $db->prepare('
        INSERT INTO users (
            name, email, plex_username, joined, invitation_code
        ) VALUES (
            :name, :email, :plex_username, :joined, :invitation_code
        )
    ');
    
    $stmt->bindValue(':name', $user_data['name'], SQLITE3_TEXT);
    $stmt->bindValue(':email', $user_data['email'] ?? null, SQLITE3_TEXT);
    $stmt->bindValue(':plex_username', $user_data['plex_username'], SQLITE3_TEXT);
    $stmt->bindValue(':joined', $user_data['joined'] ?? date('Y-m-d H:i:s'), SQLITE3_TEXT);
    $stmt->bindValue(':invitation_code', $user_data['invitation_code'] ?? null, SQLITE3_TEXT);
    
    $result = $stmt->execute();
    
    if ($result) {
        $user_id = $db->lastInsertRowID();
        
        // Add libraries if provided
        if (!empty($user_data['libraries'])) {
            $lib_stmt = $db->prepare('
                INSERT INTO user_libraries (user_id, library_id)
                SELECT :user_id, id FROM libraries WHERE library_id = :library_id
            ');
            
            foreach ($user_data['libraries'] as $library_id) {
                $lib_stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                $lib_stmt->bindValue(':library_id', $library_id, SQLITE3_TEXT);
                $lib_stmt->execute();
                $lib_stmt->reset();
            }
        }
        
        return true;
    }
    
    return false;
}

function get_users() {
    $db = get_db_connection();
    $stmt = $db->prepare('SELECT * FROM users ORDER BY joined DESC');
    $result = $stmt->execute();
    
    $users = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Get libraries for this user
        $libraries_stmt = $db->prepare('
            SELECT l.library_id, l.name
            FROM user_libraries ul
            JOIN libraries l ON ul.library_id = l.id
            WHERE ul.user_id = :user_id
        ');
        $libraries_stmt->bindValue(':user_id', $row['id'], SQLITE3_INTEGER);
        $libraries_result = $libraries_stmt->execute();
        
        $libraries = [];
        while ($lib = $libraries_result->fetchArray(SQLITE3_ASSOC)) {
            $libraries[] = $lib;
        }
        
        $row['libraries'] = $libraries;
        $users[] = $row;
    }
    
    return $users;
}

// Authentication functions
function login($username, $password) {
    $admin = get_admin();
    
    if ($admin && $admin['username'] === $username && password_verify($password, $admin['password'])) {
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['username'] = $admin['username'];
        return true;
    }
    
    return false;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function logout() {
    unset($_SESSION['user_id']);
    unset($_SESSION['username']);
    session_destroy();
}

// Flash messages
function set_flash_message($message, $type = 'info') {
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => $type
    ];
}

function get_flash_message() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    
    return null;
}

// Utility functions
function generate_unique_code($length = 8) {
    $db = get_db_connection();
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    
    do {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        // Check if code already exists
        $stmt = $db->prepare('SELECT COUNT(*) FROM invitations WHERE code = :code');
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $result = $stmt->execute();
        $count = $result->fetchArray(SQLITE3_NUM)[0];
        
    } while ($count > 0);
    
    return $code;
}

function format_date($date_string) {
    return date('M j, Y', strtotime($date_string));
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

// Plex API functions
function add_user_to_plex($username, $libraries = []) {
    $settings = get_settings();
    $token = $settings['plex_token'];
    
    if (empty($token)) {
        error_log("Plex token not configured");
        return [
            'success' => false,
            'message' => 'Plex token not configured'
        ];
    }
    
    // Get user ID from Plex
    error_log("Looking up Plex user ID for username: $username");
    $user_id = get_plex_user_id($username, $token);
    if (!$user_id) {
        error_log("User not found on Plex: $username");
        return [
            'success' => false,
            'message' => 'User not found on Plex'
        ];
    }
    error_log("Found Plex user ID: $user_id for username: $username");
    
    // Get server ID
    error_log("Getting Plex server ID");
    $server_id = get_plex_server_id($token);
    if (!$server_id) {
        error_log("Plex server not found");
        return [
            'success' => false,
            'message' => 'Plex server not found'
        ];
    }
    error_log("Found Plex server ID: $server_id");
    
    // Add user to server
    $url = 'https://plex.tv/api/v2/shared_servers';
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'X-Plex-Token: ' . $token
    ];
    
    // Prepare library section IDs
    $section_ids = [];
    if (!empty($libraries)) {
        error_log("Getting library sections to share specific libraries: " . implode(', ', $libraries));
        $all_sections = get_plex_library_sections($token);
        foreach ($all_sections as $section) {
            if (in_array($section['id'], $libraries)) {
                $section_ids[] = $section['id'];
                error_log("Adding library to share: {$section['id']} ({$section['title']})");
            }
        }
    }
    
    // If no specific libraries are selected, share all
    if (empty($section_ids)) {
        error_log("No specific libraries selected, sharing all libraries");
        $all_sections = get_plex_library_sections($token);
        foreach ($all_sections as $section) {
            $section_ids[] = $section['id'];
            error_log("Adding library to share: {$section['id']} ({$section['title']})");
        }
    }
    
    if (empty($section_ids)) {
        error_log("No libraries found to share");
        return [
            'success' => false,
            'message' => 'No libraries found to share'
        ];
    }
    
    $post_data = [
        'machineIdentifier' => $server_id,
        'invitedId' => $user_id,
        'librarySectionIds' => $section_ids
    ];
    
    error_log("Sending request to Plex API to share server: " . json_encode($post_data));
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Execute the request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Check if the request was successful
    if ($http_code >= 200 && $http_code < 300) {
        error_log("Successfully added user to Plex server");
        return [
            'success' => true,
            'message' => 'User added to Plex server successfully'
        ];
    } else {
        error_log("Failed to add user to Plex server: HTTP $http_code, Error: $curl_error, Response: $response");
        return [
            'success' => false,
            'message' => "Failed to add user to Plex server (HTTP $http_code): " . ($response ?: $curl_error)
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
            if (isset($resource['provides']) && $resource['provides'] == 'server') {
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
    $settings = get_settings();
    $server_id = get_plex_server_id($token);
    
    if (!$server_id) {
        error_log("Failed to get Plex server ID");
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($response && $http_code >= 200 && $http_code < 300) {
        $data = json_decode($response, true);
        if (!empty($data)) {
            error_log("Successfully retrieved libraries from Plex.tv API");
            return $data;
        }
    } else {
        error_log("Plex.tv API request failed: $curl_error (HTTP code: $http_code)");
    }
    
    // Method 2: Try direct server connection if URL is configured
    if (!empty($settings['plex_url'])) {
        $url = rtrim($settings['plex_url'], '/') . '/library/sections?X-Plex-Token=' . $token;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($response && $http_code >= 200 && $http_code < 300) {
            $data = json_decode($response, true);
            if (isset($data['MediaContainer']) && isset($data['MediaContainer']['Directory'])) {
                foreach ($data['MediaContainer']['Directory'] as $dir) {
                    $libraries[] = [
                        'id' => $dir['key'],
                        'title' => $dir['title'],
                        'type' => $dir['type']
                    ];
                }
                if (!empty($libraries)) {
                    error_log("Successfully retrieved libraries from direct server connection");
                    return $libraries;
                }
            }
        } else {
            error_log("Direct server connection failed: $curl_error (HTTP code: $http_code)");
        }
    }
    
    // Method 3: Try alternative endpoint format
    if (!empty($settings['plex_url'])) {
        $url = rtrim($settings['plex_url'], '/') . '/library/sections?X-Plex-Token=' . $token . '&X-Plex-Container-Start=0&X-Plex-Container-Size=50';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($response && $http_code >= 200 && $http_code < 300) {
            $data = json_decode($response, true);
            if (!empty($data) && isset($data['MediaContainer'])) {
                // Different Plex versions might have different response formats
                $directories = isset($data['MediaContainer']['Directory']) ? 
                    $data['MediaContainer']['Directory'] : 
                    (isset($data['MediaContainer']['Metadata']) ? $data['MediaContainer']['Metadata'] : []);
                
                if (is_array($directories)) {
                    foreach ($directories as $dir) {
                        $libraries[] = [
                            'id' => $dir['key'] ?? $dir['id'] ?? '',
                            'title' => $dir['title'] ?? $dir['name'] ?? 'Unknown',
                            'type' => $dir['type'] ?? 'unknown'
                        ];
                    }
                    if (!empty($libraries)) {
                        error_log("Successfully retrieved libraries from alternative endpoint");
                        return $libraries;
                    }
                }
            }
        } else {
            error_log("Alternative endpoint request failed: $curl_error (HTTP code: $http_code)");
        }
    }
    
    error_log("Failed to retrieve any libraries from Plex");
    return [];
}

// Sync Plex libraries to local database
function sync_plex_libraries() {
    $settings = get_settings();
    $token = $settings['plex_token'];
    $plex_url = $settings['plex_url'] ?? '';
    
    if (empty($token)) {
        error_log("Plex token not configured");
        return [
            'success' => false,
            'message' => 'Plex token not configured'
        ];
    }
    
    // Get libraries from Plex
    $plex_libraries = get_plex_library_sections($token);
    
    if (empty($plex_libraries)) {
        error_log("No libraries found or could not connect to Plex");
        return [
            'success' => false,
            'message' => 'No libraries found or could not connect to Plex'
        ];
    }
    
    // Format libraries for database
    $libraries = [];
    foreach ($plex_libraries as $lib) {
        $libraries[] = [
            'library_id' => $lib['id'],
            'name' => $lib['title'] ?? $lib['name'] ?? $lib['id']
        ];
    }
    
    // Update libraries in database
    $result = update_libraries($libraries);
    
    if ($result) {
        return [
            'success' => true,
            'message' => 'Successfully synced ' . count($libraries) . ' libraries',
            'libraries' => $libraries
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to update libraries in database'
        ];
    }
}