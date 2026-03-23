<?php
require_once __DIR__ . '/../config.php';

// === CATEGORIES ===

function getCategories(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM category ORDER BY CategoryName ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addCategory(PDO $pdo, string $name): bool {
    $name = trim($name);
    if (empty($name)) return false;
    $stmt = $pdo->prepare("INSERT INTO category (CategoryName) VALUES (?)");
    return $stmt->execute([$name]);
}

// === MENU ITEMS ===

function getMenuItems(PDO $pdo, ?int $categoryID = null, bool $onlyAvailable = false): array {
    $sql = "SELECT m.*, c.CategoryName 
            FROM menu_item m 
            JOIN category c ON m.CategoryID = c.CategoryID ";
    $params = [];
    $conditions = [];
    
    if ($categoryID !== null) {
        $conditions[] = "m.CategoryID = ?";
        $params[] = $categoryID;
    }
    
    if ($onlyAvailable) {
        $conditions[] = "m.IsAvailable = 1";
    }
    
    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY c.CategoryName ASC, m.ItemName ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMenuItem(PDO $pdo, int $itemID): ?array {
    $stmt = $pdo->prepare("SELECT * FROM menu_item WHERE ItemID = ?");
    $stmt->execute([$itemID]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function addMenuItem(PDO $pdo, array $data): bool {
    $sql = "INSERT INTO menu_item (CategoryID, ItemName, Price, IsAvailable) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $data['CategoryID'],
        trim($data['ItemName']),
        $data['Price'],
        $data['IsAvailable'] ?? 1
    ]);
}

function updateMenuItem(PDO $pdo, int $itemID, array $data): bool {
    $sql = "UPDATE menu_item SET CategoryID = ?, ItemName = ?, Price = ?, IsAvailable = ? WHERE ItemID = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $data['CategoryID'],
        trim($data['ItemName']),
        $data['Price'],
        $data['IsAvailable'] ?? 1,
        $itemID
    ]);
}

function toggleItemAvailability(PDO $pdo, int $itemID, int $status): bool {
    $stmt = $pdo->prepare("UPDATE menu_item SET IsAvailable = ? WHERE ItemID = ?");
    return $stmt->execute([$status, $itemID]);
}
?>
