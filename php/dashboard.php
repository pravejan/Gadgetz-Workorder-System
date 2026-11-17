<?php
/**
 * Admin Dashboard Page
 */
require_once 'config.php';

// Enforce login and admin role
checkLogin();
if (!isAdmin()) {
    header("Location: technician.php");
    exit;
}

try {
    // 1. Fetch Stats
    // Total Work Orders
    $stmt = $pdo->query("SELECT COUNT(*) FROM work_orders");
    $total_orders = $stmt->fetchColumn();

    // Pending Repairs (Received, Diagnosing, Waiting for Parts, Repairing)
    $stmt = $pdo->query("SELECT COUNT(*) FROM work_orders WHERE status NOT IN ('Completed', 'Collected')");
    $pending_repairs = $stmt->fetchColumn();

    // Completed Repairs
    $stmt = $pdo->query("SELECT COUNT(*) FROM work_orders WHERE status = 'Completed'");
    $completed_repairs = $stmt->fetchColumn();

    // Devices Received Today
    $stmt = $pdo->query("SELECT COUNT(*) FROM work_orders WHERE DATE(received_date) = CURRENT_DATE()");
    $received_today = $stmt->fetchColumn();

    // 2. Fetch Recent 5 Work Orders
    $stmt = $pdo->query("
        SELECT w.*, c.name as customer_name, c.phone as customer_phone 
        FROM work_orders w
        JOIN customers c ON w.customer_id = c.customer_id
        ORDER BY w.work_order_id DESC 
        LIMIT 5
    ");
    $recent_orders = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - GADGETZ.LK</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">🛠️</div>
            <div>
                <div class="brand-title">GADGETZ.LK</div>
                <div class="brand-sub">Sales & Repairs</div>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li class="active"><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="customers.php">👥 Customers</a></li>
            <li><a href="workorder.php">📝 Work Orders</a></li>
            <li><a href="technician.php">🔧 Tech Panel</a></li>
            <li><a href="reports.php">📈 Reports</a></li>
            <li class="logout-item"><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="main-header">
            <div>
                <h1 class="page-title">Dashboard</h1>
                <p style="color: var(--text-secondary); margin-top: 4px;">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
            </div>
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
                </div>
                <div>
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <span class="user-role">Administrator</span>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card stat-total">
                <div class="stat-info">
                    <h3>Total Work Orders</h3>
                    <div class="stat-value"><?php echo $total_orders; ?></div>
                </div>
                <div class="stat-icon">📁</div>
            </div>
            <div class="stat-card stat-pending">
                <div class="stat-info">
                    <h3>Pending Repairs</h3>
                    <div class="stat-value"><?php echo $pending_repairs; ?></div>
                </div>
                <div class="stat-icon">⏳</div>
            </div>
            <div class="stat-card stat-completed">
                <div class="stat-info">
                    <h3>Completed Repairs</h3>
                    <div class="stat-value"><?php echo $completed_repairs; ?></div>
                </div>
                <div class="stat-icon">✅</div>
            </div>
            <div class="stat-card stat-today">
                <div class="stat-info">
                    <h3>Received Today</h3>
                    <div class="stat-value"><?php echo $received_today; ?></div>
                </div>
                <div class="stat-icon">📥</div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Recent Work Orders Card -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Work Orders</h2>
                    <a href="workorder.php?action=new" class="btn btn-primary btn-sm">+ New Work Order</a>
                </div>
                
                <?php if (count($recent_orders) > 0): ?>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Work Order No</th>
                                    <th>Customer Name</th>
                                    <th>Device</th>
                                    <th>Date Received</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td style="font-weight: 600; color: var(--primary);">
                                            <?php echo htmlspecialchars($order['work_order_no']); ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                            <div style="font-size: 0.75rem; color: var(--text-secondary);"><?php echo htmlspecialchars($order['customer_phone']); ?></div>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($order['device_type']); ?>
                                            <div style="font-size: 0.75rem; color: var(--text-secondary);"><?php echo htmlspecialchars($order['brand'] . ' ' . $order['model']); ?></div>
                                        </td>
                                        <td>
                                            <?php echo date('M d, Y g:i A', strtotime($order['received_date'])); ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $status_class = 'badge-received';
                                            switch($order['status']) {
                                                case 'Diagnosing': $status_class = 'badge-diagnosing'; break;
                                                case 'Waiting for Parts': $status_class = 'badge-waiting'; break;
                                                case 'Repairing': $status_class = 'badge-repairing'; break;
                                                case 'Completed': $status_class = 'badge-completed'; break;
                                                case 'Collected': $status_class = 'badge-collected'; break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo htmlspecialchars($order['status']); ?>
                                            </span>
                                        </td>
                                        <td class="actions-cell">
                                            <a href="workorder.php?action=view&id=<?php echo $order['work_order_id']; ?>" class="btn btn-secondary btn-sm" title="View">👁️</a>
                                            <a href="workorder.php?action=edit&id=<?php echo $order['work_order_id']; ?>" class="btn btn-secondary btn-sm" title="Edit">✏️</a>
                                            <a href="print.php?id=<?php echo $order['work_order_id']; ?>" class="btn btn-secondary btn-sm" title="Print" target="_blank">🖨️</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-secondary); padding: 30px 0;">No work orders found. Click "+ New Work Order" to get started.</p>
                <?php endif; ?>
            </div>

            <!-- Quick Links / Shortcuts Card -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Quick Actions</h2>
                </div>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <a href="workorder.php?action=new" class="btn btn-primary" style="justify-content: center;">
                        ➕ Create Work Order
                    </a>
                    <a href="customers.php?action=new" class="btn btn-secondary" style="justify-content: center;">
                        👤 Register Customer
                    </a>
                    <a href="customers.php" class="btn btn-secondary" style="justify-content: center;">
                        🔍 Search Customers
                    </a>
                    <a href="workorder.php" class="btn btn-secondary" style="justify-content: center;">
                        📂 View All Work Orders
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
