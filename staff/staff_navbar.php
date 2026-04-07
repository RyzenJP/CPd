<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($conn)) {
    require_once '../plugins/conn.php';
}

$current_user = null;
$navbar_profile_pic = null;

$has_profile_picture_col = false;
$cols_res = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
if ($cols_res) {
    if ($cols_res->num_rows > 0) {
        $has_profile_picture_col = true;
    }
    $cols_res->free();
}

if ($stmt = $conn->prepare("SELECT id, CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) AS full_name, email, role FROM users WHERE id = ? LIMIT 1")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $current_user = $result->fetch_assoc();
    }
    $stmt->close();
}

if ($has_profile_picture_col) {
    if ($stmt_pp = $conn->prepare("SELECT profile_picture FROM users WHERE id = ? LIMIT 1")) {
        $stmt_pp->bind_param("i", $user_id);
        $stmt_pp->execute();
        $res_pp = $stmt_pp->get_result();
        if ($res_pp && $row_pp = $res_pp->fetch_assoc()) {
            if (!empty($row_pp['profile_picture'])) {
                $navbar_profile_pic = '../assets/uploads/profile_pictures/' . $row_pp['profile_picture'];
            }
        }
        $stmt_pp->close();
    }
}

if (!$current_user) {
    echo '<script>window.location.href="../logout.php";</script>';
    exit();
}

$notif_count = 0;
$latest_notifications = [];

if ($stmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND is_read = 0")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $notif_count = (int)$row['unread_count'];
    }
    $stmt->close();
}

if ($stmt = $conn->prepare("SELECT id, title, message, type, action_url, priority, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($result && $row = $result->fetch_assoc()) {
        $latest_notifications[] = $row;
    }
    $stmt->close();
}
?>

