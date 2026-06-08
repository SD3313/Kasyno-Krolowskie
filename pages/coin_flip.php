<?php
require_once __DIR__ . '/../init_session.php';
$balance = (int) $_SESSION['user_balance'];


$result  = null;
$won     = null;
$message = '';
$error   = '';

// --- Szybkie zakłady (submit przez name="quick_bet") ---
if (isset($_POST['quick_bet'])) {
    $fraction  = (float) $_POST['quick_bet'];
    $quick_val = max(1, (int) floor($balance * $fraction));
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?bet=' . $quick_val
        . (isset($_POST['choice']) ? '&choice=' . urlencode($_POST['choice']) : ''));
    exit;
}

// --- Pobierz wartości z GET (po przekierowaniu szybkiego zakładu) ---
$prefill_bet    = isset($_GET['bet'])    ? (int)   $_GET['bet']    : 50;
$prefill_choice = isset($_GET['choice']) ? (string) $_GET['choice'] : '';

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

<style>
/* ── Import czcionek ── */
@import url('https://fonts.googleapis.com/css2?family=DM+Mono:ital,wght@0,400;0,500;1,400&family=Cormorant+Garamond:wght@600;700&display=swap');

/* ── Zmienne ─────────────────────────────────────────────── */
.coin-game {
    --cg-bg:          #1a2639;   /* tło kontenera              */
    --cg-surface:     #213047;   /* tło pól / przycisków       */
    --cg-element:     #313b72;   /* kolor elementów akcentowych */
    --cg-border:      #2e3f5c;   /* obramowania                */
    --cg-border-hi:   #3d5080;   /* jaśniejsze obramowanie     */
    --cg-green:       #3ddc97;   /* podświetlenia zielone       */
    --cg-green-dim:   rgba(61,220,151,.12);
    --cg-green-glow:  rgba(61,220,151,.28);
    --cg-text:        #c8d8ea;   /* tekst główny               */
    --cg-muted:       #5a7a9a;   /* tekst drugorzędny          */
    --cg-win:         #3ddc97;   /* wygrana = zielony          */
    --cg-lose:        #e05c5c;   /* przegrana                  */
    --cg-error:       #e8964d;   /* błąd                       */
    --cg-gold-hi:     #f0cc6a;
    --cg-gold-lo:     #8a6a1a;
    --cg-r:           5px;
    --cg-body:        'DM Mono', monospace;
    --cg-head:        'Cormorant Garamond', serif;
}

.coin-game {
    font-family:   var(--cg-body);
    background:    var(--cg-bg);
    color:         var(--cg-text);
    max-width:     400px;
    margin:        0 auto;
    padding:       2.25rem 1.75rem 1.75rem;
    border:        1px solid var(--cg-border);
    border-radius: var(--cg-r);
    box-sizing:    border-box;
}

.coin-game__title {
    font-family:    var(--cg-head);
    font-size:      1.75rem;
    font-weight:    700;
    color:          var(--cg-green);
    margin:         0 0 0.2rem;
    letter-spacing: 0.01em;
    line-height:    1;
    text-shadow:    0 0 18px var(--cg-green-glow);
}

.coin-game__balance {
    font-size:      0.68rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color:          var(--cg-muted);
    margin:         0 0 1.75rem;
}

.coin-game__balance strong {
    color:       var(--cg-text);
    font-weight: 500;
}

/* ── Moneta ── */
.coin-game__coin-wrap {
    display:         flex;
    justify-content: center;
    margin-bottom:   1.75rem;
    perspective:     400px;
}

.coin-game__coin {
    width:           88px;
    height:          88px;
    border-radius:   50%;
    background:      radial-gradient(circle at 38% 32%, var(--cg-gold-hi), var(--cg-gold-lo));
    border:          3px solid var(--cg-element);
    display:         flex;
    align-items:     center;
    justify-content: center;
    font-size:       2.2rem;
    box-shadow:      0 0 24px rgba(49,59,114,.5),
                     inset 0 2px 5px rgba(255,255,255,.1);
}

.coin-game__coin--win {
    border-color: var(--cg-green);
    box-shadow:   0 0 40px var(--cg-green-glow),
                  inset 0 2px 5px rgba(255,255,255,.1);
}

.coin-game__coin--lose {
    border-color: var(--cg-lose);
    box-shadow:   0 0 36px rgba(224,92,92,.25),
                  inset 0 2px 5px rgba(255,255,255,.1);
}

/* ── Animacja rzutu ── */
@keyframes cg-toss {
    0%   { transform: translateY(0)     rotateX(0deg)    scale(1);    }
    55%  { transform: translateY(-52px) rotateX(900deg)  scale(1.18); }
    100% { transform: translateY(0)     rotateX(1440deg) scale(1);    }
}

.coin-game__coin--toss {
    animation: cg-toss .85s cubic-bezier(.22,.68,0,1.2) both;
}

/* ── Divider ── */
.coin-game__divider {
    border:     none;
    border-top: 1px solid var(--cg-border);
    margin:     1.4rem 0;
}

/* ── Etykieta pola ── */
.coin-game__label {
    display:        block;
    font-size:      0.62rem;
    letter-spacing: 0.13em;
    text-transform: uppercase;
    color:          var(--cg-muted);
    margin-bottom:  0.55rem;
}

/* ── Radio — wybór strony ── */
.coin-game__choice {
    display:       flex;
    gap:           0.5rem;
    margin-bottom: 1.4rem;
}

