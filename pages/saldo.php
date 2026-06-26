<?php
if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login');
    exit;
}


$current_balance = $_SESSION['user_balance'] ?? 0;
$message = '';
$message_type = '';


$user     = $_SESSION['user']         ?? 'Gracz';
$role     = $_SESSION['role']         ?? 'gracz';
$user_id  = $_SESSION['user_id']      ?? 0;
$balance  = $_SESSION['user_balance'] ?? 0;
$email    = $_SESSION['email']        ?? '';
$pic      = $_SESSION['profile_pic']  ?? '';

$initials = '';
$parts = explode(' ', $user);
foreach ($parts as $p) $initials .= strtoupper(mb_substr($p, 0, 1));
$initials = mb_substr($initials, 0, 2);



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
        $_SESSION['show_form'] = false; 
        header('Location: saldo');
        exit;
    }
}

$show_form = isset($_SESSION['show_form']) ? $_SESSION['show_form'] : false;
?>
<div class="profile-container">
<div class="sidebar">
    <div class="sidebar-logo">
        <span>🎲 Spróbuj szczęścia</span>
        <small>Panel gracza</small>
    </div>

    <div class="sidebar-avatar">
        <div class="avatar-circle">
            <?php if ($pic): ?>
                <img src="<?= htmlspecialchars($pic) ?>" alt="avatar">
            <?php else: ?>
                <?= htmlspecialchars($initials) ?>
            <?php endif; ?>
        </div>
        <div class="sidebar-name"><?= htmlspecialchars($user) ?></div>
        <div class="sidebar-role"><?= htmlspecialchars($role) ?></div>
    </div>

    <nav class="sidebar-nav">
        <a href="profil"   class="nav-item ">
            <span class="icon">👤</span> Profil
        </a>
        <a href="saldo"    class="nav-item active">
            <span class="icon">💰</span> Saldo
        </a>
        <a href="znajomi" class="nav-item ">
            <span class="icon">🤝</span> Znajomi
        </a>
        <a href="historia" class="nav-item ">
            <span class="icon">📋</span> Historia
        </a>
    </nav>

    <div class="sidebar-balance">
        <small>Twoje saldo</small>
        <strong><?= number_format($balance, 0, ',', ' ') ?> żetonów</strong>
    </div>

    <a href="index" class="sidebar-back">← Wróć do gier</a>
</div>
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
        </div>