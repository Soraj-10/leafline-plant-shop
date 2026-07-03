<?php
// admin_logout.php — Admin Logout (NEW FILE)
session_start();
unset($_SESSION['admin_verified'], $_SESSION['admin_id'], $_SESSION['admin_name']);
header("Location: admin_login.php");
exit;
