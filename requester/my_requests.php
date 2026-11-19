
<?php

require_once '../includes/auth_check.php';
require_once '../config/database.php';

if ($_SESSION['role'] !== 'requester') {
    header("Location: ../index.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Delete Message
$delete_message = $_SESSION['delete_message'] ?? '';
unset($_SESSION['delete_message']);

// Fetch Requests
$sql = "SELECT * FROM relief_requests
        WHERE requester_id = :requester_id
        ORDER BY created_at DESC";

$stmt = $db->prepare($sql);

$stmt->execute([
    ':requester_id' => $user_id
]);

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Request Statistics
$total_requests = count($requests);
$pending_requests = 0;
$approved_requests = 0;
$rejected_requests = 0;

foreach ($requests as $request) {
    if ($request['status'] === 'pending') {
        $pending_requests++;
    } elseif ($request['status'] === 'approved') {
        $approved_requests++;
    } elseif ($request['status'] === 'rejected') {
        $rejected_requests++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Relief Requests - ResQLink</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f4f7fb;
            font-family: Arial, sans-serif;
            color: #26364a;
        }

        .sidebar {
            width: 250px;
            min-height: 100vh;
            background: #ffffff;
            border-right: 1px solid #e5eaf1;
            position: fixed;
            top: 0;
            left: 0;
            padding: 28px 18px;
        }

        .brand {
            font-size: 25px;
            font-weight: 800;
            color: #1769d2;
            margin-bottom: 5px;
        }

        .tagline {
            font-size: 10px;
            letter-spacing: 1.3px;
            color: #8492a6;
            margin-bottom: 38px;
        }

        .menu-title {
            font-size: 11px;
            font-weight: 700;
            color: #9aa6b5;
            letter-spacing: 1px;
            margin: 22px 10px 12px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #637187;
            padding: 12px 14px;
            margin-bottom: 6px;
            border-radius: 9px;
            font-size: 14px;
            transition: 0.2s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #eaf3ff;
            color: #1769d2;
            font-weight: 600;
        }

        .sidebar a i {
            font-size: 17px;
        }

        .main-content {
            margin-left: 250px;
            padding: 32px;
        }

        .top-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .top-heading h1 {
            font-size: 27px;
            font-weight: 750;
            color: #20344f;
        }

        .top-heading p {
            color: #8290a3;
            font-size: 14px;
            margin-top: 6px;
        }

        .user-info {
            background: #ffffff;
            padding: 10px 17px;
            border-radius: 12px;
            border: 1px solid #e5eaf1;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        .welcome-banner {
            background: linear-gradient(120deg, #1769d2, #2998d5);
            color: white;
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 25px;
            box-shadow: 0 8px 20px rgba(23, 105, 210, 0.12);
        }

        .welcome-banner h2 {
            font-size: 23px;
            font-weight: 700;
            margin-bottom: 9px;
        }

        .welcome-banner p {
            font-size: 14px;
            opacity: 0.92;
            margin: 0;
            line-height: 1.6;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #e8edf4;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 3px 12px rgba(30, 55, 90, 0.035);
        }

        .stat-icon {
            width: 43px;
            height: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            font-size: 20px;
            margin-bottom: 15px;
        }

        .blue {
            background: #e8f1ff;
            color: #1769d2;
        }

        .orange {
            background: #fff2df;
            color: #df8b16;
        }

        .green {
            background: #e5f8ed;
            color: #239b5a;
        }

        .red {
            background: #ffe9e9;
            color: #d94c4c;
        }

        .stat-card h3 {
            font-size: 27px;
            font-weight: 750;
            color: #20344f;
            margin-bottom: 5px;
        }

        .stat-card p {
            color: #8492a6;
            font-size: 13px;
            margin: 0;
        }

        .content-card {
            background: #ffffff;
            border: 1px solid #e7ecf3;
            border-radius: 15px;
            padding: 24px;
            box-shadow: 0 3px 12px rgba(30, 55, 90, 0.035);
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
        }

        .section-heading h3 {
            font-size: 19px;
            font-weight: 700;
            color: #20344f;
            margin: 0;
        }

        .section-heading p {
            font-size: 13px;
            color: #8492a6;
            margin: 5px 0 0;
        }

        .btn-create {
            background: #1769d2;
            color: white;
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        .btn-create:hover {
            background: #1056b4;
            color: white;
        }

        .table-responsive {
            border-radius: 10px;
        }

        .table {
            min-width: 1100px;
            vertical-align: middle;
            margin-bottom: 0;
        }

        .table thead th {
            background: #f4f7fb;
            color: #607087;
            font-size: 12px;
            font-weight: 700;
            padding: 15px 12px;
            border-bottom: 1px solid #e7ecf3;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 15px 12px;
            font-size: 13px;
            color: #53647a;
            border-bottom: 1px solid #edf0f5;
        }

        .table tbody tr:hover {
            background: #f9fbfe;
        }

        .description-cell {
            min-width: 230px;
            max-width: 300px;
            line-height: 1.5;
        }

        .location-cell {
            min-width: 150px;
        }

        .status-badge,
        .urgency-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .status-pending {
            background: #fff2dc;
            color: #bd7911;
        }

        .status-approved {
            background: #e2f7eb;
            color: #21864e;
        }

        .status-rejected {
            background: #ffe6e6;
            color: #c83e3e;
        }

        .status-other {
            background: #edf1f6;
            color: #52647a;
        }

        .urgency-low {
            background: #e8f4ff;
            color: #2475b9;
        }

        .urgency-medium {
            background: #fff3dc;
            color: #bb801b;
        }

        .urgency-high {
            background: #ffe9dc;
            color: #d2672d;
        }

        .urgency-critical {
            background: #ffe4e7;
            color: #cb344c;
        }

        .priority-score {
            font-weight: 750;
            color: #1769d2;
        }

        .action-buttons {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .btn-edit,
        .btn-delete {
            border: none;
            border-radius: 7px;
            padding: 7px 10px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-edit {
            background: #e8f1ff;
            color: #1769d2;
        }

        .btn-edit:hover {
            background: #d7e8ff;
            color: #1056b4;
        }

        .btn-delete {
            background: #ffeaea;
            color: #d44848;
        }

        .btn-delete:hover {
            background: #ffdada;
        }

        .not-editable {
            color: #9aa6b5;
            font-size: 12px;
            white-space: nowrap;
        }

        .empty-state {
            text-align: center;
            padding: 55px 20px;
        }

        .empty-state i {
            font-size: 48px;
            color: #a9bfdc;
        }

        .empty-state h4 {
            margin-top: 17px;
            color: #30445e;
            font-weight: 700;
        }

        .empty-state p {
            color: #8492a6;
            font-size: 14px;
            margin: 10px 0 20px;
        }

        .alert {
            border-radius: 10px;
            font-size: 14px;
        }

        .footer {
            text-align: center;
            color: #9aa6b5;
            font-size: 12px;
            margin-top: 30px;
            padding: 15px;
        }

        @media (max-width: 1100px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                position: relative;
                width: 100%;
                min-height: auto;
                padding: 18px;
            }

            .tagline {
                margin-bottom: 15px;
            }

            .menu-title {
                margin-top: 15px;
            }

            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }

            .top-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .top-heading h1 {
                font-size: 23px;
            }

            .welcome-banner {
                padding: 22px;
            }

            .stats-grid {
                gap: 12px;
            }

            .stat-card {
                padding: 15px;
            }

            .content-card {
                padding: 17px;
            }

            .section-heading {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (max-width: 420px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <aside class="sidebar">

        <div class="brand">ResQLink</div>

        <div class="tagline">CONNECT • SUPPORT • REBUILD</div>

        <div class="menu-title">MAIN MENU</div>

        <a href="dashboard.php">
            <i class="bi bi-grid"></i>
            Dashboard
        </a>

        <a href="create_request.php">
            <i class="bi bi-plus-circle"></i>
            Create Request
        </a>

        <a href="my_requests.php" class="active">
            <i class="bi bi-clipboard-check"></i>
            My Requests
        </a>

        <div class="menu-title">ACCOUNT</div>

        <a href="../notifications/index.php">
            <i class="bi bi-bell"></i>
            Notifications
        </a>

        <a href="../profile/edit_profile.php">
            <i class="bi bi-person"></i>
            My Profile
        </a>

        <a href="../profile/change_password.php">
            <i class="bi bi-lock"></i>
            Change Password
        </a>

        <a href="../auth/logout.php">
            <i class="bi bi-box-arrow-right"></i>
            Logout
        </a>

    </aside>

    <!-- Main Content -->
    <main class="main-content">

        <!-- Heading -->
        <div class="top-heading">

            <div>
                <h1>My Relief Requests</h1>
                <p>Track and manage your submitted relief requests.</p>
            </div>

            <div class="user-info">
                <i class="bi bi-person-circle text-primary"></i>
                <?= htmlspecialchars($_SESSION['name']) ?>
                <span class="text-muted">| Requester</span>
            </div>

        </div>

        <!-- Welcome Banner -->
        <section class="welcome-banner">

            <h2>
                Your Requests, Your Progress!
            </h2>

            <p>
                Welcome,
                <?= htmlspecialchars($_SESSION['name']) ?>!
                Monitor your submitted requests and stay updated
                on their progress.
            </p>

        </section>

        <!-- Delete Message -->
        <?php if (!empty($delete_message)): ?>

            <div class="alert alert-success alert-dismissible fade show" role="alert">

                <i class="bi bi-check-circle-fill me-2"></i>

                <?= htmlspecialchars($delete_message) ?>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close"></button>

            </div>

        <?php endif; ?>

        <!-- Statistics -->
        <section class="stats-grid">

            <div class="stat-card">

                <div class="stat-icon blue">
                    <i class="bi bi-clipboard-data"></i>
                </div>

                <h3><?= $total_requests ?></h3>

                <p>Total Requests</p>

            </div>

            <div class="stat-card">

                <div class="stat-icon orange">
                    <i class="bi bi-clock-history"></i>
                </div>

                <h3><?= $pending_requests ?></h3>

                <p>Pending Requests</p>

            </div>

            <div class="stat-card">

                <div class="stat-icon green">
                    <i class="bi bi-check-circle"></i>
                </div>

                <h3><?= $approved_requests ?></h3>

                <p>Approved Requests</p>

            </div>

            <div class="stat-card">

                <div class="stat-icon red">
                    <i class="bi bi-x-circle"></i>
                </div>

                <h3><?= $rejected_requests ?></h3>

                <p>Rejected Requests</p>

            </div>

        </section>

        <!-- Requests Table -->
        <section class="content-card">

            <div class="section-heading">

                <div>
                    <h3>All My Requests</h3>
                    <p>View your relief request details and current status.</p>
                </div>

                <a href="create_request.php" class="btn-create">
                    <i class="bi bi-plus-lg me-1"></i>
                    Create New Request
                </a>

            </div>

            <?php if (count($requests) > 0): ?>

                <div class="table-responsive">

                    <table class="table">

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>People Affected</th>
                                <th>Urgency</th>
                                <th>Priority Score</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($requests as $request): ?>

                                <?php
                                $status = strtolower($request['status']);
                                $urgency = strtolower($request['urgency_level']);

                                $status_class = 'status-other';
                                $urgency_class = 'urgency-low';

                                if ($status === 'pending') {
                                    $status_class = 'status-pending';
                                } elseif ($status === 'approved') {
                                    $status_class = 'status-approved';
                                } elseif ($status === 'rejected') {
                                    $status_class = 'status-rejected';
                                }

                                if ($urgency === 'low') {
                                    $urgency_class = 'urgency-low';
                                } elseif ($urgency === 'medium') {
                                    $urgency_class = 'urgency-medium';
                                } elseif ($urgency === 'high') {
                                    $urgency_class = 'urgency-high';
                                } elseif ($urgency === 'critical') {
                                    $urgency_class = 'urgency-critical';
                                }
                                ?>

                                <tr>

                                    <td>
                                        #<?= htmlspecialchars((string)$request['id']) ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars($request['category']) ?>
                                        </strong>
                                    </td>

                                    <td class="description-cell">
                                        <?= htmlspecialchars($request['description']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars((string)$request['people_affected']) ?>
                                    </td>

                                    <td>
                                        <span class="urgency-badge <?= $urgency_class ?>">
                                            <?= htmlspecialchars($request['urgency_level']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="priority-score">
                                            <?= htmlspecialchars((string)$request['priority_score']) ?>
                                        </span>
                                    </td>

                                    <td class="location-cell">
                                        <?= htmlspecialchars($request['location']) ?>
                                    </td>

                                    <td>
                                        <span class="status-badge <?= $status_class ?>">
                                            <?= htmlspecialchars($request['status']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($request['created_at']) ?>
                                    </td>

                                    <td>

                                        <?php if ($request['status'] === 'pending'): ?>

                                            <div class="action-buttons">

                                                <a href="edit_request.php?id=<?= (int)$request['id'] ?>"
                                                   class="btn-edit">

                                                    <i class="bi bi-pencil-square"></i>
                                                    Edit

                                                </a>

                                                <form method="POST"
                                                      action="delete_request.php"
                                                      onsubmit="return confirm('Are you sure you want to delete this request?');">

                                                    <input type="hidden"
                                                           name="request_id"
                                                           value="<?= (int)$request['id'] ?>">

                                                    <input type="hidden"
                                                           name="csrf_token"
                                                           value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                                    <button type="submit" class="btn-delete">

                                                        <i class="bi bi-trash"></i>
                                                        Delete

                                                    </button>

                                                </form>

                                            </div>

                                        <?php else: ?>

                                            <span class="not-editable">
                                                <i class="bi bi-lock-fill"></i>
                                                Not Editable
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    <i class="bi bi-inbox"></i>

                    <h4>No Relief Requests Yet</h4>

                    <p>
                        You have not submitted any relief requests.
                        Create your first request to get started.
                    </p>

                    <a href="create_request.php" class="btn-create">
                        <i class="bi bi-plus-lg me-1"></i>
                        Create Relief Request
                    </a>

                </div>

            <?php endif; ?>

        </section>

        <!-- Footer -->
        <footer class="footer">

            Together for a Safer, Stronger Community

            <br><br>

            ResQLink © 2026 | Disaster Response and Resource Coordination System

        </footer>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
