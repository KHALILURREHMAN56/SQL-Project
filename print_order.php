<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Check if order ID is provided
if (!isset($_GET['order_id'])) {
    header('Location: sales.php');
    exit();
}

$pdo = getDBConnection();
$orderId = (int)$_GET['order_id'];

try {
    // Fetch order details
    $orderStmt = $pdo->prepare("
        SELECT 
            o.order_id,
            o.daily_order_number,
            o.order_date,
            o.total_amount,
            o.payment_method,
            u.username as attendant_name
        FROM orders o
        JOIN users u ON o.attendant_id = u.user_id
        WHERE o.order_id = ?
    ");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();

    if (!$order) {
        throw new Exception("Order not found");
    }

    // Fetch order items with toppings
    $itemsStmt = $pdo->prepare("
        SELECT 
            m.name as item_name,
            oi.quantity,
            oi.price as unit_price,
            GROUP_CONCAT(t.name SEPARATOR ', ') as toppings
        FROM order_items oi
        JOIN menu m ON oi.item_id = m.item_id
        LEFT JOIN order_toppings ot ON oi.order_item_id = ot.order_item_id
        LEFT JOIN toppings t ON ot.topping_id = t.topping_id
        WHERE oi.order_id = ?
        GROUP BY oi.order_item_id, m.name, oi.quantity, oi.price
    ");
    $itemsStmt->execute([$orderId]);
    $items = $itemsStmt->fetchAll();

} catch (Exception $e) {
    header("Location: sales.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?php echo $orderId; ?> - Print View</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
                margin: 0;
            }
            .container {
                width: 100%;
                max-width: none;
                padding: 0;
                margin: 0;
            }
        }
        .receipt {
            max-width: 80mm;
            margin: 0 auto;
            padding: 20px;
            font-family: 'Courier New', monospace;
        }
        .receipt-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .receipt-header h1 {
            font-size: 1.2em;
            margin: 0;
        }
        .receipt-header p {
            margin: 5px 0;
            font-size: 0.9em;
        }
        .receipt-body {
            margin: 20px 0;
        }
        .receipt-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.8em;
        }
        .item-row {
            margin: 10px 0;
            font-size: 0.9em;
        }
        .total-row {
            border-top: 1px dashed #000;
            margin-top: 10px;
            padding-top: 10px;
        }
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
        }
    </style>
</head>
<body>
    <button onclick="window.print()" class="btn btn-primary print-btn no-print">
        Print Order
    </button>

    <div class="receipt">
        <div class="receipt-header">
            <h1>Anees Ice Cream Parlor</h1>
            <p>Your Destination for Premium Ice Cream</p>
            <p>Order #<?php echo $order['daily_order_number']; ?></p>
            <p><?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></p>
        </div>

        <div class="receipt-body">
            <div class="items">
                <?php foreach ($items as $item): ?>
                    <div class="item-row">
                        <div><?php echo $item['quantity']; ?>x <?php echo h($item['item_name']); ?></div>
                        <div>
                            <?php if ($item['toppings']): ?>
                                <small>+ <?php echo h($item['toppings']); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="text-end">Rs. <?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="total-row">
                <div class="d-flex justify-content-between">
                    <strong>Total Amount:</strong>
                    <strong>Rs. <?php echo number_format($order['total_amount'], 2); ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Payment Method:</span>
                    <span><?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></span>
                </div>
            </div>
        </div>

        <div class="receipt-footer">
            <p>Served by: <?php echo h($order['attendant_name']); ?></p>
            <p>Thank you for your business!</p>
            <p>Please visit us again</p>
        </div>
    </div>

    <script>
        // Automatically open print dialog when page loads
        window.onload = function() {
            if (!window.location.search.includes('noprint')) {
                window.print();
            }
        };
    </script>
</body>
</html> 