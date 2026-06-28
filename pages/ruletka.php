<?php
if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login');
    exit;
}

require_once __DIR__ . '/../db_connect.php';

// Saldo bieżące
$balance = (int) $_SESSION['user_balance'];
$balance_before = $balance; // saldo przed zakładem
$user_id = $_SESSION['user_id'] ?? 0;

$result       = null;
$won          = null;
$message      = '';
$error        = '';
$result_num   = null;
$result_color = null;
$payout       = 0;

// --- Szybkie zakłady ---
if (isset($_POST['quick_bet'])) {
    $fraction  = (float) $_POST['quick_bet'];
    $quick_val = max(1, (int) floor($balance * $fraction));
    // Parsuj typ zakładu z radio
    $qt = ''; $qv = '';
    if (isset($_POST['bet_type_val']) && str_contains($_POST['bet_type_val'], '|')) {
        [$qt, $qv] = explode('|', $_POST['bet_type_val'], 2);
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?bet=' . $quick_val
        . ($qt ? '&bet_type=' . urlencode($qt) : '')
        . ($qv ? '&bet_value=' . urlencode($qv) : ''));
    exit;
}

$prefill_bet      = isset($_GET['bet'])       ? (int)    $_GET['bet']       : 50;
$prefill_bet_type = isset($_GET['bet_type'])  ? (string) $_GET['bet_type']  : 'color';
$prefill_bet_val  = isset($_GET['bet_value']) ? (string) $_GET['bet_value'] : 'red';

// Parsuj bet_type_val z radio (format "type|value")
if (isset($_POST['bet_type_val']) && str_contains($_POST['bet_type_val'], '|')) {
    [$_POST['bet_type'], $_POST['bet_value']] = explode('|', $_POST['bet_type_val'], 2);
}
// Dla zakładu na numer: wartość pochodzi z osobnego inputa
if (isset($_POST['bet_type']) && $_POST['bet_type'] === 'number' && isset($_POST['bet_number'])) {
    $_POST['bet_value'] = (string)(int)$_POST['bet_number'];
}

// Zachowaj wybór zakładu z POST
if (isset($_POST['bet_type']))  $prefill_bet_type = (string) $_POST['bet_type'];
if (isset($_POST['bet_value'])) $prefill_bet_val  = (string) $_POST['bet_value'];
if (isset($_POST['bet']))       $prefill_bet      = (int)    $_POST['bet'];

