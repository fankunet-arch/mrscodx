<?php
/**
 * Express Package Management System - Database Setup Script
 * 文件路径: app/express/setup_database.php
 * 用途: 创建数据库表
 */

// 定义入口标识
define('EXPRESS_ENTRY', true);

// 加载配置
require_once __DIR__ . '/config_express/env_express.php';

echo "Express Package Management System - Database Setup\n";
echo "==================================================\n\n";

try {
    $pdo = get_express_db_connection();
    echo "[OK] Database connection successful\n\n";

    // 读取SQL文件
    $sql_file = dirname(__DIR__) . '/../docs/express_database_schema.sql';

    if (!file_exists($sql_file)) {
        die("[ERROR] SQL file not found: $sql_file\n");
    }

    $sql = file_get_contents($sql_file);

    echo "Creating tables...\n";

    // 分割SQL语句（按照分号分隔）
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );

    $created_tables = [];
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);

                // 提取表名
                if (preg_match('/CREATE TABLE.*`(\w+)`/i', $statement, $matches)) {
                    $table_name = $matches[1];
                    $created_tables[] = $table_name;
                    echo "  [OK] Created table: $table_name\n";
                }
            } catch (PDOException $e) {
                // 如果表已存在，跳过错误
                if ($e->getCode() == '42S01') {
                    if (preg_match('/CREATE TABLE.*`(\w+)`/i', $statement, $matches)) {
                        echo "  [SKIP] Table already exists: {$matches[1]}\n";
                    }
                } else {
                    echo "  [ERROR] Failed to execute statement: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    echo "\n";
    echo "Database setup completed!\n";
    echo "Tables created: " . implode(', ', $created_tables) . "\n";

    // 验证表是否创建成功
    echo "\nVerifying tables...\n";
    $tables_to_check = ['express_batch', 'express_package', 'express_operation_log'];

    foreach ($tables_to_check as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "  [OK] Table '$table' exists\n";
        } else {
            echo "  [ERROR] Table '$table' not found\n";
        }
    }

    echo "\nSetup completed successfully!\n";

} catch (PDOException $e) {
    echo "[ERROR] Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
