<?php
require_once __DIR__ . '/../config.php';

// === CATEGORIES ===
function ensureMenuStockColumns(PDO $pdo): void {
    // Moved to db_update.php
}

function isInventoryTrackedCategory(PDO $pdo, int $categoryID): bool {
    ensureCategoryColumns($pdo);
    $stmt = $pdo->prepare("SELECT IsInventoryTracked FROM category WHERE CategoryID = ?");
    $stmt->execute([$categoryID]);
    return (bool) $stmt->fetchColumn();
}

function ensureCategoryColumns(PDO $pdo): void {
    // Moved to db_update.php
}

function getCategories(PDO $pdo): array {
    ensureCategoryColumns($pdo);
    $stmt = $pdo->query("SELECT * FROM category WHERE IsActive = 1 ORDER BY CategoryName ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllCategories(PDO $pdo): array {
    ensureCategoryColumns($pdo);
    $stmt = $pdo->query("SELECT * FROM category ORDER BY CategoryName ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addCategory(PDO $pdo, string $name, int $isInventoryTracked = 0): bool {
    ensureCategoryColumns($pdo);
    $name = ucwords(trim($name));
    if (empty($name)) return false;
    
    // Check for duplicates (case-insensitive)
    $check = $pdo->prepare("SELECT COUNT(*) FROM category WHERE LOWER(TRIM(CategoryName)) = LOWER(TRIM(?))");
    $check->execute([$name]);
    if ($check->fetchColumn() > 0) return false;
    
    $stmt = $pdo->prepare("INSERT INTO category (CategoryName, IsActive, IsInventoryTracked) VALUES (?, 1, ?)");
    return $stmt->execute([$name, $isInventoryTracked]);
}

function updateCategory(PDO $pdo, int $categoryID, string $name, int $isInventoryTracked = 0): bool {
    ensureCategoryColumns($pdo);
    $name = ucwords(trim($name));
    if (empty($name)) return false;
    
    // Check for duplicates (excluding current category, case-insensitive)
    $check = $pdo->prepare("SELECT COUNT(*) FROM category WHERE LOWER(TRIM(CategoryName)) = LOWER(TRIM(?)) AND CategoryID != ?");
    $check->execute([$name, $categoryID]);
    if ($check->fetchColumn() > 0) return false;

    $stmt = $pdo->prepare("UPDATE category SET CategoryName = ?, IsInventoryTracked = ? WHERE CategoryID = ?");
    return $stmt->execute([$name, $isInventoryTracked, $categoryID]);
}

function toggleCategoryAvailability(PDO $pdo, int $categoryID, int $status): array {
    ensureCategoryColumns($pdo);

    // If disabling a category, disable all items under it as well.
    if ((int) $status === 0) {
        $disableItems = $pdo->prepare("UPDATE menu_item SET IsAvailable = 0 WHERE CategoryID = ?");
        $disableItems->execute([$categoryID]);
    }

    $stmt = $pdo->prepare("UPDATE category SET IsActive = ? WHERE CategoryID = ?");
    if ($stmt->execute([(int) $status, $categoryID])) {
        if ((int) $status === 0) {
            return ['ok' => true, 'msg' => 'Category disabled successfully. All items under this category were also disabled.'];
        }
        return ['ok' => true, 'msg' => 'Category enabled successfully.'];
    }
    return ['ok' => false, 'msg' => 'Failed to update category status.'];
}

// === MENU ITEMS ===

function getMenuItems(PDO $pdo, ?int $categoryID = null, bool $onlyAvailable = false): array {
    ensureMenuStockColumns($pdo);
    $sql = "SELECT m.*, c.CategoryName, c.IsInventoryTracked 
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

function addMenuItem(PDO $pdo, array $data, ?string $imagePath = null): bool {
    ensureMenuStockColumns($pdo);
    $itemName = trim((string) ($data['ItemName'] ?? ''));
    if ($itemName === '') {
        return false;
    }

    // Eliminate duplicate items under the same category.
    $dup = $pdo->prepare("SELECT COUNT(*) FROM menu_item WHERE CategoryID = ? AND LOWER(TRIM(ItemName)) = LOWER(TRIM(?))");
    $dup->execute([(int) $data['CategoryID'], $itemName]);
    if ((int) $dup->fetchColumn() > 0) {
        return false;
    }

    $categoryID = (int) $data['CategoryID'];
    $isTracked = isInventoryTrackedCategory($pdo, $categoryID);
    $stockQty = $isTracked ? max(0, (float) ($data['StockQty'] ?? 0)) : 0;

    $sql = "INSERT INTO menu_item (CategoryID, ItemName, Price, IsAvailable, ImagePath, StockQty) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $categoryID,
        $itemName,
        $data['Price'],
        $data['IsAvailable'] ?? 1,
        $imagePath,
        $stockQty
    ]);
}

function updateMenuItem(PDO $pdo, int $itemID, array $data, ?string $imagePath = null): bool {
    ensureMenuStockColumns($pdo);
    $itemName = trim((string) ($data['ItemName'] ?? ''));
    if ($itemName === '') {
        return false;
    }

    // Eliminate duplicate items under the same category, excluding current item.
    $dup = $pdo->prepare("SELECT COUNT(*) FROM menu_item WHERE CategoryID = ? AND LOWER(TRIM(ItemName)) = LOWER(TRIM(?)) AND ItemID != ?");
    $dup->execute([(int) $data['CategoryID'], $itemName, $itemID]);
    if ((int) $dup->fetchColumn() > 0) {
        return false;
    }

    $categoryID = (int) $data['CategoryID'];
    $isTracked = isInventoryTrackedCategory($pdo, $categoryID);
    $stockQty = $isTracked ? max(0, (float) ($data['StockQty'] ?? 0)) : 0;

    if ($imagePath !== null) {
        $sql = "UPDATE menu_item SET CategoryID = ?, ItemName = ?, Price = ?, IsAvailable = ?, ImagePath = ?, StockQty = ? WHERE ItemID = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $categoryID,
            $itemName,
            $data['Price'],
            $data['IsAvailable'] ?? 1,
            $imagePath,
            $stockQty,
            $itemID
        ]);
    } else {
        $sql = "UPDATE menu_item SET CategoryID = ?, ItemName = ?, Price = ?, IsAvailable = ?, StockQty = ? WHERE ItemID = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $categoryID,
            $itemName,
            $data['Price'],
            $data['IsAvailable'] ?? 1,
            $stockQty,
            $itemID
        ]);
    }
}

