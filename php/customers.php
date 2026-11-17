<?php
/**
 * Customer Management Page
 */
require_once 'config.php';

// Enforce login and admin role
checkLogin();
if (!isAdmin()) {
    header("Location: technician.php");
    exit;
}

$action = isset($_GET['action']) ? sanitize($_GET['action']) : 'list';
$error = '';
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'new') {
        // Add new customer
        $name = sanitize($_POST['name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $address = sanitize($_POST['address'] ?? '');

        if (empty($name) || empty($phone)) {
            $error = "Customer Name and Phone Number are required.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO customers (name, phone, email, address) VALUES (:name, :phone, :email, :address)");
                $stmt->execute([
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email ?: null,
                    'address' => $address ?: null
                ]);
                $newId = $pdo->lastInsertId();
                header("Location: customers.php?action=view&id=$newId&success=registered");
                exit;
            } catch (PDOException $e) {
                $error = "Error adding customer: " . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        // Edit customer
        $id = (int)($_GET['id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $address = sanitize($_POST['address'] ?? '');

        if (empty($name) || empty($phone)) {
            $error = "Customer Name and Phone Number are required.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE customers SET name = :name, phone = :phone, email = :email, address = :address WHERE customer_id = :id");
                $stmt->execute([
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email ?: null,
                    'address' => $address ?: null,
                    'id' => $id
                ]);
                header("Location: customers.php?action=view&id=$id&success=updated");
                exit;
            } catch (PDOException $e) {
                $error = "Error updating customer: " . $e->getMessage();
            }
        }
    }
}

// Display messages from redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'registered') {
        $success = "Customer registered successfully.";
    } elseif ($_GET['success'] === 'updated') {
        $success = "Customer details updated successfully.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - GADGETZ.LK</title>
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
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li class="active"><a href="customers.php">👥 Customers</a></li>
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
                <h1 class="page-title">Customer Management</h1>
                <p style="color: var(--text-secondary); margin-top: 4px;">
                    <?php 
                    if ($action === 'new') echo 'Register a New Customer';
                    elseif ($action === 'edit') echo 'Modify Customer Details';
                    elseif ($action === 'view') echo 'Customer Profile & History';
                    else echo 'Manage Shop Clients';
                    ?>
                </p>
            </div>
            <div class="actions-header no-print">
                <?php if ($action !== 'list'): ?>
                    <a href="customers.php" class="btn btn-secondary"><- Back to List</a>
                <?php endif; ?>
                <?php if ($action === 'list'): ?>
                    <a href="customers.php?action=new" class="btn btn-primary">+ Register Customer</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- ACTION: NEW CUSTOMER -->
        <?php if ($action === 'new'): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Registration Form</h2>
                </div>
                <form action="customers.php?action=new" method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name" class="form-label font-bold">Customer Name *</label>
                            <input type="text" name="name" id="name" class="form-control" required placeholder="e.g. John Doe">
                        </div>
                        <div class="form-group">
                            <label for="phone" class="form-label">Phone Number *</label>
                            <input type="text" name="phone" id="phone" class="form-control" required placeholder="e.g. 555-0199">
                        </div>
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" name="email" id="email" class="form-control" placeholder="e.g. john@example.com">
                        </div>
                    </div>
                    <div class="form-group full-width" style="margin-bottom: 24px;">
                        <label for="address" class="form-label">Address</label>
                        <textarea name="address" id="address" class="form-control" placeholder="Street, City, Postcode"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Customer</button>
                </form>
            </div>

        <!-- ACTION: EDIT CUSTOMER -->
        <?php elseif ($action === 'edit'): 
            $id = (int)$_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ?");
            $stmt->execute([$id]);
            $customer = $stmt->fetch();
            
            if (!$customer):
                echo '<div class="alert alert-error">Customer not found.</div>';
            else:
        ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Edit Details for CUST-<?php echo str_pad($customer['customer_id'], 4, '0', STR_PAD_LEFT); ?></h2>
                </div>
                <form action="customers.php?action=edit&id=<?php echo $id; ?>" method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name" class="form-label">Customer Name *</label>
                            <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($customer['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone" class="form-label">Phone Number *</label>
                            <input type="text" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($customer['phone']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($customer['email']); ?>">
                        </div>
                    </div>
                    <div class="form-group full-width" style="margin-bottom: 24px;">
                        <label for="address" class="form-label">Address</label>
                        <textarea name="address" id="address" class="form-control"><?php echo htmlspecialchars($customer['address']); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Details</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- ACTION: VIEW CUSTOMER REPAIR HISTORY -->
        <?php elseif ($action === 'view'): 
            $id = (int)$_GET['id'];
            // Fetch Customer
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ?");
            $stmt->execute([$id]);
            $customer = $stmt->fetch();

            if (!$customer):
                echo '<div class="alert alert-error">Customer not found.</div>';
            else:
                // Fetch customer work orders
                $stmt = $pdo->prepare("SELECT * FROM work_orders WHERE customer_id = ? ORDER BY work_order_id DESC");
                $stmt->execute([$id]);
                $work_orders = $stmt->fetchAll();
        ?>
            <div class="detail-grid">
                <!-- Profile details -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Customer Details</h2>
                        <a href="customers.php?action=edit&id=<?php echo $id; ?>" class="btn btn-secondary btn-sm">Edit</a>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="detail-section">
                            <h4>Customer ID</h4>
                            <div class="detail-val" style="font-weight:700; color: var(--primary);">
                                CUST-<?php echo str_pad($customer['customer_id'], 4, '0', STR_PAD_LEFT); ?>
                            </div>
                        </div>
                        <div class="detail-section">
                            <h4>Name</h4>
                            <div class="detail-val"><?php echo htmlspecialchars($customer['name']); ?></div>
                        </div>
                        <div class="detail-section">
                            <h4>Phone Number</h4>
                            <div class="detail-val"><?php echo htmlspecialchars($customer['phone']); ?></div>
                        </div>
                        <div class="detail-section">
                            <h4>Email Address</h4>
                            <div class="detail-val"><?php echo htmlspecialchars($customer['email'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="detail-section" style="grid-column: 1 / -1;">
                            <h4>Address</h4>
                            <div class="detail-val"><?php echo nl2br(htmlspecialchars($customer['address'] ?: 'No address specified')); ?></div>
                        </div>
                    </div>
                </div>

                <!-- History Actions Panel -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Quick Tasks</h2>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <a href="workorder.php?action=new&customer_id=<?php echo $customer['customer_id']; ?>" class="btn btn-primary" style="justify-content: center;">
                            ➕ New Work Order
                        </a>
                    </div>
                </div>
            </div>

            <!-- Repair History Card -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Repair History (<?php echo count($work_orders); ?> Job(s))</h2>
                </div>
                
                <?php if (count($work_orders) > 0): ?>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Work Order No</th>
                                    <th>Device</th>
                                    <th>Estimated Cost</th>
                                    <th>Date Received</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($work_orders as $order): ?>
                                    <tr>
                                        <td style="font-weight:600; color:var(--primary);"><?php echo htmlspecialchars($order['work_order_no']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($order['device_type']); ?>
                                            <div style="font-size: 0.75rem; color: var(--text-secondary);"><?php echo htmlspecialchars($order['brand'] . ' ' . $order['model']); ?></div>
                                        </td>
                                        <td>Rs. <?php echo formatMoney($order['estimated_cost']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($order['received_date'])); ?></td>
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
                                            <a href="workorder.php?action=view&id=<?php echo $order['work_order_id']; ?>" class="btn btn-secondary btn-sm">👁️</a>
                                            <a href="workorder.php?action=edit&id=<?php echo $order['work_order_id']; ?>" class="btn btn-secondary btn-sm">✏️</a>
                                            <a href="print.php?id=<?php echo $order['work_order_id']; ?>" class="btn btn-secondary btn-sm" target="_blank">🖨️</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-secondary); padding: 20px 0;">This customer has no recorded repair history.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ACTION: LIST & SEARCH (DEFAULT) -->
        <?php else: 
            // Simple query with search filter
            $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
            
            try {
                if (!empty($search)) {
                    $stmt = $pdo->prepare("
                        SELECT * FROM customers 
                        WHERE name LIKE :query 
                           OR phone LIKE :query 
                           OR email LIKE :query 
                        ORDER BY customer_id DESC
                    ");
                    $stmt->execute(['query' => "%$search%"]);
                } else {
                    $stmt = $pdo->query("SELECT * FROM customers ORDER BY customer_id DESC");
                }
                $customers = $stmt->fetchAll();
            } catch (PDOException $e) {
                die("Query failed: " . $e->getMessage());
            }
        ?>
            <div class="card">
                <!-- Search bar -->
                <form action="customers.php" method="GET" class="search-wrapper">
                    <input type="text" name="search" class="form-control search-input" placeholder="Search by name, phone, or email..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="customers.php" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </form>

                <!-- Customer list table -->
                <?php if (count($customers) > 0): ?>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Customer ID</th>
                                    <th>Name</th>
                                    <th>Phone Number</th>
                                    <th>Email Address</th>
                                    <th>Address</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $c): ?>
                                    <tr>
                                        <td style="font-weight: 600; color: var(--primary);">
                                            CUST-<?php echo str_pad($c['customer_id'], 4, '0', STR_PAD_LEFT); ?>
                                        </td>
                                        <td style="font-weight: 500;">
                                            <?php echo htmlspecialchars($c['name']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($c['phone']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($c['email'] ?: 'N/A'); ?>
                                        </td>
                                        <td style="font-size: 0.8rem; color: var(--text-secondary); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo htmlspecialchars($c['address'] ?: 'N/A'); ?>
                                        </td>
                                        <td class="actions-cell">
                                            <a href="customers.php?action=view&id=<?php echo $c['customer_id']; ?>" class="btn btn-secondary btn-sm" title="View Profile">👁️ View History</a>
                                            <a href="customers.php?action=edit&id=<?php echo $c['customer_id']; ?>" class="btn btn-secondary btn-sm" title="Edit Info">✏️ Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-secondary); padding: 30px 0;">No customers found. Click "+ Register Customer" above to add one.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
