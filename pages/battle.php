<?php
if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login');
    exit;
}

$balance = (int) $_SESSION['user_balance'];
$user_id = $_SESSION['user_id'] ?? 0;

/* ═══════════════════════════════════════════════════
   TABLICA MNOŻNIKÓW — minimum 0.25×, ~50% < 1×
   ═══════════════════════════════════════════════════ */
$MULTIPLIERS = [
    [0.25,  1400],
    [0.33,  1100],
    [0.50,  1100],
    [0.66,   700],
    [0.75,   700],
    [1.00,   900],
    [1.25,   550],
    [1.50,   500],
    [2.00,   500],
    [2.50,   300],
    [3.00,   250],
    [4.00,   180],
    [5.00,   150],
    [7.50,    90],
    [10.00,   60],
    [15.00,   40],
    [25.00,   25],
    [50.00,   10],
    [100.00,   4],
    [250.00,   1],
];
$TOTAL_WEIGHT = array_sum(array_column($MULTIPLIERS, 1));

function spinMultiplier(array $mults, int $total): array {
    $r = rand(1, $total);
    $acc = 0;
    foreach ($mults as $m) {
        $acc += $m[1];
        if ($r <= $acc) return $m;
    }
    return $mults[0];
}

/* ═══════════════════════════════════════════════════
   OPCJE GRY
   ═══════════════════════════════════════════════════ */
$SPIN_PRICES = [10, 25, 50, 100, 250, 500];
$SPIN_COUNTS = [1, 2, 3, 4, 5];
$PLAYER_OPTS = [2, 3];
$MODE_OPTS   = ['normal','underdog','lastchance','winscount'];
$MODE_LABELS = [
    'normal'     => 'Normalny',
    'underdog'   => 'Underdog',
    'lastchance' => 'Ostatnia szansa',
    'winscount'  => 'Liczba wygranych',
];
$MODE_DESC = [
    'normal'     => 'Wygrywa gracz z najwyższą łączną sumą.',
    'underdog'   => 'Wygrywa gracz z najniższą łączną sumą.',
    'lastchance' => 'Liczy się tylko ostatni spin każdego gracza.',
    'winscount'  => 'Wygrywa kto wygrał najwięcej pojedynczych spinów.',
];

/* PREFILLE */
$prefill_price   = isset($_POST['spin_price']) ? (int)    $_POST['spin_price'] : 50;
$prefill_count   = isset($_POST['spin_count']) ? (int)    $_POST['spin_count'] : 1;
$prefill_players = isset($_POST['players'])    ? (int)    $_POST['players']    : 2;
$prefill_mode    = isset($_POST['mode'])       ? (string) $_POST['mode']       : 'normal';

if (!in_array($prefill_price,   $SPIN_PRICES, true)) $prefill_price   = 50;
if (!in_array($prefill_count,   $SPIN_COUNTS, true)) $prefill_count   = 1;
if (!in_array($prefill_players, $PLAYER_OPTS, true)) $prefill_players = 2;
if (!in_array($prefill_mode,    $MODE_OPTS,   true)) $prefill_mode    = 'normal';

