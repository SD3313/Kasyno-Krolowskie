<?php
require_once __DIR__ . '/../init_session.php';

header('Content-Type: application/json');

if (isset($_POST['delta'])) {
    $delta = (int) $_POST['delta'];
    $_SESSION['user_balance'] = max(0, (int) $_SESSION['user_balance'] + $delta);
    echo json_encode(['balance' => (int) $_SESSION['user_balance']]);
} else {
    echo json_encode(['balance' => (int) $_SESSION['user_balance']]);
}
?>