// --- Reset salda ---
if (isset($_POST['reset'])) {
    $_SESSION['user_balance'] = 1000;
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$wheel_order = [0,32,15,19,4,21,2,25,17,34,6,27,13,36,11,30,8,23,10,5,24,16,33,1,20,14,31,9,22,18,29,7,28,12,35,3,26];
$wheel_size  = count($wheel_order);

$roulette_colors = [
    0=>'green',
    1=>'red',2=>'black',3=>'red',4=>'black',5=>'red',6=>'black',7=>'red',8=>'black',9=>'red',
    10=>'black',11=>'black',12=>'red',13=>'black',14=>'red',15=>'black',16=>'red',17=>'black',
    18=>'red',19=>'red',20=>'black',21=>'red',22=>'black',23=>'red',24=>'black',25=>'red',
    26=>'black',27=>'red',28=>'black',29=>'black',30=>'red',31=>'black',32=>'red',33=>'black',
    34=>'red',35=>'black',36=>'red',
];

// --- Główna gra ---
if (isset($_POST['play'])) {
    $bet       = (int)    ($_POST['bet']       ?? 0);
    $bet_type  = (string) ($_POST['bet_type']  ?? '');
    $bet_value = (string) ($_POST['bet_value'] ?? '');

    if (!in_array($bet_type, ['color','parity','half','dozen','number'], true)) {
        $error = 'Wybierz rodzaj zakładu.';
    } elseif ($bet < 1 || $bet > $balance) {
        $error = 'Zakład musi być między 1 a ' . $balance . ' żetonami.';
    } else {
        $result_num   = rand(0, 36);
        $result_color = $roulette_colors[$result_num];
        $is_zero      = ($result_num === 0);

        switch ($bet_type) {
            case 'color':
                if (!in_array($bet_value, ['red','black','green'], true)) { $error='Nieprawidłowy kolor.'; break; }
                if ($bet_value === 'green') {
                    $won    = $is_zero;
                    $payout = $won ? $bet * 17 : 0;
                } else {
                    $won    = (!$is_zero && $result_color === $bet_value);
                    $payout = $won ? $bet : 0;
                }
                break;
            case 'parity':
                if (!in_array($bet_value, ['even','odd'], true)) { $error='Nieprawidłowa parzystość.'; break; }
                $won    = (!$is_zero && (($bet_value==='even') === ($result_num % 2 === 0)));
                $payout = $won ? $bet : 0;
                break;
            case 'half':
                if (!in_array($bet_value, ['low','high'], true)) { $error='Nieprawidłowa połowa.'; break; }
                $won    = (!$is_zero && (($bet_value==='low') ? $result_num<=18 : $result_num>=19));
                $payout = $won ? $bet : 0;
                break;
            case 'dozen':
                $dozens = ['1st'=>[1,12],'2nd'=>[13,24],'3rd'=>[25,36]];
                if (!isset($dozens[$bet_value])) { $error='Nieprawidłowa tuzina.'; break; }
                [$lo,$hi] = $dozens[$bet_value];
                $won    = (!$is_zero && $result_num>=$lo && $result_num<=$hi);
                $payout = $won ? $bet*2 : 0;
                break;
            case 'number':
                $target = (int) $bet_value;
                if ($target<0||$target>36) { $error='Nieprawidłowy numer.'; break; }
                $won    = ($result_num===$target);
                $payout = $won ? $bet*35 : 0;
                break;
        }

        if (!$error) {
            if ($won) {
                $_SESSION['user_balance'] += $payout;
                $message = 'Wygrałeś ' . $payout . ' żetonów!';
                $win = $payout;
            } else {
                $_SESSION['user_balance'] -= $bet;
                $message = 'Przegrałeś ' . $bet . ' żetonów.';
                $win = -$bet;
            }
            $balance          = $_SESSION['user_balance'];
            $prefill_bet      = $bet;
            $prefill_bet_type = $bet_type;
            $prefill_bet_val  = $bet_value;
            $result           = true;

            $sql = "INSERT INTO game_history (user_id, game, bet, win, balance_after) VALUES ('$user_id', 'Ruletka', '$bet', '$win', '$balance')";
            if (!mysqli_query($conn, $sql)) {
                error_log('Błąd zapisu historii Ruletka: ' . mysqli_error($conn));
            }
        }
    }
}

// Budowa taśmy
$REPEATS    = 5;
$SIDE_EXTRA = 8;
$result_wheel_idx = ($result_num !== null) ? (int) array_search($result_num, $wheel_order) : 0;

$tape = [];
for ($i = 0; $i < 14; $i++) {
    $tape[] = $wheel_order[$i % $wheel_size];
}
for ($r = 0; $r < $REPEATS; $r++) {
    foreach ($wheel_order as $n) {
        $tape[] = $n;
    }
}
for ($i = 0; $i <= $result_wheel_idx; $i++) {
    $tape[] = $wheel_order[$i];
}
for ($i = 1; $i <= $SIDE_EXTRA; $i++) {
    $tape[] = $wheel_order[($result_wheel_idx + $i) % $wheel_size];
}
$winner_cell_idx = count($tape) - $SIDE_EXTRA - 1;

// Definicja przycisków zakładów
$bet_buttons = [
    ['type'=>'color',  'val'=>'red',   'cls'=>'rl-bet-btn--red',   'label'=>'🔴 Czerwone',    'payout'=>'1:1'],
    ['type'=>'color',  'val'=>'black', 'cls'=>'rl-bet-btn--black', 'label'=>'⚫ Czarne',       'payout'=>'1:1'],
    ['type'=>'parity', 'val'=>'even',  'cls'=>'rl-bet-btn--even',  'label'=>'↔ Parzyste',     'payout'=>'1:1'],
    ['type'=>'parity', 'val'=>'odd',   'cls'=>'rl-bet-btn--odd',   'label'=>'↕ Nieparzyste',  'payout'=>'1:1'],
    ['type'=>'half',   'val'=>'low',   'cls'=>'rl-bet-btn--low',   'label'=>'⬇ 1–18',         'payout'=>'1:1'],
    ['type'=>'half',   'val'=>'high',  'cls'=>'rl-bet-btn--high',  'label'=>'⬆ 19–36',        'payout'=>'1:1'],
    ['type'=>'dozen',  'val'=>'1st',   'cls'=>'rl-bet-btn--doz',   'label'=>'📌 1–12',         'payout'=>'2:1'],
    ['type'=>'dozen',  'val'=>'2nd',   'cls'=>'rl-bet-btn--doz',   'label'=>'📌 13–24',        'payout'=>'2:1'],
    ['type'=>'dozen',  'val'=>'3rd',   'cls'=>'rl-bet-btn--doz',   'label'=>'📌 25–36',        'payout'=>'2:1'],
    ['type'=>'color',  'val'=>'green', 'cls'=>'rl-bet-btn--green', 'label'=>'🟢 Zero (0)',     'payout'=>'17:1'],
];
?>

<div class="coin-game">

    <p class="coin-game__title">Ruletka</p>
    <p class="coin-game__balance">
        Saldo: <strong id="rl-balance-display"><?= (int) $balance_before ?></strong> żetonów
    </p>

    <!-- PASEK RULETKI -->
    <div class="rl-wrap">
        <div class="rl-arrow">▼</div>
        <div class="rl-strip-outer" id="rl-outer">
            <div class="rl-strip" id="rl-strip">
                <?php foreach ($tape as $idx => $n):
                    $col      = $roulette_colors[$n];
                    $isWinner = ($result_num !== null && $idx === $winner_cell_idx);
                ?>
                <div class="rl-cell rl-cell--<?= $col ?>"
                     <?= $isWinner ? 'id="rl-winner"' : '' ?>>
                    <?= $n ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <hr class="coin-game__divider">

    <form method="POST" action="">

        <span class="coin-game__label">Rodzaj zakładu</span>
        <div class="rl-bets">

            <?php foreach ($bet_buttons as $btn):
                $checked = ($prefill_bet_type === $btn['type'] && $prefill_bet_val === $btn['val']);
                $id      = 'rl-' . $btn['type'] . '-' . $btn['val'];
            ?>
            <input type="radio" name="bet_type_val"
                   id="<?= $id ?>"
                   value="<?= $btn['type'] ?>|<?= $btn['val'] ?>"
                   <?= $checked ? 'checked' : '' ?>>
            <label for="<?= $id ?>" class="rl-bet-btn <?= $btn['cls'] ?>">
                <?= $btn['label'] ?><br>
                <small style="font-weight:400;opacity:.7">wypłata <?= $btn['payout'] ?></small>
            </label>
            <?php endforeach; ?>

            <!-- Numer bezpośredni -->
            <?php
            $numChecked = ($prefill_bet_type === 'number');
            $numVal     = $numChecked ? (int) $prefill_bet_val : '';
            ?>
            <input type="radio" name="bet_type_val"
                   id="rl-number"
                   value="number|0"
                   <?= $numChecked ? 'checked' : '' ?>>
            <label for="rl-number" class="rl-bet-btn rl-bet-btn--num">
                <div class="rl-num-row">
                    <span>🎯 Numer (0–36):</span>
                    <input type="number" class="rl-num-input" name="bet_number"
                           min="0" max="36" placeholder="0–36"
                           value="<?= $numVal ?>">
                    <small style="opacity:.65;font-weight:400;">wypłata 35:1</small>
                </div>
            </label>

        </div>

        <hr class="coin-game__divider">

        <label class="coin-game__label" for="rl-bet-input">Wysokość zakładu</label>
        <div class="coin-game__bet-wrap">
            <input type="number" class="coin-game__bet-input"
                   name="bet" id="rl-bet-input"
                   min="1" max="<?= (int)$balance ?>"
                   value="<?= (int)$prefill_bet ?>">
        </div>

        <div class="coin-game__quick">
            <?php foreach (['10%'=>0.1,'25%'=>0.25,'50%'=>0.5,'MAX'=>1.0] as $lbl=>$frac): ?>
            <button type="submit" name="quick_bet" value="<?= $frac ?>"
                    class="coin-game__quick-btn"><?= $lbl ?></button>
            <?php endforeach; ?>
        </div>

        <hr class="coin-game__divider">

        <button type="submit" name="play" value="1" class="coin-game__submit">
            🎡 Zakręć kołem
        </button>

        <?php
        if ($error) {
            $cls  = ' coin-game__message--error';
            $text = $error;
        } elseif ($result !== null) {
            $ico  = $roulette_colors[$result_num] === 'green' ? '🟢'
                  : ($roulette_colors[$result_num] === 'red'  ? '🔴' : '⚫');
            $cls  = $won ? ' coin-game__message--win' : ' coin-game__message--lose';
            $text = $ico . ' Wylosowano: ' . $result_num . ' — ' . $message;
        } else {
            $cls  = '';
            $text = 'Wybierz zakład i zakręć kołem.';
        }
        ?>
        <div class="coin-game__message<?= $cls ?>">
            <?= htmlspecialchars($text) ?>
        </div>

    </form>

    <a href="home" class="back-btn">← Wróć do gier</a>
</div>

<script>
(function () {
    const strip       = document.getElementById('rl-strip');
    const outer       = document.getElementById('rl-outer');
    const messageEl   = document.querySelector('.coin-game__message');
    const balanceEl   = document.getElementById('rl-balance-display');
    const PLAYED      = <?= $result !== null ? 'true' : 'false' ?>;
    const WIN_IDX     = <?= (int) $winner_cell_idx ?>;
    const NEW_BALANCE = <?= (int) $balance ?>;

    if (!strip || !outer) return;

    const cellElem  = strip.querySelector('.rl-cell');
    const CELL_W    = cellElem ? Math.round(cellElem.getBoundingClientRect().width) : 90;
    const centerOff = Math.round((outer.offsetWidth - CELL_W) / 2);

    // Ukryj komunikat i zablokuj formularz podczas animacji
    if (PLAYED && messageEl) {
        messageEl.style.opacity    = '0';
        messageEl.style.visibility = 'hidden';
    }

    if (!PLAYED) {
        strip.style.transition = 'none';
        strip.style.transform  = `translateX(${-(7 * CELL_W) + centerOff}px)`;
        return;
    }

    // Start: komórka 0 na środku
    strip.style.transition = 'none';
    strip.style.transform  = `translateX(${centerOff}px)`;

    void strip.offsetWidth; // reflow

    const ANIM_MS = 6000;

    strip.style.transition = `transform ${ANIM_MS}ms cubic-bezier(0.05, 0.0, 0.15, 1.0)`;
    strip.style.transform  = `translateX(${-(WIN_IDX * CELL_W) + centerOff}px)`;

    setTimeout(function () {
        // Podświetl wynik
        const winner = document.getElementById('rl-winner');
        if (winner) winner.classList.add('rl-cell--winner');

        // Zaktualizuj saldo w DOM (PHP już wyliczył nową wartość)
        if (balanceEl) balanceEl.textContent = NEW_BALANCE;
        if (typeof updateHeaderBalance === 'function') updateHeaderBalance(NEW_BALANCE);

        // Pokaż komunikat
        if (messageEl) {
            messageEl.style.opacity    = '';
            messageEl.style.visibility = '';
        }
    }, ANIM_MS);
})();
</script>