<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../index.php");
    exit();
}

require_once '../plugins/conn.php';

$page_title = 'My Profile';

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

if ($user_id <= 0) {
    header('Location: ../index.php');
    exit();
}

$user_columns = [];
$cols_result = $conn->query('SHOW COLUMNS FROM users');
if ($cols_result) {
    while ($col = $cols_result->fetch_assoc()) {
        $user_columns[$col['Field']] = true;
    }
    $cols_result->free();
}
$has_first_name = isset($user_columns['first_name']);
$has_full_name = isset($user_columns['full_name']);
$has_phone = isset($user_columns['phone']);
$has_profile_picture = isset($user_columns['profile_picture']);

$user = null;
if ($has_first_name || $has_full_name) {
    if ($has_first_name) {
        $select_sql = 'SELECT username, first_name, middle_name, last_name, email'
            . ($has_phone ? ', phone' : '')
            . ($has_profile_picture ? ', profile_picture' : '')
            . ', created_at, role FROM users WHERE id = ? LIMIT 1';
    } else {
        $select_sql = 'SELECT username, full_name, email'
            . ($has_phone ? ', phone' : '')
            . ($has_profile_picture ? ', profile_picture' : '')
            . ', created_at, role FROM users WHERE id = ? LIMIT 1';
    }
    $stmt = $conn->prepare($select_sql);
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result) {
                $user = $result->fetch_assoc();
            }
        }
        $stmt->close();
    }
    if ($user && !$has_first_name && $has_full_name && isset($user['full_name'])) {
        $parts = preg_split('/\s+/', trim($user['full_name']));
        $first_name = '';
        $middle_name = '';
        $last_name = '';
        if (count($parts) === 1) {
            $first_name = $parts[0];
        } elseif (count($parts) === 2) {
            $first_name = $parts[0];
            $last_name = $parts[1];
        } elseif (count($parts) > 2) {
            $first_name = $parts[0];
            $last_name = array_pop($parts);
            $middle_name = trim(implode(' ', $parts));
        }
        $user['first_name'] = $first_name;
        $user['middle_name'] = $middle_name;
        $user['last_name'] = $last_name;
    }
}

