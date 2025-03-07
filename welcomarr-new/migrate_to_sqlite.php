<?php
// This script migrates data from JSON to SQLite

// First, load the JSON data
require_once 'config.php';
$json_data = load_data();

// Then, load the SQLite configuration
require_once 'config_sqlite.php';

// Migrate settings
$settings = $json_data['settings'];
update_settings($settings);

// Migrate admin
$admin = $json_data['admin'];
update_admin($admin);

// Migrate libraries
if (!empty($json_data['libraries'])) {
    $libraries = [];
    foreach ($json_data['libraries'] as $lib) {
        $libraries[] = [
            'library_id' => $lib['id'],
            'name' => $lib['name']
        ];
    }
    update_libraries($libraries);
}

// Migrate invitations
if (!empty($json_data['invitations'])) {
    foreach ($json_data['invitations'] as $invitation) {
        $invitation_data = [
            'code' => $invitation['code'],
            'name' => $invitation['name'] ?? null,
            'email' => $invitation['email'] ?? null,
            'created' => $invitation['created'] ?? date('Y-m-d H:i:s'),
            'expires' => $invitation['expires'] ?? null,
            'usage_limit' => $invitation['usage_limit'] ?? 1,
            'usage_count' => $invitation['usage_count'] ?? 0,
            'libraries' => $invitation['libraries'] ?? []
        ];
        
        create_invitation($invitation_data);
    }
}

// Migrate users
if (!empty($json_data['users'])) {
    foreach ($json_data['users'] as $user) {
        $user_data = [
            'name' => $user['name'],
            'email' => $user['email'] ?? null,
            'plex_username' => $user['plex_username'],
            'joined' => $user['joined'] ?? date('Y-m-d H:i:s'),
            'invitation_code' => $user['invitation_code'] ?? null,
            'libraries' => $user['libraries'] ?? []
        ];
        
        add_user($user_data);
    }
}

echo "Migration completed successfully!\n";
echo "Please update your application to use config_sqlite.php instead of config.php\n";