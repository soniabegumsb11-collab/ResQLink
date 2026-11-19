
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

// Get relief request statistics
$sql = "SELECT
            COUNT(*) AS total_requests,
            COALESCE(SUM(status = 'pending'), 0) AS pending_requests,
            COALESCE(SUM(status = 'approved'), 0) AS approved_requests
        FROM relief_requests
        WHERE requester_id = :requester_id";

$stmt = $db->prepare($sql);
$stmt->execute([
    ':requester_id' => $user_id
]);

$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get unread notification count
$sql = "SELECT COUNT(*)
        FROM notifications
        WHERE user_id = :user_id
        AND is_read = 0";

$stmt = $db->prepare($sql);
$stmt->execute([
    ':user_id' => $user_id
]);

$unread_notifications = $stmt->fetchColumn();

$total_requests = (int) $stats['total_requests'];
$pending_requests = (int) $stats['pending_requests'];
$approved_requests = (int) $stats['approved_requests'];
$unread_notifications = (int) $unread_notifications;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Requester Dashboard | ResQLink</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --sidebar: #ffffff;
            --background: #f4f7fc;
            --text-dark: #172b4d;
            --text-muted: #718096;
            --border: #e8edf5;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text-dark);
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        .sidebar {
            width: 255px;
            min-height: 100vh;
            background: var(--sidebar);
            border-right: 1px solid var(--border);
            position: fixed;
            top: 0;
            left: 0;
            padding: 25px 18px;
            display: flex;
            flex-direction: column;
            z-index: 100;
        }

        .brand {
            padding: 5px 12px 30px;
        }

        .brand-name {
            font-size: 25px;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -1px;
        }

        .brand-tagline {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 2px;
            color: var(--text-muted);
            margin-top: 3px;
        }

        .menu-label {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.5px;
            color: #9aa7b8;
            padding: 12px 13px 8px;
            text-transform: uppercase;
        }

        .nav-link-custom {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 12px 14px;
            margin: 3px 0;
            color: #52627a;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            border-radius: 10px;
            transition: 0.2s;
        }

        .nav-link-custom i {
            font-size: 17px;
        }

        .nav-link-custom:hover,
        .nav-link-custom.active {
            background: #edf4ff;
            color: var(--primary);
        }

        .nav-link-custom.logout {
            color: #dc3545;
        }

        .nav-link-custom.logout:hover {
            background: #fff0f0;
        }

        .sidebar-bottom {
            margin-top: auto;
            padding-top: 20px;
        }

        .help-card {
            background: #eff6ff;
            border: 1px solid #dbeafe;
            padding: 15px;
            border-radius: 12px;
            margin: 10px 4px 0;
        }

        .help-card i {
            color: var(--primary);
            font-size: 22px;
        }

        .help-card p {
            font-size: 12px;
            color: #52627a;
            margin: 7px 0 0;
            line-height: 1.6;
        }

        .main-content {
            margin-left: 255px;
            padding: 30px 35px;
            min-height: 100vh;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 25px;
            font-weight: 800;
            margin: 0;
        }

        .page-subtitle {
            color: var(--text-muted);
            font-size: 13px;
            margin-top: 5px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 11px;
            background: #fff;
            padding: 9px 15px;
            border: 1px solid var(--border);
            border-radius: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: #e8f0ff;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
        }

        .user-name {
            font-size: 13px;
            font-weight: 700;
        }

        .user-role {
            font-size: 11px;
            color: var(--text-muted);
        }

        .welcome-banner {
            background: linear-gradient(120deg, #2563eb, #0e7490);
            color: white;
            padding: 30px;
            border-radius: 17px;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::after {
            content: '';
            position: absolute;
            width: 190px;
            height: 190px;
            border: 30px solid rgba(255,255,255,0.08);
            border-radius: 50%;
            right: 45px;
            top: -75px;
        }

        .welcome-banner h2 {
            font-size: 25px;
            font-weight: 800;
            margin-bottom: 9px;
            position: relative;
            z-index: 1;
        }

        .welcome-banner p {
            margin: 0;
            font-size: 14px;
            color: rgba(255,255,255,0.88);
            position: relative;
            z-index: 1;
        }

        .banner-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            padding: 10px 17px;
            border-radius: 9px;
            background: white;
            color: var(--primary);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            position: relative;
            z-index: 1;
            transition: 0.2s;
        }

        .banner-button:hover {
            background: #edf4ff;
            color: var(--primary-dark);
            transform: translateY(-2px);
        }

        .section-heading {
            font-size: 17px;
            font-weight: 800;
            margin-bottom: 17px;
        }

        .stat-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 21px;
            height: 100%;
            transition: 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(30, 60, 100, 0.07);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .stat-icon {
            width: 43px;
            height: 43px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .icon-blue {
            background: #eaf1ff;
            color: #2563eb;
        }

        .icon-orange {
            background: #fff3e6;
            color: #e58a20;
        }

        .icon-green {
            background: #e8f8ef;
            color: #159957;
        }

        .stat-number {
            font-size: 29px;
            font-weight: 800;
            margin: 0;
            color: var(--text-dark);
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
            margin-top: 4px;
        }

        .content-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 23px;
            height: 100%;
        }

        .action-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 15px;
            border: 1px solid var(--border);
            border-radius: 12px;
            text-decoration: none;
            color: var(--text-dark);
            height: 100%;
            transition: 0.2s;
        }

        .action-item:hover {
            border-color: #b7d0ff;
            background: #f8fbff;
            transform: translateY(-2px);
        }

        .action-icon {
            min-width: 43px;
            height: 43px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
        }

        .action-title {
            font-size: 13px;
            font-weight: 750;
            margin-bottom: 4px;
        }

        .action-description {
            font-size: 11px;
            color: var(--text-muted);
            margin: 0;
        }

        .action-arrow {
            margin-left: auto;
            color: #9aa7b8;
        }

        .notification-count {
            background: #fff0e8;
            color: #e56b27;
            font-size: 11px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 20px;
            margin-left: auto;
        }

        .footer {
            text-align: center;
            color: #96a2b3;
            font-size: 11px;
            padding: 30px 0 5px;
        }

        @media (max-width: 767px) {
            .sidebar {
                position: relative;
                width: 100%;
                min-height: auto;
                padding: 15px;
            }

            .brand {
                padding: 5px 8px 15px;
            }

            .sidebar .menu-label,
            .sidebar-bottom {
                display: none;
            }

            .sidebar nav {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }

            .nav-link-custom {
                padding: 9px 11px;
                font-size: 12px;
                margin: 0;
            }

            .nav-link-custom i {
                font-size: 15px;
            }

            .main-content {
                margin-left: 0;
                padding: 22px 15px;
            }

            .topbar {
                align-items: flex-start;
            }

            .page-title {
                font-size: 21px;
            }

            .user-info {
                padding: 7px;
            }

            .user-name,
            .user-role {
                display: none;
            }

            .welcome-banner {
                padding: 23px;
            }

            .welcome-banner h2 {
                font-size: 21px;
            }
        }
    </style>
