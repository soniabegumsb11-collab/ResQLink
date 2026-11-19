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

// Get assignment statistics
$sql = "SELECT
            COUNT(*) AS total_assignments,
            COALESCE(SUM(status = 'assigned'), 0) AS pending_assignments,
            COALESCE(SUM(status = 'delivered'), 0) AS completed_assignments
        FROM assignments
        WHERE volunteer_id = :volunteer_id";

$stmt = $db->prepare($sql);

$stmt->execute([
    ':volunteer_id' => $volunteer_id
]);

$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get unread notification count
$sql = "SELECT COUNT(*)
        FROM notifications
        WHERE user_id = :user_id
        AND is_read = 0";

$stmt = $db->prepare($sql);

$stmt->execute([
    ':user_id' => $volunteer_id
]);

$unread_notifications = $stmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Volunteer Dashboard - ResQLink</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-4">

    <h1 class="mb-3">Volunteer Dashboard</h1>

    <h2>Welcome, <?= htmlspecialchars($_SESSION['name']) ?>!</h2>

    <p>You are logged in as a Volunteer.</p>

    <hr>

    <h3>Dashboard Overview</h3>

    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card p-3">
                <h5>Total Assignments</h5>
                <h3><?= htmlspecialchars((string)$stats['total_assignments']) ?></h3>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-3">
                <h5>Pending Assignments</h5>
                <h3><?= htmlspecialchars((string)$stats['pending_assignments']) ?></h3>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-3">
                <h5>Completed Assignments</h5>
                <h3><?= htmlspecialchars((string)$stats['completed_assignments']) ?></h3>
            </div>
        </div>

    </div>

    <hr>

    <h3>Quick Actions</h3>

    <div class="d-flex flex-wrap gap-2">

        <a href="assignments.php" class="btn btn-primary">
            My Assignments
        </a>

        <a href="../notifications/index.php" class="btn btn-warning">
            My Notifications
            (<?= htmlspecialchars((string)$unread_notifications) ?> Unread)
        </a>

        <a href="../profile/edit_profile.php" class="btn btn-secondary">
            Edit My Profile
        </a>

        <a href="../profile/change_password.php" class="btn btn-dark">
            Change Password
        </a>

        <a href="../auth/logout.php" class="btn btn-danger">
            Logout
        </a>

    </div>

</div>

</body>
</html>