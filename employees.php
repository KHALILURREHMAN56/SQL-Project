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
                case 'add_employee':
                    // Validate input
                    if (empty($_POST['full_name']) || empty($_POST['phone'])) {
                        throw new Exception("Name and phone are required fields");
                    }

                    $stmt = $pdo->prepare("INSERT INTO attendants (full_name, phone, email, address, hire_date, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                    $stmt->execute([
                        $_POST['full_name'],
                        $_POST['phone'],
                        $_POST['email'] ?? null,
                        $_POST['address'] ?? null,
                        $_POST['hire_date'] ?? date('Y-m-d')
                    ]);
                    $message = "Employee added successfully!";
                    $messageType = "success";
                    break;

                case 'edit_employee':
                    if (!empty($_POST['attendant_id'])) {
                        // If only status is being updated
                        if (isset($_POST['is_active']) && !isset($_POST['full_name'])) {
                            $stmt = $pdo->prepare("UPDATE attendants SET is_active = ? WHERE attendant_id = ?");
                            $stmt->execute([
                                $_POST['is_active'],
                                $_POST['attendant_id']
                            ]);
                            $message = "Employee status updated successfully!";
                            $messageType = "success";
                            break;
                        }
                        
                        // Full employee edit
                        if (empty($_POST['full_name']) || empty($_POST['phone'])) {
                            throw new Exception("Invalid employee data");
                        }

                        $stmt = $pdo->prepare("UPDATE attendants SET full_name = ?, phone = ?, email = ?, salary = ?, address = ?, is_active = ? WHERE attendant_id = ?");
                        $stmt->execute([
                            $_POST['full_name'],
                            $_POST['phone'],
                            $_POST['email'] ?? null,
                            $_POST['salary'] ?? 0,
                            $_POST['address'] ?? null,
                            isset($_POST['is_active']) ? 1 : 0,
                            $_POST['attendant_id']
                        ]);
                        $message = "Employee updated successfully!";
                        $messageType = "success";
                    }
                    break;

                case 'delete_employee':
                    // Remove delete case as it's no longer needed
                    break;
            }
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Fetch all employees
try {
    $stmt = $pdo->query("
        SELECT 
            a.*,
            COALESCE(att.status, 'Not Marked') as today_status
        FROM attendants a
        LEFT JOIN attendance att ON a.attendant_id = att.attendant_id 
            AND att.date = CURRENT_DATE
        ORDER BY a.attendant_id
    ");
    $employees = $stmt->fetchAll();
} catch(PDOException $e) {
    $message = "Error fetching data: " . $e->getMessage();
    $messageType = "error";
    $employees = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - Anees Ice Cream Parlor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <main>
        <div class="content-wrapper">
            <div class="container py-4">
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                    <?php echo h($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Employee Management</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                        <i class="fas fa-plus me-2"></i>Add New Employee
                    </button>
                </div>

                <!-- Filters -->
                <div class="filter-card mb-4">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">
                                <i class="fas fa-search me-1"></i>Search
                            </label>
                            <input type="text" class="form-control" name="search" value="<?php echo isset($_GET['search']) ? h($_GET['search']) : ''; ?>" placeholder="Search employees...">
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
                        <div class="col-md-3">
                            <label class="form-label">
                                <i class="fas fa-calendar me-1"></i>Hire Date
                            </label>
                            <input type="date" class="form-control" name="hire_date" value="<?php echo isset($_GET['hire_date']) ? h($_GET['hire_date']) : ''; ?>">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i>
                            </button>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <a href="employees.php" class="btn btn-secondary w-100" title="Clear Filters">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Add Employee Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Add New Employee</h5>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_employee">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="full_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" name="phone" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Hire Date</label>
                                    <input type="date" class="form-control" name="hire_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="2"></textarea>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Add Employee
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Employee List -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="card-title mb-0">Employee List</h5>
                            <div>
                                <a href="attendance.php" class="btn btn-attendance">
                                    <i class="fas fa-calendar-check me-2"></i>Mark Attendance
                                </a>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>EMP ID</th>
                                        <th>FULL NAME</th>
                                        <th>PHONE</th>
                                        <th>EMAIL</th>
                                        <th>ADDRESS</th>
                                        <th>HIRE DATE</th>
                                        <th>STATUS</th>
                                        <th>ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employees as $employee): ?>
                                    <tr>
                                        <td><?php echo h($employee['attendant_id']); ?></td>
                                        <td><?php echo h($employee['full_name']); ?></td>
                                        <td><?php echo h($employee['phone']); ?></td>
                                        <td><?php echo h($employee['email'] ?? '-'); ?></td>
                                        <td><?php echo h($employee['address'] ?? '-'); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($employee['hire_date'])); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $employee['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $employee['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-icon btn-edit" 
                                                    onclick="editEmployee(<?php echo h(json_encode($employee)); ?>)">
                                                <i class="fas fa-pencil-alt"></i>
                                            </button>
                                            <button type="button" 
                                                class="btn btn-icon <?php echo $employee['is_active'] ? 'btn-status-active' : 'btn-status-inactive'; ?>"
                                                onclick="toggleStatus(<?php echo $employee['attendant_id']; ?>, <?php echo $employee['is_active']; ?>)">
                                                <i class="fas <?php echo $employee['is_active'] ? 'fa-user-check' : 'fa-user-slash'; ?>"></i>
                                            </button>
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
    </main>

    <!-- Edit Employee Modal -->
    <div class="modal fade" id="editEmployeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_employee">
                        <input type="hidden" name="attendant_id" id="edit_attendant_id">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name" id="edit_full_name" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
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
                        <div class="mb-3">
                            <label class="form-label">Hire Date</label>
                            <input type="date" class="form-control" id="edit_hire_date" disabled>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_active" id="edit_is_active">
                                <label class="form-check-label">Active Employee</label>
                            </div>
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
    <?php include 'footer.php'; ?>

    <!-- Delete Employee Form (Hidden) -->
    <form id="deleteEmployeeForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_employee">
        <input type="hidden" name="attendant_id" id="delete_attendant_id">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editEmployee(employee) {
            document.getElementById('edit_attendant_id').value = employee.attendant_id;
            document.getElementById('edit_full_name').value = employee.full_name;
            document.getElementById('edit_phone').value = employee.phone;
            document.getElementById('edit_email').value = employee.email || '';
            document.getElementById('edit_address').value = employee.address || '';
            document.getElementById('edit_hire_date').value = employee.hire_date;
            document.getElementById('edit_is_active').checked = employee.is_active == 1;
            
            new bootstrap.Modal(document.getElementById('editEmployeeModal')).show();
        }

        function toggleStatus(id, currentStatus) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="edit_employee">
                <input type="hidden" name="attendant_id" value="${id}">
                <input type="hidden" name="is_active" value="${currentStatus ? '0' : '1'}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    </script>

</body>
</html> 