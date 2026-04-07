<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
include 'admin_sidebar.php';
$page_title = 'Notifications';
include 'admin_navbar.php';
include '../plugins/conn.php';

// Mark all as read
if (isset($_SESSION['user_id'])) {
    $update_stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $update_stmt->bind_param("i", $_SESSION['user_id']);
    $update_stmt->execute();
}
?>

<div class="main-content">
    <div class="container-fluid">
        <h2 class="mb-4 text-primary fw-bold">Notifications History</h2>
        
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
                    $stmt->bind_param("i", $_SESSION['user_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $bg_class = $row['is_read'] ? 'bg-white' : 'bg-light';
                            $icon_color = $row['type'] == 'status_update' ? 'text-success' : 'text-primary';
                            $icon = $row['type'] == 'status_update' ? 'bi-check-circle-fill' : 'bi-info-circle-fill';
                            
                            echo "<a href='" . ($row['link'] ?: '#') . "' class='list-group-item list-group-item-action p-3 $bg_class'>";
                            echo "<div class='d-flex w-100 justify-content-between align-items-center mb-1'>";
                            echo "  <div class='d-flex align-items-center'>";
                            echo "    <i class='bi $icon $icon_color fs-4 me-3'></i>";
                            echo "    <strong class='mb-1 text-dark'>" . htmlspecialchars($row['message']) . "</strong>";
                            echo "  </div>";
                            echo "  <small class='text-muted'>" . date('M d, Y h:i A', strtotime($row['created_at'])) . "</small>";
                            echo "</div>";
                            echo "</a>";
                        }
                    } else {
                        echo "<div class='p-5 text-center text-muted'>";
                        echo "<i class='bi bi-bell-slash fs-1 d-block mb-3'></i>";
                        echo "No notifications found.";
                        echo "</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'admin_footer.php'; ?>
