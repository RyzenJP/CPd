<?php
session_start();
include 'plugins/conn.php';

$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = $_POST['password'] ?? '';

    if ($username === '') {
        $login_error = 'Username is required';
    } elseif ($password === '') {
        $login_error = 'Password is required';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $row = $result->fetch_assoc();
                if (password_verify($password, $row['password'])) {
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['role'] = $row['role'];

                    if ($row['role'] == 'superadmin') {
                        header("Location: superadmin/superadmin_dashboard.php");
                    } elseif ($row['role'] == 'admin') {
                        header("Location: admin/admin_dashboard.php");
                    } elseif ($row['role'] == 'staff' || $row['role'] == 'user') {
                        header("Location: staff/homepage.php");
                    } else {
                        $login_error = 'Access Denied: Invalid Role';
                    }
                    if ($login_error === '') {
                        exit();
                    }
                } else {
                    $login_error = 'Incorrect username or password';
                }
            } else {
                $login_error = 'Incorrect username or password';
            }
        } else {
            $login_error = 'Login error. Please contact the administrator.';
        }
    }
}

if ($login_error === '' && isset($_GET['error'])) {
    $login_error = $_GET['error'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPD-NIR Inventory System - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html, body {
            height: 100%;
            margin: 0;
        }
        :root {
            --ph-blue: #0033A0;
            --ph-blue-rgb: 0, 51, 160;
            --ph-red: #D1121F;
            --ph-red-rgb: 209, 18, 31;
            --ph-yellow: #FCD116;
            --ph-yellow-rgb: 252, 209, 22;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
        }
        @media (max-width: 1024px) {
            html, body {
                height: auto;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <div class="relative min-h-screen overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-white via-slate-50 to-white"></div>
        <div class="absolute inset-0 opacity-20" style="background-image: linear-gradient(rgba(148,163,184,.35) 1px, transparent 1px), linear-gradient(90deg, rgba(148,163,184,.35) 1px, transparent 1px); background-size: 56px 56px;"></div>
        <div class="absolute inset-0 opacity-80" style="background-image: radial-gradient(900px 520px at 80% 20%, rgba(var(--ph-red-rgb), .10), transparent 60%), radial-gradient(800px 480px at 15% 15%, rgba(var(--ph-blue-rgb), .10), transparent 60%), radial-gradient(900px 540px at 45% 95%, rgba(var(--ph-yellow-rgb), .14), transparent 60%);"></div>

        <div class="relative mx-auto flex min-h-screen max-w-6xl items-center px-6 py-12">
            <div class="grid w-full grid-cols-1 items-center gap-10 lg:grid-cols-2 lg:gap-12">
                <div>
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                            <img src="assets/img/logo no bg.png" alt="CPD-NIR Logo" class="h-7 w-7 object-contain">
                        </div>
                        <div class="leading-tight">
                            <div class="text-xs font-semibold text-slate-700">Commission on Population and Development</div>
                            <div class="text-[11px] font-medium text-slate-500">Negros Island Region</div>
                        </div>
                    </div>

                    <h1 class="mt-10 text-5xl font-black leading-[1.02] tracking-tight text-slate-900">
                        CPD-NIR Inventory<br class="hidden sm:block">System
                    </h1>
                    <p class="mt-4 max-w-xl text-sm leading-relaxed text-slate-600 sm:text-base">
                        Track inventory, process RIS requests, and generate government-standard reports with role-based access control.
                    </p>

                    <div class="mt-8 grid max-w-xl grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                            <div class="text-xs font-semibold text-slate-500">Inventory</div>
                            <div class="mt-1 text-sm font-extrabold text-slate-900">Balances &amp;<br>Movements</div>
                        </div>
                        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                            <div class="text-xs font-semibold text-slate-500">Requests</div>
                            <div class="mt-1 text-sm font-extrabold text-slate-900">RIS Workflow</div>
                        </div>
                        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                            <div class="text-xs font-semibold text-slate-500">Reports</div>
                            <div class="mt-1 text-sm font-extrabold text-slate-900">Standard Forms</div>
                        </div>
                    </div>

                    <div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs font-medium text-slate-500">
                        <div class="flex items-center gap-2">
                            <span class="inline-block h-1.5 w-1.5 rounded-full" style="background-color: var(--ph-blue);"></span>
                            Secure sessions
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block h-1.5 w-1.5 rounded-full" style="background-color: var(--ph-red);"></span>
                            Role-based access
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block h-1.5 w-1.5 rounded-full" style="background-color: var(--ph-yellow);"></span>
                            Audit-friendly logs
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-center lg:justify-end">
                    <div class="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-[0_18px_50px_rgba(15,23,42,.12)] ring-1 ring-slate-200">
                        <div class="h-2 w-full" style="background: linear-gradient(90deg, var(--ph-blue) 0%, var(--ph-blue) 34%, var(--ph-yellow) 34%, var(--ph-yellow) 38%, var(--ph-red) 38%, var(--ph-red) 100%);"></div>
                        <div class="p-7 sm:p-8">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Sign in</h2>
                                    <p class="mt-1 text-sm text-slate-500">Use your assigned account to continue.</p>
                                </div>
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white ring-1 ring-slate-200">
                                    <img src="assets/img/473022962_535116549689015_1606086345882705132_n.jpg" alt="CPD Logo" class="h-10 w-10 rounded-xl object-cover">
                                </div>
                            </div>

                            <?php if ($login_error !== ''): ?>
                            <div id="login-error" class="mt-6 flex items-start justify-between gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                <span class="leading-snug"><?php echo htmlspecialchars($login_error); ?></span>
                                <button type="button" class="rounded-xl px-2 py-1 text-red-500 hover:bg-red-100" onclick="document.getElementById('login-error').style.display='none';">×</button>
                            </div>
                            <?php endif; ?>

                            <form action="" method="POST" class="mt-6 space-y-4">
                                <div>
                                    <label for="username" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Username</label>
                                    <input
                                        type="text"
                                        id="username"
                                        name="username"
                                        required
                                        autocomplete="username"
                                        placeholder="Enter your username"
                                        class="mt-2 block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none transition focus:border-slate-300 focus:shadow-[0_0_0_4px_rgba(var(--ph-blue-rgb),.10)]"
                                    >
                                </div>

                                <div>
                                    <label for="password" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Password</label>
                                    <div class="relative mt-2">
                                        <input
                                            type="password"
                                            id="password"
                                            name="password"
                                            required
                                            autocomplete="current-password"
                                            placeholder="Enter your password"
                                            class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 pr-14 text-sm text-slate-900 placeholder:text-slate-400 outline-none transition focus:border-slate-300 focus:shadow-[0_0_0_4px_rgba(var(--ph-blue-rgb),.10)]"
                                        >
                                        <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 flex items-center justify-center px-4 text-[11px] font-bold text-slate-500 hover:text-slate-800">
                                            Show
                                        </button>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between gap-4">
                                    <label class="flex items-center gap-2 text-xs text-slate-600">
                                        <input type="checkbox" id="remember" class="h-4 w-4 rounded border-slate-300 bg-white text-[#0033A0] focus:ring-[#0033A0]/20">
                                        Remember me
                                    </label>
                                    <a href="#" class="text-xs font-bold text-[#0033A0] hover:text-[#002a82]">Forgot password?</a>
                                </div>

                                <button
                                    type="submit"
                                    class="w-full rounded-2xl px-4 py-3 text-sm font-extrabold text-white shadow-[0_12px_30px_rgba(var(--ph-blue-rgb),.20)] transition hover:brightness-[1.03] focus:outline-none focus:shadow-[0_0_0_4px_rgba(var(--ph-blue-rgb),.18)]"
                                    style="background: linear-gradient(90deg, var(--ph-blue) 0%, #1D4ED8 35%, var(--ph-red) 100%);"
                                >
                                    Sign In
                                </button>
                            </form>

                            <div class="mt-6 flex items-center justify-between text-xs text-slate-500">
                                <div>&copy; <?php echo date("Y"); ?> CPD-NIR</div>
                                <div class="text-slate-400">Inventory System v1.0</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const toggle = document.getElementById('togglePassword');
            const input = document.getElementById('password');
            if (!toggle || !input) return;
            toggle.addEventListener('click', function () {
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                toggle.textContent = isPassword ? 'Hide' : 'Show';
            });
        })();
    </script>
</body>
</html>
