<?php

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect_to(role_dashboard(current_user_role()));
}

redirect_to('login.php');