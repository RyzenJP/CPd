<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn)) {
    include_once '../plugins/conn.php';
}

$admin_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$admin_current_user = null;
$admin_navbar_profile_pic_url = null;
$admin_navbar_profile_pic_path = null;
$admin_user_columns = [];
$admin_cols_result = $conn->query('SHOW COLUMNS FROM users');
if ($admin_cols_result) {
    while ($admin_col = $admin_cols_result->fetch_assoc()) {
        $admin_user_columns[$admin_col['Field']] = true;
    }
    $admin_cols_result->free();
}

// Fetch current admin user details
if ($admin_user_id > 0) {
    $admin_name_parts = [];
    if (isset($admin_user_columns['first_name'])) {
        $admin_name_parts[] = 'first_name';
    }
    if (isset($admin_user_columns['middle_name'])) {
        $admin_name_parts[] = "NULLIF(middle_name, '')";
    }
    if (isset($admin_user_columns['last_name'])) {
        $admin_name_parts[] = 'last_name';
    }
    $admin_name_select = isset($admin_user_columns['full_name'])
        ? 'full_name'
        : (!empty($admin_name_parts) ? ('CONCAT_WS(\' \', ' . implode(', ', $admin_name_parts) . ') AS full_name') : (isset($admin_user_columns['username']) ? 'username AS full_name' : "'' AS full_name"));

    $admin_select_sql = 'SELECT id, ' . $admin_name_select . ', email, role'
        . (isset($admin_user_columns['profile_picture']) ? ', profile_picture' : '')
        . ' FROM users WHERE id = ? LIMIT 1';

    if ($stmt = $conn->prepare($admin_select_sql)) {
        $stmt->bind_param("i", $admin_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows === 1) {
            $admin_current_user = $result->fetch_assoc();
            if (!empty($admin_current_user['profile_picture'])) {
                $admin_profile_file = (string)$admin_current_user['profile_picture'];
                $admin_root_dir = dirname(__DIR__);
                $admin_candidates = [
                    [
                        'url' => '../assets/uploads/profile_pictures/' . $admin_profile_file,
                        'path' => $admin_root_dir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profile_pictures' . DIRECTORY_SEPARATOR . $admin_profile_file,
                    ],
                    [
                        'url' => '../uploads/profile_pics/' . $admin_profile_file,
                        'path' => $admin_root_dir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profile_pics' . DIRECTORY_SEPARATOR . $admin_profile_file,
                    ],
                ];
                foreach ($admin_candidates as $admin_candidate) {
                    if (file_exists($admin_candidate['path'])) {
                        $admin_navbar_profile_pic_url = $admin_candidate['url'];
                        $admin_navbar_profile_pic_path = $admin_candidate['path'];
                        break;
                    }
                }
            }
        }
        $stmt->close();
    }
}

// Fetch unread pending requests count
$admin_notif_count = 0;
$admin_notif_sql = "SELECT COUNT(*) as pending_count FROM requests WHERE status = 'Pending' AND is_read = 0";
$admin_notif_res = $conn->query($admin_notif_sql);
if ($admin_notif_res) {
    $admin_notif_row = $admin_notif_res->fetch_assoc();
    $admin_notif_count = (int)$admin_notif_row['pending_count'];
}

// Fetch latest 5 unread pending requests for dropdown
$admin_latest_req_sql = "SELECT * FROM requests WHERE status = 'Pending' AND is_read = 0 ORDER BY created_at DESC LIMIT 5";
$admin_latest_req_res = $conn->query($admin_latest_req_sql);
?>

