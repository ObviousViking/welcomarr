<?php
/**
 * Database connection and utility functions for Welcomarr
 */

// Database file path
define('DB_FILE', __DIR__ . '/data/welcomarr.db');

/**
 * Get a database connection
 * 
 * @return SQLite3 Database connection
 */
function get_db_connection() {
    // Create data directory if it doesn't exist
    if (!file_exists(dirname(DB_FILE))) {
        mkdir(dirname(DB_FILE), 0755, true);
    }
    
    // Create database connection
    $db = new SQLite3(DB_FILE);
    $db->enableExceptions(true);
    
    // Set pragmas for better performance and safety
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('PRAGMA foreign_keys = ON');
    
    // Initialize database if needed
    init_database($db);
    
    return $db;
}

/**
 * Initialize the database schema if tables don't exist
 * 
 * @param SQLite3 $db Database connection
 */
function init_database($db) {
    // Create settings table
    $db->exec('
        CREATE TABLE IF NOT EXISTS settings (
            id INTEGER PRIMARY KEY,
            key TEXT UNIQUE NOT NULL,
            value TEXT
        )
    ');
    
    // Create invitations table
    $db->exec('
        CREATE TABLE IF NOT EXISTS invitations (
            id INTEGER PRIMARY KEY,
            code TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            email TEXT,
            created TEXT NOT NULL,
            expires TEXT,
            usage_limit INTEGER DEFAULT 1,
            usage_count INTEGER DEFAULT 0,
            last_used_by TEXT,
            last_used_at TEXT
        )
    ');
    
    // Create libraries table
    $db->exec('
        CREATE TABLE IF NOT EXISTS libraries (
            id INTEGER PRIMARY KEY,
            library_id TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            type TEXT,
            last_updated TEXT
        )
    ');
    
    // Create invitation_libraries table (many-to-many)
    $db->exec('
        CREATE TABLE IF NOT EXISTS invitation_libraries (
            invitation_id INTEGER,
            library_id INTEGER,
            PRIMARY KEY (invitation_id, library_id),
            FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE,
            FOREIGN KEY (library_id) REFERENCES libraries(id) ON DELETE CASCADE
        )
    ');
    
    // Create users table
    $db->exec('
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            email TEXT,
            plex_username TEXT NOT NULL,
            joined TEXT NOT NULL,
            invitation_code TEXT,
            plex_status TEXT,
            plex_message TEXT
        )
    ');
    
    // Create user_libraries table (many-to-many)
    $db->exec('
        CREATE TABLE IF NOT EXISTS user_libraries (
            user_id INTEGER,
            library_id INTEGER,
            PRIMARY KEY (user_id, library_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (library_id) REFERENCES libraries(id) ON DELETE CASCADE
        )
    ');
    
    // Initialize default settings if not already set
    init_default_settings($db);
}

/**
 * Initialize default settings if they don't exist
 * 
 * @param SQLite3 $db Database connection
 */
function init_default_settings($db) {
    $default_settings = [
        'site_name' => 'Welcomarr',
        'welcome_message' => 'Welcome to our Plex server! Please enter your information below to get started.',
        'admin_username' => 'admin',
        'admin_password' => password_hash('admin', PASSWORD_DEFAULT), // Default password, should be changed
        'plex_token' => '',
        'theme_color' => '#E5A00D',
        'allow_registration' => '1'
    ];
    
    $stmt = $db->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (:key, :value)');
    
    foreach ($default_settings as $key => $value) {
        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        $stmt->bindValue(':value', $value, SQLITE3_TEXT);
        $stmt->execute();
        $stmt->reset();
    }
}

/**
 * Get a setting value from the database
 * 
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value or default
 */
function get_setting($key, $default = null) {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare('SELECT value FROM settings WHERE key = :key LIMIT 1');
        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        $result = $stmt->execute();
        
        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            return $row['value'];
        }
    } catch (Exception $e) {
        error_log("Error getting setting $key: " . $e->getMessage());
    }
    
    return $default;
}

/**
 * Get all settings as an associative array
 * 
 * @return array Settings
 */
function get_settings() {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare('SELECT key, value FROM settings');
        $result = $stmt->execute();
        
        $settings = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $settings[$row['key']] = $row['value'];
        }
        
        return $settings;
    } catch (Exception $e) {
        error_log("Error getting settings: " . $e->getMessage());
        return [];
    }
}

/**
 * Update a setting value
 * 
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @return bool Success
 */
function update_setting($key, $value) {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :value)');
        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        $stmt->bindValue(':value', $value, SQLITE3_TEXT);
        $stmt->execute();
        
        return true;
    } catch (Exception $e) {
        error_log("Error updating setting $key: " . $e->getMessage());
        return false;
    }
}

/**
 * Update multiple settings at once
 * 
 * @param array $settings Associative array of settings
 * @return bool Success
 */
