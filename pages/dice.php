<?php
if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login');
    exit;
}


$balance = (int) $_SESSION['user_balance'];

$result    = null;
$won       = null;
$message   = '';
$error     = '';

// --- Szybkie zakłady ---
if (isset($_POST['quick_bet'])) {
    $fraction  = (float) $_POST['quick_bet'];
    $quick_val = max(1, (int) floor($_SESSION['user_balance'] * $fraction));
    $choice    = isset($_POST['choice']) ? $_POST['choice'] : 'under';
    $threshold = isset($_POST['threshold']) ? $_POST['threshold'] : '0.50';
    $_SESSION['bet'] = $quick_val;
    $_SESSION['choice'] = $choice;
    $_SESSION['threshold'] = $threshold;}

// --- Pobierz wartości z GET ---
$prefill_bet       = isset($_SESSION['bet'])       ? (int)    $_SESSION['bet']       : 50;
$prefill_choice    = isset($_SESSION['choice'])    ? (string) $_SESSION['choice']    : '';
$prefill_threshold = isset($_SESSION['threshold']) ? (float)  $_SESSION['threshold'] : 0.50;
$prefill_threshold = max(0.01, min(0.99, $prefill_threshold));


// --- Główna gra ---
if (isset($_POST['play'])) {
    $bet       = (int)   ($_POST['bet']       ?? 0);
    $choice    = (string)($_POST['choice']    ?? '');
    $threshold = (float) ($_POST['threshold'] ?? 0.50);
    $threshold = max(0.01, min(0.99, $threshold));

    if (!in_array($choice, ['under', 'over'], true)) {
        $error = 'Wybierz Under lub Over.';
    } elseif ($bet < 1 || $bet > $balance) {
        $error = 'Zakład musi być między 1 a ' . $balance . ' żetonami.';
    } else {
        // Losujemy liczbę z zakresu 0.00–1.00 (2 miejsca po przecinku)
        $_SESSION['result'] = rand(0, 100) / 100;
        $won    = ($choice === 'under') ? ($_SESSION['result'] < $threshold) : ($_SESSION['result'] > $threshold);

        // Oblicz mnożnik na podstawie prawdopodobieństwa (z małym house edge 2%)
        $house_edge = 0.02;
        if ($choice === 'under') {
            $prob = $threshold;
        } else {
            $prob = 1 - $threshold;
        }
        $prob      = max(0.01, $prob);
        $multiplier = round((1 - $house_edge) / $prob, 2);

        if ($won) {
            $gain = (int) floor($bet * ($multiplier - 1));
            $_SESSION['user_balance'] += $gain;
            $message = 'Wygrałeś ' . $gain . ' żetonów! (×' . $multiplier . ')';
        } else {
            $_SESSION['user_balance'] -= $bet;
            $message = 'Przegrałeś ' . $bet . ' żetonów.';
        }

        $balance           = $_SESSION['user_balance'];
        $prefill_bet       = $bet;
        $prefill_choice    = $choice;
        $prefill_threshold = $threshold;
    }
}

// --- Pomocnicze wartości do szablonu ---
$msg_class = '';
if ($won === true)  $msg_class = ' sg__message--win';
if ($won === false) $msg_class = ' sg__message--lose';
if ($error)         $msg_class = ' sg__message--error';

