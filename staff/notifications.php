<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    header("Location: ../index.php");
    exit();
}

require_once '../plugins/conn.php';

function staffNotifSendJson(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function staffNotifGetJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        staffNotifSendJson(400, [
            'success' => false,
            'message' => 'Invalid JSON payload',
        ]);
    }
    return $data;
}

$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        if (!($conn instanceof mysqli)) {
            staffNotifSendJson(500, [
                'success' => false,
                'message' => 'Database connection not available',
            ]);
        }
        $data = staffNotifGetJsonBody();
        if (isset($data['action']) && $data['action'] === 'mark_all_read') {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            if ($stmt === false) {
                staffNotifSendJson(500, [
                    'success' => false,
                    'message' => 'Database error preparing statement',
                ]);
            }
            $stmt->bind_param('i', $user_id);
            if ($stmt->execute()) {
                staffNotifSendJson(200, ['success' => true]);
            }
            staffNotifSendJson(500, [
                'success' => false,
                'message' => 'Database error while marking all as read',
            ]);
        }
        if (isset($data['id'])) {
            if (!($conn instanceof mysqli)) {
                staffNotifSendJson(500, [
                    'success' => false,
                    'message' => 'Database connection not available',
                ]);
            }
            $id = filter_var($data['id'], FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) {
                staffNotifSendJson(400, [
                    'success' => false,
                    'message' => 'Invalid notification identifier',
                ]);
            }
            $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
            if ($stmt === false) {
                staffNotifSendJson(500, [
                    'success' => false,
                    'message' => 'Database error preparing statement',
                ]);
            }
            $stmt->bind_param('ii', $id, $user_id);
            if ($stmt->execute()) {
                staffNotifSendJson(200, ['success' => true]);
            }
            staffNotifSendJson(500, [
                'success' => false,
                'message' => 'Database error while marking as read',
            ]);
        }
        staffNotifSendJson(400, [
            'success' => false,
            'message' => 'Invalid request payload',
        ]);
    }
    if (isset($_POST['delete_notification_id']) && $conn instanceof mysqli) {
        $deleteId = (int)$_POST['delete_notification_id'];
        if ($deleteId > 0) {
            if ($stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?")) {
                $stmt->bind_param('ii', $deleteId, $user_id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['success'] = "Notification deleted successfully.";
                header("Location: notifications.php");
                exit();
            }
        }
    }
}

$page_title = 'Notifications';

require_once 'staff_sidebar.php';
require_once 'staff_navbar.php';
$notifications = null;

if ($stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $notifications = $stmt->get_result();
    $stmt->close();
}

$stats = [
    'total' => 0,
    'unread' => 0,
    'request' => ['total' => 0, 'unread' => 0],
    'assignment' => ['total' => 0, 'unread' => 0],
    'system' => ['total' => 0, 'unread' => 0],
    'reminder' => ['total' => 0, 'unread' => 0],
];

if ($stmt_counts = $conn->prepare("SELECT type, is_read, COUNT(*) as count FROM notifications WHERE user_id = ? GROUP BY type, is_read")) {
    $stmt_counts->bind_param("i", $user_id);
    $stmt_counts->execute();
    $result_counts = $stmt_counts->get_result();
    while ($row = $result_counts->fetch_assoc()) {
        $stats['total'] += (int)$row['count'];
        if ((int)$row['is_read'] === 0) {
            $stats['unread'] += (int)$row['count'];
            if (isset($stats[$row['type']])) {
                $stats[$row['type']]['unread'] += (int)$row['count'];
            }
        }
        if (isset($stats[$row['type']])) {
            $stats[$row['type']]['total'] += (int)$row['count'];
        }
    }
    $stmt_counts->close();
}
?>

