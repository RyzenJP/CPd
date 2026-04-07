<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

include '../plugins/conn.php';
$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';
$user_columns = [];
$cols_result = $conn->query('SHOW COLUMNS FROM users');
if ($cols_result) {
    while ($col = $cols_result->fetch_assoc()) {
        $user_columns[$col['Field']] = true;
    }
    $cols_result->free();
}
$has_full_name = isset($user_columns['full_name']);
$has_first_name = isset($user_columns['first_name']);
$has_middle_name = isset($user_columns['middle_name']);
$has_last_name = isset($user_columns['last_name']);
$has_profile_picture = isset($user_columns['profile_picture']);

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    
    if (empty($full_name)) {
        $error_msg = "Full Name cannot be empty.";
    } else {
        $conn->begin_transaction();
        try {
            if ($has_full_name) {
                $stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE id = ?");
                if (!$stmt) {
                    throw new Exception("Error preparing update statement.");
                }
                $stmt->bind_param("si", $full_name, $user_id);
                if (!$stmt->execute()) {
                    throw new Exception("Error updating profile.");
                }
                $stmt->close();
            } elseif ($has_first_name || $has_last_name) {
                $nameParts = preg_split('/\s+/', $full_name);
                $first_name = $nameParts[0] ?? $full_name;
                $last_name = $nameParts[count($nameParts) - 1] ?? $full_name;
                $middle_name = '';
                if (count($nameParts) > 2) {
                    $middle_name = trim(implode(' ', array_slice($nameParts, 1, -1)));
                }
                if ($first_name === '') {
                    $first_name = $full_name;
                }
                if ($last_name === '') {
                    $last_name = $full_name;
                }
                $update_sql = 'UPDATE users SET '
                    . ($has_first_name ? 'first_name = ?' : '')
                    . (($has_first_name && $has_middle_name) ? ', ' : '')
                    . ($has_middle_name ? 'middle_name = ?' : '')
                    . ((($has_first_name || $has_middle_name) && $has_last_name) ? ', ' : '')
                    . ($has_last_name ? 'last_name = ?' : '')
                    . ' WHERE id = ?';

                $stmt = $conn->prepare($update_sql);
                if (!$stmt) {
                    throw new Exception("Error preparing update statement.");
                }
                if ($has_first_name && $has_middle_name && $has_last_name) {
                    $stmt->bind_param('sssi', $first_name, $middle_name, $last_name, $user_id);
                } elseif ($has_first_name && !$has_middle_name && $has_last_name) {
                    $stmt->bind_param('ssi', $first_name, $last_name, $user_id);
                } elseif ($has_first_name && $has_middle_name && !$has_last_name) {
                    $stmt->bind_param('ssi', $first_name, $middle_name, $user_id);
                } elseif (!$has_first_name && $has_middle_name && $has_last_name) {
                    $stmt->bind_param('ssi', $middle_name, $last_name, $user_id);
                } elseif ($has_first_name && !$has_middle_name && !$has_last_name) {
                    $stmt->bind_param('si', $first_name, $user_id);
                } elseif (!$has_first_name && !$has_middle_name && $has_last_name) {
                    $stmt->bind_param('si', $last_name, $user_id);
                } elseif (!$has_first_name && $has_middle_name && !$has_last_name) {
                    $stmt->bind_param('si', $middle_name, $user_id);
                } else {
                    throw new Exception("Users table does not have expected name columns.");
                }
                if (!$stmt->execute()) {
                    throw new Exception("Error updating profile.");
                }
                $stmt->close();
            } else {
                throw new Exception("Users table does not have expected name columns.");
            }

            // Handle Profile Picture Upload
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['profile_pic']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $new_filename = "profile_" . $user_id . "_" . time() . "." . $ext;
                    $upload_dir = dirname(__DIR__) . "/uploads/profile_pics/";
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0775, true);
                    }
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                        $stmt_old = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                        if ($stmt_old) {
                            $stmt_old->bind_param("i", $user_id);
                            $stmt_old->execute();
                            $result_old = $stmt_old->get_result();
                            if ($result_old) {
                                $row_old = $result_old->fetch_assoc();
                                if ($row_old && isset($row_old['profile_picture'])) {
                                    $old_pic = $row_old['profile_picture'];
                                    if ($old_pic && file_exists($upload_dir . $old_pic)) {
                                        unlink($upload_dir . $old_pic);
                                    }
                                }
                            }
                        }

                        $stmt_pic = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                        if ($stmt_pic) {
                            $stmt_pic->bind_param("si", $new_filename, $user_id);
                            $stmt_pic->execute();
                        }
                    }
                } else {
                    throw new Exception("Invalid file type. Only JPG, PNG, and GIF are allowed.");
                }
            }

            $conn->commit();
            $success_msg = "Profile updated successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Error updating profile: " . $e->getMessage();
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // 1. Verify Current Password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!password_verify($current_password, $user['password'])) {
        $error_msg = "Incorrect current password.";
    } elseif ($new_password !== $confirm_password) {
        $error_msg = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error_msg = "Password must be at least 6 characters long.";
    } else {
        // 2. Update Password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt_update->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt_update->execute()) {
            $success_msg = "Password changed successfully.";
        } else {
            $error_msg = "Error updating password: " . $conn->error;
        }
    }
}

