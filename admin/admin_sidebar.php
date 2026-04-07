<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>CPD-NIR Inventory System</title>

    <!-- CSS -->
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css" rel="stylesheet">
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
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: #1a237e;
            border-right: 1px solid rgba(255,255,255,0.1);
            z-index: 1000;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        /* Desktop: collapse to icon-only strip */
        @media (min-width: 992px) {
            .sidebar.active {
                width: 80px;
            }
            .sidebar.active .sidebar-text,
            .sidebar.active .nav-link span,
            .sidebar.active .nav-group-label,
            .sidebar.active .sidebar-footer span {
                display: none !important;
            }
            .sidebar.active .sidebar-header {
                padding: 0;
                justify-content: center;
            }
            .sidebar.active .nav-link {
                justify-content: center;
                padding: 0.75rem 0;
                text-align: center;
            }
            .sidebar.active .nav-link i {
                margin-right: 0;
                font-size: 1.5rem;
            }
            .main-content.active {
                margin-left: 80px;
            }
            .top-navbar.active {
                left: 80px;
            }
        }

        /* Mobile: slide in/out */
        @media (max-width: 991.98px) {
            .sidebar {
                margin-left: -260px;
            }
            .sidebar.active {
                margin-left: 0;
            }
            .main-content {
                margin-left: 0 !important;
            }
            .top-navbar {
                left: 0 !important;
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
            height: calc(100vh - 110px);
            overflow-y: auto;
            position: relative !important;
            z-index: 100000 !important;
            pointer-events: auto !important;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        .sidebar-menu::-webkit-scrollbar {
            display: none; /* Chrome, Safari and Opera */
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
            display: flex !important;
            align-items: center;
            font-weight: 500;
            transition: all 0.2s;
            position: relative !important;
            z-index: 100001 !important;
            cursor: pointer !important;
            text-decoration: none !important;
            pointer-events: auto !important;
        }
        .nav-link:hover {
            background-color: #facc15;
            color: #1a237e;
        }
        .nav-link.active {
            background: #facc15;
            color: #1a237e;
            font-weight: 600;
        }
        .nav-link i {
            margin-right: 0.75rem;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        .nav-group-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            padding: 1rem 1rem 0.5rem 1rem;
            letter-spacing: 0.5px;
        }
        .top-navbar {
            position: fixed;
            top: 0;
            left: 260px;
            right: 0;
            height: 70px;
            background-color: rgba(255, 255, 255, 0.85);
            -webkit-backdrop-filter: blur(12px);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid #f3f4f6;
            z-index: 999;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            transition: left 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            padding-top: calc(70px + 2rem);
            background-color: var(--bg-light);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const topNavbar = document.querySelector('.top-navbar');

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    if (mainContent) mainContent.classList.toggle('active');
                    if (topNavbar) topNavbar.classList.toggle('active');
                });
            }
        });
    </script>
</head>
<body>

<nav class="sidebar" id="sidebar">
    <div class="h-20 flex items-center px-6 border-b border-white/10 bg-black/10 sidebar-header transition-all">
        <a href="admin_dashboard" class="flex items-center gap-3 no-underline">
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
        <a href="admin_dashboard" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
        </a>
        <div class="nav-group-label">Inventory</div>
        <a href="inventory_list" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventory_list.php' ? 'active' : ''; ?>">
            <i class="bi bi-box"></i> <span>All Items</span>
        </a>
        <div class="nav-group-label">Requests</div>
        <a href="requests" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'requests.php' ? 'active' : ''; ?>">
            <i class="bi bi-folder2-open"></i> <span>Manage Request</span>
        </a>
        <div class="nav-group-label">Reports</div>
        <a href="reports" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
            <i class="bi bi-printer"></i> <span>Reports Generator</span>
        </a>
        <div class="nav-group-label">System</div>
        <a href="../logout.php" class="nav-link text-danger">
            <i class="bi bi-box-arrow-right"></i> <span>Logout</span>
        </a>
    </div>
    <div class="sidebar-footer">
        <span>CPD-NIR Inventory System Version 1.0</span>
    </div>
</nav>

<?php
function renderHeader($title) {
    global $page_title;
    $page_title = $title;
    include 'admin_navbar.php';
}

function renderSidebar() {
    // Sidebar is already rendered above
}

function renderFooter() {
    ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Initialize DataTables
        document.addEventListener('DOMContentLoaded', function() {
            const datatables = document.querySelectorAll('.datatable');
            datatables.forEach(function(table) {
                if (!$.fn.DataTable.isDataTable(table)) {
                    $(table).DataTable({
                        responsive: true,
                        pageLength: 10,
                        order: [[0, 'desc']],
                        columnDefs: [
                            { orderable: false, targets: 'no-sort' }
                        ]
                    });
                }
            });
        });
    </script>
    </body>
    </html>
    <?php
}
?>
