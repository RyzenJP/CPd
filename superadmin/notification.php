<?php
declare(strict_types=1);

session_start();
include_once "../plugins/conn.php";

header('Content-Type: application/json; charset=utf-8');

/**
 * Send a JSON response and terminate the script.
 *
 * @param int   $statusCode HTTP status code to send
 * @param array $payload    Data to encode as JSON
 */
function sendJsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

/**
 * Ensure the current user is authenticated and authorized.
 */
function ensureAuthorized(): void
{
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        sendJsonResponse(401, [
            'success' => false,
            'message' => 'Unauthorized',
        ]);
    }

    if (!in_array($_SESSION['role'], ['superadmin', 'admin'], true)) {
        sendJsonResponse(403, [
            'success' => false,
            'message' => 'Forbidden',
        ]);
    }
}

/**
 * Parse and validate the incoming JSON request body.
 *
 * @return array<string,mixed>
 */
function getJsonBody(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || $raw === '') {
        return [];
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        sendJsonResponse(400, [
            'success' => false,
            'message' => 'Invalid JSON payload',
        ]);
    }

    return $data;
}

ensureAuthorized();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, [
        'success' => false,
        'message' => 'Method not allowed',
    ]);
}

/** @var mysqli $conn */
$data = getJsonBody();

if (isset($data['action']) && $data['action'] === 'mark_all_read') {
    $sql = "UPDATE requests SET is_read = 1 WHERE status = 'Pending' AND is_read = 0";

    if ($conn->query($sql) === true) {
        sendJsonResponse(200, ['success' => true]);
    }

    sendJsonResponse(500, [
        'success' => false,
        'message' => 'Database error while marking all as read',
    ]);
}

if (isset($data['id'])) {
    $id = filter_var($data['id'], FILTER_VALIDATE_INT);

    if ($id === false || $id <= 0) {
        sendJsonResponse(400, [
            'success' => false,
            'message' => 'Invalid notification identifier',
        ]);
    }

    $stmt = $conn->prepare('UPDATE requests SET is_read = 1 WHERE id = ?');

    if ($stmt === false) {
        sendJsonResponse(500, [
            'success' => false,
            'message' => 'Database error preparing statement',
        ]);
    }

    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        sendJsonResponse(200, ['success' => true]);
    }

    sendJsonResponse(500, [
        'success' => false,
        'message' => 'Database error while marking as read',
    ]);
}

sendJsonResponse(400, [
    'success' => false,
    'message' => 'Invalid request payload',
]);
?>
