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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $stmt = $pdo->prepare("INSERT INTO menu (name, description, price_pkr, category, is_available) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['description'],
                        $_POST['price'],
                        $_POST['category'],
                        isset($_POST['is_available']) ? 1 : 0
                    ]);
                    setFlashMessage("Menu item added successfully!", "success");
                    header("Location: menu.php");
                    exit();
                } catch (PDOException $e) {
                    $message = "Error adding menu item: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'edit':
                try {
                    $stmt = $pdo->prepare("UPDATE menu SET name = ?, description = ?, price_pkr = ?, category = ?, is_available = ? WHERE item_id = ?");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['description'],
                        $_POST['price'],
                        $_POST['category'],
                        isset($_POST['is_available']) ? 1 : 0,
                        $_POST['item_id']
                    ]);
                    setFlashMessage("Menu item updated successfully!", "success");
                    header("Location: menu.php");
                    exit();
                } catch (PDOException $e) {
                    $message = "Error updating menu item: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'delete':
                try {
                    $stmt = $pdo->prepare("DELETE FROM menu WHERE item_id = ?");
                    $stmt->execute([$_POST['item_id']]);
                    setFlashMessage("Menu item deleted successfully!", "success");
                    header("Location: menu.php");
                    exit();
                } catch (PDOException $e) {
                    $message = "Error deleting menu item: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch all menu items
try {
    $stmt = $pdo->query("SELECT * FROM menu ORDER BY category, name");
    $menuItems = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = "Error fetching menu items: " . $e->getMessage();
    $messageType = "error";
    $menuItems = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management - Anees Ice Cream Parlor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <div class="container py-4">
        <!-- Flash Messages -->
        <?php if ($flashMessage = getFlashMessage()): ?>
        <div class="alert alert-<?php echo $flashMessage['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
            <?php echo h($flashMessage['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Menu Management</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                <i class="fas fa-plus me-2"></i>Add New Item
            </button>
        </div>

        <!-- Filters -->
        <div class="filter-card mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">
                        <i class="fas fa-search me-1"></i>Search
                    </label>
                    <input type="text" class="form-control" name="search" value="<?php echo isset($_GET['search']) ? h($_GET['search']) : ''; ?>" placeholder="Search items...">
                </div>
                <div class="col-md-3">
                    <label class="fo    rm-label">
                        <i class="fas fa-tag me-1"></i>Category
                    </label>
                    <select class="form-select" name="category">
                        <option value="">All Categories</option>
                        <option value="ice_cream" <?php echo isset($_GET['category']) && $_GET['category'] === 'ice_cream' ? 'selected' : ''; ?>>Ice Cream</option>
                        <option value="gelato" <?php echo isset($_GET['category']) && $_GET['category'] === 'gelato' ? 'selected' : ''; ?>>Gelato</option>
                        <option value="sorbet" <?php echo isset($_GET['category']) && $_GET['category'] === 'sorbet' ? 'selected' : ''; ?>>Sorbet</option>
                        <option value="sundae" <?php echo isset($_GET['category']) && $_GET['category'] === 'sundae' ? 'selected' : ''; ?>>Sundae</option>
                        <option value="milkshake" <?php echo isset($_GET['category']) && $_GET['category'] === 'milkshake' ? 'selected' : ''; ?>>Milkshake</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">
                        <i class="fas fa-check-circle me-1"></i>Availability
                    </label>
                    <select class="form-select" name="availability">
                        <option value="">All Status</option>
                        <option value="1" <?php echo isset($_GET['availability']) && $_GET['availability'] === '1' ? 'selected' : ''; ?>>Available</option>
                        <option value="0" <?php echo isset($_GET['availability']) && $_GET['availability'] === '0' ? 'selected' : ''; ?>>Unavailable</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i>
                    </button>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <a href="menu.php" class="btn btn-secondary w-100" title="Clear Filters">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Menu Items Grid -->
        <div class="row">
            <!-- Menu Stats Card -->
            <div class="col-12 mb-4">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3>Menu Items</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($menuItems as $item): ?>
                                <tr>
                                    <td><?php echo h($item['name']); ?></td>
                                    <td><span class="category-badge"><?php echo h(str_replace('_', ' ', ucfirst($item['category']))); ?></span></td>
                                    <td><?php echo h($item['description']); ?></td>
                                    <td class="price"><?php echo formatPrice($item['price_pkr']); ?></td>
                                    <td>
                                        <?php if ($item['is_available']): ?>
                                            <span class="badge bg-success">Available</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Unavailable</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-primary" onclick="editItem(<?php echo h(json_encode($item)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['item_id']; ?>, '<?php echo h($item['name']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Menu Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addItemForm" method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Price (PKR)</label>
                            <input type="number" class="form-control" name="price" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category" required>
                                <option value="ice_cream">Ice Cream</option>
                                <option value="gelato">Gelato</option>
                                <option value="sorbet">Sorbet</option>
                                <option value="sundae">Sundae</option>
                                <option value="milkshake">Milkshake</option>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="is_available" id="addIsAvailable" checked>
                            <label class="form-check-label" for="addIsAvailable">Available</label>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Item</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div class="modal fade" id="editItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Menu Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editItemForm" method="POST">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="item_id" id="editItemId">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" id="editName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="editDescription" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Price (PKR)</label>
                            <input type="number" class="form-control" name="price" id="editPrice" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category" id="editCategory" required>
                                <option value="ice_cream">Ice Cream</option>
                                <option value="gelato">Gelato</option>
                                <option value="sorbet">Sorbet</option>
                                <option value="sundae">Sundae</option>
                                <option value="milkshake">Milkshake</option>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="is_available" id="editIsAvailable">
                            <label class="form-check-label" for="editIsAvailable">Available</label>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Menu Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete "<span id="deleteItemName"></span>"?</p>
                    <form id="deleteItemForm" method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="item_id" id="deleteItemId">
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>  


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editItem(item) {
            document.getElementById('editItemId').value = item.item_id;
            document.getElementById('editName').value = item.name;
            document.getElementById('editDescription').value = item.description;
            document.getElementById('editPrice').value = item.price_pkr;
            document.getElementById('editCategory').value = item.category;
            document.getElementById('editIsAvailable').checked = item.is_available == 1;
            
            new bootstrap.Modal(document.getElementById('editItemModal')).show();
        }

        function deleteItem(itemId, itemName) {
            document.getElementById('deleteItemId').value = itemId;
            document.getElementById('deleteItemName').textContent = itemName;
            
            new bootstrap.Modal(document.getElementById('deleteItemModal')).show();
        }
    </script>
</body>
</html> 