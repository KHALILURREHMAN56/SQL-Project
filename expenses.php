<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$pdo = getDBConnection();
$message = '';
$messageType = '';
$editExpense = null;

// Initialize filters
$filters = [
    'start_date' => $_GET['start_date'] ?? date('Y-m-01'), // First day of current month
    'end_date' => $_GET['end_date'] ?? date('Y-m-t'), // Last day of current month
    'category' => $_GET['category'] ?? '',
    'search' => $_GET['search'] ?? '',
    'min_amount' => $_GET['min_amount'] ?? '',
    'max_amount' => $_GET['max_amount'] ?? ''
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_expense':
                    if (empty($_POST['amount']) || empty($_POST['category']) || empty($_POST['expense_date'])) {
                        throw new Exception("Amount, category and date are required fields");
                    }

                    $stmt = $pdo->prepare("INSERT INTO expenses (expense_date, category, description, amount) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['expense_date'],
                        $_POST['category'],
                        $_POST['description'] ?? null,
                        $_POST['amount']
                    ]);
                    $message = "Expense added successfully!";
                    $messageType = "success";
                    break;

                case 'edit_expense':
                    if (empty($_POST['amount']) || empty($_POST['category']) || empty($_POST['expense_date'])) {
                        throw new Exception("Amount, category and date are required fields");
                    }

                    $stmt = $pdo->prepare("UPDATE expenses SET expense_date = ?, category = ?, description = ?, amount = ? WHERE expense_id = ?");
                    $stmt->execute([
                        $_POST['expense_date'],
                        $_POST['category'],
                        $_POST['description'] ?? null,
                        $_POST['amount'],
                        $_POST['expense_id']
                    ]);
                    $message = "Expense updated successfully!";
                    $messageType = "success";
                    break;

                case 'delete_expense':
                    if (!empty($_POST['expense_id'])) {
                        $stmt = $pdo->prepare("DELETE FROM expenses WHERE expense_id = ?");
                        $stmt->execute([$_POST['expense_id']]);
                        $message = "Expense deleted successfully!";
                        $messageType = "success";
                    }
                    break;

                case 'get_expense':
                    if (!empty($_POST['expense_id'])) {
                        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE expense_id = ?");
                        $stmt->execute([$_POST['expense_id']]);
                        $editExpense = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Build the query with filters
$query = "SELECT * FROM expenses WHERE 1=1";
$params = [];

if ($filters['start_date']) {
    $query .= " AND expense_date >= ?";
    $params[] = $filters['start_date'];
}
if ($filters['end_date']) {
    $query .= " AND expense_date <= ?";
    $params[] = $filters['end_date'];
}
if ($filters['category']) {
    $query .= " AND category = ?";
    $params[] = $filters['category'];
}
if ($filters['search']) {
    $query .= " AND (description LIKE ? OR category LIKE ?)";
    $params[] = "%{$filters['search']}%";
    $params[] = "%{$filters['search']}%";
}
if ($filters['min_amount']) {
    $query .= " AND amount >= ?";
    $params[] = $filters['min_amount'];
}
if ($filters['max_amount']) {
    $query .= " AND amount <= ?";
    $params[] = $filters['max_amount'];
}

$query .= " ORDER BY expense_date DESC";

// Fetch filtered expenses
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $message = "Error fetching data: " . $e->getMessage();
    $messageType = "error";
    $expenses = [];
}

// Calculate statistics
$total_expenses = array_reduce($expenses, function($carry, $expense) {
    return $carry + $expense['amount'];
}, 0);

$category_totals = [];
foreach ($expenses as $expense) {
    $category = $expense['category'];
    if (!isset($category_totals[$category])) {
        $category_totals[$category] = 0;
    }
    $category_totals[$category] += $expense['amount'];
}

$categories = ['electricity', 'salary', 'maintenance', 'rent', 'other'];

