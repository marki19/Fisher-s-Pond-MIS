<?php
require __DIR__ . '/config.php';

try {
    // Add IsInventoryTracked to category table
    $pdo->exec("ALTER TABLE category ADD COLUMN IsInventoryTracked TINYINT(1) DEFAULT 0");
    echo "Added IsInventoryTracked to category.<br>\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false || strpos($e->getMessage(), '1060') !== false) {
        echo "Column IsInventoryTracked already exists in category.<br>\n";
    } else {
        echo "Error adding IsInventoryTracked: " . $e->getMessage() . "<br>\n";
    }
}

try {
    // Add PaymentVerification to orders table
    $pdo->exec("ALTER TABLE orders ADD COLUMN PaymentVerification VARCHAR(50) DEFAULT 'Pending'");
    echo "Added PaymentVerification to orders.<br>\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false || strpos($e->getMessage(), '1060') !== false) {
        echo "Column PaymentVerification already exists in orders.<br>\n";
    } else {
        echo "Error adding PaymentVerification: " . $e->getMessage() . "<br>\n";
    }
}

try {
    // Add StockQty to menu_item table
    $pdo->exec("ALTER TABLE menu_item ADD COLUMN StockQty DECIMAL(10,2) NOT NULL DEFAULT 0");
    echo "Added StockQty to menu_item.<br>\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false || strpos($e->getMessage(), '1060') !== false) {
        echo "Column StockQty already exists in menu_item.<br>\n";
    } else {
        echo "Error adding StockQty: " . $e->getMessage() . "<br>\n";
    }
}

try {
    // Add SpecialRequest to orders table
    $pdo->exec("ALTER TABLE orders ADD COLUMN SpecialRequest VARCHAR(255) DEFAULT NULL");
    echo "Added SpecialRequest to orders.<br>\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false || strpos($e->getMessage(), '1060') !== false) {
        echo "Column SpecialRequest already exists in orders.<br>\n";
    } else {
        echo "Error adding SpecialRequest: " . $e->getMessage() . "<br>\n";
    }
}

try {
    // Add IsActive to category table
    $pdo->exec("ALTER TABLE category ADD COLUMN IsActive TINYINT(1) NOT NULL DEFAULT 1");
    echo "Added IsActive to category.<br>\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false || strpos($e->getMessage(), '1060') !== false) {
        echo "Column IsActive already exists in category.<br>\n";
    } else {
        echo "Error adding IsActive: " . $e->getMessage() . "<br>\n";
    }
}

echo "Done.";
?>
