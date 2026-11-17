<?php
/**
 * Reports Page — graphical overview of work order activity, revenue and status
 * breakdown. Charts are rendered as plain SVG generated server-side, so the
 * page has zero external dependencies (consistent with the rest of the app).
 */
require_once 'config.php';

// Enforce login and admin role
checkLogin();
if (!isAdmin()) {
    header("Location: technician.php");
    exit;
}

/**
 * Build a vertical bar chart as an SVG string.
 */
function renderBarChart($values, $labels, $color, $money = false, $width = 600, $height = 240) {
    $padding_left = 55;
    $padding_bottom = 32;
    $padding_top = 24;
    $padding_right = 10;
    $chart_w = $width - $padding_left - $padding_right;
    $chart_h = $height - $padding_top - $padding_bottom;
    $max = max($values);
    $max = $max > 0 ? $max : 1;
    $n = max(count($values), 1);
    $bar_gap = 16;
    $bar_w = ($chart_w - ($bar_gap * ($n - 1))) / $n;

    $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg" style="width:100%; height:auto;" role="img">';

    // Horizontal grid lines + axis labels
    for ($g = 0; $g <= 4; $g++) {
        $y = $padding_top + $chart_h - ($chart_h * $g / 4);
        $svg .= '<line x1="' . $padding_left . '" y1="' . round($y, 1) . '" x2="' . ($width - $padding_right) . '" y2="' . round($y, 1) . '" stroke="#e2e8f0" stroke-width="1" />';
        $val = round($max * $g / 4);
        $label = $money ? 'Rs.' . number_format($val) : number_format($val);
        $svg .= '<text x="' . ($padding_left - 8) . '" y="' . round($y + 4, 1) . '" font-size="10" fill="#64748b" text-anchor="end">' . $label . '</text>';
    }

    foreach ($values as $i => $v) {
        $bar_h = ($v / $max) * $chart_h;
        $x = $padding_left + $i * ($bar_w + $bar_gap);
        $y = $padding_top + $chart_h - $bar_h;
        $svg .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($bar_w, 1) . '" height="' . round($bar_h, 1) . '" rx="4" fill="' . $color . '" />';

        if ($v > 0) {
            $val_label = $money ? 'Rs.' . number_format($v) : number_format($v);
            $svg .= '<text x="' . round($x + $bar_w / 2, 1) . '" y="' . round($y - 6, 1) . '" font-size="10" fill="#334155" text-anchor="middle" font-weight="700">' . $val_label . '</text>';
        }

        $svg .= '<text x="' . round($x + $bar_w / 2, 1) . '" y="' . ($height - 10) . '" font-size="11" fill="#64748b" text-anchor="middle">' . htmlspecialchars($labels[$i]) . '</text>';
    }

    $svg .= '</svg>';
    return $svg;
}

/**
 * Build a donut chart as an SVG string for a set of status counts.
 */
function renderDonutChart($counts, $colors) {
    $total = array_sum($counts);
    $r = 80;
    $circumference = 2 * M_PI * $r;
    $cumulative = 0;

    $svg = '<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" style="width:100%; max-width:220px; height:auto; display:block; margin:0 auto;" role="img">';
    $svg .= '<g transform="rotate(-90 100 100)">';

    if ($total === 0) {
        $svg .= '<circle cx="100" cy="100" r="' . $r . '" fill="none" stroke="#e2e8f0" stroke-width="28" />';
    } else {
        foreach ($counts as $label => $cnt) {
            if ($cnt <= 0) continue;
            $frac = $cnt / $total;
            $len = $frac * $circumference;
            $gap = $circumference - $len;
            $offset = -$cumulative;
            $color = $colors[$label] ?? '#94a3b8';
            $svg .= '<circle cx="100" cy="100" r="' . $r . '" fill="none" stroke="' . $color . '" stroke-width="28" stroke-dasharray="' . round($len, 2) . ' ' . round($gap, 2) . '" stroke-dashoffset="' . round($offset, 2) . '" />';
            $cumulative += $len;
        }
    }

    $svg .= '</g>';
    $svg .= '<text x="100" y="95" text-anchor="middle" font-size="28" font-weight="800" fill="#0f172a">' . $total . '</text>';
    $svg .= '<text x="100" y="118" text-anchor="middle" font-size="11" fill="#64748b">Total Orders</text>';
    $svg .= '</svg>';
    return $svg;
}

