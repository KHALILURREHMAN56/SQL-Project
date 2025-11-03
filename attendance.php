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
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$statusFilter = $_GET['status_filter'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Date range handling
$dateRange = $_GET['date_range'] ?? 'today';
$customStartDate = $_GET['start_date'] ?? '';
$customEndDate = $_GET['end_date'] ?? '';

// Calculate dates based on selection
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$lastWeekStart = date('Y-m-d', strtotime('-6 days'));
$thisMonthStart = date('Y-m-01');
$thisMonthEnd = date('Y-m-t');
$lastMonthStart = date('Y-m-01', strtotime('-1 month'));
$lastMonthEnd = date('Y-m-t', strtotime('-1 month'));

switch ($dateRange) {
    case 'today':
        $startDate = $today;
        $endDate = $today;
        break;
    case 'yesterday':
        $startDate = $yesterday;
        $endDate = $yesterday;
        break;
    case 'last7days':
        $startDate = $lastWeekStart;
        $endDate = $today;
        break;
    case 'thismonth':
        $startDate = $thisMonthStart;
        $endDate = $thisMonthEnd;
        break;
    case 'lastmonth':
        $startDate = $lastMonthStart;
        $endDate = $lastMonthEnd;
        break;
    case 'custom':
        $startDate = $customStartDate ?: $today;
        $endDate = $customEndDate ?: $today;
        break;
    default:
        $startDate = $today;
        $endDate = $today;
}

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_attendance']) && isset($_POST['status']) && is_array($_POST['status'])) {
            $hasError = false;
            $errorMessages = [];

            foreach ($_POST['status'] as $attendantId => $status) {
                $date = $_POST['date'] ?? date('Y-m-d');

                // Check if attendance already exists
                $checkStmt = $pdo->prepare("SELECT attendant_id FROM attendance WHERE attendant_id = ? AND date = ?");
                $checkStmt->execute([$attendantId, $date]);
                $existing = $checkStmt->fetch();

                if ($existing) {
                    // Update existing record
                    $stmt = $pdo->prepare("UPDATE attendance SET status = ? WHERE attendant_id = ? AND date = ?");
                    $stmt->execute([$status, $attendantId, $date]);
                } else {
                    // Insert new record
                    $stmt = $pdo->prepare("INSERT INTO attendance (attendant_id, date, status) VALUES (?, ?, ?)");
                    $stmt->execute([$attendantId, $date, $status]);
                }
            }

            if ($hasError) {
                $message = "Errors occurred: " . implode(", ", $errorMessages);
                $messageType = "error";
            } else {
                $message = "Attendance saved successfully!";
                $messageType = "success";
            }
        } elseif (isset($_POST['all_present'])) {
            $date = $_POST['date'] ?? date('Y-m-d');
            $stmt = $pdo->prepare("SELECT attendant_id FROM attendants WHERE is_active = 1");
            $stmt->execute();
            $attendants = $stmt->fetchAll();

            foreach ($attendants as $attendant) {
                $checkStmt = $pdo->prepare("SELECT attendant_id FROM attendance WHERE attendant_id = ? AND date = ?");
                $checkStmt->execute([$attendant['attendant_id'], $date]);
                $existing = $checkStmt->fetch();

                if ($existing) {
                    $stmt = $pdo->prepare("UPDATE attendance SET status = 'present' WHERE attendant_id = ? AND date = ?");
                    $stmt->execute([$attendant['attendant_id'], $date]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO attendance (attendant_id, date, status) VALUES (?, ?, 'present')");
                    $stmt->execute([$attendant['attendant_id'], $date]);
                }
            }
            $message = "All employees marked as present!";
            $messageType = "success";
        } elseif (isset($_POST['all_absent'])) {
            $date = $_POST['date'] ?? date('Y-m-d');
            $stmt = $pdo->prepare("SELECT attendant_id FROM attendants WHERE is_active = 1");
            $stmt->execute();
            $attendants = $stmt->fetchAll();

            foreach ($attendants as $attendant) {
                $checkStmt = $pdo->prepare("SELECT attendant_id FROM attendance WHERE attendant_id = ? AND date = ?");
                $checkStmt->execute([$attendant['attendant_id'], $date]);
                $existing = $checkStmt->fetch();

                if ($existing) {
                    $stmt = $pdo->prepare("UPDATE attendance SET status = 'absent' WHERE attendant_id = ? AND date = ?");
                    $stmt->execute([$attendant['attendant_id'], $date]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO attendance (attendant_id, date, status) VALUES (?, ?, 'absent')");
                    $stmt->execute([$attendant['attendant_id'], $date]);
                }
            }
            $message = "All employees marked as absent!";
            $messageType = "success";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Add attendance statistics
try {
    // Get attendance summary for the date range
    $statsSql = "SELECT 
        COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
        COUNT(CASE WHEN status = 'leave' THEN 1 END) as leave_count,
        COUNT(*) as total_count
        FROM attendance 
        WHERE date BETWEEN ? AND ?";
    $statsStmt = $pdo->prepare($statsSql);
    $statsStmt->execute([$startDate, $endDate]);
    $stats = $statsStmt->fetch();

    // Handle CSV Export
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="attendance_' . $startDate . '_to_' . $endDate . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, ['Name', 'Employee ID', 'Date', 'Status']);
        
        // Export the same data we're displaying
        $stmt = $pdo->prepare("
            SELECT 
                a.full_name,
                a.attendant_id,
                att.date,
                COALESCE(att.status, 'present') as status
            FROM attendants a
            LEFT JOIN (
                SELECT * FROM attendance 
                WHERE date BETWEEN ? AND ?
            ) att ON a.attendant_id = att.attendant_id
            WHERE a.is_active = 1
        ");
        $stmt->execute([$startDate, $endDate]);
        $employees = $stmt->fetchAll();
        
        foreach ($employees as $employee) {
            fputcsv($output, [
                $employee['full_name'],
                $employee['attendant_id'],
                $employee['date'],
                $employee['status']
            ]);
        }
        
        fclose($output);
        exit();
    }

    // Initialize stats if null
    if (!$stats) {
        $stats = [
            'present_count' => 0,
            'absent_count' => 0,
            'leave_count' => 0,
            'total_count' => 0
        ];
    }

    // Calculate percentages
    $total = max(1, $stats['total_count']);
    $presentPercent = round(($stats['present_count'] / $total) * 100);
    $absentPercent = round(($stats['absent_count'] / $total) * 100);
    $leavePercent = round(($stats['leave_count'] / $total) * 100);
} catch (PDOException $e) {
    $message = "Error fetching statistics: " . $e->getMessage();
    $messageType = "error";
}

// Fetch all active employees with their attendance for the date range
try {
    $params = [$startDate, $endDate];
    $searchClause = '';
    $statusClause = '';
    
    if (!empty($searchQuery)) {
        $searchClause = " AND (a.full_name LIKE ? OR a.attendant_id LIKE ?)";
        $params[] = "%$searchQuery%";
        $params[] = "%$searchQuery%";
    }
    
    if ($statusFilter !== 'all') {
        $statusClause = " AND att.status = ?";
        $params[] = $statusFilter;
    }

    $stmt = $pdo->prepare("
        SELECT 
            a.full_name,
            a.attendant_id,
            att.date,
            COALESCE(att.status, 'present') as status
        FROM attendants a
        LEFT JOIN (
            SELECT attendant_id, date, status 
            FROM attendance 
            WHERE date BETWEEN ? AND ?
        ) att ON a.attendant_id = att.attendant_id
        WHERE a.is_active = 1
        " . $searchClause . $statusClause . "
        ORDER BY a.attendant_id ASC, att.date ASC
    ");

    $stmt->execute($params);
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
    <title>Attendance Management - Anees Ice Cream Parlor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-md-8">
                <div class="stats-card">
                    <h4>Attendance Overview (<?php echo $startDate; ?> to <?php echo $endDate; ?>)</h4>
                    <div class="chart-container">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <h4>Statistics</h4>
                    <div class="list-group">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Present
                            <span class="badge bg-success rounded-pill"><?php echo $stats['present_count']; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Absent
                            <span class="badge bg-danger rounded-pill"><?php echo $stats['absent_count']; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Leave
                            <span class="badge bg-warning rounded-pill"><?php echo $stats['leave_count']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Date Range</label>
                    <div class="date-range-container">
                        <select name="date_range" class="form-select mb-2" onchange="toggleCustomDateRange(this.value)">
                            <option value="today" <?php echo $dateRange === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="yesterday" <?php echo $dateRange === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                            <option value="last7days" <?php echo $dateRange === 'last7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="thismonth" <?php echo $dateRange === 'thismonth' ? 'selected' : ''; ?>>This Month</option>
                            <option value="lastmonth" <?php echo $dateRange === 'lastmonth' ? 'selected' : ''; ?>>Last Month</option>
                            <option value="custom" <?php echo $dateRange === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                        <div id="customDateRange" class="custom-date-inputs <?php echo $dateRange === 'custom' ? '' : 'd-none'; ?>">
                            <div class="d-flex gap-2">
                                <div class="input-group">
                                    <span class="input-group-text">From</span>
                                    <input type="date" name="start_date" class="form-control" value="<?php echo $customStartDate; ?>">
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text">To</span>
                                    <input type="date" name="end_date" class="form-control" value="<?php echo $customEndDate; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status Filter</label>
                    <select name="status_filter" class="form-select">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="present" <?php echo $statusFilter === 'present' ? 'selected' : ''; ?>>Present</option>
                        <option value="absent" <?php echo $statusFilter === 'absent' ? 'selected' : ''; ?>>Absent</option>
                        <option value="leave" <?php echo $statusFilter === 'leave' ? 'selected' : ''; ?>>Leave</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Search Employee</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Name or ID..." value="<?php echo h($searchQuery); ?>">
                        <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-2"></i>Apply
                    </button>
                    <a href="attendance.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Print Button -->
        <div class="text-end mb-3">
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print Report
            </button>
        </div>

        <!-- Active Filters Display -->
        <?php if ($statusFilter !== 'all' || !empty($searchQuery)): ?>
        <div class="active-filters mb-3">
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted">Active Filters:</span>
                <?php if ($statusFilter !== 'all'): ?>
                <span class="badge bg-info">
                    Status: <?php echo ucfirst($statusFilter); ?>
                    <a href="<?php echo removeQueryParam('status_filter'); ?>" class="text-white text-decoration-none ms-2">&times;</a>
                </span>
                <?php endif; ?>
                <?php if (!empty($searchQuery)): ?>
                <span class="badge bg-info">
                    Search: <?php echo h($searchQuery); ?>
                    <a href="<?php echo removeQueryParam('search'); ?>" class="text-white text-decoration-none ms-2">&times;</a>
                </span>
                <?php endif; ?>
                <a href="attendance.php" class="btn btn-sm btn-outline-secondary ms-auto">Clear All Filters</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- No Results Message -->
        <?php if (empty($employees)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>No employees found matching the current filters.
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title mb-0">Mark Attendance</h4>
                        <div>
                            <button type="submit" name="all_present" class="btn btn-success me-2">
                                <i class="fas fa-check-circle me-2"></i>All Present
                            </button>
                            <button type="submit" name="all_absent" class="btn btn-danger">
                                <i class="fas fa-times-circle me-2"></i>All Absent
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Employee ID</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($employees)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No records found</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($employees as $employee): ?>
                                    <tr>
                                        <td><?php echo h($employee['full_name']); ?></td>
                                        <td><?php echo h($employee['attendant_id']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($employee['date'])); ?></td>
                                        <td>
                                            <select name="status[<?php echo $employee['attendant_id']; ?>]" 
                                                    class="form-select status-select">
                                                <option value="present" <?php echo $employee['status'] === 'present' ? 'selected' : ''; ?>>Present</option>
                                                <option value="absent" <?php echo $employee['status'] === 'absent' ? 'selected' : ''; ?>>Absent</option>
                                                <option value="leave" <?php echo $employee['status'] === 'leave' ? 'selected' : ''; ?>>Leave</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" name="save_attendance" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>Save Attendance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateTimeInputs(selectElement) {
            const row = selectElement.closest('tr');
            const timeInInput = row.querySelector('input[name^="time_in"]');
            const timeOutInput = row.querySelector('input[name^="time_out"]');
            const status = selectElement.value;
            
            if (status === 'absent' || status === 'leave') {
                timeInInput.disabled = true;
                timeOutInput.disabled = true;
                timeInInput.value = '';
                timeOutInput.value = '';
            } else if (status === 'half_leave') {
                timeInInput.disabled = false;
                timeOutInput.disabled = false;
                timeInInput.value = '08:00';
                timeOutInput.value = '13:00';
            } else {
                timeInInput.disabled = false;
                timeOutInput.disabled = false;
                
                // For present status, set default times
                if (status === 'present') {
                    if (!timeInInput.value) {
                        timeInInput.value = '08:00';
                    }
                    if (!timeOutInput.value) {
                        timeOutInput.value = '22:00';
                    }
                }
            }

            validateTimeRange(row);
        }

        function validateTimeRange(row) {
            const timeInInput = row.querySelector('input[name^="time_in"]');
            const timeOutInput = row.querySelector('input[name^="time_out"]');
            
            if (timeInInput.value && timeOutInput.value) {
                const timeInDate = new Date('1970-01-01T' + timeInInput.value);
                const timeOutDate = new Date('1970-01-01T' + timeOutInput.value);
                const startTime = new Date('1970-01-01T08:00:00');
                const endTime = new Date('1970-01-01T22:00:00');
                
                if (timeInDate < startTime) {
                    timeInInput.setCustomValidity('Check-in time cannot be before 8 AM');
                    timeInInput.reportValidity();
                    return;
                }
                
                if (timeOutDate > endTime) {
                    timeOutInput.setCustomValidity('Check-out time cannot be after 10 PM');
                    timeOutInput.reportValidity();
                    return;
                }
                
                if (timeInDate >= timeOutDate) {
                    timeOutInput.setCustomValidity('Check-out time must be after check-in time');
                    timeOutInput.reportValidity();
                    return;
                }
                
                timeInInput.setCustomValidity('');
                timeOutInput.setCustomValidity('');
            } else {
                timeInInput.setCustomValidity('');
                timeOutInput.setCustomValidity('');
            }
        }

        // Initialize status colors and add time input event listeners
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.status-select').forEach(select => {
                const row = select.closest('tr');
                const timeInputs = row.querySelectorAll('input[type="time"]');
                
                updateTimeInputs(select);
                
                timeInputs.forEach(input => {
                    input.addEventListener('change', () => validateTimeRange(row));
                });
                
                select.addEventListener('change', () => updateTimeInputs(select));
            });
        });

        // Add form validation before submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const rows = document.querySelectorAll('tr');
            let hasError = false;

            rows.forEach(row => {
                const status = row.querySelector('.status-select')?.value;
                if (status && status !== 'absent' && status !== 'leave') {
                    const timeIn = row.querySelector('input[name^="time_in"]');
                    const timeOut = row.querySelector('input[name^="time_out"]');

                    if (timeIn && timeOut && timeIn.value && timeOut.value) {
                        const timeInDate = new Date('1970-01-01T' + timeIn.value);
                        const timeOutDate = new Date('1970-01-01T' + timeOut.value);
                        const timeDiff = (timeOutDate - timeInDate) / (1000 * 60);

                        if (timeDiff <= 0 || timeDiff > 720 || timeDiff < 1) {
                            hasError = true;
                            timeOut.setCustomValidity('Invalid time range');
                            timeOut.reportValidity();
                        }
                    }
                }
            });

            if (hasError) {
                e.preventDefault();
                return false;
            }
        });

        function clearSearch() {
            document.querySelector('input[name="search"]').value = '';
            document.querySelector('form').submit();
        }

        // Add helper function to remove query parameter
        function removeQueryParam(param) {
            const url = new URL(window.location.href);
            url.searchParams.delete(param);
            return url.toString();
        }

        // Add date range validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const dateRange = document.querySelector('select[name="date_range"]').value;
            if (dateRange === 'custom') {
                const startDate = new Date(document.querySelector('input[name="start_date"]').value);
                const endDate = new Date(document.querySelector('input[name="end_date"]').value);
                
                if (!document.querySelector('input[name="start_date"]').value || 
                    !document.querySelector('input[name="end_date"]').value) {
                    e.preventDefault();
                    alert('Please select both start and end dates for custom range');
                    return;
                }
                
                if (startDate > endDate) {
                    e.preventDefault();
                    alert('Start date cannot be later than end date');
                    return;
                }
            }
        });

        function toggleCustomDateRange(value) {
            const customDateRange = document.getElementById('customDateRange');
            if (value === 'custom') {
                customDateRange.classList.remove('d-none');
            } else {
                customDateRange.classList.add('d-none');
            }
        }

        // Initialize Chart.js
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('attendanceChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Present', 'Absent', 'Leave'],
                    datasets: [{
                        data: [
                            <?php echo $stats['present_count']; ?>,
                            <?php echo $stats['absent_count']; ?>,
                            <?php echo $stats['leave_count']; ?>
                        ],
                        backgroundColor: [
                            '#28a745', // Present - Green
                            '#dc3545', // Absent - Red
                            '#ffc107'  // Leave - Yellow
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>

<?php
// Helper function to remove query parameter
function removeQueryParam($param) {
    $params = $_GET;
    unset($params[$param]);
    return '?' . http_build_query($params);
}
?> 