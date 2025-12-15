<?php
/**
 * API: Get Active Batches
 * 文件路径: app/express/actions/get_batches_api.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$batches = express_get_batches($pdo, 'active', 50);
express_json_response(true, $batches);