/**
 * Build a horizontal bar chart as an SVG string.
 */
function renderHBarChart($rows, $color = '#2563eb', $width = 600) {
    if (count($rows) === 0) {
        return '<p style="text-align:center; color:var(--text-secondary); padding:20px 0;">No data yet.</p>';
    }
    $max = max(array_column($rows, 'cnt'));
    $max = $max > 0 ? $max : 1;
    $row_h = 30;
    $gap = 12;
    $label_w = 130;
    $height = count($rows) * ($row_h + $gap);
    $chart_w = $width - $label_w - 55;

    $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg" style="width:100%; height:auto;" role="img">';
    foreach ($rows as $i => $row) {
        $y = $i * ($row_h + $gap);
        $bar_w = ($row['cnt'] / $max) * $chart_w;
        $svg .= '<text x="0" y="' . round($y + $row_h / 2 + 4, 1) . '" font-size="12" fill="#334155" font-weight="600">' . htmlspecialchars($row['label']) . '</text>';
        $svg .= '<rect x="' . $label_w . '" y="' . $y . '" width="' . round($bar_w, 1) . '" height="' . $row_h . '" rx="6" fill="' . $color . '" opacity="0.9" />';
        $svg .= '<text x="' . round($label_w + $bar_w + 8, 1) . '" y="' . round($y + $row_h / 2 + 4, 1) . '" font-size="12" fill="#0f172a" font-weight="700">' . $row['cnt'] . '</text>';
    }
    $svg .= '</svg>';
    return $svg;
}

