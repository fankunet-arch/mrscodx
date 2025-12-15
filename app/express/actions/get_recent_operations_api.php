<?php
/**
 * API: Get Recent Operations with Type Filter and Dedup
 * 文件路径: app/express/actions/get_recent_operations_api.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

// 捕获所有警告/通知，避免破坏JSON输出
ob_start();
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$success = false;
$payload = null;
$msg = '';

try {
    $batch_id = $_GET['batch_id'] ?? 0;
    $operation_type = $_GET['operation_type'] ?? null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

    if (empty($batch_id)) {
        throw new InvalidArgumentException('批次ID不能为空');
    }

    if (!empty($operation_type) && !in_array($operation_type, ['verify', 'count', 'adjust'])) {
        throw new InvalidArgumentException('无效的操作类型');
    }

    $payload = express_get_recent_operations($pdo, $batch_id, $operation_type, $limit);
    $success = true;
} catch (Throwable $e) {
    $msg = $e instanceof InvalidArgumentException ? $e->getMessage() : '获取历史失败';
    express_log('Get recent operations API failed: ' . $e->getMessage(), 'ERROR');
}

// 清理缓冲并记录意外输出
$buffer = ob_get_clean();
if (!empty($buffer)) {
    express_log('Get recent operations API extra output: ' . trim($buffer), $success ? 'WARNING' : 'ERROR');
}
restore_error_handler();

express_json_response($success, $payload, $msg);
