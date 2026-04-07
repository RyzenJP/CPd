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

    // Use a safer approach: Only delete if no critical history, otherwise maybe just mark as 'Condemned' or 'Archived'
    // But since the user asked for a system that works and I have CASCADE set up in SQL, I will rely on CASCADE 
    // BUT I should probably be careful.
    // For now, let's proceed with DELETE as requested by the "Delete" action.
    
    $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Item deleted successfully.';
        session_write_close();
        header("Location: inventory_list.php");
        exit();
    } else {
        $_SESSION['error'] = 'Failed to delete item.';
        session_write_close();
        header("Location: inventory_list.php");
        exit();
    }
} else {
    header("Location: inventory_list.php");
}
?>
