<?php
// Action: batch_detail.php - 批次详情/确认入库页面

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

$batch_id = $_GET['id'] ?? null;

if (!$batch_id) {
    $_SESSION['error_message'] = '批次ID缺失';
    header('Location: /mrs/be/index.php?action=batch_list');
    exit;
}

try {
    $pdo = get_db_connection();

    // 获取批次信息
    $stmt = $pdo->prepare("SELECT * FROM mrs_batch WHERE batch_id = ?");
    $stmt->execute([$batch_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        $_SESSION['error_message'] = '批次不存在';
        header('Location: /mrs/be/index.php?action=batch_list');
        exit;
    }

    // 获取批次的原始记录
    $stmt = $pdo->prepare("
        SELECT r.*, s.sku_name, s.brand_name, s.standard_unit, c.category_name
        FROM mrs_batch_raw_record r
        LEFT JOIN mrs_sku s ON r.sku_id = s.sku_id
        LEFT JOIN mrs_category c ON s.category_id = c.category_id
        WHERE r.batch_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$batch_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 获取合并数据（按SKU汇总）- 包括所有状态和未知物料
    $merge_data = [];
    $aggregated_data = [];
    $confirmed_items = [];
    if (in_array($batch['batch_status'], ['receiving', 'pending_merge', 'draft', 'confirmed'])) {
        // 获取已确认的数量（用于显示保存后的数据）
        $confirm_stmt = $pdo->prepare("SELECT sku_id, confirmed_case_qty, confirmed_single_qty, total_standard_qty FROM mrs_batch_confirmed_item WHERE batch_id = ?");
        $confirm_stmt->execute([$batch_id]);
        foreach ($confirm_stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $confirmed_items[$item['sku_id']] = $item;
        }

        // Query ALL raw records grouped by SKU and processing_status
        // IMPORTANT: Use LEFT JOIN to include records with NULL sku_id (unknown items)
        $stmt = $pdo->prepare("
            SELECT
                r.sku_id,
                r.input_sku_name,
                r.processing_status,
                COALESCE(s.sku_name, r.input_sku_name, '未知物料') as sku_name,
                COALESCE(s.brand_name, '未知品牌') as brand_name,
                COALESCE(c.category_name, '未分类') as category_name,
                COALESCE(s.is_precise_item, 1) as is_precise_item,
                COALESCE(s.standard_unit, r.unit_name) as standard_unit,
                s.case_unit_name,
                s.case_to_standard_qty,
                r.unit_name as input_unit_name,
                SUM(
                    CASE
                        WHEN r.unit_name = s.case_unit_name AND s.case_to_standard_qty > 0
                        THEN r.qty * s.case_to_standard_qty
                        ELSE r.qty
                    END
                ) as total_quantity,
                SUM(r.qty) as total_raw_input_qty,
                SUM(COALESCE(r.physical_box_count, 0)) as total_physical_boxes,
                COUNT(*) as record_count
            FROM mrs_batch_raw_record r
            LEFT JOIN mrs_sku s ON r.sku_id = s.sku_id
            LEFT JOIN mrs_category c ON s.category_id = c.category_id
            WHERE r.batch_id = ?
            GROUP BY r.sku_id, r.input_sku_name, r.processing_status, s.sku_name, s.brand_name, c.category_name, s.is_precise_item, s.standard_unit, s.case_unit_name, s.case_to_standard_qty, r.unit_name
        ");
        $stmt->execute([$batch_id]);
        $merge_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Transform merge_data to aggregated_data format expected by view
        // Group by SKU and show all records with their processing status
        foreach ($merge_data as $item) {
            $sku_id = $item['sku_id'];
            $processing_status = $item['processing_status'];
            $case_to_standard = floatval($item['case_to_standard_qty'] ?? 0);
            $total_qty = floatval($item['total_quantity']);
            $total_raw_input_qty = floatval($item['total_raw_input_qty']);
            $total_physical_boxes = floatval($item['total_physical_boxes'] ?? 0);

            // [FIX] Dynamic Spec Calculation
            // If physical box count is present, use it to derive the dynamic spec (coefficient).
            // This ensures the backend calculates totals based on the ACTUAL box configuration.
            $use_input_units = false;
            $display_coefficient = $case_to_standard;
            $display_raw_total = (int)$total_qty;
            $display_unit_name = $item['standard_unit'];

            if ($total_physical_boxes > 0 && $total_qty > 0) {
                // Check if we should display in Input Units (e.g. Box/Box) instead of Standard Units (Pieces/Box)
                // If the input unit is different from Standard Unit, use the Input Unit for display to match frontend experience
                if ($item['input_unit_name'] !== $item['standard_unit']) {
                    $use_input_units = true;
                    // Coefficient = Total Input Qty / Physical Boxes (e.g. 50 Boxes / 5 PhysBoxes = 10)
                    $display_coefficient = $total_raw_input_qty / $total_physical_boxes;
                    $display_raw_total = (int)$total_raw_input_qty;
                    $display_unit_name = $item['input_unit_name'];
                    
                    // For internal calculation (standardization), we still need the standard spec
                    $dynamic_spec = $total_qty / $total_physical_boxes;
                    $case_to_standard = $dynamic_spec;
                } else {
                    // Standard Unit Input: simple division
                    $dynamic_spec = $total_qty / $total_physical_boxes;
                    $case_to_standard = $dynamic_spec;
                    $display_coefficient = $dynamic_spec;
                    $display_raw_total = (int)$total_qty;
                }
            } else {
                $display_coefficient = $case_to_standard;
            }

            // Create unique key for SKU + status combination
            // For unknown items (sku_id IS NULL), use input_sku_name
            if ($sku_id) {
                // [FIX] Include input unit in unique key to prevent overwriting when multiple units are used for same SKU
                $unique_key = $sku_id . '_' . md5($item['input_unit_name'] ?? '') . '_' . $processing_status;
            } else {
                $unique_key = 'unknown_' . md5($item['input_sku_name'] ?? '') . '_' . md5($item['input_unit_name'] ?? '') . '_' . $processing_status;
            }

            // Calculate case and single quantities
            $calculated_case_qty = 0;
            $calculated_single_qty = 0;

            // [FIX] Priority Logic: Use physical box count if available
            if ($total_physical_boxes > 0) {
                $calculated_case_qty = $total_physical_boxes;
                // For dynamic spec, usually single qty is 0 because the spec absorbs the remainder,
                // or we consider the box count as the primary unit.
                // However, let's keep the math strict: Total = Box * Spec + Single.
                // Since Spec = Total / Box, then Total = Box * (Total/Box) + 0.
                // So Single is 0.
                $calculated_single_qty = 0;
            } elseif ($case_to_standard > 0 && fmod($case_to_standard, 1.0) == 0.0) {
                // If case conversion is a whole number, calculate breakdown
                $case_size = (int)$case_to_standard;
                $calculated_case_qty = intdiv((int)$total_qty, $case_size);
                $calculated_single_qty = (int)$total_qty % $case_size;
            } else {
                // Otherwise, all goes to single quantity
                $calculated_single_qty = (int)$total_qty;
            }

            // Build sku_spec string
            $sku_spec = '';
            if ($display_coefficient > 0 && !empty($item['case_unit_name'])) {
                $is_dynamic = ($total_physical_boxes > 0);
                // [UI FIX] Use 2 decimals precision
                // If using input units, display e.g. "10.00 Box/Box"
                // If standard units, display e.g. "200.00 Piece/Box"
                $case_unit_label = $item['case_unit_name']; // Usually 'Box' or 'Carton'
                
                // If dynamic and input unit is used, we might want to say "Box/PhysicalBox"
                // But let's stick to "Unit/CaseUnit" format
                $sku_spec = format_number($display_coefficient, 2) . ' ' . $display_unit_name . '/' . $case_unit_label;
                
                if ($is_dynamic) {
                    $sku_spec .= ' (动态)';
                }
            } else {
                $sku_spec = $item['standard_unit'];
            }

            $confirm_item = ($sku_id !== null && isset($confirmed_items[$sku_id])) ? $confirmed_items[$sku_id] : null;

            // If confirmed item exists, we need to decide what to show as "calculated_total"
            // If using input units, we should probably attempt to back-calculate confirmed qty to input units?
            // This is tricky. Confirmed items only store Standard Qty.
            // If the row is already confirmed, we might want to switch back to Standard Units display to avoid confusion?
            // Or just show Standard Units for confirmed items.
            // But let's try to be consistent with "pending" view.
            
            // For now, if confirmed, we just use the confirmed totals (which are in Standard Units).
            // So if confirmed, the display might revert to Standard Units unless we convert it back.
            // Since the user is likely complaining about the PENDING state (verification), we focus on that.
            
            $aggregated_data[$unique_key] = [
                'sku_id' => $sku_id,
                'processing_status' => $processing_status,
                'sku_name' => $item['sku_name'],
                'brand_name' => $item['brand_name'],
                'category_name' => $item['category_name'],
                'sku_spec' => $sku_spec,
                'current_coefficient' => $display_coefficient, // [UI FIX] Pass display coefficient (Input Units)
                'case_to_standard_qty' => $display_coefficient, // Used by JS for calculation
                'standard_unit' => $display_unit_name, // Display Unit Name
                'calculated_case_qty' => $calculated_case_qty,
                'calculated_single_qty' => $calculated_single_qty,
                'calculated_total' => $display_raw_total, // Display Total (Input Units)
                'raw_total' => $display_raw_total, // Display Raw Total (Input Units)
                'raw_physical_boxes' => $total_physical_boxes, // [UI FIX] Pass raw box count
                'record_count' => $item['record_count'],
                'confirmed_case_qty' => $confirm_item['confirmed_case_qty'] ?? null,
                'confirmed_single_qty' => $confirm_item['confirmed_single_qty'] ?? null,
                'confirmed_total' => $confirm_item['total_standard_qty'] ?? null,
            ];
        }
    }

} catch (PDOException $e) {
    mrs_log("Failed to load batch detail: " . $e->getMessage(), 'ERROR');
    $_SESSION['error_message'] = '加载批次详情失败';
    header('Location: /mrs/be/index.php?action=batch_list');
    exit;
}

$is_spa = false;
$page_title = "批次详情 - " . $batch['batch_code'];
$action = 'batch_detail';

require_once MRS_VIEW_PATH . '/batch_detail.php';
