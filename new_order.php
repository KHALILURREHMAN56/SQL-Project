<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$pdo = getDBConnection();
$message = '';
$messageType = '';

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_order') {
    try {
        // Validate input data
        if (empty($_POST['items']) || !is_array($_POST['items'])) {
            throw new Exception("No items selected");
        }
        
        if (empty($_POST['quantities']) || !is_array($_POST['quantities'])) {
            throw new Exception("Invalid quantities");
        }
        
        if (empty($_POST['payment_method'])) {
            throw new Exception("Payment method is required");
        }
        
        $totalAmount = str_replace(['Rs. ', ','], '', $_POST['total_amount']);
        if (!is_numeric($totalAmount) || $totalAmount <= 0) {
            throw new Exception("Invalid total amount");
        }

        $pdo->beginTransaction();

        // Get next daily order number
        $dailyOrderNumber = getNextDailyOrderNumber($pdo);

        // Insert into orders table
        $stmt = $pdo->prepare("INSERT INTO orders (daily_order_number, order_date, total_amount, payment_method, status, attendant_id) VALUES (?, NOW(), ?, ?, 'completed', ?)");
        $stmt->execute([
            $dailyOrderNumber,
            $totalAmount,
            $_POST['payment_method'],
            $_SESSION['user_id']
        ]);
        
        $orderId = $pdo->lastInsertId();

        // Insert order items and their toppings
        $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, item_id, quantity, price) VALUES (?, ?, ?, ?)");
        $toppingStmt = $pdo->prepare("INSERT INTO order_toppings (order_item_id, topping_id, quantity) VALUES (?, ?, 1)");
        
        foreach ($_POST['items'] as $key => $itemId) {
            if (empty($itemId)) {
                continue; // Skip empty items
            }
            
            // Validate item exists
            $priceStmt = $pdo->prepare("SELECT price_pkr, name FROM menu WHERE item_id = ? AND is_available = 1");
            $priceStmt->execute([$itemId]);
            $itemData = $priceStmt->fetch();
            
            if (!$itemData) {
                throw new Exception("Invalid or unavailable item selected");
            }
            
            $quantity = isset($_POST['quantities'][$key]) ? (int)$_POST['quantities'][$key] : 0;
            if ($quantity <= 0) {
                throw new Exception("Invalid quantity for " . $itemData['name']);
            }
            
            $itemStmt->execute([
                $orderId,
                $itemId,
                $quantity,
                $itemData['price_pkr']
            ]);
            
            $orderItemId = $pdo->lastInsertId();
            
            // Add toppings for this item if selected
            if (isset($_POST['toppings'][$key]) && is_array($_POST['toppings'][$key])) {
                foreach ($_POST['toppings'][$key] as $toppingId) {
                    // Validate topping exists
                    $toppingCheckStmt = $pdo->prepare("SELECT 1 FROM toppings WHERE topping_id = ? AND is_available = 1");
                    $toppingCheckStmt->execute([$toppingId]);
                    if ($toppingCheckStmt->fetchColumn()) {
                        $toppingStmt->execute([$orderItemId, $toppingId]);
                    }
                }
            }
        }

        $pdo->commit();
        header("Location: print_order.php?order_id=" . $orderId);
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error placing order: " . $e->getMessage();
        $messageType = "error";
    }
}