function update_settings($settings) {
    try {
        $db = get_db_connection();
        $db->exec('BEGIN TRANSACTION');
        
        $stmt = $db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :value)');
        
        foreach ($settings as $key => $value) {
            $stmt->bindValue(':key', $key, SQLITE3_TEXT);
            $stmt->bindValue(':value', $value, SQLITE3_TEXT);
            $stmt->execute();
            $stmt->reset();
        }
        
        $db->exec('COMMIT');
        return true;
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        error_log("Error updating settings: " . $e->getMessage());
        return false;
    }
}

/**
 * Update invitation usage information
 * 
 * @param string $code Invitation code
 * @param string $used_by Username or email of the person who used the invitation
 * @return bool Success
 */
function update_invitation_usage($code, $used_by) {
    try {
        $db = get_db_connection();
        
        // Start transaction
        $db->exec('BEGIN TRANSACTION');
        
        // Get current invitation data
        $stmt = $db->prepare('SELECT id, usage_count, usage_limit, expires FROM invitations WHERE code = :code LIMIT 1');
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $result = $stmt->execute();
        
        if ($invitation = $result->fetchArray(SQLITE3_ASSOC)) {
            $usage_count = $invitation['usage_count'] + 1;
            $now = date('Y-m-d H:i:s');
            
            // Check if invitation has expired
            if (!empty($invitation['expires']) && strtotime($invitation['expires']) < time()) {
                error_log("Failed to update invitation usage: invitation with code $code has expired");
                $db->exec('ROLLBACK');
                return false;
            }
            
            // Check if invitation has reached usage limit
            if ($invitation['usage_limit'] > 0 && $usage_count > $invitation['usage_limit']) {
                error_log("Failed to update invitation usage: invitation with code $code has reached usage limit");
                $db->exec('ROLLBACK');
                return false;
            }
            
            // Update invitation usage
            $update_stmt = $db->prepare('
                UPDATE invitations 
                SET usage_count = :usage_count, 
                    last_used_by = :last_used_by, 
                    last_used_at = :last_used_at 
                WHERE id = :id
            ');
            
            $update_stmt->bindValue(':usage_count', $usage_count, SQLITE3_INTEGER);
            $update_stmt->bindValue(':last_used_by', $used_by, SQLITE3_TEXT);
            $update_stmt->bindValue(':last_used_at', $now, SQLITE3_TEXT);
            $update_stmt->bindValue(':id', $invitation['id'], SQLITE3_INTEGER);
            
            $update_result = $update_stmt->execute();
            
            // Commit transaction
            $db->exec('COMMIT');
            
            error_log("Updated invitation usage for code $code by $used_by: count=$usage_count, time=$now");
            return true;
        } else {
            error_log("Failed to update invitation usage: invitation with code $code not found");
            $db->exec('ROLLBACK');
            return false;
        }
    } catch (Exception $e) {
        error_log("Error updating invitation usage for code $code: " . $e->getMessage());
        if (isset($db)) {
            $db->exec('ROLLBACK');
        }
        return false;
    }
}

/**
 * Check if an invitation is valid
 * 
 * @param string $code Invitation code
 * @return bool True if invitation is valid, false otherwise
 */
function is_invitation_valid($code) {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare('
            SELECT usage_count, usage_limit, expires 
            FROM invitations 
            WHERE code = :code LIMIT 1
        ');
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $result = $stmt->execute();
        
        if ($invitation = $result->fetchArray(SQLITE3_ASSOC)) {
            // Check if invitation has expired
            if (!empty($invitation['expires']) && strtotime($invitation['expires']) < time()) {
                return false;
            }
            
            // Check if invitation has reached usage limit
            if ($invitation['usage_limit'] > 0 && $invitation['usage_count'] >= $invitation['usage_limit']) {
                return false;
            }
            
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error checking invitation validity for code $code: " . $e->getMessage());
        return false;
    }
}

/**
 * Get libraries associated with an invitation
 * 
 * @param int $invitation_id Invitation ID
 * @return array Array of library IDs
 */
function get_invitation_libraries($invitation_id) {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare('
            SELECT l.library_id 
            FROM invitation_libraries il
            JOIN libraries l ON il.library_id = l.id
            WHERE il.invitation_id = :invitation_id
        ');
        $stmt->bindValue(':invitation_id', $invitation_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $libraries = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $libraries[] = $row['library_id'];
        }
        
        return $libraries;
    } catch (Exception $e) {
        error_log("Error getting libraries for invitation ID $invitation_id: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all available Plex libraries
 * 
 * @return array Array of libraries with id, title, and type
 */
function get_all_plex_libraries() {
    try {
        $plex_token = get_setting('plex_token');
        if (empty($plex_token)) {
            return [];
        }
        
        $libraries = get_plex_library_sections($plex_token);
        
        // Format libraries for display
        $formatted_libraries = [];
        foreach ($libraries as $library) {
            $formatted_libraries[] = [
                'id' => $library['id'],
                'title' => $library['title'],
                'type' => $library['type']
            ];
        }
        
        return $formatted_libraries;
    } catch (Exception $e) {
        error_log("Error getting Plex libraries: " . $e->getMessage());
        return [];
    }
}

/**
 * Create a new invitation
 * 
 * @param string $code Unique invitation code
 * @param string $name Name for the invitation
 * @param string $email Optional email address
 * @param array $libraries Array of library IDs to grant access to
 * @param int $usage_limit Maximum number of times the invitation can be used (0 for unlimited)
 * @param string $expires Expiration date in Y-m-d H:i:s format (empty for no expiration)
 * @return bool Success
 */
function create_invitation($code, $name, $email = '', $libraries = [], $usage_limit = 1, $expires = '') {
    try {
        $db = get_db_connection();
        
        // Start transaction
        $db->exec('BEGIN TRANSACTION');
        
        // Insert invitation
        $stmt = $db->prepare('
            INSERT INTO invitations (code, name, email, created, expires, usage_limit, usage_count)
            VALUES (:code, :name, :email, :created, :expires, :usage_limit, 0)
        ');
        
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':created', date('Y-m-d H:i:s'), SQLITE3_TEXT);
        $stmt->bindValue(':expires', $expires, SQLITE3_TEXT);
        $stmt->bindValue(':usage_limit', $usage_limit, SQLITE3_INTEGER);
        
        $stmt->execute();
        $invitation_id = $db->lastInsertRowID();
        
        // Add libraries if specified
        if (!empty($libraries)) {
            $library_stmt = $db->prepare('
                INSERT OR IGNORE INTO libraries (library_id, name, type, last_updated)
                VALUES (:library_id, :name, :type, :last_updated)
            ');
            
            $link_stmt = $db->prepare('
                INSERT INTO invitation_libraries (invitation_id, library_id)
                VALUES (:invitation_id, (SELECT id FROM libraries WHERE library_id = :library_id))
            ');
            
            // Get current libraries from Plex
            $plex_token = get_setting('plex_token');
            $plex_libraries = get_plex_library_sections($plex_token);
            
            foreach ($libraries as $library_id) {
                // Find library details
                $library_name = '';
                $library_type = '';
                
                foreach ($plex_libraries as $plex_library) {
                    if ($plex_library['id'] == $library_id) {
                        $library_name = $plex_library['title'];
                        $library_type = $plex_library['type'];
                        break;
                    }
                }
                
                // Insert library if it doesn't exist
                $library_stmt->bindValue(':library_id', $library_id, SQLITE3_TEXT);
                $library_stmt->bindValue(':name', $library_name, SQLITE3_TEXT);
                $library_stmt->bindValue(':type', $library_type, SQLITE3_TEXT);
                $library_stmt->bindValue(':last_updated', date('Y-m-d H:i:s'), SQLITE3_TEXT);
                $library_stmt->execute();
                $library_stmt->reset();
                
                // Link library to invitation
                $link_stmt->bindValue(':invitation_id', $invitation_id, SQLITE3_INTEGER);
                $link_stmt->bindValue(':library_id', $library_id, SQLITE3_TEXT);
                $link_stmt->execute();
                $link_stmt->reset();
            }
        }
        
        // Commit transaction
        $db->exec('COMMIT');
        
        return true;
    } catch (Exception $e) {
        error_log("Error creating invitation: " . $e->getMessage());
        if (isset($db)) {
            $db->exec('ROLLBACK');
        }
        return false;
    }
}

/**
 * Get invitation details by code
 * 
 * @param string $code Invitation code
 * @return array|false Invitation details or false if not found
 */
function get_invitation($code) {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare('
            SELECT id, code, name, email, created, expires, usage_limit, usage_count, 
                   last_used_by, last_used_at
            FROM invitations 
            WHERE code = :code LIMIT 1
        ');
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $result = $stmt->execute();
        
        if ($invitation = $result->fetchArray(SQLITE3_ASSOC)) {
            // Get associated libraries
            $invitation['libraries'] = get_invitation_libraries($invitation['id']);
            return $invitation;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error getting invitation details for code $code: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all invitations
 * 
 * @return array Array of invitations
 */
function get_all_invitations() {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare('
            SELECT id, code, name, email, created, expires, usage_limit, usage_count, 
                   last_used_by, last_used_at
            FROM invitations 
            ORDER BY created DESC
        ');
        $result = $stmt->execute();
        
        $invitations = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // Get associated libraries
            $row['libraries'] = get_invitation_libraries($row['id']);
            
            // Add status
            if (!empty($row['expires']) && strtotime($row['expires']) < time()) {
                $row['status'] = 'expired';
            } else if ($row['usage_limit'] > 0 && $row['usage_count'] >= $row['usage_limit']) {
                $row['status'] = 'used';
            } else {
                $row['status'] = 'active';
            }
            
            $invitations[] = $row;
        }
        
        return $invitations;
    } catch (Exception $e) {
        error_log("Error getting all invitations: " . $e->getMessage());
        return [];
    }
}