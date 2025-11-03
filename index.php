<?php

require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Initialize variables with default values
$todaySales = 0;
$todayOrders = 0;
$activeStaff = 0;

// Fetch dashboard statistics
try {
    $pdo = getDBConnection();
    
    // Today's sales
    $stmt = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE DATE(order_date) = CURDATE()");
    $todaySales = $stmt->fetchColumn() ?: 0;

    // Today's orders count
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(order_date) = CURDATE()");
    $todayOrders = $stmt->fetchColumn();

    // Active staff count
    $stmt = $pdo->query("SELECT COUNT(*) FROM attendants WHERE is_active = 1");
    $activeStaff = $stmt->fetchColumn();

    // Fetch recent sales
    $stmt = $pdo->query("SELECT 
        o.order_date,
        m.name as product_name,
        oi.quantity,
        oi.price as unit_price,
        (oi.quantity * oi.price) as total_amount,
        o.payment_method 
    FROM orders o 
    JOIN order_items oi ON o.order_id = oi.order_id 
    JOIN menu m ON oi.item_id = m.item_id 
    ORDER BY o.order_date DESC 
    LIMIT 5");
    $recentSales = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error fetching dashboard stats: " . $e->getMessage());
    $recentSales = [];
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Anees Ice Cream Parlor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <?php if ($flashMessage = getFlashMessage()): ?>
        <div class="flash-message <?php echo $flashMessage['type']; ?>">
            <?php echo h($flashMessage['message']); ?>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="stats-card blue-card">
                    <div class="stats-title">TODAY'S SALES</div>
                    <div class="stats-value">Rs. <?php echo number_format($todaySales, 2); ?></div>
                    <div class="stats-subtitle">
                        <i class="fas fa-clock"></i> Updated just now
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card green-card">
                    <div class="stats-title">TODAY'S ORDERS</div>
                    <div class="stats-value"><?php echo number_format($todayOrders); ?></div>
                    <div class="stats-subtitle">
                        <i class="fas fa-check-circle"></i> Total Orders Today
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card red-card">
                    <div class="stats-title">ACTIVE EMPLOYEES</div>
                    <div class="stats-value"><?php echo $activeStaff; ?></div>
                    <div class="stats-subtitle">
                        <i class="fas fa-user-check"></i> On Duty
                    </div>
                </div>
            </div>
        </div>

        <div class="activities-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0">Recent Activities</h5>
                <a href="sales.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-chart-line me-2"></i>Show All Sales
                </a>
            </div>
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active sales-box" data-bs-toggle="tab" href="#sales">
                        <i class="fas fa-shopping-cart me-2"></i>Sales
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="sales">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>DATE</th>
                                    <th>PRODUCT</th>
                                    <th>QUANTITY</th>
                                    <th>RATE</th>
                                    <th>TOTAL</th>
                                    <th>PAYMENT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentSales)): ?>
                                    <?php foreach ($recentSales as $sale): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d H:i', strtotime($sale['order_date'])); ?></td>
                                        <td><?php echo h($sale['product_name']); ?></td>
                                        <td><?php echo number_format($sale['quantity']); ?></td>
                                        <td>Rs. <?php echo number_format($sale['unit_price'], 2); ?></td>
                                        <td>Rs. <?php echo number_format($sale['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="payment-badge payment-<?php echo str_replace('_', '-', strtolower($sale['payment_method'])); ?>">
                                                <?php echo str_replace('_', ' ', ucfirst($sale['payment_method'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No recent sales found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 