/* Ukryj natywny radio */
.coin-game__choice input[type="radio"] {
    position: absolute;
    opacity:  0;
    width:    0;
    height:   0;
}

.coin-game__choice label {
    flex:           1;
    display:        block;
    padding:        0.65rem 0;
    text-align:     center;
    font-size:      0.78rem;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    background:     var(--cg-surface);
    border:         1px solid var(--cg-border);
    border-radius:  var(--cg-r);
    color:          var(--cg-muted);
    cursor:         pointer;
    transition:     border-color .15s, color .15s, background .15s, box-shadow .15s;
    user-select:    none;
}

.coin-game__choice label:hover {
    border-color: var(--cg-element);
    color:        var(--cg-text);
    background:   rgba(49,59,114,.35);
}

/* Zaznaczony radio = aktywna etykieta */
.coin-game__choice input[type="radio"]:checked + label {
    border-color: var(--cg-green);
    color:        var(--cg-green);
    background:   var(--cg-green-dim);
    box-shadow:   0 0 10px var(--cg-green-glow);
}

/* ── Pole zakładu ── */
.coin-game__bet-wrap {
    position:      relative;
    margin-bottom: 0.5rem;
}

.coin-game__bet-wrap::after {
    content:    'żetonów';
    position:   absolute;
    right:      0.75rem;
    top:        50%;
    transform:  translateY(-50%);
    font-size:  0.6rem;
    letter-spacing: 0.08em;
    color:      var(--cg-muted);
    pointer-events: none;
}

.coin-game__bet-input {
    width:           100%;
    background:      var(--cg-surface);
    border:          1px solid var(--cg-border);
    border-radius:   var(--cg-r);
    color:           var(--cg-text);
    font-family:     var(--cg-body);
    font-size:       0.92rem;
    padding:         0.65rem 5.5rem 0.65rem 0.75rem;
    outline:         none;
    box-sizing:      border-box;
    transition:      border-color .15s, box-shadow .15s;
    -moz-appearance: textfield;
}

.coin-game__bet-input::-webkit-inner-spin-button,
.coin-game__bet-input::-webkit-outer-spin-button { -webkit-appearance: none; }

.coin-game__bet-input:focus {
    border-color: var(--cg-green);
    box-shadow:   0 0 0 2px var(--cg-green-dim);
}

/* ── Szybkie zakłady ── */
.coin-game__quick {
    display:       flex;
    gap:           0.4rem;
    margin-bottom: 1.4rem;
}

.coin-game__quick-btn {
    flex:           1;
    background:     var(--cg-surface);
    border:         1px solid var(--cg-border);
    border-radius:  var(--cg-r);
    color:          var(--cg-muted);
    font-family:    var(--cg-body);
    font-size:      0.62rem;
    letter-spacing: 0.06em;
    padding:        0.4rem 0;
    cursor:         pointer;
    transition:     border-color .15s, color .15s, background .15s, box-shadow .15s;
}

.coin-game__quick-btn:hover {
    border-color: var(--cg-green);
    color:        var(--cg-green);
    background:   var(--cg-green-dim);
    box-shadow:   0 0 8px var(--cg-green-glow);
}

/* ── Główny przycisk ── */
.coin-game__submit {
    width:          100%;
    padding:        0.82rem;
    background:     var(--cg-element);
    border:         1px solid var(--cg-element);
    border-radius:  var(--cg-r);
    color:          var(--cg-text);
    font-family:    var(--cg-body);
    font-size:      0.75rem;
    font-weight:    500;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    cursor:         pointer;
    margin-bottom:  1.4rem;
    transition:     background .15s, border-color .15s, box-shadow .15s;
}

.coin-game__submit:hover {
    background:   #3a4588;
    border-color: var(--cg-green);
    box-shadow:   0 0 14px var(--cg-green-glow);
}

/* ── Komunikat wynik / błąd — zawsze widoczny ── */
.coin-game__message {
    min-height:     2.2rem;
    font-size:      0.76rem;
    text-align:     center;
    letter-spacing: 0.05em;
    color:          var(--cg-muted);
    padding:        0.5rem 0.75rem;
    background:     var(--cg-surface);
    border:         1px solid var(--cg-border);
    border-radius:  var(--cg-r);
    display:        flex;
    align-items:    center;
    justify-content: center;
}

.coin-game__message--win {
    color:        var(--cg-win);
    border-color: var(--cg-green);
    background:   var(--cg-green-dim);
    box-shadow:   0 0 10px var(--cg-green-glow);
}

.coin-game__message--lose {
    color:        var(--cg-lose);
    border-color: var(--cg-lose);
    background:   rgba(224,92,92,.08);
}

.coin-game__message--error {
    color:        var(--cg-error);
    border-color: var(--cg-error);
    background:   rgba(232,150,77,.08);
}

/* ── Reset ── */
.coin-game__reset {
    display:        block;
    width:          100%;
    margin-top:     0.9rem;
    padding:        0.48rem;
    background:     transparent;
    border:         1px solid var(--cg-border);
    border-radius:  var(--cg-r);
    color:          var(--cg-muted);
    font-family:    var(--cg-body);
    font-size:      0.6rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    cursor:         pointer;
    transition:     color .15s, border-color .15s;
}

.coin-game__reset:hover {
    color:        var(--cg-text);
    border-color: var(--cg-border-hi);
}
</style>

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

    <!-- Reset salda -->
    <form method="POST" action="">
        <button type="submit" name="reset" value="1" class="coin-game__reset">
            ↺ Reset salda (1000 żetonów)
        </button>
    </form>

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