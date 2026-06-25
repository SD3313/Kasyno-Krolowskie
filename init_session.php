<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_balance'])) {
    $_SESSION['user_balance'] = 1000; 
}
?>