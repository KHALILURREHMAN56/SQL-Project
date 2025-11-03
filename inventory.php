<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$pdo = getDBConnection();
$message = '';
$messageType = '';

// Handle inventory updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_material':
                try {
                    $stmt = $pdo->prepare("DELETE FROM raw_materials WHERE material_id = ?");
                    $stmt->execute([$_POST['material_id']]);
                    $message = "Material deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting material: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
                
            case 'update_stock':
                try {
                    $stmt = $pdo->prepare("UPDATE raw_materials SET quantity = ?, reorder_level = ? WHERE material_id = ?");
                    $stmt->execute([
                        $_POST['quantity'],
                        $_POST['reorder_level'],
                        $_POST['material_id']
                    ]);
                    $message = "Stock updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating stock: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'add_material':
                try {
                    $stmt = $pdo->prepare("INSERT INTO raw_materials (name, description, quantity, unit, reorder_level, supplier_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['description'],
                        $_POST['quantity'],
                        $_POST['unit'],
                        $_POST['reorder_level'],
                        $_POST['supplier_id']
                    ]);
                    $message = "Material added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding material: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch inventory items
try {
    $stmt = $pdo->query("
        SELECT 
            rm.*,
            s.name as supplier_name,
            CASE 
                WHEN rm.quantity <= rm.reorder_level THEN 'low'
                WHEN rm.quantity <= (rm.reorder_level * 1.5) THEN 'medium'
                ELSE 'good'
            END as stock_status
        FROM raw_materials rm
        LEFT JOIN suppliers s ON rm.supplier_id = s.supplier_id
        ORDER BY rm.name
    ");
    $inventory = $stmt->fetchAll();

    // Fetch suppliers for dropdown
    $stmt = $pdo->query("SELECT supplier_id, name FROM suppliers ORDER BY name");
    $suppliers = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = "Error fetching inventory data: " . $e->getMessage();
    $messageType = "error";
    $inventory = [];
    $suppliers = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Anees Ice Cream Parlor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <main>
        <div class="container">
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                <?php echo h($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Inventory Management</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMaterialModal">
                    <i class="fas fa-plus me-2"></i>Add New Material
                </button>
            </div>

            <!-- Filters -->
            <div class="filter-card mb-4">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">
                            <i class="fas fa-search me-1"></i>Search
                        </label>
                        <input type="text" class="form-control" name="search" value="<?php echo isset($_GET['search']) ? h($_GET['search']) : ''; ?>" placeholder="Search materials...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">
                            <i class="fas fa-sort me-1"></i>Sort By
                        </label>
                        <select class="form-select" name="sort">
                            <option value="name_asc" <?php echo isset($_GET['sort']) && $_GET['sort'] === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                            <option value="name_desc" <?php echo isset($_GET['sort']) && $_GET['sort'] === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                            <option value="stock_low" <?php echo isset($_GET['sort']) && $_GET['sort'] === 'stock_low' ? 'selected' : ''; ?>>Stock (Low to High)</option>
                            <option value="stock_high" <?php echo isset($_GET['sort']) && $_GET['sort'] === 'stock_high' ? 'selected' : ''; ?>>Stock (High to Low)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">
                            <i class="fas fa-filter me-1"></i>Stock Level
                        </label>
                        <select class="form-select" name="stock_level">
                            <option value="">All Levels</option>
                            <option value="low" <?php echo isset($_GET['stock_level']) && $_GET['stock_level'] === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                            <option value="normal" <?php echo isset($_GET['stock_level']) && $_GET['stock_level'] === 'normal' ? 'selected' : ''; ?>>Normal</option>
                            <option value="high" <?php echo isset($_GET['stock_level']) && $_GET['stock_level'] === 'high' ? 'selected' : ''; ?>>High Stock</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i>
                        </button>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <a href="inventory.php" class="btn btn-secondary w-100" title="Clear Filters">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Inventory Table -->
            <div class="inventory-card">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Description</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Reorder Level</th>
                                <th>Status</th>
                                <th>Supplier</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory as $item): ?>
                            <tr>
                                <td><?php echo h($item['name']); ?></td>
                                <td><?php echo h($item['description']); ?></td>
                                <td><?php echo number_format($item['quantity'], 2); ?></td>
                                <td><?php echo h($item['unit']); ?></td>
                                <td><?php echo number_format($item['reorder_level'], 2); ?></td>
                                <td>
                                    <span class="stock-status stock-<?php echo $item['stock_status']; ?>">
                                        <?php echo ucfirst($item['stock_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo h($item['supplier_name']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary me-1" onclick="editStock(<?php echo h(json_encode($item)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteMaterial(<?php echo $item['material_id']; ?>, '<?php echo h(addslashes($item['name'])); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($inventory)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No inventory items found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add Material Modal -->
        <div class="modal fade" id="addMaterialModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Material</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addMaterialForm" method="POST">
                            <input type="hidden" name="action" value="add_material">
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Initial Quantity</label>
                                <input type="number" class="form-control" name="quantity" step="0.01" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Unit</label>
                                <input type="text" class="form-control" name="unit" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reorder Level</label>
                                <input type="number" class="form-control" name="reorder_level" step="0.01" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Supplier</label>
                                <select class="form-select" name="supplier_id" required>
                                    <option value="">Select Supplier</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['supplier_id']; ?>">
                                        <?php echo h($supplier['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="text-end">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Add Material</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Update Stock Modal -->
        <div class="modal fade" id="updateStockModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Stock</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="updateStockForm" method="POST">
                            <input type="hidden" name="action" value="update_stock">
                            <input type="hidden" name="material_id" id="updateMaterialId">
                            <div class="mb-3">
                                <label class="form-label">Material Name</label>
                                <input type="text" class="form-control" id="updateMaterialName" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Current Quantity</label>
                                <input type="number" class="form-control" name="quantity" id="updateQuantity" step="0.01" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reorder Level</label>
                                <input type="number" class="form-control" name="reorder_level" id="updateReorderLevel" step="0.01" required>
                            </div>
                            <div class="text-end">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Update Stock</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Material Modal -->
        <div class="modal fade" id="deleteMaterialModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Material</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete <span id="deleteMaterialName"></span>?</p>
                        <p class="text-danger">This action cannot be undone.</p>
                        <form id="deleteMaterialForm" method="POST">
                            <input type="hidden" name="action" value="delete_material">
                            <input type="hidden" name="material_id" id="deleteMaterialId">
                            <div class="text-end">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Delete Material</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editStock(item) {
            document.getElementById('updateMaterialId').value = item.material_id;
            document.getElementById('updateMaterialName').value = item.name;
            document.getElementById('updateQuantity').value = item.quantity;
            document.getElementById('updateReorderLevel').value = item.reorder_level;
            
            new bootstrap.Modal(document.getElementById('updateStockModal')).show();
        }

        function deleteMaterial(materialId, materialName) {
            document.getElementById('deleteMaterialId').value = materialId;
            document.getElementById('deleteMaterialName').textContent = materialName;
            
            new bootstrap.Modal(document.getElementById('deleteMaterialModal')).show();
        }
    </script>
</body>
</html> 