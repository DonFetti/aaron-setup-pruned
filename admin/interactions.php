<?php require_once __DIR__ . '/partials/no-cache.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Interactions - CRM Dashboard</title>
    <?php include __DIR__ . '/partials/header.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/partials/nav.php'; ?>

    <div class="container-fluid mt-4">
        <?php
        $filter_type = isset($_GET['type']) ? trim((string) $_GET['type']) : '';
        $filter_direction = isset($_GET['direction']) ? trim((string) $_GET['direction']) : '';
        $search_query = isset($_GET['search']) ? trim((string) $_GET['search']) : '';

        $allowed_types = ['call', 'email', 'meeting', 'sms', 'note'];
        $allowed_directions = ['inbound', 'outbound'];

        $stats_total = 0;
        $stats_calls = 0;
        $stats_emails = 0;
        $stats_meetings = 0;
        $stats_other = 0; // sms + note
        $interactions = [];

        if ($pdo && !isset($db_error)) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM interactions");
                $stats_total = $stmt->fetch()['count'];

                $stmt = $pdo->query("SELECT COUNT(*) as count FROM interactions WHERE type = 'call'");
                $stats_calls = $stmt->fetch()['count'];

                $stmt = $pdo->query("SELECT COUNT(*) as count FROM interactions WHERE type = 'email'");
                $stats_emails = $stmt->fetch()['count'];

                $stmt = $pdo->query("SELECT COUNT(*) as count FROM interactions WHERE type = 'meeting'");
                $stats_meetings = $stmt->fetch()['count'];

                $stmt = $pdo->query("SELECT COUNT(*) as count FROM interactions WHERE type IN ('sms', 'note')");
                $stats_other = $stmt->fetch()['count'];

                $sql = "SELECT
                    i.id,
                    i.type,
                    i.direction,
                    i.subject,
                    i.body,
                    i.occurred_at,
                    i.created_at,
                    i.company_id,
                    c.id AS contact_id,
                    c.name AS contact_name,
                    d.id AS deal_id,
                    d.name AS deal_name,
                    co.name AS company_name
                FROM interactions i
                INNER JOIN contacts c ON i.contact_id = c.id
                LEFT JOIN deals d ON i.deal_id = d.id
                LEFT JOIN companies co ON i.company_id = co.id
                WHERE 1=1";
                $params = [];

                if ($filter_type && in_array($filter_type, $allowed_types, true)) {
                    $sql .= " AND i.type = :type";
                    $params[':type'] = $filter_type;
                }
                if ($filter_direction && in_array($filter_direction, $allowed_directions, true)) {
                    $sql .= " AND i.direction = :direction";
                    $params[':direction'] = $filter_direction;
                }
                if ($search_query !== '') {
                    $sql .= " AND (i.subject ILIKE :search OR i.body ILIKE :search)";
                    $params[':search'] = '%' . $search_query . '%';
                }

                $sql .= " ORDER BY i.occurred_at DESC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $interactions = $stmt->fetchAll();
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
                Interaction created successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($flash_deleted): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Interaction deleted successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <h2>Interactions</h2>
                <a href="interaction-create.php" class="btn btn-primary">Add Interaction</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total</h5>
                        <h2 class="card-text"><?php echo $stats_total; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Calls</h5>
                        <h2 class="card-text"><?php echo $stats_calls; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Emails</h5>
                        <h2 class="card-text"><?php echo $stats_emails; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Meetings</h5>
                        <h2 class="card-text"><?php echo $stats_meetings; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">All Interactions</h5>
                        <form method="GET" action="" class="d-flex flex-wrap gap-2 align-items-center">
                            <?php if ($search_query): ?>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php endif; ?>
                            <select name="type" class="form-select form-select-sm" style="width: auto;" aria-label="Filter by type" onchange="this.form.submit()">
                                <option value="">All Types</option>
                                <option value="call" <?php echo $filter_type === 'call' ? 'selected' : ''; ?>>Call</option>
                                <option value="email" <?php echo $filter_type === 'email' ? 'selected' : ''; ?>>Email</option>
                                <option value="meeting" <?php echo $filter_type === 'meeting' ? 'selected' : ''; ?>>Meeting</option>
                                <option value="sms" <?php echo $filter_type === 'sms' ? 'selected' : ''; ?>>SMS</option>
                                <option value="note" <?php echo $filter_type === 'note' ? 'selected' : ''; ?>>Note</option>
                            </select>
                            <select name="direction" class="form-select form-select-sm" style="width: auto;" aria-label="Filter by direction" onchange="this.form.submit()">
                                <option value="">All Directions</option>
                                <option value="inbound" <?php echo $filter_direction === 'inbound' ? 'selected' : ''; ?>>Inbound</option>
                                <option value="outbound" <?php echo $filter_direction === 'outbound' ? 'selected' : ''; ?>>Outbound</option>
                            </select>
                            <?php if ($filter_type || $filter_direction || $search_query): ?>
                                <a href="interactions.php" class="btn btn-sm btn-outline-secondary">Clear</a>
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
                        <div class="mb-3">
                            <form method="GET" action="" class="d-flex gap-2">
                                <?php if ($filter_type): ?>
                                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($filter_type, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php endif; ?>
                                <?php if ($filter_direction): ?>
                                    <input type="hidden" name="direction" value="<?php echo htmlspecialchars($filter_direction, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <input type="text"
                                           class="form-control"
                                           name="search"
                                           id="interactionSearch"
                                           placeholder="Search by subject or body..."
                                           value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <button type="submit" class="btn btn-outline-primary">Search</button>
                                <?php if ($search_query): ?>
                                    <a href="interactions.php<?php
                                        $q = [];
                                        if ($filter_type) $q[] = 'type=' . urlencode($filter_type);
                                        if ($filter_direction) $q[] = 'direction=' . urlencode($filter_direction);
                                        echo $q ? '?' . implode('&', $q) : '';
                                    ?>" class="btn btn-outline-secondary">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Direction</th>
                                        <th>Subject</th>
                                        <th>Contact</th>
                                        <th>Company</th>
                                        <th>Deal</th>
                                        <th>Occurred</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($interactions)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">
                                                <?php if ($search_query || $filter_type || $filter_direction): ?>
                                                    No interactions found matching your criteria.
                                                <?php else: ?>
                                                    No interactions found.
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($interactions as $row):
                                            $occurred_date = $row['occurred_at'] ? new DateTime($row['occurred_at']) : null;
                                            $subject_display = $row['subject'] !== null && $row['subject'] !== ''
                                                ? htmlspecialchars(mb_strimwidth($row['subject'], 0, 50, '…'), ENT_QUOTES, 'UTF-8')
                                                : '<span class="text-muted">—</span>';

                                            $type_class = '';
                                            if ($row['type'] === 'call') $type_class = 'bg-info';
                                            elseif ($row['type'] === 'email') $type_class = 'bg-primary';
                                            elseif ($row['type'] === 'meeting') $type_class = 'bg-success';
                                            else $type_class = 'bg-secondary';
                                        ?>
                                            <tr>
                                                <td>
                                                    <span class="badge <?php echo $type_class; ?>"><?php echo ucfirst($row['type']); ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($row['direction']): ?>
                                                        <span class="badge bg-light text-dark"><?php echo ucfirst($row['direction']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $subject_display; ?></td>
                                                <td>
                                                    <a href="contact.php?id=<?php echo htmlspecialchars($row['contact_id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['contact_name'], ENT_QUOTES, 'UTF-8'); ?></a>
                                                </td>
                                                <td>
                                                    <?php if (!empty($row['company_name'])): ?>
                                                        <a href="company.php?id=<?php echo (int) $row['company_id']; ?>"><?php echo htmlspecialchars($row['company_name'], ENT_QUOTES, 'UTF-8'); ?></a>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($row['deal_id'] && $row['deal_name']): ?>
                                                        <a href="deal.php?id=<?php echo (int) $row['deal_id']; ?>"><?php echo htmlspecialchars($row['deal_name'], ENT_QUOTES, 'UTF-8'); ?></a>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($occurred_date): ?>
                                                        <?php echo $occurred_date->format('M d, Y H:i'); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="interaction.php?id=<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