// Fetch Current User Data (After Update)
$current_user = null;
$name_parts = [];
if ($has_first_name) {
    $name_parts[] = 'first_name';
}
if ($has_middle_name) {
    $name_parts[] = "NULLIF(middle_name, '')";
}
if ($has_last_name) {
    $name_parts[] = 'last_name';
}
$name_select = $has_full_name
    ? 'full_name'
    : (!empty($name_parts) ? ('CONCAT_WS(\' \', ' . implode(', ', $name_parts) . ') AS full_name') : (isset($user_columns['username']) ? 'username AS full_name' : "'' AS full_name"));

$select_sql = 'SELECT username, ' . $name_select . ', role'
    . ($has_profile_picture ? ', profile_picture' : '')
    . ' FROM users WHERE id = ?';
$stmt = $conn->prepare($select_sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result_user = $stmt->get_result();
    if ($result_user) {
        $current_user = $result_user->fetch_assoc();
    }
    $stmt->close();
}
if (!$current_user) {
    $current_user = [
        'username' => '',
        'full_name' => '',
        'role' => 'admin',
        'profile_picture' => null
    ];
} elseif (!array_key_exists('profile_picture', $current_user)) {
    $current_user['profile_picture'] = null;
}

include 'admin_sidebar.php';
$page_title = 'Profile Settings';
include 'admin_navbar.php';
?>
<style>
    .profile-avatar-container {
        position: relative;
        width: 120px;
        height: 120px;
        margin: 0 auto;
    }
    .profile-avatar-preview {
        width: 120px;
        height: 120px;
        object-fit: cover;
        border: 4px solid #fff;
        box-shadow: 0 0 15px rgba(0,0,0,0.1);
    }
    .profile-avatar-edit {
        position: absolute;
        bottom: 5px;
        right: 5px;
        background: #0d6efd;
        color: #fff;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid #fff;
    }
    .profile-avatar-edit:hover {
        background: #0b5ed7;
        transform: scale(1.1);
    }
    #profile_pic_input {
        display: none;
    }
</style>