</head>

<body>

<!-- Sidebar -->
<aside class="sidebar">

    <div class="brand">
        <div class="brand-name">ResQLink</div>
        <div class="brand-tagline">CONNECT • SUPPORT • REBUILD</div>
    </div>

    <div class="menu-label">Main Menu</div>

    <nav>
        <a href="dashboard.php" class="nav-link-custom active">
            <i class="bi bi-grid-1x2-fill"></i>
            Dashboard
        </a>

        <a href="create_request.php" class="nav-link-custom">
            <i class="bi bi-plus-circle"></i>
            Create Request
        </a>

        <a href="my_requests.php" class="nav-link-custom">
            <i class="bi bi-clipboard-check"></i>
            My Requests
        </a>
    </nav>

    <div class="menu-label mt-3">Account</div>

    <nav>
        <a href="../notifications/index.php" class="nav-link-custom">
            <i class="bi bi-bell"></i>
            Notifications

            <?php if ($unread_notifications > 0): ?>
                <span class="notification-count">
                    <?= $unread_notifications ?>
                </span>
            <?php endif; ?>
        </a>

        <a href="../profile/edit_profile.php" class="nav-link-custom">
            <i class="bi bi-person-circle"></i>
            My Profile
        </a>

        <a href="../profile/change_password.php" class="nav-link-custom">
            <i class="bi bi-shield-lock"></i>
            Change Password
        </a>

        <a href="../auth/logout.php" class="nav-link-custom logout">
            <i class="bi bi-box-arrow-right"></i>
            Logout
        </a>
    </nav>

    <div class="sidebar-bottom">
        <div class="help-card">
            <i class="bi bi-heart-pulse"></i>
            <p>
                Need help? Create a relief request to let the ResQLink team know what support you need.
            </p>
        </div>
    </div>

