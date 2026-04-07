<?php
session_start();
include '../plugins/conn.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'superadmin' && $_SESSION['role'] !== 'admin')) {
    header("Location: ../index.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Check if item has transactions or assignments
    $check = $conn->query("SELECT 
        (SELECT COUNT(*) FROM inventory_transactions WHERE item_id = $id) + 
        (SELECT COUNT(*) FROM assignments WHERE item_id = $id) as total_usage");
    $usage = $check->fetch_assoc()['total_usage'];

    $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header("Location: inventory_list.php?msg=deleted");
    } else {
        header("Location: inventory_list.php?error=delete_failed");
    }
} else {
    header("Location: inventory_list.php");
}
?>
