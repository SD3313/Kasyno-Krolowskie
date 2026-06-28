<?php
if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login');
    exit;
}

$balance = (int) $_SESSION['user_balance'];

if (!isset($_SESSION['crash_history'])) {
    $_SESSION['crash_history'] = []; 
}
$history = $_SESSION['crash_history'];
?>

<div class="cg">
  <p class="cg-title">Crash</p>
  <p class="cg-sub">Saldo: <strong id="balTxt"><?= $balance ?> żetonów</strong></p>

  <div class="crash-canvas-wrap">
    <canvas id="crashCanvas"></canvas>
    <div class="mult-overlay">
      <div class="mult-val waiting" id="multVal">1.00×</div>
      <div class="mult-label" id="multLabel">oczekiwanie...</div>
    </div>
  </div>

  <div class="label">Wysokość zakładu</div>
  <div class="row">
    <div class="bet-wrap">
      <input type="number" class="bet-input" id="betInput" min="1" max="<?= $balance ?>" value="50">
    </div>
    <div class="cashout-wrap">
      <input type="number" class="cashout-input" id="cashoutAt" min="1.01" step="0.1" value="2.0" title="Auto cashout przy">
    </div>
  </div>

  <div class="quick">
    <button class="qbtn" data-frac="0.1">10%</button>
    <button class="qbtn" data-frac="0.25">25%</button>
    <button class="qbtn" data-frac="0.5">50%</button>
    <button class="qbtn" data-frac="1">MAX</button>
  </div>

  <hr class="divider">
  <button class="play-btn" id="playBtn">Postaw zakład</button>

  <div class="msg" id="msgBox">Postaw zakład i kliknij start.</div>

  <div class="history" id="histBox">
    <?php foreach ($history as $h): ?>
      <span class="hchip <?= $h['won'] ? 'w' : 'l' ?>"><?= number_format($h['mult'], 2) ?>×</span>
    <?php endforeach; ?>
  </div>
  
  <a href="home" class="back-btn">← Wróć do gier</a>
</div>

