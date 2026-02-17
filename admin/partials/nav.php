<?php
$nav_script = basename($_SERVER['PHP_SELF'] ?? '');
$nav_dashboard = ($nav_script === 'index.php' || $nav_script === '');
$nav_contacts = in_array($nav_script, ['contacts.php', 'contact.php', 'contact-create.php'], true);
$nav_companies = in_array($nav_script, ['companies.php', 'company.php'], true);
$nav_deals = in_array($nav_script, ['deals.php', 'deal.php', 'deal-create.php'], true);
$nav_tasks = in_array($nav_script, ['tasks.php', 'task.php', 'task-create.php'], true);
$nav_interactions = in_array($nav_script, ['interactions.php', 'interaction.php', 'interaction-create.php'], true);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/admin/">CRM Dashboard</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link<?php echo $nav_dashboard ? ' active' : ''; ?>" href="/admin/">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php echo $nav_contacts ? ' active' : ''; ?>" href="contacts.php">Contacts</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php echo $nav_companies ? ' active' : ''; ?>" href="companies.php">Companies</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php echo $nav_deals ? ' active' : ''; ?>" href="deals.php">Deals</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php echo $nav_tasks ? ' active' : ''; ?>" href="tasks.php">Tasks</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php echo $nav_interactions ? ' active' : ''; ?>" href="interactions.php">Interactions</a>
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
