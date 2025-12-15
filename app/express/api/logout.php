<?php
/**
 * API: Logout
 * 文件路径: app/express/api/logout.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$username = $_SESSION['user_login'] ?? 'unknown';

express_destroy_user_session();

express_log('Admin logged out: ' . $username, 'INFO');

header('Location: /express/exp/index.php?action=login&status=logout');
exit;