</aside>


<!-- Main Content -->
<main class="main-content">

    <!-- Topbar -->
    <div class="topbar">

        <div>
            <h1 class="page-title">Requester Dashboard</h1>
            <p class="page-subtitle">
                Your personal relief request overview
            </p>
        </div>

        <div class="user-info">
            <div class="user-avatar">
                <i class="bi bi-person-fill"></i>
            </div>

            <div>
                <div class="user-name">
                    <?= htmlspecialchars($_SESSION['name']) ?>
                </div>

                <div class="user-role">Requester Account</div>
            </div>
        </div>

    </div>


    <!-- Welcome Banner -->
    <section class="welcome-banner">

        <h2>
            Welcome back, <?= htmlspecialchars($_SESSION['name']) ?>!
        </h2>

        <p>
            Stay informed about your relief requests and get the support you need.
        </p>

        <a href="create_request.php" class="banner-button">
            <i class="bi bi-plus-circle"></i>
            Create New Request
        </a>

    </section>


    <!-- Statistics -->
    <section class="mb-4">

        <h3 class="section-heading">Your Request Overview</h3>

        <div class="row g-3">

            <div class="col-md-4 col-sm-6">
                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon icon-blue">
                            <i class="bi bi-clipboard-data"></i>
                        </div>
                        <i class="bi bi-three-dots text-muted"></i>
                    </div>

                    <h2 class="stat-number">
                        <?= $total_requests ?>
                    </h2>

                    <div class="stat-label">Total Relief Requests</div>

                </div>
            </div>


            <div class="col-md-4 col-sm-6">
                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon icon-orange">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <i class="bi bi-three-dots text-muted"></i>
                    </div>

                    <h2 class="stat-number">
                        <?= $pending_requests ?>
                    </h2>

                    <div class="stat-label">Pending Requests</div>

                </div>
            </div>


            <div class="col-md-4 col-sm-6">
                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon icon-green">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <i class="bi bi-three-dots text-muted"></i>
                    </div>

                    <h2 class="stat-number">
                        <?= $approved_requests ?>
                    </h2>

                    <div class="stat-label">Approved Requests</div>

                </div>
            </div>

        </div>

    </section>


    <!-- Quick Actions -->
    <section>

        <h3 class="section-heading">Quick Actions</h3>

        <div class="content-card">

            <div class="row g-3">

                <div class="col-md-6">
                    <a href="create_request.php" class="action-item">

                        <div class="action-icon icon-blue">
                            <i class="bi bi-plus-circle"></i>
                        </div>

                        <div>
                            <div class="action-title">Create Relief Request</div>
                            <p class="action-description">
                                Request food, water, medicine or shelter
                            </p>
                        </div>

                        <i class="bi bi-arrow-right action-arrow"></i>

                    </a>
                </div>


                <div class="col-md-6">
                    <a href="my_requests.php" class="action-item">

                        <div class="action-icon icon-green">
                            <i class="bi bi-list-check"></i>
                        </div>

                        <div>
                            <div class="action-title">My Relief Requests</div>
                            <p class="action-description">
                                View and manage your submitted requests
                            </p>
                        </div>

                        <i class="bi bi-arrow-right action-arrow"></i>

                    </a>
                </div>


                <div class="col-md-6">
                    <a href="../notifications/index.php" class="action-item">

                        <div class="action-icon icon-orange">
                            <i class="bi bi-bell"></i>
                        </div>

                        <div>
                            <div class="action-title">My Notifications</div>
                            <p class="action-description">
                                Check updates about your requests
                            </p>
                        </div>

                        <span class="notification-count">
                            <?= $unread_notifications ?> Unread
                        </span>

                    </a>
                </div>


                <div class="col-md-6">
                    <a href="../profile/edit_profile.php" class="action-item">

                        <div class="action-icon icon-blue">
                            <i class="bi bi-person-gear"></i>
                        </div>

                        <div>
                            <div class="action-title">Edit My Profile</div>
                            <p class="action-description">
                                Update your personal information
                            </p>
                        </div>

                        <i class="bi bi-arrow-right action-arrow"></i>

                    </a>
                </div>

            </div>

        </div>

    </section>


    <!-- Footer -->
    <footer class="footer">
        ResQLink © 2026 | Disaster Response and Resource Coordination System
    </footer>

</main>

</body>
</html>