<script>
(function(){
  let balance = <?= $balance ?>;
  let history = <?= json_encode($history) ?>;
  
  let state = 'idle';
  let currentMult = 1.0;
  let crashAt = 1.0;
  let bet = 0;
  let autoCashout = 2.0;
  let cashedOut = false;
  let rafId = null;
  let startTime = 0;
  let points = [];
  let countdownVal = 3;
  let countdownInt = null;

  const canvas = document.getElementById('crashCanvas');
  const ctx = canvas.getContext('2d');
  const multVal = document.getElementById('multVal');
  const multLabel = document.getElementById('multLabel');
  const balTxt = document.getElementById('balTxt');
  const msgBox = document.getElementById('msgBox');
  const playBtn = document.getElementById('playBtn');
  const betInput = document.getElementById('betInput');
  const cashoutInput = document.getElementById('cashoutAt');
  const histBox = document.getElementById('histBox');

  function resize(){
    const wrap = canvas.parentElement;
    const dpr = devicePixelRatio || 1;
    canvas.width = wrap.clientWidth * dpr;
    canvas.height = wrap.clientHeight * dpr;
    ctx.setTransform(1,0,0,1,0,0);
    ctx.scale(dpr, dpr);
    draw();
  }


  function genCrash(){
    const r = Math.random();
    if(r < 0.05) return 1.0;
    return Math.max(1.0, parseFloat((0.95 / (1 - r)).toFixed(2)));
  }

  function multToTime(m){ return Math.pow((m - 1) / 0.06, 1 / 1.3); }
  function timeToMult(t){ return 1 + 0.06 * Math.pow(t, 1.3); }

  // Synchronizacja wyniku z serwerem PHP
  async function saveResult(delta, currentMultiplier, checkWon){
    try {
      const response = await fetch('pages/crash_update.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'delta=' + delta + '&mult=' + currentMultiplier + '&won=' + (checkWon ? 1 : 0) + '&bet=' + bet
      });
      const data = await response.json();
      balance = data.balance;
      updateUI();
      
      if (typeof updateHeaderBalance === 'function') {
        updateHeaderBalance(balance);
      }
    } catch(err) {
      console.error('Błąd połączenia z serwerem:', err);
      balance = Math.max(0, balance + delta);
      updateUI();
    }
  }

  function updateUI(){
    balTxt.textContent = balance + ' żetonów';
    betInput.max = balance;
  }

  function draw(){
    const W = canvas.parentElement.clientWidth;
    const H = canvas.parentElement.clientHeight;
    ctx.clearRect(0, 0, W, H);

    const PAD_L = 44, PAD_B = 28, PAD_T = 14, PAD_R = 12;
    const PW = W - PAD_L - PAD_R;
    const PH = H - PAD_T - PAD_B;

    if(points.length < 2){ drawGrid(PAD_L, PAD_T, PW, PH, W, H); return; }

    const maxMult = Math.max(currentMult + 0.3, 2.0);
    const maxT = Math.max(multToTime(maxMult) + 0.5, 2);

    drawGrid(PAD_L, PAD_T, PW, PH, W, H, maxT, maxMult);

    ctx.beginPath();
    points.forEach((p, i) => {
      const x = PAD_L + (p.t / maxT) * PW;
      const y = PAD_T + PH - ((p.m - 1) / (maxMult - 1)) * PH;
      i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    });
    ctx.lineTo(PAD_L + (points[points.length - 1].t / maxT) * PW, PAD_T + PH);
    ctx.lineTo(PAD_L, PAD_T + PH);
    ctx.closePath();
    const grad = ctx.createLinearGradient(0, PAD_T, 0, PAD_T + PH);
    const isCrashed = state === 'crashed';
    grad.addColorStop(0, isCrashed ? 'rgba(224,92,92,.25)' : 'rgba(61,220,151,.18)');
    grad.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = grad;
    ctx.fill();

    ctx.beginPath();
    points.forEach((p, i) => {
      const x = PAD_L + (p.t / maxT) * PW;
      const y = PAD_T + PH - ((p.m - 1) / (maxMult - 1)) * PH;
      i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    });
    ctx.strokeStyle = isCrashed ? '#e05c5c' : '#3ddc97';
    ctx.lineWidth = 2.5;
    ctx.lineJoin = 'round';
    ctx.stroke();

    const last = points[points.length - 1];
    const lx = PAD_L + (last.t / maxT) * PW;
    const ly = PAD_T + PH - ((last.m - 1) / (maxMult - 1)) * PH;
    ctx.beginPath();
    ctx.arc(lx, ly, 5, 0, Math.PI * 2);
    ctx.fillStyle = isCrashed ? '#e05c5c' : '#3ddc97';
    ctx.fill();
  }

  function drawGrid(pl, pt, pw, ph, W, H, maxT, maxM){
    maxT = maxT || 2;
    maxM = maxM || 2;
    ctx.strokeStyle = 'rgba(46,63,92,.7)';
    ctx.lineWidth = 1;
    ctx.fillStyle = '#5a7a9a';
    ctx.font = "10px 'DM Mono',monospace";

    const mTicks = [1, 1.5, 2, 3, 5, 10, 20, 50, 100].filter(v => v <= maxM + 0.5);
    mTicks.forEach(m => {
      const y = pt + ph - ((m - 1) / (Math.max(maxM - 1, 0.01))) * ph;
      if(y < pt - 5 || y > pt + ph + 5) return;
      ctx.beginPath(); ctx.moveTo(pl, y); ctx.lineTo(pl + pw, y); ctx.stroke();
      ctx.fillText(m.toFixed(m < 2 ? 1 : 0) + '×', 2, y + 4);
    });

    const tSteps = [0.5, 1, 2, 3, 5, 10, 20].filter(v => v <= maxT + 0.3);
    tSteps.forEach(t => {
      const x = pl + (t / maxT) * pw;
      if(x < pl || x > pl + pw + 5) return;
      ctx.beginPath(); ctx.moveTo(x, pt); ctx.lineTo(x, pt + ph); ctx.stroke();
      ctx.textAlign = 'center';
      ctx.fillText(t + 's', x, pt + ph + 16);
    });
    ctx.textAlign = 'left';
  }

  function setMsg(txt, cls = ''){
    msgBox.className = 'msg' + (cls ? ' ' + cls : '');
    msgBox.textContent = txt;
  }

  function renderHistoryUI(mult, won){
    history.unshift({ mult, won });
    if(history.length > 10) history.pop();
    histBox.innerHTML = history.map(h =>
      `<span class="hchip ${h.won ? 'w' : 'l'}">${parseFloat(h.mult).toFixed(2)}×</span>`
    ).join('');
  }

  function startCountdown(){
    if(state !== 'idle') return;
    const b = parseInt(betInput.value) || 0;
    if(b < 1 || b > balance){ setMsg('Nieprawidłowy zakład!', 'lose'); return; }
    bet = b;
    autoCashout = Math.max(1.01, parseFloat(cashoutInput.value) || 2.0);
    state = 'countdown';
    cashedOut = false;
    countdownVal = 3;
    points = [{ t: 0, m: 1 }];
    crashAt = genCrash();
    playBtn.textContent = 'Anuluj';
    playBtn.className = 'play-btn';
    setMsg(`Rakieta startuje za ${countdownVal}...`);
    multVal.textContent = '';
    multVal.className = 'mult-val waiting';
    multLabel.textContent = 'start za...';
    betInput.disabled = true;
    cashoutInput.disabled = true;
    countdownInt = setInterval(() => {
      countdownVal--;
      if(countdownVal <= 0){ clearInterval(countdownInt); startFlight(); }
      else setMsg(`Rakieta startuje za ${countdownVal}...`);
    }, 1000);
  }

  function cancelBet(){
    if(state !== 'countdown') return;
    clearInterval(countdownInt);
    state = 'idle';
    bet = 0;
    playBtn.textContent = 'Postaw zakład';
    playBtn.className = 'play-btn';
    betInput.disabled = false;
    cashoutInput.disabled = false;
    multVal.textContent = '1.00×';
    multVal.className = 'mult-val waiting';
    multLabel.textContent = 'oczekiwanie...';
    setMsg('Zakład anulowany.');
    points = [];
    draw();
  }

  function startFlight(){
    state = 'flying';
    startTime = performance.now();
    playBtn.textContent = 'Wypłać teraz';
    playBtn.className = 'play-btn cashout-now';
    multVal.className = 'mult-val live';
    multLabel.textContent = 'leci!';
    setMsg('Rośnie... wypłać przed crashem!');
    rafId = requestAnimationFrame(tick);
  }

  function tick(now){
    if(state !== 'flying') return;
    const elapsed = (now - startTime) / 1000;
    currentMult = timeToMult(elapsed);
    points.push({ t: elapsed, m: currentMult });

    if(!cashedOut && currentMult >= autoCashout){ doCashout(); return; }
    if(currentMult >= crashAt){ doCrash(); return; }

    multVal.textContent = currentMult.toFixed(2) + '×';
    draw();
    rafId = requestAnimationFrame(tick);
  }

  async function doCashout(){
    if(cashedOut || state !== 'flying') return;
    cashedOut = true;
    cancelAnimationFrame(rafId);
    const win = Math.floor(bet * currentMult);
    const delta = win - bet;
    state = 'crashed';
    multVal.textContent = currentMult.toFixed(2) + '×';
    multVal.className = 'mult-val live';
    multLabel.textContent = 'wypłacono!';
    setMsg(`Wypłacono przy ${currentMult.toFixed(2)}× — zysk: +${delta} żetonów 🎉`, 'win');
    renderHistoryUI(currentMult, true);
    await saveResult(delta, currentMult, true);
    endRound();
  }

  async function doCrash(){
    state = 'crashed';
    currentMult = crashAt;
    points.push({ t: multToTime(crashAt), m: crashAt });
    draw();
    multVal.textContent = crashAt.toFixed(2) + '×';
    multVal.className = 'mult-val crashed';
    multLabel.textContent = 'crash!';
    if(!cashedOut){
      setMsg(`Crash przy ${crashAt.toFixed(2)}× — strata: -${bet} żetonów`, 'lose');
      renderHistoryUI(crashAt, false);
      await saveResult(-bet, crashAt, false);
    }
    endRound();
  }

  function endRound(){
    cancelAnimationFrame(rafId);
    betInput.disabled = false;
    cashoutInput.disabled = false;
    playBtn.textContent = 'Postaw zakład';
    playBtn.className = 'play-btn';
    setTimeout(() => {
      if(state === 'crashed'){
        state = 'idle';
        currentMult = 1.0;
        points = [];
        multVal.textContent = '1.00×';
        multVal.className = 'mult-val waiting';
        multLabel.textContent = 'oczekiwanie...';
        if(!msgBox.classList.contains('win') && !msgBox.classList.contains('lose'))
          setMsg('Postaw zakład i kliknij start.');
        draw();
      }
    }, 3500);
  }

  playBtn.addEventListener('click', () => {
    if(state === 'idle') startCountdown();
    else if(state === 'countdown') cancelBet();
    else if(state === 'flying') doCashout();
  });

  document.querySelectorAll('.qbtn').forEach(b => {
    b.addEventListener('click', () => {
      const frac = parseFloat(b.dataset.frac);
      betInput.value = Math.max(1, Math.floor(balance * frac));
    });
  });

  window.addEventListener('resize', resize);
  resize();
  updateUI();
})();
</script>