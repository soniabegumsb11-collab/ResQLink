
<?php

require_once '../includes/auth_check.php';
require_once '../config/database.php';

if ($_SESSION['role'] !== 'volunteer') {
    header("Location: ../index.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

$volunteer_id = $_SESSION['user_id'];

$message = "";

// Create CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle delivery update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            throw new Exception("Invalid request. Please try again.");
        }

        $assignment_id = (int)($_POST['assignment_id'] ?? 0);

        if ($assignment_id <= 0) {
            throw new Exception("Invalid assignment.");
        }

        // Start database transaction
        $db->beginTransaction();

        // Get assignment and volunteer details
        $sql = "SELECT
                    a.id,
                    a.assigned_by,
                    u.name AS volunteer_name
                FROM assignments a
                INNER JOIN users u
                    ON a.volunteer_id = u.id
                WHERE a.id = :assignment_id
                AND a.volunteer_id = :volunteer_id
                AND a.status = 'assigned'
                FOR UPDATE";

        $stmt = $db->prepare($sql);

        $stmt->execute([
            ':assignment_id' => $assignment_id,
            ':volunteer_id' => $volunteer_id
        ]);

        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assignment) {
            throw new Exception(
                "Assignment could not be updated. Please check its status."
            );
        }

        // Update assignment delivery status
        $sql = "UPDATE assignments
                SET status = 'delivered',
                    delivered_at = CURRENT_TIMESTAMP
                WHERE id = :assignment_id
                AND volunteer_id = :volunteer_id
                AND status = 'assigned'";

        $stmt = $db->prepare($sql);

        $stmt->execute([
            ':assignment_id' => $assignment_id,
            ':volunteer_id' => $volunteer_id
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new Exception("Delivery update failed.");
        }

        // Create notification for admin
        $notificationTitle = "Relief Delivery Completed";

        $notificationMessage =
            "Volunteer " . $assignment['volunteer_name'] .
            " has completed delivery for Assignment #" .
            $assignment_id . ".";

        $sql = "INSERT INTO notifications
                    (user_id, title, message)
                VALUES
                    (:user_id, :title, :message)";

        $stmt = $db->prepare($sql);

        $stmt->execute([
            ':user_id' => $assignment['assigned_by'],
            ':title' => $notificationTitle,
            ':message' => $notificationMessage
        ]);

        // Create Activity Log
        $logAction = "COMPLETE_DELIVERY";
        $entityType = "assignment";

        $logDetails =
            "Volunteer " . $assignment['volunteer_name'] .
            " completed delivery for Assignment #" .
            $assignment_id . ".";

        $sql = "INSERT INTO activity_logs
                    (user_id, action, entity_type, entity_id, details)
                VALUES
                    (:user_id, :action, :entity_type, :entity_id, :details)";

        $stmt = $db->prepare($sql);

        $stmt->execute([
            ':user_id' => $volunteer_id,
            ':action' => $logAction,
            ':entity_type' => $entityType,
            ':entity_id' => $assignment_id,
            ':details' => $logDetails
        ]);

        // Save all changes
        $db->commit();

        $_SESSION['delivery_message'] =
            "Delivery completed successfully! Admin has been notified.";

        header("Location: assignments.php");
        exit;

    } catch (Exception $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $message = $e->getMessage();
    }
}

// Get flash message
if (isset($_SESSION['delivery_message'])) {
    $message = $_SESSION['delivery_message'];
    unset($_SESSION['delivery_message']);
}

// Fetch volunteer assignments
$sql = "SELECT
            a.id AS assignment_id,
            a.quantity_assigned,
            a.status AS assignment_status,
            a.assigned_at,
            a.delivered_at,
            a.notes,
            rr.category,
            rr.description,
            rr.people_affected,
            rr.urgency_level,
            rr.priority_score,
            rr.location,
            r.name AS resource_name
        FROM assignments a
        INNER JOIN relief_requests rr
            ON a.relief_request_id = rr.id
        LEFT JOIN resources r
            ON a.resource_id = r.id
        WHERE a.volunteer_id = :volunteer_id
        ORDER BY a.assigned_at DESC";

$stmt = $db->prepare($sql);

$stmt->execute([
    ':volunteer_id' => $volunteer_id
]);

$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Assignments - ResQLink</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-4">

    <h1 class="mb-3">My Relief Assignments</h1>

    <p>Welcome, <?php echo e($_SESSION['name']); ?>!</p>

    <?php if ($message !== ""): ?>
        <div class="alert alert-info">
            <?php echo e($message); ?>
        </div>
    <?php endif; ?>

    <hr>

    <?php if (count($assignments) > 0): ?>

        <div class="table-responsive">

            <table class="table table-bordered table-striped align-middle">

                <thead class="table-dark">
                    <tr>
                        <th>Assignment ID</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>People Affected</th>
                        <th>Urgency</th>
                        <th>Priority Score</th>
                        <th>Location</th>
                        <th>Resource</th>
                        <th>Quantity</th>
                        <th>Status</th>
                        <th>Assigned Date</th>
                        <th>Delivered Date</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($assignments as $assignment): ?>

                        <tr>

                            <td><?php echo e($assignment['assignment_id']); ?></td>
                            <td><?php echo e($assignment['category']); ?></td>
                            <td><?php echo e($assignment['description']); ?></td>
                            <td><?php echo e($assignment['people_affected']); ?></td>
                            <td><?php echo e($assignment['urgency_level']); ?></td>
                            <td><?php echo e($assignment['priority_score']); ?></td>
                            <td><?php echo e($assignment['location']); ?></td>

                            <td>
                                <?php echo e($assignment['resource_name'] ?? 'Not specified'); ?>
                            </td>

                            <td><?php echo e($assignment['quantity_assigned']); ?></td>

                            <td>
                                <?php if ($assignment['assignment_status'] === 'delivered'): ?>

                                    <span class="badge bg-success">Delivered</span>

                                <?php else: ?>

                                    <span class="badge bg-warning text-dark">
                                        <?php echo e($assignment['assignment_status']); ?>
                                    </span>

                                <?php endif; ?>
                            </td>

                            <td><?php echo e($assignment['assigned_at']); ?></td>

                            <td>
                                <?php
                                echo $assignment['delivered_at']
                                    ? e($assignment['delivered_at'])
                                    : 'Not delivered yet';
                                ?>
                            </td>

                            <td>

                                <?php if ($assignment['assignment_status'] === 'assigned'): ?>

                                    <form method="POST"
                                          action="assignments.php"
                                          onsubmit="return confirm('Have you completed this delivery?');">

                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?php echo e($_SESSION['csrf_token']); ?>">

                                        <input type="hidden"
                                               name="assignment_id"
                                               value="<?php echo e($assignment['assignment_id']); ?>">

                                        <button type="submit" class="btn btn-success btn-sm">
                                            Mark as Delivered
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span class="text-success">Completed</span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php else: ?>

        <div class="alert alert-info">
            You do not have any assigned relief tasks yet.
        </div>

    <?php endif; ?>

    <br>

    <a href="dashboard.php" class="btn btn-secondary">
        Back to Dashboard
    </a>

    <a href="../auth/logout.php" class="btn btn-danger">
        Logout
    </a>

</div>

</body>
</html>
