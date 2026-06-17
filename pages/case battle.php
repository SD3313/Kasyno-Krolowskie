<?php
require_once __DIR__ . '/../init_session.php';
$balance = (int) $_SESSION['user_balance'];

/* ═══════════════════════════════════════════════════
   TABLICA MNOŻNIKÓW — minimum 0.25×, ~50% < 1×
   ═══════════════════════════════════════════════════ */
$MULTIPLIERS = [
    [0.25,  1400],  // 0.25×  — 14.00%
    [0.33,  1100],  // 0.33×  — 11.00%
    [0.50,  1100],  // 0.50×  — 11.00%
    [0.66,   700],  // 0.66×  —  7.00%
    [0.75,   700],  // 0.75×  —  7.00%  łącznie <1×: ~50%
    [1.00,   900],  // 1×     —  9.00%
    [1.25,   550],  // 1.25×  —  5.50%
    [1.50,   500],  // 1.50×  —  5.00%
    [2.00,   500],  // 2×     —  5.00%
    [2.50,   300],  // 2.50×  —  3.00%
    [3.00,   250],  // 3×     —  2.50%
    [4.00,   180],  // 4×     —  1.80%
    [5.00,   150],  // 5×     —  1.50%
    [7.50,    90],  // 7.50×  —  0.90%
    [10.00,   60],  // 10×    —  0.60%
    [15.00,   40],  // 15×    —  0.40%
    [25.00,   25],  // 25×    —  0.25%
    [50.00,   10],  // 50×    —  0.10%
    [100.00,   4],  // 100×   —  0.04%
    [250.00,   1],  // 250×   —  0.01%
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
$player_spins   = [];   // [gracz][spin] = ['mult'=>float, 'val'=>int]
$player_total   = [];   // suma wylosowanych wartości każdego gracza
$player_wins    = [];   // ile razy wygrał pojedynczy spin (tryb winscount)
$tiebreak_spins = [];   // dogrywkowe spiny [gracz] = ['mult'=>, 'val'=>]
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
            // Odejmij koszt gracza
            $_SESSION['user_balance'] -= $total_cost;
            $balance -= $total_cost;

            // Generuj spiny dla wszystkich graczy
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

            // Wyznacz zwycięzcę każdego spinu (tryb winscount)
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

            // Wyznacz zwycięzcę wg trybu
            $determine_winner = function() use ($mode, $num_players, $player_total, $player_wins, $player_spins, $spin_count): ?int {
                switch ($mode) {
                    case 'normal':
                        $max = max($player_total);
                        $candidates = array_keys(array_filter($player_total, fn($v) => $v === $max));
                        return count($candidates) === 1 ? $candidates[0] : null; // null = remis
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

            // REMIS — dogrywka spin po spinie (wyniki NIE wliczają się do sumy)
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
            // Awaryjne — jeśli 20 dogrywek bez rozstrzygnięcia, wygrywa gracz 0
            if ($winner_idx === null) $winner_idx = 0;

            // NAGRODA = suma wylosowanych wartości WSZYSTKICH graczy łącznie
            // (tj. gracz dostaje to co wszyscy razem wylosowali)
            $prize = 0;
            for ($p = 0; $p < $num_players; $p++) {
                $prize += $player_total[$p];
            }

            if ($winner_idx === 0) {
                $_SESSION['user_balance'] += $prize;
                $balance += $prize;
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
   BUDOWANIE TAŚM dla animacji
   Schemat: [BUFOR_GÓRA 10 pól] + [18 losowych] + [WYNIK] + [BUFOR_DÓŁ 5 pól]
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
    // Taśmy dogrywkowe
    foreach ($tiebreak_spins as $round => $players_tb) {
        foreach ($players_tb as $p => $tb) {
            $tiebreak_tapes[$round][$p] = buildTape(
                $MULTIPLIERS, $TOTAL_WEIGHT,
                $tb['mult'], $TAPE_PRE, $TAPE_BOTTOM
            );
        }
    }
}

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
<!-- CASE BATTLE — fragment do osadzenia; <style> można przenieść do .css -->

<style>
/* ════════ PRZYCISKI OPCJI ════════ */
.cb-config { display:grid; gap:14px; margin-bottom:16px; }
.cb-config-row { display:flex; flex-direction:column; gap:6px; }
.cb-options { display:flex; flex-wrap:wrap; gap:8px; }
.cb-opt-btn {
    padding:8px 14px; border-radius:7px; border:2px solid #444;
    background:#2a2a2a; color:#ccc; font-size:.85rem; font-weight:600;
    cursor:pointer; transition:border-color .15s, background .15s, color .15s;
}
.cb-opt-btn:hover  { background:#333; border-color:#666; color:#fff; }
.cb-opt-btn.cb-active {
    border-color:var(--accent-color,#c9a84c);
    background:#3a2f10; color:var(--accent-color,#c9a84c);
}
.cb-mode-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.cb-mode-btn {
    padding:10px; border-radius:8px; border:2px solid #444;
    background:#2a2a2a; color:#ccc; font-size:.82rem; font-weight:600;
    cursor:pointer; text-align:left; line-height:1.3;
    transition:border-color .15s, background .15s, color .15s;
}
.cb-mode-btn:hover { background:#333; border-color:#666; }
.cb-mode-btn.cb-active {
    border-color:var(--accent-color,#c9a84c);
    background:#3a2f10; color:#fff;
}
.cb-mode-btn small { display:block; font-weight:400; opacity:.6; font-size:.73rem; margin-top:3px; }

.cb-cost-bar {
    background:#1e1e1e; border:1px solid #3a3a3a; border-radius:8px;
    padding:10px 14px; font-size:.9rem; color:#ccc;
    display:flex; justify-content:space-between; align-items:center;
    flex-wrap:wrap; gap:6px;
}
.cb-cost-bar strong { color:var(--accent-color,#c9a84c); font-size:1.05rem; }

/* ════════ ARENA ════════ */
.cb-arena {
    display:flex; gap:10px; margin:18px 0;
    align-items:flex-start; overflow-x:auto;
}
.cb-player-col {
    flex:1; min-width:0; display:flex;
    flex-direction:column; gap:8px;
}
.cb-player-label {
    text-align:center; font-size:.78rem; font-weight:700;
    color:#777; text-transform:uppercase; letter-spacing:.06em;
}
.cb-player-label--you { color:var(--accent-color,#c9a84c); }

/* Pasek spinu */
.cb-spin-wrap { position:relative; padding-top:22px; }
.cb-spin-arrow {
    position:absolute; top:0; left:50%; transform:translateX(-50%);
    font-size:16px; color:var(--accent-color,#c9a84c); z-index:20;
    filter:drop-shadow(0 0 3px var(--accent-color,#c9a84c));
}
.cb-spin-outer {
    width:100%; height:200px; overflow:hidden;
    border-radius:8px; border:2px solid var(--border-color,#3a3a2a);
    background:#0d0d0d; position:relative;
    box-shadow:0 4px 16px rgba(0,0,0,.6) inset;
}
/* Złota linia środkowa */
.cb-spin-outer::after {
    content:''; position:absolute; left:0; right:0;
    top:50%; transform:translateY(-50%);
    height:3px; background:var(--accent-color,#c9a84c);
    box-shadow:0 0 10px var(--accent-color,#c9a84c), 0 0 20px rgba(201,168,76,.3);
    z-index:9; pointer-events:none;
}
/* Zaciemnienie góry/dołu */
.cb-spin-outer::before {
    content:''; position:absolute; inset:0; z-index:8;
    background:linear-gradient(to bottom,rgba(0,0,0,.65) 0%,transparent 28%,transparent 72%,rgba(0,0,0,.65) 100%);
    pointer-events:none; border-radius:6px;
}
.cb-reel { display:flex; flex-direction:column; will-change:transform; }
.cb-cell {
    width:100%; height:50px; flex-shrink:0;
    display:flex; flex-direction:column;
    align-items:center; justify-content:center;
    border-bottom:1px solid rgba(255,255,255,.06);
    font-weight:800; line-height:1.1; user-select:none;
}
.cb-cell-mult { font-size:.95rem; }
.cb-cell-val  { font-size:.7rem; opacity:.7; font-weight:500; }
.cb-cell--winner {
    outline:3px solid var(--accent-color,#c9a84c); outline-offset:-3px;
    animation:cb-pulse .65s ease-in-out 4; position:relative; z-index:5;
}
@keyframes cb-pulse {
    0%,100% { filter:brightness(1); }
    50%      { filter:brightness(1.75); }
}

.cb-spin-result {
    text-align:center; font-size:.82rem; font-weight:700;
    margin-top:4px; min-height:18px; color:#888; transition:color .3s;
}
.cb-spin-label {
    text-align:center; font-size:.7rem; color:#555;
    font-weight:600; letter-spacing:.04em; margin-top:2px;
}

/* Suma gracza */
.cb-player-total {
    text-align:center; font-size:.95rem; font-weight:700;
    padding:6px 4px; border-radius:6px; background:#1a1a1a;
    border:1px solid #333; color:#ccc; min-height:34px;
    display:flex; align-items:center; justify-content:center;
    gap:6px; transition:all .4s;
}
.cb-player-total--winner {
    border-color:var(--accent-color,#c9a84c);
    color:var(--accent-color,#c9a84c); background:#2a200a;
    box-shadow:0 0 14px rgba(201,168,76,.25);
}
.cb-player-total--loser { opacity:.45; }

/* Dogrywka */
.cb-tiebreak-section {
    margin:10px 0; padding:10px;
    border:1px solid #4a3a10; border-radius:8px; background:#1e1a0a;
}
.cb-tiebreak-title {
    text-align:center; font-size:.8rem; color:#c9a84c; font-weight:700;
    margin-bottom:8px; text-transform:uppercase; letter-spacing:.06em;
}
.cb-tiebreak-arena { display:flex; gap:8px; }
.cb-tiebreak-col { flex:1; min-width:0; }

/* Baner wyniku */
.cb-result-banner {
    text-align:center; padding:12px; border-radius:10px;
    font-size:1.1rem; font-weight:700; margin:10px 0;
    display:none; opacity:0; transition:opacity .5s;
}
.cb-result-banner.cb-show { display:block; }
.cb-result-banner--win  { background:#1a3a1a; border:2px solid #27ae60; color:#2ecc71; }
.cb-result-banner--lose { background:#3a1a1a; border:2px solid #c0392b; color:#e74c3c; }

/* Etykieta rundy spinu */
.cb-round-label {
    text-align:center; font-size:.68rem; color:#555;
    font-weight:700; letter-spacing:.05em;
    text-transform:uppercase; margin-bottom:2px;
}
</style>

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
                            data-val="<?= $p ?>"><?= $p ?> 🪙</button>
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
                    (pula: <span id="cb-pot-display"><?= $prefill_price * $prefill_count * $prefill_players ?></span> 🪙)
                </span>
            </div>
        </div>

        <hr class="coin-game__divider">

        <!-- ══ ARENA GŁÓWNYCH SPINÓW ══ -->
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
                                    // Placeholder
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

        <!-- ══ SEKCJA DOGRYWKI (ukryta, pokazuje się jeśli był remis) ══ -->
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

        <!-- Baner wyniku (ukryty do końca animacji) -->
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

<script>
(function () {

    const PLAYED      = <?= $played ? 'true' : 'false' ?>;
    const NUM_PLAYERS = <?= (int)$prefill_players ?>;
    const NUM_SPINS   = <?= (int)$prefill_count ?>;
    const SPIN_PRICE  = <?= (int)$prefill_price ?>;
    const WINNER_IDX  = <?= $winner_idx !== null ? (int)$winner_idx : 'null' ?>;

    // Pozycje wyników w taśmach
    const WINNER_POS = <?= $played ? json_encode(array_map(function($p) use ($prefill_count, $tapes_data) {
        return array_map(fn($s) => $tapes_data[$p][$s]['winner_pos'] ?? 0, range(0, $prefill_count-1));
    }, range(0, $prefill_players-1))) : '[]' ?>;

    // Dane dogrywki
    const TB_ROUNDS = <?= json_encode(array_keys($tiebreak_spins)) ?>;
    const TB_POS    = <?= $played && count($tiebreak_spins) > 0 ? json_encode(
        array_map(function($round) use ($prefill_players, $tiebreak_tapes) {
            return array_map(fn($p) => $tiebreak_tapes[$round][$p]['winner_pos'] ?? 0, range(0, $prefill_players-1));
        }, array_keys($tiebreak_spins))
    ) : '{}' ?>;
    const TB_VALS = <?= $played && count($tiebreak_spins) > 0 ? json_encode(
        array_map(function($round) use ($prefill_players, $tiebreak_spins) {
            return array_map(fn($p) => $tiebreak_spins[$round][$p]['val'], range(0, $prefill_players-1));
        }, array_keys($tiebreak_spins))
    ) : '{}' ?>;

    // Sumy graczy
    const PLAYER_TOTALS = <?= $played ? json_encode(array_values($player_total)) : '[]' ?>;
    const PLAYER_WINS   = <?= $played ? json_encode(array_values($player_wins))  : '[]' ?>;
    const MODE          = <?= json_encode($prefill_mode) ?>;
    const SPIN_RESULTS  = <?= $played ? json_encode(array_map(fn($p) =>
        array_map(fn($s) => $player_spins[$p][$s], range(0, $prefill_count-1)),
        range(0, $prefill_players-1)
    )) : '[]' ?>;

    const CELL_H  = 50;
    const OUTER_H = 200;
    const CENTER  = Math.floor(OUTER_H / 2) - Math.floor(CELL_H / 2);
    const ANIM_DURATION = 5500; // ms

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
            void reel.offsetWidth; // reflow
            reel.style.transition = `transform ${ANIM_DURATION}ms cubic-bezier(0.05,0.0,0.15,1.0)`;
            reel.style.transform  = `translateY(${targetY}px)`;
            reel.addEventListener('transitionend', function onEnd() {
                reel.removeEventListener('transitionend', onEnd);
                resolve();
            });
        });
    }

    /* ── Sekwencja: spiny rundami (runda 1 wszyscy, runda 2 wszyscy...) ── */
    async function runAllSpins() {

        for (let s = 0; s < NUM_SPINS; s++) {
            // Uruchom wszystkich graczy równolegle w tej rundzie
            const promises = [];
            for (let p = 0; p < NUM_PLAYERS; p++) {
                promises.push(animateReel(`cb-reel-${p}-${s}`, WINNER_POS[p][s]));
            }
            await Promise.all(promises);

            // Podświetl zwycięzcę tej rundy i pokaż wartość pod paskiem
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

            // Krótka pauza między rundami
            if (s < NUM_SPINS - 1) await sleep(600);
        }

        // Pokaż sumy
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

        // Dogrywka (jeśli była)
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

                // Podświetl
                for (let p = 0; p < NUM_PLAYERS; p++) {
                    const tw = document.getElementById(`cb-tbwinner-${round}-${p}`);
                    if (tw) tw.classList.add('cb-cell--winner');
                }
                if (ri < TB_ROUNDS.length - 1) await sleep(600);
            }
            await sleep(400);
        }

        // Podświetl zwycięzcę i przegranego
        for (let p = 0; p < NUM_PLAYERS; p++) {
            const totalEl = document.getElementById(`cb-total-${p}`);
            if (!totalEl) continue;
            if (p === WINNER_IDX) {
                totalEl.classList.add('cb-player-total--winner');
            } else {
                totalEl.classList.add('cb-player-total--loser');
            }
        }

        // Pokaż baner
        if (banner) {
            banner.style.display = 'block';
            await sleep(50);
            banner.style.transition = 'opacity .6s';
            banner.style.opacity    = '1';
        }

        // Zaktualizuj saldo w headerze
        const balance = <?= (int) $balance ?>;
        if (typeof updateHeaderBalance === 'function') updateHeaderBalance(balance);
    }

    function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

    setTimeout(runAllSpins, 100);

})();
</script>