
<?php

require_once '../includes/auth_check.php';
require_once '../config/database.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {

    $sql = "SELECT
                r.id,
                u.name AS requester_name,
                r.category,
                r.description,
                r.people_affected,
                r.urgency_level,
                r.priority_score,
                r.location,
                r.status,
                r.created_at
            FROM relief_requests r
            JOIN users u ON r.requester_id = u.id
            ORDER BY r.priority_score DESC";

    $stmt = $db->query($sql);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=resqlink_relief_report.csv');

    $output = fopen('php://output', 'w');

    // CSV Header
    fputcsv($output, [
        'Request ID',
        'Requester',
        'Category',
        'Description',
        'People Affected',
        'Urgency',
        'Priority Score',
        'Location',
        'Status',
        'Date'
    ], ',', '"', '');

    // CSV Data
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row, ',', '"', '');
    }

    fclose($output);
    exit;
}

// Report Statistics
$stmt = $db->query("SELECT COUNT(*) FROM relief_requests");
$total_requests = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM relief_requests WHERE status = 'pending'");
$pending_requests = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM relief_requests WHERE status = 'approved'");
$approved_requests = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM relief_requests WHERE status = 'rejected'");
$rejected_requests = $stmt->fetchColumn();

// Get all requests
$sql = "SELECT
            r.id,
            u.name AS requester_name,
            r.category,
            r.urgency_level,
            r.priority_score,
            r.location,
            r.status,
            r.created_at
        FROM relief_requests r
        JOIN users u ON r.requester_id = u.id
        ORDER BY r.created_at DESC";

$stmt = $db->query($sql);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reports - ResQLink</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-4">

    <h1>Relief Request Reports</h1>

    <p>Welcome, <?= htmlspecialchars($_SESSION['name']) ?>!</p>

    <hr>

    <h3>Report Overview</h3>

    <div class="row g-3 mb-4">

        <div class="col-md-3">
            <div class="card p-3">
                <h5>Total Requests</h5>
                <h3><?= (int)$total_requests ?></h3>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3">
                <h5>Pending</h5>
                <h3><?= (int)$pending_requests ?></h3>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3">
                <h5>Approved</h5>
                <h3><?= (int)$approved_requests ?></h3>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3">
                <h5>Rejected</h5>
                <h3><?= (int)$rejected_requests ?></h3>
            </div>
        </div>

    </div>

    <div class="mb-3">

        <a href="reports.php?export=csv" class="btn btn-success">
            Download CSV Report
        </a>

        <a href="dashboard.php" class="btn btn-secondary">
            Back to Dashboard
        </a>

    </div>

    <h3 class="mt-4">All Relief Requests</h3>

    <div class="table-responsive">

        <table class="table table-bordered table-striped bg-white">

            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Requester</th>
                    <th>Category</th>
                    <th>Urgency</th>
                    <th>Priority</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>

            <tbody>

                <?php if (count($requests) > 0): ?>

                    <?php foreach ($requests as $request): ?>

                        <tr>
                            <td><?= (int)$request['id'] ?></td>
                            <td><?= htmlspecialchars($request['requester_name']) ?></td>
                            <td><?= htmlspecialchars($request['category']) ?></td>
                            <td><?= htmlspecialchars($request['urgency_level']) ?></td>
                            <td><?= (int)$request['priority_score'] ?></td>
                            <td><?= htmlspecialchars($request['location']) ?></td>
                            <td><?= htmlspecialchars($request['status']) ?></td>
                            <td><?= htmlspecialchars($request['created_at']) ?></td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="text-center">
                            No relief requests found.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>
