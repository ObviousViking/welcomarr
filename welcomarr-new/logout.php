<?php
require_once 'config.php';

// Destroy the session
session_destroy();

// Set flash message
set_flash_message('You have been logged out successfully', 'success');

// Redirect to home page
header('Location: index.php');
exit;
?>