<nav id="main-navbar" class="fixed top-0 right-0 left-0 lg:left-[260px] h-20 bg-white border-b border-gray-100 z-40 px-4 md:px-8 flex items-center justify-between transition-all duration-300">
    <!-- Left Section: Mobile Menu & Title -->
    <div class="flex items-center gap-4">
        <button type="button" onclick="toggleSidebar()" aria-label="Toggle Sidebar"
                class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center text-gray-500 hover:bg-primary hover:text-white transition-all duration-200">
            <i class="bi bi-list text-xl"></i>
        </button>
        
        <div>
            <h1 class="text-lg md:text-xl font-black text-primary tracking-tight truncate max-w-[200px] md:max-w-none">
                <?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Staff Portal'; ?>
            </h1>
        </div>
    </div>

    <!-- Right Section: Actions -->
    <div class="flex items-center gap-3 md:gap-4">
        <!-- Notifications Dropdown -->
        <div class="relative" id="notif-dropdown-container">
            <button type="button" onclick="toggleDropdown('notif-dropdown')" 
                    class="relative w-10 h-10 md:w-12 md:h-12 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-center text-gray-500 hover:bg-white hover:shadow-md hover:text-primary transition-all duration-200 group">
                <i class="bi bi-bell text-lg md:text-xl group-hover:scale-110 transition-transform"></i>
                <?php if ($notif_count > 0): ?>
                    <span id="staff-notif-badge" class="absolute -top-1 -right-1 w-5 h-5 bg-secondary text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white shadow-sm animate-pulse">
                        <?php echo $notif_count > 9 ? '9+' : $notif_count; ?>
                    </span>
                <?php endif; ?>
            </button>

            <!-- Dropdown Menu -->
            <div id="notif-dropdown" class="hidden absolute right-0 top-full mt-4 w-80 md:w-96 bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden z-50 transform origin-top-right transition-all duration-200">
                <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between bg-white sticky top-0 z-10">
                    <span class="text-sm font-black text-primary uppercase tracking-wider">Notifications</span>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 bg-blue-50 text-primary rounded-md text-[10px] font-bold uppercase tracking-widest">
                            <?php echo $notif_count; ?> New
                        </span>
                        <?php if ($notif_count > 0): ?>
                            <button onclick="markAllStaffNotificationsRead(event)" class="text-[10px] font-bold text-gray-400 hover:text-primary uppercase tracking-widest transition-colors">
                                Mark Read
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="max-h-[60vh] overflow-y-auto custom-scrollbar" id="notificationDropdownMenu">
                    <?php if (count($latest_notifications) === 0): ?>
                        <div class="py-12 px-6 text-center">
                            <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3 text-gray-300">
                                <i class="bi bi-bell-slash text-2xl"></i>
                            </div>
                            <p class="text-sm font-bold text-gray-400">No notifications yet</p>
                            <p class="text-xs text-gray-400 mt-1">We'll alert you when something happens.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($latest_notifications as $notif): ?>
                            <?php 
                            $is_unread = (int)$notif['is_read'] === 0;
                            $type_styles = [
                                'request' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'icon' => 'bi-cart-check'],
                                'assignment' => ['bg' => 'bg-green-50', 'text' => 'text-green-600', 'icon' => 'bi-briefcase'],
                                'reminder' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'icon' => 'bi-alarm'],
                                'system' => ['bg' => 'bg-gray-50', 'text' => 'text-gray-600', 'icon' => 'bi-gear'],
                            ];
                            $style = $type_styles[$notif['type']] ?? $type_styles['system'];
                            $target_url = 'notifications.php';
                            ?>
                            <button onclick="handleStaffNotificationClick(event, <?php echo (int)$notif['id']; ?>, '<?php echo $target_url; ?>', <?php echo $is_unread ? 'true' : 'false'; ?>)" 
                                    class="w-full text-left p-4 hover:bg-gray-50 transition-colors border-b border-gray-50 last:border-0 group relative notif-item <?php echo $is_unread ? 'bg-blue-50/30 notif-item-unread' : 'notif-item-read'; ?>" 
                                    data-notification-id="<?php echo (int)$notif['id']; ?>">
                                <div class="flex gap-3">
                                    <div class="shrink-0 w-10 h-10 rounded-xl <?php echo $style['bg']; ?> <?php echo $style['text']; ?> flex items-center justify-center">
                                        <i class="bi <?php echo $style['icon']; ?> text-lg"></i>
                                    </div>
                                    <div class="flex-grow min-w-0">
                                        <div class="flex justify-between items-start mb-1">
                                            <span class="text-xs font-bold uppercase tracking-wider text-gray-400"><?php echo htmlspecialchars($notif['type']); ?></span>
                                            <span class="text-[10px] font-bold text-gray-300 whitespace-nowrap ml-2"><?php echo date('M d, H:i', strtotime($notif['created_at'])); ?></span>
                                        </div>
                                        <h4 class="text-sm font-bold text-primary mb-0.5 truncate pr-4 <?php echo $is_unread ? '' : 'font-medium opacity-80'; ?>">
                                            <?php echo htmlspecialchars($notif['title']); ?>
                                        </h4>
                                        <p class="text-xs text-gray-500 line-clamp-2 leading-relaxed opacity-80">
                                            <?php echo htmlspecialchars($notif['message']); ?>
                                        </p>
                                    </div>
                                    <?php if ($is_unread): ?>
                                        <div class="absolute top-4 right-4 w-2 h-2 rounded-full bg-secondary notif-dot"></div>
                                    <?php endif; ?>
                                </div>
                            </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="p-2 bg-gray-50 border-t border-gray-100">
                    <a href="notifications.php" class="block w-full py-2.5 text-center text-xs font-black text-primary uppercase tracking-widest hover:bg-white hover:shadow-sm rounded-xl transition-all duration-200">
                        View All Notifications
                    </a>
                </div>
            </div>
        </div>

        <!-- Profile Dropdown -->
        <div class="relative" id="profile-dropdown-container">
            <button type="button" onclick="toggleDropdown('profile-dropdown')" 
                    class="w-10 h-10 md:w-12 md:h-12 rounded-full overflow-hidden border-2 border-white shadow-sm hover:shadow-md transition-all duration-200 group">
                <?php if ($navbar_profile_pic): ?>
                    <img src="<?php echo htmlspecialchars($navbar_profile_pic, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile" class="w-full h-full object-cover">
                <?php else: ?>
                    <div class="w-full h-full bg-primary flex items-center justify-center text-white font-bold text-sm">
                        <?php echo strtoupper(substr($current_user['full_name'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </button>

            <!-- Profile Menu -->
            <div id="profile-dropdown" class="hidden absolute right-0 top-full mt-4 w-64 bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden z-50 transform origin-top-right transition-all duration-200">
                <div class="p-6 bg-primary text-white text-center relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/5 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                    <div class="absolute bottom-0 left-0 w-24 h-24 bg-accent/10 rounded-full -ml-12 -mb-12 blur-xl"></div>
                    
                    <div class="relative z-10">
                        <div class="w-16 h-16 rounded-2xl bg-white/20 backdrop-blur-sm p-1 mx-auto mb-3">
                            <?php if ($navbar_profile_pic): ?>
                                <img src="<?php echo htmlspecialchars($navbar_profile_pic, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile" class="w-full h-full object-cover rounded-xl">
                            <?php else: ?>
                                <div class="w-full h-full bg-white/90 rounded-xl flex items-center justify-center text-primary font-black text-2xl">
                                    <?php echo strtoupper(substr($current_user['full_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h4 class="font-bold text-sm truncate"><?php echo htmlspecialchars($current_user['full_name']); ?></h4>
                        <p class="text-xs text-white/60 truncate"><?php echo htmlspecialchars($current_user['email']); ?></p>
                    </div>
                </div>
                
                <div class="p-2">
                    <a href="profile.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-50 text-gray-600 hover:text-primary transition-all duration-200 group">
                        <div class="w-8 h-8 rounded-lg bg-gray-50 group-hover:bg-white group-hover:shadow-sm flex items-center justify-center transition-all">
                            <i class="bi bi-person-gear text-lg"></i>
                        </div>
                        <span class="text-sm font-bold">My Profile</span>
                    </a>

                    <div class="h-px bg-gray-50 my-1"></div>
                    <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-red-50 text-gray-600 hover:text-red-600 transition-all duration-200 group">
                        <div class="w-8 h-8 rounded-lg bg-gray-50 group-hover:bg-white group-hover:shadow-sm flex items-center justify-center transition-all">
                            <i class="bi bi-box-arrow-right text-lg"></i>
                        </div>
                        <span class="text-sm font-bold">Sign Out</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
// Toggle Sidebar Function is in staff_sidebar.php

// Dropdown Management
function toggleDropdown(id) {
    const dropdown = document.getElementById(id);
    const allDropdowns = ['notif-dropdown', 'profile-dropdown'];
    
    // Close other dropdowns
    allDropdowns.forEach(dId => {
        if (dId !== id) {
            document.getElementById(dId)?.classList.add('hidden');
        }
    });
    
    // Toggle current
    if (dropdown) {
        dropdown.classList.toggle('hidden');
    }
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    const notifContainer = document.getElementById('notif-dropdown-container');
    const profileContainer = document.getElementById('profile-dropdown-container');
    
    if (notifContainer && !notifContainer.contains(event.target)) {
        document.getElementById('notif-dropdown')?.classList.add('hidden');
    }
    
    if (profileContainer && !profileContainer.contains(event.target)) {
        document.getElementById('profile-dropdown')?.classList.add('hidden');
    }
});

function internalStaffNavigate(url) {
    if (!url) return;
    window.location.href = url;
}

function updateStaffNotifBadgeFromDom() {
    var container = document.getElementById('notificationDropdownMenu');
    var badge = document.getElementById('staff-notif-badge');
    if (!container) return;
    
    var unreadItems = container.querySelectorAll('.notif-item-unread');
    var unreadCount = unreadItems.length;
    
    if (unreadCount <= 0) {
        if (badge) badge.remove();
    } else {
        // Logic to update or create badge if missing (simplified for brevity as PHP handles initial render)
        if (badge) badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
    }
}

function handleStaffNotificationClick(event, id, url, isUnread) {
    if (event) event.preventDefault();
    
    if (isUnread) {
        // Optimistic UI update
        const btn = event.currentTarget;
        btn.classList.remove('bg-blue-50/30', 'notif-item-unread');
        btn.classList.add('notif-item-read');
        
        const dot = btn.querySelector('.notif-dot');
        if (dot) dot.remove();
        
        const title = btn.querySelector('h4');
        if (title) {
            title.classList.remove('text-primary'); // Remove bold color if needed
            title.classList.add('font-medium', 'opacity-80');
        }

        // Send API request
        fetch('notifications.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: id })
        }).catch(console.error);
        
        updateStaffNotifBadgeFromDom();
    }
    
    // Navigate
    setTimeout(() => {
        window.location.href = url;
    }, 100);
}

function markAllStaffNotificationsRead(event) {
    if (event) event.preventDefault();
    
    fetch('notifications.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'mark_all_read' })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        }
    })
    .catch(console.error);
}
</script>
