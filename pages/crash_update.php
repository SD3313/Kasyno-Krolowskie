<?php
session_start();

require_once __DIR__ . '/../db_connect.php';
header('Content-Type: application/json');


if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_balance'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Brak autoryzacji lub sesja wygasła.']);
    exit;
}

if (isset($_POST['delta']) && isset($_POST['mult']) && isset($_POST['won']) && isset($_POST['bet'])) {
    
    $delta = (int) $_POST['delta'];
    $mult = (float) $_POST['mult'];
    $won = (int) $_POST['won'] === 1;
    $bet = (int) $_POST['bet'];
    $user_id = $_SESSION['user_id'] ?? 0;

    $_SESSION['user_balance'] = max(0, (int) $_SESSION['user_balance'] + $delta);

    if (!isset($_SESSION['crash_history'])) {
        $_SESSION['crash_history'] = [];
    }

    $newGame = [
        'mult' => $mult,
        'won' => $won
    ];

    array_unshift($_SESSION['crash_history'], $newGame);

    if (count($_SESSION['crash_history']) > 10) {
        array_pop($_SESSION['crash_history']);
    }

    $win = $won ? $delta : -$bet;
    $balanceAfter = $_SESSION['user_balance'];
    $sql = "INSERT INTO game_history (user_id, game, bet, win, balance_after) VALUES ('$user_id', 'Crash', '$bet', '$win', '$balanceAfter')";
    $result = mysqli_query($conn, $sql);
    if (! $result) {
        $errorMessage = mysqli_error($conn);
        error_log('Błąd zapisu historii Crash: ' . $errorMessage);
        echo json_encode(['error' => 'Nie udało się zapisać wyniku do bazy.', 'db_error' => $errorMessage]);
        exit;
    }

    echo json_encode(['balance' => $balanceAfter]);

} else {
    echo json_encode(['balance' => $_SESSION['user_balance']]);
}
?>