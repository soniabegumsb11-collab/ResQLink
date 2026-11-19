<?php

session_start();

require_once '../config/database.php';
require_once '../classes/Auth.php';

$database = new Database();
$db = $database->getConnection();

$auth = new Auth($db);

$error = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {

        $error = "Please enter email and password.";

    } else {

        $user = $auth->login($email, $password);

        if ($user) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            if ($_SESSION['role'] === 'admin') {

                header("Location: ../admin/dashboard.php");

            } elseif ($_SESSION['role'] === 'volunteer') {

                header("Location: ../volunteer/dashboard.php");

            } else {

                header("Location: ../requester/dashboard.php");

            }

            exit;

        } else {

            $error = "Invalid email or password.";

        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - ResQLink</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>

        body {
            background: #f1f5fb;
            font-family: Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            width: 100%;
            max-width: 450px;
            background: white;
            padding: 40px 35px;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.08);
        }

        .login-title {
            text-align: center;
            color: #14213d;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #6c757d;
            margin-bottom: 30px;
        }

        .form-label {
            font-weight: 600;
            color: #26344d;
        }

        .form-control {
            padding: 12px;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: #14213d;
            color: white;
            font-weight: bold;
            border: none;
            border-radius: 7px;
        }

        .btn-login:hover {
            background: #304c89;
            color: white;
        }

        .signup-link {
            color: #14213d;
            font-weight: bold;
            text-decoration: none;
        }

        .signup-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 576px) {
            .login-card {
                padding: 28px 20px;
            }
        }

    </style>

</head>

<body>

    <div class="login-card">

        <h2 class="login-title">Login to ResQLink</h2>

        <p class="subtitle">
            Welcome back! Please login to continue.
        </p>

        <?php if ($error): ?>

            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <div class="mb-3">

                <label class="form-label">Email</label>

                <input
                    type="email"
                    name="email"
                    class="form-control"
                    value="<?= htmlspecialchars($email) ?>"
                    required
                >

            </div>

            <div class="mb-4">

                <label class="form-label">Password</label>

                <input
                    type="password"
                    name="password"
                    class="form-control"
                    required
                >

            </div>

            <button type="submit" class="btn-login">
                Login
            </button>

        </form>

        <p class="text-center mt-4 mb-0">

            Don't have an account?

            <a href="signup.php" class="signup-link">
                Sign Up
            </a>

        </p>

        <p class="text-center mt-3">

            <a href="../index.php" class="text-secondary text-decoration-none">
                Back to Home
            </a>

        </p>

    </div>

</body>

</html>
