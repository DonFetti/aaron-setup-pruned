<?php
require_once __DIR__ . '/partials/no-cache.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$company_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($company_id <= 0) {
    header('Location: /admin/companies.php?error=' . urlencode('Company not specified.'));
    exit;
}

$company = null;
$contacts = [];
$db_error = null;

if ($pdo && !isset($db_error)) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, company_type, created_at FROM companies WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $company_id]);
        $company = $stmt->fetch();

        if ($company) {
            $stmt = $pdo->prepare("SELECT id, name, email, role, status, created_at
                                   FROM contacts
                                   WHERE company_id = :company_id
                                   ORDER BY name ASC");
            $stmt->execute([':company_id' => $company_id]);
            $contacts = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $db_error = $e->getMessage();
    }
}

if (!$company) {
    header('Location: /admin/companies.php?error=' . urlencode('Company not found.'));
    exit;
}

$created_date = !empty($company['created_at']) ? new DateTime($company['created_at']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Company: <?php echo htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8'); ?> - CRM Dashboard</title>
    <?php include __DIR__ . '/partials/header.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/partials/nav.php'; ?>

    <div class="container-fluid mt-4">
        <?php if (isset($db_error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($db_error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/admin/companies.php">Companies</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8'); ?></li>
                    </ol>
                </nav>
                <a href="/admin/companies.php" class="btn btn-outline-secondary">Back to Companies</a>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Company Details</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-1">Name</p>
                        <p class="mb-3"><?php echo htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="text-muted small mb-1">Type</p>
                        <p class="mb-3"><?php echo htmlspecialchars($company['company_type'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="text-muted small mb-1">Created</p>
                        <p class="mb-3"><?php echo $created_date ? $created_date->format('M d, Y') : '—'; ?></p>
                        <p class="text-muted small mb-1">Company ID</p>
                        <p class="mb-0 font-monospace small"><?php echo (int) $company['id']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Contacts (<?php echo count($contacts); ?>)</h5>
                        <a href="/admin/contacts.php" class="btn btn-sm btn-primary">Add Contact</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($contacts)): ?>
                            <p class="text-muted mb-0">No contacts linked to this company yet. <a href="/admin/contacts.php">Add a contact</a> and select this company.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($contacts as $contact): ?>
                                            <?php
                                            $contact_created = !empty($contact['created_at']) ? new DateTime($contact['created_at']) : null;
                                            $status_class = $contact['status'] === 'active' ? 'bg-success' : ($contact['status'] === 'inactive' ? 'bg-secondary' : '');
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($contact['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo $contact['email'] ? htmlspecialchars($contact['email'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">—</span>'; ?></td>
                                                <td><?php echo $contact['role'] ? htmlspecialchars($contact['role'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">—</span>'; ?></td>
                                                <td>
                                                    <?php if ($contact['status']): ?>
                                                        <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($contact['status']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $contact_created ? $contact_created->format('M d, Y') : '—'; ?></td>
                                                <td>
                                                    <a href="contact.php?id=<?php echo htmlspecialchars($contact['id'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-primary">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
