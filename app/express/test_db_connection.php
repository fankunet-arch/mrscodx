<?php
/**
 * Express Package Management System - Database Connection Test
 * 文件路径: app/express/test_db_connection.php
 * 用途: 测试数据库连接
 */

echo "Express Package Management System - Database Connection Test\n";
echo "=============================================================\n\n";

// 测试1: 检查环境变量
echo "Step 1: Checking environment variables...\n";
$env_vars = ['EXPRESS_DB_HOST', 'EXPRESS_DB_NAME', 'EXPRESS_DB_USER', 'EXPRESS_DB_PASS'];
foreach ($env_vars as $var) {
    $value = getenv($var);
    if ($value) {
        echo "  [OK] $var is set\n";
    } else {
        echo "  [INFO] $var not set (will use default)\n";
    }
}
echo "\n";

// 测试2: 加载配置
echo "Step 2: Loading configuration...\n";
define('EXPRESS_ENTRY', true);
require_once __DIR__ . '/config_express/env_express.php';

echo "  Database Host: " . DB_HOST . "\n";
echo "  Database Name: " . DB_NAME . "\n";
echo "  Database User: " . DB_USER . "\n";
echo "  Database Charset: " . DB_CHARSET . "\n\n";

// 测试3: 尝试连接
echo "Step 3: Attempting database connection...\n";
try {
    $pdo = get_express_db_connection();
    echo "  [OK] Database connection successful!\n\n";

    // 测试4: 检查现有表
    echo "Step 4: Checking for existing tables...\n";
    $tables = ['express_batch', 'express_package', 'express_operation_log'];

    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "  [OK] Table '$table' exists\n";

            // 获取记录数
            $count_stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $count_stmt->fetchColumn();
            echo "       Records: $count\n";
        } else {
            echo "  [INFO] Table '$table' does not exist\n";
        }
    }

    echo "\n[SUCCESS] Database is ready!\n";

} catch (PDOException $e) {
    echo "  [ERROR] Connection failed!\n";
    echo "  Error: " . $e->getMessage() . "\n\n";

    echo "=== Troubleshooting ===\n";
    echo "If you want to use a local MySQL database for testing:\n\n";
    echo "1. Make sure MySQL is running:\n";
    echo "   sudo service mysql start\n\n";
    echo "2. Create a test database:\n";
    echo "   mysql -u root -p -e \"CREATE DATABASE express_test;\"\n\n";
    echo "3. Set environment variables before running:\n";
    echo "   export EXPRESS_DB_HOST=localhost\n";
    echo "   export EXPRESS_DB_NAME=express_test\n";
    echo "   export EXPRESS_DB_USER=root\n";
    echo "   export EXPRESS_DB_PASS=your_password\n\n";
    echo "4. Or modify the config file directly:\n";
    echo "   Edit: app/express/config_express/env_express.php\n";

    exit(1);
}
