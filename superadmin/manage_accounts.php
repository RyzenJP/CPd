<?php
require_once 'superadmin_sidebar.php';
include '../plugins/conn.php';
require_once __DIR__ . '/../plugins/mailer.php';

// Access control is already handled in superadmin_sidebar.php
// which allows both 'superadmin' and 'admin' roles.

$invite_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'generate_invite') {
        $role = $_POST['role'] ?? '';
        $email = $_POST['email'] ?? '';
        $full_name = $_POST['full_name'] ?? '';

        if (!$role) {
            $_SESSION['error'] = 'Please select a role before generating a link.';
        } elseif (!in_array($role, ['admin', 'staff', 'superadmin'])) {
            $_SESSION['error'] = 'Invalid role selected for invitation.';
        } else {
            $token = bin2hex(random_bytes(32));
            $expires_at = (new DateTime('+4 hours'))->format('Y-m-d H:i:s');

            $stmt = $conn->prepare('INSERT INTO invitations (token, email, full_name, role, expires_at) VALUES (?, ?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param('sssss', $token, $email, $full_name, $role, $expires_at);
                if ($stmt->execute()) {
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
                    $invite_url = $scheme . $host . $basePath . '/invite_register.php?token=' . urlencode($token);
                    $_SESSION['success'] = 'Invitation link generated successfully.';

                    if ($email !== '') {
                        $subject = 'CPD-NIR Account Registration Link';
                        $htmlBody = '<p>Good day' . ($full_name !== '' ? ' ' . htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') : '') . ',</p>'
                            . '<p>You have been invited to create an account in the CPD-NIR Inventory System.</p>'
                            . '<p><a href="' . htmlspecialchars($invite_url, ENT_QUOTES, 'UTF-8') . '">Click here to register your account</a></p>'
                            . '<p>This link will expire in 4 hours.</p>';

                        $plainBody = "Good day" . ($full_name !== '' ? " " . $full_name : '') . "\n\n"
                            . "You have been invited to create an account in the CPD-NIR Inventory System.\n"
                            . "Registration link: " . $invite_url . "\n"
                            . "This link will expire in 4 hours.";

                        $mailError = '';
                        $ok = send_app_email($email, $full_name, $subject, $htmlBody, $plainBody, $mailError);
                        if (!$ok && $mailError !== '') {
                            $_SESSION['error'] = 'Invitation link created, but email was not sent: ' . $mailError;
                        }
                    }
                    session_write_close();
                    header("Location: manage_accounts.php");
                    exit();
                } else {
                    $_SESSION['error'] = 'Error saving invitation link: ' . $stmt->error;
                    session_write_close();
                    header("Location: manage_accounts.php");
                    exit();
                }
                $stmt->close();
            } else {
                $_SESSION['error'] = 'Error preparing invitation link: ' . $conn->error;
                session_write_close();
                header("Location: manage_accounts.php");
                exit();
            }
        }
    } elseif ($action === 'create') {
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $role = isset($_POST['role']) ? trim($_POST['role']) : '';
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';
        
        $error_msg = '';
        $success_msg = '';

        if ($username === '' || $full_name === '' || $role === '' || $password === '' || $confirm_password === '') {
            $error_msg = 'Please fill in all required fields.';
        } elseif ($password !== $confirm_password) {
            $error_msg = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error_msg = 'Password must be at least 6 characters.';
        } elseif (!in_array($role, ['superadmin', 'admin', 'staff', 'user'], true)) {
            $error_msg = 'Invalid role selected.';
        } else {
            $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $error_msg = 'Username is already taken.';
                } else {
                    $nameParts = preg_split('/\s+/', $full_name);
                    $first_name = $nameParts[0] ?? $full_name;
                    $last_name = $nameParts[count($nameParts) - 1] ?? $full_name;
                    $middle_name = '';
                    if (count($nameParts) > 2) {
                        $middle_name = implode(' ', array_slice($nameParts, 1, -1));
                    }
                    $phone = '';
                    $address = '';

                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt_insert = $conn->prepare('INSERT INTO users (username, first_name, middle_name, last_name, password, role, email, phone, address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                    if ($stmt_insert) {
                        $stmt_insert->bind_param('ssssssssss', $username, $first_name, $middle_name, $last_name, $hashed_password, $role, $email, $phone, $address);
                        if ($stmt_insert->execute()) {
                            $success_msg = 'Account created successfully.';

                            if ($email !== '') {
                                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                                $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
                                $rootPath = rtrim(preg_replace('#/superadmin$#', '', $scriptDir), '/');
                                if ($rootPath === '') {
                                    $rootPath = '';
                                }
                                $loginUrl = $scheme . $host . $rootPath . '/index.php';
                                $logoFile = 'logo no bg.png';
                                $logoUrl = $scheme . $host . $rootPath . '/assets/img/' . rawurlencode($logoFile);

                                list($welcomeHtml, $welcomePlain) = build_account_welcome_email($username, $password, $role, $loginUrl, $logoUrl);
                                $mailErrorCreate = '';
                                send_app_email($email, $full_name, 'CPD-NIR Account Created', $welcomeHtml, $welcomePlain, $mailErrorCreate);
                            }
                        } else {
                            $error_msg = 'Error creating account.';
                        }
                        $stmt_insert->close();
                    } else {
                        $error_msg = 'Error preparing account creation.';
                    }
                }
                $stmt->close();
            } else {
                $error_msg = 'Error checking username.';
            }
        }
        
        if ($error_msg) {
            $_SESSION['error'] = $error_msg;
        } elseif ($success_msg) {
            $_SESSION['success'] = $success_msg;
        }
        session_write_close();
        header("Location: manage_accounts.php");
        exit();

    } elseif ($action === 'edit') {
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $role = isset($_POST['role']) ? trim($_POST['role']) : '';

        $error_msg = '';
        $success_msg = '';

        if ($user_id <= 0 || $full_name === '' || $role === '') {
            $error_msg = 'Invalid data for update.';
        } elseif (!in_array($role, ['superadmin', 'admin', 'staff', 'user'], true)) {
            $error_msg = 'Invalid role selected.';
        } else {
            $nameParts = preg_split('/\s+/', $full_name);
            $first_name = $nameParts[0] ?? $full_name;
            $last_name = $nameParts[count($nameParts) - 1] ?? $full_name;
            $middle_name = '';
            if (count($nameParts) > 2) {
                $middle_name = implode(' ', array_slice($nameParts, 1, -1));
            }

            $stmt = $conn->prepare('UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, email = ?, role = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('sssssi', $first_name, $middle_name, $last_name, $email, $role, $user_id);
                if ($stmt->execute()) {
                    $success_msg = 'Account updated successfully.';
                } else {
                    $error_msg = 'Error updating account.';
                }
                $stmt->close();
            } else {
                $error_msg = 'Error preparing account update.';
            }
        }
        
        if ($error_msg) {
            $_SESSION['error'] = $error_msg;
        } elseif ($success_msg) {
            $_SESSION['success'] = $success_msg;
        }
        session_write_close();
        header("Location: manage_accounts.php");
        exit();

    } elseif ($action === 'reset_password') {
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';

        $error_msg = '';
        $success_msg = '';

        if ($user_id <= 0 || $password === '' || $confirm_password === '') {
            $error_msg = 'Please fill in all password fields.';
        } elseif ($password !== $confirm_password) {
            $error_msg = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error_msg = 'Password must be at least 6 characters.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('si', $hashed_password, $user_id);
                if ($stmt->execute()) {
                    $success_msg = 'Password reset successfully.';
                } else {
                    $error_msg = 'Error resetting password.';
                }
                $stmt->close();
            } else {
                $error_msg = 'Error preparing password reset.';
            }
        }
        
        if ($error_msg) {
            $_SESSION['error'] = $error_msg;
        } elseif ($success_msg) {
            $_SESSION['success'] = $success_msg;
        }
        session_write_close();
        header("Location: manage_accounts.php");
        exit();
    }
}

