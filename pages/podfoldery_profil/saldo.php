<?php
require_once __DIR__ . '/../../init_session.php';

$current_balance = $_SESSION['user_balance'] ?? 0;
$message = '';
$message_type = '';

// Obsługa dodawania salda
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_balance'])) {
    $amount = (int) $_POST['amount'] ?? 0;
    
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
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zarządzanie Saldem</title>
    <link rel="stylesheet" href="../../style.css">
</head>
<body style="background-color: #1a2639;">
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

        <button class="btn-add-saldo" onclick="openAddBalanceModal()">+ Dodaj Saldo</button>

        <a href="home" class="back-btn">← Wróć do gier</a>
    </div>

    <!-- Modal do dodawania salda -->
    <div id="addBalanceModal" class="modal">
        <div class="modal-content">
            <h2>Dodaj Saldo</h2>
            <form method="POST" action="">
                <label for="amount">Ile żetonów chcesz dodać?</label>
                <input 
                    type="number" 
                    id="amount" 
                    name="amount" 
                    min="1" 
                    max="999999" 
                    placeholder="Wpisz kwotę"
                    required
                    autofocus
                >
                <div class="modal-buttons">
                    <button type="submit" name="add_balance" value="1" class="btn-confirm">Dodaj</button>
                    <button type="button" class="btn-cancel" onclick="closeAddBalanceModal()">Anuluj</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddBalanceModal() {
            document.getElementById('addBalanceModal').classList.add('show');
            document.getElementById('amount').focus();
        }

        function closeAddBalanceModal() {
            document.getElementById('addBalanceModal').classList.remove('show');
        }

        // Zamknij modal po kliknięciu poza nim
        document.getElementById('addBalanceModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddBalanceModal();
            }
        });

        // Pozwól zamknąć modal klawiszem ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddBalanceModal();
            }
        });
    </script>
</body>
</html>