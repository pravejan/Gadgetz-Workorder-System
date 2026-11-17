<?php
/**
 * Technician Panel Page
 */
require_once 'config.php';

// Enforce login
checkLogin();

$action = isset($_GET['action']) ? sanitize($_GET['action']) : 'list';
$error = '';
$success = '';
$is_admin = isAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    $id = (int)($_GET['id'] ?? 0);
    $notes = sanitize($_POST['notes'] ?? '');
    $parts_used = sanitize($_POST['parts_used'] ?? '');
    $actual_cost = (float)($_POST['actual_cost'] ?? 0);
    $status = sanitize($_POST['status'] ?? '');

    if (empty($notes) || empty($status)) {
        $error = "Diagnostic Note and Status are required fields.";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Insert diagnostic note
            $stmtNote = $pdo->prepare("INSERT INTO technician_notes (work_order_id, notes, parts_used) VALUES (?, ?, ?)");
            $stmtNote->execute([$id, $notes, $parts_used ?: null]);

            // 2. Update work order status and actual cost
            $stmtOrder = $pdo->prepare("UPDATE work_orders SET status = ?, actual_cost = ? WHERE work_order_id = ?");
            $stmtOrder->execute([$status, $actual_cost, $id]);

            $pdo->commit();
            header("Location: technician.php?action=view&id=$id&success=updated");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error updating repair log: " . $e->getMessage();
        }
    }
}

