<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Dashboard | ProctorX</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>

<div class="dashboard-shell">
    <aside class="dashboard-sidebar">
        <div class="sidebar-brand">
            <div class="brand-mark small">PX</div>
            <div>
                <h2>ProctorX</h2>
                <span>Teacher Panel</span>
            </div>
        </div>

        <nav>
            <a class="active" href="dashboard.php">Dashboard</a>
            <a href="#">Students</a>
            <a href="#">Proctors</a>
            <a href="#">Classes</a>
            <a href="#">Exams</a>
            <a href="#">Results</a>
        </nav>
    </aside>

    <main class="dashboard-main">
        <header class="dashboard-topbar">
            <div>
                <span>Teacher Dashboard</span>
                <h1>Welcome, <?php echo e(current_user_name()); ?></h1>
            </div>

            <a class="logout-link" href="../logout.php">Logout</a>
        </header>

        <section class="dashboard-grid">
            <div class="dashboard-card">
                <span>Total Exams</span>
                <h3>0</h3>
            </div>

            <div class="dashboard-card">
                <span>Students</span>
                <h3>0</h3>
            </div>

            <div class="dashboard-card">
                <span>Proctors</span>
                <h3>0</h3>
            </div>

            <div class="dashboard-card warning">
                <span>Flagged Attempts</span>
                <h3>0</h3>
            </div>
        </section>
    </main>
</div>

</body>
</html>