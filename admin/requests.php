
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
$admin_message = $_SESSION['admin_message'] ?? '';
unset($_SESSION['admin_message']);

// Search and Filter
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$urgency = $_GET['urgency'] ?? '';

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Build conditions
$conditions = [];
$params = [];

if ($search !== '') {
    $conditions[] = "(users.name LIKE :search_name
                      OR relief_requests.category LIKE :search_category
                      OR relief_requests.location LIKE :search_location)";

    $params[':search_name'] = "%$search%";
    $params[':search_category'] = "%$search%";
    $params[':search_location'] = "%$search%";
}

if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
    $conditions[] = "relief_requests.status = :status";
    $params[':status'] = $status;
}

if (in_array($urgency, ['low', 'medium', 'high', 'critical'], true)) {
    $conditions[] = "relief_requests.urgency_level = :urgency";
    $params[':urgency'] = $urgency;
}

$where = '';

if (!empty($conditions)) {
    $where = ' WHERE ' . implode(' AND ', $conditions);
}

// Count requests
$count_sql = "SELECT COUNT(*)
              FROM relief_requests
              JOIN users ON relief_requests.requester_id = users.id"
              . $where;

$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);

$total_requests = $count_stmt->fetchColumn();

$total_pages = max(1, (int)ceil($total_requests / $limit));

if ($page > $total_pages) {
    $page = $total_pages;
}

$offset = ($page - 1) * $limit;

// Fetch requests
$sql = "SELECT relief_requests.*, users.name AS requester_name
        FROM relief_requests
        JOIN users ON relief_requests.requester_id = users.id"
        . $where .
        " ORDER BY relief_requests.priority_score DESC
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Relief Requests - ResQLink</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container-fluid py-4">

    <h1>Manage Relief Requests</h1>

    <p>Welcome, <?= htmlspecialchars($_SESSION['name']) ?>!</p>

    <hr>

    <div id="messageArea">

        <?php if (!empty($admin_message)): ?>
            <div class="alert alert-info">
                <?= htmlspecialchars($admin_message) ?>
            </div>
        <?php endif; ?>

    </div>

    <h2>All Relief Requests</h2>

    <!-- Search and Filter -->
    <form method="GET" class="card card-body mb-4">

        <div class="row g-3">

            <div class="col-md-4">
                <label class="form-label">Search</label>

                <input type="text"
                       name="search"
                       class="form-control"
                       placeholder="Requester, category or location"
                       value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Filter by Status</label>

                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Filter by Urgency</label>

                <select name="urgency" class="form-select">
                    <option value="">All Urgency Levels</option>
                    <option value="low" <?= $urgency === 'low' ? 'selected' : '' ?>>Low</option>
                    <option value="medium" <?= $urgency === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="high" <?= $urgency === 'high' ? 'selected' : '' ?>>High</option>
                    <option value="critical" <?= $urgency === 'critical' ? 'selected' : '' ?>>Critical</option>
                </select>
            </div>

            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary">
                    Search
                </button>

                <a href="requests.php" class="btn btn-secondary">
                    Reset
                </a>
            </div>

        </div>

    </form>

    <p>
        Total Matching Requests:
        <strong><?= htmlspecialchars((string)$total_requests) ?></strong>
    </p>

    <?php if (count($requests) > 0): ?>

        <div class="table-responsive">

            <table class="table table-bordered table-striped table-hover bg-white">

                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Requester</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>People Affected</th>
                        <th>Urgency</th>
                        <th>Priority Score</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($requests as $request): ?>

                        <tr id="request-row-<?= (int)$request['id'] ?>">

                            <td><?= (int)$request['id'] ?></td>

                            <td><?= htmlspecialchars($request['requester_name']) ?></td>

                            <td><?= htmlspecialchars($request['category']) ?></td>

                            <td><?= htmlspecialchars($request['description']) ?></td>

                            <td><?= (int)$request['people_affected'] ?></td>

                            <td><?= htmlspecialchars($request['urgency_level']) ?></td>

                            <td><?= (int)$request['priority_score'] ?></td>

                            <td><?= htmlspecialchars($request['location']) ?></td>

                            <td class="request-status">
                                <?= htmlspecialchars($request['status']) ?>
                            </td>

                            <td><?= htmlspecialchars($request['created_at']) ?></td>

                            <td class="request-actions">

                                <?php if ($request['status'] === 'pending'): ?>

                                    <form class="status-form">

                                        <input type="hidden"
                                               name="request_id"
                                               value="<?= (int)$request['id'] ?>">

                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                        <button type="submit"
                                                name="status"
                                                value="approved"
                                                class="btn btn-success btn-sm mb-1">
                                            Approve
                                        </button>

                                        <button type="submit"
                                                name="status"
                                                value="rejected"
                                                class="btn btn-danger btn-sm">
                                            Reject
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span class="text-muted">No Action Available</span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>

            <nav aria-label="Request pagination">

                <ul class="pagination">

                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">

                        <a class="page-link"
                           href="?search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&urgency=<?= urlencode($urgency) ?>&page=<?= $page - 1 ?>">
                            Previous
                        </a>

                    </li>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>

                        <li class="page-item <?= $page === $i ? 'active' : '' ?>">

                            <a class="page-link"
                               href="?search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&urgency=<?= urlencode($urgency) ?>&page=<?= $i ?>">
                                <?= $i ?>
                            </a>

                        </li>

                    <?php endfor; ?>

                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">

                        <a class="page-link"
                           href="?search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&urgency=<?= urlencode($urgency) ?>&page=<?= $page + 1 ?>">
                            Next
                        </a>

                    </li>

                </ul>

            </nav>

        <?php endif; ?>

    <?php else: ?>

        <div class="alert alert-warning">
            No relief requests found.
        </div>

    <?php endif; ?>

    <hr>

    <a href="dashboard.php" class="btn btn-secondary">
        Back to Admin Dashboard
    </a>

    <a href="../auth/logout.php" class="btn btn-danger">
        Logout
    </a>

</div>

<script>

document.querySelectorAll('.status-form').forEach(function(form) {

    form.addEventListener('submit', async function(event) {

        event.preventDefault();

        const button = event.submitter;
        const row = form.closest('tr');
        const statusCell = row.querySelector('.request-status');
        const actionCell = row.querySelector('.request-actions');

        const formData = new FormData(form);

        formData.append('status', button.value);

        const originalText = button.innerText;

        // Disable buttons and show loading
        form.querySelectorAll('button').forEach(function(btn) {
            btn.disabled = true;
        });

        button.innerText = 'Processing...';

        try {

            const response = await fetch('update_request_status.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Something went wrong.');
            }

            // Update status without reloading
            statusCell.innerText = result.status;

            // Remove action buttons
            actionCell.innerHTML =
                '<span class="text-muted">No Action Available</span>';

            // Show success message
            const messageArea = document.getElementById('messageArea');

            messageArea.innerHTML = '';

            const alert = document.createElement('div');
            alert.className = 'alert alert-success';
            alert.textContent = result.message;

            messageArea.appendChild(alert);

        } catch (error) {

            // Restore buttons if request fails
            form.querySelectorAll('button').forEach(function(btn) {
                btn.disabled = false;
            });

            button.innerText = originalText;

            const messageArea = document.getElementById('messageArea');

            messageArea.innerHTML = '';

            const alert = document.createElement('div');
            alert.className = 'alert alert-danger';
            alert.textContent = error.message;

            messageArea.appendChild(alert);

        }

    });

});

</script>

</body>
</html>
