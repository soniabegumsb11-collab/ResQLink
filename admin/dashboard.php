
<?php

require_once '../includes/auth_check.php';
require_once '../config/database.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Total Users
$stmt = $db->query("SELECT COUNT(*) FROM users");
$total_users = $stmt->fetchColumn();

// Total Requesters
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'requester'");
$total_requesters = $stmt->fetchColumn();

// Total Volunteers
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'volunteer'");
$total_volunteers = $stmt->fetchColumn();

// Total Relief Requests
$stmt = $db->query("SELECT COUNT(*) FROM relief_requests");
$total_requests = $stmt->fetchColumn();

// Pending Requests
$stmt = $db->query("SELECT COUNT(*) FROM relief_requests WHERE status = 'pending'");
$pending_requests = $stmt->fetchColumn();

// Approved Requests
$stmt = $db->query("SELECT COUNT(*) FROM relief_requests WHERE status = 'approved'");
$approved_requests = $stmt->fetchColumn();

// Rejected Requests
$stmt = $db->query("SELECT COUNT(*) FROM relief_requests WHERE status = 'rejected'");
$rejected_requests = $stmt->fetchColumn();

// Unread Notifications
$user_id = $_SESSION['user_id'];

$sql = "SELECT COUNT(*)
        FROM notifications
        WHERE user_id = :user_id
        AND is_read = 0";

$stmt = $db->prepare($sql);
$stmt->execute([':user_id' => $user_id]);

