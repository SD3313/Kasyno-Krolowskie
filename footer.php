</div>
<div class="footer-bar">
    <div class="footer-content">
        <!-- Tekst ostrzeżenia -->
        <p class="footer-warning">
            Hazard związany jest z ryzykiem, a udział w nielegalnych grach hazardowych jest niezgodny z polskim prawem.
        </p>
        
        <!-- Linki -->
        <div class="footer-links">
            <a href="page.php?page=regulamin">Regulamin</a>
            <span class="footer-separator">|</span>
            <a href="page.php?page=kontakt">Kontakt</a>
            <span class="footer-separator">|</span>
            <a href="page.php?page=pytania">Pytania</a>
            <span class="footer-separator">|</span>
            <a href="page.php?page=kontrola-rodzicielska">Kontrola rodzicielska</a>
        </div>
    </div>
</div>
</div>

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