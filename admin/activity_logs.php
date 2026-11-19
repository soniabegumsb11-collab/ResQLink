
<?php

require_once '../includes/auth_check.php';
require_once '../config/database.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Get all activity logs with user information
$sql = "SELECT
            activity_logs.id,
            activity_logs.action,
            activity_logs.entity_type,
            activity_logs.entity_id,
            activity_logs.details,
            activity_logs.created_at,
            users.name AS user_name,
            users.role AS user_role
        FROM activity_logs
        INNER JOIN users
        ON activity_logs.user_id = users.id
        ORDER BY activity_logs.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute();

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Activity Logs - ResQLink</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-4">

    <h1>Activity Logs</h1>

    <p>Welcome, <?= htmlspecialchars($_SESSION['name']) ?>!</p>

    <hr>

    <h3>System Activity History</h3>

    <div class="card p-3">

        <div class="table-responsive">

            <table class="table table-bordered table-striped">

                <thead class="table-dark">

                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Entity Type</th>
                        <th>Entity ID</th>
                        <th>Details</th>
                        <th>Date & Time</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (count($logs) > 0): ?>

                        <?php foreach ($logs as $log): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars((string)$log['id']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($log['user_name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($log['user_role']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($log['action']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($log['entity_type']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string)($log['entity_id'] ?? 'N/A')) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($log['details'] ?? '') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($log['created_at']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8" class="text-center">
                                No activity logs found.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <br>

    <a href="dashboard.php" class="btn btn-primary">
        Back to Admin Dashboard
    </a>

</div>

</body>
</html>
