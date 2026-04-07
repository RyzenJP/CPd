<?php
// Consolidated Layout File for Superadmin

// Session and Auth Check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check for Superadmin or Admin role
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'superadmin' && $_SESSION['role'] != 'admin')) {
    header("Location: ../index.php?error=Access Denied");
    exit();
}

/**
 * Render the HTML Header
 */
function renderHeader($page_title = '') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo !empty($page_title) ? $page_title . ' - ' : ''; ?>CPD-NIR Inventory System</title>
        <link rel="icon" type="image/jpeg" href="../assets/img/473022962_535116549689015_1606086345882705132_n.jpg">

        <!-- Bootstrap 5 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

        <!-- Bootstrap Icons -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

        <!-- DataTables CSS -->
        <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
        <link href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css" rel="stylesheet">

        <!-- Google Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <style>
            :root {
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 80px;
            --primary-color: #1a237e;
            --primary-bg: #e0f2fe;
            --secondary-color: #ffffff;
            --success-color: #10b981;
            --bg-light: #dde3ec;
        }

            body {
                font-family: 'Inter', sans-serif;
                background-color: var(--bg-light);
                overflow-x: hidden;
            }

            /* Sidebar */
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                width: var(--sidebar-width);
                background: #1a237e;
                border-right: 1px solid rgba(255,255,255,0.1);
                z-index: 1000;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                overflow: hidden;
            }

        /* Collapsed Sidebar Styles (Desktop Only) */
        @media (min-width: 1024px) {
            body.sidebar-collapsed #sidebar {
                width: 80px;
            }
            body.sidebar-collapsed #sidebar .sidebar-text,
            body.sidebar-collapsed #sidebar .nav-group-label,
            body.sidebar-collapsed #sidebar .sidebar-footer span,
            body.sidebar-collapsed #sidebar .sidebar-logo span,
            body.sidebar-collapsed #sidebar .nav-link span {
                display: none;
            }
            body.sidebar-collapsed #sidebar .sidebar-header {
                padding-left: 0;
                padding-right: 0;
                justify-content: center;
            }
            body.sidebar-collapsed #sidebar .nav-link {
                padding-left: 0;
                padding-right: 0;
                justify-content: center;
            }
            body.sidebar-collapsed #sidebar .nav-link i {
                margin-right: 0;
                font-size: 1.5rem;
            }
            body.sidebar-collapsed #main-navbar {
                left: 80px;
            }
            body.sidebar-collapsed .main-content {
                margin-left: 80px !important;
            }
        }

        @media (max-width: 1023px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content, #main-navbar {
                margin-left: 0 !important;
            }
        }

        /* Remove focus outline from toggle button */
        #sidebarToggle:focus {
            box-shadow: none;
        }

        .sidebar-header {
            height: 70px;
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background-color: transparent;
        }

        .sidebar-logo {
            font-weight: 700;
            font-size: 1.25rem;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .sidebar-menu {
            padding: 1.5rem 1rem;
            height: calc(100vh - 70px - 40px);
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .sidebar-menu::-webkit-scrollbar {
            display: none;
        }

        .sidebar-footer {
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            color: rgba(255,255,255,0.5);
            border-top: 1px solid rgba(255,255,255,0.1);
            background-color: transparent;
        }

        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
        }

        .nav-link:hover, .nav-link.active {
            background-color: #facc15;
            color: #1a237e;
        }

        .nav-link i {
            margin-right: 0.75rem;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .nav-group-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: rgba(255,255,255,0.5);
            margin: 1.5rem 0 0.5rem 1rem;
            font-weight: 700;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            padding-top: calc(80px + 2rem); /* Account for fixed navbar height (80px) */
            min-height: 100vh;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Navbar */
        .top-navbar {
            display: none; /* Hide old navbar class as we use new superadmin_navbar.php */
        }

        /* Cards */
        .card {
            border: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 0.75rem;
        }

        .card-header {
            background-color: #ffffff;
            border-bottom: 1px solid #f1f5f9;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }

        /* Mobile Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.5);
            z-index: 998;
            display: none;
        }
        .sidebar-overlay.active {
            display: block;
        }
        </style>
        <script>
            function toggleSidebar() {
                const sidebar = document.querySelector('.sidebar');
                const mainContent = document.querySelector('.main-content');
                const topNavbar = document.getElementById('main-navbar');
                const overlay = document.querySelector('.sidebar-overlay');
                const body = document.body;

                if (window.innerWidth >= 1024) {
                    // Desktop Toggle
                    body.classList.toggle('sidebar-collapsed');

                    // Save state
                    const isCollapsed = body.classList.contains('sidebar-collapsed');
                    localStorage.setItem('sidebarCollapsed', isCollapsed);
                } else {
                    // Mobile Toggle
                    if (sidebar) sidebar.classList.toggle('active');
                    if (overlay) overlay.classList.toggle('active');
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                const sidebarToggle = document.getElementById('sidebarToggle');
                if (sidebarToggle) {
                    sidebarToggle.addEventListener('click', toggleSidebar);
                }

                // Restore state on load
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                if (isCollapsed && window.innerWidth >= 1024) {
                    document.body.classList.add('sidebar-collapsed');
                }
            });
        </script>
    </head>
    <body>
    <?php
}

/**
 * Render the Sidebar
 */
function renderSidebar() {
    global $conn;
    if (!isset($conn)) {
        include_once '../plugins/conn.php';
    }
    $sidebar_notif_count = 0;
    if ($conn) {
        $res = $conn->query("SELECT COUNT(*) as pending_count FROM requests WHERE status = 'Pending' AND is_read = 0");
        if ($res) {
            $row = $res->fetch_assoc();
            $sidebar_notif_count = (int)$row['pending_count'];
        }
    }
    ?>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="h-20 flex items-center px-6 border-b border-white/10 bg-black/10 sidebar-header transition-all">
            <a href="superadmin_dashboard" class="flex items-center gap-3 no-underline">
                <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-lg overflow-hidden shrink-0">
                    <img src="../assets/img/logo no bg.png" alt="Logo" class="w-full h-full object-contain">
                </div>
                <div class="flex flex-col sidebar-text">
                    <span class="text-white font-bold text-lg leading-tight tracking-tight">CPD-NIR</span>
                    <span class="text-white/60 text-[10px] font-medium uppercase tracking-[0.2em]">Inventory System</span>
                </div>
            </a>
        </div>

        <div class="sidebar-menu">
            <div class="nav-group-label">Main</div>
            <a href="superadmin_dashboard" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'superadmin_dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
            </a>

            <div class="nav-group-label">Inventory Management</div>
            <a href="inventory_list" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventory_list.php' ? 'active' : ''; ?>">
                <i class="bi bi-box"></i> <span>All Items</span>
            </a>

            <div class="nav-group-label">Requests</div>
            <a href="request" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'request.php' || basename($_SERVER['PHP_SELF']) == 'view_request.php') ? 'active' : ''; ?>">
                <i class="bi bi-folder2-open"></i> <span>Manage Requests</span>
                <?php if ($sidebar_notif_count > 0): ?>
                    <span class="ml-auto w-6 h-6 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white shadow-sm">
                        <?php echo $sidebar_notif_count > 9 ? '9+' : $sidebar_notif_count; ?>
                    </span>
                <?php endif; ?>
            </a>

            <div class="nav-group-label">Reports & Disposal</div>
            <a href="./reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <i class="bi bi-printer"></i> <span>Generate Reports</span>
            </a>
            <a href="./physical_count.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'physical_count.php' ? 'active' : ''; ?>">
                <i class="bi bi-clipboard-check"></i> <span>Physical Count</span>
            </a>
            <a href="./transfers.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'transfers.php' ? 'active' : ''; ?>">
                <i class="bi bi-arrow-left-right"></i> <span>Transfers (PTR)</span>
            </a>
            <a href="./disposals.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'disposals.php' ? 'active' : ''; ?>">
                <i class="bi bi-trash"></i> <span>Disposals (IIRUP)</span>
            </a>
            <a href="./lost_properties.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'lost_properties.php' ? 'active' : ''; ?>">
                <i class="bi bi-exclamation-triangle"></i> <span>Lost Properties</span>
            </a>

            <div class="nav-group-label">System</div>
            <a href="./manage_accounts.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_accounts.php' ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> <span>Manage Accounts</span>
            </a>

        </div>

        <div class="sidebar-footer">
            <span>CPD-NIR Inventory System Version 1.0</span>
        </div>
    </nav>
    <?php
}

/**
 * Render the Footer
 */
function renderFooter() {
    ?>
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // SweetAlert2 Session Notifications
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

    <!-- Custom Scripts -->
    <script>
        $(document).ready(function() {
            // Initialize DataTables globally if class exists, skip already-initialized tables
            if ($('.datatable').length > 0) {
                $('.datatable').each(function() {
                    if (!$.fn.DataTable.isDataTable(this)) {
                        $(this).DataTable({
                            responsive: true,
                            columnDefs: [
                                { orderable: false, targets: 'no-sort' }
                            ]
                        });
                    }
                });
            }
        });
    </script>

    </body>
    </html>
    <?php
}
?>
