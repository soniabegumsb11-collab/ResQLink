
<?php

require_once '../includes/auth_check.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

$message = "";

// Create CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle notification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        // Validate CSRF token
        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            throw new Exception("Invalid request. Please try again.");
        }

        // Mark all notifications as read
        if (isset($_POST['mark_all_read'])) {

            $sql = "UPDATE notifications
                    SET is_read = 1
                    WHERE user_id = :user_id";

            $stmt = $db->prepare($sql);

            $stmt->execute([
                ':user_id' => $user_id
            ]);

            $_SESSION['notification_message'] =
                "All notifications marked as read successfully!";

        }

        // Mark individual notification as read
        elseif (isset($_POST['mark_one_read'])) {

            $notification_id = (int)($_POST['notification_id'] ?? 0);

            if ($notification_id <= 0) {
                throw new Exception("Invalid notification.");
            }

            $sql = "UPDATE notifications
                    SET is_read = 1
                    WHERE id = :id
                    AND user_id = :user_id";

            $stmt = $db->prepare($sql);

            $stmt->execute([
                ':id' => $notification_id,
                ':user_id' => $user_id
            ]);

            $_SESSION['notification_message'] =
                $stmt->rowCount() > 0
                ? "Notification marked as read successfully!"
                : "Notification not found or already read.";

        }

        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

// Get flash message
if (isset($_SESSION['notification_message'])) {
    $message = $_SESSION['notification_message'];
    unset($_SESSION['notification_message']);
}

// Get user's notifications
$sql = "SELECT *
        FROM notifications
        WHERE user_id = :user_id
        ORDER BY created_at DESC";

$stmt = $db->prepare($sql);

$stmt->execute([
    ':user_id' => $user_id
]);

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count unread notifications
$unread_count = 0;

foreach ($notifications as $notification) {
    if ($notification['is_read'] == 0) {
        $unread_count++;
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

    <title>My Notifications - ResQLink</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-4">

    <h2 class="mb-3">My Notifications</h2>

    <p>
        Welcome, <?php echo e($_SESSION['name']); ?>!
    </p>

    <?php if ($message !== ""): ?>
        <div class="alert alert-info">
            <?php echo e($message); ?>
        </div>
    <?php endif; ?>

    <div class="alert alert-info">
        Unread Notifications:
        <strong><?php echo $unread_count; ?></strong>
    </div>

    <?php if ($unread_count > 0): ?>

        <form method="POST" class="mb-3">

            <input type="hidden"
                   name="csrf_token"
                   value="<?php echo e($_SESSION['csrf_token']); ?>">

            <button type="submit"
                    name="mark_all_read"
                    value="1"
                    class="btn btn-primary">

                Mark All as Read

            </button>

        </form>

    <?php endif; ?>

    <hr>

    <?php if (count($notifications) > 0): ?>

        <?php foreach ($notifications as $notification): ?>

            <div class="card mb-3">

                <div class="card-body">

                    <h5 class="card-title">

                        <?php echo e($notification['title']); ?>

                        <?php if ($notification['is_read'] == 0): ?>

                            <span class="badge bg-warning text-dark">
                                New
                            </span>

                        <?php else: ?>

                            <span class="badge bg-success">
                                Read
                            </span>

                        <?php endif; ?>

                    </h5>

                    <p class="card-text">
                        <?php echo e($notification['message']); ?>
                    </p>

                    <small class="text-muted d-block mb-3">
                        <?php echo e($notification['created_at']); ?>
                    </small>

                    <?php if ($notification['is_read'] == 0): ?>

                        <form method="POST">

                            <input type="hidden"
                                   name="csrf_token"
                                   value="<?php echo e($_SESSION['csrf_token']); ?>">

                            <input type="hidden"
                                   name="notification_id"
                                   value="<?php echo e($notification['id']); ?>">

                            <button type="submit"
                                    name="mark_one_read"
                                    value="1"
                                    class="btn btn-outline-primary btn-sm">

                                Mark as Read

                            </button>

                        </form>

                    <?php endif; ?>

                </div>

            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <div class="alert alert-secondary">
            You have no notifications yet.
        </div>

    <?php endif; ?>

    <a href="../index.php" class="btn btn-secondary">
        Back to Home
    </a>

</div>

</body>
</html>
