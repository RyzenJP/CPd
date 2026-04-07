<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../index.php");
    exit();
}

$staff_pending_ris_count = 0;

if (!isset($conn)) {
    require_once '../plugins/conn.php';
}

$user_id = $_SESSION['user_id'];

if ($stmt = $conn->prepare("SELECT CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) AS full_name FROM users WHERE id = ? LIMIT 1")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $staff_full_name = $row['full_name'];
        if ($staff_full_name !== null && $staff_full_name !== '') {
            if ($stmt2 = $conn->prepare("SELECT COUNT(*) AS cnt FROM requests WHERE status = 'Pending' AND requested_by = ?")) {
                $stmt2->bind_param("s", $staff_full_name);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                if ($res2 && $r2 = $res2->fetch_assoc()) {
                    $staff_pending_ris_count = (int)$r2['cnt'];
                }
                $stmt2->close();
            }
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>CPD-NIR Inventory System</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#1a237e', // Deep Blue
                            light: '#283593',
                            dark: '#121858',
                        },
                        secondary: {
                            DEFAULT: '#b71c1c', // Crimson Red
                            light: '#d32f2f',
                            dark: '#7f0000',
                        },
                        accent: {
                            DEFAULT: '#facc15', // Bright Gold
                        },
                        'bg-light': '#dde3ec', // Grey Blue
                    }
                }
            }
        }
    </script>

    <!-- Icons and Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap JS for Modals/Functionality -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #dde3ec;
        }
        /* Sidebar Item Styles matching Superadmin */
        .sidebar-item {
            color: rgba(255, 255, 255, 0.8);
            margin: 0 1rem 0.25rem 1rem; /* mx-4 mb-1 */
            padding: 0.75rem 1rem; /* py-3 px-4 */
            border-radius: 0.5rem; /* rounded-lg */
            transition: all 0.2s;
        }
        .sidebar-active {
            background-color: #facc15 !important; /* Accent background */
            color: #1a237e !important; /* Primary text */
            font-weight: 600;
        }
        .sidebar-item:hover {
            background-color: #facc15 !important;
            color: #1a237e !important;
        }
        .sidebar-active i, .sidebar-item:hover i {
            color: #1a237e !important;
        }

        /* Hide scrollbar for Chrome, Safari and Opera */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        /* Hide scrollbar for IE, Edge and Firefox */
        .no-scrollbar {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }

        /* Collapsed Sidebar Styles (Desktop Only) */
        @media (min-width: 1024px) {
            body.sidebar-collapsed #sidebar {
                width: 80px;
            }
            body.sidebar-collapsed #sidebar .sidebar-text,
            body.sidebar-collapsed #sidebar .sidebar-footer-text,
            body.sidebar-collapsed #sidebar .sidebar-section-header {
                display: none;
            }
            body.sidebar-collapsed #sidebar .sidebar-header {
                padding-left: 0;
                padding-right: 0;
                justify-content: center;
            }
            body.sidebar-collapsed #sidebar .sidebar-item {
                padding-left: 0;
                padding-right: 0;
                justify-content: center;
            }
            body.sidebar-collapsed #main-navbar {
                left: 80px;
            }
            /* Override main content margin */
            body.sidebar-collapsed .lg\:ml-\[260px\] {
                margin-left: 80px !important;
            }
        }
    </style>
</head>
<body class="bg-bg-light">

<!-- Mobile Sidebar Overlay -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-[1035] hidden lg:hidden" onclick="toggleSidebar()"></div>

