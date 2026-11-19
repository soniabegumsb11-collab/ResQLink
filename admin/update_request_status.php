
<?php

require_once '../includes/auth_check.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

if (
    !isset($_POST['request_id']) ||
    !isset($_POST['status']) ||
    !isset($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token or request.'
    ]);
    exit;
}

$request_id = (int) $_POST['request_id'];
$status = $_POST['status'];

if (
    $request_id <= 0 ||
    !in_array($status, ['approved', 'rejected'], true)
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request information.'
    ]);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {

    $db->beginTransaction();

    $sql = "SELECT requester_id
            FROM relief_requests
            WHERE id = :id
            AND status = 'pending'
            FOR UPDATE";

    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $request_id]);

    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new Exception("Request not found or already processed.");
    }

    $requester_id = $request['requester_id'];

    $sql = "UPDATE relief_requests
            SET status = :status,
                verified_by = :admin_id,
                verified_at = NOW()
            WHERE id = :id
            AND status = 'pending'";

    $stmt = $db->prepare($sql);

    $stmt->execute([
        ':status' => $status,
        ':admin_id' => $_SESSION['user_id'],
        ':id' => $request_id
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new Exception("Request status could not be updated.");
    }

    if ($status === 'approved') {

        $notificationTitle = "Relief Request Approved";

        $notificationMessage =
            "Your relief request #" . $request_id .
            " has been approved by the admin.";

        $activityAction = "APPROVE_REQUEST";

        $activityDetails =
            "Admin approved relief request #" . $request_id . ".";

    } else {

        $notificationTitle = "Relief Request Rejected";

        $notificationMessage =
            "Your relief request #" . $request_id .
            " has been rejected by the admin.";

        $activityAction = "REJECT_REQUEST";

        $activityDetails =
            "Admin rejected relief request #" . $request_id . ".";

    }

    $sql = "INSERT INTO notifications
                (user_id, title, message)
            VALUES
                (:user_id, :title, :message)";

    $stmt = $db->prepare($sql);

    $stmt->execute([
        ':user_id' => $requester_id,
        ':title' => $notificationTitle,
        ':message' => $notificationMessage
    ]);

    $sql = "INSERT INTO activity_logs
                (user_id, action, entity_type, entity_id, details)
            VALUES
                (:user_id, :action, :entity_type, :entity_id, :details)";

    $stmt = $db->prepare($sql);

    $stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':action' => $activityAction,
        ':entity_type' => 'relief_request',
        ':entity_id' => $request_id,
        ':details' => $activityDetails
    ]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Relief request ' . $status . ' successfully! Notification sent and activity logged.',
        'status' => $status,
        'request_id' => $request_id
    ]);

} catch (Exception $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit;


?>
