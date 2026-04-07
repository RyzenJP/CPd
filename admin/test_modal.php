<?php
include 'admin_sidebar.php';
$page_title = 'Test Modal';
include '../plugins/conn.php';
include 'admin_navbar.php';
?>

<div class="main-content">
    <div class="container-fluid py-4">
        <h4 class="mb-3">Modal Click Test</h4>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#testSettingsModal">
            Open Test Modal
        </button>
    </div>
</div>

<div class="modal fade report-modal" id="testSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Test Modal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>If this text, the inputs, and the buttons are clickable, the modal itself is working.</p>
                <div class="mb-3">
                    <label class="form-label">Sample Input</label>
                    <input type="text" class="form-control" placeholder="Type here to test focus">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">Test Button</button>
            </div>
        </div>
    </div>
</div>
