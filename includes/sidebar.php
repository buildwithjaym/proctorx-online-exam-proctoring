<?php

$role = current_user_role();

?>
<aside class="dashboard-sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark small">PX</div>
        <div>
            <h2>ProctorX</h2>
            <span><?php echo e(ucfirst($role)); ?> Panel</span>
        </div>
    </div>

    <nav>
        <?php if ($role === 'teacher'): ?>
            <a class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>" href="<?php echo e(app_url('teacher/dashboard.php')); ?>">Dashboard</a>
            <a class="<?php echo $activePage === 'students' ? 'active' : ''; ?>" href="<?php echo e(app_url('teacher/students.php')); ?>">Students</a>
            <a class="<?php echo $activePage === 'proctors' ? 'active' : ''; ?>" href="<?php echo e(app_url('teacher/proctors.php')); ?>">Proctors</a>
            <a class="<?php echo $activePage === 'classes' ? 'active' : ''; ?>" href="<?php echo e(app_url('teacher/classes.php')); ?>">Classes</a>
            <a class="<?php echo $activePage === 'exams' ? 'active' : ''; ?>" href="<?php echo e(app_url('teacher/exams.php')); ?>">Exams</a>
            <a class="<?php echo $activePage === 'results' ? 'active' : ''; ?>" href="<?php echo e(app_url('teacher/results.php')); ?>">Results</a>
        <?php endif; ?>

        <?php if ($role === 'student'): ?>
            <a class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>" href="<?php echo e(app_url('student/dashboard.php')); ?>">Dashboard</a>
            <a class="<?php echo $activePage === 'exams' ? 'active' : ''; ?>" href="<?php echo e(app_url('student/exams.php')); ?>">Assigned Exams</a>
            <a class="<?php echo $activePage === 'results' ? 'active' : ''; ?>" href="<?php echo e(app_url('student/result.php')); ?>">Results</a>
        <?php endif; ?>

        <?php if ($role === 'proctor'): ?>
            <a class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>" href="<?php echo e(app_url('proctor/dashboard.php')); ?>">Dashboard</a>
            <a class="<?php echo $activePage === 'assigned_exams' ? 'active' : ''; ?>" href="<?php echo e(app_url('proctor/assigned_exams.php')); ?>">Assigned Exams</a>
            <a class="<?php echo $activePage === 'monitoring' ? 'active' : ''; ?>" href="<?php echo e(app_url('proctor/monitor_exam.php')); ?>">Monitoring</a>
        <?php endif; ?>
    </nav>
</aside>