
<?php

require_once '../config/database.php';
require_once '../classes/Auth.php';

$database = new Database();
$db = $database->getConnection();

$auth = new Auth($db);

$errors = [];
$success = "";

$name = "";
$email = "";
$phone = "";
$address = "";
$role = "requester";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $role = $_POST['role'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        $errors[] = "Please fill in all required fields.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (!in_array($role, ['requester', 'volunteer'], true)) {
        $errors[] = "Invalid user role.";
    }

    if (empty($errors)) {
        try {
            $result = $auth->register(
                $name,
                $email,
                $password,
                $phone,
                $address,
                $role
            );

            if ($result) {
                $success = "Registration successful! You can now login.";

                $name = "";
                $email = "";
                $phone = "";
                $address = "";
                $role = "requester";
            }

        } catch (PDOException $e) {
            $errors[] = "This email may already be registered.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sign Up - ResQLink</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f1f5fb;
            font-family: Arial, sans-serif;
            min-height: 100vh;
            padding: 40px 15px;
        }

        .signup-card {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 35px;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.08);
        }

        .signup-title {
            color: #14213d;
            font-weight: bold;
            text-align: center;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: #6c757d;
            margin-bottom: 28px;
        }

        .form-label {
            font-weight: 600;
            color: #26344d;
        }

        .form-control, .form-select {
            padding: 11px;
        }

        .btn-signup {
            background: #14213d;
            color: white;
            padding: 12px;
            font-weight: bold;
            width: 100%;
            border: none;
            border-radius: 7px;
        }

        .btn-signup:hover {
            background: #304c89;
            color: white;
        }

        .login-link {
            color: #14213d;
            font-weight: bold;
            text-decoration: none;
        }

        .login-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 576px) {
            .signup-card {
                padding: 23px 18px;
            }
        }
    </style>
</head>

<body>

<div class="signup-card">

    <h2 class="signup-title">Create ResQLink Account</h2>

    <p class="subtitle">
        Join our community and help during emergencies.
    </p>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endforeach; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
            <br>
            <a href="login.php">Go to Login</a>
        </div>
    <?php endif; ?>

    <form method="POST">

        <div class="mb-3">
            <label class="form-label">Full Name *</label>
            <input
                type="text"
                name="name"
                class="form-control"
                value="<?= htmlspecialchars($name) ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label class="form-label">Email *</label>
            <input
                type="email"
                name="email"
                class="form-control"
                value="<?= htmlspecialchars($email) ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label class="form-label">Password *</label>
            <input
                type="password"
                name="password"
                class="form-control"
                minlength="8"
                required
            >
        </div>

        <div class="mb-3">
            <label class="form-label">Confirm Password *</label>
            <input
                type="password"
                name="confirm_password"
                class="form-control"
                required
            >
        </div>

        <div class="mb-3">
            <label class="form-label">Phone</label>
            <input
                type="text"
                name="phone"
                class="form-control"
                value="<?= htmlspecialchars($phone) ?>"
            >
        </div>

        <div class="mb-3">
            <label class="form-label">Address</label>
            <textarea
                name="address"
                class="form-control"
                rows="3"
            ><?= htmlspecialchars($address) ?></textarea>
        </div>

        <div class="mb-4">
            <label class="form-label">Select Role *</label>

            <select name="role" class="form-select" required>
                <option value="requester" <?= $role === 'requester' ? 'selected' : '' ?>>
                    Requester
                </option>

                <option value="volunteer" <?= $role === 'volunteer' ? 'selected' : '' ?>>
                    Volunteer
                </option>
            </select>
        </div>

        <button type="submit" class="btn-signup">
            Create Account
        </button>

    </form>

    <p class="text-center mt-4 mb-0">
        Already have an account?
        <a href="login.php" class="login-link">Login</a>
    </p>

    <p class="text-center mt-3">
        <a href="../index.php" class="text-secondary text-decoration-none">
            Back to Home
        </a>
    </p>

</div>

</body>
</html>
