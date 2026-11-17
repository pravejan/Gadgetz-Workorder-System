<?php
/**
 * Application Configuration & Database Connection
 * Auto-initializes the database from the SQL file if not already present.
 */

// Set secure session parameters before starting the session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    // session_start must be called in all pages requiring authentication
    session_start();
}

$host = 'localhost';
$db_user = 'root';
$db_pass = ''; // Default password for XAMPP is empty
$db_name = 'computer_repair_db';

try {
    // Connect to MySQL server first (without database to ensure it exists or can be created)
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db_name`");

    // Check if the 'users' table exists to verify database setup
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($tableCheck->rowCount() === 0) {
        $sqlFile = dirname(__DIR__) . '/database/database.sql';
        if (file_exists($sqlFile)) {
            $sqlContent = file_get_contents($sqlFile);
            // Execute the schema instructions
            $pdo->exec($sqlContent);
        } else {
            throw new Exception("Database schema file not found at: " . $sqlFile);
        }
    }

    // Migration: add completed_date column for existing installs created
    // before the "Mark as Completed" feature existed.
    $colCheck = $pdo->query("SHOW COLUMNS FROM work_orders LIKE 'completed_date'");
    if ($colCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE work_orders ADD COLUMN completed_date DATETIME DEFAULT NULL AFTER completion_date");
    }
} catch (Exception $e) {
    die("Database Initialization Error: " . $e->getMessage());
}

/**
 * Sanitize User Input
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate and format monetary values
 */
function formatMoney($value) {
    return number_format((float)$value, 2, '.', '');
}

/**
 * Generate formatted Work Order Number (e.g. WO-2026-0001)
 */
function generateWorkOrderNumber($pdo) {
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT work_order_no FROM work_orders WHERE work_order_no LIKE :prefix ORDER BY work_order_id DESC LIMIT 1");
    $stmt->execute(['prefix' => "WO-$year-%"]);
    $row = $stmt->fetch();

    if ($row) {
        // Extract the last number part
        $parts = explode('-', $row['work_order_no']);
        $lastNum = (int)end($parts);
        $nextNum = $lastNum + 1;
    } else {
        $nextNum = 1;
    }

    return "WO-" . $year . "-" . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

/**
 * Check if User is Logged In
 */
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Check if User has Admin permissions
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if User has Technician permissions
 */
function isTechnician() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'technician';
}

/**
 * Helper to display active sidebar link
 */
function activeClass($page) {
    $current = basename($_SERVER['PHP_SELF']);
    return ($current === $page) ? 'active' : '';
}

// The shop's own WhatsApp Business number (for reference/display only —
// WhatsApp click-to-chat links target the *customer's* number, the app
// opens using whichever WhatsApp account is logged in on the device).
define('SHOP_WHATSAPP_NUMBER', '+94 77 612 7170');

// Shop letterhead / branding details, used on the printable job sheet.
define('SHOP_PHONE_DISPLAY', '077 612 7170');
define('SHOP_WEBSITE', 'www.gadgetz.lk');
define('SHOP_ADDRESS_LINE1', '7/1A Alexandra Road');
define('SHOP_ADDRESS_LINE2', 'Colombo 06');

/**
 * Normalize a locally-stored phone number (e.g. "0771234567") into the
 * digits-only international format WhatsApp's click-to-chat links expect
 * (e.g. "94771234567"). Assumes Sri Lankan numbers by default.
 */
function formatWhatsAppNumber($phone) {
    $digits = preg_replace('/\D/', '', $phone ?? '');

    if ($digits === '') {
        return '';
    }
    if (substr($digits, 0, 3) === '940' && strlen($digits) === 12) {
        // e.g. 940771234567 (stray 0 after country code) -> strip it
        $digits = '94' . substr($digits, 3);
    }
    if (substr($digits, 0, 2) === '94' && strlen($digits) === 11) {
        return $digits; // already international, e.g. 94771234567
    }
    if (substr($digits, 0, 1) === '0' && strlen($digits) === 10) {
        return '94' . substr($digits, 1); // local format, e.g. 0771234567
    }
    if (strlen($digits) === 9) {
        return '94' . $digits; // missing leading 0, e.g. 771234567
    }

    // Fallback: return whatever digits we have; WhatsApp will simply fail
    // to resolve a chat if the number turns out to be invalid.
    return $digits;
}

/**
 * Build a WhatsApp click-to-chat link with a pre-filled message.
 * Returns null if the phone number can't be normalized at all.
 */
function buildWhatsAppLink($phone, $message) {
    $number = formatWhatsAppNumber($phone);
    if (strlen($number) < 11) {
        return null;
    }
    return 'https://wa.me/' . $number . '?text=' . rawurlencode($message);
}
?>
