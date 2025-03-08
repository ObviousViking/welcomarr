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
        $stmt = $db->prepare('SELECT id, usage_count FROM invitations WHERE code = :code LIMIT 1');
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $result = $stmt->execute();
        
        if ($invitation = $result->fetchArray(SQLITE3_ASSOC)) {
            $usage_count = $invitation['usage_count'] + 1;
            $now = date('Y-m-d H:i:s');
            
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