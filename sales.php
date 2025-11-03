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

// Initialize filters with default values
$dateRange = isset($_GET['date_range']) ? $_GET['date_range'] : 'today';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$paymentFilter = isset($_GET['payment']) ? $_GET['payment'] : 'all';
$sortOrder = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';

// Calculate date range based on selection
switch ($dateRange) {
    case 'yesterday':
        $startDate = $endDate = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'last7days':
        $startDate = date('Y-m-d', strtotime('-6 days'));
        $endDate = date('Y-m-d');
        break;
    case 'thismonth':
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        break;
    case 'lastmonth':
        $startDate = date('Y-m-01', strtotime('last month'));
        $endDate = date('Y-m-t', strtotime('last month'));
        break;
    case 'custom':
        // Use the provided dates
        break;
    default: // today
        $startDate = $endDate = date('Y-m-d');
}

// Initialize statistics variables
$totalSales = 0;
$totalItems = 0;
$sales = [];

// Fetch sales data with filters
try {
    $query = "SELECT 
                o.order_id,
                o.daily_order_number,
                o.order_date,
                m.name as product_name,
                oi.quantity,
                oi.price as unit_price,
                GROUP_CONCAT(t.name SEPARATOR ', ') as toppings,
                o.total_amount,
                o.payment_method,
                u.username as sold_by
              FROM orders o
              JOIN order_items oi ON o.order_id = oi.order_id
              JOIN menu m ON oi.item_id = m.item_id
              JOIN users u ON o.attendant_id = u.user_id
              LEFT JOIN order_toppings oit ON oi.order_item_id = oit.order_item_id
              LEFT JOIN toppings t ON oit.topping_id = t.topping_id
              WHERE DATE(o.order_date) BETWEEN ? AND ?";
    
    $params = [$startDate, $endDate];
    
    if ($paymentFilter !== 'all') {
        $query .= " AND o.payment_method = ?";
        $params[] = str_replace('card', 'credit_card', $paymentFilter);
    }
    
    $query .= " GROUP BY o.order_id, o.order_date, m.name, oi.quantity, oi.price, o.total_amount, o.payment_method, u.username";
    
    // Add sorting
    switch ($sortOrder) {
        case 'date_asc':
            $query .= " ORDER BY o.order_date ASC";
            break;
        case 'amount_desc':
            $query .= " ORDER BY o.total_amount DESC";
            break;
        case 'amount_asc':
            $query .= " ORDER BY o.total_amount ASC";
            break;
        case 'id_desc':
            $query .= " ORDER BY o.order_id DESC";
            break;
        case 'id_asc':
            $query .= " ORDER BY o.order_id ASC";
            break;
        default: // date_desc
            $query .= " ORDER BY o.order_date DESC";
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $sales = $stmt->fetchAll();

    // Calculate summary statistics
    $totalSales = array_sum(array_column($sales, 'total_amount'));
    $totalItems = array_sum(array_column($sales, 'quantity'));
    
} catch(PDOException $e) {
    $message = "Error fetching data: " . $e->getMessage();
    $messageType = "error";
    $sales = [];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Management - Anees Ice Cream Parlor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <main>
        <div class="container py-4">
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                <?php echo h($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="mb-0">Sales Management</h1>
                <a href="new_order.php" class="btn btn-new-order">
                    <i class="fas fa-plus me-2"></i>Add New Order
                </a>
            </div>

            <!-- Sales Statistics -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="sales-stats">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-value">Rs. <?php echo number_format($totalSales, 2); ?></div>
                        <div class="stat-label">Total Sales</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="sales-stats">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-value"><?php echo number_format($totalItems); ?></div>
                        <div class="stat-label">Items Sold</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="sales-stats">
                        <div class="stat-icon">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="stat-value"><?php echo count($sales); ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                </div>
            </div>

            <!-- Sales Filters -->
            <div class="sales-filters">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Date Range</label>
                        <select name="date_range" class="form-select" onchange="this.form.submit()">
                            <option value="today" <?php echo $dateRange === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="yesterday" <?php echo $dateRange === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                            <option value="last7days" <?php echo $dateRange === 'last7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="thismonth" <?php echo $dateRange === 'thismonth' ? 'selected' : ''; ?>>This Month</option>
                            <option value="lastmonth" <?php echo $dateRange === 'lastmonth' ? 'selected' : ''; ?>>Last Month</option>
                            <option value="custom" <?php echo $dateRange === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                    
                    <?php if ($dateRange === 'custom'): ?>
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-3">
                        <label class="form-label">Payment Method</label>
                        <select name="payment" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $paymentFilter === 'all' ? 'selected' : ''; ?>>All Methods</option>
                            <option value="cash" <?php echo $paymentFilter === 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="credit_card" <?php echo $paymentFilter === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                            <option value="debit_card" <?php echo $paymentFilter === 'debit_card' ? 'selected' : ''; ?>>Debit Card</option>
                            <option value="mobile_payment" <?php echo $paymentFilter === 'mobile_payment' ? 'selected' : ''; ?>>Mobile Payment</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Sort By</label>
                        <select name="sort" class="form-select" onchange="this.form.submit()">
                            <option value="date_desc" <?php echo $sortOrder === 'date_desc' ? 'selected' : ''; ?>>Date (Newest First)</option>
                            <option value="date_asc" <?php echo $sortOrder === 'date_asc' ? 'selected' : ''; ?>>Date (Oldest First)</option>
                            <option value="amount_desc" <?php echo $sortOrder === 'amount_desc' ? 'selected' : ''; ?>>Amount (Highest First)</option>
                            <option value="amount_asc" <?php echo $sortOrder === 'amount_asc' ? 'selected' : ''; ?>>Amount (Lowest First)</option>
                            <option value="id_desc" <?php echo $sortOrder === 'id_desc' ? 'selected' : ''; ?>>Order ID (Highest First)</option>
                            <option value="id_asc" <?php echo $sortOrder === 'id_asc' ? 'selected' : ''; ?>>Order ID (Lowest First)</option>
                        </select>
                    </div>
                </form>
            </div>

            <style>
                .filter-card {
                    background: white;
                    border-radius: 10px;
                    padding: 20px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }

                .form-label {
                    font-weight: 500;
                    color: #666;
                    margin-bottom: 0.5rem;
                }

                .form-select, .form-control {
                    border-color: #dee2e6;
                    border-radius: 0.375rem;
                }

                .form-select:focus, .form-control:focus {
                    border-color: var(--primary-color);
                    box-shadow: 0 0 0 0.25rem rgba(48, 213, 200, 0.25);
                }

                .btn-primary {
                    background-color: var(--primary-color);
                    border-color: var(--primary-color);
                }

                .btn-primary:hover {
                    background-color: #28c0b5;
                    border-color: #28c0b5;
                }

                .payment-badge {
                    padding: 6px 12px;
                    border-radius: 20px;
                    font-size: 0.875rem;
                    font-weight: 500;
                }

                .payment-cash {
                    background-color: #e8f5e9;
                    color: #2e7d32;
                }

                .payment-credit-card {
                    background-color: #e3f2fd;
                    color: #1565c0;
                }

                .payment-debit-card {
                    background-color: #f3e5f5;
                    color: #7b1fa2;
                }

                .payment-mobile-payment {
                    background-color: #fff3e0;
                    color: #f57c00;
                }
            </style>

            <script>
                function toggleCustomDates(value) {
                    const customDateRange = document.getElementById('customDateRange');
                    customDateRange.style.display = value === 'custom' ? 'block' : 'none';
                }

                // Initialize custom date range visibility
                document.addEventListener('DOMContentLoaded', function() {
                    const dateRange = document.querySelector('select[name="date_range"]').value;
                    toggleCustomDates(dateRange);
                });
            </script>

            <!-- Sales Table -->
            <div class="sales-table">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date & Time</th>
                                <th>Items</th>
                                <th>Payment</th>
                                <th>Amount</th>
                                <th>Sold By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sales)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-info-circle me-2"></i>No sales found for the selected period
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($sales as $sale): ?>
                                <tr>
                                    <td>#<?php echo $sale['daily_order_number']; ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($sale['order_date'])); ?></td>
                                    <td>
                                        <ul class="order-items">
                                            <li class="order-item">
                                                <span class="order-item-quantity"><?php echo $sale['quantity']; ?>x</span>
                                                <span class="order-item-name"><?php echo h($sale['product_name']); ?></span>
                                                <?php if ($sale['toppings']): ?>
                                                <small class="text-muted ms-2">(<?php echo h($sale['toppings']); ?>)</small>
                                                <?php endif; ?>
                                            </li>
                                        </ul>
                                    </td>
                                    <td>
                                        <span class="payment-badge payment-<?php echo $sale['payment_method']; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $sale['payment_method'])); ?>
                                        </span>
                                    </td>
                                    <td>Rs. <?php echo number_format($sale['total_amount'], 2); ?></td>
                                    <td><?php echo h($sale['sold_by']); ?></td>
                                    <td>
                                        <a href="print_order.php?order_id=<?php echo $sale['order_id']; ?>&noprint=1" class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html> 