<?php
require __DIR__ . '/config.php';

try {
    // Add IsInventoryTracked to category table
    $pdo->exec("ALTER TABLE category ADD COLUMN IsInventoryTracked TINYINT(1) DEFAULT 0");
    echo "Added IsInventoryTracked to category.\n";
} catch (PDOException $e) {
    echo "Error adding IsInventoryTracked: " . $e->getMessage() . "\n";
}

try {
    // Add PaymentVerification to orders table
    $pdo->exec("ALTER TABLE orders ADD COLUMN PaymentVerification VARCHAR(50) DEFAULT 'Pending'");
    echo "Added PaymentVerification to orders.\n";
} catch (PDOException $e) {
    echo "Error adding PaymentVerification: " . $e->getMessage() . "\n";
}

echo "Done.";
?>
