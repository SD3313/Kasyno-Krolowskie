<?php
// Startuj sesję tylko jeśli jeszcze nie jest aktywna
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inicjalizuj saldo w sesji (raz na początek)
if (!isset($_SESSION['user_balance'])) {
    $_SESSION['user_balance'] = 1000; // Domyślne saldo
}
?>