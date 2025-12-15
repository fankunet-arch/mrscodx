<?php
/**
 * Express Package Management System - SQLite配置文件（用于本地测试）
 * 文件路径: app/express/config_express/env_express_sqlite.php
 * 说明: SQLite数据库连接配置，用于本地测试
 */

// 防止直接访问
if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

// ============================================
// 路径常量
// ============================================

// 应用根目录
if (!defined('EXPRESS_APP_PATH')) {
    define('EXPRESS_APP_PATH', dirname(dirname(__FILE__)));
}

// 配置目录
if (!defined('EXPRESS_CONFIG_PATH')) {
    define('EXPRESS_CONFIG_PATH', EXPRESS_APP_PATH . '/config_express');
}

// 业务库目录
if (!defined('EXPRESS_LIB_PATH')) {
    define('EXPRESS_LIB_PATH', EXPRESS_APP_PATH . '/lib');
}

// 控制器目录
if (!defined('EXPRESS_ACTION_PATH')) {
    define('EXPRESS_ACTION_PATH', EXPRESS_APP_PATH . '/actions');
}

// API目录
if (!defined('EXPRESS_API_PATH')) {
    define('EXPRESS_API_PATH', EXPRESS_APP_PATH . '/api');
}

// 视图目录
if (!defined('EXPRESS_VIEW_PATH')) {
    define('EXPRESS_VIEW_PATH', EXPRESS_APP_PATH . '/views');
}

// 日志目录
if (!defined('EXPRESS_LOG_PATH')) {
    define('EXPRESS_LOG_PATH', dirname(dirname(EXPRESS_APP_PATH)) . '/logs/express');
}

// Web根目录 (dc_html/express)
if (!defined('EXPRESS_WEB_ROOT')) {
    define('EXPRESS_WEB_ROOT', dirname(dirname(dirname(EXPRESS_APP_PATH))) . '/dc_html/express');
}

// SQLite数据库文件路径
define('SQLITE_DB_PATH', dirname(dirname(EXPRESS_APP_PATH)) . '/data/express.db');

// ============================================
// 系统配置
// ============================================

// 时区设置
date_default_timezone_set('UTC');

// 错误报告级别
error_reporting(E_ALL);
ini_set('display_errors', '1'); // 本地测试设为1
ini_set('log_errors', '1');

// 确保日志目录存在
if (!is_dir(EXPRESS_LOG_PATH)) {
    mkdir(EXPRESS_LOG_PATH, 0755, true);
}

ini_set('error_log', EXPRESS_LOG_PATH . '/error.log');

// 确保数据目录存在
$data_dir = dirname(SQLITE_DB_PATH);
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0755, true);
}

// ============================================
// 数据库连接函数
// ============================================

/**
 * 获取数据库PDO连接（SQLite版本）
 * @return PDO
 * @throws PDOException
 */
function get_express_db_connection() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'sqlite:' . SQLITE_DB_PATH,
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            // 启用外键约束
            $pdo->exec('PRAGMA foreign_keys = ON');

        } catch (PDOException $e) {
            // 记录错误日志
            error_log('Express Database connection failed: ' . $e->getMessage());
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
function express_log($message, $level = 'INFO', $context = []) {
    $log_file = EXPRESS_LOG_PATH . '/debug.log';

    // 确保日志目录存在
    if (!is_dir(EXPRESS_LOG_PATH)) {
        mkdir(EXPRESS_LOG_PATH, 0755, true);
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
function express_json_response($success, $data = null, $message = '') {
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
function express_get_json_input() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

/**
 * 启动安全会话
 */
function express_start_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Lax');
        session_name('EXPRESS_SESSION');
        session_start();
    }
}
