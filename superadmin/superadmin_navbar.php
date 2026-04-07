<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure database connection is available
if (!isset($conn)) {
    include_once '../plugins/conn.php';
}

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$current_user = null;
$navbar_profile_pic = null;

// Fetch current user details
if ($user_id > 0) {
    if ($stmt = $conn->prepare("SELECT id, CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) AS full_name, email, role, profile_picture FROM users WHERE id = ? LIMIT 1")) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows === 1) {
            $current_user = $result->fetch_assoc();
            if (!empty($current_user['profile_picture'])) {
                $navbar_profile_pic = '../assets/uploads/profile_pictures/' . $current_user['profile_picture'];
            }
        }
        $stmt->close();
    }
}

// Fetch Pending Requests Count (Only unread)
$notif_sql = "SELECT COUNT(*) as pending_count FROM requests WHERE status = 'Pending' AND is_read = 0";
$notif_res = $conn->query($notif_sql);
$notif_count = 0;
if ($notif_res) {
    $notif_row = $notif_res->fetch_assoc();
    $notif_count = $notif_row['pending_count'];
}

// Fetch latest 5 unread pending requests for dropdown
$latest_req_sql = "SELECT * FROM requests WHERE status = 'Pending' AND is_read = 0 ORDER BY created_at DESC LIMIT 5";
$latest_req_res = $conn->query($latest_req_sql);
?>

