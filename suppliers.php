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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_supplier':
                    // Validate input
                    if (empty($_POST['name']) || empty($_POST['contact_person']) || empty($_POST['phone'])) {
                        throw new Exception("Company name, contact person and phone are required fields");
                    }

                    $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['contact_person'],
                        $_POST['phone'],
                        $_POST['email'] ?? null,
                        $_POST['address'] ?? null
                    ]);
                    $message = "Supplier added successfully!";
                    $messageType = "success";
                    break;

                case 'edit_supplier':
                    if (!empty($_POST['supplier_id'])) {
                        // Full supplier edit
                        if (empty($_POST['name']) || empty($_POST['contact_person']) || empty($_POST['phone'])) {
                            throw new Exception("Invalid supplier data");
                        }

                        $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, contact_person = ?, phone = ?, email = ?, address = ? WHERE supplier_id = ?");
                        $stmt->execute([
                            $_POST['name'],
                            $_POST['contact_person'],
                            $_POST['phone'],
                            $_POST['email'] ?? null,
                            $_POST['address'] ?? null,
                            $_POST['supplier_id']
                        ]);
                        $message = "Supplier updated successfully!";
                        $messageType = "success";
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Fetch all suppliers
try {
    $stmt = $pdo->query("SELECT * FROM suppliers ORDER BY supplier_id ASC");
    $suppliers = $stmt->fetchAll();
} catch(PDOException $e) {
    $message = "Error fetching data: " . $e->getMessage();
    $messageType = "error";
    $suppliers = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Management - Anees Ice Cream Parlor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #30D5C8;
            --accent-color: #FF4B4B;
            --light-bg: #f5f5f5;
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-active {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #2e7d32;
        }

        .status-inactive {
            background-color: #ffe0e0;
            color: #cc0000;
            border: 1px solid #cc0000;
        }

        .btn-icon {
            width: 35px;
            height: 35px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
            margin-right: 0.5rem;
        }

        .btn-edit {
            background-color: #3498db;
            color: white;
        }

        .btn-edit:hover {
            background-color: #2980b9;
            color: white;
        }

        .btn-status-active {
            background-color: #2ecc71;
            color: white;
            border: none;
        }

        .btn-status-active:hover {
            background-color: #27ae60;
            color: white;
        }

        .btn-status-inactive {
            background-color: #e74c3c;
            color: white;
            border: none;
        }

        .btn-status-inactive:hover {
            background-color: #c0392b;
            color: white;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: #2CC0B4;
            border-color: #2CC0B4;
        }

        .btn-danger {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }

        .btn-danger:hover {
            background-color: #ff3333;
            border-color: #ff3333;
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

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Suppliers Management</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                <i class="fas fa-plus me-2"></i>Add New Supplier
            </button>
        </div>

        <!-- Filters -->
        <div class="filter-card mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">
                        <i class="fas fa-search me-1"></i>Search
                    </label>
                    <input type="text" class="form-control" name="search" value="<?php echo isset($_GET['search']) ? h($_GET['search']) : ''; ?>" placeholder="Search suppliers...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">
                        <i class="fas fa-check-circle me-1"></i>Status
                    </label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="1" <?php echo isset($_GET['status']) && $_GET['status'] === '1' ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo isset($_GET['status']) && $_GET['status'] === '0' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i>
                    </button>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <a href="suppliers.php" class="btn btn-secondary w-100" title="Clear Filters">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Add Supplier Form -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title mb-4">Add New Supplier</h5>
                <form method="POST">
                    <input type="hidden" name="action" value="add_supplier">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Person <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="contact_person" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="phone" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="2"></textarea>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add Supplier
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Supplier List -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-4">Supplier List</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>SUPPLIER ID</th>
                                <th>COMPANY NAME</th>
                                <th>CONTACT PERSON</th>
                                <th>PHONE</th>
                                <th>EMAIL</th>
                                <th>ADDRESS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppliers as $supplier): ?>
                            <tr>
                                <td><?php echo h($supplier['supplier_id']); ?></td>
                                <td><?php echo h($supplier['name']); ?></td>
                                <td><?php echo h($supplier['contact_person']); ?></td>
                                <td><?php echo h($supplier['phone']); ?></td>
                                <td><?php echo h($supplier['email'] ?? '-'); ?></td>
                                <td><?php echo h($supplier['address'] ?? '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Supplier Modal -->
    <div class="modal fade" id="editSupplierModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_supplier">
                        <input type="hidden" name="supplier_id" id="edit_supplier_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" id="edit_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Person <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="contact_person" id="edit_contact_person" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" name="phone" id="edit_phone" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="edit_email">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" id="edit_address" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editSupplier(supplier) {
            document.getElementById('edit_supplier_id').value = supplier.supplier_id;
            document.getElementById('edit_name').value = supplier.name;
            document.getElementById('edit_contact_person').value = supplier.contact_person;
            document.getElementById('edit_phone').value = supplier.phone;
            document.getElementById('edit_email').value = supplier.email || '';
            document.getElementById('edit_address').value = supplier.address || '';
            
            new bootstrap.Modal(document.getElementById('editSupplierModal')).show();
        }
    </script>
</body>
</html> 