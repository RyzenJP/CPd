<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit('Unauthorized');
}

include '../plugins/conn.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['backup_file'])) {
    $file = $_FILES['backup_file']['tmp_name'];
    
    if (file_exists($file)) {
        // Read the SQL file
        $sql = file_get_contents($file);
        
        // Disable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 0;");
        
        // Execute multi-query
        if ($conn->multi_query($sql)) {
            do {
                // Store first result set
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->next_result());
            
            // Re-enable foreign key checks
            $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
            
            header("Location: settings.php?msg=Database restored successfully.");
        } else {
            header("Location: settings.php?error=Failed to restore database: " . $conn->error);
        }
    } else {
        header("Location: settings.php?error=File not found.");
    }
} else {
    header("Location: settings.php");
}
exit();
?>