// Display messages
if (isset($_GET['success']) && $_GET['success'] === 'updated') {
    $success = "Diagnostic log added and status updated successfully.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Workspace - GADGETZ.LK</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <!-- Sidebar Navigation (Responsive & Role Aware) -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">🔧</div>
            <div>
                <div class="brand-title">GADGETZ.LK</div>
                <div class="brand-sub">Technician Panel</div>
            </div>
        </div>
        <ul class="sidebar-menu">
            <?php if ($is_admin): ?>
                <li><a href="dashboard.php">📊 Dashboard</a></li>
                <li><a href="customers.php">👥 Customers</a></li>
                <li><a href="workorder.php">📝 Work Orders</a></li>
                <li><a href="reports.php">📈 Reports</a></li>
            <?php endif; ?>
            <li class="active"><a href="technician.php">🔧 Tech Panel</a></li>
            <li class="logout-item"><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="main-header">
            <div>
                <h1 class="page-title">Technician Panel</h1>
                <p style="color: var(--text-secondary); margin-top: 4px;">Logged in as: <?php echo htmlspecialchars($_SESSION['full_name']); ?> (<?php echo ucfirst($_SESSION['role']); ?>)</p>
            </div>
            <div class="actions-header no-print">
                <?php if ($action !== 'list'): ?>
                    <a href="technician.php" class="btn btn-secondary"><- Back to Jobs</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- ACTION: DIAGNOSE & UPDATE FORM / VIEW TICKET -->
        <?php if ($action === 'view' || $action === 'update'): 
            $id = (int)$_GET['id'];
            
            // Fetch Work Order
            $stmt = $pdo->prepare("
                SELECT w.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email 
                FROM work_orders w
                JOIN customers c ON w.customer_id = c.customer_id
                WHERE w.work_order_id = ?
            ");
            $stmt->execute([$id]);
            $order = $stmt->fetch();

            if (!$order):
                echo '<div class="alert alert-error">Repair ticket not found.</div>';
            else:
                // Fetch previous notes
                $stmtNotes = $pdo->prepare("SELECT * FROM technician_notes WHERE work_order_id = ? ORDER BY date DESC");
                $stmtNotes->execute([$id]);
                $notes = $stmtNotes->fetchAll();

                // Fetch images
                $stmtImg = $pdo->prepare("SELECT * FROM images WHERE work_order_id = ?");
                $stmtImg->execute([$id]);
                $images = $stmtImg->fetchAll();

                $accessories = json_decode($order['accessories'] ?: '[]');
                $conditions = json_decode($order['physical_condition'] ?: '[]');
        ?>
            <div class="detail-grid">
                
                <!-- Update Diagnostics Section -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Add Diagnostic Log: <?php echo htmlspecialchars($order['work_order_no']); ?></h2>
                    </div>

                    <form action="technician.php?action=update&id=<?php echo $id; ?>" method="POST">
                        <div class="form-group" style="margin-bottom:20px;">
                            <label for="notes" class="form-label">Diagnostic Notes *</label>
                            <textarea name="notes" id="notes" class="form-control" required placeholder="Describe diagnosis findings, issues found, or work done..."></textarea>
                        </div>

                        <div class="form-group" style="margin-bottom:20px;">
                            <label for="parts_used" class="form-label">Parts Used / Replaced</label>
                            <input type="text" name="parts_used" id="parts_used" class="form-control" placeholder="e.g. 500GB SSD, HP Battery model X">
                        </div>

                        <div class="form-grid" style="margin-bottom:24px;">
                            <div class="form-group">
                                <label for="actual_cost" class="form-label">Actual Invoice Cost (Rs.)</label>
                                <input type="number" step="0.01" name="actual_cost" id="actual_cost" class="form-control" value="<?php echo formatMoney($order['actual_cost'] > 0 ? $order['actual_cost'] : $order['estimated_cost']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="status" class="form-label">Update Status *</label>
                                <select name="status" id="status" class="form-control" required>
                                    <option value="Diagnosing" <?php echo ($order['status'] === 'Diagnosing') ? 'selected' : ''; ?>>Diagnosing</option>
                                    <option value="Waiting for Parts" <?php echo ($order['status'] === 'Waiting for Parts') ? 'selected' : ''; ?>>Waiting for Parts</option>
                                    <option value="Repairing" <?php echo ($order['status'] === 'Repairing') ? 'selected' : ''; ?>>Repairing</option>
                                    <option value="Completed" <?php echo ($order['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Collected" <?php echo ($order['status'] === 'Collected') ? 'selected' : ''; ?>>Collected</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Submit Diagnostic Log</button>
                    </form>
                </div>

                <!-- Ticket details summary card -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Job Details</h2>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:16px;">
                        <div class="detail-section">
                            <h4>Device</h4>
                            <div class="detail-val" style="font-weight:600;"><?php echo htmlspecialchars($order['device_type'] . ' - ' . $order['brand'] . ' ' . $order['model']); ?></div>
                        </div>
                        <div class="detail-section">
                            <h4>Serial Number / IMEI</h4>
                            <div class="detail-val"><?php echo htmlspecialchars($order['serial_number'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="detail-section">
                            <h4>Customer Complaint</h4>
                            <div class="detail-val" style="background:#fff7ed; border-left:4px solid #ea580c; padding:8px 12px; font-size:0.9rem; border-radius:4px;">
                                <?php echo nl2br(htmlspecialchars($order['complaint'])); ?>
                            </div>
                        </div>
                        <div class="detail-section">
                            <h4>Device Note / Remarks</h4>
                            <div class="detail-val" style="background:#eff6ff; border-left:4px solid var(--accent); padding:8px 12px; font-size:0.9rem; border-radius:4px;">
                                <?php echo nl2br(htmlspecialchars($order['device_note'] ?: 'No internal remarks.')); ?>
                            </div>
                        </div>
                        <div class="detail-section">
                            <h4>Accessories</h4>
                            <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:4px;">
                                <?php foreach($accessories as $acc): ?>
                                    <span class="badge" style="background:#f1f5f9; color:#475569;"><?php echo htmlspecialchars($acc); ?></span>
                                <?php endforeach; if(count($accessories)===0) echo 'None'; ?>
                            </div>
                        </div>
                        <div class="detail-section">
                            <h4>Damages noted</h4>
                            <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:4px;">
                                <?php foreach($conditions as $cond): ?>
                                    <span class="badge" style="background:#fee2e2; color:#ef4444;"><?php echo htmlspecialchars($cond); ?></span>
                                <?php endforeach; if(count($conditions)===0) echo 'None'; ?>
                            </div>
                        </div>
                        <div class="detail-section">
                            <h4>Device Photos</h4>
                            <?php if (count($images) > 0): ?>
                                <div class="image-gallery" style="grid-template-columns: repeat(3, 1fr);">
                                    <?php foreach($images as $img): ?>
                                        <div class="gallery-item" onclick="window.open('<?php echo htmlspecialchars($img['image_path']); ?>', '_blank')">
                                            <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="Device view">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p style="font-size:0.8rem; color:var(--text-secondary);">No attached images.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Previous Logs Card -->
                <div class="card" style="grid-column: 1 / span 2;">
                    <div class="card-header">
                        <h2 class="card-title">Previous Diagnostic Logs (<?php echo count($notes); ?>)</h2>
                    </div>

                    <?php if (count($notes) > 0): ?>
                        <div style="display:flex; flex-direction:column; gap:12px;">
                            <?php foreach($notes as $note): ?>
                                <div style="background:#f8fafc; padding:12px 16px; border-radius:6px; border:1px solid var(--border-color); font-size:0.9rem;">
                                    <div style="color:var(--text-secondary); margin-bottom:6px; font-weight:600;">
                                        Logged: <?php echo date('M d, Y g:i A', strtotime($note['date'])); ?>
                                    </div>
                                    <div style="margin-bottom:6px;">
                                        <strong>Diagnostic Notes:</strong> <?php echo nl2br(htmlspecialchars($note['notes'])); ?>
                                    </div>
                                    <?php if (!empty($note['parts_used'])): ?>
                                        <div style="color:var(--primary); font-weight:500;">
                                            🛠️ <strong>Parts Used:</strong> <?php echo htmlspecialchars($note['parts_used']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align:center; padding:15px; color:var(--text-secondary);">No diagnostic notes logged yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ACTION: LIST ALL ACTIVE JOBS (DEFAULT) -->
        <?php else: 
            try {
                // Fetch active repair orders (Not Collected yet)
                $stmt = $pdo->query("
                    SELECT w.*, c.name as customer_name, c.phone as customer_phone 
                    FROM work_orders w
                    JOIN customers c ON w.customer_id = c.customer_id
                    WHERE w.status != 'Collected'
                    ORDER BY w.work_order_id ASC
                ");
                $active_jobs = $stmt->fetchAll();
            } catch (PDOException $e) {
                die("Query failed: " . $e->getMessage());
            }
        ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Active Repair Tickets</h2>
                </div>

                <?php if (count($active_jobs) > 0): ?>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Work Order No</th>
                                    <th>Device</th>
                                    <th>Complaint</th>
                                    <th>Estimated Cost</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_jobs as $job): ?>
                                    <tr>
                                        <td style="font-weight:600; color:var(--primary);"><?php echo htmlspecialchars($job['work_order_no']); ?></td>
                                        <td>
                                            <div style="font-weight:500;"><?php echo htmlspecialchars($job['brand'] . ' ' . $job['model']); ?></div>
                                            <div style="font-size:0.75rem; color:var(--text-secondary);"><?php echo htmlspecialchars($job['device_type']); ?></div>
                                        </td>
                                        <td style="max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo htmlspecialchars($job['complaint']); ?>">
                                            <?php echo htmlspecialchars($job['complaint']); ?>
                                        </td>
                                        <td>Rs. <?php echo formatMoney($job['estimated_cost']); ?></td>
                                        <td>
                                            <?php 
                                            $status_class = 'badge-received';
                                            switch($job['status']) {
                                                case 'Diagnosing': $status_class = 'badge-diagnosing'; break;
                                                case 'Waiting for Parts': $status_class = 'badge-waiting'; break;
                                                case 'Repairing': $status_class = 'badge-repairing'; break;
                                                case 'Completed': $status_class = 'badge-completed'; break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo htmlspecialchars($job['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="technician.php?action=view&id=<?php echo $job['work_order_id']; ?>" class="btn btn-primary btn-sm">🔧 Diagnose</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align:center; color:var(--text-secondary); padding:30px 0;">No active repair tickets in queue. Nice work!</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
