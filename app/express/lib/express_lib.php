<?php
/**
 * Express Package Management System - Core Library
 * 文件路径: app/express/lib/express_lib.php
 * 说明: 核心业务逻辑函数
 */

// ============================================
// 认证相关函数（与MRS一致的逻辑）
// ============================================

/**
 * 验证用户登录
 * @param PDO $pdo
 * @param string $username
 * @param string $password
 * @return array|false
 */
function express_authenticate_user($pdo, $username, $password) {
    try {
        $stmt = $pdo->prepare("SELECT user_id, user_login, user_secret_hash, user_email, user_display_name, user_status FROM sys_users WHERE user_login = :username LIMIT 1");
        $stmt->bindValue(':username', $username);
        $stmt->execute();

        $user = $stmt->fetch();

        if (!$user) {
            express_log("登录失败: 用户不存在 - {$username}", 'WARNING');
            return false;
        }

        if ($user['user_status'] !== 'active') {
            express_log("登录失败: 账户未激活 - {$username}", 'WARNING');
            return false;
        }

        if (password_verify($password, $user['user_secret_hash'])) {
            $update = $pdo->prepare("UPDATE sys_users SET user_last_login_at = NOW(6) WHERE user_id = :user_id");
            $update->bindValue(':user_id', $user['user_id'], PDO::PARAM_INT);
            $update->execute();

            unset($user['user_secret_hash']);
            express_log("登录成功: {$username}", 'INFO');
            return $user;
        }

        express_log("登录失败: 密码错误 - {$username}", 'WARNING');
        return false;
    } catch (PDOException $e) {
        express_log('用户认证失败: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * 创建用户会话
 * @param array $user
 */
function express_create_user_session($user) {
    express_start_secure_session();

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_login'] = $user['user_login'];
    $_SESSION['user_display_name'] = $user['user_display_name'];
    $_SESSION['user_email'] = $user['user_email'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
}

/**
 * 检查用户是否登录
 * @return bool
 */
function express_is_user_logged_in() {
    express_start_secure_session();

    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }

    $timeout = 1800;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        express_destroy_user_session();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * 销毁会话
 */
function express_destroy_user_session() {
    express_start_secure_session();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * 登录保护
 */
function express_require_login() {
    if (!express_is_user_logged_in()) {
        header('Location: /express/exp/index.php?action=login');
        exit;
    }
}

// ============================================
// 批次管理函数
// ============================================

/**
 * 获取批次列表
 * @param PDO $pdo
 * @param string $status 批次状态（'active', 'closed', 'all'）
 * @param int $limit 限制数量
 * @return array
 */
function express_get_batches($pdo, $status = 'all', $limit = 100) {
    try {
        // 添加清点状态判断字段
        $sql = "SELECT *,
                CASE
                    WHEN counted_count > 0 AND counted_count < total_count THEN 'counting'
                    WHEN counted_count = total_count AND total_count > 0 THEN 'completed'
                    ELSE 'pending'
                END AS count_status
                FROM express_batch";

        if ($status !== 'all') {
            $sql .= " WHERE status = :status";
        }

        // 优化排序：正在清点的靠前，已完成的靠后，其他按创建时间倒序
        $sql .= " ORDER BY
                  CASE
                      WHEN counted_count > 0 AND counted_count < total_count THEN 1
                      WHEN counted_count = 0 OR total_count = 0 THEN 2
                      WHEN counted_count = total_count AND total_count > 0 THEN 3
                  END,
                  created_at DESC
                  LIMIT :limit";

        $stmt = $pdo->prepare($sql);

        if ($status !== 'all') {
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        express_log('Failed to get batches: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 根据批次ID获取批次详情
 * @param PDO $pdo
 * @param int $batch_id
 * @return array|null
 */
function express_get_batch_by_id($pdo, $batch_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM express_batch WHERE batch_id = :batch_id");
        $stmt->execute(['batch_id' => $batch_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        express_log('Failed to get batch: ' . $e->getMessage(), 'ERROR');
        return null;
    }
}

/**
 * 创建新批次
 * @param PDO $pdo
 * @param string $batch_name
 * @param string $created_by
 * @param string $notes
 * @return int|false 返回新批次ID或false
 */
function express_create_batch($pdo, $batch_name, $created_by = null, $notes = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO express_batch (batch_name, created_by, notes)
            VALUES (:batch_name, :created_by, :notes)
        ");

        $stmt->execute([
            'batch_name' => trim($batch_name),
            'created_by' => $created_by,
            'notes' => $notes
        ]);

        express_log('Batch created: ' . $batch_name, 'INFO');
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        express_log('Failed to create batch: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * 更新批次信息
 * @param PDO $pdo
 * @param int $batch_id
 * @param string $batch_name
 * @param string $status
 * @param string|null $notes
 * @return array
 */
function express_update_batch($pdo, $batch_id, $batch_name, $status = 'active', $notes = null) {
    try {
        $normalized_status = in_array($status, ['active', 'closed']) ? $status : 'active';

        $stmt = $pdo->prepare("
            UPDATE express_batch
            SET batch_name = :batch_name,
                status = :status,
                notes = :notes
            WHERE batch_id = :batch_id
        ");

        $stmt->execute([
            'batch_name' => trim($batch_name),
            'status' => $normalized_status,
            'notes' => $notes,
            'batch_id' => $batch_id
        ]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => '批次未修改或不存在'];
        }

        express_log('Batch updated: ' . $batch_id, 'INFO');
        return ['success' => true, 'message' => '批次更新成功'];
    } catch (PDOException $e) {
        $message = $e->getCode() === '23000'
            ? '批次名称已存在'
            : '批次更新失败: ' . $e->getMessage();

        express_log('Failed to update batch: ' . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => $message];
    }
}

/**
 * 删除批次（级联删除包裹和日志）
 * @param PDO $pdo
 * @param int $batch_id
 * @return array
 */
function express_delete_batch($pdo, $batch_id) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM express_batch WHERE batch_id = :batch_id");
        $stmt->execute(['batch_id' => $batch_id]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            return ['success' => false, 'message' => '批次不存在'];
        }

        $pdo->commit();
        express_log('Batch deleted: ' . $batch_id, 'INFO');
        return ['success' => true, 'message' => '批次已删除'];
    } catch (PDOException $e) {
        $pdo->rollBack();
        express_log('Failed to delete batch: ' . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => '批次删除失败: ' . $e->getMessage()];
    }
}

/**
 * 更新批次统计数据
 * @param PDO $pdo
 * @param int $batch_id
 * @return bool
 */
function express_update_batch_statistics($pdo, $batch_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE express_batch SET
                total_count = (SELECT COUNT(*) FROM express_package WHERE batch_id = :batch_id1),
                verified_count = (SELECT COUNT(*) FROM express_package WHERE batch_id = :batch_id2 AND package_status IN ('verified', 'counted', 'adjusted')),
                counted_count = (SELECT COUNT(*) FROM express_package WHERE batch_id = :batch_id3 AND package_status IN ('counted', 'adjusted')),
                adjusted_count = (SELECT COUNT(*) FROM express_package WHERE batch_id = :batch_id4 AND package_status = 'adjusted')
            WHERE batch_id = :batch_id5
        ");

        $stmt->execute([
            'batch_id1' => $batch_id,
            'batch_id2' => $batch_id,
            'batch_id3' => $batch_id,
            'batch_id4' => $batch_id,
            'batch_id5' => $batch_id
        ]);

        return true;
    } catch (PDOException $e) {
        express_log('Failed to update batch statistics: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

// ============================================
// 包裹管理函数
// ============================================

/**
 * 模糊搜索快递单号
 * @param PDO $pdo
 * @param int $batch_id
 * @param string $keyword
 * @param int $limit
 * @return array
 */
function express_search_tracking($pdo, $batch_id, $keyword, $limit = 20) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM express_package
            WHERE batch_id = :batch_id AND tracking_number LIKE :keyword
            ORDER BY tracking_number ASC
            LIMIT :limit
        ");

        $stmt->bindValue(':batch_id', $batch_id, PDO::PARAM_INT);
        $stmt->bindValue(':keyword', '%' . $keyword . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $packages = $stmt->fetchAll();

        // 为每个包裹获取产品明细
        foreach ($packages as &$package) {
            $stmt = $pdo->prepare("
                SELECT * FROM express_package_items
                WHERE package_id = :package_id
                ORDER BY sort_order ASC
            ");
            $stmt->execute(['package_id' => $package['package_id']]);
            $package['items'] = $stmt->fetchAll();
        }

        return $packages;
    } catch (PDOException $e) {
        express_log('Failed to search tracking: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 按内容备注搜索包裹（跨批次）
 * @param PDO $pdo
 * @param string $keyword
 * @param int $limit
 * @return array
 */
function express_search_content_note($pdo, $keyword, $limit = 50) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                pkg.package_id,
                pkg.batch_id,
                pkg.tracking_number,
                pkg.content_note,
                pkg.package_status,
                pkg.counted_at,
                pkg.created_at,
                batch.batch_name
            FROM express_package pkg
            INNER JOIN express_batch batch ON pkg.batch_id = batch.batch_id
            WHERE pkg.content_note IS NOT NULL
              AND pkg.content_note <> ''
              AND pkg.content_note LIKE :keyword
            ORDER BY pkg.counted_at DESC, pkg.created_at DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':keyword', '%' . $keyword . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        express_log('Failed to search content note: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取包裹详情
 * @param PDO $pdo
 * @param int $package_id
 * @return array|null
 */
function express_get_package_by_id($pdo, $package_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM express_package WHERE package_id = :package_id");
        $stmt->execute(['package_id' => $package_id]);
        $package = $stmt->fetch();

        if ($package) {
            // 获取产品明细
            $stmt = $pdo->prepare("
                SELECT * FROM express_package_items
                WHERE package_id = :package_id
                ORDER BY sort_order ASC
            ");
            $stmt->execute(['package_id' => $package_id]);
            $package['items'] = $stmt->fetchAll();
        }

        return $package;
    } catch (PDOException $e) {
        express_log('Failed to get package: ' . $e->getMessage(), 'ERROR');
        return null;
    }
}

/**
 * 获取批次下的所有包裹
 * @param PDO $pdo
 * @param int $batch_id
 * @param string $status 过滤状态（'all' 或具体状态）
 * @return array
 */
function express_get_packages_by_batch($pdo, $batch_id, $status = 'all') {
    try {
        $sql = "SELECT * FROM express_package WHERE batch_id = :batch_id";

        if ($status !== 'all') {
            $sql .= " AND package_status = :status";
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':batch_id', $batch_id, PDO::PARAM_INT);

        if ($status !== 'all') {
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        }

        $stmt->execute();
        $packages = $stmt->fetchAll();

        // 为每个包裹获取产品明细
        foreach ($packages as &$package) {
            $stmt = $pdo->prepare("
                SELECT * FROM express_package_items
                WHERE package_id = :package_id
                ORDER BY sort_order ASC
            ");
            $stmt->execute(['package_id' => $package['package_id']]);
            $package['items'] = $stmt->fetchAll();
        }

        return $packages;
    } catch (PDOException $e) {
        express_log('Failed to get packages: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取批次内的内容备注统计
 * @param PDO $pdo
 * @param int $batch_id
 * @return array
 */
function express_get_content_summary($pdo, $batch_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT content_note, COUNT(*) AS package_count
            FROM express_package
            WHERE batch_id = :batch_id
              AND content_note IS NOT NULL
              AND content_note <> ''
            GROUP BY content_note
            ORDER BY package_count DESC, content_note ASC
        ");

        $stmt->bindValue(':batch_id', $batch_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        express_log('Failed to get content summary: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取批次的最近操作记录（按类型过滤后再去重单号）
 * @param PDO $pdo
 * @param int $batch_id
 * @param string|null $operation_type
 * @param int $limit
 * @return array
 */
function express_get_recent_operations($pdo, $batch_id, $operation_type = null, $limit = 50) {
    try {
        $sql = "
            SELECT
                log.log_id,
                log.operation_type,
                log.operation_time,
                log.operator,
                log.old_status,
                log.new_status,
                log.notes,
                pkg.tracking_number,
                pkg.package_status
            FROM express_operation_log log
            INNER JOIN express_package pkg ON log.package_id = pkg.package_id
            WHERE pkg.batch_id = :batch_id
        ";

        if (!empty($operation_type)) {
            $sql .= " AND log.operation_type = :operation_type";
        }

        $sql .= " ORDER BY log.operation_time DESC, log.log_id DESC LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':batch_id', $batch_id, PDO::PARAM_INT);
        if (!empty($operation_type)) {
            $stmt->bindValue(':operation_type', $operation_type, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        // 按单号去重，仅保留时间最新一条（当前结果已按时间倒序）
        $seen = [];
        $deduped = [];

        foreach ($rows as $row) {
            $tracking = $row['tracking_number'];
            if (isset($seen[$tracking])) {
                continue;
            }

            $seen[$tracking] = true;
            $deduped[] = [
                'tracking_number' => $row['tracking_number'],
                'operation_type' => $row['operation_type'],
                'operation_time' => $row['operation_time'],
                'operator' => $row['operator'],
                'old_status' => $row['old_status'],
                'new_status' => $row['new_status'],
                'package_status' => $row['package_status'],
                'notes' => $row['notes'],
            ];
        }

        return $deduped;
    } catch (PDOException $e) {
        express_log('Failed to get recent operations: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 创建新包裹记录（仅用于批量导入）
 * @param PDO $pdo
 * @param int $batch_id
 * @param string $tracking_number
 * @param string|null $expiry_date 有效期（选填）
 * @param int|null $quantity 数量（选填，参考用途）
 * @return int|false
 */
function express_create_package($pdo, $batch_id, $tracking_number, $expiry_date = null, $quantity = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO express_package (batch_id, tracking_number, expiry_date, quantity)
            VALUES (:batch_id, :tracking_number, :expiry_date, :quantity)
        ");

        $stmt->execute([
            'batch_id' => $batch_id,
            'tracking_number' => trim($tracking_number),
            'expiry_date' => $expiry_date,
            'quantity' => $quantity
        ]);

        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        // 重复单号不报错，只记录日志
        if ($e->getCode() == 23000) {
            express_log('Duplicate tracking number: ' . $tracking_number, 'WARNING');
        } else {
            express_log('Failed to create package: ' . $e->getMessage(), 'ERROR');
        }
        return false;
    }
}

/**
 * 获取批次的下一个自定义包裹编号
 * @param PDO $pdo
 * @param int $batch_id
 * @return string 格式: CUSTOM-{batch_id}-{序号}
 */
function express_get_next_custom_tracking_number($pdo, $batch_id) {
    try {
        // 查找该批次中最后一个自定义包裹编号
        $stmt = $pdo->prepare("
            SELECT tracking_number
            FROM express_package
            WHERE batch_id = :batch_id
              AND tracking_number LIKE :pattern
            ORDER BY tracking_number DESC
            LIMIT 1
        ");

        $pattern = "CUSTOM-{$batch_id}-%";
        $stmt->execute([
            'batch_id' => $batch_id,
            'pattern' => $pattern
        ]);

        $last = $stmt->fetch();

        if (!$last) {
            // 没有自定义包裹，从0001开始
            return "CUSTOM-{$batch_id}-0001";
        }

        // 解析最后一个编号，递增
        // 格式: CUSTOM-{batch_id}-{序号}
        $parts = explode('-', $last['tracking_number']);
        if (count($parts) >= 3) {
            $last_num = intval(end($parts));
            $next_num = $last_num + 1;
            return "CUSTOM-{$batch_id}-" . str_pad($next_num, 4, '0', STR_PAD_LEFT);
        }

        // 如果解析失败，默认返回0001
        return "CUSTOM-{$batch_id}-0001";

    } catch (PDOException $e) {
        express_log('Failed to get next custom tracking number: ' . $e->getMessage(), 'ERROR');
        return "CUSTOM-{$batch_id}-0001";
    }
}

/**
 * 批量创建自定义包裹
 * @param PDO $pdo
 * @param int $batch_id
 * @param int $count 要创建的数量
 * @param string $operator 操作人
 * @return array ['success' => bool, 'message' => string, 'data' => array]
 */
function express_create_custom_packages($pdo, $batch_id, $count, $operator = '') {
    try {
        $pdo->beginTransaction();

        $created = [];
        $errors = [];

        for ($i = 0; $i < $count; $i++) {
            // 获取下一个自定义编号
            $tracking_number = express_get_next_custom_tracking_number($pdo, $batch_id);

            // 创建包裹记录
            $package_id = express_create_package($pdo, $batch_id, $tracking_number);

            if ($package_id) {
                $created[] = [
                    'package_id' => $package_id,
                    'tracking_number' => $tracking_number
                ];

                // 记录操作日志
                $log_stmt = $pdo->prepare("
                    INSERT INTO express_operation_log
                    (package_id, operation_type, operation_time, operator, old_status, new_status, notes)
                    VALUES (:package_id, 'create_custom', NOW(), :operator, NULL, 'pending', :notes)
                ");
                $log_stmt->execute([
                    'package_id' => $package_id,
                    'operator' => $operator,
                    'notes' => "创建自定义包裹: {$tracking_number}"
                ]);
            } else {
                $errors[] = "创建包裹失败: {$tracking_number}";
            }
        }

        // 更新批次统计
        express_update_batch_statistics($pdo, $batch_id);

        $pdo->commit();

        return [
            'success' => true,
            'message' => "成功创建 " . count($created) . " 个自定义包裹",
            'data' => [
                'created' => $created,
                'errors' => $errors
            ]
        ];

    } catch (PDOException $e) {
        $pdo->rollBack();
        express_log('Failed to create custom packages: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => '创建自定义包裹失败: ' . $e->getMessage()
        ];
    }
}

// ============================================
// 业务操作函数
// ============================================

/**
 * 处理包裹操作（核实/清点/调整）
 * @param PDO $pdo
 * @param int $batch_id
 * @param string $tracking_number
 * @param string $operation_type 'verify', 'count', 'adjust'
 * @param string $operator
 * @param string $content_note 内容备注（清点时使用,向后兼容）
 * @param string $adjustment_note 调整备注（调整时使用）
 * @param string $expiry_date 保质期（清点时使用,向后兼容）
 * @param int $quantity 数量（清点时使用,向后兼容）
 * @param array $products 多产品数据数组（清点时使用,新增）
 * @return array ['success' => bool, 'message' => string, 'package' => array]
 */
function express_process_package($pdo, $batch_id, $tracking_number, $operation_type, $operator, $content_note = null, $adjustment_note = null, $expiry_date = null, $quantity = null, $products = null) {
    try {
        $pdo->beginTransaction();

        // 1. 查找或创建包裹记录
        $stmt = $pdo->prepare("
            SELECT * FROM express_package
            WHERE batch_id = :batch_id AND tracking_number = :tracking_number
        ");
        $stmt->execute([
            'batch_id' => $batch_id,
            'tracking_number' => trim($tracking_number)
        ]);
        $package = $stmt->fetch();

        // 如果不存在，创建新包裹（状态为pending）
        if (!$package) {
            $package_id = express_create_package($pdo, $batch_id, $tracking_number);
            if (!$package_id) {
                $pdo->rollBack();
                return ['success' => false, 'message' => '创建包裹记录失败'];
            }
            $package = express_get_package_by_id($pdo, $package_id);
        }

        $old_status = $package['package_status'];
        $package_id = $package['package_id'];

        // 2. 根据操作类型处理
        $result = null;
        switch ($operation_type) {
            case 'verify':
                $result = express_process_verify($pdo, $package_id, $old_status, $operator);
                break;
            case 'count':
                $result = express_process_count($pdo, $package_id, $old_status, $operator, $content_note, $expiry_date, $quantity, $products);
                break;
            case 'adjust':
                $result = express_process_adjust($pdo, $package_id, $old_status, $operator, $adjustment_note);
                break;
            default:
                $pdo->rollBack();
                return ['success' => false, 'message' => '无效的操作类型'];
        }

        if (!$result['success']) {
            $pdo->rollBack();
            return $result;
        }

        // 3. 记录操作日志
        $stmt = $pdo->prepare("
            INSERT INTO express_operation_log (package_id, operation_type, operator, old_status, new_status, notes)
            VALUES (:package_id, :operation_type, :operator, :old_status, :new_status, :notes)
        ");
        $stmt->execute([
            'package_id' => $package_id,
            'operation_type' => $operation_type,
            'operator' => $operator,
            'old_status' => $old_status,
            'new_status' => $result['new_status'],
            'notes' => $content_note ?? $adjustment_note
        ]);

        // 4. 更新批次统计
        express_update_batch_statistics($pdo, $batch_id);

        $pdo->commit();

        // 获取更新后的包裹信息
        $updated_package = express_get_package_by_id($pdo, $package_id);

        return [
            'success' => true,
            'message' => $result['message'],
            'package' => $updated_package
        ];

    } catch (PDOException $e) {
        $pdo->rollBack();
        express_log('Failed to process package: ' . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => '操作失败: ' . $e->getMessage()];
    }
}

/**
 * 更新包裹的内容备注、保质期和数量
 * @param PDO $pdo
 * @param int $package_id
 * @param string $operator
 * @param string $content_note
 * @param string $expiry_date
 * @param int $quantity
 * @return array
 */
function express_update_content_note($pdo, $package_id, $operator, $content_note, $expiry_date = null, $quantity = null, $items = null) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT package_id, package_status, batch_id FROM express_package WHERE package_id = :package_id");
        $stmt->execute(['package_id' => $package_id]);
        $package = $stmt->fetch();

        if (!$package) {
            $pdo->rollBack();
            return ['success' => false, 'message' => '包裹不存在'];
        }

        $old_status = $package['package_status'];
        $new_status = $old_status;

        // 如果包裹状态是pending或verified，并且填写了内容备注，则自动变为counted（已清点）
        if (in_array($old_status, ['pending', 'verified']) && !empty($content_note)) {
            $new_status = 'counted';

            $update = $pdo->prepare("
                UPDATE express_package
                SET content_note = :content_note,
                    expiry_date = :expiry_date,
                    quantity = :quantity,
                    package_status = :new_status,
                    counted_at = NOW(),
                    counted_by = :counted_by,
                    verified_at = COALESCE(verified_at, NOW()),
                    verified_by = COALESCE(verified_by, :verified_by)
                WHERE package_id = :package_id
            ");

            $update->execute([
                'package_id' => $package_id,
                'content_note' => $content_note,
                'expiry_date' => $expiry_date,
                'quantity' => $quantity,
                'new_status' => $new_status,
                'counted_by' => $operator,
                'verified_by' => $operator
            ]);
        } else {
            // 已经是counted或adjusted状态，只更新内容备注、保质期和数量
            $update = $pdo->prepare("
                UPDATE express_package
                SET content_note = :content_note,
                    expiry_date = :expiry_date,
                    quantity = :quantity
                WHERE package_id = :package_id
            ");

            $update->execute([
                'package_id' => $package_id,
                'content_note' => $content_note,
                'expiry_date' => $expiry_date,
                'quantity' => $quantity
            ]);
        }

        // 处理产品明细
        if ($items !== null && is_array($items)) {
            // 先删除旧的产品明细
            $delete = $pdo->prepare("DELETE FROM express_package_items WHERE package_id = :package_id");
            $delete->execute(['package_id' => $package_id]);

            // 插入新的产品明细
            if (count($items) > 0) {
                $insert = $pdo->prepare("
                    INSERT INTO express_package_items
                    (package_id, product_name, quantity, expiry_date, sort_order)
                    VALUES (:package_id, :product_name, :quantity, :expiry_date, :sort_order)
                ");

                foreach ($items as $index => $item) {
                    if (!empty($item['product_name'])) {
                        $insert->execute([
                            'package_id' => $package_id,
                            'product_name' => trim($item['product_name']),
                            'quantity' => !empty($item['quantity']) ? (int)$item['quantity'] : null,
                            'expiry_date' => !empty($item['expiry_date']) ? $item['expiry_date'] : null,
                            'sort_order' => $index + 1
                        ]);
                    }
                }

                // 生成content_note（只包含产品名称，不包含数量）
                $product_names = [];
                foreach ($items as $item) {
                    if (!empty($item['product_name'])) {
                        // 只使用产品名称，不加数量
                        $product_names[] = trim($item['product_name']);
                    }
                }
                if (count($product_names) > 0) {
                    // 如果只有一个产品，直接使用产品名
                    // 如果有多个产品，用逗号分隔（如："番茄酱, 辣椒酱"）
                    $generated_note = implode(', ', $product_names);
                    // 更新content_note
                    $update_note = $pdo->prepare("
                        UPDATE express_package
                        SET content_note = :content_note
                        WHERE package_id = :package_id
                    ");
                    $update_note->execute([
                        'package_id' => $package_id,
                        'content_note' => $generated_note
                    ]);
                    $content_note = $generated_note;  // 用于日志
                }
            }
        }

        $log = $pdo->prepare("
            INSERT INTO express_operation_log (package_id, operation_type, operator, old_status, new_status, notes)
            VALUES (:package_id, 'update_content', :operator, :old_status, :new_status, :notes)
        ");

        $log->execute([
            'package_id' => $package_id,
            'operator' => $operator,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'notes' => $content_note
        ]);

        // 如果状态发生了变化，需要更新批次统计
        if ($old_status !== $new_status) {
            express_update_batch_statistics($pdo, $package['batch_id']);
        }

        $pdo->commit();

        return [
            'success' => true,
            'message' => ($old_status !== $new_status) ? '内容信息已更新，状态已变为已清点' : '内容信息已更新',
            'package' => express_get_package_by_id($pdo, $package_id)
        ];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        express_log('Failed to update content note: ' . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => '更新内容信息失败'];
    }
}

/**
 * 处理核实操作
 * @param PDO $pdo
 * @param int $package_id
 * @param string $old_status
 * @param string $operator
 * @return array
 */
function express_process_verify($pdo, $package_id, $old_status, $operator) {
    // 任何状态都可以核实
    $stmt = $pdo->prepare("
        UPDATE express_package SET
            package_status = 'verified',
            verified_at = NOW(),
            verified_by = :operator
        WHERE package_id = :package_id
    ");

    $stmt->execute([
        'package_id' => $package_id,
        'operator' => $operator
    ]);

    return [
        'success' => true,
        'message' => '核实成功',
        'new_status' => 'verified'
    ];
}

/**
 * 处理清点操作
 * @param PDO $pdo
 * @param int $package_id
 * @param string $old_status
 * @param string $operator
 * @param string $content_note 向后兼容
 * @param string $expiry_date 保质期（向后兼容）
 * @param int $quantity 数量（向后兼容）
 * @param array $products 多产品数据数组（新增）
 * @return array
 */
function express_process_count($pdo, $package_id, $old_status, $operator, $content_note, $expiry_date = null, $quantity = null, $products = null) {
    // 清点操作自动包含核实，同时更新两个时间戳

    // 生成content_note（只包含产品名称，不包含数量）
    $summary_note = $content_note;
    if ($products && is_array($products) && count($products) > 0) {
        // 从products数组生成产品名称列表（不含数量）
        $product_names = [];
        foreach ($products as $item) {
            if (!empty($item['product_name'])) {
                // 只使用产品名称，不加数量
                $product_names[] = trim($item['product_name']);
            }
        }
        if (count($product_names) > 0) {
            // 如果只有一个产品，直接使用产品名
            // 如果有多个产品，用逗号分隔（如："番茄酱, 辣椒酱"）
            $summary_note = implode(', ', $product_names);
        }
    }

    // 如果有多产品数据，使用第一个产品的有效期和数量（向后兼容）
    $first_expiry = $expiry_date;
    $first_quantity = $quantity;
    if ($products && is_array($products) && count($products) > 0) {
        $first_expiry = $products[0]['expiry_date'] ?? null;
        $first_quantity = $products[0]['quantity'] ?? null;
    }

    $stmt = $pdo->prepare("
        UPDATE express_package SET
            package_status = 'counted',
            verified_at = COALESCE(verified_at, NOW()),
            verified_by = COALESCE(verified_by, :verified_by),
            counted_at = NOW(),
            counted_by = :counted_by,
            content_note = :content_note,
            expiry_date = :expiry_date,
            quantity = :quantity
        WHERE package_id = :package_id
    ");

    $stmt->execute([
        'package_id' => $package_id,
        'verified_by' => $operator,
        'counted_by' => $operator,
        'content_note' => $summary_note,
        'expiry_date' => $first_expiry,
        'quantity' => $first_quantity
    ]);

    // 保存多产品明细数据
    if ($products && is_array($products) && count($products) > 0) {
        // 先删除旧的产品明细
        $stmt = $pdo->prepare("DELETE FROM express_package_items WHERE package_id = :package_id");
        $stmt->execute(['package_id' => $package_id]);

        // 插入新的产品明细
        $stmt = $pdo->prepare("
            INSERT INTO express_package_items
            (package_id, product_name, quantity, expiry_date, sort_order)
            VALUES (:package_id, :product_name, :quantity, :expiry_date, :sort_order)
        ");

        foreach ($products as $item) {
            $stmt->execute([
                'package_id' => $package_id,
                'product_name' => $item['product_name'] ?? null,
                'quantity' => $item['quantity'] ?? null,
                'expiry_date' => $item['expiry_date'] ?? null,
                'sort_order' => $item['sort_order'] ?? 0
            ]);
        }
    }

    return [
        'success' => true,
        'message' => '清点成功',
        'new_status' => 'counted'
    ];
}

/**
 * 处理调整操作
 * @param PDO $pdo
 * @param int $package_id
 * @param string $old_status
 * @param string $operator
 * @param string $adjustment_note
 * @return array
 */
function express_process_adjust($pdo, $package_id, $old_status, $operator, $adjustment_note) {
    // 调整操作需要先经过清点
    if ($old_status !== 'counted' && $old_status !== 'adjusted') {
        return [
            'success' => false,
            'message' => '包裹必须先完成清点才能调整',
            'new_status' => $old_status
        ];
    }

    $stmt = $pdo->prepare("
        UPDATE express_package SET
            package_status = 'adjusted',
            adjusted_at = NOW(),
            adjusted_by = :operator,
            adjustment_note = :adjustment_note
        WHERE package_id = :package_id
    ");

    $stmt->execute([
        'package_id' => $package_id,
        'operator' => $operator,
        'adjustment_note' => $adjustment_note
    ]);

    return [
        'success' => true,
        'message' => '调整成功',
        'new_status' => 'adjusted'
    ];
}

/**
 * 批量导入快递单号
 * @param PDO $pdo
 * @param int $batch_id
 * @param array $tracking_numbers 支持格式：单号 或 单号|有效期|数量
 * @return array ['success' => bool, 'imported' => int, 'duplicates' => int, 'errors' => array]
 */
function express_bulk_import($pdo, $batch_id, $tracking_numbers) {
    $imported = 0;
    $duplicates = 0;
    $errors = [];

    try {
        $pdo->beginTransaction();

        foreach ($tracking_numbers as $line) {
            $line = trim($line);

            // 跳过空行
            if (empty($line)) {
                continue;
            }

            // 解析导入格式：单号|有效期|数量（字段可选）
            $parts = array_map('trim', explode('|', $line));
            $tracking_number = $parts[0] ?? '';
            $expiry_date = !empty($parts[1]) ? $parts[1] : null;
            $quantity = !empty($parts[2]) && is_numeric($parts[2]) ? (int)$parts[2] : null;

            // 跳过无效单号
            if (empty($tracking_number)) {
                continue;
            }

            // 验证有效期格式（如果提供）
            if ($expiry_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry_date)) {
                $errors[] = $tracking_number . ' (有效期格式错误)';
                continue;
            }

            $result = express_create_package($pdo, $batch_id, $tracking_number, $expiry_date, $quantity);

            if ($result) {
                $imported++;
            } else {
                // 检查是否是重复单号
                $stmt = $pdo->prepare("
                    SELECT package_id FROM express_package
                    WHERE batch_id = :batch_id AND tracking_number = :tracking_number
                ");
                $stmt->execute([
                    'batch_id' => $batch_id,
                    'tracking_number' => $tracking_number
                ]);

                if ($stmt->fetch()) {
                    $duplicates++;
                } else {
                    $errors[] = $tracking_number;
                }
            }
        }

        // 更新批次统计
        express_update_batch_statistics($pdo, $batch_id);

        $pdo->commit();

        express_log("Bulk import completed: imported=$imported, duplicates=$duplicates", 'INFO');

        return [
            'success' => true,
            'imported' => $imported,
            'duplicates' => $duplicates,
            'errors' => $errors
        ];

    } catch (PDOException $e) {
        $pdo->rollBack();
        express_log('Failed to bulk import: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => '批量导入失败: ' . $e->getMessage()
        ];
    }
}
