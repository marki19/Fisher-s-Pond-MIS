<?php
include "config.php";

if (isset($_FILES['image'])) {

    $file = $_FILES['image'];
    $customName = $_POST['custom_name'] ?? null;

    // check error
    if ($file['error'] !== 0) {
        die("Upload error.");
    }

    // validate server-side
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        die("Invalid image.");
    }

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($check['mime'], $allowed)) {
        die("Unsupported format.");
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        die("File too large (max 5MB).");
    }

    // filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

    if ($customName) {
        $safe = preg_replace("/[^a-zA-Z0-9_-]/", "_", $customName);
        $fileName = $safe . "_" . uniqid() . "." . $ext;
    } else {
        $fileName = uniqid() . "." . $ext;
    }

    $path = "uploads/" . $fileName;

    try {
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            throw new Exception("Upload failed.");
        }

        $stmt = $pdo->prepare("INSERT INTO tbl_files (name) VALUES (:name)");
        $stmt->execute(['name' => $fileName]);

        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        echo $e->getMessage();
    }
}
?>