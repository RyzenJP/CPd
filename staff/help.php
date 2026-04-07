<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../index.php");
    exit();
}

$page_title = 'Help & Support';
require_once '../plugins/conn.php';

// Create support_tickets table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    category ENUM('general', 'technical', 'account', 'request') DEFAULT 'general',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Create faqs table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS faqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(100) DEFAULT 'Getting Started',
    `order` INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Insert sample FAQs if table is empty
$check_faqs = $conn->query("SELECT COUNT(*) as count FROM faqs");
if ($check_faqs) {
    $row = $check_faqs->fetch_assoc();
    if ($row && $row['count'] == 0) {
    $sample_faqs = [
        ['How do I request an item from inventory?', 'Navigate to the Inventory List, find the item you need, and click the "Request" button. Fill in the required quantity and submit your request.', 'Getting Started', 1],
        ['How can I track my requests?', 'Go to "My RIS" in the sidebar to view all your requests and their current status.', 'Getting Started', 2],
        ['What is the maximum quantity I can request?', 'The maximum quantity depends on item availability and your department policies. You cannot request more than the available stock.', 'Policies', 1],
                ['I forgot my password, what should I do?', 'Click on the "Forgot Password" link on the login page and follow the reset instructions sent to your email.', 'Technical', 1]
    ];

    foreach ($sample_faqs as $faq) {
        $stmt = $conn->prepare("INSERT INTO faqs (question, answer, category, `order`) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $faq[0], $faq[1], $faq[2], $faq[3]);
        $stmt->execute();
    }
    }
}

// Handle support ticket submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ticket'])) {
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    $category = $_POST['category'] ?? 'general';
    $priority = $_POST['priority'] ?? 'medium';
    
    if (empty($subject) || empty($message)) {
        $_SESSION['error'] = "Subject and message are required";
    } else {
        $sql = "INSERT INTO support_tickets (user_id, subject, message, category, priority, status) 
                VALUES (?, ?, ?, ?, ?, 'open')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issss", $user_id, $subject, $message, $category, $priority);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Support ticket submitted successfully! We'll respond within 24 hours.";
            header("Location: help.php?tab=support");
            exit();
        } else {
            $_SESSION['error'] = "Error submitting support ticket. Please try again.";
        }
    }
}

// Get FAQs
$category_filter = $_GET['category'] ?? 'all';
$search_query = $_GET['search'] ?? '';

$where_conditions = ["is_active = 1"];
$params = [];
$types = "";

