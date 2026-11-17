<?php
/**
 * Printable Work Order / Job Sheet
 */
require_once 'config.php';

// Enforce login
checkLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = isset($_GET['type']) ? sanitize($_GET['type']) : 'customer'; // customer or shop

if ($id <= 0) {
    die("Invalid Work Order ID.");
}

try {
    // Fetch Work Order and Customer Details
    $stmt = $pdo->prepare("
        SELECT w.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email, c.address as customer_address 
        FROM work_orders w
        JOIN customers c ON w.customer_id = c.customer_id
        WHERE w.work_order_id = ?
    ");
    $stmt->execute([$id]);
    $order = $stmt->fetch();

    if (!$order) {
        die("Work Order not found.");
    }

    // Fetch technician notes (for Shop Copy)
    $stmtNotes = $pdo->prepare("SELECT * FROM technician_notes WHERE work_order_id = ? ORDER BY date DESC");
    $stmtNotes->execute([$id]);
    $notes = $stmtNotes->fetchAll();

    $accessories = json_decode($order['accessories'] ?: '[]');
    $conditions = json_decode($order['physical_condition'] ?: '[]');

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Work Order - <?php echo htmlspecialchars($order['work_order_no']); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="../js/script.js" defer></script>
    <style>
        :root {
            --navy: #0f172a;
        }

        /* Screen-only adjustments for printable pages */
        body {
            background-color: #64748b;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 30px 10px;
            min-height: 100vh;
        }

        .print-control-bar {
            width: 100%;
            max-width: 620px;
            background: #0f172a;
            padding: 16px 24px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-lg);
            flex-wrap: wrap;
            gap: 12px;
        }

        .print-copy-badge {
            background-color: var(--primary);
            color: white;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            text-transform: uppercase;
        }

        .print-a5-note {
            width: 100%;
            max-width: 620px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            font-size: 0.8rem;
            line-height: 1.5;
            padding: 10px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
        }

        .print-paper {
            background: white;
            width: 100%;
            max-width: 620px;
            padding: 36px 32px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            position: relative;
            box-sizing: border-box;
        }

        /* Top letterhead accent bars */
        .letterhead-accent-thick {
            height: 5px;
            width: 42%;
            background: var(--navy);
        }
        .letterhead-accent-thin {
            height: 2px;
            width: 100%;
            background: var(--navy);
            margin-bottom: 14px;
        }

        /* Header layout: brand block (left) + WO info table (right) */
        .letterhead-grid {
            display: flex;
            gap: 18px;
            align-items: flex-start;
            margin-bottom: 14px;
        }
        .letterhead-grid > div:first-child {
            flex: 1.15;
            min-width: 0;
        }
        .letterhead-grid > div:last-child {
            flex: 1;
            min-width: 0;
        }

        .letterhead-brand {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .letterhead-logo-icon {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            margin-top: 2px;
            object-fit: contain;
        }

        .letterhead-wordmark {
            font-size: 1.6rem;
            font-weight: 900;
            letter-spacing: -0.01em;
            line-height: 1;
            color: var(--navy);
        }
        .letterhead-wordmark .accent {
            color: var(--primary);
        }

        .letterhead-tagline {
            font-size: 0.62rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            color: var(--navy);
            margin-top: 5px;
            text-transform: uppercase;
        }

        .letterhead-contact-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.74rem;
            color: var(--text-primary);
            margin-top: 5px;
            font-weight: 500;
        }

        .letterhead-contact-icon {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
            color: var(--navy);
        }

        .wo-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--navy);
            text-transform: uppercase;
            letter-spacing: 0.01em;
            margin-bottom: 6px;
            text-align: right;
        }

        .wo-info-table {
            border: 1px solid var(--border-color);
            border-radius: 4px;
            overflow: hidden;
        }
        .wo-info-row {
            display: flex;
            border-bottom: 1px solid var(--border-color);
        }
        .wo-info-row:last-child {
            border-bottom: none;
        }
        .wo-info-label {
            background: var(--navy);
            color: #fff;
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            padding: 6px 8px;
            display: flex;
            align-items: center;
            flex: 0 0 48%;
        }
        .wo-info-value {
            padding: 6px 8px;
            font-size: 0.76rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            background: #fff;
            flex: 1;
            min-width: 0;
        }

        /* Numbered section blocks */
        .wo-section {
            border: 1px solid var(--border-color);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .wo-section-header {
            background: var(--navy);
            color: #fff;
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 6px 10px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .wo-section-header svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
        }
        .wo-section-body {
            padding: 9px 10px;
        }

        .wo-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            padding: 4px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.76rem;
        }
        .wo-detail-row:last-child {
            border-bottom: none;
        }
        .wo-detail-label {
            color: var(--text-secondary);
            font-weight: 500;
            flex-shrink: 0;
        }
        .wo-detail-value {
            color: var(--text-primary);
            font-weight: 600;
            text-align: right;
        }

        .wo-two-col {
            display: flex;
            gap: 14px;
            margin-bottom: 0;
        }
        .wo-two-col > .wo-section {
            flex: 1;
            min-width: 0;
        }

        .wo-checkbox-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 9px 6px;
        }
        .wo-checkbox-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.72rem;
            color: var(--text-primary);
            flex: 0 0 47%;
            white-space: nowrap;
        }
        .wo-checkbox-box {
            width: 12px;
            height: 12px;
            border: 1.5px solid #94a3b8;
            border-radius: 2px;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .wo-checkbox-box.checked {
            background: var(--navy);
            border-color: var(--navy);
            color: #fff;
            font-size: 0.55rem;
            font-weight: 800;
        }

        .wo-notes-line {
            display: flex;
            align-items: baseline;
            gap: 6px;
            margin-top: 6px;
            font-size: 0.72rem;
        }
        .wo-notes-line strong {
            flex-shrink: 0;
        }
        .wo-notes-line .fill-line {
            flex: 1;
            border-bottom: 1px solid var(--border-color);
        }
        .wo-notes-value {
            font-size: 0.72rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .wo-condition-label {
            font-weight: 700;
            font-size: 0.76rem;
            color: var(--text-primary);
            margin-bottom: 6px;
        }
        .wo-condition-text {
            font-size: 0.76rem;
            color: var(--text-primary);
        }

        .wo-complaint-text {
            font-size: 0.82rem;
            line-height: 1.55;
            color: var(--text-primary);
        }

        .wo-cost-box {
            background: #eff4fb;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 10px;
            text-align: center;
            margin-bottom: 10px;
        }
        .wo-cost-label {
            font-size: 0.66rem;
            font-weight: 800;
            color: var(--navy);
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .wo-cost-value {
            font-size: 1.45rem;
            font-weight: 800;
            color: #000;
            margin-top: 4px;
        }
        .wo-cost-grid {
            display: flex;
            gap: 14px;
        }
        .wo-cost-grid > div {
            flex: 1;
        }
        .wo-cost-status {
            font-size: 1rem;
            font-weight: 800;
            color: var(--primary);
            margin-top: 4px;
            text-transform: uppercase;
        }

        .wo-footer {
            border-top: 2px solid var(--navy);
            padding-top: 8px;
            text-align: center;
            font-size: 0.72rem;
            color: var(--text-secondary);
        }

        .wo-shop-internal {
            border: 2px dashed var(--navy);
        }
        .wo-shop-internal .wo-section-header {
            background: var(--primary);
        }

        /* Explicit physical dimensions (148mm x 210mm = A5 portrait) rather than
           the named "A5" keyword — some printer drivers don't map the CSS
           keyword to their own media list correctly and silently fall back
           to their default size (usually A4/Letter). Explicit mm values are
           matched far more reliably across different printer drivers. */
        @page {
            size: 148mm 210mm;
            margin: 10mm;
        }

        /* Force the browser to print background colors (navy section headers,
           the light-blue cost box, etc.) even when the person printing hasn't
           manually ticked "Background graphics" in the print dialog — browsers
           strip background colors from printed output by default to save ink,
           this overrides that default for this page specifically. */
        html {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            color-adjust: exact;
        }

        @media print {
            html, body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            body {
                background: none !important;
                padding: 0 !important;
            }
            .print-control-bar {
                display: none !important;
            }
            .print-a5-note {
                display: none !important;
            }
            .print-paper {
                box-shadow: none !important;
                padding: 0 !important;
                border-radius: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body>

    <!-- Control Bar (hidden during print) -->
    <div class="print-control-bar no-print">
        <div style="display:flex; align-items:center; gap:12px;">
            <a href="workorder.php?action=view&id=<?php echo $id; ?>" class="btn btn-secondary btn-sm"><- Back to Ticket</a>
            <span class="print-copy-badge">
                <?php echo $type === 'shop' ? 'Shop Copy' : 'Customer Copy'; ?>
            </span>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="print.php?id=<?php echo $id; ?>&type=customer" class="btn btn-secondary btn-sm <?php echo $type === 'customer' ? 'btn-primary' : ''; ?>">Customer Layout</a>
            <a href="print.php?id=<?php echo $id; ?>&type=shop" class="btn btn-secondary btn-sm <?php echo $type === 'shop' ? 'btn-primary' : ''; ?>">Shop Layout</a>
            <button onclick="window.print()" class="btn btn-success btn-sm">🖨️ Print / Download PDF</button>
            <?php
                $wa_message = "Hello " . $order['customer_name'] . ", this is GADGETZ.LK.\n\n"
                    . "Here's the job sheet for your work order " . $order['work_order_no'] . " (" . $order['brand'] . " " . $order['model'] . "). "
                    . "Please find the attached PDF. Thank you!";
                $wa_link = buildWhatsAppLink($order['customer_phone'], $wa_message);
            ?>
            <?php if ($wa_link): ?>
                <a href="<?php echo htmlspecialchars($wa_link); ?>" class="btn btn-whatsapp btn-sm" target="_blank" title="1. Print / Download the PDF above first  2. This opens WhatsApp with the message ready  3. Attach the downloaded PDF before sending">📱 Send via WhatsApp</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- A5 paper-size reminder: printers don't always auto-select A5, this has to be a manual check -->
    <div class="print-a5-note no-print">
        📄 <strong>Printing on the physical printer?</strong> This sheet is designed for A5 paper. In the print dialog, open <strong>"More settings"</strong> and check that <strong>Paper size = A5</strong> and <strong>Scale = 100% (Default)</strong> before printing — printers sometimes default to A4/Letter, which can shrink or misalign the layout.
    </div>

    <!-- Print Sheet Paper Container -->
    <div class="print-paper">

        <!-- Letterhead accent bars -->
        <div class="letterhead-accent-thick"></div>
        <div class="letterhead-accent-thin"></div>

        <!-- Letterhead: Brand block + Work Order info table -->
        <div class="letterhead-grid">
            <div>
                <div class="letterhead-brand">
                    <img src="../images/logo_icon.png" class="letterhead-logo-icon" alt="GADGETZ.LK">
                    <div class="letterhead-wordmark">GADGETZ<span class="accent">.LK</span></div>
                </div>
                <div class="letterhead-tagline">Sales, Repair &amp; IT Services Center</div>

                <div class="letterhead-contact-row">
                    <svg class="letterhead-contact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <?php echo SHOP_PHONE_DISPLAY; ?>
                </div>
                <div class="letterhead-contact-row">
                    <svg class="letterhead-contact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    <?php echo SHOP_WEBSITE; ?>
                </div>
                <div class="letterhead-contact-row">
                    <svg class="letterhead-contact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span><?php echo SHOP_ADDRESS_LINE1; ?>, <?php echo SHOP_ADDRESS_LINE2; ?></span>
                </div>
            </div>

            <div>
                <div class="wo-title">Work Order / Job Sheet<?php echo $type === 'shop' ? '<br>(Shop Copy)' : ''; ?></div>
                <div class="wo-info-table">
                    <div class="wo-info-row">
                        <div class="wo-info-label">Work Order No.</div>
                        <div class="wo-info-value"><?php echo htmlspecialchars($order['work_order_no']); ?></div>
                    </div>
                    <div class="wo-info-row">
                        <div class="wo-info-label">Date Received</div>
                        <div class="wo-info-value"><?php echo date('M d, Y', strtotime($order['received_date'])); ?></div>
                    </div>
                    <div class="wo-info-row">
                        <div class="wo-info-label">Received By</div>
                        <div class="wo-info-value">Staff</div>
                    </div>
                    <div class="wo-info-row">
                        <div class="wo-info-label">Expected Date</div>
                        <div class="wo-info-value"><?php echo $order['completion_date'] ? date('M d, Y', strtotime($order['completion_date'])) : 'TBD'; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 1 & 2 -->
        <div class="wo-two-col" style="margin-bottom:10px;">
            <div class="wo-section">
                <div class="wo-section-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    1. Customer Details
                </div>
                <div class="wo-section-body">
                    <div class="wo-detail-row">
                        <span class="wo-detail-label">Customer Name</span>
                        <span class="wo-detail-value"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                    </div>
                    <div class="wo-detail-row">
                        <span class="wo-detail-label">Phone Number</span>
                        <span class="wo-detail-value"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                    </div>
                    <div class="wo-detail-row">
                        <span class="wo-detail-label">Email Address</span>
                        <span class="wo-detail-value"><?php echo htmlspecialchars($order['customer_email'] ?: 'N/A'); ?></span>
                    </div>
                    <div class="wo-detail-row">
                        <span class="wo-detail-label">Address</span>
                        <span class="wo-detail-value"><?php echo htmlspecialchars($order['customer_address'] ?: 'N/A'); ?></span>
                    </div>
                </div>
            </div>

            <div class="wo-section">
                <div class="wo-section-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="1"/><line x1="2" y1="20" x2="22" y2="20"/></svg>
                    2. Device Information
                </div>
                <div class="wo-section-body">
                    <div class="wo-detail-row">
                        <span class="wo-detail-label">Device Type</span>
                        <span class="wo-detail-value"><?php echo htmlspecialchars($order['device_type']); ?></span>
                    </div>
                    <div class="wo-detail-row">
                        <span class="wo-detail-label">Brand</span>
                        <span class="wo-detail-value"><?php echo htmlspecialchars($order['brand']); ?></span>
                    </div>
                    <div class="wo-detail-row">
                        <span class="wo-detail-label">Model</span>
                        <span class="wo-detail-value"><?php echo htmlspecialchars($order['model']); ?></span>
                    </div>
                    <div class="wo-detail-row">
                        <span class="wo-detail-label">Serial Number</span>
                        <span class="wo-detail-value"><?php echo htmlspecialchars($order['serial_number'] ?: 'N/A'); ?></span>
                    </div>
                    <div class="wo-detail-row">
                        <span class="wo-detail-label">Colour</span>
                        <span class="wo-detail-value"><?php echo htmlspecialchars($order['colour'] ?: 'N/A'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3 & 4 -->
        <div class="wo-two-col" style="margin-bottom:10px;">
            <div class="wo-section">
                <div class="wo-section-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                    3. Accessories Received
                </div>
                <div class="wo-section-body">
                    <?php $acc_options = ['Charger', 'Laptop Bag', 'Mouse', 'Adapter', 'Battery', 'Other']; ?>
                    <div class="wo-checkbox-grid">
                        <?php foreach ($acc_options as $opt): $is_checked = in_array($opt, $accessories); ?>
                            <div class="wo-checkbox-item">
                                <span class="wo-checkbox-box <?php echo $is_checked ? 'checked' : ''; ?>"><?php echo $is_checked ? '✓' : ''; ?></span>
                                <?php echo htmlspecialchars($opt === 'Other' ? 'Others' : $opt); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="wo-notes-line">
                        <strong>Notes:</strong><span class="fill-line"></span>
                    </div>
                    <?php if (count($accessories) === 0): ?>
                        <div class="wo-notes-value">No accessories received</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="wo-section">
                <div class="wo-section-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    4. Physical Condition
                </div>
                <div class="wo-section-body">
                    <div class="wo-condition-label">Condition at Received:</div>
                    <div class="wo-condition-text">
                        <?php echo count($conditions) > 0 ? htmlspecialchars(implode(', ', $conditions)) : 'No damages noted'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 5 -->
        <div class="wo-section" style="margin-bottom:10px;">
            <div class="wo-section-header">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                5. Customer Complaint / Issue Description
            </div>
            <div class="wo-section-body">
                <div class="wo-complaint-text"><?php echo nl2br(htmlspecialchars($order['complaint'])); ?></div>
            </div>
        </div>

        <!-- SHOP COPY SPECIFIC PORTION -->
        <?php if ($type === 'shop'): ?>

            <?php if (!empty($order['device_note'])): ?>
                <div class="wo-section" style="margin-bottom:10px;">
                    <div class="wo-section-header" style="background: var(--primary);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        Device Note / Internal Remarks
                    </div>
                    <div class="wo-section-body">
                        <div class="wo-complaint-text"><?php echo nl2br(htmlspecialchars($order['device_note'])); ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="wo-section wo-shop-internal" style="margin-bottom:10px;">
                <div class="wo-section-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    Technician Internal Diagnosis &amp; Notes
                </div>
                <div class="wo-section-body">
                    <?php if (count($notes) > 0): ?>
                        <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:12px;">
                            <?php foreach ($notes as $note): ?>
                                <div style="border-bottom:1px solid var(--border-color); padding-bottom:8px; font-size:0.78rem;">
                                    <div style="color:var(--text-secondary); margin-bottom:4px;">
                                        <strong>Logged:</strong> <?php echo date('M d, Y g:i A', strtotime($note['date'])); ?>
                                    </div>
                                    <div><strong>Notes:</strong> <?php echo htmlspecialchars($note['notes']); ?></div>
                                    <?php if (!empty($note['parts_used'])): ?>
                                        <div style="color:var(--primary); margin-top:2px;">
                                            <strong>Parts:</strong> <?php echo htmlspecialchars($note['parts_used']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="font-size:0.76rem; color:var(--text-secondary); margin-bottom:12px;">No active diagnostics logged yet. Write updates below on paper if needed.</p>
                    <?php endif; ?>
                    <div class="wo-notes-line">
                        <strong>Diagnostic Notes / Parts Used:</strong><span class="fill-line"></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Estimated Cost -->
        <div class="wo-cost-box">
            <?php if ($type === 'shop'): ?>
                <div class="wo-cost-grid">
                    <div>
                        <div class="wo-cost-label">Estimated Cost</div>
                        <div class="wo-cost-value">Rs. <?php echo formatMoney($order['estimated_cost']); ?></div>
                    </div>
                    <div>
                        <div class="wo-cost-label">Current Status</div>
                        <div class="wo-cost-status"><?php echo htmlspecialchars($order['status']); ?></div>
                    </div>
                </div>
                <?php if ($order['actual_cost'] > 0): ?>
                    <div style="border-top:1px solid var(--border-color); padding-top:10px; margin-top:10px; text-align:right;">
                        <strong>Actual Invoice Cost (Final Billing): <span style="color:var(--status-completed); font-size:1.1rem;">Rs. <?php echo formatMoney($order['actual_cost']); ?></span></strong>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="wo-cost-label">Estimated Cost</div>
                <div class="wo-cost-value">Rs. <?php echo formatMoney($order['estimated_cost']); ?></div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="wo-footer">
            Thank you for choosing GADGETZ.LK! We appreciate your business.<br>
            All repairs carry a 30-day warranty. Terms and conditions apply.
        </div>
    </div>
</body>
</html>