<!-- Sidebar Container -->
<nav id="sidebar" class="fixed top-0 left-0 h-screen w-[260px] bg-primary z-[1040] transition-all duration-300 lg:translate-x-0 -translate-x-full">
    <!-- Sidebar Header -->
    <div class="h-20 flex items-center px-6 border-b border-white/10 bg-black/10 sidebar-header transition-all">
        <a href="homepage.php" class="flex items-center gap-3 no-underline">
            <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-lg overflow-hidden shrink-0">
                <img src="../assets/img/logo no bg.png" alt="Logo" class="w-full h-full object-contain">
            </div>
            <div class="flex flex-col sidebar-text">
                <span class="text-white font-bold text-lg leading-tight tracking-tight">CPD-NIR</span>
                <span class="text-white/60 text-[10px] font-medium uppercase tracking-[0.2em]">Inventory System</span>
            </div>
        </a>
    </div>

    <!-- Sidebar Menu -->
    <div class="flex flex-col py-6 overflow-y-auto h-[calc(100vh-140px)] no-scrollbar">
        <div class="px-6 mb-2 sidebar-section-header">
            <span class="text-white/40 text-[10px] font-bold uppercase tracking-[0.2em] px-2">Core</span>
        </div>

        <a href="homepage.php" class="sidebar-item flex items-center gap-3 no-underline <?php echo basename($_SERVER['PHP_SELF']) == 'homepage.php' ? 'sidebar-active' : ''; ?>">
            <i class="bi bi-speedometer2 text-lg"></i>
            <span class="font-semibold text-sm sidebar-text">Dashboard</span>
        </a>

        <a href="inventory_list.php" class="sidebar-item flex items-center gap-3 no-underline <?php echo basename($_SERVER['PHP_SELF']) == 'inventory_list.php' ? 'sidebar-active' : ''; ?>">
            <i class="bi bi-box-seam text-lg"></i>
            <span class="font-semibold text-sm sidebar-text">Inventory</span>
        </a>

        <div class="px-6 mt-6 mb-2 sidebar-section-header">
            <span class="text-white/40 text-[10px] font-bold uppercase tracking-[0.2em] px-2">Transactions</span>
        </div>

        <a href="ris.php" class="sidebar-item flex items-center gap-3 no-underline <?php echo (basename($_SERVER['PHP_SELF']) == 'ris.php' || basename($_SERVER['PHP_SELF']) == 'create_ris.php') ? 'sidebar-active' : ''; ?>">
            <div class="relative">
                <i class="bi bi-file-earmark-text text-lg"></i>
                <?php if ($staff_pending_ris_count > 0): ?>
                    <span class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-accent text-primary text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-primary">
                        <?php echo $staff_pending_ris_count; ?>
                    </span>
                <?php endif; ?>
            </div>
            <span class="font-semibold text-sm sidebar-text">RIS Requests</span>
        </a>

        <div class="px-6 mt-6 mb-2 sidebar-section-header">
            <span class="text-white/40 text-[10px] font-bold uppercase tracking-[0.2em] px-2">Account</span>
        </div>

        <a href="help.php" class="sidebar-item flex items-center gap-3 no-underline <?php echo basename($_SERVER['PHP_SELF']) == 'help.php' ? 'sidebar-active' : ''; ?>">
            <i class="bi bi-question-circle text-lg"></i>
            <span class="font-semibold text-sm sidebar-text">Help Center</span>
        </a>
    </div>

    <!-- Sidebar Footer -->
    <div class="h-[60px] flex items-center px-8 border-t border-white/10 bg-black/5 transition-all justify-center sidebar-footer">
        <span class="text-white/30 text-[10px] font-medium tracking-wider sidebar-footer-text">CPD-NIR System v1.0</span>
    </div>
</nav>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const body = document.body;

    // Check if desktop or mobile
    if (window.innerWidth >= 1024) {
        // Desktop Toggle
        body.classList.toggle('sidebar-collapsed');

        // Save state
        const isCollapsed = body.classList.contains('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    } else {
        // Mobile Toggle
        if (sidebar.classList.contains('-translate-x-full')) {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
        } else {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        }
    }
}

// Restore state on load
document.addEventListener('DOMContentLoaded', function() {
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed && window.innerWidth >= 1024) {
        document.body.classList.add('sidebar-collapsed');
    }
});

<?php if (isset($_SESSION['success'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?php echo addslashes($_SESSION['success']); ?>',
            confirmButtonColor: '#1a237e'
        });
    });
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: '<?php echo addslashes($_SESSION['error']); ?>',
            confirmButtonColor: '#d32f2f'
        });
    });
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['warning'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'warning',
            title: 'Warning!',
            text: '<?php echo addslashes($_SESSION['warning']); ?>',
            confirmButtonColor: '#facc15'
        });
    });
    <?php unset($_SESSION['warning']); ?>
<?php endif; ?>
</script>
