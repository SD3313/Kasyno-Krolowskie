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
    <style>
        .saldo-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background-color: #213047;
            border-radius: 8px;
            border: 1px solid #2e3f5c;
            color: #c8d8ea;
        }

        .saldo-container h1 {
            color: #3ddc97;
            margin-bottom: 10px;
            text-align: center;
            text-shadow: 0 0 18px rgba(61, 220, 151, .28);
        }

        .saldo-info {
            background-color: #313b72;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 25px;
            text-align: center;
            border: 1px solid #3d5080;
        }

        .saldo-info p {
            font-size: 14px;
            color: #5a7a9a;
            margin: 5px 0;
        }

        .saldo-info .balance {
            font-size: 32px;
            font-weight: bold;
            color: #3ddc97;
            margin: 10px 0;
        }

        .btn-add-saldo {
            width: 100%;
            padding: 12px;
            background-color: #3ddc97;
            border: none;
            border-radius: 6px;
            color: #1a2639;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-bottom: 15px;
        }

        .btn-add-saldo:hover {
            background-color: #2dbc7d;
        }

        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            text-align: center;
            display: none;
        }

        .message.show {
            display: block;
        }

        .message.success {
            background-color: rgba(61, 220, 151, .2);
            border: 1px solid #3ddc97;
            color: #3ddc97;
        }

        .message.error {
            background-color: rgba(224, 92, 92, .2);
            border: 1px solid #e05c5c;
            color: #e05c5c;
        }

        .back-btn {
            display: block;
            text-align: center;
            margin-top: 20px;
            padding: 10px;
            background-color: #2e3f5c;
            color: #3ddc97;
            text-decoration: none;
            border-radius: 6px;
            transition: background-color 0.3s;
        }

        .back-btn:hover {
            background-color: #3d5080;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: #213047;
            padding: 30px;
            border-radius: 8px;
            border: 1px solid #3d5080;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 0 20px rgba(61, 220, 151, .3);
        }

        .modal-content h2 {
            color: #3ddc97;
            margin-bottom: 20px;
            text-align: center;
        }

        .modal-content label {
            display: block;
            color: #c8d8ea;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .modal-content input {
            width: 100%;
            padding: 10px;
            background-color: #313b72;
            border: 1px solid #2e3f5c;
            border-radius: 6px;
            color: #c8d8ea;
            margin-bottom: 20px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .modal-content input:focus {
            outline: none;
            border-color: #3ddc97;
            box-shadow: 0 0 10px rgba(61, 220, 151, .3);
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
        }

        .modal-buttons button {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-confirm {
            background-color: #3ddc97;
            color: #1a2639;
        }

        .btn-confirm:hover {
            background-color: #2dbc7d;
        }

        .btn-cancel {
            background-color: #2e3f5c;
            color: #c8d8ea;
        }

        .btn-cancel:hover {
            background-color: #3d5080;
        }
    </style>
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