// Oblicz mnożnik do wyświetlenia w UI (nawet przed grą)
$display_threshold = $prefill_threshold > 0 ? $prefill_threshold : 0.50;
$display_choice    = $prefill_choice ?: 'under';
if ($display_choice === 'under') {
    $disp_prob = $display_threshold;
} else {
    $disp_prob = 1 - $display_threshold;
}
$disp_prob       = max(0.01, $disp_prob);
$display_mult    = round((1 - 0.02) / $disp_prob, 2);
$display_win_pct = round($disp_prob * 100, 1);
?>
<div class="sg">

    <p class="sg__title">Slide</p>
    <p class="sg__balance">
        Saldo: <strong><?= (int) $balance ?> żetonów</strong>
    </p>

    <!-- Wizualizacja wynikowego paska -->
    <div class="sg__result-wrap">
        <div class="sg__result-track" id="sg-track">
            <div class="sg__result-fill-under" id="sg-fill-under"></div>
            <div class="sg__result-fill-over"  id="sg-fill-over"></div>
            <div class="sg__threshold-line"    id="sg-thresh-line"></div>
            <div class="sg__result-dot <?= isset($_SESSION['result']) ? 'sg__result-dot--visible' : '' ?> <?= $won === true ? 'sg__result-dot--win' : ($won === false ? 'sg__result-dot--lose' : '') ?>"
                 id="sg-dot"></div>
        </div>
        <div class="sg__result-labels">
            <span>0.00</span>
            <span>0.25</span>
            <span>0.50</span>
            <span>0.75</span>
            <span>1.00</span>
        </div>
        <div class="sg__rolled-value <?= $won === true ? 'sg__rolled-value--win' : ($won === false ? 'sg__rolled-value--lose' : '') ?>"
             id="sg-rolled">
            <?= isset($_SESSION['result']) ? number_format($_SESSION['result'], 2) : '—' ?>
        </div>
    </div>

    <hr class="sg__divider">

    <form method="POST" action="">

        <!-- Ukryte pole threshold — wypełniane przez JS -->
        <input type="hidden" name="threshold" id="sg-threshold-input"
               value="<?= number_format($prefill_threshold, 2) ?>">

        <!-- Slider progu -->
        <span class="sg__label">Próg (threshold)</span>
        <div class="sg__slider-wrap">
            <div class="sg__slider-row">
                <input type="range"
                       class="sg__slider"
                       id="sg-slider"
                       min="1" max="99" step="1"
                       value="<?= (int) round($prefill_threshold * 100) ?>">
                <span class="sg__slider-val" id="sg-slider-val">
                    <?= number_format($prefill_threshold, 2) ?>
                </span>
            </div>
        </div>

        <!-- Statsy -->
        <div class="sg__stats">
            <div class="sg__stat">
                <span class="sg__stat-label">Szansa wygranej</span>
                <span class="sg__stat-value" id="sg-win-pct"><?= $display_win_pct ?>%</span>
            </div>
            <div class="sg__stat">
                <span class="sg__stat-label">Mnożnik</span>
                <span class="sg__stat-value" id="sg-multiplier">×<?= number_format($display_mult, 2) ?></span>
            </div>
            <div class="sg__stat">
                <span class="sg__stat-label">Potencjalna wygrana</span>
                <span class="sg__stat-value" id="sg-potential">+<?= (int) floor($prefill_bet * ($display_mult - 1)) ?></span>
            </div>
        </div>

        <!-- Wybór Under / Over -->
        <span class="sg__label">Twój wybór</span>
        <div class="sg__choice">
            <input type="radio" name="choice" id="sg_under" value="under"
                <?= ($prefill_choice === 'under' || $prefill_choice === '') ? 'checked' : '' ?>>
            <label for="sg_under">📉 Under</label>

            <input type="radio" name="choice" id="sg_over" value="over"
                <?= ($prefill_choice === 'over') ? 'checked' : '' ?>>
            <label for="sg_over">📈 Over</label>
        </div>

        <!-- Zakład -->
        <label class="sg__label" for="sg_bet">Wysokość zakładu</label>
        <div class="sg__bet-wrap">
            <input type="number"
                   class="sg__bet-input"
                   name="bet"
                   id="sg_bet"
                   min="1"
                   max="<?= (int) $balance ?>"
                   value="<?= (int) $prefill_bet ?>">
        </div>

        <!-- Szybkie zakłady -->
        <div class="sg__quick">
            <?php
            $fractions = ['10%' => 0.1, '25%' => 0.25, '50%' => 0.5, 'MAX' => 1.0];
            foreach ($fractions as $label => $frac):
            ?>
            <button type="submit" name="quick_bet" value="<?= $frac ?>"
                    class="sg__quick-btn">
                <?= $label ?>
            </button>
            <?php endforeach; ?>
        </div>

        <hr class="sg__divider">

        <!-- Rzut -->
        <button type="submit" name="play" value="1" class="sg__submit">
            🎲 Zagraj
        </button>

        <!-- Komunikat -->
        <?php
        if ($error):
            $inline_class = ' sg__message--error';
            $inline_text  = $error;
        elseif (isset($_SESSION['result'])):
            $choice_label = ($prefill_choice === 'over') ? 'Over' : 'Under';
            $result_label = number_format($_SESSION['result'], 2);
            $inline_class = $msg_class;
            $inline_text  = 'Wylosowano: ' . $result_label . ' — ' . $message;
        else:
            $inline_class = '';
            $inline_text  = 'Ustaw próg, wybierz Under / Over i postaw zakład.';
        endif;
        ?>
        <div class="sg__message<?= $inline_class ?>">
            <?= htmlspecialchars($inline_text) ?>
        </div>

    </form>
    <a href="home" class="back-btn">← Wróć do gier</a>
