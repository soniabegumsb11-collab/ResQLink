
<?php

require_once '../includes/auth_check.php';
require_once '../config/database.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$database = new Database();
$db = $database->getConnection();

$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            throw new Exception("Invalid request. Please try again.");
        }

        $requestId = (int)($_POST['relief_request_id'] ?? 0);
        $volunteerId = (int)($_POST['volunteer_id'] ?? 0);
        $resourceId = (int)($_POST['resource_id'] ?? 0);
        $quantity = (int)($_POST['quantity_assigned'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($requestId <= 0 || $volunteerId <= 0 || $resourceId <= 0 || $quantity <= 0) {
            throw new Exception("Please provide all required information correctly.");
        }

        $db->beginTransaction();

        // Check approved relief request
        $stmt = $db->prepare(
            "SELECT id FROM relief_requests
             WHERE id = :id AND status = 'approved'
             FOR UPDATE"
        );

        $stmt->execute([':id' => $requestId]);

        if (!$stmt->fetch()) {
            throw new Exception("This relief request is not approved or no longer available.");
        }

        // Check active volunteer
        $stmt = $db->prepare(
            "SELECT id FROM users
             WHERE id = :id AND role = 'volunteer' AND status = 'active'"
        );

        $stmt->execute([':id' => $volunteerId]);

        if (!$stmt->fetch()) {
            throw new Exception("Selected volunteer is not active.");
        }

        // Check resource stock
        $stmt = $db->prepare(
            "SELECT id, name, quantity_available, unit
             FROM resources
             WHERE id = :id
             FOR UPDATE"
        );

        $stmt->execute([':id' => $resourceId]);
        $resource = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resource) {
            throw new Exception("Selected resource was not found.");
        }

        if ($quantity > $resource['quantity_available']) {
            throw new Exception(
                "Not enough stock. Available: " .
                $resource['quantity_available'] . " " . $resource['unit']
            );
        }

        // Deduct resource quantity
        $stmt = $db->prepare(
            "UPDATE resources
             SET quantity_available = quantity_available - :quantity
             WHERE id = :id AND quantity_available >= :quantity"
        );

        $stmt->execute([
            ':quantity' => $quantity,
            ':id' => $resourceId
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new Exception("Stock update failed. Please try again.");
        }

        // Create assignment
        $stmt = $db->prepare(
            "INSERT INTO assignments
            (relief_request_id, volunteer_id, resource_id,
             quantity_assigned, assigned_by, status, notes)
            VALUES
            (:request_id, :volunteer_id, :resource_id,
             :quantity, :admin_id, 'assigned', :notes)"
        );

        $stmt->execute([
            ':request_id' => $requestId,
            ':volunteer_id' => $volunteerId,
            ':resource_id' => $resourceId,
            ':quantity' => $quantity,
            ':admin_id' => $_SESSION['user_id'],
            ':notes' => $notes !== '' ? $notes : null
        ]);

        // Get new assignment ID
        $assignmentId = $db->lastInsertId();

        // Create notification for volunteer
        $notificationTitle = "New Relief Assignment";

        $notificationMessage =
            "You have been assigned " .
            $quantity . " " . $resource['unit'] .
            " " . $resource['name'] .
            " for Food/Relief Request #" . $requestId . ".";

        $stmt = $db->prepare(
            "INSERT INTO notifications (user_id, title, message)
             VALUES (:user_id, :title, :message)"
        );

        $stmt->execute([
            ':user_id' => $volunteerId,
            ':title' => $notificationTitle,
            ':message' => $notificationMessage
        ]);

        // Insert Activity Log
        $sql = "INSERT INTO activity_logs
                (user_id, action, entity_type, entity_id, details)
                VALUES
                (:user_id, :action, :entity_type, :entity_id, :details)";

        $stmt = $db->prepare($sql);

        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':action' => 'ASSIGN_RESOURCE',
            ':entity_type' => 'assignment',
            ':entity_id' => $assignmentId,
            ':details' => "Admin assigned " .
                $quantity . " " . $resource['unit'] .
                " " . $resource['name'] .
                " to Volunteer ID " . $volunteerId .
                " for Relief Request #" . $requestId . "."
        ]);

        // Save assignment, stock deduction,
        // notification and activity log together
        $db->commit();

        $_SESSION['assignment_message'] =
            "Assignment, notification and activity log created successfully!";

        $_SESSION['assignment_message_type'] = "success";

        header("Location: assignments.php");
        exit;

    } catch (Exception $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $message = $e->getMessage();
        $messageType = "error";
    }
}

