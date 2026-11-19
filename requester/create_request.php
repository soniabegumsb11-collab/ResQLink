
<?php

require_once '../includes/auth_check.php';
require_once '../config/database.php';

if ($_SESSION['role'] !== 'requester') {
    header("Location: ../index.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success = "";

$category = "";
$description = "";
$people_affected = "";
$urgency_level = "";
$location = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!is_string($csrf_token) ||
        !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $errors[] = "Invalid form submission. Please try again.";
    }

    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $people_affected = trim($_POST['people_affected'] ?? '');
    $urgency_level = $_POST['urgency_level'] ?? '';
    $location = trim($_POST['location'] ?? '');

    if ($category === '' || $description === '' || $location === '') {
        $errors[] = "Please fill in all required fields.";
    }

    if (
        filter_var($people_affected, FILTER_VALIDATE_INT) === false ||
        (int)$people_affected < 1
    ) {
        $errors[] = "At least one affected person is required.";
    }

    if (!in_array($category, ['Food', 'Water', 'Medicine', 'Shelter'], true)) {
        $errors[] = "Invalid relief category.";
    }

    if (!in_array($urgency_level, ['low', 'medium', 'high', 'critical'], true)) {
        $errors[] = "Invalid urgency level.";
    }

    if (empty($errors)) {

        $urgency_scores = [
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            'critical' => 4
        ];

        $priority_score = (int)$people_affected * $urgency_scores[$urgency_level];

        $sql = "INSERT INTO relief_requests
                (requester_id, category, description, people_affected,
                 urgency_level, location, priority_score)
                VALUES
                (:requester_id, :category, :description, :people_affected,
                 :urgency_level, :location, :priority_score)";

        $stmt = $db->prepare($sql);

        $stmt->execute([
            ':requester_id' => $_SESSION['user_id'],
            ':category' => $category,
            ':description' => $description,
            ':people_affected' => (int)$people_affected,
            ':urgency_level' => $urgency_level,
            ':location' => $location,
            ':priority_score' => $priority_score
        ]);

        $success = "Relief request submitted successfully!";

        $category = "";
        $description = "";
        $people_affected = "";
        $urgency_level = "";
        $location = "";

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

$userName = htmlspecialchars(
    $_SESSION['name'] ?? 'Requester',
    ENT_QUOTES,
    'UTF-8'
);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Relief Request | ResQLink</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
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

        /* Sidebar */

        .sidebar {
            width: 255px;
            min-height: 100vh;
            background: #ffffff;
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
            text-decoration: none;
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

        /* Main content */

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
            margin-bottom: 28px;
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

        /* Welcome banner */

        .welcome-banner {
            background: linear-gradient(120deg, #2563eb, #0e7490);
            color: white;
            padding: 28px 30px;
            border-radius: 17px;
            margin-bottom: 25px;
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
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        .welcome-banner p {
            margin: 0;
            font-size: 13px;
            color: rgba(255,255,255,0.88);
            position: relative;
            z-index: 1;
        }

        /* Form card */

        .request-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 5px 20px rgba(30, 60, 100, 0.04);
        }

        .form-heading {
            display: flex;
            align-items: center;
            gap: 13px;
            padding-bottom: 22px;
            border-bottom: 1px solid #edf1f6;
            margin-bottom: 25px;
        }

        .heading-icon {
            width: 46px;
            height: 46px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            color: var(--primary);
            background: #eaf3fc;
            font-size: 22px;
            flex-shrink: 0;
        }

        .form-heading h2 {
            font-size: 19px;
            font-weight: 800;
            color: var(--text-dark);
            margin: 0 0 4px;
        }

        .form-heading p {
            color: var(--text-muted);
            font-size: 12px;
            margin: 0;
        }

        .form-label {
            color: #24364b;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 9px;
        }

        .required {
            color: #dc3545;
        }

        .form-control,
        .form-select {
            border: 1px solid #dce5ef;
            border-radius: 9px;
            padding: 12px 14px;
            font-size: 13px;
            color: #26384c;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.10);
        }

        textarea.form-control {
            resize: vertical;
        }

        .field-help {
            color: var(--text-muted);
            font-size: 11px;
            margin-top: 7px;
        }

        /* Category options */

        .category-option {
            display: block;
            cursor: pointer;
            height: 100%;
        }

        .category-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .category-box {
            border: 1px solid #dce5ef;
            border-radius: 11px;
            padding: 17px 8px;
            text-align: center;
            transition: 0.2s;
            background: white;
            color: #40536a;
        }

        .category-box i {
            display: block;
            font-size: 23px;
            margin-bottom: 7px;
        }

        .category-box span {
            font-size: 13px;
            font-weight: 700;
        }

        .category-option input:checked + .category-box {
            border: 2px solid var(--primary);
            background: #edf4ff;
            color: var(--primary);
            padding: 16px 7px;
        }

        .category-option input:focus-visible + .category-box {
            outline: 3px solid rgba(37,99,235,0.25);
        }

        .category-box:hover {
            border-color: var(--primary);
        }

        /* Urgency options */

        .urgency-option {
            display: block;
            cursor: pointer;
        }

        .urgency-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .urgency-box {
            display: flex;
            align-items: center;
            gap: 9px;
            border: 1px solid #dce5ef;
            padding: 12px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
            background: white;
            transition: 0.2s;
        }

        .urgency-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .urgency-option input:checked + .urgency-box {
            border: 2px solid var(--primary);
            background: #edf4ff;
            padding: 11px;
        }

        .urgency-option input:focus-visible + .urgency-box {
            outline: 3px solid rgba(37,99,235,0.25);
        }

        .low-dot {
            background: #27ae60;
        }

        .medium-dot {
            background: #f0ad32;
        }

        .high-dot {
            background: #e55353;
        }

        .critical-dot {
            background: #8e44ad;
        }

        /* Submit */

        .submit-btn {
            background: linear-gradient(110deg, #2563eb, #0e7490);
            border: none;
            color: white;
            width: 100%;
            padding: 15px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            transition: 0.2s;
        }

        .submit-btn:hover {
            background: linear-gradient(110deg, #1d4ed8, #0f766e);
            color: white;
            transform: translateY(-1px);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            margin-top: 22px;
        }

        .back-link:hover {
            color: #0e7490;
        }

        .footer {
            text-align: center;
            color: #96a2b3;
            font-size: 11px;
            padding: 28px 0 5px;
        }

        /* Mobile layout */

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

            .request-card {
                padding: 21px 16px;
            }
        }
    </style>
</head>

<body>

<!-- Sidebar -->

<aside class="sidebar">

    <a href="dashboard.php" class="brand">
        <div class="brand-name">ResQLink</div>
        <div class="brand-tagline">CONNECT • SUPPORT • REBUILD</div>
    </a>

    <div class="menu-label">Main Menu</div>

    <nav>
        <a href="dashboard.php" class="nav-link-custom">
            <i class="bi bi-grid-1x2-fill"></i>
            Dashboard
        </a>

        <a href="create_request.php" class="nav-link-custom active">
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
                Need help? Submit a relief request and let the ResQLink team know what support you need.
            </p>
        </div>
    </div>

</aside>


<!-- Main Content -->

<main class="main-content">

    <!-- Topbar -->

    <div class="topbar">

        <div>
            <h1 class="page-title">Create Relief Request</h1>
            <p class="page-subtitle">
                Submit your emergency support requirements
            </p>
        </div>

        <div class="user-info">

            <div class="user-avatar">
                <i class="bi bi-person-fill"></i>
            </div>

            <div>
                <div class="user-name">
                    <?= $userName ?>
                </div>

                <div class="user-role">Requester Account</div>
            </div>

        </div>

    </div>


    <!-- Welcome Banner -->

    <section class="welcome-banner">

        <h2>
            Your Request Matters, <?= $userName ?>!
        </h2>

        <p>
            Share accurate information about your emergency so the response team can coordinate the right support.
        </p>

    </section>


    <!-- Request Form -->

    <section class="request-card">

        <?php foreach ($errors as $error): ?>

            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle me-2"></i>

                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close"></button>
            </div>

        <?php endforeach; ?>


        <?php if ($success): ?>

            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>


        <div class="form-heading">

            <div class="heading-icon">
                <i class="bi bi-clipboard2-plus"></i>
            </div>

            <div>
                <h2>Relief Request Information</h2>
                <p>Provide accurate details so support can reach you.</p>
            </div>

        </div>


        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
            >


            <!-- Category -->

            <div class="mb-4">

                <label class="form-label">
                    Relief Category <span class="required">*</span>
                </label>

                <div class="row g-2">

                    <div class="col-6 col-md-3">
                        <label class="category-option">

                            <input
                                type="radio"
                                name="category"
                                value="Food"
                                <?= $category === 'Food' ? 'checked' : '' ?>
                                required
                            >

                            <div class="category-box">
                                <i class="bi bi-basket2"></i>
                                <span>Food</span>
                            </div>

                        </label>
                    </div>


                    <div class="col-6 col-md-3">
                        <label class="category-option">

                            <input
                                type="radio"
                                name="category"
                                value="Water"
                                <?= $category === 'Water' ? 'checked' : '' ?>
                            >

                            <div class="category-box">
                                <i class="bi bi-droplet"></i>
                                <span>Water</span>
                            </div>

                        </label>
                    </div>


                    <div class="col-6 col-md-3">
                        <label class="category-option">

                            <input
                                type="radio"
                                name="category"
                                value="Medicine"
                                <?= $category === 'Medicine' ? 'checked' : '' ?>
                            >

                            <div class="category-box">
                                <i class="bi bi-capsule"></i>
                                <span>Medicine</span>
                            </div>

                        </label>
                    </div>


                    <div class="col-6 col-md-3">
                        <label class="category-option">

                            <input
                                type="radio"
                                name="category"
                                value="Shelter"
                                <?= $category === 'Shelter' ? 'checked' : '' ?>
                            >

                            <div class="category-box">
                                <i class="bi bi-house-heart"></i>
                                <span>Shelter</span>
                            </div>

                        </label>
                    </div>

                </div>

            </div>


            <!-- Description -->

            <div class="mb-4">

                <label class="form-label">
                    Situation Description <span class="required">*</span>
                </label>

                <textarea
                    name="description"
                    class="form-control"
                    rows="4"
                    placeholder="Describe the emergency situation and the support required..."
                    required
                ><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>

                <div class="field-help">
                    Explain the current situation and what kind of assistance is needed.
                </div>

            </div>


            <!-- People and Urgency -->

            <div class="row g-4 mb-4">

                <div class="col-md-6">

                    <label class="form-label">
                        Number of People Affected <span class="required">*</span>
                    </label>

                    <input
                        type="number"
                        name="people_affected"
                        class="form-control"
                        min="1"
                        value="<?= htmlspecialchars($people_affected, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="e.g. 100"
                        required
                    >

                    <div class="field-help">
                        Total number of people who need assistance.
                    </div>

                </div>


                <div class="col-md-6">

                    <label class="form-label">
                        Urgency Level <span class="required">*</span>
                    </label>

                    <div class="row g-2">

                        <div class="col-6">
                            <label class="urgency-option">

                                <input
                                    type="radio"
                                    name="urgency_level"
                                    value="low"
                                    <?= $urgency_level === 'low' ? 'checked' : '' ?>
                                    required
                                >

                                <div class="urgency-box">
                                    <span class="urgency-dot low-dot"></span>
                                    Low
                                </div>

                            </label>
                        </div>


                        <div class="col-6">
                            <label class="urgency-option">

                                <input
                                    type="radio"
                                    name="urgency_level"
                                    value="medium"
                                    <?= $urgency_level === 'medium' ? 'checked' : '' ?>
                                >

                                <div class="urgency-box">
                                    <span class="urgency-dot medium-dot"></span>
                                    Medium
                                </div>

                            </label>
                        </div>


                        <div class="col-6">
                            <label class="urgency-option">

                                <input
                                    type="radio"
                                    name="urgency_level"
                                    value="high"
                                    <?= $urgency_level === 'high' ? 'checked' : '' ?>
                                >

                                <div class="urgency-box">
                                    <span class="urgency-dot high-dot"></span>
                                    High
                                </div>

                            </label>
                        </div>


                        <div class="col-6">
                            <label class="urgency-option">

                                <input
                                    type="radio"
                                    name="urgency_level"
                                    value="critical"
                                    <?= $urgency_level === 'critical' ? 'checked' : '' ?>
                                >

                                <div class="urgency-box">
                                    <span class="urgency-dot critical-dot"></span>
                                    Critical
                                </div>

                            </label>
                        </div>

                    </div>

                </div>

            </div>


            <!-- Location -->

            <div class="mb-4">

                <label class="form-label">
                    Emergency Location <span class="required">*</span>
                </label>

                <textarea
                    name="location"
                    class="form-control"
                    rows="2"
                    placeholder="Enter village, area, union, upazila or district..."
                    required
                ><?= htmlspecialchars($location, ENT_QUOTES, 'UTF-8') ?></textarea>

                <div class="field-help">
                    Provide a clear location so the response team can identify the affected area.
                </div>

            </div>


            <!-- Submit -->

            <button type="submit" class="submit-btn">
                <i class="bi bi-send-fill me-2"></i>
                Submit Relief Request
            </button>

        </form>

    </section>


    <!-- Back to Dashboard -->

    <div class="text-center">

        <a href="dashboard.php" class="back-link">
            <i class="bi bi-arrow-left"></i>
            Back to Dashboard
        </a>

    </div>


    <!-- Footer -->

    <footer class="footer">
        <i class="bi bi-heart-fill text-danger me-1"></i>
        Together for a Safer, Stronger Community

        <div class="mt-1">
            ResQLink © 2026 | Disaster Response and Resource Coordination System
        </div>
    </footer>

</main>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