function adjustMenuItemStock(PDO $pdo, int $itemID, float $delta): bool {
    ensureMenuStockColumns($pdo);
    $stmt = $pdo->prepare("UPDATE menu_item SET StockQty = GREATEST(0, StockQty + ?) WHERE ItemID = ?");
    return $stmt->execute([$delta, $itemID]);
}

function toggleItemAvailability(PDO $pdo, int $itemID, int $status): bool {
    $stmt = $pdo->prepare("UPDATE menu_item SET IsAvailable = ? WHERE ItemID = ?");
    return $stmt->execute([$status, $itemID]);
}

function getCategoryIdByName(PDO $pdo, string $name, int $isTracked): int {
    $name = ucwords(trim($name));
    if (empty($name)) {
        return 0;
    }
    
    // Check if exists
    $stmt = $pdo->prepare("SELECT CategoryID FROM category WHERE LOWER(TRIM(CategoryName)) = LOWER(TRIM(?))");
    $stmt->execute([$name]);
    $catId = $stmt->fetchColumn();
    
    if ($catId) {
        return (int) $catId;
    }
    
    // Create new
    $insert = $pdo->prepare("INSERT INTO category (CategoryName, IsActive, IsInventoryTracked) VALUES (?, 1, ?)");
    if ($insert->execute([$name, $isTracked])) {
        return (int) $pdo->lastInsertId();
    }
    
    return 0;
}



?>
