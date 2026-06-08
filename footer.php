</div>
<div class="footer-bar"></div>

<script>
// Globalna zmienna salda
let globalBalance = <?= (int) $balance ?>;

// Globalna funkcja do aktualizacji salda wszędzie
function updateHeaderBalance(newBalance) {
    globalBalance = newBalance;
    
    // Aktualizuj nagłówek
    const headerValue = document.getElementById('headerBalanceValue');
    if (headerValue) {
        headerValue.textContent = newBalance;
    }
    
    // Aktualizuj saldo w grze (jeśli istnieje)
    const balTxt = document.getElementById('balTxt');
    if (balTxt) {
        balTxt.textContent = newBalance + ' żetonów';
    }
    
    console.log('Saldo zaktualizowane na:', newBalance);
}
</script>

<?php
if (isset($conn)) {
    mysqli_close($conn);
}
?>

</body>
</html>