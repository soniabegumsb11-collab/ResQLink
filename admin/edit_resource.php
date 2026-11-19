
<?php

require_once '../includes/auth_check.php';
require_once '../config/database.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    die("Invalid resource ID.");
}

// Fetch Resource
$sql = "SELECT * FROM resources WHERE id = :id";
$stmt = $db->prepare($sql);
$stmt->execute([':id' => $id]);

$resource = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resource) {
    die("Resource not found.");
}

$error = "";

// Update Resource
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
        $error = "Please enter valid resource information.";

    } else {

        try {

            $db->beginTransaction();

            // Update Resource
            $sql = "UPDATE resources
                    SET name = :name,
                        category = :category,
                        quantity_available = :quantity,
                        unit = :unit,
                        storage_location = :location
                    WHERE id = :id";

            $stmt = $db->prepare($sql);

            $stmt->execute([
                ':name' => $name,
                ':category' => $category,
                ':quantity' => $quantity,
                ':unit' => $unit,
                ':location' => $location,
                ':id' => $id
            ]);

            // Insert Activity Log
            $sql = "INSERT INTO activity_logs
                    (user_id, action, entity_type, entity_id, details)
                    VALUES
                    (:user_id, :action, :entity_type, :entity_id, :details)";

            $stmt = $db->prepare($sql);

            $stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':action' => 'EDIT_RESOURCE',
                ':entity_type' => 'resource',
                ':entity_id' => $id,
                ':details' => "Admin updated resource: $name, Category: $category, Quantity: $quantity $unit, Location: $location."
            ]);

            $db->commit();

            $_SESSION['resource_message'] =
                "Resource updated successfully and activity logged!";

            header("Location: resources.php");
            exit;

        } catch (Exception $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $error = "Resource could not be updated. Please try again.";
        }
    }

    $resource['name'] = $name;
    $resource['category'] = $category;
    $resource['quantity_available'] = $_POST['quantity_available'];
    $resource['unit'] = $unit;
    $resource['storage_location'] = $location;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Resource - ResQLink</title>
</head>

<body>

    <h1>Edit Resource</h1>

    <p>Welcome, <?= htmlspecialchars($_SESSION['name']) ?>!</p>

    <hr>

    <?php if (!empty($error)): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
        >

        <label>Resource Name:</label><br>
        <input
            type="text"
            name="name"
            value="<?= htmlspecialchars($resource['name']) ?>"
            required
        >
        <br><br>

        <label>Resource Category:</label><br>

        <select name="category" required>
            <option value="Food" <?= $resource['category'] === 'Food' ? 'selected' : '' ?>>Food</option>
            <option value="Water" <?= $resource['category'] === 'Water' ? 'selected' : '' ?>>Water</option>
            <option value="Medicine" <?= $resource['category'] === 'Medicine' ? 'selected' : '' ?>>Medicine</option>
            <option value="Shelter" <?= $resource['category'] === 'Shelter' ? 'selected' : '' ?>>Shelter</option>
        </select>

        <br><br>

        <label>Available Quantity:</label><br>
        <input
            type="number"
            name="quantity_available"
            min="0"
            value="<?= htmlspecialchars((string)$resource['quantity_available']) ?>"
            required
        >
        <br><br>

        <label>Unit:</label><br>
        <input
            type="text"
            name="unit"
            value="<?= htmlspecialchars($resource['unit']) ?>"
            required
        >
        <br><br>

        <label>Storage Location:</label><br>
        <textarea name="storage_location" required><?= htmlspecialchars($resource['storage_location']) ?></textarea>
        <br><br>

        <button type="submit">Update Resource</button>

    </form>

    <br>

    <a href="resources.php">Back to Resources</a>

</body>
</html>