if ($category_filter != 'all') {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

if (!empty($search_query)) {
    $where_conditions[] = "(question LIKE ? OR answer LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$where_clause = implode(" AND ", $where_conditions);

$sql = "SELECT * FROM faqs WHERE $where_clause ORDER BY category, `order` ASC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Group FAQs by category
$faqs_by_category = [];
while ($row = $result->fetch_assoc()) {
    $faqs_by_category[$row['category']][] = $row;
}

// Get user's support tickets
$sql_tickets = "SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
$stmt_tickets = $conn->prepare($sql_tickets);
$stmt_tickets->bind_param("i", $user_id);
$stmt_tickets->execute();
$result_tickets = $stmt_tickets->get_result();
?>

<?php
require_once 'staff_sidebar.php';
require_once 'staff_navbar.php';
?>

<div class="lg:ml-[260px] pt-20 min-h-screen transition-all duration-300">
    <div class="px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header Section -->

        <!-- Quick Action Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div onclick="switchTab('faq')" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 transition-all duration-300 hover:shadow-md hover:-translate-y-1 cursor-pointer group">
                <div class="w-12 h-12 rounded-2xl bg-primary/5 text-primary flex items-center justify-center mb-4 group-hover:bg-primary group-hover:text-white transition-colors duration-300">
                    <i class="bi bi-question-circle text-2xl"></i>
                </div>
                <h3 class="font-bold text-gray-800">Browse FAQs</h3>
                <p class="text-xs text-gray-500 mt-1">Quick answers to common questions</p>
            </div>
            
            <div onclick="switchTab('guides')" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 transition-all duration-300 hover:shadow-md hover:-translate-y-1 cursor-pointer group">
                <div class="w-12 h-12 rounded-2xl bg-accent/10 text-accent flex items-center justify-center mb-4 group-hover:bg-accent group-hover:text-white transition-colors duration-300">
                    <i class="bi bi-book text-2xl"></i>
                </div>
                <h3 class="font-bold text-gray-800">User Guides</h3>
                <p class="text-xs text-gray-500 mt-1">Step-by-step documentation</p>
            </div>

            <div onclick="switchTab('support')" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 transition-all duration-300 hover:shadow-md hover:-translate-y-1 cursor-pointer group">
                <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4 group-hover:bg-blue-600 group-hover:text-white transition-colors duration-300">
                    <i class="bi bi-headset text-2xl"></i>
                </div>
                <h3 class="font-bold text-gray-800">Contact Support</h3>
                <p class="text-xs text-gray-500 mt-1">Get personalized help</p>
            </div>

            <div onclick="switchTab('tickets')" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 transition-all duration-300 hover:shadow-md hover:-translate-y-1 cursor-pointer group">
                <div class="w-12 h-12 rounded-2xl bg-secondary/10 text-secondary flex items-center justify-center mb-4 group-hover:bg-secondary group-hover:text-white transition-colors duration-300">
                    <i class="bi bi-ticket-detailed text-2xl"></i>
                </div>
                <h3 class="font-bold text-gray-800">My Tickets</h3>
                <p class="text-xs text-gray-500 mt-1">Track your support requests</p>
            </div>
        </div>



        <!-- Help Tabs -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 bg-gray-50/50 border-b border-gray-100">
                <nav class="flex flex-wrap gap-2" role="tablist">
                    <button onclick="switchTab('faq')" class="tab-btn px-6 py-3 rounded-2xl text-xs font-bold transition-all duration-200 bg-primary text-white shadow-md shadow-primary/20" id="tab-btn-faq">
                        <i class="bi bi-question-circle mr-2"></i>FAQ
                    </button>
                    <button onclick="switchTab('guides')" class="tab-btn px-6 py-3 rounded-2xl text-xs font-bold text-gray-500 hover:bg-white hover:text-primary transition-all duration-200" id="tab-btn-guides">
                        <i class="bi bi-book mr-2"></i>User Guides
                    </button>
                    <button onclick="switchTab('support')" class="tab-btn px-6 py-3 rounded-2xl text-xs font-bold text-gray-500 hover:bg-white hover:text-primary transition-all duration-200" id="tab-btn-support">
                        <i class="bi bi-headset mr-2"></i>Contact Support
                    </button>
                    <button onclick="switchTab('tickets')" class="tab-btn px-6 py-3 rounded-2xl text-xs font-bold text-gray-500 hover:bg-white hover:text-primary transition-all duration-200" id="tab-btn-tickets">
                        <i class="bi bi-ticket-detailed mr-2"></i>My Tickets
                    </button>
                </nav>
            </div>

            <div class="p-8">
                <!-- FAQ Tab -->
                <div id="tab-content-faq" class="tab-content block animate-fade-in">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                        <form method="GET" class="relative">
                            <input type="hidden" name="tab" value="faq">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400">
                                <i class="bi bi-search"></i>
                            </div>
                            <input type="text" name="search" class="w-full pl-11 pr-4 py-3 bg-white border border-gray-200 rounded-2xl text-sm focus:ring-4 focus:ring-primary/5 focus:border-primary transition-all outline-none" placeholder="Search FAQs..." value="<?php echo htmlspecialchars($search_query); ?>">
                        </form>
                        <form method="GET">
                            <input type="hidden" name="tab" value="faq">
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400">
                                    <i class="bi bi-funnel"></i>
                                </div>
                                <select name="category" class="w-full pl-11 pr-4 py-3 bg-white border border-gray-200 rounded-2xl text-sm focus:ring-4 focus:ring-primary/5 focus:border-primary transition-all outline-none appearance-none cursor-pointer" onchange="this.form.submit()">
                                    <option value="all">All Categories</option>
                                    <option value="Getting Started" <?php echo $category_filter == 'Getting Started' ? 'selected' : ''; ?>>Getting Started</option>
                                    <option value="Account" <?php echo $category_filter == 'Account' ? 'selected' : ''; ?>>Account</option>
                                    <option value="Technical" <?php echo $category_filter == 'Technical' ? 'selected' : ''; ?>>Technical</option>
                                    <option value="Policies" <?php echo $category_filter == 'Policies' ? 'selected' : ''; ?>>Policies</option>
                                </select>
                                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-gray-400">
                                    <i class="bi bi-chevron-down text-xs"></i>
                                </div>
                            </div>
                        </form>
                    </div>

                    <?php if (!empty($faqs_by_category)): ?>
                        <?php foreach ($faqs_by_category as $category => $faqs): ?>
                            <div class="mb-10">
                                <div class="flex items-center gap-4 mb-6">
                                    <span class="px-4 py-1.5 bg-primary/5 text-primary text-[11px] font-bold rounded-full border border-primary/10 flex items-center shrink-0 uppercase tracking-wider">
                                        <i class="bi bi-folder mr-2"></i><?php echo htmlspecialchars($category); ?>
                                    </span>
                                    <div class="h-px bg-gray-100 w-full"></div>
                                </div>
                                <div class="space-y-4">
                                    <?php foreach ($faqs as $index => $faq): ?>
                                        <div class="group border border-gray-100 rounded-2xl overflow-hidden bg-white hover:border-primary/20 transition-all duration-300">
                                            <button class="w-full px-6 py-4 flex items-center justify-between text-left focus:outline-none" onclick="toggleAccordion(this)">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 rounded-lg bg-primary/5 text-primary flex items-center justify-center mr-4 group-hover:bg-primary group-hover:text-white transition-colors">
                                                        <i class="bi bi-question-lg"></i>
                                                    </div>
                                                    <span class="font-bold text-gray-800 text-sm"><?php echo htmlspecialchars($faq['question']); ?></span>
                                                </div>
                                                <i class="bi bi-chevron-down text-gray-400 text-xs transition-transform duration-300"></i>
                                            </button>
                                            <div class="hidden accordion-content">
                                                <div class="px-6 pb-6 pt-0 ml-12">
                                                    <div class="p-4 bg-gray-50 rounded-2xl text-sm text-gray-600 leading-relaxed border border-gray-100">
                                                        <?php echo nl2br(htmlspecialchars($faq['answer'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-20">
                            <div class="w-20 h-20 bg-gray-50 rounded-3xl flex items-center justify-center mx-auto mb-4">
                                <i class="bi bi-search text-3xl text-gray-200"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-800">No FAQs found</h3>
                            <p class="text-sm text-gray-500 mt-1 max-w-xs mx-auto">Try adjusting your search criteria or browse all categories.</p>
                            <button type="button" class="mt-6 px-6 py-2.5 bg-primary text-white text-xs font-bold rounded-xl hover:bg-primary-light transition-all" onclick="window.location.href='help.php?tab=faq'">
                                Clear All Filters
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- User Guides Tab -->
                <div id="tab-content-guides" class="tab-content hidden animate-fade-in">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-2">
                            <div class="flex items-center gap-4 mb-8">
                                <span class="px-4 py-1.5 bg-accent/10 text-accent text-[11px] font-bold rounded-full border border-accent/20 flex items-center shrink-0 uppercase tracking-wider">
                                    <i class="bi bi-book mr-2"></i>Guides & Documentation
                                </span>
                                <div class="h-px bg-gray-100 w-full"></div>
                            </div>
                            
                            <div class="space-y-4">
                                <a href="#" class="group flex items-start p-6 bg-white border border-gray-100 rounded-3xl hover:border-primary/20 hover:shadow-md transition-all duration-300">
                                    <div class="w-12 h-12 rounded-2xl bg-primary/5 text-primary flex items-center justify-center mr-6 group-hover:bg-primary group-hover:text-white transition-colors shrink-0">
                                        <i class="bi bi-play-circle text-2xl"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-gray-800 group-hover:text-primary transition-colors">Getting Started Guide</h4>
                                        <p class="text-sm text-gray-500 mt-1 leading-relaxed">Learn the basics of using the inventory system effectively from login to dashboard navigation.</p>
                                        <div class="flex items-center gap-4 mt-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                                            <span class="flex items-center"><i class="bi bi-clock mr-1.5 text-primary"></i> 5 min read</span>
                                            <span class="flex items-center"><i class="bi bi-bar-chart mr-1.5 text-accent"></i> Beginner</span>
                                        </div>
                                    </div>
                                </a>

                                <a href="#" class="group flex items-start p-6 bg-white border border-gray-100 rounded-3xl hover:border-blue-200 hover:shadow-md transition-all duration-300">
                                    <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mr-6 group-hover:bg-blue-600 group-hover:text-white transition-colors shrink-0">
                                        <i class="bi bi-cart-plus text-2xl"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-gray-800 group-hover:text-blue-600 transition-colors">How to Request Items</h4>
                                        <p class="text-sm text-gray-500 mt-1 leading-relaxed">Complete step-by-step guide for submitting item requests and tracking requisition status.</p>
                                        <div class="flex items-center gap-4 mt-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                                            <span class="flex items-center"><i class="bi bi-clock mr-1.5 text-blue-600"></i> 3 min read</span>
                                            <span class="flex items-center"><i class="bi bi-bar-chart mr-1.5 text-accent"></i> Beginner</span>
                                        </div>
                                    </div>
                                </a>

                                <a href="#" class="group flex items-start p-6 bg-white border border-gray-100 rounded-3xl hover:border-accent/20 hover:shadow-md transition-all duration-300">
                                    <div class="w-12 h-12 rounded-2xl bg-accent/10 text-accent flex items-center justify-center mr-6 group-hover:bg-accent group-hover:text-white transition-colors shrink-0">
                                        <i class="bi bi-briefcase text-2xl"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-gray-800 group-hover:text-accent transition-colors">Managing Assignments</h4>
                                        <p class="text-sm text-gray-500 mt-1 leading-relaxed">Learn how to view and manage your assigned items, accountability reports, and returns.</p>
                                        <div class="flex items-center gap-4 mt-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                                            <span class="flex items-center"><i class="bi bi-clock mr-1.5 text-accent"></i> 4 min read</span>
                                            <span class="flex items-center"><i class="bi bi-bar-chart mr-1.5 text-secondary"></i> Intermediate</span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                        
                        <div class="space-y-6">
                            <div class="bg-gray-50/50 rounded-3xl p-6 border border-gray-100">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-8 h-8 rounded-xl bg-accent/10 text-accent flex items-center justify-center">
                                        <i class="bi bi-lightbulb text-lg"></i>
                                    </div>
                                    <h4 class="font-bold text-gray-800">Quick Tips</h4>
                                </div>
                                <ul class="space-y-4">
                                    <li class="flex items-start">
                                        <i class="bi bi-check2-circle text-primary mt-0.5 mr-3 shrink-0"></i>
                                        <p class="text-xs text-gray-600 leading-relaxed font-medium">Use the search bar in inventory to find items by name or stock number.</p>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="bi bi-check2-circle text-primary mt-0.5 mr-3 shrink-0"></i>
                                        <p class="text-xs text-gray-600 leading-relaxed font-medium">Check your dashboard for real-time updates on your pending requests.</p>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="bi bi-check2-circle text-primary mt-0.5 mr-3 shrink-0"></i>
                                        <p class="text-xs text-gray-600 leading-relaxed font-medium">Keep your profile updated to receive email notifications correctly.</p>
                                    </li>
                                </ul>
                            </div>
                            
                            <div class="bg-primary rounded-3xl p-6 text-white shadow-lg shadow-primary/20">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="w-8 h-8 rounded-xl bg-white/20 text-white flex items-center justify-center">
                                        <i class="bi bi-info-circle text-lg"></i>
                                    </div>
                                    <h4 class="font-bold">Need More Help?</h4>
                                </div>
                                <p class="text-xs text-primary-light leading-relaxed mb-6 font-medium">Can't find what you're looking for? Our support team is ready to assist you with any issues.</p>
                                <button type="button" class="w-full py-3 bg-white text-primary text-[11px] font-bold rounded-xl hover:bg-accent hover:text-white transition-all duration-300 uppercase tracking-wider" onclick="switchTab('support')">
                                    <i class="bi bi-headset mr-2"></i>Contact Support Team
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Support Tab -->
                <div id="tab-content-support" class="tab-content hidden animate-fade-in">
                    <div class="flex items-center gap-4 mb-8">
                        <span class="px-4 py-1.5 bg-blue-50 text-blue-600 text-[11px] font-bold rounded-full border border-blue-100 flex items-center shrink-0 uppercase tracking-wider">
                            <i class="bi bi-headset mr-2"></i>Submit Support Ticket
                        </span>
                        <div class="h-px bg-gray-100 w-full"></div>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-2">
                            <div class="bg-white rounded-3xl border border-gray-100 p-8">
                                <form method="POST" class="space-y-6">
                                    <input type="hidden" name="submit_ticket" value="1">
                                    <div class="space-y-2">
                                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider ml-1">Subject</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400">
                                                <i class="bi bi-chat-text"></i>
                                            </div>
                                            <input type="text" name="subject" class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl text-sm focus:ring-4 focus:ring-primary/5 focus:border-primary transition-all outline-none" required placeholder="Brief description of your issue...">
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="space-y-2">
                                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider ml-1">Category</label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400">
                                                    <i class="bi bi-tag"></i>
                                                </div>
                                                <select name="category" class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl text-sm focus:ring-4 focus:ring-primary/5 focus:border-primary transition-all outline-none appearance-none cursor-pointer" required>
                                                    <option value="">Select category</option>
                                                    <option value="general">General Inquiry</option>
                                                    <option value="technical">Technical Issue</option>
                                                    <option value="account">Account Problem</option>
                                                    <option value="request">Request Issue</option>
                                                </select>
                                                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-gray-400">
                                                    <i class="bi bi-chevron-down text-xs"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="space-y-2">
                                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider ml-1">Priority</label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400">
                                                    <i class="bi bi-flag"></i>
                                                </div>
                                                <select name="priority" class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl text-sm focus:ring-4 focus:ring-primary/5 focus:border-primary transition-all outline-none appearance-none cursor-pointer" required>
                                                    <option value="low">Low</option>
                                                    <option value="medium" selected>Medium</option>
                                                    <option value="high">High</option>
                                                    <option value="urgent">Urgent</option>
                                                </select>
                                                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-gray-400">
                                                    <i class="bi bi-chevron-down text-xs"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-2">
                                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider ml-1">Message</label>
                                        <textarea name="message" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl text-sm focus:ring-4 focus:ring-primary/5 focus:border-primary transition-all outline-none resize-none" rows="6" required placeholder="Describe your issue in detail. Include any error messages or steps you've already taken..."></textarea>
                                    </div>
                                    
                                    <button type="submit" class="w-full py-4 bg-primary text-white text-xs font-bold rounded-2xl hover:bg-primary-light transition-all duration-200 shadow-lg shadow-primary/20 uppercase tracking-widest">
                                        <i class="bi bi-send mr-2"></i>Submit Support Ticket
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="space-y-6">
                            <div class="bg-white rounded-3xl p-6 border border-gray-100">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                                        <i class="bi bi-clock text-lg"></i>
                                    </div>
                                    <h4 class="font-bold text-gray-800">Response Time</h4>
                                </div>
                                <div class="space-y-4">
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs text-gray-500 font-medium">Urgent:</span>
                                        <span class="px-2 py-0.5 bg-red-50 text-red-600 text-[10px] font-bold rounded-full border border-red-100">2 Hours</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs text-gray-500 font-medium">High:</span>
                                        <span class="px-2 py-0.5 bg-amber-50 text-amber-600 text-[10px] font-bold rounded-full border border-amber-100">4 Hours</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs text-gray-500 font-medium">Medium:</span>
                                        <span class="px-2 py-0.5 bg-blue-50 text-blue-600 text-[10px] font-bold rounded-full border border-blue-100">24 Hours</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs text-gray-500 font-medium">Low:</span>
                                        <span class="px-2 py-0.5 bg-gray-50 text-gray-500 text-[10px] font-bold rounded-full border border-gray-100">48 Hours</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 rounded-3xl p-6 border border-gray-100">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-8 h-8 rounded-xl bg-primary/5 text-primary flex items-center justify-center">
                                        <i class="bi bi-telephone text-lg"></i>
                                    </div>
                                    <h4 class="font-bold text-gray-800">Contact Info</h4>
                                </div>
                                <div class="space-y-6">
                                    <div>
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Email Address</p>
                                        <p class="text-sm text-gray-800 font-bold italic underline decoration-primary/30">support@cpdnir.gov.ph</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Phone Number</p>
                                        <p class="text-sm text-gray-800 font-bold">(02) 1234-5678</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Office Hours</p>
                                        <p class="text-sm text-gray-800 font-bold">8:00 AM - 5:00 PM</p>
                                        <p class="text-xs text-gray-500 mt-0.5 font-medium">Monday - Friday</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- My Tickets Tab -->
                <div id="tab-content-tickets" class="tab-content hidden animate-fade-in">
                    <div class="flex items-center gap-4 mb-8">
                        <span class="px-4 py-1.5 bg-secondary/10 text-secondary text-[11px] font-bold rounded-full border border-secondary/20 flex items-center shrink-0 uppercase tracking-wider">
                            <i class="bi bi-ticket-detailed mr-2"></i>My Support Tickets
                        </span>
                        <div class="h-px bg-gray-100 w-full"></div>
                    </div>
                    
                    <?php if ($result_tickets->num_rows > 0): ?>
                        <div class="bg-white rounded-3xl border border-gray-100 overflow-hidden shadow-sm">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-gray-50/50">
                                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Ticket ID</th>
                                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Subject</th>
                                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Category</th>
                                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Priority</th>
                                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Created</th>
                                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <?php while ($ticket = $result_tickets->fetch_assoc()): ?>
                                            <tr class="hover:bg-gray-50/30 transition-colors">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="w-8 h-8 rounded-lg bg-primary/5 text-primary flex items-center justify-center mr-3">
                                                            <i class="bi bi-hash"></i>
                                                        </div>
                                                        <span class="text-sm font-bold text-primary">#<?php echo str_pad($ticket['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm font-bold text-gray-800 line-clamp-1"><?php echo htmlspecialchars($ticket['subject']); ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2.5 py-1 bg-gray-100 text-gray-500 text-[10px] font-bold rounded uppercase">
                                                        <?php echo htmlspecialchars($ticket['category']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php
                                                    $p_styles = match ($ticket['priority']) {
                                                        'urgent' => 'bg-red-50 text-red-600 border-red-100',
                                                        'high' => 'bg-amber-50 text-amber-600 border-amber-100',
                                                        'medium' => 'bg-blue-50 text-blue-600 border-blue-100',
                                                        'low' => 'bg-gray-50 text-gray-500 border-gray-100',
                                                        default => 'bg-gray-50 text-gray-500 border-gray-100'
                                                    };
                                                    ?>
                                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border <?php echo $p_styles; ?>">
                                                        <?php echo strtoupper($ticket['priority']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php
                                                    $s_styles = match ($ticket['status']) {
                                                        'open' => 'bg-amber-50 text-amber-600 border-amber-100',
                                                        'in_progress' => 'bg-blue-50 text-blue-600 border-blue-100',
                                                        'resolved' => 'bg-green-50 text-green-600 border-green-100',
                                                        'closed' => 'bg-gray-50 text-gray-500 border-gray-100',
                                                        default => 'bg-gray-50 text-gray-500 border-gray-100'
                                                    };
                                                    ?>
                                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border <?php echo $s_styles; ?>">
                                                        <?php echo str_replace('_', ' ', strtoupper($ticket['status'])); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-[11px] font-bold text-gray-800"><?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></div>
                                                    <div class="text-[10px] text-gray-400"><?php echo date('h:i A', strtotime($ticket['created_at'])); ?></div>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <button type="button" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-primary/5 text-primary hover:bg-primary hover:text-white transition-all duration-200" onclick="viewTicket(<?php echo $ticket['id']; ?>)">
                                                        <i class="bi bi-eye text-sm"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-20 bg-white rounded-3xl border border-gray-100">
                            <div class="w-20 h-20 bg-gray-50 rounded-3xl flex items-center justify-center mx-auto mb-4">
                                <i class="bi bi-ticket-perforated text-3xl text-gray-200"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-800">No Support Tickets</h3>
                            <p class="text-sm text-gray-500 mt-1 max-w-xs mx-auto">You haven't submitted any support tickets yet.</p>
                            <button type="button" class="mt-6 px-6 py-2.5 bg-secondary text-white text-xs font-bold rounded-xl hover:bg-secondary-dark transition-all shadow-lg shadow-secondary/20 uppercase tracking-widest" onclick="switchTab('support')">
                                <i class="bi bi-plus-lg mr-2"></i>Create Your First Ticket
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ticket Details Modal -->
<div id="ticketModal" class="fixed inset-0 z-[9999] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeModal('ticketModal')"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <div class="bg-gray-50/50 border-b border-gray-100 px-8 py-6 flex items-center justify-between">
                    <h5 class="text-xl font-bold text-primary" id="modal-title">Ticket Details</h5>
                    <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" onclick="closeModal('ticketModal')">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="p-8" id="ticketDetails">
                    <!-- Details will be loaded here -->
                </div>
                <div class="bg-gray-50/50 border-t border-gray-100 px-8 py-6">
                    <button type="button" class="w-full px-6 py-2.5 bg-gray-100 text-gray-600 text-xs font-bold rounded-xl hover:bg-gray-200 transition-all" onclick="closeModal('ticketModal')">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Tab Switching Logic
function switchTab(tabId) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Show selected tab content
    document.getElementById('tab-content-' + tabId).classList.remove('hidden');
    
    // Update tab button styles
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('bg-primary', 'text-white', 'shadow-md', 'shadow-primary/20');
        btn.classList.add('text-gray-500', 'hover:bg-white', 'hover:text-primary');
    });
    
    // Active tab style
    const activeBtn = document.getElementById('tab-btn-' + tabId);
    activeBtn.classList.remove('text-gray-500', 'hover:bg-white', 'hover:text-primary');
    activeBtn.classList.add('bg-primary', 'text-white', 'shadow-md', 'shadow-primary/20');
}

// Accordion Logic
function toggleAccordion(button) {
    const content = button.nextElementSibling;
    const icon = button.querySelector('.bi-chevron-down');
    
    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        icon.classList.add('rotate-180');
        button.classList.add('bg-gray-50');
    } else {
        content.classList.add('hidden');
        icon.classList.remove('rotate-180');
        button.classList.remove('bg-gray-50');
    }
}

// Modal Logic
function openModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function viewTicket(ticketId) {
    document.getElementById('ticketDetails').innerHTML = `
        <div class="text-center py-10">
            <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4 animate-pulse">
                <i class="bi bi-arrow-repeat text-2xl"></i>
            </div>
            <p class="text-sm font-bold text-gray-800">Loading ticket details...</p>
            <p class="text-xs text-gray-400 mt-1">Please wait a moment.</p>
        </div>
    `;
    openModal('ticketModal');
    
    // Simulate API call
    setTimeout(() => {
        // In a real app, you would fetch details via AJAX here
        document.getElementById('ticketDetails').innerHTML = `
            <div class="space-y-4">
                <div class="p-4 bg-blue-50 rounded-2xl border border-blue-100">
                    <p class="text-xs text-blue-600 font-bold uppercase tracking-wider mb-1">Status</p>
                    <p class="text-sm font-bold text-blue-800">In Progress</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-1">Subject</p>
                    <p class="text-sm font-bold text-gray-800">Sample Ticket #${ticketId}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-1">Message</p>
                    <p class="text-sm text-gray-600 leading-relaxed bg-gray-50 p-4 rounded-xl">This is a placeholder for the ticket message. In a production environment, this would display the actual content retrieved from the database.</p>
                </div>
            </div>
        `;
    }, 1000);
}

// Initialize based on URL params
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab && ['faq', 'guides', 'support', 'tickets'].includes(tab)) {
        switchTab(tab);
    }
});
</script>

</body>
</html>
