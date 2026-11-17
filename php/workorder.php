<?php
/**
 * Work Order Management Page
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

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'new') {
        // Create Work Order
        $customer_id = (int)($_POST['customer_id'] ?? 0);
        $device_type = sanitize($_POST['device_type'] ?? '');
        $brand = sanitize($_POST['brand'] ?? '');
        $model = sanitize($_POST['model'] ?? '');
        $serial_number = sanitize($_POST['serial_number'] ?? '');
        $colour = sanitize($_POST['colour'] ?? '');
        $complaint = sanitize($_POST['complaint'] ?? '');
        $device_note = sanitize($_POST['device_note'] ?? '');
        
        // Checkboxes stored as JSON
        $accessories = isset($_POST['accessories']) ? json_encode($_POST['accessories']) : json_encode([]);
        $physical_condition = isset($_POST['physical_condition']) ? json_encode($_POST['physical_condition']) : json_encode([]);
        
        $estimated_cost = (float)($_POST['estimated_cost'] ?? 0);
        $completion_date = sanitize($_POST['completion_date'] ?? '');
        $status = sanitize($_POST['status'] ?? '');

        if (empty($customer_id) || empty($device_type) || empty($brand) || empty($model) || empty($complaint)) {
            $error = "Please fill in all required fields (*).";
        } else {
            try {
                // Generate WO number inside transaction
                $pdo->beginTransaction();
                $work_order_no = generateWorkOrderNumber($pdo);

                $stmt = $pdo->prepare("
                    INSERT INTO work_orders (
                        work_order_no, customer_id, device_type, brand, model, 
                        serial_number, colour, complaint, device_note, accessories, 
                        physical_condition, estimated_cost, status, completion_date
                    ) VALUES (
                        :work_order_no, :customer_id, :device_type, :brand, :model, 
                        :serial_number, :colour, :complaint, :device_note, :accessories, 
                        :physical_condition, :estimated_cost, :status, :completion_date
                    )
                ");

                $stmt->execute([
                    'work_order_no' => $work_order_no,
                    'customer_id' => $customer_id,
                    'device_type' => $device_type,
                    'brand' => $brand,
                    'model' => $model,
                    'serial_number' => $serial_number ?: null,
                    'colour' => $colour ?: null,
                    'complaint' => $complaint,
                    'device_note' => $device_note ?: null,
                    'accessories' => $accessories,
                    'physical_condition' => $physical_condition,
                    'estimated_cost' => $estimated_cost,
                    'status' => $status,
                    'completion_date' => $completion_date ?: null
                ]);

                $work_order_id = $pdo->lastInsertId();

                // Handle Image Uploads
                if (!empty($_FILES['device_images']['name'][0])) {
                    $upload_dir = dirname(__DIR__) . '/images/uploads/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $total_files = count($_FILES['device_images']['name']);
                    // Cap at 5 images
                    $upload_limit = min($total_files, 5);

                    for ($i = 0; $i < $upload_limit; $i++) {
                        $file_name = $_FILES['device_images']['name'][$i];
                        $file_tmp = $_FILES['device_images']['tmp_name'][$i];
                        $file_size = $_FILES['device_images']['size'][$i];
                        $file_error = $_FILES['device_images']['error'][$i];

                        if ($file_error === UPLOAD_ERR_OK) {
                            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

                            if (in_array($file_ext, $allowed_types)) {
                                if ($file_size <= 5 * 1024 * 1024) { // 5MB Limit
                                    $new_file_name = uniqid('wo_img_', true) . '.' . $file_ext;
                                    $dest_path = $upload_dir . $new_file_name;

                                    if (move_uploaded_file($file_tmp, $dest_path)) {
                                        // Save path to DB
                                        $relative_path = '../images/uploads/' . $new_file_name;
                                        $img_stmt = $pdo->prepare("INSERT INTO images (work_order_id, image_path) VALUES (?, ?)");
                                        $img_stmt->execute([$work_order_id, $relative_path]);
                                    }
                                }
                            }
                        }
                    }
                }

                $pdo->commit();
                header("Location: workorder.php?action=view&id=$work_order_id&success=created");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error creating Work Order: " . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        // Edit Work Order
        $id = (int)($_GET['id'] ?? 0);
        $customer_id = (int)($_POST['customer_id'] ?? 0);
        $device_type = sanitize($_POST['device_type'] ?? '');
        $brand = sanitize($_POST['brand'] ?? '');
        $model = sanitize($_POST['model'] ?? '');
        $serial_number = sanitize($_POST['serial_number'] ?? '');
        $colour = sanitize($_POST['colour'] ?? '');
        $complaint = sanitize($_POST['complaint'] ?? '');
        $device_note = sanitize($_POST['device_note'] ?? '');
        
        $accessories = isset($_POST['accessories']) ? json_encode($_POST['accessories']) : json_encode([]);
        $physical_condition = isset($_POST['physical_condition']) ? json_encode($_POST['physical_condition']) : json_encode([]);
        
        $estimated_cost = (float)($_POST['estimated_cost'] ?? 0);
        $completion_date = sanitize($_POST['completion_date'] ?? '');
        $status = sanitize($_POST['status'] ?? '');

        if (empty($customer_id) || empty($device_type) || empty($brand) || empty($model) || empty($complaint)) {
            $error = "Please fill in all required fields (*).";
        } else {
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    UPDATE work_orders SET 
                        customer_id = :customer_id, device_type = :device_type, brand = :brand, 
                        model = :model, serial_number = :serial_number, colour = :colour, 
                        complaint = :complaint, device_note = :device_note, accessories = :accessories, 
                        physical_condition = :physical_condition, estimated_cost = :estimated_cost, 
                        status = :status, completion_date = :completion_date 
                    WHERE work_order_id = :id
                ");

                $stmt->execute([
                    'customer_id' => $customer_id,
                    'device_type' => $device_type,
                    'brand' => $brand,
                    'model' => $model,
                    'serial_number' => $serial_number ?: null,
                    'colour' => $colour ?: null,
                    'complaint' => $complaint,
                    'device_note' => $device_note ?: null,
                    'accessories' => $accessories,
                    'physical_condition' => $physical_condition,
                    'estimated_cost' => $estimated_cost,
                    'status' => $status,
                    'completion_date' => $completion_date ?: null,
                    'id' => $id
                ]);

                // Handle additional image uploads
                if (!empty($_FILES['device_images']['name'][0])) {
                    $upload_dir = dirname(__DIR__) . '/images/uploads/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $total_files = count($_FILES['device_images']['name']);
                    $upload_limit = min($total_files, 5);

                    for ($i = 0; $i < $upload_limit; $i++) {
                        $file_name = $_FILES['device_images']['name'][$i];
                        $file_tmp = $_FILES['device_images']['tmp_name'][$i];
                        $file_size = $_FILES['device_images']['size'][$i];
                        $file_error = $_FILES['device_images']['error'][$i];

                        if ($file_error === UPLOAD_ERR_OK) {
                            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

                            if (in_array($file_ext, $allowed_types)) {
                                if ($file_size <= 5 * 1024 * 1024) {
                                    $new_file_name = uniqid('wo_img_', true) . '.' . $file_ext;
                                    $dest_path = $upload_dir . $new_file_name;

                                    if (move_uploaded_file($file_tmp, $dest_path)) {
                                        $relative_path = '../images/uploads/' . $new_file_name;
                                        $img_stmt = $pdo->prepare("INSERT INTO images (work_order_id, image_path) VALUES (?, ?)");
                                        $img_stmt->execute([$id, $relative_path]);
                                    }
                                }
                            }
                        }
                    }
                }

                $pdo->commit();
                header("Location: workorder.php?action=view&id=$id&success=updated");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error updating Work Order: " . $e->getMessage();
            }
        }
    } elseif ($action === 'status') {
        // Quick update status
        $id = (int)($_GET['id'] ?? 0);
        $status = sanitize($_POST['status'] ?? '');

        try {
            $stmt = $pdo->prepare("UPDATE work_orders SET status = ? WHERE work_order_id = ?");
            $stmt->execute([$status, $id]);
            header("Location: workorder.php?action=view&id=$id&success=status_updated");
            exit;
        } catch (PDOException $e) {
            $error = "Error updating status: " . $e->getMessage();
        }
    } elseif ($action === 'complete') {
        // Mark as Completed: sets status to Completed and stamps the actual completion date/time
        $id = (int)($_GET['id'] ?? 0);
        $redirect_to = ($_POST['redirect_to'] ?? 'view') === 'list' ? 'workorder.php' : "workorder.php?action=view&id=$id";
        $glue = (strpos($redirect_to, '?') !== false) ? '&' : '?';

        try {
            $stmt = $pdo->prepare("UPDATE work_orders SET status = 'Completed', completed_date = NOW() WHERE work_order_id = ?");
            $stmt->execute([$id]);
            header("Location: $redirect_to{$glue}success=marked_completed");
            exit;
        } catch (PDOException $e) {
            $error = "Error marking Work Order as completed: " . $e->getMessage();
        }
    } elseif ($action === 'remove_image') {
        // Deletes an image DB record whose underlying file is no longer on disk
        // (a "clean up this broken reference" action, not a general image delete).
        $image_id = (int)($_GET['id'] ?? 0);
        $wo_id = (int)($_GET['wo_id'] ?? 0);

        try {
            $stmt = $pdo->prepare("SELECT image_path FROM images WHERE image_id = ?");
            $stmt->execute([$image_id]);
            $img_row = $stmt->fetch();

            if ($img_row) {
                $abs_path = dirname(__DIR__) . '/' . ltrim(str_replace('../', '', $img_row['image_path']), '/');
                // Only allow deleting the DB record if the file is genuinely missing —
                // this action is for cleanup, not for removing images that still exist.
                if (!file_exists($abs_path)) {
                    $del = $pdo->prepare("DELETE FROM images WHERE image_id = ?");
                    $del->execute([$image_id]);
                }
            }
            header("Location: workorder.php?action=view&id=$wo_id&success=image_cleaned");
            exit;
        } catch (PDOException $e) {
            $error = "Error removing image reference: " . $e->getMessage();
        }
    }
}

// Display messages from redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'created') {
        $success = "Work Order created successfully.";
    } elseif ($_GET['success'] === 'updated') {
        $success = "Work Order details updated successfully.";
    } elseif ($_GET['success'] === 'status_updated') {
        $success = "Repair status updated successfully.";
    } elseif ($_GET['success'] === 'marked_completed') {
        $success = "Work Order marked as Completed.";
    } elseif ($_GET['success'] === 'image_cleaned') {
        $success = "Broken image reference removed.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Order Management - GADGETZ.LK</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="../js/script.js" defer></script>
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
            <li><a href="customers.php">👥 Customers</a></li>
            <li class="active"><a href="workorder.php">📝 Work Orders</a></li>
            <li><a href="technician.php">🔧 Tech Panel</a></li>
            <li><a href="reports.php">📈 Reports</a></li>
            <li class="logout-item"><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="main-header">
            <div>
                <h1 class="page-title">Work Orders</h1>
                <p style="color: var(--text-secondary); margin-top: 4px;">
                    <?php 
                    if ($action === 'new') echo 'Create a New Repair Ticket';
                    elseif ($action === 'edit') echo 'Modify Repair Job Sheet';
                    elseif ($action === 'view') echo 'Work Order Details';
                    else echo 'Manage All Shop Repairs';
                    ?>
                </p>
            </div>
            <div class="actions-header no-print">
                <?php if ($action !== 'list'): ?>
                    <a href="workorder.php" class="btn btn-secondary"><- Back to List</a>
                <?php endif; ?>
                <?php if ($action === 'list'): ?>
                    <a href="workorder.php?action=new" class="btn btn-primary">+ Create Work Order</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- ACTION: NEW WORK ORDER -->
        <?php if ($action === 'new'): 
            // Load all customers for dropdown select
            $customers = $pdo->query("SELECT customer_id, name, phone FROM customers ORDER BY name ASC")->fetchAll();
            $preselected_customer = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
        ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">New Repair Job Sheet</h2>
                </div>
                
                <?php if (count($customers) === 0): ?>
                    <div style="text-align: center; padding: 20px;">
                        <p style="color: var(--text-secondary); margin-bottom: 15px;">No customers found. You must register a customer before creating a work order.</p>
                        <a href="customers.php?action=new" class="btn btn-primary">Register Customer First</a>
                    </div>
                <?php else: ?>
                    <form action="workorder.php?action=new" method="POST" enctype="multipart/form-data">
                        <!-- Customer Selection -->
                        <div class="form-group" style="margin-bottom: 24px;">
                            <label for="customer_id" class="form-label">Associate Customer *</label>
                            <select name="customer_id" id="customer_id" class="form-control" required>
                                <option value="">-- Choose Customer --</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?php echo $c['customer_id']; ?>" <?php echo ($c['customer_id'] == $preselected_customer) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['name']) . ' (' . htmlspecialchars($c['phone']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p style="font-size:0.75rem; color:var(--text-secondary); margin-top:4px;">Customer not in list? <a href="customers.php?action=new" style="color:var(--primary); font-weight:600;">Register new customer</a></p>
                        </div>

                        <!-- Device Details -->
                        <h3 style="font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-bottom: 16px;">Device Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="device_type" class="form-label">Device Type *</label>
                                <select name="device_type" id="device_type" class="form-control" required>
                                    <option value="Laptop">Laptop</option>
                                    <option value="Desktop">Desktop</option>
                                    <option value="Printer">Printer</option>
                                    <option value="Phone">Phone</option>
                                    <option value="CCTV Camera">CCTV Camera</option>
                                    <option value="DVR">DVR</option>
                                    <option value="Monitor">Monitor</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="brand" class="form-label">Brand *</label>
                                <input type="text" name="brand" id="brand" class="form-control" required placeholder="e.g. Dell, HP, Apple">
                            </div>
                            <div class="form-group">
                                <label for="model" class="form-label">Model *</label>
                                <input type="text" name="model" id="model" class="form-control" required placeholder="e.g. Inspiron 15, LaserJet">
                            </div>
                            <div class="form-group">
                                <label for="serial_number" class="form-label">Serial Number / IMEI (Optional)</label>
                                <input type="text" name="serial_number" id="serial_number" class="form-control" placeholder="e.g. SN-98765432">
                            </div>
                            <div class="form-group">
                                <label for="colour" class="form-label">Colour</label>
                                <input type="text" name="colour" id="colour" class="form-control" placeholder="e.g. Space Gray, Silver">
                            </div>
                        </div>

                        <!-- Accessories Received -->
                        <h3 style="font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-top: 24px; margin-bottom: 16px;">Accessories Received</h3>
                        <div class="checkbox-group" style="margin-bottom: 24px;">
                            <?php 
                            $acc_options = ['Charger', 'Laptop Bag', 'Mouse', 'Adapter', 'Battery', 'Other'];
                            foreach($acc_options as $opt):
                            ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="accessories[]" value="<?php echo $opt; ?>">
                                    <?php echo $opt; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <!-- Customer Complaint -->
                        <div class="form-group full-width" style="margin-bottom: 24px;">
                            <label for="complaint" class="form-label">Customer Complaint *</label>
                            <textarea name="complaint" id="complaint" class="form-control" required placeholder="e.g. Laptop does not boot, fan makes loud noise..."></textarea>
                        </div>

                        <!-- Device Note -->
                        <div class="form-group full-width" style="margin-bottom: 24px;">
                            <label for="device_note" class="form-label">Device Note / Internal Remarks (Optional)</label>
                            <textarea name="device_note" id="device_note" class="form-control" placeholder="e.g. Password/PIN: 1234, has sticker on back..."></textarea>
                        </div>

                        <!-- Physical Condition -->
                        <h3 style="font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-top: 24px; margin-bottom: 16px;">Physical Condition</h3>
                        <div class="checkbox-group" style="margin-bottom: 24px;">
                            <?php 
                            $cond_options = ['Minor Scratches', 'Broken Screen', 'Missing Keys', 'Water Damage', 'Other'];
                            foreach($cond_options as $opt):
                            ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="physical_condition[]" value="<?php echo $opt; ?>">
                                    <?php echo $opt; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <!-- Estimates & Status -->
                        <h3 style="font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-top: 24px; margin-bottom: 16px;">Repair Estimates & Status</h3>
                        <div class="form-grid" style="margin-bottom: 24px;">
                            <div class="form-group">
                                <label for="estimated_cost" class="form-label">Estimated Cost (Rs.)</label>
                                <input type="number" step="0.01" name="estimated_cost" id="estimated_cost" class="form-control" value="0.00">
                            </div>
                            <div class="form-group">
                                <label for="completion_date" class="form-label">Estimated Completion Date</label>
                                <input type="date" name="completion_date" id="completion_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>">
                            </div>
                            <div class="form-group">
                                <label for="status" class="form-label">Initial Status</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="Received">Received</option>
                                    <option value="Diagnosing">Diagnosing</option>
                                    <option value="Waiting for Parts">Waiting for Parts</option>
                                    <option value="Repairing">Repairing</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Collected">Collected</option>
                                </select>
                            </div>
                        </div>

                        <!-- Upload Device Images -->
                        <h3 style="font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-top: 24px; margin-bottom: 16px;">Upload Device Images (Max 5)</h3>
                        <div class="form-group" style="margin-bottom: 24px;">
                            <div class="image-upload-wrapper">
                                <div class="upload-icon">📷</div>
                                <p style="font-weight: 600; margin-bottom: 4px;">Click or Drag images to upload</p>
                                <p style="font-size: 0.75rem; color: var(--text-secondary);">Formats: JPG, PNG, GIF. Max size 5MB each.</p>
                                <input type="file" name="device_images[]" id="device_images" multiple accept="image/*">
                            </div>
                            <div id="image_previews" class="image-preview-container"></div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="padding: 12px 24px;">Create Work Order</button>
                    </form>
                <?php endif; ?>
            </div>

        <!-- ACTION: EDIT WORK ORDER -->
        <?php elseif ($action === 'edit'): 
            $id = (int)$_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM work_orders WHERE work_order_id = ?");
            $stmt->execute([$id]);
            $order = $stmt->fetch();

            if (!$order):
                echo '<div class="alert alert-error">Work Order not found.</div>';
            else:
                $customers = $pdo->query("SELECT customer_id, name, phone FROM customers ORDER BY name ASC")->fetchAll();
                $selected_accessories = json_decode($order['accessories'] ?: '[]');
                $selected_condition = json_decode($order['physical_condition'] ?: '[]');
        ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Modify Work Order: <?php echo htmlspecialchars($order['work_order_no']); ?></h2>
                </div>
                <form action="workorder.php?action=edit&id=<?php echo $id; ?>" method="POST" enctype="multipart/form-data">
                    
                    <!-- Customer Select -->
                    <div class="form-group" style="margin-bottom: 24px;">
                        <label for="customer_id" class="form-label">Associate Customer *</label>
                        <select name="customer_id" id="customer_id" class="form-control" required>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?php echo $c['customer_id']; ?>" <?php echo ($c['customer_id'] == $order['customer_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['name']) . ' (' . htmlspecialchars($c['phone']) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Device Details -->
                    <h3 style="font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-bottom: 16px;">Device Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="device_type" class="form-label">Device Type *</label>
                            <select name="device_type" id="device_type" class="form-control" required>
                                 <option value="Laptop" <?php echo ($order['device_type'] === 'Laptop') ? 'selected' : ''; ?>>Laptop</option>
                                 <option value="Desktop" <?php echo ($order['device_type'] === 'Desktop') ? 'selected' : ''; ?>>Desktop</option>
                                 <option value="Printer" <?php echo ($order['device_type'] === 'Printer') ? 'selected' : ''; ?>>Printer</option>
                                 <option value="Phone" <?php echo ($order['device_type'] === 'Phone') ? 'selected' : ''; ?>>Phone</option>
                                 <option value="CCTV Camera" <?php echo ($order['device_type'] === 'CCTV Camera') ? 'selected' : ''; ?>>CCTV Camera</option>
                                 <option value="DVR" <?php echo ($order['device_type'] === 'DVR') ? 'selected' : ''; ?>>DVR</option>
                                 <option value="Monitor" <?php echo ($order['device_type'] === 'Monitor') ? 'selected' : ''; ?>>Monitor</option>
                                 <option value="Other" <?php echo ($order['device_type'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                             </select>
                        </div>
                        <div class="form-group">
                            <label for="brand" class="form-label">Brand *</label>
                            <input type="text" name="brand" id="brand" class="form-control" value="<?php echo htmlspecialchars($order['brand']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="model" class="form-label">Model *</label>
                            <input type="text" name="model" id="model" class="form-control" value="<?php echo htmlspecialchars($order['model']); ?>" required>
                        </div>
                         <div class="form-group">
                             <label for="serial_number" class="form-label">Serial Number / IMEI (Optional)</label>
                             <input type="text" name="serial_number" id="serial_number" class="form-control" value="<?php echo htmlspecialchars($order['serial_number'] ?: ''); ?>">
                         </div>
                        <div class="form-group">
                            <label for="colour" class="form-label">Colour</label>
                            <input type="text" name="colour" id="colour" class="form-control" value="<?php echo htmlspecialchars($order['colour'] ?: ''); ?>">
                        </div>
                    </div>

                    <!-- Accessories -->
                    <h3 style="font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-top: 24px; margin-bottom: 16px;">Accessories Received</h3>
                    <div class="checkbox-group" style="margin-bottom: 24px;">
                        <?php 
                        $acc_options = ['Charger', 'Laptop Bag', 'Mouse', 'Adapter', 'Battery', 'Other'];
                        foreach($acc_options as $opt):
                        ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="accessories[]" value="<?php echo $opt; ?>" <?php echo in_array($opt, $selected_accessories) ? 'checked' : ''; ?>>
                                <?php echo $opt; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Complaint -->
                    <div class="form-group full-width" style="margin-bottom: 24px;">
                        <label for="complaint" class="form-label">Customer Complaint *</label>
                        <textarea name="complaint" id="complaint" class="form-control" required><?php echo htmlspecialchars($order['complaint']); ?></textarea>
                    </div>

                    <!-- Device Note -->
                    <div class="form-group full-width" style="margin-bottom: 24px;">
                        <label for="device_note" class="form-label">Device Note / Internal Remarks (Optional)</label>
                        <textarea name="device_note" id="device_note" class="form-control"><?php echo htmlspecialchars($order['device_note'] ?: ''); ?></textarea>
                    </div>

                    <!-- Condition -->
                    <h3 style="font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-top: 24px; margin-bottom: 16px;">Physical Condition</h3>
                    <div class="checkbox-group" style="margin-bottom: 24px;">
                        <?php 
                        $cond_options = ['Minor Scratches', 'Broken Screen', 'Missing Keys', 'Water Damage', 'Other'];
                        foreach($cond_options as $opt):
                        ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="physical_condition[]" value="<?php echo $opt; ?>" <?php echo in_array($opt, $selected_condition) ? 'checked' : ''; ?>>
                                <?php echo $opt; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Cost & Status -->
                    <h3 style="font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-top: 24px; margin-bottom: 16px;">Repair Estimates & Status</h3>
                    <div class="form-grid" style="margin-bottom: 24px;">
                        <div class="form-group">
                            <label for="estimated_cost" class="form-label">Estimated Cost (Rs.)</label>
                            <input type="number" step="0.01" name="estimated_cost" id="estimated_cost" class="form-control" value="<?php echo formatMoney($order['estimated_cost']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="completion_date" class="form-label">Estimated Completion Date</label>
                            <input type="date" name="completion_date" id="completion_date" class="form-control" value="<?php echo $order['completion_date']; ?>">
                        </div>
                        <div class="form-group">
                            <label for="status" class="form-label">Repair Status</label>
                            <select name="status" id="status" class="form-control">
                                <option value="Received" <?php echo ($order['status'] === 'Received') ? 'selected' : ''; ?>>Received</option>
                                <option value="Diagnosing" <?php echo ($order['status'] === 'Diagnosing') ? 'selected' : ''; ?>>Diagnosing</option>
                                <option value="Waiting for Parts" <?php echo ($order['status'] === 'Waiting for Parts') ? 'selected' : ''; ?>>Waiting for Parts</option>
                                <option value="Repairing" <?php echo ($order['status'] === 'Repairing') ? 'selected' : ''; ?>>Repairing</option>
                                <option value="Completed" <?php echo ($order['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                <option value="Collected" <?php echo ($order['status'] === 'Collected') ? 'selected' : ''; ?>>Collected</option>
                            </select>
                        </div>
                    </div>

                    <!-- Upload Additional Device Images -->
                    <h3 style="font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-top: 24px; margin-bottom: 16px;">Add Device Images (Max 5)</h3>
                    <div class="form-group" style="margin-bottom: 24px;">
                        <div class="image-upload-wrapper">
                            <div class="upload-icon">📷</div>
                            <p style="font-weight: 600; margin-bottom: 4px;">Click or Drag images to upload</p>
                            <input type="file" name="device_images[]" id="device_images" multiple accept="image/*">
                        </div>
                        <div id="image_previews" class="image-preview-container"></div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="padding: 12px 24px;">Save Changes</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- ACTION: VIEW WORK ORDER DETAIL -->
        <?php elseif ($action === 'view'): 
            $id = (int)$_GET['id'];
            
            // Fetch Work Order
            $stmt = $pdo->prepare("
                SELECT w.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email, c.address as customer_address 
                FROM work_orders w
                JOIN customers c ON w.customer_id = c.customer_id
                WHERE w.work_order_id = ?
            ");
            $stmt->execute([$id]);
            $order = $stmt->fetch();

            if (!$order):
                echo '<div class="alert alert-error">Work Order not found.</div>';
            else:
                // Fetch images
                $stmtImg = $pdo->prepare("SELECT * FROM images WHERE work_order_id = ?");
                $stmtImg->execute([$id]);
                $images = $stmtImg->fetchAll();

                // Fetch diagnosis notes
                $stmtNotes = $pdo->prepare("SELECT * FROM technician_notes WHERE work_order_id = ? ORDER BY date DESC");
                $stmtNotes->execute([$id]);
                $notes = $stmtNotes->fetchAll();

                $accessories = json_decode($order['accessories'] ?: '[]');
                $conditions = json_decode($order['physical_condition'] ?: '[]');
        ?>
            <div class="detail-grid">
                
                <!-- Main Details Card -->
                <div class="card" style="grid-column: 1 / span 2; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span style="font-size:0.8rem; font-weight:700; color:var(--text-secondary); text-transform:uppercase;">Work Order Number</span>
                        <h2 style="font-size:2rem; font-weight:800; color:var(--primary);"><?php echo htmlspecialchars($order['work_order_no']); ?></h2>
                    </div>
                    <div style="display:flex; gap:12px; flex-wrap:wrap;" class="no-print">
                        <?php if (!in_array($order['status'], ['Completed', 'Collected'])): ?>
                            <form action="workorder.php?action=complete&id=<?php echo $id; ?>" method="POST" onsubmit="return confirm('Mark this work order as Completed? This will record today\'s date as the completion date.');" style="display:inline;">
                                <input type="hidden" name="redirect_to" value="view">
                                <button type="submit" class="btn btn-success">✅ Mark as Completed</button>
                            </form>
                        <?php endif; ?>
                        <a href="workorder.php?action=edit&id=<?php echo $id; ?>" class="btn btn-secondary">✏️ Edit Ticket</a>
                        <a href="print.php?id=<?php echo $id; ?>" class="btn btn-success" target="_blank">🖨️ Print Copies</a>
                        <?php
                            $wa_message = "Hello " . $order['customer_name'] . ", this is GADGETZ.LK.\n\n"
                                . "Your work order " . $order['work_order_no'] . " for your " . $order['brand'] . " " . $order['model'] . " (" . $order['device_type'] . ") has been logged.\n"
                                . "Status: " . $order['status'] . "\n\n"
                                . "We'll attach your job sheet PDF here shortly. Thank you for choosing us!";
                            $wa_link = buildWhatsAppLink($order['customer_phone'], $wa_message);
                        ?>
                        <?php if ($wa_link): ?>
                            <a href="<?php echo htmlspecialchars($wa_link); ?>" class="btn btn-whatsapp" target="_blank" title="Opens WhatsApp with <?php echo htmlspecialchars($order['customer_phone']); ?> and a pre-filled message. Attach the printed PDF before sending.">📱 Send via WhatsApp</a>
                        <?php else: ?>
                            <span class="btn btn-secondary" style="opacity:0.55; cursor:not-allowed;" title="This customer's phone number doesn't look valid — check it under Customers.">📱 WhatsApp Unavailable</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Device & Job Specification</h2>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
                        <div class="detail-section">
                            <h4>Device Type</h4>
                            <div class="detail-val"><?php echo htmlspecialchars($order['device_type']); ?></div>
                        </div>
                        <div class="detail-section">
                            <h4>Brand & Model</h4>
                            <div class="detail-val"><?php echo htmlspecialchars($order['brand'] . ' ' . $order['model']); ?></div>
                        </div>
                        <div class="detail-section">
                            <h4>Serial Number / IMEI</h4>
                            <div class="detail-val"><?php echo htmlspecialchars($order['serial_number'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="detail-section">
                            <h4>Colour</h4>
                            <div class="detail-val"><?php echo htmlspecialchars($order['colour'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="detail-section" style="grid-column: 1 / -1;">
                            <h4>Accessories Received</h4>
                            <div class="detail-val">
                                <?php if (count($accessories) > 0): ?>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:6px;">
                                        <?php foreach($accessories as $acc): ?>
                                            <span class="badge" style="background:#e2e8f0; color:#334155;"><?php echo htmlspecialchars($acc); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    No accessories received.
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="detail-section" style="grid-column: 1 / -1;">
                            <h4>Physical Condition Checklist</h4>
                            <div class="detail-val">
                                <?php if (count($conditions) > 0): ?>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:6px;">
                                        <?php foreach($conditions as $cond): ?>
                                            <span class="badge" style="background:#fee2e2; color:#b91c1c;"><?php echo htmlspecialchars($cond); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    No physical damages noted.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4>Customer Complaint Description</h4>
                        <div class="detail-val" style="background:#f8fafc; padding:16px; border-radius:6px; border-left:4px solid var(--primary); margin-top:8px;">
                            <?php echo nl2br(htmlspecialchars($order['complaint'])); ?>
                        </div>
                    </div>

                    <div class="detail-section" style="margin-top:16px;">
                        <h4>Device Note / Internal Remarks</h4>
                        <div class="detail-val" style="background:#f8fafc; padding:16px; border-radius:6px; border-left:4px solid var(--accent); margin-top:8px;">
                            <?php echo nl2br(htmlspecialchars($order['device_note'] ?: 'No internal remarks.')); ?>
                        </div>
                    </div>

                    <!-- Uploaded Images Section -->
                    <div class="detail-section" style="margin-top: 24px;">
                        <h4>Attached Device Images</h4>
                        <?php if (count($images) > 0): ?>
                            <div class="image-gallery">
                                <?php foreach($images as $img): 
                                    // The DB stores a path relative to /php/ (e.g. "../images/uploads/x.jpg").
                                    // Resolve it to an absolute path to check the file is actually still on disk —
                                    // it can go missing if the uploads folder was ever replaced separately from the DB.
                                    $img_abs_path = dirname(__DIR__) . '/' . ltrim(str_replace('../', '', $img['image_path']), '/');
                                    $img_exists = file_exists($img_abs_path);
                                ?>
                                    <?php if ($img_exists): ?>
                                        <div class="gallery-item" onclick="window.open('<?php echo htmlspecialchars($img['image_path']); ?>', '_blank')">
                                            <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="Device view">
                                        </div>
                                    <?php else: ?>
                                        <div class="gallery-item gallery-item-missing" title="This file is no longer on the server. It may have been lost when the app files were last updated.">
                                            <div class="gallery-missing-icon">🖼️</div>
                                            <div class="gallery-missing-label">Image Missing</div>
                                            <form action="workorder.php?action=remove_image&id=<?php echo $img['image_id']; ?>&wo_id=<?php echo $id; ?>" method="POST" onsubmit="return confirm('Remove this broken image reference? The file is already gone — this just cleans up the record.');">
                                                <button type="submit" class="gallery-missing-remove" title="Remove broken reference">✕</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="color:var(--text-secondary); font-size:0.9rem; margin-top:8px;">No device images uploaded.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column: Status & Costs / Customer info -->
                <div style="display: flex; flex-direction: column; gap: 24px;">
                    
                    <!-- Customer Card -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Customer Information</h2>
                        </div>
                        <div class="detail-section">
                            <h4>Name</h4>
                            <div class="detail-val" style="font-weight: 600;"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                        </div>
                        <div class="detail-section">
                            <h4>Phone</h4>
                            <div class="detail-val"><?php echo htmlspecialchars($order['customer_phone']); ?></div>
                        </div>
                        <div class="detail-section">
                            <h4>Email</h4>
                            <div class="detail-val"><?php echo htmlspecialchars($order['customer_email'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="detail-section">
                            <h4>Address</h4>
                            <div class="detail-val" style="font-size:0.85rem; color:var(--text-secondary);"><?php echo nl2br(htmlspecialchars($order['customer_address'] ?: 'N/A')); ?></div>
                        </div>
                    </div>

                    <!-- Status & Costs -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Status & Pricing</h2>
                        </div>
                        
                        <!-- Quick status updater form -->
                        <form action="workorder.php?action=status&id=<?php echo $id; ?>" method="POST" style="margin-bottom:20px;">
                            <div class="form-group" style="margin-bottom:12px;">
                                <label for="status" class="form-label">Repair Status</label>
                                <select name="status" id="status" class="form-control" onchange="this.form.submit()">
                                    <option value="Received" <?php echo ($order['status'] === 'Received') ? 'selected' : ''; ?>>Received</option>
                                    <option value="Diagnosing" <?php echo ($order['status'] === 'Diagnosing') ? 'selected' : ''; ?>>Diagnosing</option>
                                    <option value="Waiting for Parts" <?php echo ($order['status'] === 'Waiting for Parts') ? 'selected' : ''; ?>>Waiting for Parts</option>
                                    <option value="Repairing" <?php echo ($order['status'] === 'Repairing') ? 'selected' : ''; ?>>Repairing</option>
                                    <option value="Completed" <?php echo ($order['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Collected" <?php echo ($order['status'] === 'Collected') ? 'selected' : ''; ?>>Collected</option>
                                </select>
                            </div>
                        </form>

                        <div class="detail-section">
                            <h4>Received Date</h4>
                            <div class="detail-val"><?php echo date('M d, Y g:i A', strtotime($order['received_date'])); ?></div>
                        </div>
                        <div class="detail-section">
                            <h4>Target Completion Date</h4>
                            <div class="detail-val"><?php echo $order['completion_date'] ? date('M d, Y', strtotime($order['completion_date'])) : 'N/A'; ?></div>
                        </div>
                        <?php if (!empty($order['completed_date'])): ?>
                        <div class="detail-section">
                            <h4>Completed On</h4>
                            <div class="detail-val" style="color: var(--status-completed); font-weight:700;"><?php echo date('M d, Y g:i A', strtotime($order['completed_date'])); ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="detail-section">
                            <h4>Estimated Repair Cost</h4>
                            <div class="detail-val" style="font-size:1.2rem; font-weight:700; color:var(--primary);">Rs. <?php echo formatMoney($order['estimated_cost']); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Diagnosis Logs (Technician Notes) -->
                <div class="card" style="grid-column: 1 / span 2;">
                    <div class="card-header">
                        <h2 class="card-title">Technician Diagnostic Logs & Progress</h2>
                    </div>

                    <?php if (count($notes) > 0): ?>
                        <div style="display:flex; flex-direction:column; gap:16px;">
                            <?php foreach($notes as $note): ?>
                                <div style="background:#f8fafc; padding:16px; border-radius:8px; border:1px solid var(--border-color);">
                                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; align-items:center; flex-wrap:wrap; gap:8px;">
                                        <div style="font-weight:600; font-size:0.9rem; color:var(--sidebar-bg);">Logged at: <?php echo date('M d, Y g:i A', strtotime($note['date'])); ?></div>
                                    </div>
                                    <div style="font-size:0.95rem; line-height:1.5; color:var(--text-primary);">
                                        <strong>Diagnostic Notes:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($note['notes'])); ?>
                                    </div>
                                    <?php if (!empty($note['parts_used'])): ?>
                                        <div style="margin-top:10px; font-size:0.85rem; background:#eff6ff; padding:8px 12px; border-radius:4px; color:var(--primary); font-weight:500;">
                                            🛠️ <strong>Parts Used:</strong> <?php echo htmlspecialchars($note['parts_used']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align:center; padding:20px; color:var(--text-secondary); font-size:0.9rem;">No technician diagnostic logs found for this repair ticket.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ACTION: LIST & SEARCH (DEFAULT) -->
        <?php else: 
            $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
            $filter = isset($_GET['filter']) ? sanitize($_GET['filter']) : 'pending';
            if (!in_array($filter, ['pending', 'completed', 'collected', 'all'])) {
                $filter = 'pending';
            }

            try {
                // Base query with optional search + status filter
                $conditions = [];
                $params = [];

                if (!empty($search)) {
                    $conditions[] = "(w.work_order_no LIKE :q OR c.name LIKE :q OR c.phone LIKE :q OR w.model LIKE :q)";
                    $params['q'] = "%$search%";
                }

                if ($filter === 'pending') {
                    $conditions[] = "w.status NOT IN ('Completed', 'Collected')";
                } elseif ($filter === 'completed') {
                    $conditions[] = "w.status = 'Completed'";
                } elseif ($filter === 'collected') {
                    $conditions[] = "w.status = 'Collected'";
                }

                $where_sql = count($conditions) > 0 ? "WHERE " . implode(' AND ', $conditions) : '';

                $stmt = $pdo->prepare("
                    SELECT w.*, c.name as customer_name, c.phone as customer_phone 
                    FROM work_orders w
                    JOIN customers c ON w.customer_id = c.customer_id
                    $where_sql
                    ORDER BY w.work_order_id DESC
                ");
                $stmt->execute($params);
                $work_orders = $stmt->fetchAll();

                // Counts for tab badges (respect the active search, ignore the active filter)
                $count_where = !empty($search) ? "WHERE (w.work_order_no LIKE :q OR c.name LIKE :q OR c.phone LIKE :q OR w.model LIKE :q)" : '';
                $count_params = !empty($search) ? ['q' => "%$search%"] : [];

                $pending_count = $pdo->prepare("SELECT COUNT(*) FROM work_orders w JOIN customers c ON w.customer_id = c.customer_id " . ($count_where ? $count_where . " AND w.status NOT IN ('Completed','Collected')" : "WHERE w.status NOT IN ('Completed','Collected')"));
                $pending_count->execute($count_params);
                $pending_total = $pending_count->fetchColumn();

                $completed_count = $pdo->prepare("SELECT COUNT(*) FROM work_orders w JOIN customers c ON w.customer_id = c.customer_id " . ($count_where ? $count_where . " AND w.status = 'Completed'" : "WHERE w.status = 'Completed'"));
                $completed_count->execute($count_params);
                $completed_total = $completed_count->fetchColumn();

                $collected_count = $pdo->prepare("SELECT COUNT(*) FROM work_orders w JOIN customers c ON w.customer_id = c.customer_id " . ($count_where ? $count_where . " AND w.status = 'Collected'" : "WHERE w.status = 'Collected'"));
                $collected_count->execute($count_params);
                $collected_total = $collected_count->fetchColumn();

                $all_total = $pending_total + $completed_total + $collected_total;
            } catch (PDOException $e) {
                die("Query failed: " . $e->getMessage());
            }
        ?>
            <div class="card">
                <!-- Pending / Completed / Collected / All Tabs -->
                <div class="wo-filter-tabs no-print">
                    <a href="workorder.php?filter=pending<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="wo-filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                        🔧 Pending <span class="wo-filter-count"><?php echo $pending_total; ?></span>
                    </a>
                    <a href="workorder.php?filter=completed<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="wo-filter-tab <?php echo $filter === 'completed' ? 'active' : ''; ?>">
                        ✅ Completed <span class="wo-filter-count"><?php echo $completed_total; ?></span>
                    </a>
                    <a href="workorder.php?filter=collected<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="wo-filter-tab <?php echo $filter === 'collected' ? 'active' : ''; ?>">
                        📦 Collected <span class="wo-filter-count"><?php echo $collected_total; ?></span>
                    </a>
                    <a href="workorder.php?filter=all<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="wo-filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        📋 All <span class="wo-filter-count"><?php echo $all_total; ?></span>
                    </a>
                </div>

                <form action="workorder.php" method="GET" class="search-wrapper">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <input type="text" name="search" class="form-control search-input" placeholder="Search Work Order #, Customer Name, Phone, or Model..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="workorder.php?filter=<?php echo htmlspecialchars($filter); ?>" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </form>

                <?php if (count($work_orders) > 0): ?>
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
                                <?php foreach ($work_orders as $order): ?>
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
                                            <?php echo date('M d, Y', strtotime($order['received_date'])); ?>
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
                                            <a href="workorder.php?action=view&id=<?php echo $order['work_order_id']; ?>" class="btn btn-secondary btn-sm" title="View Details">👁️ View</a>
                                            <a href="workorder.php?action=edit&id=<?php echo $order['work_order_id']; ?>" class="btn btn-secondary btn-sm" title="Edit">✏️ Edit</a>
                                            <a href="print.php?id=<?php echo $order['work_order_id']; ?>" class="btn btn-secondary btn-sm" title="Print Layout" target="_blank">🖨️ Print</a>
                                            <?php if (!in_array($order['status'], ['Completed', 'Collected'])): ?>
                                                <form action="workorder.php?action=complete&id=<?php echo $order['work_order_id']; ?>" method="POST" onsubmit="return confirm('Mark this work order as Completed?');" style="display:inline;">
                                                    <input type="hidden" name="redirect_to" value="list">
                                                    <button type="submit" class="btn btn-success btn-sm" title="Mark as Completed">✅ Complete</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-secondary); padding: 30px 0;">
                        <?php if ($filter === 'pending'): ?>
                            No pending work orders. Everything's caught up! 🎉
                        <?php elseif ($filter === 'completed'): ?>
                            No completed work orders yet.
                        <?php elseif ($filter === 'collected'): ?>
                            No collected work orders yet.
                        <?php else: ?>
                            No work orders found. Click "+ Create Work Order" above to log a repair.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
