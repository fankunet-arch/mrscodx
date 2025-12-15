<?php
/**
 * MRS 测试配置文件
 * 使用SQLite本地数据库进行测试
 */

// 防止直接访问
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// ============================================
// SQLite测试数据库配置
// ============================================

$test_db_path = __DIR__ . '/../../test_mrs.sqlite';

// ============================================
// 路径常量
// ============================================

// 应用根目录
if (!defined('MRS_APP_PATH')) {
    define('MRS_APP_PATH', dirname(dirname(__FILE__)));
}

// 配置目录
if (!defined('MRS_CONFIG_PATH')) {
    define('MRS_CONFIG_PATH', MRS_APP_PATH . '/config_mrs');
}

// 业务库目录
if (!defined('MRS_LIB_PATH')) {
    define('MRS_LIB_PATH', MRS_APP_PATH . '/lib');
}

// 控制器目录
if (!defined('MRS_ACTION_PATH')) {
    define('MRS_ACTION_PATH', MRS_APP_PATH . '/actions');
}

// API目录
if (!defined('MRS_API_PATH')) {
    define('MRS_API_PATH', MRS_APP_PATH . '/api');
}

// 日志目录
if (!defined('MRS_LOG_PATH')) {
    define('MRS_LOG_PATH', dirname(dirname(MRS_APP_PATH)) . '/logs/mrs');
}

// Web根目录
if (!defined('MRS_WEB_ROOT')) {
    define('MRS_WEB_ROOT', dirname(dirname(dirname(MRS_APP_PATH))) . '/dc_html/mrs');
}

// ============================================
// 系统配置
// ============================================

// 时区设置
date_default_timezone_set('UTC');

// 错误报告级别
error_reporting(E_ALL);
ini_set('display_errors', '1'); // 测试环境显示错误
ini_set('log_errors', '1');
ini_set('error_log', MRS_LOG_PATH . '/error.log');

// ============================================
// 数据库连接函数（SQLite版本）
// ============================================

/**
 * 获取数据库PDO连接（SQLite测试版）
 * @return PDO
 * @throws PDOException
 */
function get_db_connection() {
    global $test_db_path;

    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'sqlite:' . $test_db_path,
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            // SQLite specific settings
            $pdo->exec('PRAGMA foreign_keys = ON');

        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw $e;
        }
    }

    return $pdo;
}

// ============================================
// 日志函数
// ============================================

/**
 * 写入日志
 * @param string $message 日志消息
 * @param string $level 日志级别 (INFO, WARNING, ERROR)
 * @param array $context 上下文数据
 */
function mrs_log($message, $level = 'INFO', $context = []) {
    $log_file = MRS_LOG_PATH . '/debug.log';

    // 确保日志目录存在
    if (!is_dir(MRS_LOG_PATH)) {
        mkdir(MRS_LOG_PATH, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $context_str = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $log_line = sprintf("[%s] [%s] %s%s\n", $timestamp, $level, $message, $context_str);

    file_put_contents($log_file, $log_line, FILE_APPEND);
}

// ============================================
// 辅助函数
// ============================================

/**
 * 输出JSON响应
 * @param bool $success 成功标志
 * @param mixed $data 响应数据
 * @param string $message 消息
 */
function json_response($success, $data = null, $message = '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 获取POST JSON数据
 * @return array|null
 */
function get_json_input() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}
