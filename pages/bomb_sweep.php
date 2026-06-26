<?php
if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login');
    exit;
}


$balance = (int) $_SESSION['user_balance'];
$message = '';
$error   = '';
$game_name = 'Bomb Sweeper';
$user_id  = $_SESSION['user_id'] ?? 0;

if (isset($_POST['go_to_config'])) {
    unset($_SESSION['bs_game']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}


if (isset($_POST['quick_bet'])) {
    $_SESSION['quick_val'] = max(1, (int) floor($_SESSION['user_balance'] * (float) $_POST['quick_bet']));
    $_SESSION['grid_size'] = max(3, min(8, (int) ($_POST['grid_size']?? 5)));
    $_SESSION['bombs'] = max(1, min($_POST['grid_size'] * $_POST['grid_size'] - 1, (int) ($_POST['bombs'] ?? 3)));

    header('Location: bomb_sweep');
    exit;
}

$game = $_SESSION['bs_game'] ?? null;


if (isset($_POST['new_game'])) {
    $size  = max(3, min(8, (int) ($_SESSION['grid_size'] ?? 5)));
    $cells = $size * $size;
    $bombs = max(1, min($cells - 1, (int) ($_SESSION['bombs'] ?? 3)));
    $bet   = (int) ($_POST['bet'] ?? 0);

    if ($bet < 1 || $bet > $balance) {
        $error = 'Zakład musi być między 1 a ' . $balance . ' żetonami.';
    } else {
        $positions = range(0, $cells - 1);
        shuffle($positions);
        $game = [
            'size'       => $size,
            'bombs'      => $bombs,
            'bomb_pos'   => array_slice($positions, 0, $bombs),
            'revealed'   => [],
            'status'     => 'active',
            'bet'        => $bet,
            'multiplier' => 1.0,
            'winnings'   => 0,
        ];
        $_SESSION['bs_game']      = $game;
        $_SESSION['user_balance'] -= $bet;
        $balance = (int) $_SESSION['user_balance'];
    }
}


if (isset($_POST['reveal']) && $game && $game['status'] === 'active') {
    $cell  = (int) $_POST['reveal'];
    $cells = $game['size'] ** 2;

    if ($cell >= 0 && $cell < $cells && !in_array($cell, $game['revealed'])) {
        if (in_array($cell, $game['bomb_pos'])) {
            $game['revealed'][] = $cell;
            $game['status']     = 'lost';
            $message = 'Boom! Trafiłeś na bombę i straciłeś ' . $game['bet'] . ' żetonów.';
            $lost = -$game['bet'];
            $bet = $game['bet'];
            $balance = (int) $_SESSION['user_balance'] - $bet;
            $sql = "INSERT INTO game_history (user_id, game, bet, win, balance_after) VALUES ('$user_id', '$game_name', '$bet', '$lost', '$balance')";
            try {
                mysqli_query($conn, $sql);
            } catch(mysqli_sql_exception $e) {
                echo "<p style='color:red;'> Wystąpił błąd podczas zapisywania wyniku</p>";
            }
        } else {
            $game['revealed'][] = $cell;
            $safe_total = $cells - $game['bombs'];
            $revealed_count = count($game['revealed']);

            $mult = 1.0;
            $rc = $cells; $rs = $safe_total;
            for ($i = 0; $i < $revealed_count; $i++) {
                $mult *= $rc / $rs; $rc--; $rs--;
            }
            $game['multiplier'] = round($mult * 0.97, 3);
            $game['winnings']   = (int) floor($game['bet'] * $game['multiplier']);

            if ($revealed_count >= $safe_total) {
                $game['status'] = 'won';
                $_SESSION['user_balance'] += $game['winnings'];
                $balance = (int) $_SESSION['user_balance'];
                $message = 'Brawo! Odkryłeś wszystkie bezpieczne pola i wygrałeś ' . $game['winnings'] . ' żetonów!';
                $bet = $game['bet'];
                $win = $game['winnings'];
                $sql = "INSERT INTO game_history (user_id, game, bet, win, balance_after) VALUES ('$user_id', '$game_name', '$bet', '$win', '$balance')";
                try {
                    mysqli_query($conn, $sql);
                } catch(mysqli_sql_exception $e) {
                    echo "<p style='color:red;'> Wystąpił błąd podczas zapisywania wyniku</p>";
                }

            }
        }
        $_SESSION['bs_game'] = $game;
    }
}

// ── Wypłata ──────────────────────────────────────────────────────
if (isset($_POST['cashout']) && $game && $game['status'] === 'active' && count($game['revealed']) > 0) {
    $game['status'] = 'won';
    $_SESSION['user_balance'] += $game['winnings'];
    $balance = (int) $_SESSION['user_balance'];
    $_SESSION['bs_game'] = $game;
    $message = 'Wypłaciłeś ' . $game['winnings'] . ' żetonów! (×' . number_format($game['multiplier'], 2) . ')';
    $bet = $game['bet'];
    $win = $game['winnings'];
    $sql = "INSERT INTO game_history (user_id, game, bet, win, balance_after) VALUES ('$user_id', '$game_name', '$bet', '$win', '$balance')";
    try {
        mysqli_query($conn, $sql);
    } catch(mysqli_sql_exception $e) {
        echo "<p style='color:red;'> Wystąpił błąd podczas zapisywania wyniku</p>";
    }
}

// ── Odśwież zmienne ──────────────────────────────────────────────
$game    = $_SESSION['bs_game'] ?? null;
$balance = (int) $_SESSION['user_balance'];

$prefill_bet   = isset($_SESSION['quick_val'])  ? $_SESSION['quick_val']  : 50;
$prefill_size  = isset($_SESSION['grid_size']) ? (int) $_SESSION['grid_size']  : (isset($game['size'] ) ? max(3, min(8, (int) $game['size'] )) : 5);
$prefill_bombs = isset($_SESSION['bombs']) ? (int) $_SESSION['bombs'] : (isset($game['bombs']) ? (int) $game['bombs'] : 3);

function bs_calc_mult(int $cells, int $bombs, int $revealed): float {
    $safe = $cells - $bombs;
    if ($safe <= 0) return 1.0;
    $mult = 1.0; $rc = $cells; $rs = $safe;
    for ($i = 0; $i < $revealed; $i++) {
        if ($rs <= 0) break;
        $mult *= $rc / $rs; $rc--; $rs--;
    }
    return round($mult * 0.97, 3);
}

// Komunikat statusu
if ($error) {
    $inline_text  = $error;
    $inline_class = ' bs__message--error';
} elseif ($message) {
    $inline_text  = $message;
    $inline_class = $game && $game['status'] === 'won'  ? ' bs__message--win'
                  : ($game && $game['status'] === 'lost' ? ' bs__message--lose' : '');
} elseif ($game && $game['status'] === 'active') {
    $n = count($game['revealed']);
    $inline_text  = $n === 0 ? 'Odkryj pierwsze pole, żeby zacząć.'
                              : 'Odkryto ' . $n . ' ' . ($n === 1 ? 'pole' : 'pola/pól') . '. Kontynuuj lub wypłać.';
    $inline_class = '';
} else {
    $inline_text  = 'Skonfiguruj planszę i postaw zakład.';
    $inline_class = '';
}
?>

<div class="bs">

    <p class="bs__title">Bomb Sweeper</p>
    <p class="bs__balance">
        Saldo: <strong><?= $balance ?> żetonów</strong>
    </p>

    <!-- ── Komunikat ─────────────────────────────────────────────── -->
    <div class="bs__message<?= htmlspecialchars($inline_class) ?>">
        <?= htmlspecialchars($inline_text) ?>
    </div>

    <?php if ($game): ?>

    <!-- ── Statsy ────────────────────────────────────────────────── -->
    <div class="bs__stats">
        <div class="bs__stat">
            <span class="bs__stat-label">Mnożnik</span>
            <span class="bs__stat-value <?= count($game['revealed']) > 0 && $game['status'] !== 'lost' ? 'bs__stat-value--glow' : '' ?>">
                ×<?= number_format($game['multiplier'], 2) ?>
            </span>
        </div>
        <div class="bs__stat">
            <span class="bs__stat-label">Aktualna wygrana</span>
            <span class="bs__stat-value"><?= $game['winnings'] ?> <span style="font-size:.7em;color:var(--bs-muted)">żetonów</span></span>
        </div>
        <div class="bs__stat">
            <span class="bs__stat-label">Bomby</span>
            <span class="bs__stat-value" style="color:var(--bs-lose)">
                💣 <?= $game['bombs'] ?> / <?= $game['size'] ** 2 ?>
            </span>
        </div>
    </div>

    <?php
    $size      = $game['size'];
    $cells     = $size ** 2;
    $revealed  = $game['revealed'];
    $bomb_pos  = $game['bomb_pos'];
    $status    = $game['status'];
    $game_over = $status !== 'active';
    $safe_found = count(array_diff($revealed, $bomb_pos));
    $safe_total = $cells - $game['bombs'];
    ?>

    <!-- ── Plansza ───────────────────────────────────────────────── -->
    <div class="bs__grid-wrap">
        <div class="bs__bombs-info">
            <?= $safe_found ?> / <?= $safe_total ?> bezpiecznych pól odkrytych
        </div>
        <div class="bs__grid"
             style="grid-template-columns: repeat(<?= $size ?>, 1fr); max-width: <?= min(400, $size * 52) ?>px;">
            <?php for ($i = 0; $i < $cells; $i++):
                $is_revealed = in_array($i, $revealed);
                $is_bomb     = in_array($i, $bomb_pos);

                if ($is_revealed && $is_bomb) {
                    $cls = 'bs__cell bs__cell--bomb'; $icon = '💣';
                } elseif ($is_revealed) {
                    $cls = 'bs__cell bs__cell--safe'; $icon = '💎';
                } elseif ($game_over && $is_bomb && $status === 'lost') {
                    $cls = 'bs__cell bs__cell--revealed-bomb'; $icon = '💣';
                } elseif ($game_over && $is_bomb) {
                    $cls = 'bs__cell bs__cell--bomb-reveal'; $icon = '💣';
                } elseif ($game_over) {
                    $cls = 'bs__cell bs__cell--locked'; $icon = '';
                } else {
                    $cls = 'bs__cell bs__cell--active'; $icon = '';
                }
            ?>
                <?php if (!$game_over && !$is_revealed): ?>
                <form method="POST" style="display:contents">
                    <button type="submit" name="reveal" value="<?= $i ?>" class="<?= $cls ?>" title="Pole <?= $i + 1 ?>">
                        <?= $icon ?>
                    </button>
                </form>
                <?php else: ?>
                <div class="<?= $cls ?>"><?= $icon ?></div>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    </div>

    <hr class="bs__divider">

    <!-- ── Wypłata ───────────────────────────────────────────────── -->
    <?php if ($status === 'active'): ?>
    <form method="POST">
        <button type="submit" name="cashout" value="1" class="bs__cashout"
                <?= count($revealed) === 0 ? 'disabled' : '' ?>>
            💰 Wypłać <?= $game['winnings'] ?> żetonów (×<?= number_format($game['multiplier'], 2) ?>)
        </button>
    </form>
    <?php endif; ?>

    <!-- ── Przyciski po zakończeniu gry ─────────────────────────── -->
    <?php if ($game_over): ?>
    <div class="bs__end-actions">
        <form method="POST">
            <input type="hidden" name="grid_size" value="<?= $size ?>">
            <input type="hidden" name="bombs"     value="<?= $game['bombs'] ?>">
            <input type="hidden" name="bet"       value="<?= $game['bet'] ?>">
            <button type="submit" name="new_game" value="1" class="bs__submit">
                🔄 Zagraj ponownie
            </button>
        </form>
        <form method="POST">
            <button type="submit" name="go_to_config" value="1" class="bs__submit bs__submit--secondary">
                ⚙️ Zmień ustawienia
            </button>
        </form>
    </div>
    <?php endif; ?>

    <?php else: /* Brak aktywnej gry – pokaż konfigurację */ ?>

    <!-- ── Konfiguracja ──────────────────────────────────────────── -->
    <form method="POST">
        <div class="bs__config">
            <div class="bs__config-field">
                <label class="bs__label" for="bs_grid_size">Rozmiar planszy</label>
                <select class="bs__select" name="grid_size" id="bs_grid_size">
                    <?php for ($s = 3; $s <= 8; $s++): ?>
                    <option value="<?= $s ?>" <?= $prefill_size === $s ? 'selected' : '' ?>>
                        <?= $s ?>×<?= $s ?> (<?= $s * $s ?> pól)
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="bs__config-field">
                <label class="bs__label" for="bs_bombs">Liczba bomb</label>
                <input type="number" class="bs__number-input" name="bombs" id="bs_bombs"
                       min="1" max="<?= $prefill_size ** 2 - 1 ?>" value="<?= $prefill_bombs ?>">
            </div>
        </div>

        <!-- Podgląd mnożnika -->
        <div class="bs__stats" style="margin-bottom:1rem">
            <div class="bs__stat">
                <span class="bs__stat-label">Mnożnik ×1 (pierwsze pole)</span>
                <span class="bs__stat-value" id="bs-preview-mult1">
                    ×<?= number_format(bs_calc_mult($prefill_size ** 2, $prefill_bombs, 1), 2) ?>
                </span>
            </div>
            <div class="bs__stat">
                <span class="bs__stat-label">Mnożnik ×3 (po 3 polach)</span>
                <span class="bs__stat-value" id="bs-preview-mult3">
                    ×<?= number_format(bs_calc_mult($prefill_size ** 2, $prefill_bombs, 3), 2) ?>
                </span>
            </div>
            <div class="bs__stat">
                <span class="bs__stat-label">Szansa na pierwsze pole</span>
                <span class="bs__stat-value" id="bs-preview-chance">
                    <?= round((($prefill_size ** 2 - $prefill_bombs) / $prefill_size ** 2) * 100, 1) ?>%
                </span>
            </div>
        </div>

        <!-- Zakład -->
        <label class="bs__label" for="bs_bet">Wysokość zakładu</label>
        <div class="bs__bet-wrap">
            <input type="number" class="bs__bet-input" name="bet" id="bs_bet"
                   min="1" max="<?= $balance ?>" value="<?= $prefill_bet ?>">
        </div>

        <!-- Szybkie zakłady -->
        <div class="bs__quick">
            <?php foreach (['10%' => 0.1, '25%' => 0.25, '50%' => 0.5, 'MAX' => 1.0] as $label => $frac): ?>
            <button type="submit" name="quick_bet" value="<?= $frac ?>" class="bs__quick-btn">
                <?= $label ?>
            </button>
            <?php endforeach; ?>
        </div>

        <hr class="bs__divider">

        <button type="submit" name="new_game" value="1" class="bs__submit">
            💣 Rozpocznij grę
        </button>

    </form>

    <?php endif; ?>


    <a href="home" class="back-btn">← Wróć do gier</a>
</div>

<script>
(function () {
    const HOUSE_EDGE = 0.03;
    const gridSel  = document.getElementById('bs_grid_size');
    const bombsInp = document.getElementById('bs_bombs');
    const mult1El  = document.getElementById('bs-preview-mult1');
    const mult3El  = document.getElementById('bs-preview-mult3');
    const chanceEl = document.getElementById('bs-preview-chance');

    function calcMult(cells, bombs, steps) {
        const safe = cells - bombs;
        if (safe <= 0) return 1;
        let mult = 1, rc = cells, rs = safe;
        for (let i = 0; i < steps; i++) {
            if (rs <= 0) break;
            mult *= rc / rs; rc--; rs--;
        }
        return Math.round(mult * (1 - HOUSE_EDGE) * 100) / 100;
    }

    function updatePreview() {
        if (!gridSel || !bombsInp) return;
        const size  = parseInt(gridSel.value);
        const cells = size * size;
        const bombs = Math.max(1, Math.min(cells - 1, parseInt(bombsInp.value) || 1));

        bombsInp.max = cells - 1;
        if (parseInt(bombsInp.value) > cells - 1) bombsInp.value = cells - 1;

        if (mult1El) mult1El.textContent = '×' + calcMult(cells, bombs, 1).toFixed(2);
        if (mult3El) mult3El.textContent = '×' + calcMult(cells, bombs, Math.min(3, cells - bombs)).toFixed(2);
        if (chanceEl) chanceEl.textContent = Math.round(((cells - bombs) / cells) * 1000) / 10 + '%';
    }

    if (gridSel)  gridSel.addEventListener('change', updatePreview);
    if (bombsInp) bombsInp.addEventListener('input',  updatePreview);

    const balance = <?= $balance ?>;
    if (typeof updateHeaderBalance === 'function') updateHeaderBalance(balance);
})();
</script>