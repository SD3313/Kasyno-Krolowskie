<?php
require_once __DIR__ . '/../init_session.php';
$balance = (int) $_SESSION['user_balance'];

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
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?bet=' . $quick_val
        . (isset($_POST['bet_type'])  ? '&bet_type='  . urlencode($_POST['bet_type'])  : '')
        . (isset($_POST['bet_value']) ? '&bet_value=' . urlencode($_POST['bet_value']) : ''));
    exit;
}

$prefill_bet      = isset($_GET['bet'])       ? (int)    $_GET['bet']       : 50;
$prefill_bet_type = isset($_GET['bet_type'])  ? (string) $_GET['bet_type']  : 'color';
$prefill_bet_val  = isset($_GET['bet_value']) ? (string) $_GET['bet_value'] : 'red';

// --- Reset salda ---
if (isset($_POST['reset'])) {
    $_SESSION['user_balance'] = 1000;
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Kolejność europejska kołowa
$wheel_order = [0,32,15,19,4,21,2,25,17,34,6,27,13,36,11,30,8,23,10,5,24,16,33,1,20,14,31,9,22,18,29,7,28,12,35,3,26];
$wheel_size  = count($wheel_order); // 37

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
            } else {
                $_SESSION['user_balance'] -= $bet;
                $message = 'Przegrałeś ' . $bet . ' żetonów.';
            }
            $balance          = $_SESSION['user_balance'];
            $prefill_bet      = $bet;
            $prefill_bet_type = $bet_type;
            $prefill_bet_val  = $bet_value;
            $result           = true;
        }
    }
}

/*
 * Budujemy taśmę tak, żeby wynik zawsze lądował na środku
 * i po bokach były widoczne sąsiednie komórki.
 *
 * Schemat: [LEWY_BUFOR] [5 obrotów kołem] [WYNIK] [PRAWY_BUFOR]
 *
 * JS przesuwa taśmę tak, żeby komórka WYNIK trafiła dokładnie
 * pod złotą linię w centrum paska.
 * Lewy bufor = tyle komórek, żeby pasek był widocznie wypełniony
 * przed startem (startujemy od lewej krawędzi).
 */
$REPEATS     = 5;   // ile pełnych obrotów przed wynikiem
$SIDE_EXTRA  = 8;   // dodatkowe komórki po prawej od wyniku (widoczne sąsiedzi)

// Indeks wylosowanego numeru w tablicy wheel_order
$result_wheel_idx = ($result_num !== null) ? (int) array_search($result_num, $wheel_order) : 0;

// Buduję listę komórek PHP (tylko numery) — JS zna kolory
$tape = [];
// Lewy bufor (10 komórek widocznych na starcie + zapas)
for ($i = 0; $i < 14; $i++) {
    $tape[] = $wheel_order[$i % $wheel_size];
}
// N pełnych obrotów
for ($r = 0; $r < $REPEATS; $r++) {
    foreach ($wheel_order as $n) {
        $tape[] = $n;
    }
}
// Dociągnij do wylosowanego numeru (włącznie)
for ($i = 0; $i <= $result_wheel_idx; $i++) {
    $tape[] = $wheel_order[$i];
}
// Prawy bufor (sąsiedzi po prawej)
for ($i = 1; $i <= $SIDE_EXTRA; $i++) {
    $tape[] = $wheel_order[($result_wheel_idx + $i) % $wheel_size];
}

// Indeks komórki wyniku w tablicy $tape (ostatnia przed prawym buforem)
$winner_cell_idx = count($tape) - $SIDE_EXTRA - 1;
?>
<!-- RULETKA — fragment do osadzenia; <style> można przenieść do .css -->

<style>
/* ════════════════════════════════
   PASEK RULETKI
   ════════════════════════════════ */
.rl-wrap {
    margin: 20px 0 32px;
    position: relative;
    padding-top: 28px; /* miejsce na strzałkę */
}

