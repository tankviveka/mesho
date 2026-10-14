<?php
/**
 * Logout - destroys the PHP session and returns to the login page.
 */

require_once __DIR__ . '/includes/init.php';

logout_user();
session_start();
flash_set('success', 'You have been logged out safely.');
header('Location: login.php');
exit;
