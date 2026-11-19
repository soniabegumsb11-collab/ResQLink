
<?php
session_start();

$isLoggedIn = isset($_SESSION['user_id']);
$dashboardLink = '';

if ($isLoggedIn) {
    if ($_SESSION['role'] === 'admin') {
        $dashboardLink = 'admin/dashboard.php';
    } elseif ($_SESSION['role'] === 'requester') {
        $dashboardLink = 'requester/dashboard.php';
    } elseif ($_SESSION['role'] === 'volunteer') {
        $dashboardLink = 'volunteer/dashboard.php';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ResQLink - Home</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f7fc;
            margin: 0;
        }

        .navbar {
            background: #14213d;
        }

        .navbar-brand {
            color: white;
            font-size: 25px;
            font-weight: bold;
        }

        .navbar-brand:hover {
            color: #ffc107;
        }

        .hero {
            background: linear-gradient(135deg, #14213d, #304c89);
            color: white;
            padding: 100px 20px;
            text-align: center;
        }

        .hero h1 {
            font-size: 45px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .hero p {
            font-size: 18px;
            max-width: 700px;
            margin: auto;
            line-height: 1.7;
        }

        .btn-main {
            background: #ffc107;
            color: #14213d;
            font-weight: bold;
            padding: 12px 28px;
            border-radius: 7px;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
        }

        .btn-main:hover {
            background: #e0a800;
            color: #14213d;
        }

        .btn-outline {
            border: 2px solid white;
            color: white;
            padding: 10px 28px;
            border-radius: 7px;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
        }

        .btn-outline:hover {
            background: white;
            color: #14213d;
        }

        .feature-card {
            background: white;
            padding: 30px 20px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.07);
            height: 100%;
            text-align: center;
        }

        .feature-card h4 {
            color: #14213d;
            margin-bottom: 15px;
        }

        .feature-card p {
            color: #555;
            line-height: 1.6;
        }

        .welcome-box {
            background: white;
            color: #14213d;
            max-width: 550px;
            margin: 30px auto 0;
            padding: 20px;
            border-radius: 12px;
        }

        footer {
            background: #14213d;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 50px;
        }

        @media (max-width: 576px) {
            .hero {
                padding: 70px 15px;
            }

            .hero h1 {
                font-size: 32px;
            }

            .hero p {
                font-size: 16px;
            }
        }
    </style>
</head>

<body>

<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="index.php">ResQLink</a>
    </div>
</nav>

<section class="hero">

    <div class="container">

        <h1>Welcome to ResQLink</h1>

        <p>
            A Smart Community Disaster Response and Resource Coordination System.
            Connecting people in need with volunteers and essential relief resources
            during emergencies.
        </p>

        <?php if ($isLoggedIn): ?>

            <div class="welcome-box">

                <h3>
                    Hello, <?= htmlspecialchars($_SESSION['name']) ?>!
                </h3>

                <p class="mb-3">
                    You are logged in as:
                    <strong><?= htmlspecialchars($_SESSION['role']) ?></strong>
                </p>

                <?php if ($dashboardLink !== ''): ?>

                    <a href="<?= htmlspecialchars($dashboardLink) ?>" class="btn-main">
                        Go to Dashboard
                    </a>

                <?php endif; ?>

                <a href="auth/logout.php" class="btn-outline" style="border-color:#14213d;color:#14213d;">
                    Logout
                </a>

            </div>

        <?php else: ?>

            <div class="mt-4">

                <a href="auth/login.php" class="btn-main">
                    Login
                </a>

                <a href="auth/signup.php" class="btn-outline">
                    Sign Up
                </a>

            </div>

        <?php endif; ?>

    </div>

</section>

<section class="container py-5">

    <div class="text-center mb-5">

        <h2 class="fw-bold">Our Main Features</h2>

        <p class="text-muted">
            Supporting communities through organized disaster response.
        </p>

    </div>

    <div class="row g-4">

        <div class="col-md-4">
            <div class="feature-card">
                <h4>Relief Requests</h4>
                <p>
                    People affected by disasters can request food, water,
                    medicine and shelter.
                </p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="feature-card">
                <h4>Volunteer Support</h4>
                <p>
                    Volunteers can receive assignments and track
                    relief delivery activities.
                </p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="feature-card">
                <h4>Resource Management</h4>
                <p>
                    Administrators can manage relief resources,
                    approve requests and coordinate assignments.
                </p>
            </div>
        </div>

    </div>

</section>

<footer>
    <p class="mb-0">
        &copy; <?= date('Y') ?> ResQLink. All Rights Reserved.
    </p>
</footer>

</body>
</html>
