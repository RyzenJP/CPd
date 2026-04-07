<?php
include 'admin_sidebar.php';
$page_title = 'Edit Item';
include '../plugins/conn.php';
include 'admin_navbar.php';

if (!isset($_GET['id'])) {
    header("Location: inventory_list.php");
    exit();
}

$id = $_GET['id'];
$sql = "SELECT * FROM items WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();

if (!$item) {
    header("Location: inventory_list.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $item_name = $_POST['item'];
    $description = $_POST['description'];
    $stock_no = $_POST['stock_no'];
    $unit_measurement = $_POST['unit_measurement'];
    $unit_value = $_POST['unit_value'];
    $item_type = $_POST['item_type'];
    $status = $_POST['status'];

    $stmt_update = $conn->prepare("UPDATE items SET item=?, description=?, stock_no=?, unit_measurement=?, unit_value=?, item_type=?, status=? WHERE id=?");
    $stmt_update->bind_param("ssssdssi", $item_name, $description, $stock_no, $unit_measurement, $unit_value, $item_type, $status, $id);

    if ($stmt_update->execute()) {
        echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Item updated successfully',
                        icon: 'success'
                    }).then((result) => {
                        window.location.href = 'inventory_list.php';
                    });
                });
              </script>";
    } else {
        echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Update failed',
                        icon: 'error'
                    });
                });
              </script>";
    }
}
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-primary fw-bold">Edit Item</h2>
            <a href="inventory_list.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i> Back to List
            </a>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="" id="editItemForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="item" class="form-label">Item <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="item" name="item" value="<?php echo htmlspecialchars($item['item']); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="stock_no" class="form-label">Stock No.</label>
                            <input type="text" class="form-control" id="stock_no" name="stock_no" value="<?php echo htmlspecialchars($item['stock_no']); ?>">
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($item['description']); ?></textarea>
                        </div>

                        <div class="col-md-4">
                            <label for="unit_measurement" class="form-label">Unit of Measurement <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="unit_measurement" name="unit_measurement" value="<?php echo htmlspecialchars($item['unit_measurement']); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label for="unit_value" class="form-label">Unit Value (₱) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="unit_value" name="unit_value" value="<?php echo $item['unit_value']; ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label for="balance_qty" class="form-label">Balance Qty (Read Only)</label>
                            <input type="text" class="form-control bg-light" value="<?php echo $item['balance_qty']; ?>" readonly>
                            <div class="form-text">To adjust quantity, use the Transaction module.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="item_type" class="form-label">Item Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="item_type" name="item_type" required>
                                <option value="Expendable" <?php if($item['item_type'] == 'Expendable') echo 'selected'; ?>>Expendable</option>
                                <option value="Semi-Expendable" <?php if($item['item_type'] == 'Semi-Expendable') echo 'selected'; ?>>Semi-Expendable</option>
                                <option value="Non-Expendable" <?php if($item['item_type'] == 'Non-Expendable') echo 'selected'; ?>>Non-Expendable</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="Active" <?php if($item['status'] == 'Active') echo 'selected'; ?>>Active</option>
                                <option value="Condemned" <?php if($item['status'] == 'Condemned') echo 'selected'; ?>>Condemned</option>
                                <option value="Transferred" <?php if($item['status'] == 'Transferred') echo 'selected'; ?>>Transferred</option>
                                <option value="Lost" <?php if($item['status'] == 'Lost') echo 'selected'; ?>>Lost</option>
                            </select>
                        </div>

                        <div class="col-12 mt-4">
                            <button type="button" class="btn btn-primary btn-lg" id="btnUpdateItem">
                                <i class="bi bi-save me-2"></i> Update Item
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'admin_footer.php'; ?>
<script>
document.getElementById('btnUpdateItem').addEventListener('click', function () {
    const form = document.getElementById('editItemForm');

    // Trigger browser's native validation first
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const itemName = document.getElementById('item').value;

    Swal.fire({
        title: 'Update Item?',
        html: 'You are about to save changes to <strong>' + itemName + '</strong>.<br>Do you want to continue?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1a237e',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-save me-1"></i> Yes, Update',
        cancelButtonText: '<i class="bi bi-x me-1"></i> Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state on button
            const btn = document.getElementById('btnUpdateItem');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Saving...';

            form.submit();
        }
    });
});
</script>