if (!$user) {
    $user = [
        'username' => '',
        'first_name' => '',
        'middle_name' => '',
        'last_name' => '',
        'email' => '',
        'phone' => '',
        'profile_picture' => null,
        'created_at' => date('Y-m-d H:i:s'),
        'role' => 'staff'
    ];
} else {
    if (!isset($user['first_name'])) {
        $user['first_name'] = '';
    }
    if (!isset($user['middle_name'])) {
        $user['middle_name'] = '';
    }
    if (!isset($user['last_name'])) {
        $user['last_name'] = '';
    }
    if (!isset($user['email'])) {
        $user['email'] = '';
    }
    if (!isset($user['phone'])) {
        $user['phone'] = '';
    }
    if (!isset($user['profile_picture'])) {
        $user['profile_picture'] = null;
    }
    if (!isset($user['created_at'])) {
        $user['created_at'] = date('Y-m-d H:i:s');
    }
    if (!isset($user['role'])) {
        $user['role'] = 'staff';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['change_password'])) {
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';

    if ($first_name === '' || $last_name === '' || $email === '') {
        $_SESSION['error'] = 'First name, last name, and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Please enter a valid email address.';
    } else {
        $new_profile_picture = $user['profile_picture'];

        if (isset($_FILES['profile_picture']) && isset($_FILES['profile_picture']['error']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['profile_picture']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $size = (int) $_FILES['profile_picture']['size'];

                if (!in_array($ext, $allowed_ext, true)) {
                    $_SESSION['error'] = 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.';
                } elseif ($size > 5 * 1024 * 1024) {
                    $_SESSION['error'] = 'File is too large. Maximum size is 5MB.';
                } else {
                    $upload_dir = dirname(__DIR__) . '/assets/uploads/profile_pictures/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0775, true);
                    }
                    $new_name = 'user_' . $user_id . '_' . time() . '.' . $ext;
                    $destination = $upload_dir . $new_name;
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $destination)) {
                        if (!empty($new_profile_picture)) {
                            $old_path = $upload_dir . $new_profile_picture;
                            if (is_file($old_path)) {
                                @unlink($old_path);
                            }
                        }
                        $new_profile_picture = $new_name;
                    } else {
                        $_SESSION['error'] = 'Failed to upload profile picture.';
                    }
                }
            } else {
                $_SESSION['error'] = 'Error uploading file.';
            }
        }

        if (!isset($_SESSION['error'])) {
            $conn->begin_transaction();
            try {
                if ($has_first_name) {
                    if ($has_phone && $has_profile_picture) {
                        $update_sql = 'UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, email = ?, phone = ?, profile_picture = ? WHERE id = ?';
                        $stmt_upd = $conn->prepare($update_sql);
                        if (!$stmt_upd) {
                            throw new Exception('Error preparing update statement: ' . $conn->error);
                        }
                        $stmt_upd->bind_param('ssssssi', $first_name, $middle_name, $last_name, $email, $phone, $new_profile_picture, $user_id);
                    } elseif ($has_phone && !$has_profile_picture) {
                        $update_sql = 'UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?';
                        $stmt_upd = $conn->prepare($update_sql);
                        if (!$stmt_upd) {
                            throw new Exception('Error preparing update statement: ' . $conn->error);
                        }
                        $stmt_upd->bind_param('sssssi', $first_name, $middle_name, $last_name, $email, $phone, $user_id);
                    } elseif (!$has_phone && $has_profile_picture) {
                        $update_sql = 'UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, email = ?, profile_picture = ? WHERE id = ?';
                        $stmt_upd = $conn->prepare($update_sql);
                        if (!$stmt_upd) {
                            throw new Exception('Error preparing update statement: ' . $conn->error);
                        }
                        $stmt_upd->bind_param('sssssi', $first_name, $middle_name, $last_name, $email, $new_profile_picture, $user_id);
                    } else {
                        $update_sql = 'UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, email = ? WHERE id = ?';
                        $stmt_upd = $conn->prepare($update_sql);
                        if (!$stmt_upd) {
                            throw new Exception('Error preparing update statement: ' . $conn->error);
                        }
                        $stmt_upd->bind_param('ssssi', $first_name, $middle_name, $last_name, $email, $user_id);
                    }
                } elseif ($has_full_name) {
                    $full_name_combined = trim($first_name . ' ' . ($middle_name !== '' ? $middle_name . ' ' : '') . $last_name);
                    if ($has_phone && $has_profile_picture) {
                        $update_sql = 'UPDATE users SET full_name = ?, email = ?, phone = ?, profile_picture = ? WHERE id = ?';
                        $stmt_upd = $conn->prepare($update_sql);
                        if (!$stmt_upd) {
                            throw new Exception('Error preparing update statement: ' . $conn->error);
                        }
                        $stmt_upd->bind_param('ssssi', $full_name_combined, $email, $phone, $new_profile_picture, $user_id);
                    } elseif ($has_phone && !$has_profile_picture) {
                        $update_sql = 'UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?';
                        $stmt_upd = $conn->prepare($update_sql);
                        if (!$stmt_upd) {
                            throw new Exception('Error preparing update statement: ' . $conn->error);
                        }
                        $stmt_upd->bind_param('sssi', $full_name_combined, $email, $phone, $user_id);
                    } elseif (!$has_phone && $has_profile_picture) {
                        $update_sql = 'UPDATE users SET full_name = ?, email = ?, profile_picture = ? WHERE id = ?';
                        $stmt_upd = $conn->prepare($update_sql);
                        if (!$stmt_upd) {
                            throw new Exception('Error preparing update statement: ' . $conn->error);
                        }
                        $stmt_upd->bind_param('sssi', $full_name_combined, $email, $new_profile_picture, $user_id);
                    } else {
                        $update_sql = 'UPDATE users SET full_name = ?, email = ? WHERE id = ?';
                        $stmt_upd = $conn->prepare($update_sql);
                        if (!$stmt_upd) {
                            throw new Exception('Error preparing update statement: ' . $conn->error);
                        }
                        $stmt_upd->bind_param('ssi', $full_name_combined, $email, $user_id);
                    }
                } else {
                    throw new Exception('Users table does not have expected name columns.');
                }

                if (!$stmt_upd->execute()) {
                    throw new Exception('Error updating profile.');
                }
                $stmt_upd->close();
                $conn->commit();
                $_SESSION['success'] = 'Profile updated successfully.';
                $user['first_name'] = $first_name;
                $user['middle_name'] = $middle_name;
                $user['last_name'] = $last_name;
                $user['email'] = $email;
                $user['phone'] = $phone;
                $user['profile_picture'] = $new_profile_picture;
                
                // Redirect to avoid resubmission
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = $e->getMessage();
            }
        }
    }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($current_password === '' || $new_password === '' || $confirm_password === '') {
            $_SESSION['error'] = 'All fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['error'] = 'New password and confirm password do not match.';
        } elseif (strlen($new_password) < 8) {
            $_SESSION['error'] = 'Password must be at least 8 characters long.';
        } else {
            $sql = "SELECT password FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_data = $result->fetch_assoc();
            
            if ($user_data && password_verify($current_password, $user_data['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Password changed successfully!";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $_SESSION['error'] = "Error changing password. Please try again.";
                }
            } else {
                $_SESSION['error'] = "Current password is incorrect";
            }
        }
    }
}