</div>


<script>
(function () {
    const HOUSE_EDGE = 0.02;

    // ── Elementy DOM ──────────────────────────────────────────────
    const slider       = document.getElementById('sg-slider');
    const sliderVal    = document.getElementById('sg-slider-val');
    const threshInput  = document.getElementById('sg-threshold-input');
    const fillUnder    = document.getElementById('sg-fill-under');
    const fillOver     = document.getElementById('sg-fill-over');
    const threshLine   = document.getElementById('sg-thresh-line');
    const dot          = document.getElementById('sg-dot');
    const winPctEl     = document.getElementById('sg-win-pct');
    const multEl       = document.getElementById('sg-multiplier');
    const potentialEl  = document.getElementById('sg-potential');
    const betInput     = document.getElementById('sg_bet');
    const radioUnder   = document.getElementById('sg_under');
    const radioOver    = document.getElementById('sg_over');

    // ── Helpers ───────────────────────────────────────────────────
    function fmt2(n) { return n.toFixed(2); }

    function getChoice() {
        return radioOver && radioOver.checked ? 'over' : 'under';
    }

    function calcStats(threshold, choice) {
        const prob   = choice === 'under' ? threshold : (1 - threshold);
        const safeP  = Math.max(0.01, prob);
        const mult   = Math.round((1 - HOUSE_EDGE) / safeP * 100) / 100;
        const winPct = Math.round(safeP * 10000) / 100;
        return { prob: safeP, mult, winPct };
    }

    // ── Aktualizacja paska i statystyk ────────────────────────────
    function updateUI(threshPct) {
        const t      = threshPct / 100;          // 0..1
        const choice = getChoice();
        const { mult, winPct } = calcStats(t, choice);
        const bet    = parseInt(betInput ? betInput.value : 0) || 0;

        // Etykieta slidera
        sliderVal.textContent  = fmt2(t);
        threshInput.value      = fmt2(t);

        // Pasek — pod progiem: niebieski, nad progiem: żółty
        fillUnder.style.width  = (t * 100) + '%';
        fillOver.style.width   = ((1 - t) * 100) + '%';
        threshLine.style.left  = (t * 100) + '%';

        // Statsy
        winPctEl.textContent   = winPct + '%';
        multEl.textContent     = '×' + fmt2(mult);
        const gain             = Math.floor(bet * (mult - 1));
        potentialEl.textContent = '+' + gain;
    }

    // ── Pozycja kropki po załadowaniu (jeśli wynik już jest) ──────
    <?php if (isset($_SESSION['result'])): ?>
    (function () {
        const resultVal = <?= (float) $_SESSION['result'] ?>;
        dot.style.left  = (resultVal * 100) + '%';
        // małe opóźnienie — animacja pojawia się po chwili
        setTimeout(function () {
            dot.classList.add('sg__result-dot--visible');
        }, 100);
    })();
    <?php endif; ?>

    // ── Nasłuchy ──────────────────────────────────────────────────
    slider.addEventListener('input', function () {
        updateUI(parseInt(this.value));
    });

    if (radioUnder) radioUnder.addEventListener('change', function () {
        updateUI(parseInt(slider.value));
    });
    if (radioOver) radioOver.addEventListener('change', function () {
        updateUI(parseInt(slider.value));
    });

    if (betInput) betInput.addEventListener('input', function () {
        updateUI(parseInt(slider.value));
    });

    // Pierwsze uruchomienie
    updateUI(parseInt(slider.value));

    // ── Sync salda w nagłówku ─────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        const balance = <?= (int) $balance ?>;
        if (typeof updateHeaderBalance === 'function') {
            updateHeaderBalance(balance);
        }
    });
})();
</script>