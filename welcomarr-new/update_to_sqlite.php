<?php
// This script updates all PHP files to use config_sqlite.php instead of config.php

$directory = __DIR__;
$files = glob($directory . '/*.php');

foreach ($files as $file) {
    // Skip the config files and this script
    if (basename($file) == 'config.php' || 
        basename($file) == 'config_sqlite.php' || 
        basename($file) == 'update_to_sqlite.php' ||
        basename($file) == 'migrate_to_sqlite.php' ||
        strpos(basename($file), '_sqlite.php') !== false) {
        continue;
    }
    
    // Read the file content
    $content = file_get_contents($file);
    
    // Replace config.php with config_sqlite.php
    $updated_content = str_replace('require_once \'config.php\';', 'require_once \'config_sqlite.php\';', $content);
    
    // Replace require_login() with the SQLite equivalent
    $updated_content = str_replace(
        'require_login();', 
        'if (!is_logged_in()) {
    set_flash_message(\'Please login to access this page\', \'warning\');
    header(\'Location: login.php\');
    exit;
}', 
        $updated_content
    );
    
    // Replace load_data() and save_data() calls
    $updated_content = preg_replace('/\$data = load_data\(\);/', '', $updated_content);
    $updated_content = preg_replace('/\$settings = \$data\[\'settings\'\];/', '$settings = get_settings();', $updated_content);
    $updated_content = preg_replace('/save_data\(\$data\);/', '', $updated_content);
    
    // Write the updated content back to the file
    file_put_contents($file, $updated_content);
    
    echo "Updated: " . basename($file) . "\n";
}

// Rename the SQLite versions to replace the original files
$sqlite_files = glob($directory . '/*_sqlite.php');
foreach ($sqlite_files as $file) {
    $new_name = str_replace('_sqlite.php', '.php', $file);
    rename($file, $new_name);
    echo "Renamed: " . basename($file) . " to " . basename($new_name) . "\n";
}

// Rename config files
rename($directory . '/config.php', $directory . '/config_json.php');
rename($directory . '/config_sqlite.php', $directory . '/config.php');

echo "Migration completed successfully!\n";
echo "The application now uses SQLite instead of JSON for data storage.\n";