<nav id="main-navbar" class="fixed top-0 right-0 left-0 lg:left-[260px] h-20 bg-white/80 backdrop-blur-md border-b border-gray-100 z-40 px-4 md:px-8 flex items-center justify-between transition-all duration-300">
    <!-- Left Section: Mobile Menu & Title -->
    <div class="flex items-center gap-4">
        <button type="button" onclick="toggleSidebar()" aria-label="Toggle Sidebar"
                class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center text-gray-500 hover:bg-[#1a237e] hover:text-white transition-all duration-200">
            <i class="bi bi-list text-xl"></i>
        </button>

        <div>
            <h1 class="text-lg md:text-xl font-black text-[#1a237e] tracking-tight truncate max-w-[200px] md:max-w-none">
                <?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Admin Portal'; ?>
            </h1>
        </div>
    </div>

    <!-- Right Section: Actions -->
    <div class="flex items-center gap-3 md:gap-4">
        <!-- Notifications Dropdown -->
        <div class="relative" id="notif-dropdown-container">
            <button type="button" onclick="toggleDropdown('notif-dropdown')"
                    class="relative w-10 h-10 md:w-12 md:h-12 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-center text-gray-500 hover:bg-white hover:shadow-md hover:text-[#1a237e] transition-all duration-200 group">
                <i class="bi bi-bell text-lg md:text-xl group-hover:scale-110 transition-transform"></i>
                <?php if ($notif_count > 0): ?>
                    <span id="notif-badge" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white shadow-sm animate-pulse">
                        <?php echo $notif_count > 9 ? '9+' : $notif_count; ?>
                    </span>
                <?php endif; ?>
            </button>

            <!-- Dropdown Menu -->
            <div id="notif-dropdown" class="hidden absolute right-0 top-full mt-4 w-80 md:w-96 bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden z-50 transform origin-top-right transition-all duration-200">
                <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between bg-white sticky top-0 z-10">
                    <span class="text-sm font-black text-[#1a237e] uppercase tracking-wider">Pending Requests</span>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 bg-blue-50 text-[#1a237e] rounded-md text-[10px] font-bold uppercase tracking-widest">
                            <?php echo $notif_count; ?> New
                        </span>
                    </div>
                </div>

                <div class="max-h-[60vh] overflow-y-auto custom-scrollbar" id="notificationDropdownMenu">
                    <?php if ($latest_req_res->num_rows === 0): ?>
                        <div class="py-12 px-6 text-center">
                            <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3 text-gray-300">
                                <i class="bi bi-bell-slash text-2xl"></i>
                            </div>
                            <p class="text-sm font-bold text-gray-400">No pending requests</p>
                        </div>
                    <?php else: ?>
                        <?php while($req = $latest_req_res->fetch_assoc()): ?>
                            <a href="view_request.php?id=<?php echo $req['id']; ?>"
                               class="block w-full text-left p-4 hover:bg-gray-50 transition-colors border-b border-gray-50 last:border-0 group relative bg-blue-50/30">
                                <div class="flex gap-3">
                                    <div class="shrink-0 w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                        <i class="bi bi-file-earmark-text text-lg"></i>
                                    </div>
                                    <div class="flex-grow min-w-0">
                                        <div class="flex justify-between items-start mb-1">
                                            <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Request #<?php echo $req['id']; ?></span>
                                            <span class="text-[10px] font-bold text-gray-300 whitespace-nowrap ml-2"><?php echo date('M d, H:i', strtotime($req['created_at'])); ?></span>
                                        </div>
                                        <h4 class="text-sm font-bold text-[#1a237e] mb-0.5 truncate pr-4">
                                            From: <?php echo htmlspecialchars($req['requested_by']); ?>
                                        </h4>
                                        <p class="text-xs text-gray-500 line-clamp-2 leading-relaxed opacity-80">
                                            Purpose: <?php echo htmlspecialchars($req['purpose']); ?>
                                        </p>
                                    </div>
                                    <div class="absolute top-4 right-4 w-2 h-2 rounded-full bg-secondary notif-dot"></div>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>

                <div class="p-2 bg-gray-50 border-t border-gray-100">
                    <a href="request.php" class="block w-full py-2.5 text-center text-xs font-black text-[#1a237e] uppercase tracking-widest hover:bg-white hover:shadow-sm rounded-xl transition-all duration-200">
                        View All Requests
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
                    <div class="w-full h-full bg-[#1a237e] flex items-center justify-center text-white font-bold text-sm">
                        <?php echo strtoupper(substr($current_user['full_name'] ?? 'A', 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </button>

            <!-- Profile Menu -->
            <div id="profile-dropdown" class="hidden absolute right-0 top-full mt-4 w-64 bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden z-50 transform origin-top-right transition-all duration-200">
                <div class="p-6 bg-[#1a237e] text-white text-center relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/5 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                    <div class="absolute bottom-0 left-0 w-24 h-24 bg-accent/10 rounded-full -ml-12 -mb-12 blur-xl"></div>

                    <div class="relative z-10">
                        <div class="w-16 h-16 rounded-2xl bg-white/20 backdrop-blur-sm p-1 mx-auto mb-3">
                            <?php if ($navbar_profile_pic): ?>
                                <img src="<?php echo htmlspecialchars($navbar_profile_pic, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile" class="w-full h-full object-cover rounded-xl">
                            <?php else: ?>
                                <div class="w-full h-full bg-white/90 rounded-xl flex items-center justify-center text-[#1a237e] font-black text-2xl">
                                    <?php echo strtoupper(substr($current_user['full_name'] ?? 'A', 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h4 class="font-bold text-sm truncate"><?php echo htmlspecialchars($current_user['full_name'] ?? 'Admin'); ?></h4>
                        <p class="text-xs text-white/60 truncate"><?php echo htmlspecialchars($current_user['email'] ?? ''); ?></p>
                    </div>
                </div>

                <div class="p-2">
                    <a href="profile.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-50 text-gray-600 hover:text-[#1a237e] transition-all duration-200 group">
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

    // Check if click is inside dropdown or toggle button
    const isNotifClick = notifContainer && notifContainer.contains(event.target);
    const isProfileClick = profileContainer && profileContainer.contains(event.target);

    if (!isNotifClick) {
        document.getElementById('notif-dropdown')?.classList.add('hidden');
    }

    if (!isProfileClick) {
        document.getElementById('profile-dropdown')?.classList.add('hidden');
    }
});
</script>
