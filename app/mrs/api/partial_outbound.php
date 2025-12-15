<?php
/**
 * API: Partial Outbound (拆零出库)
 * 文件路径: app/mrs/api/partial_outbound.php
 * 说明: 从包裹中拆出部分数量给门店，记录用量统计
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mrs_json_response(false, null, '非法请求方式');
}

$input = mrs_get_json_input();
if (!$input) {
    $input = $_POST;
}

// 接收参数
$ledger_id = (int)($input['ledger_id'] ?? 0);
$deduct_qty = floatval($input['deduct_qty'] ?? 0);
$destination = trim($input['destination'] ?? '');
$remark = trim($input['remark'] ?? '');
$outbound_date = trim($input['outbound_date'] ?? '');

// 参数验证
if ($ledger_id <= 0) {
    mrs_json_response(false, null, '包裹ID无效');
}

if ($deduct_qty <= 0) {
    mrs_json_response(false, null, '出库数量必须大于0');
}

if (empty($destination)) {
    mrs_json_response(false, null, '目的地（门店）不能为空');
}

// 校验出库日期（可选，默认今天）
$outbound_datetime = new DateTime();
if (!empty($outbound_date)) {
    $parsed_date = DateTime::createFromFormat('Y-m-d', $outbound_date);
    if (!$parsed_date) {
        mrs_json_response(false, null, '出库日期格式无效，应为YYYY-MM-DD');
    }
    // 使用用户选择的日期，时间沿用当前时间
    $outbound_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $outbound_date . ' ' . date('H:i:s'));
}

// 获取操作员
$operator = $_SESSION['user_login'] ?? 'system';

try {
    // 1. 获取包裹信息
    $stmt = $pdo->prepare("
        SELECT ledger_id, content_note, quantity, status
        FROM mrs_package_ledger
        WHERE ledger_id = :ledger_id
    ");
    $stmt->execute(['ledger_id' => $ledger_id]);
    $package = $stmt->fetch();

    if (!$package) {
        mrs_json_response(false, null, '包裹不存在');
    }

    if ($package['status'] !== 'in_stock') {
        mrs_json_response(false, null, '只能从在库包裹中出货');
    }

    // 2. 清洗并解析当前数量（处理不规范数据：纯数字、空值、包含文字）
    $quantity_str = $package['quantity'] ?? '';

    // 清洗数量：提取数字部分
    if ($quantity_str === null || $quantity_str === '') {
        $current_qty = 0.0;
    } else {
        // 移除所有非数字字符（保留小数点）
        $cleaned = preg_replace('/[^0-9.]/', '', trim((string)$quantity_str));
        $current_qty = $cleaned !== '' ? floatval($cleaned) : 0.0;
    }

    // 3. 检查库存是否足够
    if ($deduct_qty > $current_qty) {
        mrs_json_response(false, null, "库存不足。当前库存：{$current_qty} 件，需要出库：{$deduct_qty} 件");
    }

    // 4. 计算剩余数量
    $new_qty = $current_qty - $deduct_qty;

    // 5. 开启事务
    $pdo->beginTransaction();

    // 6. 更新包裹数量
    $update_stmt = $pdo->prepare("
        UPDATE mrs_package_ledger
        SET quantity = :new_qty,
            updated_at = NOW()
        WHERE ledger_id = :ledger_id
    ");
    $update_stmt->execute([
        'new_qty' => $new_qty,
        'ledger_id' => $ledger_id
    ]);

    // 7. 记录出库到统计表
    $insert_stmt = $pdo->prepare("
        INSERT INTO mrs_usage_log (
            ledger_id,
            product_name,
            outbound_type,
            deduct_qty,
            destination,
            operator,
            created_at,
            remark
        ) VALUES (
            :ledger_id,
            :product_name,
            'partial',
            :deduct_qty,
            :destination,
            :operator,
            :created_at,
            :remark
        )
    ");
    $insert_stmt->execute([
        'ledger_id' => $ledger_id,
        'product_name' => $package['content_note'],
        'deduct_qty' => $deduct_qty,
        'destination' => $destination,
        'operator' => $operator,
        'created_at' => $outbound_datetime->format('Y-m-d H:i:s'),
        'remark' => $remark ?: null
    ]);

    // 8. 提交事务
    $pdo->commit();

    // 9. 返回成功
    mrs_json_response(true, [
        'ledger_id' => $ledger_id,
        'product_name' => $package['content_note'],
        'deduct_qty' => $deduct_qty,
        'remaining_qty' => $new_qty,
        'destination' => $destination
    ], "拆零出库成功！已从包裹中扣减 {$deduct_qty} 件，剩余 {$new_qty} 件");

} catch (PDOException $e) {
    // 回滚事务
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    mrs_log('Partial outbound error: ' . $e->getMessage(), 'ERROR', [
        'ledger_id' => $ledger_id,
        'deduct_qty' => $deduct_qty,
        'destination' => $destination
    ]);

    mrs_json_response(false, null, '拆零出库失败：' . $e->getMessage());
} catch (Exception $e) {
    // 回滚事务
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    mrs_json_response(false, null, '拆零出库失败：' . $e->getMessage());
}