try {
    // ---- Status distribution (all-time) ----
    $status_order = ['Received', 'Diagnosing', 'Waiting for Parts', 'Repairing', 'Completed', 'Collected'];
    $status_colors = [
        'Received'          => '#64748b',
        'Diagnosing'        => '#ea580c',
        'Waiting for Parts' => '#8b5cf6',
        'Repairing'         => '#0284c7',
        'Completed'         => '#16a34a',
        'Collected'         => '#334155',
    ];
    $status_counts = array_fill_keys($status_order, 0);
    $stmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM work_orders GROUP BY status");
    foreach ($stmt->fetchAll() as $row) {
        if (isset($status_counts[$row['status']])) {
            $status_counts[$row['status']] = (int)$row['cnt'];
        }
    }
    $total_orders = array_sum($status_counts);

    // ---- Last 6 months (including current) ----
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $months[] = date('Y-m', strtotime("-$i months"));
    }
    $month_labels = array_map(function ($m) {
        return date('M \'y', strtotime($m . '-01'));
    }, $months);

    // Work orders received per month
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(received_date, '%Y-%m') ym, COUNT(*) cnt 
        FROM work_orders 
        WHERE received_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY ym
    ");
    $volume_map = [];
    foreach ($stmt->fetchAll() as $row) {
        $volume_map[$row['ym']] = (int)$row['cnt'];
    }
    $volume_data = array_map(function ($m) use ($volume_map) {
        return $volume_map[$m] ?? 0;
    }, $months);

    // ---- Device type breakdown (all-time, top 8) ----
    $stmt = $pdo->query("SELECT device_type, COUNT(*) cnt FROM work_orders GROUP BY device_type ORDER BY cnt DESC LIMIT 8");
    $device_rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $device_rows[] = ['label' => $row['device_type'], 'cnt' => (int)$row['cnt']];
    }

    // ---- Summary stat cards ----
    $completed_total = $status_counts['Completed'] + $status_counts['Collected'];
    $completion_rate = $total_orders > 0 ? round(($completed_total / $total_orders) * 100) : 0;

    // Average turnaround time in days, for orders with a recorded completion date
    $stmt = $pdo->query("
        SELECT AVG(DATEDIFF(completed_date, received_date)) 
        FROM work_orders 
        WHERE completed_date IS NOT NULL
    ");
    $avg_turnaround = $stmt->fetchColumn();
    $avg_turnaround = $avg_turnaround !== null ? round((float)$avg_turnaround, 1) : null;

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - GADGETZ.LK</title>
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
            <li><a href="customers.php">👥 Customers</a></li>
            <li><a href="workorder.php">📝 Work Orders</a></li>
            <li><a href="technician.php">🔧 Tech Panel</a></li>
            <li class="active"><a href="reports.php">📈 Reports</a></li>
            <li class="logout-item"><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="main-header">
            <div>
                <h1 class="page-title">Reports</h1>
                <p style="color: var(--text-secondary); margin-top: 4px;">Graphical overview of repair activity and revenue</p>
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

        <!-- Summary Stats -->
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
                    <div class="stat-value"><?php echo $status_counts['Received'] + $status_counts['Diagnosing'] + $status_counts['Waiting for Parts'] + $status_counts['Repairing']; ?></div>
                </div>
                <div class="stat-icon">⏳</div>
            </div>
            <div class="stat-card stat-completed">
                <div class="stat-info">
                    <h3>Completed Repairs</h3>
                    <div class="stat-value"><?php echo $status_counts['Completed']; ?></div>
                </div>
                <div class="stat-icon">✅</div>
            </div>
            <div class="stat-card stat-today">
                <div class="stat-info">
                    <h3>Completion Rate</h3>
                    <div class="stat-value"><?php echo $completion_rate; ?>%</div>
                </div>
                <div class="stat-icon">📈</div>
            </div>
        </div>

        <?php if ($avg_turnaround !== null): ?>
            <div class="card" style="margin-bottom:24px; display:flex; align-items:center; gap:16px;">
                <div style="font-size:1.8rem;">⏱️</div>
                <div>
                    <div style="font-weight:700; color:var(--text-primary);">Average Turnaround Time: <?php echo $avg_turnaround; ?> day<?php echo $avg_turnaround == 1 ? '' : 's'; ?></div>
                    <div style="font-size:0.85rem; color:var(--text-secondary);">From the day a device is received to the day it's marked Completed.</div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Charts Grid -->
        <div class="dashboard-grid">

            <!-- Status Distribution -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Work Orders by Status</h2>
                </div>
                <div style="display:flex; gap:24px; flex-wrap:wrap; align-items:center;">
                    <div style="flex: 0 0 220px;">
                        <?php echo renderDonutChart($status_counts, $status_colors); ?>
                    </div>
                    <div style="flex:1; min-width:200px; display:flex; flex-direction:column; gap:10px;">
                        <?php foreach ($status_order as $s): ?>
                            <?php
                                $cnt = $status_counts[$s];
                                $pct = $total_orders > 0 ? round(($cnt / $total_orders) * 100) : 0;
                            ?>
                            <div style="display:flex; align-items:center; gap:10px; font-size:0.85rem;">
                                <span style="width:12px; height:12px; border-radius:3px; background:<?php echo $status_colors[$s]; ?>; flex-shrink:0;"></span>
                                <span style="flex:1; color:var(--text-primary); font-weight:500;"><?php echo htmlspecialchars($s); ?></span>
                                <span style="color:var(--text-secondary);"><?php echo $cnt; ?> (<?php echo $pct; ?>%)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Device Type Breakdown -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Top Device Types</h2>
                </div>
                <?php echo renderHBarChart($device_rows, '#2563eb'); ?>
            </div>

            <!-- Monthly Volume Trend -->
            <div class="card" style="grid-column: 1 / -1;">
                <div class="card-header">
                    <h2 class="card-title">Work Orders Received — Last 6 Months</h2>
                </div>
                <?php echo renderBarChart($volume_data, $month_labels, '#2563eb'); ?>
            </div>

        </div>
    </div>
</body>
</html>
