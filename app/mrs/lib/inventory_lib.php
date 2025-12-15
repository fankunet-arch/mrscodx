<?php
/**
 * MRS Inventory Management Library
 * Handles inventory transactions and stock level management
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

/**
 * Record an inventory transaction and update current stock
 *
 * @param PDO $pdo Database connection
 * @param int $sku_id SKU ID
 * @param string $transaction_type 'inbound', 'outbound', or 'adjustment'
 * @param string $transaction_subtype e.g., 'batch_receipt', 'picking', 'surplus', 'deficit'
 * @param float $quantity_change Quantity change (positive for increase, negative for decrease)
 * @param string $unit Unit name
 * @param string $operator_name Operator name
 * @param array $references Optional references: ['batch_id' => X, 'outbound_order_id' => Y, etc.]
 * @param string $remark Optional remark
 * @return bool Success status
 */
function record_inventory_transaction($pdo, $sku_id, $transaction_type, $transaction_subtype, $quantity_change, $unit, $operator_name, $references = [], $remark = null)
{
    try {
        // Get or create inventory record
        $inv_sql = "SELECT inventory_id, current_qty FROM mrs_inventory WHERE sku_id = :sku_id FOR UPDATE";
        $inv_stmt = $pdo->prepare($inv_sql);
        $inv_stmt->execute([':sku_id' => $sku_id]);
        $inventory = $inv_stmt->fetch(PDO::FETCH_ASSOC);

        if ($inventory) {
            $new_qty = $inventory['current_qty'] + $quantity_change;
            $inventory_id = $inventory['inventory_id'];
        } else {
            // Create new inventory record
            $new_qty = $quantity_change;
            $create_inv_sql = "INSERT INTO mrs_inventory (sku_id, current_qty, unit) VALUES (:sku_id, :qty, :unit)";
            $create_inv_stmt = $pdo->prepare($create_inv_sql);
            $create_inv_stmt->execute([
                ':sku_id' => $sku_id,
                ':qty' => $new_qty,
                ':unit' => $unit
            ]);
            $inventory_id = $pdo->lastInsertId();
        }

        // Insert transaction record
        $trans_sql = "INSERT INTO mrs_inventory_transaction (
            sku_id, transaction_type, transaction_subtype, quantity_change, quantity_after, unit,
            batch_id, outbound_order_id, adjustment_id, raw_record_id,
            operator_name, remark, transaction_date
        ) VALUES (
            :sku_id, :type, :subtype, :change, :after, :unit,
            :batch_id, :outbound_id, :adjustment_id, :raw_record_id,
            :operator, :remark, NOW(6)
        )";

        $trans_stmt = $pdo->prepare($trans_sql);
        $trans_stmt->execute([
            ':sku_id' => $sku_id,
            ':type' => $transaction_type,
            ':subtype' => $transaction_subtype,
            ':change' => $quantity_change,
            ':after' => $new_qty,
            ':unit' => $unit,
            ':batch_id' => $references['batch_id'] ?? null,
            ':outbound_id' => $references['outbound_order_id'] ?? null,
            ':adjustment_id' => $references['adjustment_id'] ?? null,
            ':raw_record_id' => $references['raw_record_id'] ?? null,
            ':operator' => $operator_name,
            ':remark' => $remark
        ]);

        $transaction_id = $pdo->lastInsertId();

        // Update inventory with new quantity and last transaction
        $update_inv_sql = "UPDATE mrs_inventory SET current_qty = :qty, last_transaction_id = :trans_id, updated_at = NOW(6) WHERE inventory_id = :inv_id";
        $update_inv_stmt = $pdo->prepare($update_inv_sql);
        $update_inv_stmt->execute([
            ':qty' => $new_qty,
            ':trans_id' => $transaction_id,
            ':inv_id' => $inventory_id
        ]);

        mrs_log("Inventory transaction recorded: SKU={$sku_id}, Type={$transaction_type}, Change={$quantity_change}, After={$new_qty}", 'INFO');
        return true;

    } catch (Exception $e) {
        mrs_log("Failed to record inventory transaction: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Get current inventory for a SKU
 *
 * @param PDO $pdo Database connection
 * @param int $sku_id SKU ID
 * @return array|null Inventory data or null if not found
 */
function get_current_inventory($pdo, $sku_id)
{
    $sql = "SELECT * FROM mrs_inventory WHERE sku_id = :sku_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':sku_id' => $sku_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get inventory transaction history for a SKU
 *
 * @param PDO $pdo Database connection
 * @param int $sku_id SKU ID
 * @param int $limit Number of records to return
 * @return array Transaction history
 */
function get_inventory_transactions($pdo, $sku_id, $limit = 100)
{
    $sql = "SELECT * FROM mrs_inventory_transaction
            WHERE sku_id = :sku_id
            ORDER BY transaction_date DESC, transaction_id DESC
            LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':sku_id', $sku_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
