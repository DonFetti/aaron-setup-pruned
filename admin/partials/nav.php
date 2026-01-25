<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">CRM Dashboard</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="/admin/">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contacts.php">Contacts</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Deals</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tasks.php?status=&priority=">Tasks</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Interactions</a>
                    </li>
                    <?php if (isset($logged_in_first_name) && $logged_in_first_name): ?>
                    <li class="nav-item">
                        <span class="nav-link text-light">
                            <?php echo htmlspecialchars($logged_in_first_name, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>