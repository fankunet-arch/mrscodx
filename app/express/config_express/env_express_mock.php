<?php
/**
 * Mock database configuration for testing WITHOUT real database
 * This allows testing routing, file paths, and page rendering
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

// Path constants
if (!defined('EXPRESS_APP_PATH')) {
    define('EXPRESS_APP_PATH', dirname(dirname(__FILE__)));
}
if (!defined('EXPRESS_CONFIG_PATH')) {
    define('EXPRESS_CONFIG_PATH', EXPRESS_APP_PATH . '/config_express');
}
if (!defined('EXPRESS_LIB_PATH')) {
    define('EXPRESS_LIB_PATH', EXPRESS_APP_PATH . '/lib');
}
if (!defined('EXPRESS_ACTION_PATH')) {
    define('EXPRESS_ACTION_PATH', EXPRESS_APP_PATH . '/actions');
}
if (!defined('EXPRESS_API_PATH')) {
    define('EXPRESS_API_PATH', EXPRESS_APP_PATH . '/api');
}
if (!defined('EXPRESS_VIEW_PATH')) {
    define('EXPRESS_VIEW_PATH', EXPRESS_APP_PATH . '/views');
}
if (!defined('EXPRESS_LOG_PATH')) {
    define('EXPRESS_LOG_PATH', dirname(dirname(EXPRESS_APP_PATH)) . '/logs/express');
}
if (!defined('EXPRESS_WEB_ROOT')) {
    define('EXPRESS_WEB_ROOT', dirname(dirname(dirname(EXPRESS_APP_PATH))) . '/dc_html/express');
}

// System config
date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

if (!is_dir(EXPRESS_LOG_PATH)) {
    mkdir(EXPRESS_LOG_PATH, 0755, true);
}
ini_set('error_log', EXPRESS_LOG_PATH . '/error.log');

// Mock PDO class for testing
class MockPDO {
    private $mock_data = [];

    public function __construct() {
        // Pre-populate with test data
        $this->mock_data['batches'] = [
            ['batch_id' => 1, 'batch_name' => '测试批次-2024-11-28', 'status' => 'active',
             'total_count' => 10, 'verified_count' => 3, 'counted_count' => 2, 'adjusted_count' => 1,
             'created_at' => date('Y-m-d H:i:s'), 'created_by' => 'admin', 'notes' => '测试批次']
        ];
        $this->mock_data['packages'] = [];
        for ($i = 1; $i <= 10; $i++) {
            $status = $i <= 3 ? 'verified' : 'pending';
            if ($i <= 2) $status = 'counted';
            if ($i == 1) $status = 'adjusted';

            $this->mock_data['packages'][] = [
                'package_id' => $i,
                'batch_id' => 1,
                'tracking_number' => str_pad($i, 6, '0', STR_PAD_LEFT),
                'package_status' => $status,
                'content_note' => $i <= 2 ? "测试内容×$i" : null,
                'adjustment_note' => $i == 1 ? '测试调整' : null,
                'created_at' => date('Y-m-d H:i:s'),
                'verified_at' => $i <= 3 ? date('Y-m-d H:i:s') : null,
                'counted_at' => $i <= 2 ? date('Y-m-d H:i:s') : null,
                'adjusted_at' => $i == 1 ? date('Y-m-d H:i:s') : null,
                'verified_by' => $i <= 3 ? 'test_user' : null,
                'counted_by' => $i <= 2 ? 'test_user' : null,
                'adjusted_by' => $i == 1 ? 'test_user' : null
            ];
        }
    }

    public function prepare($sql) {
        return new MockPDOStatement($sql, $this->mock_data);
    }

    public function query($sql) {
        return new MockPDOStatement($sql, $this->mock_data);
    }

    public function exec($sql) {
        return 1;
    }

    public function lastInsertId() {
        return rand(100, 999);
    }

    public function beginTransaction() {
        return true;
    }

    public function commit() {
        return true;
    }

    public function rollBack() {
        return true;
    }
}

class MockPDOStatement {
    private $sql;
    private $data;
    private $params = [];

    public function __construct($sql, $data) {
        $this->sql = $sql;
        $this->data = $data;
    }

    public function execute($params = []) {
        $this->params = $params;
        return true;
    }

    public function fetch() {
        if (strpos($this->sql, 'express_batch') !== false) {
            return $this->data['batches'][0] ?? false;
        }
        if (strpos($this->sql, 'express_package') !== false) {
            return $this->data['packages'][0] ?? false;
        }
        return false;
    }

    public function fetchAll() {
        if (strpos($this->sql, 'express_batch') !== false) {
            return $this->data['batches'];
        }
        if (strpos($this->sql, 'express_package') !== false) {
            return $this->data['packages'];
        }
        return [];
    }

    public function fetchColumn() {
        return 10;
    }

    public function rowCount() {
        return 1;
    }

    public function bindValue($param, $value, $type = null) {
        $this->params[$param] = $value;
    }
}

function get_express_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new MockPDO();
    }
    return $pdo;
}

function express_log($message, $level = 'INFO', $context = []) {
    $log_file = EXPRESS_LOG_PATH . '/debug.log';
    if (!is_dir(EXPRESS_LOG_PATH)) {
        mkdir(EXPRESS_LOG_PATH, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $context_str = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $log_line = sprintf("[%s] [%s] %s%s\n", $timestamp, $level, $message, $context_str);
    file_put_contents($log_file, $log_line, FILE_APPEND);
}

function express_json_response($success, $data = null, $message = '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function express_get_json_input() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

function express_start_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Lax');
        session_name('EXPRESS_SESSION');
        session_start();
    }
}
