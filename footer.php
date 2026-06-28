</div>
<div class="footer-bar">
    <div class="footer-content">
        <p class="footer-warning">
            Hazard związany jest z ryzykiem, a udział w nielegalnych grach hazardowych jest niezgodny z polskim prawem.
        </p>
        
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
let globalBalance = <?= (int) $balance ?>;

function updateHeaderBalance(newBalance) {
    globalBalance = newBalance;
    
    const headerValue = document.getElementById('headerBalanceValue');
    if (headerValue) {
        headerValue.textContent = newBalance;
    }
    
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