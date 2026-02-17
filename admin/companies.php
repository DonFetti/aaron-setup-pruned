<?php require_once __DIR__ . '/partials/no-cache.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Companies - CRM Dashboard</title>
    <?php include 'partials/header.php'; ?>
</head>
<body>
    <?php include 'partials/nav.php'; ?>

    <div class="container-fluid mt-4">
        <?php
        $search_query = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
        $filter_company_type = isset($_GET['company_type']) ? trim((string) $_GET['company_type']) : '';

        $companies = [];
        $company_types = [];
        $stats_total = 0;
        if ($pdo && !isset($db_error)) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM companies");
                $stats_total = (int) $stmt->fetch()['count'];

                $stmt = $pdo->query("SELECT DISTINCT company_type FROM companies WHERE company_type IS NOT NULL AND company_type != '' ORDER BY company_type ASC");
                $company_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $sql = "SELECT c.id, c.name, c.company_type, c.created_at,
                         (SELECT COUNT(*) FROM contacts WHERE company_id = c.id) AS contact_count
                         FROM companies c
                         WHERE 1=1";
                $params = [];
                if ($search_query !== '') {
                    $sql .= " AND c.name ILIKE :search";
                    $params[':search'] = '%' . $search_query . '%';
                }
                if ($filter_company_type !== '') {
                    $sql .= " AND c.company_type = :company_type";
                    $params[':company_type'] = $filter_company_type;
                }
                $sql .= " ORDER BY c.name ASC";
                if ($params) {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                } else {
                    $stmt = $pdo->query($sql);
                }
                $companies = $stmt->fetchAll();
            } catch (PDOException $e) {
                $db_error = $e->getMessage();
            }
        }
        ?>

        <?php
        $flash_error = isset($_GET['error']) ? trim((string) $_GET['error']) : null;
        ?>
        <?php if ($flash_error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-3">
            <div class="col-12">
                <h2>Companies</h2>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Companies</h5>
                        <h2 class="card-text"><?php echo $stats_total; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">All Companies</h5>
                        <form method="GET" action="" class="d-flex flex-wrap gap-2 align-items-center">
                            <?php if ($search_query): ?>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php endif; ?>
                            <select name="company_type" class="form-select form-select-sm" style="width: auto;" aria-label="Filter by industry type" onchange="this.form.submit()">
                                <option value="">All Types</option>
                                <?php foreach ($company_types as $ct): ?>
                                    <option value="<?php echo htmlspecialchars($ct, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filter_company_type === $ct ? 'selected' : ''; ?>><?php echo htmlspecialchars($ct, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($search_query || $filter_company_type !== ''): ?>
                                <a href="companies.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="card-body">
                        <?php if (isset($db_error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <strong>Database Connection Error:</strong><br>
                                <?php echo htmlspecialchars($db_error, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php else: ?>
                        <!-- Search Bar -->
                        <div class="mb-3">
                            <form method="GET" action="" class="d-flex gap-2">
                                <?php if ($filter_company_type !== ''): ?>
                                    <input type="hidden" name="company_type" value="<?php echo htmlspecialchars($filter_company_type, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <input type="text"
                                           class="form-control"
                                           name="search"
                                           id="companySearch"
                                           placeholder="Search companies by name..."
                                           value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <button type="submit" class="btn btn-outline-primary">Search</button>
                                <?php if ($search_query): ?>
                                    <?php
                                    $clear_params = $filter_company_type !== '' ? ['company_type=' . urlencode($filter_company_type)] : [];
                                    $clear_url = 'companies.php' . (count($clear_params) ? '?' . implode('&', $clear_params) : '');
                                    ?>
                                    <a href="<?php echo htmlspecialchars($clear_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                        <?php if (empty($companies)): ?>
                            <p class="text-muted mb-0"><?php echo ($search_query || $filter_company_type !== '') ? 'No companies found matching your criteria.' : 'No companies yet. Create a company when adding a contact.'; ?></p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Contacts</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($companies as $co): ?>
                                            <?php
                                            $created_date = !empty($co['created_at']) ? new DateTime($co['created_at']) : null;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($co['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($co['company_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo (int) $co['contact_count']; ?></td>
                                                <td><?php echo $created_date ? $created_date->format('M d, Y') : '—'; ?></td>
                                                <td>
                                                    <a href="company.php?id=<?php echo (int) $co['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