<div class="main-content">
    <div class="container-fluid">
        <h2 class="mb-4 text-primary fw-bold">My Profile</h2>
        
        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i> <?php echo $success_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $error_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Information Overview -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2 text-primary"></i>Profile Overview</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="profile-avatar-container mb-3">
                            <?php if (!empty($current_user['profile_picture']) && file_exists("../uploads/profile_pics/" . $current_user['profile_picture'])): ?>
                                <img src="../uploads/profile_pics/<?php echo $current_user['profile_picture']; ?>" alt="Profile" class="rounded-circle profile-avatar-preview" id="avatarPreviewSide">
                            <?php else: ?>
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center profile-avatar-preview mx-auto" id="avatarPlaceholderSide" style="font-size: 3.5rem;">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($current_user['full_name']); ?></h4>
                        <p class="text-muted small mb-3"><?php echo ucfirst($current_user['role']); ?></p>
                        
                        <div class="text-start border-top pt-3">
                            <div class="mb-2">
                                <small class="text-muted d-block">Username</small>
                                <span class="fw-bold"><?php echo htmlspecialchars($current_user['username']); ?></span>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted d-block">Role</small>
                                <span class="badge bg-primary"><?php echo ucfirst($current_user['role']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Forms -->
            <div class="col-lg-8">
                <!-- Profile Information Form -->
                <div class="card mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Profile</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="update_profile" value="1">
                            <div class="row g-3">
                                <div class="col-12 text-center mb-3">
                                    <div class="profile-avatar-container">
                                        <?php if (!empty($current_user['profile_picture']) && file_exists("../uploads/profile_pics/" . $current_user['profile_picture'])): ?>
                                            <img src="../uploads/profile_pics/<?php echo $current_user['profile_picture']; ?>" alt="Profile" class="rounded-circle profile-avatar-preview" id="avatarPreview">
                                        <?php else: ?>
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center profile-avatar-preview mx-auto" id="avatarPlaceholder" style="font-size: 3.5rem;">
                                                <i class="bi bi-person-fill"></i>
                                            </div>
                                        <?php endif; ?>
                                        <label for="profile_pic_input" class="profile-avatar-edit">
                                            <i class="bi bi-camera-fill"></i>
                                        </label>
                                        <input type="file" name="profile_pic" id="profile_pic_input" accept="image/*" onchange="previewImage(this)">
                                    </div>
                                    <p class="text-muted small mt-2">Click camera to change photo</p>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Full Name</label>
                                    <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($current_user['full_name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">Username (Read-only)</label>
                                    <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($current_user['username']); ?>" disabled>
                                </div>
                                <div class="col-12 d-grid">
                                    <button type="submit" class="btn btn-primary fw-bold">
                                        <i class="bi bi-check-circle me-2"></i> Update Profile
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Change Password Form -->
                <div class="card mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-shield-lock me-2 text-primary"></i>Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="change_password" value="1">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold">Current Password</label>
                                    <input type="password" class="form-control" name="current_password" required placeholder="Enter current password">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">New Password</label>
                                    <input type="password" class="form-control" name="new_password" required minlength="6" placeholder="At least 6 characters">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Confirm New Password</label>
                                    <input type="password" class="form-control" name="confirm_password" required minlength="6" placeholder="Repeat new password">
                                </div>
                                <div class="col-12 d-grid">
                                    <button type="submit" class="btn btn-warning fw-bold text-white">
                                        <i class="bi bi-key me-2"></i> Change Password
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                // Update main preview
                var preview = document.getElementById('avatarPreview');
                var placeholder = document.getElementById('avatarPlaceholder');
                
                if (preview) {
                    preview.src = e.target.result;
                } else if (placeholder) {
                    var img = document.createElement('img');
                    img.id = 'avatarPreview';
                    img.className = 'rounded-circle profile-avatar-preview';
                    img.src = e.target.result;
                    placeholder.parentNode.replaceChild(img, placeholder);
                }

                // Update sidebar preview
                var previewSide = document.getElementById('avatarPreviewSide');
                var placeholderSide = document.getElementById('avatarPlaceholderSide');
                
                if (previewSide) {
                    previewSide.src = e.target.result;
                } else if (placeholderSide) {
                    var imgSide = document.createElement('img');
                    imgSide.id = 'avatarPreviewSide';
                    imgSide.className = 'rounded-circle profile-avatar-preview';
                    imgSide.src = e.target.result;
                    placeholderSide.parentNode.replaceChild(imgSide, placeholderSide);
                }
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>

<?php include 'admin_footer.php'; ?>
</body>
</html>
