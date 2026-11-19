
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
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($request_id < 1) {
    header("Location: my_requests.php");
    exit;
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch request belonging to the logged-in requester
$sql = "SELECT * FROM relief_requests
        WHERE id = :id AND requester_id = :requester_id";

$stmt = $db->prepare($sql);
$stmt->execute([
    ':id' => $request_id,
    ':requester_id' => $user_id
]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    header("Location: my_requests.php");
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF Verification
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $csrf_token)
    ) {
        $errors[] = "Invalid form submission. Please try again.";
    }

    // Submitted values
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $people_affected = trim($_POST['people_affected'] ?? '');
    $urgency_level = $_POST['urgency_level'] ?? '';
    $location = trim($_POST['location'] ?? '');

    // Preserve submitted values
    $request['category'] = $category;
    $request['description'] = $description;
    $request['people_affected'] = $people_affected;
    $request['urgency_level'] = $urgency_level;
    $request['location'] = $location;

    // Check request status
    if ($request['status'] !== 'pending') {
        $errors[] = "Only pending requests can be edited.";
    }

    // Required field validation
    if (
        $category === '' ||
        $description === '' ||
        $location === ''
    ) {
        $errors[] = "Please fill in all required fields.";
    }

    // People affected validation
    if (
        filter_var($people_affected, FILTER_VALIDATE_INT) === false ||
        (int)$people_affected < 1
    ) {
        $errors[] = "At least one affected person is required.";
    }

    // Category validation
    if (!in_array(
        $category,
        ['Food', 'Water', 'Medicine', 'Shelter'],
        true
    )) {
        $errors[] = "Invalid relief category.";
    }

    // Urgency validation
    if (!in_array(
        $urgency_level,
        ['low', 'medium', 'high', 'critical'],
        true
    )) {
        $errors[] = "Invalid urgency level.";
    }

    // Update database
    if (empty($errors)) {

        $urgency_scores = [
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            'critical' => 4
        ];

        $priority_score =
            (int)$people_affected * $urgency_scores[$urgency_level];

        $sql = "UPDATE relief_requests
                SET category = :category,
                    description = :description,
                    people_affected = :people_affected,
                    urgency_level = :urgency_level,
                    location = :location,
                    priority_score = :priority_score
                WHERE id = :id
                AND requester_id = :requester_id
                AND status = 'pending'";

        $stmt = $db->prepare($sql);

        $stmt->execute([
            ':category' => $category,
            ':description' => $description,
            ':people_affected' => (int)$people_affected,
            ':urgency_level' => $urgency_level,
            ':location' => $location,
            ':priority_score' => $priority_score,
            ':id' => $request_id,
            ':requester_id' => $user_id
        ]);

        if ($stmt->rowCount() > 0) {

            $success = "Relief request updated successfully!";

        } else {

            // Check whether the request is still pending
            $check = $db->prepare(
                "SELECT status FROM relief_requests
                 WHERE id = :id AND requester_id = :requester_id"
            );

            $check->execute([
                ':id' => $request_id,
                ':requester_id' => $user_id
            ]);

            $current_request = $check->fetch(PDO::FETCH_ASSOC);

            if (
                $current_request &&
                $current_request['status'] === 'pending'
            ) {
                $success = "No changes were needed. Your request is already up to date.";
            } else {
                $errors[] = "This request is no longer pending and cannot be edited.";
            }
        }

        // Refresh request data
        $stmt = $db->prepare(
            "SELECT * FROM relief_requests
             WHERE id = :id AND requester_id = :requester_id"
        );

        $stmt->execute([
            ':id' => $request_id,
            ':requester_id' => $user_id
        ]);

        $request = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$userName = $_SESSION['name'] ?? 'Requester';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Relief Request - ResQLink</title>

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

        /* Sidebar */

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

        /* Main Content */

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

        /* Welcome Banner */

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

        /* Form Card */

        .form-card {
            background: #ffffff;
            border: 1px solid #e7ecf3;
            border-radius: 15px;
            padding: 28px;
            box-shadow: 0 3px 12px rgba(30, 55, 90, 0.035);
            max-width: 950px;
            margin: 0 auto;
        }

        .form-heading {
            display: flex;
            align-items: center;
            gap: 13px;
            border-bottom: 1px solid #edf1f6;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }

        .heading-icon {
            width: 46px;
            height: 46px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e8f1ff;
            color: #1769d2;
            border-radius: 12px;
            font-size: 22px;
        }

        .form-heading h3 {
            color: #20344f;
            font-size: 19px;
            font-weight: 700;
            margin: 0 0 5px;
        }

        .form-heading p {
            color: #8492a6;
            font-size: 13px;
            margin: 0;
        }

        .form-label {
            color: #26364a;
            font-size: 14px;
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
            font-size: 14px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #1769d2;
            box-shadow: 0 0 0 3px rgba(23, 105, 210, 0.10);
        }

        .form-text {
            font-size: 12px;
            color: #8492a6;
        }

        .request-id {
            display: inline-block;
            background: #eaf3ff;
            color: #1769d2;
            padding: 6px 11px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 700;
        }

        /* Buttons */

        .btn-update {
            width: 100%;
            background: #1769d2;
            color: #ffffff;
            border: none;
            padding: 14px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 700;
            transition: 0.2s;
        }

        .btn-update:hover {
            background: #1056b4;
            color: #ffffff;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #1769d2;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .back-link:hover {
            color: #1056b4;
        }

        .footer {
            text-align: center;
            color: #9aa6b5;
            font-size: 12px;
            margin-top: 30px;
            padding: 15px;
        }

        .alert {
            border-radius: 10px;
            font-size: 14px;
        }

        /* Responsive */

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

            .form-card {
                padding: 20px 16px;
            }

            .form-heading h3 {
                font-size: 17px;
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

        <div class="top-heading">

            <div>
                <h1>Edit Relief Request</h1>
                <p>Update your request details so the right support can reach you.</p>
            </div>

            <div class="user-info">
                <i class="bi bi-person-circle text-primary"></i>
                <?= htmlspecialchars($userName) ?>
                <span class="text-muted">| Requester</span>
            </div>

        </div>

        <!-- Welcome Banner -->

        <section class="welcome-banner">

            <h2>Update Your Relief Request</h2>

            <p>
                Keep your emergency information accurate so the
                response team can coordinate the right support.
            </p>

        </section>

        <!-- Edit Form -->

        <section class="form-card">

            <?php foreach ($errors as $error): ?>

                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endforeach; ?>

            <?php if (!empty($success)): ?>

                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= htmlspecialchars($success) ?>
                </div>

            <?php endif; ?>

            <?php if ($request['status'] === 'pending'): ?>

                <div class="form-heading">

                    <div class="heading-icon">
                        <i class="bi bi-clipboard2-check"></i>
                    </div>

                    <div>
                        <h3>Update Request Information</h3>
                        <p>
                            Request ID:
                            <span class="request-id">
                                #<?= (int)$request_id ?>
                            </span>
                        </p>
                    </div>

                </div>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                    >

                    <!-- Category -->

                    <div class="mb-4">

                        <label class="form-label">
                            Relief Category <span class="required">*</span>
                        </label>

                        <select name="category" class="form-select" required>

                            <option value="Food"
                                <?= $request['category'] === 'Food' ? 'selected' : '' ?>>
                                Food
                            </option>

                            <option value="Water"
                                <?= $request['category'] === 'Water' ? 'selected' : '' ?>>
                                Water
                            </option>

                            <option value="Medicine"
                                <?= $request['category'] === 'Medicine' ? 'selected' : '' ?>>
                                Medicine
                            </option>

                            <option value="Shelter"
                                <?= $request['category'] === 'Shelter' ? 'selected' : '' ?>>
                                Shelter
                            </option>

                        </select>

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
                            placeholder="Explain your current situation..."
                            required
                        ><?= htmlspecialchars($request['description']) ?></textarea>

                    </div>

                    <!-- People and Urgency -->

                    <div class="row g-3 mb-4">

                        <div class="col-md-6">

                            <label class="form-label">
                                Number of People Affected
                                <span class="required">*</span>
                            </label>

                            <input
                                type="number"
                                name="people_affected"
                                class="form-control"
                                min="1"
                                value="<?= htmlspecialchars((string)$request['people_affected']) ?>"
                                required
                            >

                            <div class="form-text">
                                Enter the total number of people who need assistance.
                            </div>

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Urgency Level <span class="required">*</span>
                            </label>

                            <select name="urgency_level" class="form-select" required>

                                <option value="low"
                                    <?= $request['urgency_level'] === 'low' ? 'selected' : '' ?>>
                                    Low
                                </option>

                                <option value="medium"
                                    <?= $request['urgency_level'] === 'medium' ? 'selected' : '' ?>>
                                    Medium
                                </option>

                                <option value="high"
                                    <?= $request['urgency_level'] === 'high' ? 'selected' : '' ?>>
                                    High
                                </option>

                                <option value="critical"
                                    <?= $request['urgency_level'] === 'critical' ? 'selected' : '' ?>>
                                    Critical
                                </option>

                            </select>

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
                            placeholder="Enter your emergency location..."
                            required
                        ><?= htmlspecialchars($request['location']) ?></textarea>

                    </div>

                    <!-- Update Button -->

                    <button type="submit" class="btn-update">

                        <i class="bi bi-check2-circle me-2"></i>
                        Update Relief Request

                    </button>

                </form>

            <?php else: ?>

                <div class="alert alert-warning">
                    <i class="bi bi-lock-fill me-2"></i>
                    This request is no longer pending and cannot be edited.
                </div>

            <?php endif; ?>

            <div class="text-center">

                <a href="my_requests.php" class="back-link">
                    <i class="bi bi-arrow-left me-2"></i>
                    Back to My Requests
                </a>

            </div>

        </section>

        <!-- Footer -->

        <footer class="footer">

            Together for a Safer, Stronger Community

            <br><br>

            ResQLink © 2026 | Disaster Response and Resource Coordination System

        </footer>

    </main>

</body>
</html>
