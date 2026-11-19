
<?php

require_once '../includes/auth_check.php';
require_once '../config/database.php';

if ($_SESSION['role'] !== 'requester') {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my_requests.php");
    exit;
}

if (
    !isset($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    $_SESSION['delete_message'] = "Invalid request. Please try again.";
    header("Location: my_requests.php");
    exit;
}

$request_id = isset($_POST['request_id'])
    ? (int) $_POST['request_id']
    : 0;

if ($request_id < 1) {
    $_SESSION['delete_message'] = "Invalid request ID.";
    header("Location: my_requests.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

$sql = "DELETE FROM relief_requests
        WHERE id = :id
        AND requester_id = :requester_id
        AND status = 'pending'";

$stmt = $db->prepare($sql);

$stmt->execute([
    ':id' => $request_id,
    ':requester_id' => $_SESSION['user_id']
]);

if ($stmt->rowCount() > 0) {
    $_SESSION['delete_message'] = "Relief request deleted successfully!";
} else {
    $_SESSION['delete_message'] = "Request could not be deleted.";
}

header("Location: my_requests.php");
exit;

?>
