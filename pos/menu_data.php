<?php
require_once __DIR__ . '/../config.php';

// === CATEGORIES ===

function getCategories(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM category ORDER BY CategoryName ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addCategory(PDO $pdo, string $name): bool {
    $name = ucwords(trim($name));
    if (empty($name)) return false;
    
    // Check for duplicates
    $check = $pdo->prepare("SELECT COUNT(*) FROM category WHERE CategoryName = ?");
    $check->execute([$name]);
    if ($check->fetchColumn() > 0) return false;
    
    $stmt = $pdo->prepare("INSERT INTO category (CategoryName) VALUES (?)");
    return $stmt->execute([$name]);
}

function updateCategory(PDO $pdo, int $categoryID, string $name): bool {
    $name = ucwords(trim($name));
    if (empty($name)) return false;
    
    // Check for duplicates (excluding current category)
    $check = $pdo->prepare("SELECT COUNT(*) FROM category WHERE CategoryName = ? AND CategoryID != ?");
    $check->execute([$name, $categoryID]);
    if ($check->fetchColumn() > 0) return false;

    $stmt = $pdo->prepare("UPDATE category SET CategoryName = ? WHERE CategoryID = ?");
    return $stmt->execute([$name, $categoryID]);
}

function deleteCategory(PDO $pdo, int $categoryID): array {
    $check = $pdo->prepare("SELECT COUNT(*) FROM menu_item WHERE CategoryID = ?");
    $check->execute([$categoryID]);
    if ($check->fetchColumn() > 0) {
        return ['ok' => false, 'msg' => 'Cannot delete: Category still contains items.'];
    }
    
    $stmt = $pdo->prepare("DELETE FROM category WHERE CategoryID = ?");
    if ($stmt->execute([$categoryID])) {
        return ['ok' => true, 'msg' => 'Category deleted successfully.'];
    }
    return ['ok' => false, 'msg' => 'Failed to delete category.'];
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
