<?php
require_once __DIR__ . '/../../init_session.php';

$current_balance = $_SESSION['user_balance'] ?? 0;
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_balance'])) {
    $amount = (int) ($_POST['amount'] ?? 0);

    if ($amount <= 0) {
        $message = 'Kwota musi być większa niż 0!';
        $message_type = 'error';
    } else {
        $_SESSION['user_balance'] += $amount;
        $current_balance = $_SESSION['user_balance'];
        $message = "Dodano $amount żetonów! Twoje nowe saldo: $current_balance";
        $message_type = 'success';
    }
}

$show_form = isset($_SESSION['show_form']) ? $_SESSION['show_form'] : false;
?>

    <div class="saldo-container">
        <h1>💰 Zarządzanie Saldem</h1>

        <div class="saldo-info">
            <p>Twoje obecne saldo:</p>
            <div class="balance"><?= $current_balance ?> żetonów</div>
        </div>

        <?php if ($message): ?>
            <div class="message show <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($show_form): ?>
            <!-- Formularz dodawania salda -->
            <div class="modal-content">
                <h2>Dodaj Saldo</h2>
                <form method="POST" action="saldo">
                    <label for="amount">Ile żetonów chcesz dodać?</label>
                    <input
                        type="number"
                        id="amount"
                        name="amount"
                        min="1"
                        placeholder="Wpisz kwotę"
                        required
                        autofocus
                    >
                    <div class="modal-buttons">
                        <button type="submit" name="add_balance" value="1" class="btn-confirm">Dodaj</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <?php $_SESSION['show_form'] = true; ?>
            <a href="saldo" class="btn-add-saldo">+ Dodaj Saldo</a>
            
        <?php endif; ?>

        <a href="home" class="back-btn">← Wróć do gier</a>
    </div>