<div class="lg:ml-[260px] pt-20 min-h-screen bg-bg-light transition-all duration-300">
    <div class="p-4 md:p-8 max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-end gap-4 mb-8">
            <div class="flex items-center gap-3">
                <button type="button" 
                        onclick="markAllStaffCenterNotificationsRead()"
                        class="px-6 py-3 bg-white border border-gray-200 text-primary rounded-2xl font-bold text-sm hover:bg-gray-50 hover:shadow-sm transition-all duration-200 flex items-center group">
                    <i class="bi bi-check-all mr-2 text-lg group-hover:scale-110 transition-transform"></i>
                    Mark All Read
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
            <!-- Total Notifications -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 group hover:border-primary/20 transition-all duration-300">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center text-primary group-hover:scale-110 transition-transform duration-300">
                        <i class="bi bi-bell-fill text-xl"></i>
                    </div>
                    <span class="text-xs font-black text-primary/40 uppercase tracking-widest">Total</span>
                </div>
                <div class="text-2xl font-black text-primary tracking-tight"><?php echo number_format($stats['total']); ?></div>
                <div class="text-xs text-gray-400 font-bold uppercase mt-1">Notifications</div>
            </div>

            <!-- Unread Notifications -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 group hover:border-amber-200 transition-all duration-300">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 flex items-center justify-center text-amber-500 group-hover:scale-110 transition-transform duration-300">
                        <i class="bi bi-envelope-fill text-xl"></i>
                    </div>
                    <span class="text-xs font-black text-amber-500/40 uppercase tracking-widest">New</span>
                </div>
                <div class="text-2xl font-black text-amber-600 tracking-tight"><?php echo number_format($stats['unread']); ?></div>
                <div class="text-xs text-gray-400 font-bold uppercase mt-1">Unread Alerts</div>
            </div>

            <!-- Request Updates -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 group hover:border-blue-200 transition-all duration-300">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-500 group-hover:scale-110 transition-transform duration-300">
                        <i class="bi bi-cart-check-fill text-xl"></i>
                    </div>
                    <span class="text-xs font-black text-blue-500/40 uppercase tracking-widest">Requests</span>
                </div>
                <div class="text-2xl font-black text-primary tracking-tight">
                    <?php echo number_format($stats['request']['unread']); ?><span class="text-gray-300 mx-1">/</span><span class="text-gray-400 text-lg"><?php echo number_format($stats['request']['total']); ?></span>
                </div>
                <div class="text-xs text-gray-400 font-bold uppercase mt-1">Unread Requests</div>
            </div>

            <!-- Assignments -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 group hover:border-green-200 transition-all duration-300">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-12 h-12 rounded-2xl bg-green-50 flex items-center justify-center text-green-500 group-hover:scale-110 transition-transform duration-300">
                        <i class="bi bi-briefcase-fill text-xl"></i>
                    </div>
                    <span class="text-xs font-black text-green-500/40 uppercase tracking-widest">Work</span>
                </div>
                <div class="text-2xl font-black text-primary tracking-tight">
                    <?php echo number_format($stats['assignment']['unread']); ?><span class="text-gray-300 mx-1">/</span><span class="text-gray-400 text-lg"><?php echo number_format($stats['assignment']['total']); ?></span>
                </div>
                <div class="text-xs text-gray-400 font-bold uppercase mt-1">Unread Tasks</div>
            </div>
        </div>

        <!-- Notification List -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-8 py-6 border-b border-gray-50 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center text-gray-400">
                        <i class="bi bi-list-ul text-xl"></i>
                    </div>
                    <h3 class="text-lg font-black text-primary uppercase tracking-tight">Notification History</h3>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 bg-gray-100 text-gray-500 rounded-full text-xs font-bold uppercase tracking-widest">
                        <?php echo $notifications && $notifications->num_rows > 0 ? $notifications->num_rows : 0; ?> Total
                    </span>
                </div>
            </div>

            <div class="divide-y divide-gray-50">
                <?php if ($notifications && $notifications->num_rows > 0): ?>
                    <?php while ($notification = $notifications->fetch_assoc()): ?>
                        <?php
                        $type_config = [
                            'request' => ['icon' => 'bi-cart-check', 'bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'border' => 'border-blue-100'],
                            'assignment' => ['icon' => 'bi-briefcase', 'bg' => 'bg-green-50', 'text' => 'text-green-600', 'border' => 'border-green-100'],
                            'system' => ['icon' => 'bi-gear', 'bg' => 'bg-primary/5', 'text' => 'text-primary', 'border' => 'border-primary/10'],
                            'reminder' => ['icon' => 'bi-bell', 'bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'border' => 'border-amber-100'],
                        ];
                        $config = $type_config[$notification['type']] ?? $type_config['system'];
                        $is_unread = (int)$notification['is_read'] === 0;
                        ?>
                        <div class="notification-item group p-6 hover:bg-gray-50/50 transition-all duration-200 <?php echo $is_unread ? 'bg-blue-50/30' : ''; ?>" 
                             data-notification-id="<?php echo $notification['id']; ?>">
                            <div class="flex items-start gap-4">
                                <!-- Icon Column -->
                                <div class="shrink-0">
                                    <div class="w-12 h-12 rounded-2xl <?php echo $config['bg']; ?> <?php echo $config['text']; ?> flex items-center justify-center border <?php echo $config['border']; ?> shadow-sm group-hover:scale-110 transition-transform">
                                        <i class="bi <?php echo $config['icon']; ?> text-xl"></i>
                                    </div>
                                </div>

                                <!-- Content Column -->
                                <div class="flex-grow min-w-0">
                                    <div class="flex items-center justify-between gap-2 mb-1">
                                        <h4 class="text-sm md:text-base font-bold text-primary truncate <?php echo $is_unread ? 'pr-2' : ''; ?>">
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                        </h4>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <?php if ($is_unread): ?>
                                                <span class="px-2 py-0.5 bg-amber-500 text-white text-[10px] font-black uppercase tracking-widest rounded-full animate-pulse">New</span>
                                            <?php endif; ?>
                                            <span class="text-[10px] md:text-xs font-bold text-gray-400 uppercase tracking-wider">
                                                <?php echo date('M d, H:i', strtotime($notification['created_at'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <p class="text-sm <?php echo $is_unread ? 'text-gray-700 font-medium' : 'text-gray-500'; ?> leading-relaxed mb-3">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </p>
                                    
                                    <div class="flex items-center gap-3">
                                        <?php if ($is_unread): ?>
                                            <button onclick="markStaffNotificationRead(<?php echo $notification['id']; ?>)" 
                                                    class="text-xs font-black text-primary uppercase tracking-widest hover:text-secondary transition-colors flex items-center gap-1">
                                                <i class="bi bi-check2-circle text-lg"></i>
                                                Mark as Read
                                            </button>
                                        <?php else: ?>
                                            <span class="text-xs font-bold text-gray-400 uppercase tracking-widest flex items-center gap-1">
                                                <i class="bi bi-check2-all text-lg text-green-500"></i>
                                                Read
                                            </span>
                                        <?php endif; ?>
                                        
                                        <form method="POST" class="inline" onsubmit="return confirm('Delete this notification?')">
                                            <input type="hidden" name="delete_notification_id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" class="text-xs font-black text-red-400 uppercase tracking-widest hover:text-red-600 transition-colors flex items-center gap-1">
                                                <i class="bi bi-trash text-lg"></i>
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center py-20 px-4 text-center">
                        <div class="w-24 h-24 rounded-full bg-gray-50 flex items-center justify-center mb-6">
                            <i class="bi bi-bell-slash text-gray-200 text-5xl"></i>
                        </div>
                        <h3 class="text-xl font-black text-primary mb-2">YOU'RE ALL CAUGHT UP!</h3>
                        <p class="text-gray-500 max-w-xs mx-auto">There are no notifications to show right now. We'll alert you when something important happens.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function markStaffNotificationRead(id) {
    fetch('notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id: id })
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (!data || data.success !== true) {
            console.error('Failed to mark notification read', data && data.message ? data.message : '');
            return;
        }
        var item = document.querySelector('.notification-item[data-notification-id="' + id + '"]');
        if (item) {
            // Update UI to reflect read state
            item.classList.remove('bg-blue-50/30');
            
            // Remove 'New' badge
            var badge = item.querySelector('.animate-pulse');
            if (badge) badge.remove();
            
            // Update text styles
            var message = item.querySelector('p');
            if (message) {
                message.classList.remove('text-gray-700', 'font-medium');
                message.classList.add('text-gray-500');
            }
            
            // Update action area
            var actionArea = item.querySelector('.flex.items-center.gap-3');
            if (actionArea) {
                var markReadBtn = actionArea.querySelector('button[onclick*="markStaffNotificationRead"]');
                if (markReadBtn) {
                    var readStatus = document.createElement('span');
                    readStatus.className = 'text-xs font-bold text-gray-400 uppercase tracking-widest flex items-center gap-1';
                    readStatus.innerHTML = '<i class="bi bi-check2-all text-lg text-green-500"></i> Read';
                    markReadBtn.replaceWith(readStatus);
                }
            }
        }
    })
    .catch(function(error) {
        console.error('Error marking notification read', error);
    });
}

function markAllStaffCenterNotificationsRead() {
    fetch('notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ action: 'mark_all_read' })
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (!data || data.success !== true) {
            console.error('Failed to mark all notifications read', data && data.message ? data.message : '');
            return;
        }
        location.reload(); // Simplest way to update all items and stats
    })
    .catch(function(error) {
        console.error('Error marking all notifications read', error);
    });
}
</script>
