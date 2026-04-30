<?php
require 'config.php';

$migrations = [
    // 1. Add new columns to orders table
    "ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `OrderType` VARCHAR(20) DEFAULT 'Dine-in'",
    "ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `TableNumber` VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `DiscountID` INT DEFAULT NULL",
    "ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `DiscountAmount` DECIMAL(10,2) DEFAULT 0.00",

    // 2. Create discounts table
    "CREATE TABLE IF NOT EXISTS `discounts` (
        `DiscountID` INT NOT NULL AUTO_INCREMENT,
        `DiscountName` VARCHAR(100) NOT NULL,
        `DiscountType` ENUM('Percentage','Fixed') NOT NULL DEFAULT 'Percentage',
        `DiscountValue` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `IsActive` TINYINT(1) DEFAULT 1,
        PRIMARY KEY (`DiscountID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

    // 3. Seed with common discounts
    "INSERT IGNORE INTO `discounts` (`DiscountName`, `DiscountType`, `DiscountValue`, `IsActive`) VALUES
        ('Senior Citizen (20%)', 'Percentage', 20.00, 1),
        ('PWD (20%)', 'Percentage', 20.00, 1),
        ('Employee Discount (50%)', 'Percentage', 50.00, 1)",
];

$errors = [];
foreach ($migrations as $sql) {
    try {
        $pdo->exec($sql);
        echo "OK: " . substr($sql, 0, 60) . "...<br>";
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
        echo "ERROR: " . $e->getMessage() . "<br>";
    }
}

if (empty($errors)) {
    echo "<br><strong>All migrations applied successfully!</strong>";
} else {
    echo "<br><strong>Some migrations failed. See above.</strong>";
}