/* Strzałka wskaźnik */
.rl-arrow {
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    font-size: 22px;
    color: var(--accent-color, #c9a84c);
    line-height: 1;
    z-index: 20;
    filter: drop-shadow(0 0 4px var(--accent-color, #c9a84c));
}

.rl-strip-outer {
    width: 100%;
    overflow: hidden;
    border-radius: 10px;
    border: 3px solid var(--border-color, #5a4a2a);
    position: relative;
    height: 110px;       /* większy pasek */
    background: #0d0d0d;
    box-shadow: 0 4px 24px rgba(0,0,0,.7) inset,
                0 0 0 1px rgba(255,255,255,.04);
}

/* Złota linia środkowa */
.rl-strip-outer::after {
    content: '';
    position: absolute;
    top: 0; bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 3px;
    background: var(--accent-color, #c9a84c);
    z-index: 9;
    box-shadow: 0 0 10px var(--accent-color, #c9a84c),
                0 0 20px rgba(201,168,76,.4);
    pointer-events: none;
}

/* Zaciemnienie brzegów (efekt głębi) */
.rl-strip-outer::before {
    content: '';
    position: absolute;
    inset: 0;
    z-index: 8;
    background: linear-gradient(
        to right,
        rgba(0,0,0,.55) 0%,
        transparent 22%,
        transparent 78%,
        rgba(0,0,0,.55) 100%
    );
    pointer-events: none;
    border-radius: 8px;
}

/* Taśma */
.rl-strip {
    display: flex;
    height: 100%;
    will-change: transform;
    /* Startujemy przesunięci tak, żeby bufor wypełniał widok */
    transform: translateX(0);
}

/* Komórka */
.rl-cell {
    min-width: 90px;   
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.7rem;
    font-weight: 800;
    color: #fff;
    border-right: 2px solid rgba(0,0,0,.3);
    user-select: none;
    flex-shrink: 0;
    text-shadow: 0 2px 4px rgba(0,0,0,.8);
    transition: filter .2s;
}
.rl-cell--red   { background: linear-gradient(160deg, #d63031, #a52020); }
.rl-cell--black { background: linear-gradient(160deg, #2d2d2d, #111); }
.rl-cell--green { background: linear-gradient(160deg, #27ae60, #1a6e3c); }

/* Podświetlenie zwycięzcy */
.rl-cell--winner {
    outline: 4px solid var(--accent-color, #c9a84c);
    outline-offset: -4px;
    animation: rl-pulse .7s ease-in-out 4;
    z-index: 5;
    position: relative;
}
@keyframes rl-pulse {
    0%,100% { filter: brightness(1); }
    50%      { filter: brightness(1.6); }
}

/* ════════════════════════════════
   PRZYCISKI ZAKŁADÓW
   ════════════════════════════════ */
.rl-bets {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin: 14px 0 6px;
}
.rl-bet-btn {
    padding: 11px 8px;
    border-radius: 8px;
    border: 2px solid transparent;
    cursor: pointer;
    font-size: .87rem;
    font-weight: 600;
    color: #fff;
    background: #2a2a2a;
    transition: border-color .15s, transform .1s, background .15s;
    text-align: center;
    line-height: 1.35;
}
.rl-bet-btn:hover  { background: #363636; transform: translateY(-1px); }
.rl-bet-btn:active { transform: translateY(0); }
.rl-bet-btn.rl-active {
    border-color: var(--accent-color, #c9a84c);
    background: #3a2f10;
}

.rl-bet-btn--red   { border-left: 5px solid #c0392b; }
.rl-bet-btn--black { border-left: 5px solid #777; }
.rl-bet-btn--green { border-left: 5px solid #27ae60; }
.rl-bet-btn--even,
.rl-bet-btn--odd   { border-left: 5px solid #5b8dee; }
.rl-bet-btn--low,
.rl-bet-btn--high  { border-left: 5px solid #9b59b6; }
.rl-bet-btn--doz   { border-left: 5px solid #e67e22; }
.rl-bet-btn--num   { border-left: 5px solid var(--accent-color, #c9a84c); grid-column: 1/-1; }

.rl-num-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.rl-num-input {
    width: 72px;
    padding: 5px 8px;
    border-radius: 6px;
    border: 2px solid #444;
    background: #1a1a1a;
    color: #fff;
    font-size: 1rem;
    font-weight: 700;
    text-align: center;
}
.rl-num-input:focus { border-color: var(--accent-color, #c9a84c); outline: none; }
</style>

<div class="coin-game">

    <p class="coin-game__title">Ruletka</p>
    <p class="coin-game__balance">
        Saldo: <strong><?= (int) $balance ?> żetonów</strong>
    </p>

    <!-- ══════════════ PASEK RULETKI ══════════════ -->
    <div class="rl-wrap">
        <div class="rl-arrow">▼</div>
        <div class="rl-strip-outer" id="rl-outer">
            <div class="rl-strip" id="rl-strip">
                <?php foreach ($tape as $idx => $n):
                    $col = $roulette_colors[$n];
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

    <form method="POST" action="" id="rl-form">

        <!-- ══ ZAKŁADY ══ -->
        <span class="coin-game__label">Rodzaj zakładu</span>
        <div class="rl-bets" id="rl-bets">

            <button type="button" class="rl-bet-btn rl-bet-btn--red"
                    data-type="color" data-val="red"
                    <?= ($prefill_bet_type==='color'&&$prefill_bet_val==='red') ? 'data-selected="1"' : '' ?>>
                🔴 Czerwone<br><small style="font-weight:400;opacity:.7">wypłata 1:1</small>
            </button>
            <button type="button" class="rl-bet-btn rl-bet-btn--black"
                    data-type="color" data-val="black"
                    <?= ($prefill_bet_type==='color'&&$prefill_bet_val==='black') ? 'data-selected="1"' : '' ?>>
                ⚫ Czarne<br><small style="font-weight:400;opacity:.7">wypłata 1:1</small>
            </button>

            <button type="button" class="rl-bet-btn rl-bet-btn--even"
                    data-type="parity" data-val="even"
                    <?= ($prefill_bet_type==='parity'&&$prefill_bet_val==='even') ? 'data-selected="1"' : '' ?>>
                ↔ Parzyste<br><small style="font-weight:400;opacity:.7">wypłata 1:1</small>
            </button>
            <button type="button" class="rl-bet-btn rl-bet-btn--odd"
                    data-type="parity" data-val="odd"
                    <?= ($prefill_bet_type==='parity'&&$prefill_bet_val==='odd') ? 'data-selected="1"' : '' ?>>
                ↕ Nieparzyste<br><small style="font-weight:400;opacity:.7">wypłata 1:1</small>
            </button>

            <button type="button" class="rl-bet-btn rl-bet-btn--low"
                    data-type="half" data-val="low"
                    <?= ($prefill_bet_type==='half'&&$prefill_bet_val==='low') ? 'data-selected="1"' : '' ?>>
                ⬇ 1–18<br><small style="font-weight:400;opacity:.7">wypłata 1:1</small>
            </button>
            <button type="button" class="rl-bet-btn rl-bet-btn--high"
                    data-type="half" data-val="high"
                    <?= ($prefill_bet_type==='half'&&$prefill_bet_val==='high') ? 'data-selected="1"' : '' ?>>
                ⬆ 19–36<br><small style="font-weight:400;opacity:.7">wypłata 1:1</small>
            </button>

            <button type="button" class="rl-bet-btn rl-bet-btn--doz"
                    data-type="dozen" data-val="1st"
                    <?= ($prefill_bet_type==='dozen'&&$prefill_bet_val==='1st') ? 'data-selected="1"' : '' ?>>
                📌 1–12<br><small style="font-weight:400;opacity:.7">wypłata 2:1</small>
            </button>
            <button type="button" class="rl-bet-btn rl-bet-btn--doz"
                    data-type="dozen" data-val="2nd"
                    <?= ($prefill_bet_type==='dozen'&&$prefill_bet_val==='2nd') ? 'data-selected="1"' : '' ?>>
                📌 13–24<br><small style="font-weight:400;opacity:.7">wypłata 2:1</small>
            </button>
            <button type="button" class="rl-bet-btn rl-bet-btn--doz"
                    data-type="dozen" data-val="3rd"
                    <?= ($prefill_bet_type==='dozen'&&$prefill_bet_val==='3rd') ? 'data-selected="1"' : '' ?>>
                📌 25–36<br><small style="font-weight:400;opacity:.7">wypłata 2:1</small>
            </button>

            <button type="button" class="rl-bet-btn rl-bet-btn--green"
                    data-type="color" data-val="green"
                    <?= ($prefill_bet_type==='color'&&$prefill_bet_val==='green') ? 'data-selected="1"' : '' ?>>
                🟢 Zero (0)<br><small style="font-weight:400;opacity:.7">wypłata 17:1</small>
            </button>

            <button type="button" class="rl-bet-btn rl-bet-btn--num"
                    data-type="number" data-val=""
                    id="rl-num-btn"
                    <?= ($prefill_bet_type==='number') ? 'data-selected="1"' : '' ?>>
                <div class="rl-num-row">
                    <span>🎯 Numer:</span>
                    <input type="number" class="rl-num-input" id="rl-num-val"
                           min="0" max="36" placeholder="0–36"
                           value="<?= ($prefill_bet_type==='number') ? (int)$prefill_bet_val : '' ?>"
                           onclick="event.stopPropagation();">
                    <small style="opacity:.65;font-weight:400;">wypłata 35:1</small>
                </div>
            </button>
        </div>

        <input type="hidden" name="bet_type"  id="rl-hidden-type"  value="<?= htmlspecialchars($prefill_bet_type) ?>">
        <input type="hidden" name="bet_value" id="rl-hidden-val"   value="<?= htmlspecialchars($prefill_bet_val) ?>">

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

    /* ── Zaznaczanie przycisków zakładu ── */
    const hiddenType = document.getElementById('rl-hidden-type');
    const hiddenVal  = document.getElementById('rl-hidden-val');
    const numInput   = document.getElementById('rl-num-val');
    const numBtn     = document.getElementById('rl-num-btn');

    document.querySelectorAll('#rl-bets .rl-bet-btn').forEach(btn => {
        if (btn.dataset.selected === '1') btn.classList.add('rl-active');
        btn.addEventListener('click', function () {
            document.querySelectorAll('#rl-bets .rl-bet-btn').forEach(b => b.classList.remove('rl-active'));
            this.classList.add('rl-active');
            hiddenType.value = this.dataset.type;
            hiddenVal.value  = (this.dataset.type === 'number')
                ? (numInput ? numInput.value : '')
                : this.dataset.val;
        });
    });

    if (numInput) {
        numInput.addEventListener('input', function () {
            hiddenVal.value  = this.value;
            hiddenType.value = 'number';
            document.querySelectorAll('#rl-bets .rl-bet-btn').forEach(b => b.classList.remove('rl-active'));
            numBtn.classList.add('rl-active');
        });
    }

    /* ── Animacja paska ── */
    const strip      = document.getElementById('rl-strip');
    const outer      = document.getElementById('rl-outer');
    const PLAYED     = <?= $result !== null ? 'true' : 'false' ?>;
    const WINNER_IDX = <?= (int) $winner_cell_idx ?>;
    const CELL_W     = 92; // 90px + 2px border

    if (!strip || !outer) return;

    if (!PLAYED) {
        // Przed grą: wyśrodkuj taśmę na ~7. komórce (środek lewego bufora)
        // żeby pasek był kolorowy, nie pusty
        const startX = -(7 * CELL_W) + Math.floor(outer.offsetWidth / 2) - Math.floor(CELL_W / 2);
        strip.style.transition = 'none';
        strip.style.transform  = `translateX(${startX}px)`;
        return;
    }

    // Pozycja startowa: taśma ustawiona na początku (komórka 0 na środku)
    const outerW    = outer.offsetWidth;
    const centerOff = Math.floor(outerW / 2) - Math.floor(CELL_W / 2);

    // Komórka wyniku ma wylądować na środku paska
    const startX  = centerOff;                           // startujemy od środka (komórka 0 widoczna)
    const targetX = -(WINNER_IDX * CELL_W) + centerOff; // wynik na środku

    // Ustaw pozycję startową bez animacji
    strip.style.transition = 'none';
    strip.style.transform  = `translateX(${startX}px)`;

    // Wymuszenie reflow, potem odpal animację
    void strip.offsetWidth;

    strip.style.transition = 'transform 6s cubic-bezier(0.05, 0.0, 0.15, 1.0)';
    strip.style.transform  = `translateX(${targetX}px)`;

    // Po zatrzymaniu — podświetl zwycięzcę
    strip.addEventListener('transitionend', function onEnd() {
        strip.removeEventListener('transitionend', onEnd);
        const winner = document.getElementById('rl-winner');
        if (winner) winner.classList.add('rl-cell--winner');
    });

    /* ── Synchronizacja salda ── */
    const balance = <?= (int) $balance ?>;
    if (typeof updateHeaderBalance === 'function') updateHeaderBalance(balance);
    document.getElementById('rl-form')?.addEventListener('submit', () => {
        setTimeout(() => {
            if (typeof updateHeaderBalance === 'function') updateHeaderBalance(balance);
        }, 100);
    });

})();
</script>