<nav id="main-navbar" class="top-navbar bg-white/80 backdrop-blur-md border-b border-gray-100 px-4 md:px-8 flex items-center justify-between transition-all duration-300">

    <!-- Left: Toggle & Page Title -->
    <div class="flex items-center gap-4">
        <button type="button" id="sidebarToggle" aria-label="Toggle Sidebar"
                class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center text-gray-500 hover:bg-[#1a237e] hover:text-white transition-all duration-200 focus:outline-none">
            <i class="bi bi-list text-xl"></i>
        </button>

        <div>
            <h1 class="text-lg md:text-xl font-black text-[#1a237e] tracking-tight truncate max-w-[200px] md:max-w-none">
                <?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Admin Portal'; ?>
            </h1>
        </div>
    </div>

    <!-- Right: Notifications + Profile -->
    <div class="flex items-center gap-3 md:gap-4">

        <!-- ── Notifications ── -->
        <div class="relative" id="admin-notif-container">
            <button type="button" id="admin-notif-btn"
                    class="relative w-10 h-10 md:w-12 md:h-12 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-center text-gray-500 hover:bg-white hover:shadow-md hover:text-[#1a237e] transition-all duration-200 group focus:outline-none">
                <i class="bi bi-bell text-lg md:text-xl group-hover:scale-110 transition-transform"></i>
                <?php if ($admin_notif_count > 0): ?>
                    <span id="admin-notif-badge"
                          class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white shadow-sm animate-pulse">
                        <?php echo $admin_notif_count > 9 ? '9+' : $admin_notif_count; ?>
                    </span>
                <?php endif; ?>
            </button>

            <!-- Notification Panel -->
            <div id="admin-notif-dropdown"
                 class="hidden absolute right-0 top-full mt-4 w-80 md:w-96 bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden z-50 origin-top-right">

                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between bg-white sticky top-0 z-10">
                    <span class="text-sm font-black text-[#1a237e] uppercase tracking-wider">Pending Requests</span>
                    <span class="px-2 py-0.5 bg-blue-50 text-[#1a237e] rounded-md text-[10px] font-bold uppercase tracking-widest">
                        <?php echo $admin_notif_count; ?> New
                    </span>
                </div>

                <!-- Items -->
                <div class="max-h-[60vh] overflow-y-auto" style="scrollbar-width: none; -ms-overflow-style: none;">
                    <?php if ($admin_latest_req_res && $admin_latest_req_res->num_rows === 0): ?>
                        <div class="py-12 px-6 text-center">
                            <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="bi bi-bell-slash text-2xl text-gray-300"></i>
                            </div>
                            <p class="text-sm font-bold text-gray-400">No pending requests</p>
                        </div>
                    <?php elseif ($admin_latest_req_res): ?>
                        <?php while ($req = $admin_latest_req_res->fetch_assoc()): ?>
                            <a href="view_request.php?id=<?php echo (int)$req['id']; ?>"
                               style="text-decoration: none;"
                               class="block p-4 hover:bg-gray-50 transition-colors border-b border-gray-50 last:border-0 relative"
                               style="background: rgba(239,246,255,0.3);">
                                <div class="flex gap-3">
                                    <div class="shrink-0 w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                        <i class="bi bi-file-earmark-text text-lg"></i>
                                    </div>
                                    <div class="flex-grow min-w-0">
                                        <div class="flex justify-between items-start mb-1">
                                            <span class="text-xs font-bold uppercase tracking-wider text-gray-400">
                                                Request #<?php echo (int)$req['id']; ?>
                                            </span>
                                            <span class="text-[10px] font-bold text-gray-300 whitespace-nowrap ml-2">
                                                <?php echo date('M d, H:i', strtotime($req['created_at'])); ?>
                                            </span>
                                        </div>
                                        <h4 class="text-sm font-bold text-[#1a237e] mb-0.5 truncate pr-4">
                                            From: <?php echo htmlspecialchars($req['requested_by']); ?>
                                        </h4>
                                        <p class="text-xs text-gray-500 opacity-80"
                                           style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                                            Purpose: <?php echo htmlspecialchars($req['purpose'] ?? ''); ?>
                                        </p>
                                    </div>
                                    <div class="absolute top-4 right-4 w-2 h-2 rounded-full bg-red-500 shrink-0"></div>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>

                <!-- Footer -->
                <div class="p-2 bg-gray-50 border-t border-gray-100">
                    <a href="requests.php"
                       style="text-decoration: none;"
                       class="block w-full py-2.5 text-center text-xs font-black text-[#1a237e] uppercase tracking-widest hover:bg-white hover:shadow-sm rounded-xl transition-all duration-200">
                        View All Requests
                    </a>
                </div>
            </div>
        </div>

        <!-- ── Profile ── -->
        <div class="relative" id="admin-profile-container">
            <button type="button" id="admin-profile-btn"
                    class="w-10 h-10 md:w-12 md:h-12 rounded-full overflow-hidden border-2 border-white shadow-sm hover:shadow-md transition-all duration-200 focus:outline-none">
                <?php if ($admin_navbar_profile_pic_path && file_exists($admin_navbar_profile_pic_path)): ?>
                    <img src="<?php echo htmlspecialchars($admin_navbar_profile_pic_url, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="Profile" class="w-full h-full object-cover">
                <?php else: ?>
                    <div class="w-full h-full bg-[#1a237e] flex items-center justify-center text-white font-bold text-sm">
                        <?php echo strtoupper(substr($admin_current_user['full_name'] ?? 'A', 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </button>

            <!-- Profile Panel -->
            <div id="admin-profile-dropdown"
                 class="hidden absolute right-0 top-full mt-4 w-72 bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden z-50 origin-top-right">

                <!-- Blue Header -->
                <div class="p-6 bg-[#1a237e] text-white text-center relative overflow-hidden">
                    <!-- Decorative blobs -->
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/5 rounded-full -mr-16 -mt-16 blur-2xl pointer-events-none"></div>
                    <div class="absolute bottom-0 left-0 w-24 h-24 rounded-full -ml-12 -mb-12 blur-xl pointer-events-none"
                         style="background:rgba(250,204,21,0.10);"></div>

                    <div class="relative z-10">
                        <!-- Avatar -->
                        <div class="w-16 h-16 rounded-2xl bg-white/20 backdrop-blur-sm p-1 mx-auto mb-3">
                            <?php if ($admin_navbar_profile_pic_path && file_exists($admin_navbar_profile_pic_path)): ?>
                                <img src="<?php echo htmlspecialchars($admin_navbar_profile_pic_url, ENT_QUOTES, 'UTF-8'); ?>"
                                     alt="Profile" class="w-full h-full object-cover rounded-xl">
                            <?php else: ?>
                                <div class="w-full h-full bg-white/90 rounded-xl flex items-center justify-center text-[#1a237e] font-black text-2xl">
                                    <?php echo strtoupper(substr($admin_current_user['full_name'] ?? 'A', 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- Name & Email -->
                        <h4 class="font-bold text-sm truncate px-2">
                            <?php echo htmlspecialchars($admin_current_user['full_name'] ?? 'Admin'); ?>
                        </h4>
                        <p class="text-xs truncate px-2" style="color:rgba(255,255,255,0.6);">
                            <?php echo htmlspecialchars($admin_current_user['email'] ?? ''); ?>
                        </p>
                    </div>
                </div>

                <!-- Menu Links -->
                <div class="p-2">
                    <a href="profile.php"
                       style="text-decoration:none;"
                       class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:text-[#1a237e] hover:bg-gray-50 transition-all duration-200 group">
                        <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center group-hover:bg-white group-hover:shadow-sm transition-all">
                            <i class="bi bi-person-gear text-lg"></i>
                        </div>
                        <span class="text-sm font-bold">My Profile</span>
                    </a>

                    <a href="settings.php"
                       style="text-decoration:none;"
                       class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:text-[#1a237e] hover:bg-gray-50 transition-all duration-200 group">
                        <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center group-hover:bg-white group-hover:shadow-sm transition-all">
                            <i class="bi bi-gear text-lg"></i>
                        </div>
                        <span class="text-sm font-bold">Settings</span>
                    </a>

                    <div class="h-px bg-gray-100 my-1 mx-2"></div>

                    <a href="../logout.php"
                       style="text-decoration:none;"
                       class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:text-red-600 hover:bg-red-50 transition-all duration-200 group">
                        <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center group-hover:bg-white group-hover:shadow-sm transition-all">
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
(function () {
    // ── helpers ──────────────────────────────────────────────
    function getEl(id) { return document.getElementById(id); }

    function closeAll() {
        ['admin-notif-dropdown', 'admin-profile-dropdown'].forEach(function (id) {
            var el = getEl(id);
            if (el) el.classList.add('hidden');
        });
    }

    function togglePanel(panelId) {
        var panel = getEl(panelId);
        if (!panel) return;
        var isHidden = panel.classList.contains('hidden');
        closeAll();
        if (isHidden) panel.classList.remove('hidden');
    }

    // ── button wiring ─────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        var notifBtn   = getEl('admin-notif-btn');
        var profileBtn = getEl('admin-profile-btn');

        if (notifBtn)   notifBtn.addEventListener('click',   function (e) { e.stopPropagation(); togglePanel('admin-notif-dropdown'); });
        if (profileBtn) profileBtn.addEventListener('click', function (e) { e.stopPropagation(); togglePanel('admin-profile-dropdown'); });
    });

    // ── close on outside click ────────────────────────────────
    document.addEventListener('click', function (e) {
        var notifWrap   = getEl('admin-notif-container');
        var profileWrap = getEl('admin-profile-container');

        if (notifWrap   && !notifWrap.contains(e.target))   { var el = getEl('admin-notif-dropdown');   if (el) el.classList.add('hidden'); }
        if (profileWrap && !profileWrap.contains(e.target)) { var el = getEl('admin-profile-dropdown'); if (el) el.classList.add('hidden'); }
    });

    // ── close on Escape ───────────────────────────────────────
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAll();
    });
}());
</script>