$page_title = 'Manage Accounts';
renderHeader($page_title);
renderSidebar();
include 'superadmin_navbar.php';

$users = [];
$result = $conn->query("SELECT id, username, CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) AS full_name, email, role, created_at FROM users ORDER BY created_at DESC");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($invite_url): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3">
                        <div class="d-flex align-items-center gap-3 flex-grow-1">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="bi bi-link-45deg"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <div>
                                        <div class="fw-semibold text-primary small text-uppercase">
                                            Registration Link Generated
                                        </div>
                                        <div class="small text-muted">
                                            Share this one-time link with the intended user only.
                                        </div>
                                    </div>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle small">
                                        Active – expires in 4 hours
                                    </span>
                                </div>
                                <div class="mt-3">
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="bi bi-globe2 text-muted"></i>
                                        </span>
                                        <input
                                            id="invite_link_input"
                                            type="text"
                                            class="form-control border-start-0 font-monospace text-truncate"
                                            value="<?php echo htmlspecialchars($invite_url); ?>"
                                            readonly
                                        >
                                        <button
                                            id="copy_invite_link"
                                            type="button"
                                            class="btn btn-primary fw-semibold"
                                            style="min-width: 140px;"
                                        >
                                            <i class="bi bi-clipboard-check me-1"></i>
                                            Copy Link
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-secondary">User Accounts</h5>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#inviteLinkModal">
                        <i class="bi bi-link-45deg me-1"></i>Generate Registration Link
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle datatable w-100">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Created</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo (int)$user['id']; ?></td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary text-uppercase">
                                            <?php echo htmlspecialchars($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $created = $user['created_at'];
                                        if ($created) {
                                            echo htmlspecialchars(date('M d, Y', strtotime($created)));
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary btn-edit-user"
                                                data-id="<?php echo (int)$user['id']; ?>"
                                                data-username="<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>"
                                                data-full-name="<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES); ?>"
                                                data-email="<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>"
                                                data-role="<?php echo htmlspecialchars($user['role'], ENT_QUOTES); ?>"
                                            >
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-secondary btn-reset-password"
                                                data-id="<?php echo (int)$user['id']; ?>"
                                                data-username="<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>"
                                            >
                                                <i class="bi bi-key"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addAccountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 px-4 pt-4 pb-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0">Add New Account</h5>
                        <div class="text-muted small">Create a new user account with appropriate role.</div>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">
                <div class="modal-body pt-0 pb-4 px-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username<span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Full Name<span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role<span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="">Select role</option>
                                <option value="superadmin">Superadmin</option>
                                <option value="admin">Admin</option>
                                <option value="staff">Staff</option>
                                <option value="user">User</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password<span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password<span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-light border-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="inviteLinkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Generate Registration Link</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="generate_invite">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role" id="invite_role" class="form-select" required>
                            <option value="">Select role</option>
                            <option value="admin">Admin</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btn_generate_invite" class="btn btn-primary" disabled>
                        <i class="bi bi-link-45deg me-1"></i>Generate Link
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editAccountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" id="edit_username" class="form-control" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name<span class="text-danger">*</span></label>
                        <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role<span class="text-danger">*</span></label>
                        <select name="role" id="edit_role" class="form-select" required>
                            <option value="superadmin">Superadmin</option>
                            <option value="admin">Admin</option>
                            <option value="staff">Staff</option>
                            <option value="user">User</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_user_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" id="reset_username" class="form-control" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password<span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password<span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-key me-1"></i>Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var editButtons = document.querySelectorAll('.btn-edit-user');
    var resetButtons = document.querySelectorAll('.btn-reset-password');
    var roleSelect = document.getElementById('invite_role');
    var btnGenerateInvite = document.getElementById('btn_generate_invite');
    var copyBtn = document.getElementById('copy_invite_link');
    var inviteInput = document.getElementById('invite_link_input');

    editButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.getAttribute('data-id');
            var username = this.getAttribute('data-username');
            var fullName = this.getAttribute('data-full-name');
            var email = this.getAttribute('data-email');
            var role = this.getAttribute('data-role');

            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_username').value = username || '';
            document.getElementById('edit_full_name').value = fullName || '';
            document.getElementById('edit_email').value = email || '';
            document.getElementById('edit_role').value = role || 'staff';

            var modal = new bootstrap.Modal(document.getElementById('editAccountModal'));
            modal.show();
        });
    });

    resetButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.getAttribute('data-id');
            var username = this.getAttribute('data-username');

            document.getElementById('reset_user_id').value = id;
            document.getElementById('reset_username').value = username || '';

            var modal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
            modal.show();
        });
    });

    if (roleSelect && btnGenerateInvite) {
        roleSelect.addEventListener('change', function () {
            btnGenerateInvite.disabled = (this.value === '');
        });
    }

    if (copyBtn && inviteInput) {
        copyBtn.addEventListener('click', function () {
            inviteInput.select();
            inviteInput.setSelectionRange(0, inviteInput.value.length);
            try {
                var successful = document.execCommand('copy');
                if (successful) {
                    copyBtn.classList.remove('btn-outline-primary');
                    copyBtn.classList.add('btn-success');
                    copyBtn.innerHTML = '<i class="bi bi-check2 me-1"></i>Copied';
                    setTimeout(function () {
                        copyBtn.classList.remove('btn-success');
                        copyBtn.classList.add('btn-outline-primary');
                        copyBtn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy';
                    }, 2000);
                }
            } catch (e) {
            }
        });
    }
});
</script>

<?php renderFooter(); ?>