$unread_notifications = $stmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard - ResQLink</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
          rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --navy: #142d4e;
            --blue: #2563eb;
            --teal: #0d9488;
            --light-bg: #f4f7fb;
            --text-dark: #24364b;
        }

        body {
            background: var(--light-bg);
            font-family: Arial, sans-serif;
            color: var(--text-dark);
        }

        .sidebar {
            background: var(--navy);
            min-height: 100vh;
            padding: 25px 16px;
            color: white;
        }

        .brand {
            font-size: 23px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .brand span {
            color: #45d6c5;
        }

        .sidebar-subtitle {
            font-size: 11px;
            color: #aebfd3;
            letter-spacing: 1px;
            margin-bottom: 35px;
        }

        .menu-label {
            font-size: 11px;
            color: #9db1c9;
            text-transform: uppercase;
            margin: 22px 10px 10px;
        }

        .sidebar a {
            display: block;
            color: #dce6f2;
            text-decoration: none;
            padding: 11px 12px;
            border-radius: 8px;
            margin-bottom: 6px;
            font-size: 14px;
            transition: 0.2s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #25476e;
            color: white;
        }

        .sidebar a i {
            margin-right: 10px;
        }

        .main-content {
            padding: 30px;
        }

        .topbar {
            background: white;
            padding: 18px 22px;
            border-radius: 12px;
            margin-bottom: 28px;
            box-shadow: 0 2px 10px rgba(20, 45, 78, 0.04);
        }

        .page-title {
            font-size: 25px;
            font-weight: 700;
            color: var(--navy);
        }

        .stat-card {
            background: white;
            border: none;
            border-radius: 13px;
            padding: 21px;
            height: 100%;
            box-shadow: 0 3px 12px rgba(20, 45, 78, 0.05);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-icon {
            width: 43px;
            height: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 20px;
            margin-bottom: 15px;
        }

        .icon-blue {
            background: #e8f0ff;
            color: #2563eb;
        }

        .icon-teal {
            background: #e0f7f3;
            color: #0d9488;
        }

        .icon-orange {
            background: #fff2df;
            color: #e99522;
        }

        .icon-green {
            background: #e3f7e9;
            color: #198754;
        }

        .icon-red {
            background: #fde8e8;
            color: #dc3545;
        }

        .stat-label {
            font-size: 13px;
            color: #718096;
            margin-bottom: 7px;
        }

        .stat-number {
            font-size: 27px;
            font-weight: 700;
            color: var(--navy);
            margin: 0;
        }

        .section-heading {
            font-size: 19px;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 17px;
        }

        .content-card {
            background: white;
            border: none;
            border-radius: 13px;
            padding: 24px;
            box-shadow: 0 3px 12px rgba(20, 45, 78, 0.05);
            height: 100%;
        }

        .action-link {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 15px;
            border: 1px solid #e8edf4;
            border-radius: 10px;
            text-decoration: none;
            color: var(--text-dark);
            transition: 0.2s;
            height: 100%;
        }

        .action-link:hover {
            border-color: #9bbcf4;
            background: #f7faff;
            color: var(--blue);
            transform: translateY(-2px);
        }

        .action-icon {
            width: 40px;
            height: 40px;
            border-radius: 9px;
            background: #eaf1ff;
            color: var(--blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            flex-shrink: 0;
        }

        .action-title {
            font-size: 14px;
            font-weight: 600;
            margin: 0;
        }

        .action-description {
            font-size: 12px;
            color: #8190a3;
            margin: 3px 0 0;
        }

        .notification-badge {
            background: #eaf1ff;
            color: var(--blue);
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 12px;
        }

        .footer-text {
            font-size: 12px;
            color: #8996a8;
            text-align: center;
            padding: 25px 0 5px;
        }

        @media (max-width: 767px) {
            .sidebar {
                min-height: auto;
                padding: 18px;
            }

            .sidebar-subtitle {
                margin-bottom: 15px;
            }

            .menu-label {
                margin-top: 15px;
            }

            .main-content {
                padding: 18px 13px;
            }

            .topbar {
                padding: 16px;
            }

            .page-title {
                font-size: 21px;
            }
        }
    </style>
</head>

<body>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <aside class="col-md-3 col-lg-2 sidebar">

            <div class="brand">ResQ<span>Link</span></div>
            <div class="sidebar-subtitle">CONNECT • SUPPORT • REBUILD</div>

            <div class="menu-label">Main Menu</div>

            <a href="dashboard.php" class="active">
                <i class="bi bi-grid"></i> Dashboard
            </a>

            <a href="requests.php">
                <i class="bi bi-clipboard-check"></i> Relief Requests
            </a>

            <a href="resources.php">
                <i class="bi bi-box-seam"></i> Resources
            </a>

            <a href="assignments.php">
                <i class="bi bi-truck"></i> Assignments
            </a>

            <div class="menu-label">Management</div>

            <a href="reports.php">
                <i class="bi bi-bar-chart"></i> Reports
            </a>

            <a href="activity_logs.php">
                <i class="bi bi-clock-history"></i> Activity Logs
            </a>

            <a href="../notifications/index.php">
                <i class="bi bi-bell"></i>
                Notifications
                <span class="badge bg-danger ms-1">
                    <?= (int)$unread_notifications ?>
                </span>
            </a>

            <div class="menu-label">Account</div>

            <a href="../profile/edit_profile.php">
                <i class="bi bi-person"></i> My Profile
            </a>

            <a href="../profile/change_password.php">
                <i class="bi bi-shield-lock"></i> Change Password
            </a>

            <a href="../auth/logout.php">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>

        </aside>

        <!-- Main Content -->
        <main class="col-md-9 col-lg-10 main-content">

            <!-- Topbar -->
            <div class="topbar d-flex flex-wrap justify-content-between align-items-center gap-2">

                <div>
                    <div class="page-title">Admin Dashboard</div>
                    <small class="text-secondary">
                        Disaster response management overview
                    </small>
                </div>

                <div class="text-md-end">
                    <div class="fw-semibold">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($_SESSION['name']) ?>
                    </div>

                    <small class="text-secondary">Administrator</small>
                </div>

            </div>

            <!-- Welcome -->
            <div class="mb-4">
                <h4 class="fw-bold">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?>!</h4>
                <p class="text-secondary mb-0">
                    Here is an overview of your ResQLink platform.
                </p>
            </div>

            <!-- Statistics -->
            <h5 class="section-heading">Platform Overview</h5>

            <div class="row g-3 mb-4">

                <div class="col-6 col-lg-4">
                    <div class="stat-card">
                        <div class="stat-icon icon-blue">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-label">Total Users</div>
                        <h3 class="stat-number"><?= (int)$total_users ?></h3>
                    </div>
                </div>

                <div class="col-6 col-lg-4">
                    <div class="stat-card">
                        <div class="stat-icon icon-teal">
                            <i class="bi bi-person-heart"></i>
                        </div>
                        <div class="stat-label">Total Requesters</div>
                        <h3 class="stat-number"><?= (int)$total_requesters ?></h3>
                    </div>
                </div>

                <div class="col-6 col-lg-4">
                    <div class="stat-card">
                        <div class="stat-icon icon-orange">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <div class="stat-label">Total Volunteers</div>
                        <h3 class="stat-number"><?= (int)$total_volunteers ?></h3>
                    </div>
                </div>

                <div class="col-6 col-lg-4">
                    <div class="stat-card">
                        <div class="stat-icon icon-blue">
                            <i class="bi bi-clipboard-data"></i>
                        </div>
                        <div class="stat-label">Total Relief Requests</div>
                        <h3 class="stat-number"><?= (int)$total_requests ?></h3>
                    </div>
                </div>

                <div class="col-6 col-lg-4">
                    <div class="stat-card">
                        <div class="stat-icon icon-orange">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div class="stat-label">Pending Requests</div>
                        <h3 class="stat-number"><?= (int)$pending_requests ?></h3>
                    </div>
                </div>

                <div class="col-6 col-lg-4">
                    <div class="stat-card">
                        <div class="stat-icon icon-green">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-label">Approved Requests</div>
                        <h3 class="stat-number"><?= (int)$approved_requests ?></h3>
                    </div>
                </div>

            </div>

            <!-- Chart -->
            <h5 class="section-heading">Relief Request Statistics</h5>

            <div class="content-card mb-4">

                <div class="row align-items-center">

                    <div class="col-lg-7">
                        <div style="max-width: 420px; margin: auto;">
                            <canvas id="requestChart"></canvas>
                        </div>
                    </div>

                    <div class="col-lg-5 mt-4 mt-lg-0">

                        <h6 class="fw-bold mb-3">Request Status Summary</h6>

                        <div class="d-flex justify-content-between mb-3">
                            <span>
                                <i class="bi bi-circle-fill text-warning me-2"></i>
                                Pending
                            </span>
                            <strong><?= (int)$pending_requests ?></strong>
                        </div>

                        <div class="d-flex justify-content-between mb-3">
                            <span>
                                <i class="bi bi-circle-fill text-success me-2"></i>
                                Approved
                            </span>
                            <strong><?= (int)$approved_requests ?></strong>
                        </div>

                        <div class="d-flex justify-content-between mb-3">
                            <span>
                                <i class="bi bi-circle-fill text-danger me-2"></i>
                                Rejected
                            </span>
                            <strong><?= (int)$rejected_requests ?></strong>
                        </div>

                    </div>

                </div>

            </div>

            <!-- Quick Actions -->
            <h5 class="section-heading">Quick Actions</h5>

            <div class="row g-3">

                <div class="col-md-6 col-xl-4">
                    <a href="requests.php" class="action-link">
                        <div class="action-icon">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                        <div>
                            <p class="action-title">Manage Relief Requests</p>
                            <p class="action-description">Review and process requests</p>
                        </div>
                    </a>
                </div>

                <div class="col-md-6 col-xl-4">
                    <a href="resources.php" class="action-link">
                        <div class="action-icon">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div>
                            <p class="action-title">Manage Resources</p>
                            <p class="action-description">Manage available supplies</p>
                        </div>
                    </a>
                </div>

                <div class="col-md-6 col-xl-4">
                    <a href="assignments.php" class="action-link">
                        <div class="action-icon">
                            <i class="bi bi-truck"></i>
                        </div>
                        <div>
                            <p class="action-title">Manage Assignments</p>
                            <p class="action-description">Coordinate relief delivery</p>
                        </div>
                    </a>
                </div>

                <div class="col-md-6 col-xl-4">
                    <a href="reports.php" class="action-link">
                        <div class="action-icon">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                        </div>
                        <div>
                            <p class="action-title">View Reports</p>
                            <p class="action-description">Check platform reports</p>
                        </div>
                    </a>
                </div>

                <div class="col-md-6 col-xl-4">
                    <a href="../notifications/index.php" class="action-link">
                        <div class="action-icon">
                            <i class="bi bi-bell"></i>
                        </div>
                        <div>
                            <p class="action-title">My Notifications</p>
                            <p class="action-description">
                                <?= (int)$unread_notifications ?> unread notifications
                            </p>
                        </div>
                    </a>
                </div>

                <div class="col-md-6 col-xl-4">
                    <a href="../profile/edit_profile.php" class="action-link">
                        <div class="action-icon">
                            <i class="bi bi-person-gear"></i>
                        </div>
                        <div>
                            <p class="action-title">Edit My Profile</p>
                            <p class="action-description">Update account information</p>
                        </div>
                    </a>
                </div>

            </div>

            <div class="footer-text">
                ResQLink &copy; <?= date('Y') ?> | Disaster Response and Resource Coordination System
            </div>

        </main>

    </div>
</div>

<script>

const chartData = {
    labels: ['Pending', 'Approved', 'Rejected'],
    datasets: [{
        label: 'Relief Requests',
        data: [
            <?= (int)$pending_requests ?>,
            <?= (int)$approved_requests ?>,
            <?= (int)$rejected_requests ?>
        ],
        backgroundColor: [
            '#f59e0b',
            '#198754',
            '#dc3545'
        ],
        borderColor: '#ffffff',
        borderWidth: 3,
        hoverOffset: 8
    }]
};

const chartConfig = {
    type: 'doughnut',
    data: chartData,
    options: {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '65%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true
                }
            },
            title: {
                display: true,
                text: 'Relief Request Status',
                font: {
                    size: 16
                },
                padding: 15
            }
        }
    }
};

new Chart(
    document.getElementById('requestChart'),
    chartConfig
);

</script>

</body>
</html>
