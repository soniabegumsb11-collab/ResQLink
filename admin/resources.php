
<?php

require_once '../includes/auth_check.php';
require_once '../config/database.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Admin Message
$message = $_SESSION['resource_message'] ?? '';
unset($_SESSION['resource_message']);

// Add Resource
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die("Invalid request.");
    }

    $name = trim($_POST['name'] ?? '');
    $category = $_POST['category'] ?? '';
    $quantity = filter_var(
        $_POST['quantity_available'] ?? '',
        FILTER_VALIDATE_INT
    );
    $unit = trim($_POST['unit'] ?? '');
    $location = trim($_POST['storage_location'] ?? '');

    $allowed_categories = ['Food', 'Water', 'Medicine', 'Shelter'];

    if (
        $name === '' ||
        !in_array($category, $allowed_categories, true) ||
        $quantity === false ||
        $quantity < 0 ||
        $unit === '' ||
        $location === ''
    ) {
        $_SESSION['resource_message'] =
            "Please enter valid resource information.";

    } else {

        try {

            $db->beginTransaction();

            // Insert Resource
            $sql = "INSERT INTO resources
                    (name, category, quantity_available, unit,
                     storage_location, created_by)
                    VALUES
                    (:name, :category, :quantity, :unit,
                     :location, :created_by)";

            $stmt = $db->prepare($sql);

            $stmt->execute([
                ':name' => $name,
                ':category' => $category,
                ':quantity' => $quantity,
                ':unit' => $unit,
                ':location' => $location,
                ':created_by' => $_SESSION['user_id']
            ]);

            // Get new resource ID
            $resource_id = $db->lastInsertId();

            // Insert Activity Log
            $sql = "INSERT INTO activity_logs
                    (user_id, action, entity_type, entity_id, details)
                    VALUES
                    (:user_id, :action, :entity_type, :entity_id, :details)";

            $stmt = $db->prepare($sql);

            $stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':action' => 'ADD_RESOURCE',
                ':entity_type' => 'resource',
                ':entity_id' => $resource_id,
                ':details' => "Admin added resource: $name, Category: $category, Quantity: $quantity $unit."
            ]);

            $db->commit();

            $_SESSION['resource_message'] =
                "Resource added successfully and activity logged!";

        } catch (Exception $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $_SESSION['resource_message'] =
                "Resource could not be added. Please try again.";
        }
    }

    header("Location: resources.php");
    exit;
}

// Fetch Resources
$sql = "SELECT resources.*, users.name AS admin_name
        FROM resources
        LEFT JOIN users ON resources.created_by = users.id
        ORDER BY resources.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute();

$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Resources - ResQLink</title>
</head>

<body>

    <h1>Manage Resources</h1>

    <p>
        Welcome, <?= htmlspecialchars($_SESSION['name']) ?>!
    </p>

    <hr>

    <?php if (!empty($message)): ?>
        <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <h2>Add New Resource</h2>

    <form method="POST" action="resources.php">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
        >

        <label>Resource Name:</label><br>
        <input type="text" name="name" required>
        <br><br>

        <label>Resource Category:</label><br>

        <select name="category" required>
            <option value="">Select Category</option>
            <option value="Food">Food</option>
            <option value="Water">Water</option>
            <option value="Medicine">Medicine</option>
            <option value="Shelter">Shelter</option>
        </select>

        <br><br>

        <label>Available Quantity:</label><br>
        <input
            type="number"
            name="quantity_available"
            min="0"
            required
        >

        <br><br>

        <label>Unit:</label><br>
        <input
            type="text"
            name="unit"
            placeholder="kg, liter, packet, etc."
            required
        >

        <br><br>

        <label>Storage Location:</label><br>
        <textarea name="storage_location" required></textarea>

        <br><br>

        <button type="submit">Add Resource</button>

    </form>

    <hr>

    <h2>Available Resources</h2>

    <?php if (count($resources) > 0): ?>

        <table border="1" cellpadding="10" cellspacing="0">

            <tr>
                <th>ID</th>
                <th>Resource Name</th>
                <th>Category</th>
                <th>Available Quantity</th>
                <th>Unit</th>
                <th>Storage Location</th>
                <th>Added By</th>
                <th>Actions</th>
            </tr>

            <?php foreach ($resources as $resource): ?>

                <tr>

                    <td>
                        <?= htmlspecialchars((string)$resource['id']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($resource['name']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($resource['category']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars((string)$resource['quantity_available']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($resource['unit']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($resource['storage_location']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($resource['admin_name'] ?? 'Unknown') ?>
                    </td>

                    <td>

                        <a href="edit_resource.php?id=<?= (int)$resource['id'] ?>">
                            Edit
                        </a>

                        |

                        <form
                            method="POST"
                            action="delete_resource.php"
                            style="display:inline;"
                            onsubmit="return confirm('Are you sure you want to delete this resource?');"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                            >

                            <input
                                type="hidden"
                                name="resource_id"
                                value="<?= (int)$resource['id'] ?>"
                            >

                            <button type="submit">Delete</button>

                        </form>

                    </td>

                </tr>

            <?php endforeach; ?>

        </table>

    <?php else: ?>

        <p>No resources available yet.</p>

    <?php endif; ?>

    <br>

    <a href="dashboard.php">Back to Admin Dashboard</a>

    <br><br>

    <a href="activity_logs.php">View Activity Logs</a>

    <br><br>

    <a href="../auth/logout.php">Logout</a>

</body>
</html>