$full_name_parts = [];
if ($user['first_name'] !== '') {
    $full_name_parts[] = $user['first_name'];
}
if ($user['middle_name'] !== '') {
    $full_name_parts[] = $user['middle_name'];
}
if ($user['last_name'] !== '') {
    $full_name_parts[] = $user['last_name'];
}
$full_name = trim(implode(' ', $full_name_parts));

$profile_pic_url = null;
if (!empty($user['profile_picture'])) {
    $profile_pic_url = '../assets/uploads/profile_pictures/' . htmlspecialchars($user['profile_picture'], ENT_QUOTES, 'UTF-8');
}

require_once 'staff_sidebar.php';
include 'staff_navbar.php';
?>
<div class="lg:ml-[260px] pt-20 min-h-screen bg-bg-light transition-all duration-300">
    <div class="p-4 md:p-8 max-w-7xl mx-auto">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Profile Sidebar -->
            <div class="lg:col-span-4">
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden sticky top-24">
                    <div class="h-32 bg-primary relative">
                        <div class="absolute -bottom-12 left-1/2 -translate-x-1/2">
                            <div class="relative group">
                                <div class="w-32 h-32 rounded-3xl overflow-hidden border-4 border-white shadow-lg bg-white">
                                    <?php if ($profile_pic_url): ?>
                                        <img src="<?php echo $profile_pic_url; ?>" alt="Profile Picture" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full bg-blue-50 flex items-center justify-center">
                                            <i class="bi bi-person-fill text-primary text-5xl"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pt-16 pb-8 px-6 text-center">
                        <h2 class="text-xl font-black text-primary mb-1">
                            <?php echo htmlspecialchars($full_name !== '' ? $full_name : $user['username'], ENT_QUOTES, 'UTF-8'); ?>
                        </h2>
                        <p class="text-gray-500 text-sm mb-4"><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                        
                        <div class="inline-flex items-center px-4 py-1.5 rounded-full bg-blue-50 text-primary text-xs font-bold border border-blue-100 uppercase tracking-wider mb-6">
                            <span class="w-2 h-2 rounded-full bg-primary mr-2 animate-pulse"></span>
                            Staff Member
                        </div>

                        <div class="grid grid-cols-1 gap-3 text-left border-t border-gray-50 pt-6">
                            <div class="flex items-center justify-between p-3 rounded-2xl bg-gray-50/50 hover:bg-gray-50 transition-colors">
                                <span class="text-gray-500 text-sm">Member Since</span>
                                <span class="text-primary font-bold text-sm"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                            </div>
                            <div class="flex items-center justify-between p-3 rounded-2xl bg-gray-50/50 hover:bg-gray-50 transition-colors">
                                <span class="text-gray-500 text-sm">Username</span>
                                <span class="text-primary font-bold text-sm">@<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Forms Area -->
            <div class="lg:col-span-8 space-y-8">
                <!-- Edit Profile Card -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-8 py-6 border-b border-gray-50 flex items-center justify-between bg-white">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center mr-4">
                                <i class="bi bi-person-gear text-primary text-xl"></i>
                            </div>
                            <h3 class="text-lg font-black text-primary uppercase tracking-tight">Edit Profile Information</h3>
                        </div>
                    </div>
                    
                    <div class="p-8">
                        <form method="post" enctype="multipart/form-data" class="space-y-6">
                            <!-- Profile Picture Upload -->
                            <div class="group">
                                <label class="block text-sm font-bold text-gray-700 mb-2">Update Profile Photo</label>
                                <div class="flex items-center space-x-4">
                                    <div class="flex-grow">
                                        <div class="relative">
                                            <input type="file" name="profile_picture" accept="image/*" 
                                                   class="block w-full text-sm text-gray-500
                                                          file:mr-4 file:py-2.5 file:px-4
                                                          file:rounded-xl file:border-0
                                                          file:text-sm file:font-bold
                                                          file:bg-blue-50 file:text-primary
                                                          hover:file:bg-primary hover:file:text-white
                                                          file:transition-all file:duration-200
                                                          cursor-pointer border border-gray-200 rounded-xl p-1">
                                        </div>
                                        <p class="mt-2 text-xs text-gray-400">Accepted formats: JPG, PNG, GIF. Maximum file size: 5MB.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">First Name</label>
                                    <input type="text" name="first_name" required
                                           class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all outline-none font-medium"
                                           value="<?php echo htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Middle Name</label>
                                    <input type="text" name="middle_name"
                                           class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all outline-none font-medium"
                                           value="<?php echo htmlspecialchars($user['middle_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Last Name</label>
                                    <input type="text" name="last_name" required
                                           class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all outline-none font-medium"
                                           value="<?php echo htmlspecialchars($user['last_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Email Address</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400">
                                            <i class="bi bi-envelope"></i>
                                        </span>
                                        <input type="email" name="email" required
                                               class="w-full pl-11 pr-4 py-3 rounded-xl border border-gray-200 focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all outline-none font-medium"
                                               value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Phone Number</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400">
                                            <i class="bi bi-telephone"></i>
                                        </span>
                                        <input type="text" name="phone"
                                               class="w-full pl-11 pr-4 py-3 rounded-xl border border-gray-200 focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all outline-none font-medium"
                                               value="<?php echo htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-gray-50 flex justify-end">
                                <button type="submit" 
                                        class="px-8 py-3 bg-primary text-white rounded-2xl font-black text-sm uppercase tracking-widest hover:bg-opacity-90 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 flex items-center">
                                    <i class="bi bi-check2-circle mr-2 text-lg"></i>
                                    Save Profile Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Change Password Card -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-8 py-6 border-b border-gray-50 flex items-center justify-between bg-white">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center mr-4">
                                <i class="bi bi-shield-lock text-amber-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-black text-primary uppercase tracking-tight">Security & Password</h3>
                        </div>
                    </div>
                    
                    <div class="p-8">
                        
                        <form method="post" class="space-y-6">
                            <input type="hidden" name="change_password" value="1">
                            
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Current Password</label>
                                <input type="password" name="current_password" required
                                       class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all outline-none">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">New Password</label>
                                    <input type="password" name="new_password" required minlength="8"
                                           class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all outline-none">
                                    <p class="mt-2 text-xs text-gray-400">Must be at least 8 characters long.</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Confirm New Password</label>
                                    <input type="password" name="confirm_password" required minlength="8"
                                           class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all outline-none">
                                </div>
                            </div>

                            <div class="pt-4 border-t border-gray-50 flex justify-end">
                                <button type="submit" 
                                        class="px-8 py-3 bg-amber-500 text-white rounded-2xl font-black text-sm uppercase tracking-widest hover:bg-amber-600 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 flex items-center">
                                    <i class="bi bi-lock-fill mr-2 text-lg"></i>
                                    Update Password
                                </button>
                            </div>
                        </form>
                        
                        <div class="mt-10 pt-8 border-t border-gray-50">
                            <h4 class="text-sm font-black text-primary uppercase tracking-wider mb-4">Security Best Practices</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="flex items-start p-4 rounded-2xl bg-gray-50/50">
                                    <i class="bi bi-check2-circle text-primary mt-0.5 mr-3"></i>
                                    <p class="text-xs text-gray-600 leading-relaxed">Use a strong password with a mix of letters, numbers, and symbols.</p>
                                </div>
                                <div class="flex items-start p-4 rounded-2xl bg-gray-50/50">
                                    <i class="bi bi-shield-slash text-secondary mt-0.5 mr-3"></i>
                                    <p class="text-xs text-gray-600 leading-relaxed">Never share your login credentials or password with anyone.</p>
                                </div>
                                <div class="flex items-start p-4 rounded-2xl bg-gray-50/50">
                                    <i class="bi bi-box-arrow-right text-primary mt-0.5 mr-3"></i>
                                    <p class="text-xs text-gray-600 leading-relaxed">Always log out when using a shared or public computer.</p>
                                </div>
                                <div class="flex items-start p-4 rounded-2xl bg-gray-50/50">
                                    <i class="bi bi-browser-chrome text-primary mt-0.5 mr-3"></i>
                                    <p class="text-xs text-gray-600 leading-relaxed">Ensure your browser and operating system are up to date.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
