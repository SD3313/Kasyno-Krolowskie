<?php
if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || (string)($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: home');
    exit();
}

$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
if ($user_id <= 0) {
    header('Location: admin');
    exit();
}

if ($user_id === (int)($_SESSION['user_id'] ?? 0)) {
    $_SESSION['admin_error'] = 'Nie możesz usunąć swojego konta administracyjnego.';
    header('Location: admin');
    exit();
}

$sql = "SELECT user_id FROM users WHERE user_id = $user_id LIMIT 1";
$result = mysqli_query($conn, $sql);
if (!$result || mysqli_num_rows($result) !== 1) {
    $_SESSION['admin_error'] = 'Użytkownik o podanym identyfikatorze nie istnieje.';
    header('Location: admin');
    exit();
}

$historyDeleteSql = "DELETE FROM game_history WHERE user_id = $user_id";
if (!mysqli_query($conn, $historyDeleteSql)) {
    $_SESSION['admin_error'] = 'Błąd podczas usuwania powiązanej historii gier: ' . mysqli_error($conn);
    header('Location: admin');
    exit();
}

$deleteSql = "DELETE FROM users WHERE user_id = $user_id";
if (mysqli_query($conn, $deleteSql)) {
    $_SESSION['admin_message'] = 'Użytkownik został usunięty.';
} else {
    $_SESSION['admin_error'] = 'Wystąpił błąd podczas usuwania użytkownika: ' . mysqli_error($conn);
}

header('Location: admin');
exit();
