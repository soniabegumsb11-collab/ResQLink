
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

// Fetch current user information
$sql = "SELECT name, email, phone, address
        FROM users
        WHERE id = :id";

$stmt = $db->prepare($sql);
$stmt->execute([':id' => $user_id]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            throw new Exception("Invalid request. Please try again.");
        }

        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($name === '' || $phone === '' || $address === '') {
            throw new Exception("All fields are required.");
        }

        if (strlen($name) > 100) {
            throw new Exception("Name is too long.");
        }

        if (strlen($phone) > 20) {
            throw new Exception("Phone number is too long.");
        }

        // Update profile
        $sql = "UPDATE users
                SET name = :name,
                    phone = :phone,
                    address = :address
                WHERE id = :id";

        $stmt = $db->prepare($sql);

        $stmt->execute([
            ':name' => $name,
            ':phone' => $phone,
            ':address' => $address,
            ':id' => $user_id
        ]);

        // Update session name
        $_SESSION['name'] = $name;

        // Refresh user information
        $user['name'] = $name;
        $user['phone'] = $phone;
        $user['address'] = $address;

        $message = "Profile updated successfully!";
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

    <title>Edit Profile - ResQLink</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-md-7">

            <div class="card shadow-sm">

                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Edit My Profile</h3>
                </div>

                <div class="card-body">

                    <?php if ($message !== ""): ?>

                        <div class="alert alert-<?php echo e($messageType); ?>">
                            <?php echo e($message); ?>
                        </div>

                    <?php endif; ?>

                    <form method="POST" action="edit_profile.php">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?php echo e($_SESSION['csrf_token']); ?>">

                        <div class="mb-3">

                            <label class="form-label">Full Name</label>

                            <input type="text"
                                   name="name"
                                   class="form-control"
                                   value="<?php echo e($user['name']); ?>"
                                   maxlength="100"
                                   required>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">Email Address</label>

                            <input type="email"
                                   class="form-control"
                                   value="<?php echo e($user['email']); ?>"
                                   readonly>

                            <small class="text-muted">
                                Email address cannot be changed here.
                            </small>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">Phone Number</label>

                            <input type="text"
                                   name="phone"
                                   class="form-control"
                                   value="<?php echo e($user['phone']); ?>"
                                   maxlength="20"
                                   required>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">Address</label>

                            <textarea name="address"
                                      class="form-control"
                                      rows="3"
                                      required><?php echo e($user['address']); ?></textarea>

                        </div>

                        <button type="submit" class="btn btn-primary">
                            Update Profile
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