// Fetch menu items for the order form
try {
    $menuStmt = $pdo->query("SELECT * FROM menu WHERE is_available = 1 ORDER BY name");
    $menuItems = $menuStmt->fetchAll();
    
    // Fetch all toppings
    $toppingStmt = $pdo->query("SELECT * FROM toppings WHERE is_available = 1 ORDER BY name");
    $toppings = $toppingStmt->fetchAll();
} catch(PDOException $e) {
    $menuItems = [];
    $toppings = [];
    $message = "Error loading menu items and toppings: " . $e->getMessage();
    $messageType = "error";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Order - Anees Ice Cream Parlor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        .order-item {
            background: #fff;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .topping-container {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
            background: #f8f9fa;
        }
        
        .topping-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .topping-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .topping-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 8px 12px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .topping-item:hover {
            border-color: #30D5C8;
            background: #f0fdfb;
        }
        
        .topping-item.selected {
            background: #30D5C8;
            color: white;
            border-color: #30D5C8;
        }
        
        .topping-item input[type="checkbox"] {
            margin-right: 8px;
        }
        
        .topping-price {
            font-size: 0.85em;
            color: #6c757d;
        }
        
        .topping-item.selected .topping-price {
            color: rgba(255, 255, 255, 0.9);
        }
        
        .item-subtotal {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quantity-control button {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 1px solid #dee2e6;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .quantity-control button:hover {
            background: #30D5C8;
            color: white;
            border-color: #30D5C8;
        }
        
        .quantity-control input {
            width: 60px;
            text-align: center;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 5px;
        }
        
        .select2-container--bootstrap-5 .select2-selection {
            border-radius: 10px;
            border: 1px solid #dee2e6;
            padding: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
            <?php echo h($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>New Order</h2>
            <a href="sales.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Sales
            </a>
        </div>

        <form id="orderForm" method="POST">
            <input type="hidden" name="action" value="add_order">
            
            <div id="orderItems">
                <!-- Order items will be added here dynamically -->
            </div>
            
            <div class="text-center mb-4">
                <button type="button" class="btn btn-primary btn-add-item">
                    <i class="fas fa-plus me-2"></i>Add Another Item
                </button>
            </div>

            <hr>

            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Payment Method *</label>
                        <select class="form-select" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="debit_card">Debit Card</option>
                            <option value="mobile_payment">Mobile Payment</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Total Amount</label>
                        <input type="text" class="form-control bg-light" name="total_amount" id="totalAmount" readonly>
                    </div>
                </div>
            </div>

            <div class="text-end mt-4">
                <a href="sales.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary ms-2">Place Order</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Pass PHP data to JavaScript
        window.menuItems = <?php echo !empty($menuItems) ? json_encode($menuItems) : '[]'; ?>;
        window.toppings = <?php echo !empty($toppings) ? json_encode($toppings) : '[]'; ?>;
        
        let itemCounter = 0;
        
        // Format currency
        function formatCurrency(amount) {
            return parseFloat(amount).toFixed(2);
        }
        
        // Get menu items options
        function getMenuItemsOptions() {
            if (!window.menuItems || !window.menuItems.length) {
                return '<option value="">No items available</option>';
            }
            return window.menuItems.map(item => `
                <option value="${item.item_id}" data-price="${item.price_pkr}">
                    ${item.name} (Rs. ${formatCurrency(item.price_pkr)})
                </option>
            `).join('');
        }
        
        // Get toppings HTML
        function getToppingsHTML(index) {
            if (!window.toppings || !window.toppings.length) {
                return '<p class="text-muted">No toppings available</p>';
            }
            
            return `
                <div class="topping-container">
                    <div class="topping-header">
                        <label class="form-label mb-0">Add Toppings</label>
                        <small class="text-muted">Select multiple</small>
                    </div>
                    <div class="topping-grid">
                        ${window.toppings.map(topping => `
                            <div class="topping-item" onclick="toggleTopping(this, ${index}, ${topping.topping_id})">
                                <input type="checkbox" name="toppings[${index}][]" value="${topping.topping_id}" style="display: none;">
                                <span class="topping-name">${topping.name}</span>
                                <span class="topping-price">+Rs. ${formatCurrency(topping.price)}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }
        
        // Toggle topping selection
        function toggleTopping(element, itemIndex, toppingId) {
            const checkbox = element.querySelector('input[type="checkbox"]');
            element.classList.toggle('selected');
            checkbox.checked = !checkbox.checked;
            updatePrice(itemIndex);
        }
        
        // Add new order item
        function addOrderItem() {
            if (!window.menuItems || !window.menuItems.length) {
                alert('Cannot add items: No menu items available');
                return;
            }

            const template = `
                <div class="order-item" id="item-${itemCounter}">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Item #${itemCounter + 1}</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeOrderItem(${itemCounter})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Select Item *</label>
                            <select class="form-select item-select" name="items[]" required onchange="updatePrice(${itemCounter})">
                                <option value="">Select Item</option>
                                ${getMenuItemsOptions()}
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Quantity *</label>
                            <div class="quantity-control">
                                <button type="button" onclick="updateQuantity(${itemCounter}, -1)">-</button>
                                <input type="number" class="form-control" name="quantities[]" value="1" min="1" required onchange="updatePrice(${itemCounter})">
                                <button type="button" onclick="updateQuantity(${itemCounter}, 1)">+</button>
                            </div>
                        </div>
                        <div class="col-12">
                            ${getToppingsHTML(itemCounter)}
                        </div>
                        <div class="col-12">
                            <div class="item-subtotal text-end">
                                Subtotal: Rs. 0.00
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#orderItems').append(template);
            itemCounter++;
            updateTotalAmount();
        }
        
        // Update quantity
        function updateQuantity(index, change) {
            const $input = $(`#item-${index} input[name="quantities[]"]`);
            let value = parseInt($input.val()) || 1;
            value = Math.max(1, value + change);
            $input.val(value);
            updatePrice(index);
        }
        
        // Calculate item subtotal
        function calculateItemSubtotal($item) {
            let subtotal = 0;
            try {
                const item = $item.find('select[name="items[]"] option:selected');
                const quantity = parseInt($item.find('input[name="quantities[]"]').val()) || 0;
                const price = parseFloat(item.data('price')) || 0;
                
                // Add item price
                subtotal += price * quantity;
                
                // Add toppings price
                const selectedToppings = $item.find('input[type="checkbox"]:checked');
                selectedToppings.each(function() {
                    const toppingId = $(this).val();
                    const topping = window.toppings.find(t => t.topping_id == toppingId);
                    if (topping) {
                        subtotal += topping.price * quantity;
                    }
                });
            } catch (error) {
                console.error('Error calculating subtotal:', error);
                subtotal = 0;
            }
            return subtotal;
        }
        
        // Update price for an item
        function updatePrice(index) {
            const $item = $(`#item-${index}`);
            const subtotal = calculateItemSubtotal($item);
            $item.find('.item-subtotal').text(`Subtotal: Rs. ${formatCurrency(subtotal)}`);
            updateTotalAmount();
        }
        
        // Update total amount
        function updateTotalAmount() {
            let total = 0;
            $('.order-item').each(function() {
                total += calculateItemSubtotal($(this));
            });
            $('#totalAmount').val(`Rs. ${formatCurrency(total)}`);
        }
        
        // Remove order item
        function removeOrderItem(index) {
            $(`#item-${index}`).remove();
            updateTotalAmount();
            
            // If no items left, add a new one
            if ($('.order-item').length === 0) {
                addOrderItem();
            }
        }
        
        // Initialize first item
        $(document).ready(function() {
            addOrderItem();
            
            // Add item button click handler
            $('.btn-add-item').click(addOrderItem);
            
            // Form validation
            $('#orderForm').submit(function(e) {
                const items = $('select[name="items[]"]').map(function() {
                    return $(this).val();
                }).get().filter(Boolean);
                
                if (items.length === 0) {
                    e.preventDefault();
                    alert('Please add at least one item to the order');
                }
            });
        });
    </script>
</body>
</html> 