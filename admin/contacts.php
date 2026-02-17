<?php require_once __DIR__ . '/partials/no-cache.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Contacts - CRM Dashboard</title>
    <?php include 'partials/header.php'; ?>
</head>
<body>
    <?php include 'partials/nav.php'; ?>

    <div class="container-fluid mt-4">
        <?php
        // Get filter parameters
        $filter_status = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
        $search_query = isset($_GET['search']) ? trim((string) $_GET['search']) : '';

        // Initialize stats
        $stats_total = 0;
        $stats_active = 0;
        $stats_inactive = 0;
        $stats_with_open_deals = 0;
        $contacts = [];

        if ($pdo && !isset($db_error)) {
            try {
                // Get total contacts count
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM contacts");
                $stats_total = $stmt->fetch()['count'];

                // Get active contacts count
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM contacts WHERE status = 'active'");
                $stats_active = $stmt->fetch()['count'];

                // Get inactive contacts count
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM contacts WHERE status = 'inactive'");
                $stats_inactive = $stmt->fetch()['count'];

                // Get contacts with open deals (deals that are not won or lost)
                $stmt = $pdo->query("SELECT COUNT(DISTINCT c.id) as count 
                                     FROM contacts c 
                                     INNER JOIN deals d ON c.id = d.contact_id 
                                     WHERE d.won_at IS NULL AND d.lost_at IS NULL");
                $stats_with_open_deals = $stmt->fetch()['count'];

                // Build query for contacts
                $sql = "SELECT 
                    id,
                    name,
                    email,
                    company,
                    role,
                    phone,
                    status,
                    source,
                    created_at
                FROM contacts
                WHERE 1=1";

                $params = [];

                // Add status filter
                if ($filter_status && in_array($filter_status, ['active', 'inactive'], true)) {
                    $sql .= " AND status = :status";
                    $params[':status'] = $filter_status;
                }

                // Add search filter
                if ($search_query !== '') {
                    $sql .= " AND (
                        name ILIKE :search 
                        OR email ILIKE :search 
                        OR company ILIKE :search 
                        OR phone ILIKE :search
                    )";
                    $params[':search'] = '%' . $search_query . '%';
                }

                $sql .= " ORDER BY name ASC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $contacts = $stmt->fetchAll();
            } catch (PDOException $e) {
                $db_error = $e->getMessage();
            }
        }
        ?>

        <?php
        $flash_error = isset($_GET['error']) ? trim((string) $_GET['error']) : null;
        $flash_created = isset($_GET['created']) && $_GET['created'] === '1';
        $flash_deleted = isset($_GET['deleted']) && $_GET['deleted'] === '1';
        ?>
        <?php if ($flash_error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($flash_created): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Contact created successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($flash_deleted): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Contact deleted successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <h2>Contacts</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addContactModal">
                    Add Contact
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Contacts</h5>
                        <h2 class="card-text"><?php echo $stats_total; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Active</h5>
                        <h2 class="card-text"><?php echo $stats_active; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Inactive</h5>
                        <h2 class="card-text"><?php echo $stats_inactive; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">With Open Deals</h5>
                        <h2 class="card-text"><?php echo $stats_with_open_deals; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters + Table -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">All Contacts</h5>
                        <form method="GET" action="" class="d-flex gap-2">
                            <?php if ($search_query): ?>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php endif; ?>
                            <select name="status" class="form-select form-select-sm" style="width: auto;" aria-label="Filter by status" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                            <?php if ($filter_status || $search_query): ?>
                                <a href="contacts.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="card-body">
                        <?php if (isset($db_error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <strong>Database Connection Error:</strong><br>
                                <?php echo htmlspecialchars($db_error, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                        <!-- Search Bar -->
                        <div class="mb-3">
                            <form method="GET" action="" class="d-flex gap-2">
                                <?php if ($filter_status): ?>
                                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <input type="text" 
                                           class="form-control" 
                                           name="search" 
                                           id="contactSearch" 
                                           placeholder="Search contacts by name, email, company, or phone..." 
                                           value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <button type="submit" class="btn btn-outline-primary">
                                    Search
                                </button>
                                <?php if ($search_query): ?>
                                    <a href="contacts.php<?php echo $filter_status ? '?status=' . urlencode($filter_status) : ''; ?>" class="btn btn-outline-secondary">
                                        Clear
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Company</th>
                                        <th>Role</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                        <th>Source</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($contacts)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">
                                                <?php if ($search_query || $filter_status): ?>
                                                    No contacts found matching your criteria.
                                                <?php else: ?>
                                                    No contacts found.
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($contacts as $contact): 
                                            $created_date = $contact['created_at'] ? new DateTime($contact['created_at']) : null;
                                            
                                            // Status badge classes
                                            $status_class = '';
                                            if ($contact['status'] === 'active') {
                                                $status_class = 'bg-success';
                                            } elseif ($contact['status'] === 'inactive') {
                                                $status_class = 'bg-secondary';
                                            }
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($contact['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo $contact['email'] ? htmlspecialchars($contact['email'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">—</span>'; ?></td>
                                                <td><?php echo $contact['company'] ? htmlspecialchars($contact['company'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">—</span>'; ?></td>
                                                <td><?php echo $contact['role'] ? htmlspecialchars($contact['role'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">—</span>'; ?></td>
                                                <td><?php echo $contact['phone'] ? htmlspecialchars($contact['phone'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">—</span>'; ?></td>
                                                <td>
                                                    <?php if ($contact['status']): ?>
                                                        <span class="badge <?php echo $status_class; ?>">
                                                            <?php echo ucfirst($contact['status']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $contact['source'] ? htmlspecialchars($contact['source'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">—</span>'; ?></td>
                                                <td>
                                                    <?php if ($created_date): ?>
                                                        <?php echo $created_date->format('M d, Y'); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="contact.php?id=<?php echo htmlspecialchars($contact['id'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-primary">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Contact Modal: TODO form action, validation, redirect -->
    <div class="modal fade" id="addContactModal" tabindex="-1" aria-labelledby="addContactModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="/admin/contact-create.php" method="POST">
                    <input type="hidden" name="add_contact" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addContactModalLabel">Add Contact</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="add_name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="add_name" name="name" required placeholder="Full name">
                        </div>
                        <div class="mb-3">
                            <label for="add_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="add_email" name="email" placeholder="email@example.com">
                        </div>
                        <div class="mb-3">
                            <label for="add_phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="add_phone" name="phone" placeholder="+1 555 0000">
                        </div>
                        <div class="mb-3">
                            <label for="add_company" class="form-label">Company</label>
                            <input type="text" class="form-control" id="add_company" name="company" placeholder="Company name">
                        </div>
                        <div class="mb-3">
                            <label for="add_role" class="form-label">Role</label>
                            <input type="text" class="form-control" id="add_role" name="role" placeholder="Job title or role">
                        </div>
                        <div class="mb-3">
                            <label for="add_status" class="form-label">Status</label>
                            <select class="form-select" id="add_status" name="status">
                                <option value="">— None —</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="add_source" class="form-label">Source</label>
                            <input type="text" class="form-control" id="add_source" name="source" placeholder="e.g. Website, Referral">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Contact</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