// Calculate percentage for each category
$category_percentages = [];
foreach ($categories as $category) {
    $amount = $category_totals[$category] ?? 0;
    $category_percentages[$category] = $total_expenses > 0 ? ($amount / $total_expenses) * 100 : 0;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses Management - Anees Ice Cream Parlor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <main>
        <div class="container py-4">
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4 mb-4">
                    <div class="card expenses-stats-card h-100">
                        <div class="card-body">
                            <h6 class="card-title">
                                <i class="fas fa-money-bill-wave me-2"></i>
                                Total Expenses
                            </h6>
                            <h3>Rs. <?php echo number_format($total_expenses, 2); ?></h3>
                            <small>For selected period</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="category-breakdown h-100">
                        <h6 class="mb-3">Category Breakdown</h6>
                        <div class="row">
                            <?php 
                            $category_icons = [
                                'electricity' => 'fa-bolt',
                                'salary' => 'fa-users',
                                'maintenance' => 'fa-wrench',
                                'rent' => 'fa-building',
                                'other' => 'fa-box'
                            ];
                            
                            foreach ($categories as $category): 
                                $amount = $category_totals[$category] ?? 0;
                                $percentage = $category_percentages[$category];
                            ?>
                            <div class="col-md-4 col-sm-6 mb-3">
                                <div class="category-card category-<?php echo $category; ?>">
                                    <div class="category-icon">
                                        <i class="fas <?php echo $category_icons[$category]; ?>"></i>
                                    </div>
                                    <h6 class="text-uppercase"><?php echo ucfirst($category); ?></h6>
                                    <div class="category-amount">
                                        Rs. <?php echo number_format($amount, 2); ?>
                                    </div>
                                    <div class="category-percentage">
                                        <?php echo number_format($percentage, 1); ?>% of total
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?php echo $percentage; ?>%"
                                             aria-valuenow="<?php echo $percentage; ?>"
                                             aria-valuemin="0"
                                             aria-valuemax="100"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-section">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" value="<?php echo $filters['start_date']; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" value="<?php echo $filters['end_date']; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category; ?>" <?php echo $filters['category'] === $category ? 'selected' : ''; ?>>
                                <?php echo ucfirst($category); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Min Amount</label>
                        <input type="number" class="form-control" name="min_amount" value="<?php echo $filters['min_amount']; ?>" placeholder="Min">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Max Amount</label>
                        <input type="number" class="form-control" name="max_amount" value="<?php echo $filters['max_amount']; ?>" placeholder="Max">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>Apply Filters
                        </button>
                        <a href="expenses.php" class="btn btn-secondary">
                            <i class="fas fa-sync-alt me-2"></i>Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Expenses Table -->
            <div class="expenses-table">
                <div class="table-responsive">
                    <table class="table table-hover expenses-table mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($expense['expense_date'])); ?></td>
                                <td>
                                    <span class="category-badge category-<?php echo $expense['category']; ?>">
                                        <i class="fas <?php echo $category_icons[$expense['category']]; ?> me-1"></i>
                                        <?php echo ucfirst($expense['category']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($expense['description'] ?? '-'); ?></td>
                                <td>Rs. <?php echo number_format($expense['amount'], 2); ?></td>
                                <td>
                                    <button type="button" class="btn btn-action btn-edit" 
                                            onclick="editExpense(<?php echo htmlspecialchars(json_encode($expense)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-action btn-delete" 
                                            onclick="deleteExpense(<?php echo $expense['expense_id']; ?>)">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($expenses)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <i class="fas fa-receipt fa-2x mb-3 text-muted d-block"></i>
                                    No expenses found for the selected filters.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add/Edit Expense Form -->
            <div class="card mb-4 expense-form">
                <div class="card-body">
                    <h5 class="card-title mb-4"><?php echo $editExpense ? 'Edit Expense' : 'Add New Expense'; ?></h5>
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="<?php echo $editExpense ? 'edit_expense' : 'add_expense'; ?>">
                        <?php if ($editExpense): ?>
                            <input type="hidden" name="expense_id" value="<?php echo $editExpense['expense_id']; ?>">
                        <?php endif; ?>
                        
                        <div class="col-md-6">
                            <label class="form-label">Amount (Rs.) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="amount" required 
                                   value="<?php echo $editExpense ? $editExpense['amount'] : ''; ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category; ?>" 
                                    <?php echo ($editExpense && $editExpense['category'] === $category) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($category); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="expense_date" required
                                   value="<?php echo $editExpense ? $editExpense['expense_date'] : date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Description (Optional)</label>
                            <textarea class="form-control" name="description" rows="2"><?php echo $editExpense ? $editExpense['description'] : ''; ?></textarea>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas <?php echo $editExpense ? 'fa-save' : 'fa-plus'; ?> me-2"></i>
                                <?php echo $editExpense ? 'Update Expense' : 'Add Expense'; ?>
                            </button>
                            <?php if ($editExpense): ?>
                                <a href="expenses.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 