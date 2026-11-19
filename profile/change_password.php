
<?php

require_once '../includes/auth_check.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

$message = "";
$messageType = "info";

// Create CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            throw new Exception("Invalid request. Please try again.");
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (
            $currentPassword === '' ||
            $newPassword === '' ||
            $confirmPassword === ''
        ) {
            throw new Exception("All fields are required.");
        }

        if (strlen($newPassword) < 8) {
            throw new Exception("New password must be at least 8 characters.");
        }

        if ($newPassword !== $confirmPassword) {
            throw new Exception("New passwords do not match.");
        }

        // Get current password from database
        $sql = "SELECT password FROM users WHERE id = :id";

        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $user_id]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            throw new Exception("Current password is incorrect.");
        }

        if (password_verify($newPassword, $user['password'])) {
            throw new Exception("New password must be different from current password.");
        }

        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update password
        $sql = "UPDATE users
                SET password = :password
                WHERE id = :id";

        $stmt = $db->prepare($sql);

        $stmt->execute([
            ':password' => $hashedPassword,
            ':id' => $user_id
        ]);

        $message = "Password changed successfully!";
        $messageType = "success";

    } catch (Exception $e) {

        $message = $e->getMessage();
        $messageType = "danger";
    }
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Change Password - ResQLink</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-md-7">

            <div class="card shadow-sm">

                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Change Password</h3>
                </div>

                <div class="card-body">

                    <?php if ($message !== ""): ?>

                        <div class="alert alert-<?php echo e($messageType); ?>">
                            <?php echo e($message); ?>
                        </div>

                    <?php endif; ?>

                    <form method="POST" action="change_password.php">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?php echo e($_SESSION['csrf_token']); ?>">

                        <div class="mb-3">

                            <label class="form-label">Current Password</label>

                            <input type="password"
                                   name="current_password"
                                   class="form-control"
                                   required>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">New Password</label>

                            <input type="password"
                                   name="new_password"
                                   class="form-control"
                                   minlength="8"
                                   required>

                            <small class="text-muted">
                                Password must contain at least 8 characters.
                            </small>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">Confirm New Password</label>

                            <input type="password"
                                   name="confirm_password"
                                   class="form-control"
                                   minlength="8"
                                   required>

                        </div>

                        <button type="submit" class="btn btn-primary">
                            Change Password
                        </button>

                        <a href="../index.php" class="btn btn-secondary">
                            Back to Home
                        </a>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>