// Get flash message
if (isset($_SESSION['assignment_message'])) {

    $message = $_SESSION['assignment_message'];
    $messageType = $_SESSION['assignment_message_type'] ?? 'success';

    unset(
        $_SESSION['assignment_message'],
        $_SESSION['assignment_message_type']
    );
}

// Fetch approved requests
$stmt = $db->prepare(
    "SELECT id, category, location, people_affected
     FROM relief_requests
     WHERE status = 'approved'
     ORDER BY id DESC"
);

$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active volunteers
$stmt = $db->prepare(
    "SELECT id, name
     FROM users
     WHERE role = 'volunteer' AND status = 'active'
     ORDER BY name"
);

$stmt->execute();
$volunteers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch available resources
$stmt = $db->prepare(
    "SELECT id, name, category, quantity_available, unit
     FROM resources
     WHERE quantity_available > 0
     ORDER BY name"
);

$stmt->execute();
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing assignments
$stmt = $db->prepare(
    "SELECT a.*,
            r.category,
            r.location,
            u.name AS volunteer_name,
            res.name AS resource_name,
            res.unit
     FROM assignments a
     JOIN relief_requests r ON a.relief_request_id = r.id
     JOIN users u ON a.volunteer_id = u.id
     JOIN resources res ON a.resource_id = res.id
     ORDER BY a.id DESC"
);

$stmt->execute();
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

    <title>Manage Assignments - ResQLink</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Manage Assignments</h2>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if ($message !== ""): ?>

        <div class="alert <?php echo $messageType === 'success' ? 'alert-success' : 'alert-danger'; ?>">
            <?php echo e($message); ?>
        </div>

    <?php endif; ?>

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Create New Assignment</h5>
        </div>

        <div class="card-body">

            <form method="POST" action="assignments.php">

                <input type="hidden"
                       name="csrf_token"
                       value="<?php echo e($_SESSION['csrf_token']); ?>">

                <div class="mb-3">
                    <label class="form-label">Approved Relief Request</label>

                    <select name="relief_request_id" class="form-select" required>
                        <option value="">Select Request</option>

                        <?php foreach ($requests as $request): ?>

                            <option value="<?php echo e($request['id']); ?>">
                                <?php echo e(
                                    '#' . $request['id'] . ' - ' .
                                    $request['category'] . ' - ' .
                                    $request['location'] . ' - ' .
                                    $request['people_affected'] . ' people'
                                ); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Select Volunteer</label>

                    <select name="volunteer_id" class="form-select" required>
                        <option value="">Select Volunteer</option>

                        <?php foreach ($volunteers as $volunteer): ?>

                            <option value="<?php echo e($volunteer['id']); ?>">
                                <?php echo e($volunteer['name']); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Select Resource</label>

                    <select name="resource_id" class="form-select" required>
                        <option value="">Select Resource</option>

                        <?php foreach ($resources as $resource): ?>

                            <option value="<?php echo e($resource['id']); ?>">
                                <?php echo e(
                                    $resource['name'] . ' - ' .
                                    $resource['quantity_available'] . ' ' .
                                    $resource['unit'] . ' available'
                                ); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Quantity to Assign</label>

                    <input type="number"
                           name="quantity_assigned"
                           class="form-control"
                           min="1"
                           required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Notes (Optional)</label>

                    <textarea name="notes"
                              class="form-control"
                              rows="3"
                              placeholder="Write assignment instructions..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    Assign Resource
                </button>

            </form>

        </div>
    </div>

    <div class="card shadow-sm">

        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">All Assignments</h5>
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-striped align-middle">

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Request</th>
                            <th>Volunteer</th>
                            <th>Resource</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>Assigned At</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (count($assignments) > 0): ?>

                            <?php foreach ($assignments as $assignment): ?>

                                <tr>

                                    <td><?php echo e($assignment['id']); ?></td>

                                    <td>
                                        <?php echo e(
                                            '#' . $assignment['relief_request_id'] .
                                            ' - ' . $assignment['category']
                                        ); ?>
                                    </td>

                                    <td><?php echo e($assignment['volunteer_name']); ?></td>

                                    <td><?php echo e($assignment['resource_name']); ?></td>

                                    <td>
                                        <?php echo e(
                                            $assignment['quantity_assigned'] . ' ' .
                                            $assignment['unit']
                                        ); ?>
                                    </td>

                                    <td><?php echo e($assignment['status']); ?></td>

                                    <td><?php echo e($assignment['assigned_at']); ?></td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="7" class="text-center">
                                    No assignments found.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>
        </div>
    </div>

</div>

</body>
</html>
