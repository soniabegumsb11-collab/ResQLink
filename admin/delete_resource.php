
<?php

require_once '../includes/auth_check.php';
require_once '../config/database.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: resources.php");
    exit;
}

if (
    !isset($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    die("Invalid request.");
}

$resource_id = filter_var(
    $_POST['resource_id'] ?? '',
    FILTER_VALIDATE_INT
);

if (!$resource_id || $resource_id < 1) {
    $_SESSION['resource_message'] = "Invalid resource ID.";
    header("Location: resources.php");
    exit;
}

try {

    $db->beginTransaction();

    // Fetch Resource Details
    $sql = "SELECT * FROM resources WHERE id = :id FOR UPDATE";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $resource_id]);

    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resource) {

        $db->rollBack();

        $_SESSION['resource_message'] = "Resource not found.";

    } else {

        // Check whether resource is used in an assignment
        $sql = "SELECT COUNT(*) FROM assignments WHERE resource_id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $resource_id]);

        $assignment_count = $stmt->fetchColumn();

        if ($assignment_count > 0) {

            $db->rollBack();

            $_SESSION['resource_message'] =
                "Cannot delete this resource because it is used in an assignment.";

        } else {

            // Delete Resource
            $sql = "DELETE FROM resources WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $resource_id]);

            if ($stmt->rowCount() > 0) {

                // Insert Activity Log
                $sql = "INSERT INTO activity_logs
                        (user_id, action, entity_type, entity_id, details)
                        VALUES
                        (:user_id, :action, :entity_type, :entity_id, :details)";

                $stmt = $db->prepare($sql);

                $stmt->execute([
                    ':user_id' => $_SESSION['user_id'],
                    ':action' => 'DELETE_RESOURCE',
                    ':entity_type' => 'resource',
                    ':entity_id' => $resource_id,
                    ':details' => "Admin deleted resource: " .
                        $resource['name'] .
                        ", Category: " . $resource['category'] .
                        ", Quantity: " . $resource['quantity_available'] .
                        " " . $resource['unit'] . "."
                ]);

                $db->commit();

                $_SESSION['resource_message'] =
                    "Resource deleted successfully and activity logged!";

            } else {

                $db->rollBack();

                $_SESSION['resource_message'] = "Resource not found.";
            }
        }
    }

} catch (Exception $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    $_SESSION['resource_message'] =
        "Resource could not be deleted. Please try again.";
}

header("Location: resources.php");
exit;

?>
