<?php
/**
 * Express Package Management System - Database Setup Script (SQLite)
 * 文件路径: app/express/setup_database_sqlite.php
 * 用途: 创建SQLite数据库表（用于本地测试）
 */

// 定义入口标识
define('EXPRESS_ENTRY', true);

// 加载SQLite配置
require_once __DIR__ . '/config_express/env_express_sqlite.php';

echo "Express Package Management System - Database Setup (SQLite)\n";
echo "===========================================================\n\n";

try {
    $pdo = get_express_db_connection();
    echo "[OK] Database connection successful\n";
    echo "     Database file: " . SQLITE_DB_PATH . "\n\n";

    // 读取SQL文件
    $sql_file = dirname(__DIR__) . '/../docs/express_database_schema_sqlite.sql';

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

    $created_items = [];
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);

                // 提取表名或索引名
                if (preg_match('/CREATE TABLE.*\s+(\w+)\s*\(/i', $statement, $matches)) {
                    $item_name = $matches[1];
                    $created_items[] = "Table: $item_name";
                    echo "  [OK] Created table: $item_name\n";
                } elseif (preg_match('/CREATE INDEX.*\s+(\w+)\s+ON/i', $statement, $matches)) {
                    $item_name = $matches[1];
                    $created_items[] = "Index: $item_name";
                    echo "  [OK] Created index: $item_name\n";
                }
            } catch (PDOException $e) {
                // 如果已存在，跳过错误
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    if (preg_match('/CREATE TABLE.*\s+(\w+)\s*\(/i', $statement, $matches)) {
                        echo "  [SKIP] Table already exists: {$matches[1]}\n";
                    } elseif (preg_match('/CREATE INDEX.*\s+(\w+)\s+ON/i', $statement, $matches)) {
                        echo "  [SKIP] Index already exists: {$matches[1]}\n";
                    }
                } else {
                    echo "  [ERROR] Failed to execute statement: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    echo "\n";
    echo "Database setup completed!\n";
    echo "Items created: " . count($created_items) . "\n";

    // 验证表是否创建成功
    echo "\nVerifying tables...\n";
    $tables_to_check = ['express_batch', 'express_package', 'express_operation_log'];

    foreach ($tables_to_check as $table) {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
        if ($stmt->rowCount() > 0) {
            echo "  [OK] Table '$table' exists\n";

            // 获取记录数
            $count_stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $count_stmt->fetchColumn();
            echo "       Records: $count\n";
        } else {
            echo "  [ERROR] Table '$table' not found\n";
        }
    }

    echo "\n[SUCCESS] Database setup completed successfully!\n";
    echo "Database file: " . SQLITE_DB_PATH . "\n";

} catch (PDOException $e) {
    echo "[ERROR] Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
