<?php
date_default_timezone_set('Asia/Manila');

$host = 'localhost';
$dbname = 'fishers_pond_mis'; // Kept original db name to maintain compatibility
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Auto DB Migrations Check
    try {
        // 1. Check ContactNumber type in employee table
        $checkEmp = $pdo->query("SHOW COLUMNS FROM employee LIKE 'ContactNumber'")->fetch();
        if ($checkEmp && strpos(strtolower($checkEmp['Type']), 'varchar') === false) {
            $pdo->exec("ALTER TABLE employee MODIFY COLUMN ContactNumber VARCHAR(20) NOT NULL");
        }

        // 2. Check IsInventoryTracked in category table
        $checkCatInv = $pdo->query("SHOW COLUMNS FROM category LIKE 'IsInventoryTracked'")->fetch();
        if (!$checkCatInv) {
            $pdo->exec("ALTER TABLE category ADD COLUMN IsInventoryTracked TINYINT(1) DEFAULT 0");
        }

        // 3. Check IsActive in category table
        $checkCatAct = $pdo->query("SHOW COLUMNS FROM category LIKE 'IsActive'")->fetch();
        if (!$checkCatAct) {
            $pdo->exec("ALTER TABLE category ADD COLUMN IsActive TINYINT(1) NOT NULL DEFAULT 1");
        }

        // 4. Check PaymentVerification in orders table
        $checkOrdVer = $pdo->query("SHOW COLUMNS FROM orders LIKE 'PaymentVerification'")->fetch();
        if (!$checkOrdVer) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN PaymentVerification VARCHAR(50) DEFAULT 'Pending'");
        }

        // 5. Check SpecialRequest in orders table
        $checkOrdReq = $pdo->query("SHOW COLUMNS FROM orders LIKE 'SpecialRequest'")->fetch();
        if (!$checkOrdReq) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN SpecialRequest VARCHAR(255) DEFAULT NULL");
        }

        // 6. Check StockQty in menu_item table
        $checkMenuStock = $pdo->query("SHOW COLUMNS FROM menu_item LIKE 'StockQty'")->fetch();
        if (!$checkMenuStock) {
            $pdo->exec("ALTER TABLE menu_item ADD COLUMN StockQty DECIMAL(10,2) NOT NULL DEFAULT 0");
        }
    } catch (PDOException $ex) {
        // Ignore silent migration errors to avoid blocking the app
    }
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>