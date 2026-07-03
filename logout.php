<?php
// logout.php — Customer Logout (NEW FILE)
session_start();
unset($_SESSION['customer_id'], $_SESSION['customer_name'], $_SESSION['customer_email']);
header("Location: index.php");
exit;