/* RESET */
if (isset($_POST['reset'])) {
    $_SESSION['user_balance'] = 1000;
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/* ═══════════════════════════════════════════════════
   GŁÓWNA GRA
   ═══════════════════════════════════════════════════ */
$played         = false;
$error          = '';
$player_spins   = [];
$player_total   = [];
$player_wins    = [];
$tiebreak_spins = [];
$winner_idx     = null;
$prize          = 0;
$total_cost     = 0;

if (isset($_POST['play'])) {
    $spin_price  = (int)    ($_POST['spin_price'] ?? 50);
    $spin_count  = (int)    ($_POST['spin_count'] ?? 1);
    $num_players = (int)    ($_POST['players']    ?? 2);
    $mode        = (string) ($_POST['mode']       ?? 'normal');

    if (!in_array($spin_price,  $SPIN_PRICES, true)) $error = 'Nieprawidłowa cena spinu.';
    elseif (!in_array($spin_count,  $SPIN_COUNTS, true)) $error = 'Nieprawidłowa liczba spinów.';
    elseif (!in_array($num_players, $PLAYER_OPTS, true)) $error = 'Nieprawidłowa liczba graczy.';
    elseif (!in_array($mode,        $MODE_OPTS,   true)) $error = 'Nieprawidłowy tryb.';
    else {
        $total_cost = $spin_price * $spin_count;
        if ($total_cost > $balance) {
            $error = 'Niewystarczające saldo. Potrzebujesz ' . $total_cost . ' żetonów.';
        } else {
            $_SESSION['user_balance'] -= $total_cost;
            $balance -= $total_cost;

            for ($p = 0; $p < $num_players; $p++) {
                $player_spins[$p] = [];
                $player_total[$p] = 0;
                $player_wins[$p]  = 0;
                for ($s = 0; $s < $spin_count; $s++) {
                    $m   = spinMultiplier($MULTIPLIERS, $TOTAL_WEIGHT);
                    $val = (int) round($m[0] * $spin_price);
                    $player_spins[$p][$s] = ['mult' => $m[0], 'val' => $val];
                    $player_total[$p] += $val;
                }
            }

            for ($s = 0; $s < $spin_count; $s++) {
                $best_p   = 0;
                $best_val = $player_spins[0][$s]['val'];
                for ($p = 1; $p < $num_players; $p++) {
                    if ($player_spins[$p][$s]['val'] > $best_val) {
                        $best_val = $player_spins[$p][$s]['val'];
                        $best_p   = $p;
                    }
                }
                $player_wins[$best_p]++;
            }

            $determine_winner = function() use ($mode, $num_players, $player_total, $player_wins, $player_spins, $spin_count): ?int {
                switch ($mode) {
                    case 'normal':
                        $max = max($player_total);
                        $candidates = array_keys(array_filter($player_total, fn($v) => $v === $max));
                        return count($candidates) === 1 ? $candidates[0] : null;
                    case 'underdog':
                        $min = min($player_total);
                        $candidates = array_keys(array_filter($player_total, fn($v) => $v === $min));
                        return count($candidates) === 1 ? $candidates[0] : null;
                    case 'lastchance':
                        $last = [];
                        for ($p = 0; $p < $num_players; $p++) {
                            $last[$p] = $player_spins[$p][$spin_count - 1]['val'];
                        }
                        $max = max($last);
                        $candidates = array_keys(array_filter($last, fn($v) => $v === $max));
                        return count($candidates) === 1 ? $candidates[0] : null;
                    case 'winscount':
                        $max = max($player_wins);
                        $candidates = array_keys(array_filter($player_wins, fn($v) => $v === $max));
                        return count($candidates) === 1 ? $candidates[0] : null;
                }
                return null;
            };

            $winner_idx = $determine_winner();

            $tiebreak_rounds = 0;
            while ($winner_idx === null && $tiebreak_rounds < 20) {
                $tiebreak_rounds++;
                $tb_vals = [];
                for ($p = 0; $p < $num_players; $p++) {
                    $m = spinMultiplier($MULTIPLIERS, $TOTAL_WEIGHT);
                    $val = (int) round($m[0] * $spin_price);
                    $tiebreak_spins[$tiebreak_rounds][$p] = ['mult' => $m[0], 'val' => $val];
                    $tb_vals[$p] = $val;
                }
                $max = max($tb_vals);
                $candidates = array_keys(array_filter($tb_vals, fn($v) => $v === $max));
                if (count($candidates) === 1) $winner_idx = $candidates[0];
            }
            if ($winner_idx === null) $winner_idx = 0;

            $prize = 0;
            for ($p = 0; $p < $num_players; $p++) {
                $prize += $player_total[$p];
            }

            if ($winner_idx === 0) {
                $_SESSION['user_balance'] += $prize;
                $balance += $prize;
                $win = $prize - $total_cost;
            } else {
                $win = -$total_cost;
            }

            $balanceAfter = $_SESSION['user_balance'];
            $sql = "INSERT INTO game_history (user_id, game, bet, win, balance_after) VALUES ('$user_id', 'Case Battle', '$total_cost', '$win', '$balanceAfter')";
            if (!mysqli_query($conn, $sql)) {
                error_log('Błąd zapisu historii Case Battle: ' . mysqli_error($conn));
            }

            $played          = true;
            $prefill_price   = $spin_price;
            $prefill_count   = $spin_count;
            $prefill_players = $num_players;
            $prefill_mode    = $mode;
        }
    }
}

/* ═══════════════════════════════════════════════════
   BUDOWANIE TAŚM
   ═══════════════════════════════════════════════════ */
$TAPE_PRE    = 18;
$TAPE_BOTTOM = 5;

function buildTape(array $mults, int $total, float $result_mult, int $pre, int $bottom): array {
    $tape = [];
    for ($i = 0; $i < 10; $i++)    $tape[] = spinMultiplier($mults, $total)[0];
    for ($i = 0; $i < $pre; $i++)  $tape[] = spinMultiplier($mults, $total)[0];
    $winner_pos = count($tape);
    $tape[] = $result_mult;
    for ($i = 0; $i < $bottom; $i++) $tape[] = spinMultiplier($mults, $total)[0];
    return ['tape' => $tape, 'winner_pos' => $winner_pos];
}

$tapes_data      = [];
$tiebreak_tapes  = [];

if ($played) {
    for ($p = 0; $p < $prefill_players; $p++) {
        $tapes_data[$p] = [];
        for ($s = 0; $s < $prefill_count; $s++) {
            $tapes_data[$p][$s] = buildTape(
                $MULTIPLIERS, $TOTAL_WEIGHT,
                $player_spins[$p][$s]['mult'],
                $TAPE_PRE, $TAPE_BOTTOM
            );
        }
    }
    foreach ($tiebreak_spins as $round => $players_tb) {
        foreach ($players_tb as $p => $tb) {
            $tiebreak_tapes[$round][$p] = buildTape(
                $MULTIPLIERS, $TOTAL_WEIGHT,
                $tb['mult'], $TAPE_PRE, $TAPE_BOTTOM
            );
        }
    }
}

/* ═══════════════════════════════════════════════════
   PRZYGOTOWANIE DANYCH DLA JS (czyste PHP → JSON)
   ═══════════════════════════════════════════════════ */
$js_data = [
    'played'       => $played,
    'numPlayers'   => $prefill_players,
    'numSpins'     => $prefill_count,
    'spinPrice'    => $prefill_price,
    'winnerIdx'    => $winner_idx,
    'mode'         => $prefill_mode,
    'balance'      => $balance,
    'playerTotals' => $played ? array_values($player_total) : [],
    'playerWins'   => $played ? array_values($player_wins)  : [],
    'spinResults'  => $played
        ? array_map(
            fn($p) => array_map(fn($s) => $player_spins[$p][$s], range(0, $prefill_count - 1)),
            range(0, $prefill_players - 1)
          )
        : [],
    'winnerPos'    => $played
        ? array_map(
            fn($p) => array_map(fn($s) => $tapes_data[$p][$s]['winner_pos'] ?? 0, range(0, $prefill_count - 1)),
            range(0, $prefill_players - 1)
          )
        : [],
    'tbRounds'     => $played ? array_values(array_keys($tiebreak_spins)) : [],
    'tbPos'        => ($played && count($tiebreak_spins) > 0)
        ? array_values(array_map(
            fn($round) => array_map(fn($p) => $tiebreak_tapes[$round][$p]['winner_pos'] ?? 0, range(0, $prefill_players - 1)),
            array_keys($tiebreak_spins)
          ))
        : [],
    'tbVals'       => ($played && count($tiebreak_spins) > 0)
        ? array_values(array_map(
            fn($round) => array_map(fn($p) => $tiebreak_spins[$round][$p]['val'], range(0, $prefill_players - 1)),
            array_keys($tiebreak_spins)
          ))
        : [],
    'tapesData'    => $played
        ? array_map(
            fn($p) => array_map(fn($s) => $tapes_data[$p][$s], range(0, $prefill_count - 1)),
            range(0, $prefill_players - 1)
          )
        : [],
];

/* Kolory komórek */
function multColor(float $m): string {
    if ($m < 0.5)  return '#3d1a1a';
    if ($m < 1.0)  return '#4a2e10';
    if ($m < 2.0)  return '#0f3020';
    if ($m < 5.0)  return '#0f2040';
    if ($m < 10.0) return '#2d0a4e';
    return '#4a3000';
}
function multTextColor(float $m): string {
    if ($m >= 10.0) return '#ffd700';
    if ($m >= 5.0)  return '#d8b4fe';
    if ($m >= 2.0)  return '#7dd3fc';
    if ($m >= 1.0)  return '#86efac';
    return '#fca5a5';
}
?>
<!-- CASE BATTLE -->


<div class="coin-game">

    <p class="coin-game__title">Case Battle</p>
    <p class="coin-game__balance">
        Saldo: <strong><?= (int)$balance ?> żetonów</strong>
    </p>

    <hr class="coin-game__divider">

    <form method="POST" action="" id="cb-form">

        <div class="cb-config">
            <!-- Cena spinu -->
            <div class="cb-config-row">
                <span class="coin-game__label">Cena jednego spinu</span>
                <div class="cb-options" id="cb-prices">
                    <?php foreach ($SPIN_PRICES as $p): ?>
                    <button type="button" class="cb-opt-btn cb-price-btn<?= $p===$prefill_price?' cb-active':'' ?>"
                            data-val="<?= $p ?>"><?= $p ?> </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="spin_price" id="cb-hidden-price" value="<?= $prefill_price ?>">
            </div>

            <!-- Liczba spinów -->
            <div class="cb-config-row">
                <span class="coin-game__label">Liczba spinów</span>
                <div class="cb-options" id="cb-counts">
                    <?php foreach ($SPIN_COUNTS as $c): ?>
                    <button type="button" class="cb-opt-btn cb-count-btn<?= $c===$prefill_count?' cb-active':'' ?>"
                            data-val="<?= $c ?>"><?= $c ?> spin<?= $c>1?'y':'' ?></button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="spin_count" id="cb-hidden-count" value="<?= $prefill_count ?>">
            </div>

            <!-- Liczba graczy -->
            <div class="cb-config-row">
                <span class="coin-game__label">Tryb rozgrywki</span>
                <div class="cb-options">
                    <button type="button" class="cb-opt-btn cb-players-btn<?= $prefill_players===2?' cb-active':'' ?>"
                            data-val="2">⚔️ 1v1</button>
                    <button type="button" class="cb-opt-btn cb-players-btn<?= $prefill_players===3?' cb-active':'' ?>"
                            data-val="3">⚔️ 1v2</button>
                </div>
                <input type="hidden" name="players" id="cb-hidden-players" value="<?= $prefill_players ?>">
            </div>

            <!-- Tryb wygranej -->
            <div class="cb-config-row">
                <span class="coin-game__label">Warunki zwycięstwa</span>
                <div class="cb-mode-grid">
                    <?php foreach ($MODE_OPTS as $m): ?>
                    <button type="button" class="cb-mode-btn<?= $m===$prefill_mode?' cb-active':'' ?>"
                            data-val="<?= $m ?>">
                        <?= $MODE_LABELS[$m] ?>
                        <small><?= $MODE_DESC[$m] ?></small>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="mode" id="cb-hidden-mode" value="<?= $prefill_mode ?>">
            </div>

            <!-- Koszt -->
            <div class="cb-cost-bar">
                <span>Twój koszt wejścia:</span>
                <strong id="cb-cost-display"><?= $prefill_price * $prefill_count ?> żetonów</strong>
                <span style="opacity:.55;font-size:.8rem;">
                    (pula: <span id="cb-pot-display"><?= $prefill_price * $prefill_count * $prefill_players ?></span> )
                </span>
            </div>
        </div>

        <hr class="coin-game__divider">

       
        <div class="cb-arena" id="cb-arena">
            <?php for ($p = 0; $p < $prefill_players; $p++):
                $is_you = ($p === 0);
                $label  = $is_you ? '🧑 Ty' : '🤖 Bot ' . $p;
                $lclass = $is_you ? 'cb-player-label--you' : '';
            ?>
            <div class="cb-player-col" id="cb-col-<?= $p ?>">
                <div class="cb-player-label <?= $lclass ?>"><?= $label ?></div>

                <?php for ($s = 0; $s < $prefill_count; $s++): ?>
                <div>
                    <div class="cb-round-label">Spin <?= $s+1 ?></div>
                    <div class="cb-spin-wrap">
                        <div class="cb-spin-arrow">▼</div>
                        <div class="cb-spin-outer">
                            <div class="cb-reel" id="cb-reel-<?= $p ?>-<?= $s ?>">
                                <?php
                                if ($played && isset($tapes_data[$p][$s])) {
                                    $td = $tapes_data[$p][$s];
                                    foreach ($td['tape'] as $ti => $mult) {
                                        $isW  = ($ti === $td['winner_pos']);
                                        $val  = (int) round($mult * $prefill_price);
                                        $bg   = multColor($mult);
                                        $fg   = multTextColor($mult);
                                        echo '<div class="cb-cell"'
                                           . ($isW ? ' id="cb-winner-' . $p . '-' . $s . '"' : '')
                                           . ' style="background:' . $bg . ';color:' . $fg . ';">'
                                           . '<span class="cb-cell-mult">' . $mult . '×</span>'
                                           . '<span class="cb-cell-val">' . $val . ' 🪙</span>'
                                           . '</div>';
                                    }
                                } else {
                                    foreach ([0.25,0.5,1.0,0.33,2.0,0.75,1.5,0.25,3.0,0.5,1.0,0.33,2.0,0.75,1.5] as $mult) {
                                        $val = (int) round($mult * $prefill_price);
                                        echo '<div class="cb-cell" style="background:' . multColor($mult) . ';color:' . multTextColor($mult) . ';">'
                                           . '<span class="cb-cell-mult">' . $mult . '×</span>'
                                           . '<span class="cb-cell-val">' . $val . ' 🪙</span>'
                                           . '</div>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <div class="cb-spin-result" id="cb-spin-result-<?= $p ?>-<?= $s ?>"></div>
                    </div>
                </div>
                <?php endfor; ?>

                <div class="cb-player-total" id="cb-total-<?= $p ?>">—</div>
            </div>
            <?php endfor; ?>
        </div>

        <!-- ══ SEKCJA DOGRYWKI ══ -->
        <?php if ($played && count($tiebreak_spins) > 0): ?>
        <div class="cb-tiebreak-section" id="cb-tiebreak-section" style="display:none;">
            <div class="cb-tiebreak-title">🔁 Dogrywka</div>
            <?php foreach ($tiebreak_spins as $round => $players_tb): ?>
            <div style="margin-bottom:10px;">
                <div style="text-align:center;font-size:.72rem;color:#888;margin-bottom:4px;">Runda <?= $round ?></div>
                <div class="cb-tiebreak-arena">
                    <?php for ($p = 0; $p < $prefill_players; $p++): ?>
                    <div class="cb-tiebreak-col">
                        <div class="cb-spin-wrap">
                            <div class="cb-spin-arrow">▼</div>
                            <div class="cb-spin-outer">
                                <div class="cb-reel" id="cb-tbreel-<?= $round ?>-<?= $p ?>">
                                    <?php
                                    if (isset($tiebreak_tapes[$round][$p])) {
                                        $td = $tiebreak_tapes[$round][$p];
                                        foreach ($td['tape'] as $ti => $mult) {
                                            $isW = ($ti === $td['winner_pos']);
                                            $val = (int) round($mult * $prefill_price);
                                            $bg  = multColor($mult);
                                            $fg  = multTextColor($mult);
                                            echo '<div class="cb-cell"'
                                               . ($isW ? ' id="cb-tbwinner-' . $round . '-' . $p . '"' : '')
                                               . ' style="background:' . $bg . ';color:' . $fg . ';">'
                                               . '<span class="cb-cell-mult">' . $mult . '×</span>'
                                               . '<span class="cb-cell-val">' . $val . ' 🪙</span>'
                                               . '</div>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Baner wyniku -->
        <div class="cb-result-banner<?= $played ? (' cb-show ' . ($winner_idx===0 ? 'cb-result-banner--win' : 'cb-result-banner--lose')) : '' ?>"
             id="cb-banner">
            <?php if ($played): ?>
                <?php if ($winner_idx === 0): ?>
                    🏆 Wygrałeś! Otrzymujesz <?= $prize ?> 🪙 (suma wszystkich wylosowanych wartości)
                <?php else: ?>
                    💀 Przegrałeś <?= $total_cost ?> żetonów. Bot <?= $winner_idx ?> wygrał.
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
        <div class="coin-game__message coin-game__message--error">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <hr class="coin-game__divider">

        <button type="submit" name="play" value="1" class="coin-game__submit">
            ⚔️ Rozpocznij Battle
        </button>

    </form>

    <a href="home" class="back-btn">← Wróć do gier</a>
</div>

<!-- ═══════════════════════════════════════════════════
     DANE GRY — generowane przez PHP, odczytywane przez JS
     ═══════════════════════════════════════════════════ -->
<script id="cb-game-data" type="application/json">
<?= json_encode($js_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>

<script>
(function () {

    /* ── Odczyt danych z PHP ── */
    const D           = JSON.parse(document.getElementById('cb-game-data').textContent);
    const PLAYED      = D.played;
    const NUM_PLAYERS = D.numPlayers;
    const NUM_SPINS   = D.numSpins;
    const SPIN_PRICE  = D.spinPrice;
    const WINNER_IDX  = D.winnerIdx;
    const MODE        = D.mode;
    const WINNER_POS  = D.winnerPos;
    const TB_ROUNDS   = D.tbRounds;
    const TB_POS      = D.tbPos;
    const TB_VALS     = D.tbVals;
    const PLAYER_TOTALS = D.playerTotals;
    const PLAYER_WINS   = D.playerWins;
    const SPIN_RESULTS  = D.spinResults;

    const CELL_H        = 50;
    const OUTER_H       = 200;
    const CENTER        = Math.floor(OUTER_H / 2) - Math.floor(CELL_H / 2);
    const ANIM_DURATION = 5500;

    /* ── Konfiguracja przycisków ── */
    function bindGroup(selector, hiddenId, onChange) {
        const hidden = document.getElementById(hiddenId);
        document.querySelectorAll(selector).forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll(selector).forEach(b => b.classList.remove('cb-active'));
                this.classList.add('cb-active');
                hidden.value = this.dataset.val;
                if (onChange) onChange();
            });
        });
    }
    function updateCost() {
        const price   = parseInt(document.getElementById('cb-hidden-price').value)   || 50;
        const count   = parseInt(document.getElementById('cb-hidden-count').value)   || 1;
        const players = parseInt(document.getElementById('cb-hidden-players').value) || 2;
        const cost    = price * count;
        const cd = document.getElementById('cb-cost-display');
        const pd = document.getElementById('cb-pot-display');
        if (cd) cd.textContent = cost + ' żetonów';
        if (pd) pd.textContent = cost * players;
    }
    bindGroup('.cb-price-btn',   'cb-hidden-price',   updateCost);
    bindGroup('.cb-count-btn',   'cb-hidden-count',   updateCost);
    bindGroup('.cb-players-btn', 'cb-hidden-players', updateCost);
    bindGroup('.cb-mode-btn',    'cb-hidden-mode',    null);

    if (!PLAYED) return;

    /* ── Ukryj baner do końca animacji ── */
    const banner = document.getElementById('cb-banner');
    if (banner) { banner.style.opacity = '0'; }

    /* ── Animacja jednego reela ── */
    function animateReel(reelId, winPos) {
        return new Promise(resolve => {
            const reel = document.getElementById(reelId);
            if (!reel) { resolve(); return; }
            const targetY = -(winPos * CELL_H) + CENTER;
            reel.style.transition = 'none';
            reel.style.transform  = 'translateY(0)';
            void reel.offsetWidth;
            reel.style.transition = `transform ${ANIM_DURATION}ms cubic-bezier(0.05,0.0,0.15,1.0)`;
            reel.style.transform  = `translateY(${targetY}px)`;
            reel.addEventListener('transitionend', function onEnd() {
                reel.removeEventListener('transitionend', onEnd);
                resolve();
            });
        });
    }

    /* ── Sekwencja: spiny rundami ── */
    async function runAllSpins() {

        for (let s = 0; s < NUM_SPINS; s++) {
            const promises = [];
            for (let p = 0; p < NUM_PLAYERS; p++) {
                promises.push(animateReel(`cb-reel-${p}-${s}`, WINNER_POS[p][s]));
            }
            await Promise.all(promises);

            for (let p = 0; p < NUM_PLAYERS; p++) {
                const wCell = document.getElementById(`cb-winner-${p}-${s}`);
                if (wCell) wCell.classList.add('cb-cell--winner');

                const resEl = document.getElementById(`cb-spin-result-${p}-${s}`);
                if (resEl && SPIN_RESULTS[p] && SPIN_RESULTS[p][s]) {
                    const r = SPIN_RESULTS[p][s];
                    resEl.textContent = r.mult + '× — ' + r.val + ' 🪙';
                    resEl.style.color = '#ccc';
                }
            }

            if (s < NUM_SPINS - 1) await sleep(600);
        }

        /* Sumy graczy */
        for (let p = 0; p < NUM_PLAYERS; p++) {
            const totalEl = document.getElementById(`cb-total-${p}`);
            if (!totalEl) continue;
            if (MODE === 'winscount') {
                totalEl.textContent = '🏆 ' + PLAYER_WINS[p] + ' wygranych';
            } else {
                totalEl.textContent = PLAYER_TOTALS[p] + ' 🪙';
            }
        }

        await sleep(400);

        /* Dogrywka */
        if (TB_ROUNDS.length > 0) {
            const tbSection = document.getElementById('cb-tiebreak-section');
            if (tbSection) tbSection.style.display = 'block';

            for (let ri = 0; ri < TB_ROUNDS.length; ri++) {
                const round = TB_ROUNDS[ri];
                const tbPromises = [];
                for (let p = 0; p < NUM_PLAYERS; p++) {
                    tbPromises.push(animateReel(`cb-tbreel-${round}-${p}`, TB_POS[ri][p]));
                }
                await Promise.all(tbPromises);

                for (let p = 0; p < NUM_PLAYERS; p++) {
                    const tw = document.getElementById(`cb-tbwinner-${round}-${p}`);
                    if (tw) tw.classList.add('cb-cell--winner');
                }
                if (ri < TB_ROUNDS.length - 1) await sleep(600);
            }
            await sleep(400);
        }

        /* Podświetl zwycięzcę i przegranego */
        for (let p = 0; p < NUM_PLAYERS; p++) {
            const totalEl = document.getElementById(`cb-total-${p}`);
            if (!totalEl) continue;
            if (p === WINNER_IDX) {
                totalEl.classList.add('cb-player-total--winner');
            } else {
                totalEl.classList.add('cb-player-total--loser');
            }
        }

        /* Pokaż baner */
        if (banner) {
            banner.style.display = 'block';
            await sleep(50);
            banner.style.transition = 'opacity .6s';
            banner.style.opacity    = '1';
        }

        /* Zaktualizuj saldo w headerze */
        if (typeof updateHeaderBalance === 'function') updateHeaderBalance(D.balance);
    }

    function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

    setTimeout(runAllSpins, 100);

})();
</script>