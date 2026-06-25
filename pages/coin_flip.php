<?php
if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login');
    exit;
}



$balance = (int) $_SESSION['user_balance'];

$result  = null;
$won     = null;
$message = '';
$error   = '';

// --- Szybkie zakłady (submit przez name="quick_bet") ---
if (isset($_POST['quick_bet'])) {
    $fraction  = (float) $_POST['quick_bet'];
    $_SESSION['bet'] = max(1, (int) floor($_SESSION['user_balance'] * $fraction));
    $_SESSION['choice'] = isset($_POST['choice']) ? $_POST['choice'] : '';
    header('Location: coin_flip');
    exit;
}

// --- Pobierz wartości z GET (po przekierowaniu szybkiego zakładu) ---
$prefill_bet    = isset($_SESSION['bet'])    ? (int)   $_SESSION['bet']    : 50;
$prefill_choice = isset($_SESSION['choice']) ? (string) $_SESSION['choice'] : '';

// --- Reset salda ---
if (isset($_POST['reset'])) {
    $_SESSION['user_balance'] = 1000;
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// --- Główna gra ---
if (isset($_POST['play'])) {
    $bet    = (int)    ($_POST['bet']    ?? 0);
    $choice = (string) ($_POST['choice'] ?? '');

    if (!in_array($choice, ['orzel', 'reszka'], true)) {
        $error = 'Wybierz orła lub reszkę.';
    } elseif ($bet < 1 || $bet > $balance) {
        $error = 'Zakład musi być między 1 a ' . $balance . ' żetonami.';
    } else {
        $result = (rand(0, 1) === 0) ? 'orzel' : 'reszka';
        $won    = ($result === $choice);

        if ($won) {
            $_SESSION['user_balance'] += $bet;
            $message = 'Wygrałeś ' . $bet . ' żetonów!';
        } else {
            $_SESSION['user_balance'] -= $bet;
            $message = 'Przegrałeś ' . $bet . ' żetonów.';
        }

        $balance        = $_SESSION['user_balance'];
        $prefill_bet    = $bet;
        $prefill_choice = $choice;
    }
}

// --- Pomocnicze wartości do szablonu ---
$coin_icon = '🪙';
$coin_label = '';
if ($result === 'orzel')  { $coin_icon = '🦅'; $coin_label = 'Orzeł'; }
if ($result === 'reszka') { $coin_icon = '⚜️';  $coin_label = 'Reszka'; }

$msg_class = '';
if ($won === true)  $msg_class = ' coin-game__message--win';
if ($won === false) $msg_class = ' coin-game__message--lose';
if ($error)        $msg_class = ' coin-game__message--error';

$coin_class = 'coin-game__coin';
if ($result) $coin_class .= ' coin-game__coin--toss';
if ($result) $coin_class .= $won ? ' coin-game__coin--win' : ' coin-game__coin--lose';
?>
<!-- ============================================================
     COIN FLIP GAME — fragment do osadzenia w istniejącym <div>
     Blok <style> przeznaczony do przeniesienia do .css
     ============================================================ -->


<div class="coin-game">

    <p class="coin-game__title">Rzut Monetą</p>
    <p class="coin-game__balance">
        Saldo: <strong><?= (int) $balance ?> żetonów</strong>
    </p>

    <!-- Moneta -->
    <div class="coin-game__coin-wrap">
        <div class="<?= htmlspecialchars($coin_class) ?>">
            <?= $coin_icon ?>
        </div>
    </div>

    <hr class="coin-game__divider">

    <!-- Formularz szybkich zakładów + wyboru + rzutu — jeden POST -->
    <form method="POST" action="">

        <!-- Wybór strony przez radio + label -->
        <span class="coin-game__label">Twój wybór</span>
        <div class="coin-game__choice">
            <input type="radio" name="choice" id="cg_orzel" value="orzel"
                <?= ($prefill_choice === 'orzel') ? 'checked' : '' ?>>
            <label for="cg_orzel">🦅 Orzeł</label>

            <input type="radio" name="choice" id="cg_reszka" value="reszka"
                <?= ($prefill_choice === 'reszka') ? 'checked' : '' ?>>
            <label for="cg_reszka">⚜️ Reszka</label>
        </div>

        <!-- Zakład -->
        <label class="coin-game__label" for="cg_bet">Wysokość zakładu</label>
        <div class="coin-game__bet-wrap">
            <input type="number"
                   class="coin-game__bet-input"
                   name="bet"
                   id="cg_bet"
                   min="1"
                   max="<?= (int) $balance ?>"
                   value="<?= (int) $prefill_bet ?>">
        </div>

        <!-- Szybkie zakłady — każdy to osobny submit z name="quick_bet" -->
        <div class="coin-game__quick">
            <?php
            $fractions = ['10%' => 0.1, '25%' => 0.25, '50%' => 0.5, 'MAX' => 1.0];
            foreach ($fractions as $label => $frac):
            ?>
            <button type="submit" name="quick_bet" value="<?= $frac ?>"
                    class="coin-game__quick-btn">
                <?= $label ?>
            </button>
            <?php endforeach; ?>
        </div>

        <hr class="coin-game__divider">

        <!-- Rzut -->
        <button type="submit" name="play" value="1" class="coin-game__submit">
            Rzuć monetą
        </button>

        <!-- Komunikat — zawsze widoczny -->
        <?php
        if ($error):
            $inline_class = ' coin-game__message--error';
            $inline_text  = $error;
        elseif ($result):
            $inline_class = $msg_class;
            $inline_text  = $coin_label . ' — ' . $message;
        else:
            $inline_class = '';
            $inline_text  = 'Wybierz stronę i postaw zakład.';
        endif;
        ?>
        <div class="coin-game__message<?= $inline_class ?>">
            <?= htmlspecialchars($inline_text) ?>
        </div>

    </form>
    
    <a href="home" class="back-btn">← Wróć do gier</a>
</div>

<script>
// Po załadowaniu i po każdej grze, synchronizuj nagłówek
document.addEventListener('DOMContentLoaded', function() {
    const balance = <?= (int) $balance ?>;
    if (typeof updateHeaderBalance === 'function') {
        updateHeaderBalance(balance);
    }
});

// Po wysłaniu formularza, zaraz potem też
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        setTimeout(() => {
            const balance = <?= (int) $balance ?>;
            if (typeof updateHeaderBalance === 'function') {
                updateHeaderBalance(balance);
            }
        }, 100);
    });
});
</script>