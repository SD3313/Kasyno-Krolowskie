<?php
require_once __DIR__ . '/../init_session.php';
$balance = (int) $_SESSION['user_balance'];

$error   = '';
$message = '';

// ── Reset salda ──────────────────────────────────────────────────
if (isset($_POST['reset'])) {
    $_SESSION['user_balance'] = 1000;
    unset($_SESSION['bs_game']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ── Szybkie zakłady ──────────────────────────────────────────────
if (isset($_POST['quick_bet'])) {
    $fraction  = (float) $_POST['quick_bet'];
    $quick_val = max(1, (int) floor($balance * $fraction));
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?bet=' . $quick_val);
    exit;
}

// ── Pomocnicze: wczytaj stan gry z sesji ─────────────────────────
$game = isset($_SESSION['bs_game']) ? $_SESSION['bs_game'] : null;

// ── Akcja: NOWA GRA ──────────────────────────────────────────────
if (isset($_POST['new_game'])) {
    $size  = max(3, min(8, (int) ($_POST['grid_size'] ?? 5)));
    $cells = $size * $size;
    $bombs = max(1, min($cells - 1, (int) ($_POST['bombs'] ?? 3)));
    $bet   = (int) ($_POST['bet'] ?? 0);

    if ($bet < 1 || $bet > $balance) {
        $error = 'Zakład musi być między 1 a ' . $balance . ' żetonami.';
    } else {
        // Rozłóż bomby losowo
        $positions = range(0, $cells - 1);
        shuffle($positions);
        $bomb_positions = array_slice($positions, 0, $bombs);

        $game = [
            'size'       => $size,
            'bombs'      => $bombs,
            'bomb_pos'   => $bomb_positions,
            'revealed'   => [],      // odkryte bezpieczne pola
            'status'     => 'active',// active | won | lost
            'bet'        => $bet,
            'multiplier' => 1.0,
            'winnings'   => 0,
        ];

        $_SESSION['bs_game']     = $game;
        $_SESSION['user_balance'] -= $bet;
        $balance = $_SESSION['user_balance'];
    }
}

// ── Akcja: ODKRYJ POLE ───────────────────────────────────────────
if (isset($_POST['reveal']) && $game && $game['status'] === 'active') {
    $cell = (int) $_POST['reveal'];
    $size  = $game['size'];
    $cells = $size * $size;

    if ($cell >= 0 && $cell < $cells && !in_array($cell, $game['revealed']) && !in_array($cell, $game['bomb_pos'])) {

        $game['revealed'][] = $cell;
        $safe_total = $cells - $game['bombs'];
        $revealed_count = count($game['revealed']);

        // Oblicz nowy mnożnik (wzrasta po każdym bezpiecznym polu)
        // Wzór: iloczyn (pozostałe_bezpieczne / pozostałe_pola) dla każdego kroku
        // Uproszczone: mult = (safe_total / (cells - bombs)) * (safe_total - 1) / (cells - bombs - 1) * ...
        // Używamy sprawdzonego wzoru kasyna: mult = (cells / safe_total)^revealed * 0.97 (house edge 3%)
        $mult = 1.0;
        $remaining_cells = $cells;
        $remaining_safe  = $safe_total;
        for ($i = 0; $i < $revealed_count; $i++) {
            $mult *= $remaining_cells / $remaining_safe;
            $remaining_cells--;
            $remaining_safe--;
        }
        $mult = round($mult * 0.97, 3); // house edge 3%
        $game['multiplier'] = $mult;
        $game['winnings']   = (int) floor($game['bet'] * $mult);

        // Czy odkryto wszystkie bezpieczne pola?
        if ($revealed_count >= $safe_total) {
            $game['status'] = 'won';
            $_SESSION['user_balance'] += $game['winnings'];
            $balance = $_SESSION['user_balance'];
            $message = 'Brawo! Odkryłeś wszystkie bezpieczne pola i wygrałeś ' . $game['winnings'] . ' żetonów!';
        }

        $_SESSION['bs_game'] = $game;

    } elseif (in_array($cell, $game['bomb_pos'])) {
        // BOMBA!
        $game['status']  = 'lost';
        $game['revealed'][] = $cell; // pokaż trafioną bombę
        $_SESSION['bs_game'] = $game;
        $message = 'Boom! Trafiłeś na bombę i straciłeś ' . $game['bet'] . ' żetonów.';
    }
}

// ── Akcja: WYPŁAĆ ────────────────────────────────────────────────
if (isset($_POST['cashout']) && $game && $game['status'] === 'active' && count($game['revealed']) > 0) {
    $game['status'] = 'won';
    $_SESSION['user_balance'] += $game['winnings'];
    $balance = $_SESSION['user_balance'];
    $_SESSION['bs_game'] = $game;
    $message = 'Wypłaciłeś ' . $game['winnings'] . ' żetonów! (×' . number_format($game['multiplier'], 2) . ')';
}

// ── Reload po akcji POST ─────────────────────────────────────────
// (Nie robimy PRG tutaj, bo musimy przekazać $message do widoku)
// Odświeżamy zmienne
$game    = isset($_SESSION['bs_game']) ? $_SESSION['bs_game'] : null;
$balance = (int) $_SESSION['user_balance'];

// ── Prefille formularza ──────────────────────────────────────────
$prefill_bet  = isset($_GET['bet'])      ? (int) $_GET['bet']      : 50;
$prefill_size = $game                   ? (int) $game['size']     : 5;
$prefill_bombs= $game                   ? (int) $game['bombs']    : 3;

// ── Oblicz wyświetlany mnożnik startowy (jeszcze przed grą) ──────
function bs_calc_mult(int $cells, int $bombs, int $revealed): float {
    $safe = $cells - $bombs;
    if ($safe <= 0) return 1.0;
    $mult = 1.0;
    $rc = $cells;
    $rs = $safe;
    for ($i = 0; $i < $revealed; $i++) {
        if ($rs <= 0) break;
        $mult *= $rc / $rs;
        $rc--;
        $rs--;
    }
    return round($mult * 0.97, 3);
}

// ── Status komunikatu ────────────────────────────────────────────
$msg_class = '';
if ($game && $game['status'] === 'won')  $msg_class = ' bs__message--win';
if ($game && $game['status'] === 'lost') $msg_class = ' bs__message--lose';
if ($error)                              $msg_class = ' bs__message--error';

if ($error) {
    $inline_text  = $error;
    $inline_class = ' bs__message--error';
} elseif ($message) {
    $inline_text  = $message;
    $inline_class = $msg_class;
} elseif ($game && $game['status'] === 'active' && count($game['revealed']) === 0) {
    $inline_text  = 'Odkryj pierwsze pole, żeby zacząć.';
    $inline_class = '';
} elseif ($game && $game['status'] === 'active') {
    $revealed_count = count($game['revealed']);
    $inline_text  = 'Odkryto ' . $revealed_count . ' ' . ($revealed_count === 1 ? 'pole' : 'pola/pól') . '. Kontynuuj lub wypłać.';
    $inline_class = '';
} elseif (!$game) {
    $inline_text  = 'Skonfiguruj planszę i postaw zakład.';
    $inline_class = '';
} else {
    $inline_text  = $message ?: '';
    $inline_class = $msg_class;
}
?>
<!-- ============================================================
     BOMB SWEEPER — fragment do osadzenia w istniejącym <div>
     Blok <style> przeznaczony do przeniesienia do .css
     ============================================================ -->

<style>
/* ── Import czcionek ── */
@import url('https://fonts.googleapis.com/css2?family=DM+Mono:ital,wght@0,400;0,500;1,400&family=Cormorant+Garamond:wght@600;700&display=swap');

/* ── Zmienne ─────────────────────────────────────────────── */
.bs {
    --bs-bg:          #1a2639;
    --bs-surface:     #213047;
    --bs-element:     #313b72;
    --bs-border:      #2e3f5c;
    --bs-border-hi:   #3d5080;
    --bs-green:       #3ddc97;
    --bs-green-dim:   rgba(61,220,151,.12);
    --bs-green-glow:  rgba(61,220,151,.28);
    --bs-text:        #c8d8ea;
    --bs-muted:       #5a7a9a;
    --bs-win:         #3ddc97;
    --bs-lose:        #e05c5c;
    --bs-error:       #e8964d;
    --bs-bomb:        #e05c5c;
    --bs-safe:        #3ddc97;
    --bs-cell:        #243452;
    --bs-cell-hover:  #2e4168;
    --bs-r:           5px;
    --bs-body:        'DM Mono', monospace;
    --bs-head:        'Cormorant Garamond', serif;
}

.bs {
    font-family:   var(--bs-body);
    background:    var(--bs-bg);
    color:         var(--bs-text);
    max-width:     460px;
    margin:        0 auto;
    padding:       2.25rem 1.75rem 1.75rem;
    border:        1px solid var(--bs-border);
    border-radius: var(--bs-r);
    box-sizing:    border-box;
}

.bs__title {
    font-family:    var(--bs-head);
    font-size:      1.75rem;
    font-weight:    700;
    color:          var(--bs-green);
    margin:         0 0 0.2rem;
    letter-spacing: 0.01em;
    line-height:    1;
    text-shadow:    0 0 18px var(--bs-green-glow);
}

.bs__balance {
    font-size:      0.68rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color:          var(--bs-muted);
    margin:         0 0 1.75rem;
}

.bs__balance strong {
    color:       var(--bs-text);
    font-weight: 500;
}

/* ── Divider ── */
.bs__divider {
    border:     none;
    border-top: 1px solid var(--bs-border);
    margin:     1.4rem 0;
}

/* ── Etykieta ── */
.bs__label {
    display:        block;
    font-size:      0.62rem;
    letter-spacing: 0.13em;
    text-transform: uppercase;
    color:          var(--bs-muted);
    margin-bottom:  0.55rem;
}

/* ── Statsy (mnożnik / wygrana / bomby) ── */
.bs__stats {
    display:        flex;
    gap:            0.5rem;
    margin-bottom:  1.4rem;
}

.bs__stat {
    flex:           1;
    background:     var(--bs-surface);
    border:         1px solid var(--bs-border);
    border-radius:  var(--bs-r);
    padding:        0.5rem 0.65rem;
    text-align:     center;
}

.bs__stat-label {
    font-size:      0.56rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color:          var(--bs-muted);
    display:        block;
    margin-bottom:  0.2rem;
}

.bs__stat-value {
    font-size:      1rem;
    font-weight:    500;
    color:          var(--bs-text);
    font-variant-numeric: tabular-nums;
    transition:     color .25s;
}

.bs__stat-value--glow {
    color:      var(--bs-green);
    text-shadow: 0 0 10px var(--bs-green-glow);
}

/* ── Konfiguracja planszy ── */
.bs__config {
    display:        grid;
    grid-template-columns: 1fr 1fr;
    gap:            0.75rem;
    margin-bottom:  1rem;
}

.bs__config-field {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.bs__select,
.bs__number-input {
    width:           100%;
    background:      var(--bs-surface);
    border:          1px solid var(--bs-border);
    border-radius:   var(--bs-r);
    color:           var(--bs-text);
    font-family:     var(--bs-body);
    font-size:       0.88rem;
    padding:         0.6rem 0.75rem;
    outline:         none;
    box-sizing:      border-box;
    transition:      border-color .15s, box-shadow .15s;
    cursor:          pointer;
    -webkit-appearance: none;
    -moz-appearance:    none;
    appearance:         none;
}

.bs__select:focus,
.bs__number-input:focus {
    border-color: var(--bs-green);
    box-shadow:   0 0 0 2px var(--bs-green-dim);
}

.bs__number-input::-webkit-inner-spin-button,
.bs__number-input::-webkit-outer-spin-button { -webkit-appearance: none; }
.bs__number-input { -moz-appearance: textfield; }

/* ── Pole zakładu ── */
.bs__bet-wrap {
    position:      relative;
    margin-bottom: 0.5rem;
}

.bs__bet-wrap::after {
    content:        'żetonów';
    position:       absolute;
    right:          0.75rem;
    top:            50%;
    transform:      translateY(-50%);
    font-size:      0.6rem;
    letter-spacing: 0.08em;
    color:          var(--bs-muted);
    pointer-events: none;
}

.bs__bet-input {
    width:           100%;
    background:      var(--bs-surface);
    border:          1px solid var(--bs-border);
    border-radius:   var(--bs-r);
    color:           var(--bs-text);
    font-family:     var(--bs-body);
    font-size:       0.92rem;
    padding:         0.65rem 5.5rem 0.65rem 0.75rem;
    outline:         none;
    box-sizing:      border-box;
    transition:      border-color .15s, box-shadow .15s;
    -moz-appearance: textfield;
}

.bs__bet-input::-webkit-inner-spin-button,
.bs__bet-input::-webkit-outer-spin-button { -webkit-appearance: none; }

.bs__bet-input:focus {
    border-color: var(--bs-green);
    box-shadow:   0 0 0 2px var(--bs-green-dim);
}

/* ── Szybkie zakłady ── */
.bs__quick {
    display:       flex;
    gap:           0.4rem;
    margin-bottom: 1.4rem;
}

.bs__quick-btn {
    flex:           1;
    background:     var(--bs-surface);
    border:         1px solid var(--bs-border);
    border-radius:  var(--bs-r);
    color:          var(--bs-muted);
    font-family:    var(--bs-body);
    font-size:      0.62rem;
    letter-spacing: 0.06em;
    padding:        0.4rem 0;
    cursor:         pointer;
    transition:     border-color .15s, color .15s, background .15s, box-shadow .15s;
}

.bs__quick-btn:hover {
    border-color: var(--bs-green);
    color:        var(--bs-green);
    background:   var(--bs-green-dim);
    box-shadow:   0 0 8px var(--bs-green-glow);
}

/* ── Plansza ── */
.bs__grid-wrap {
    margin-bottom: 1.2rem;
}

.bs__grid {
    display:               grid;
    gap:                   5px;
    margin:                0 auto;
}

/* Komórka */
.bs__cell {
    aspect-ratio:    1;
    background:      var(--bs-cell);
    border:          1px solid var(--bs-border);
    border-radius:   4px;
    display:         flex;
    align-items:     center;
    justify-content: center;
    font-size:       1.3rem;
    cursor:          pointer;
    transition:      background .12s, border-color .12s, box-shadow .12s, transform .1s;
    user-select:     none;
    position:        relative;
    overflow:        hidden;
}

.bs__cell--active:hover {
    background:   var(--bs-cell-hover);
    border-color: var(--bs-border-hi);
    box-shadow:   0 0 8px rgba(61,220,151,.15);
    transform:    scale(1.06);
}

.bs__cell--safe {
    background:   rgba(61,220,151,.14);
    border-color: var(--bs-green);
    box-shadow:   0 0 10px var(--bs-green-glow);
    cursor:       default;
    animation:    bs-reveal .3s cubic-bezier(.22,.68,0,1.2) both;
}

.bs__cell--bomb {
    background:   rgba(224,92,92,.18);
    border-color: var(--bs-bomb);
    box-shadow:   0 0 14px rgba(224,92,92,.35);
    cursor:       default;
    animation:    bs-explode .4s cubic-bezier(.22,.68,0,1.2) both;
}

.bs__cell--revealed-bomb {
    background:   rgba(224,92,92,.08);
    border-color: rgba(224,92,92,.35);
    cursor:       default;
    opacity:      0.7;
}

/* Zablokowana komórka (gra skończona, nie odkryta) */
.bs__cell--locked {
    opacity: 0.45;
    cursor:  default;
}

/* Komórka z bombą odkryta po wygranej */
.bs__cell--bomb-reveal {
    background:   rgba(224,92,92,.08);
    border-color: rgba(224,92,92,.28);
    animation:    bs-reveal .3s ease both;
    cursor:       default;
}

@keyframes bs-reveal {
    0%   { transform: scale(0.5); opacity: 0; }
    100% { transform: scale(1);   opacity: 1; }
}

@keyframes bs-explode {
    0%   { transform: scale(1);    }
    30%  { transform: scale(1.35); }
    60%  { transform: scale(0.9);  }
    100% { transform: scale(1);    }
}

/* ── Przycisk start ── */
.bs__submit {
    width:          100%;
    padding:        0.82rem;
    background:     var(--bs-element);
    border:         1px solid var(--bs-element);
    border-radius:  var(--bs-r);
    color:          var(--bs-text);
    font-family:    var(--bs-body);
    font-size:      0.75rem;
    font-weight:    500;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    cursor:         pointer;
    margin-bottom:  1.4rem;
    transition:     background .15s, border-color .15s, box-shadow .15s;
}

.bs__submit:hover {
    background:   #3a4588;
    border-color: var(--bs-green);
    box-shadow:   0 0 14px var(--bs-green-glow);
}

/* ── Przycisk Wypłać ── */
.bs__cashout {
    width:          100%;
    padding:        0.78rem;
    background:     var(--bs-green-dim);
    border:         1px solid var(--bs-green);
    border-radius:  var(--bs-r);
    color:          var(--bs-green);
    font-family:    var(--bs-body);
    font-size:      0.75rem;
    font-weight:    500;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    cursor:         pointer;
    margin-bottom:  1rem;
    transition:     background .15s, box-shadow .15s;
    box-shadow:     0 0 10px var(--bs-green-glow);
}

.bs__cashout:hover {
    background: rgba(61,220,151,.22);
    box-shadow: 0 0 20px var(--bs-green-glow);
}

.bs__cashout:disabled {
    opacity:       0.35;
    cursor:        default;
    box-shadow:    none;
    border-color:  var(--bs-border);
    color:         var(--bs-muted);
    background:    var(--bs-surface);
}

/* ── Komunikat ── */
.bs__message {
    min-height:      2.2rem;
    font-size:       0.76rem;
    text-align:      center;
    letter-spacing:  0.05em;
    color:           var(--bs-muted);
    padding:         0.5rem 0.75rem;
    background:      var(--bs-surface);
    border:          1px solid var(--bs-border);
    border-radius:   var(--bs-r);
    display:         flex;
    align-items:     center;
    justify-content: center;
}

.bs__message--win {
    color:        var(--bs-win);
    border-color: var(--bs-green);
    background:   var(--bs-green-dim);
    box-shadow:   0 0 10px var(--bs-green-glow);
}

.bs__message--lose {
    color:        var(--bs-lose);
    border-color: var(--bs-lose);
    background:   rgba(224,92,92,.08);
}

.bs__message--error {
    color:        var(--bs-error);
    border-color: var(--bs-error);
    background:   rgba(232,150,77,.08);
}

/* ── Reset ── */
.bs__reset {
    display:        block;
    width:          100%;
    margin-top:     0.9rem;
    padding:        0.48rem;
    background:     transparent;
    border:         1px solid var(--bs-border);
    border-radius:  var(--bs-r);
    color:          var(--bs-muted);
    font-family:    var(--bs-body);
    font-size:      0.6rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    cursor:         pointer;
    transition:     color .15s, border-color .15s;
}

.bs__reset:hover {
    color:        var(--bs-text);
    border-color: var(--bs-border-hi);
}

/* ── Bomby info ── */
.bs__bombs-info {
    font-size:      0.62rem;
    letter-spacing: 0.07em;
    color:          var(--bs-muted);
    text-align:     center;
    margin-bottom:  0.6rem;
}

/* Responsywność planszy */
@media (max-width: 360px) {
    .bs { padding: 1.5rem 1rem 1rem; }
}
</style>

<div class="bs">

    <p class="bs__title">Bomb Sweeper</p>
    <p class="bs__balance">
        Saldo: <strong><?= (int) $balance ?> żetonów</strong>
    </p>

    <?php if ($game): ?>

    <!-- ── Statsy aktywnej gry ───────────────────────────────────── -->
    <div class="bs__stats">
        <div class="bs__stat">
            <span class="bs__stat-label">Mnożnik</span>
            <span class="bs__stat-value <?= count($game['revealed']) > 0 && $game['status'] !== 'lost' ? 'bs__stat-value--glow' : '' ?>"
                  id="bs-multiplier">
                ×<?= number_format($game['multiplier'], 2) ?>
            </span>
        </div>
        <div class="bs__stat">
            <span class="bs__stat-label">Aktualna wygrana</span>
            <span class="bs__stat-value" id="bs-winnings">
                <?= $game['winnings'] ?> <span style="font-size:.7em;color:var(--bs-muted)">żetonów</span>
            </span>
        </div>
        <div class="bs__stat">
            <span class="bs__stat-label">Bomby</span>
            <span class="bs__stat-value" style="color:var(--bs-lose)">
                💣 <?= $game['bombs'] ?> / <?= $game['size'] * $game['size'] ?>
            </span>
        </div>
    </div>

    <?php
    // Zbuduj planszę
    $size      = $game['size'];
    $cells     = $size * $size;
    $revealed  = $game['revealed'];
    $bomb_pos  = $game['bomb_pos'];
    $status    = $game['status'];
    $game_over = ($status !== 'active');
    ?>

    <!-- ── Plansza ───────────────────────────────────────────────── -->
    <div class="bs__grid-wrap">
        <div class="bs__bombs-info">
            <?php
            $safe_found = count(array_diff($revealed, $bomb_pos));
            $safe_total = $cells - $game['bombs'];
            echo $safe_found . ' / ' . $safe_total . ' bezpiecznych pól odkrytych';
            ?>
        </div>
        <div class="bs__grid" id="bs-grid"
             style="grid-template-columns: repeat(<?= $size ?>, 1fr); max-width: <?= min(400, $size * 52) ?>px;">
            <?php for ($i = 0; $i < $cells; $i++):
                $is_revealed = in_array($i, $revealed);
                $is_bomb     = in_array($i, $bomb_pos);

                if ($is_revealed && $is_bomb) {
                    $cls  = 'bs__cell bs__cell--bomb';
                    $icon = '💣';
                } elseif ($is_revealed) {
                    $cls  = 'bs__cell bs__cell--safe';
                    $icon = '💎';
                } elseif ($game_over && $is_bomb && $status === 'lost') {
                    // Odkryj wszystkie bomby po przegranej
                    $cls  = 'bs__cell bs__cell--revealed-bomb';
                    $icon = '💣';
                } elseif ($game_over && $is_bomb && $status === 'won') {
                    // Odkryj bomby po wygranej
                    $cls  = 'bs__cell bs__cell--bomb-reveal';
                    $icon = '💣';
                } elseif ($game_over) {
                    $cls  = 'bs__cell bs__cell--locked';
                    $icon = '';
                } else {
                    $cls  = 'bs__cell bs__cell--active';
                    $icon = '';
                }
            ?>
                <?php if (!$game_over && !$is_revealed): ?>
                <form method="POST" action="" style="display:contents">
                    <button type="submit" name="reveal" value="<?= $i ?>"
                            class="<?= $cls ?>" title="Pole <?= $i + 1 ?>">
                        <?= $icon ?>
                    </button>
                </form>
                <?php else: ?>
                <div class="<?= $cls ?>">
                    <?= $icon ?>
                </div>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    </div>

    <hr class="bs__divider">

    <!-- ── Wypłata / Nowa gra ────────────────────────────────────── -->
    <?php if ($status === 'active'): ?>
    <form method="POST" action="">
        <button type="submit" name="cashout" value="1" class="bs__cashout"
                <?= count($revealed) === 0 ? 'disabled' : '' ?>>
            💰 Wypłać <?= $game['winnings'] ?> żetonów (×<?= number_format($game['multiplier'], 2) ?>)
        </button>
    </form>
    <?php endif; ?>

    <form method="POST" action="">
        <button type="submit" name="new_game" value="1" class="bs__submit">
            🔄 Nowa gra
        </button>

        <?php else: /* Brak aktywnej gry — formularz konfiguracji */ ?>

    <!-- ── Konfiguracja ──────────────────────────────────────────── -->
    <form method="POST" action="">

        <span class="bs__label">Konfiguracja planszy</span>
        <div class="bs__config">
            <div class="bs__config-field">
                <label class="bs__label" for="bs_grid_size">Rozmiar planszy</label>
                <select class="bs__select" name="grid_size" id="bs_grid_size">
                    <?php for ($s = 3; $s <= 8; $s++): ?>
                    <option value="<?= $s ?>" <?= $prefill_size === $s ? 'selected' : '' ?>>
                        <?= $s ?>×<?= $s ?> (<?= $s*$s ?> pól)
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="bs__config-field">
                <label class="bs__label" for="bs_bombs">Liczba bomb</label>
                <input type="number"
                       class="bs__number-input"
                       name="bombs"
                       id="bs_bombs"
                       min="1"
                       max="<?= $prefill_size * $prefill_size - 1 ?>"
                       value="<?= $prefill_bombs ?>"
                       id="bs-bombs-input">
            </div>
        </div>

        <!-- Podgląd mnożnika za pierwsze pole -->
        <div class="bs__stats" style="margin-bottom:1rem">
            <div class="bs__stat">
                <span class="bs__stat-label">Mnożnik ×1 (pierwsze pole)</span>
                <span class="bs__stat-value" id="bs-preview-mult1">
                    ×<?= number_format(bs_calc_mult($prefill_size * $prefill_size, $prefill_bombs, 1), 2) ?>
                </span>
            </div>
            <div class="bs__stat">
                <span class="bs__stat-label">Mnożnik ×3 (po 3 polach)</span>
                <span class="bs__stat-value" id="bs-preview-mult3">
                    ×<?= number_format(bs_calc_mult($prefill_size * $prefill_size, $prefill_bombs, 3), 2) ?>
                </span>
            </div>
            <div class="bs__stat">
                <span class="bs__stat-label">Szansa na ×1</span>
                <span class="bs__stat-value" id="bs-preview-chance">
                    <?php
                    $c  = $prefill_size * $prefill_size;
                    $b  = $prefill_bombs;
                    $ch = round((($c - $b) / $c) * 100, 1);
                    echo $ch . '%';
                    ?>
                </span>
            </div>
        </div>

        <!-- Zakład -->
        <label class="bs__label" for="bs_bet">Wysokość zakładu</label>
        <div class="bs__bet-wrap">
            <input type="number"
                   class="bs__bet-input"
                   name="bet"
                   id="bs_bet"
                   min="1"
                   max="<?= (int) $balance ?>"
                   value="<?= (int) $prefill_bet ?>">
        </div>

        <!-- Szybkie zakłady -->
        <div class="bs__quick">
            <?php
            $fractions = ['10%' => 0.1, '25%' => 0.25, '50%' => 0.5, 'MAX' => 1.0];
            foreach ($fractions as $label => $frac):
            ?>
            <button type="submit" name="quick_bet" value="<?= $frac ?>"
                    class="bs__quick-btn">
                <?= $label ?>
            </button>
            <?php endforeach; ?>
        </div>

        <hr class="bs__divider">

        <button type="submit" name="new_game" value="1" class="bs__submit">
            💣 Rozpocznij grę
        </button>

    <?php endif; /* koniec if($game) */ ?>

        <!-- Komunikat — zawsze widoczny -->
        <div class="bs__message<?= htmlspecialchars($inline_class) ?>">
            <?= htmlspecialchars($inline_text) ?>
        </div>

    </form>

    <!-- Reset salda -->
    <form method="POST" action="">
        <button type="submit" name="reset" value="1" class="bs__reset">
            ↺ Reset salda (1000 żetonów)
        </button>
    </form>

</div>

<script>
(function () {
    const HOUSE_EDGE = 0.03;

    // ── Podgląd mnożnika w formularzu konfiguracji ──────────────
    const gridSel   = document.getElementById('bs_grid_size');
    const bombsInp  = document.getElementById('bs_bombs');
    const mult1El   = document.getElementById('bs-preview-mult1');
    const mult3El   = document.getElementById('bs-preview-mult3');
    const chanceEl  = document.getElementById('bs-preview-chance');

    function calcMult(cells, bombs, steps) {
        const safe = cells - bombs;
        if (safe <= 0) return 1;
        let mult = 1.0;
        let rc = cells, rs = safe;
        for (let i = 0; i < steps; i++) {
            if (rs <= 0) break;
            mult *= rc / rs;
            rc--; rs--;
        }
        return Math.round(mult * (1 - HOUSE_EDGE) * 100) / 100;
    }

    function updatePreview() {
        if (!gridSel || !bombsInp) return;
        const size  = parseInt(gridSel.value);
        const cells = size * size;
        const bombs = Math.max(1, Math.min(cells - 1, parseInt(bombsInp.value) || 1));

        // Ogranicz max bomby
        bombsInp.max = cells - 1;
        if (parseInt(bombsInp.value) > cells - 1) bombsInp.value = cells - 1;

        if (mult1El) mult1El.textContent = '×' + calcMult(cells, bombs, 1).toFixed(2);
        if (mult3El) {
            const s3 = Math.min(3, cells - bombs);
            mult3El.textContent = '×' + calcMult(cells, bombs, s3).toFixed(2);
        }
        if (chanceEl) {
            const chance = Math.round(((cells - bombs) / cells) * 1000) / 10;
            chanceEl.textContent = chance + '%';
        }
    }

    if (gridSel)  gridSel.addEventListener('change', updatePreview);
    if (bombsInp) bombsInp.addEventListener('input',  updatePreview);
    updatePreview();

    // ── Sync salda w nagłówku ────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        const balance = <?= (int) $balance ?>;
        if (typeof updateHeaderBalance === 'function') {
            updateHeaderBalance(balance);
        }
    });
})();
</script>