<div class="register-container">    
    <h1> Zarejestruj się </h1>
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $imie = mysqli_real_escape_string($conn, $_POST['Imię']);
        $nazwisko = mysqli_real_escape_string($conn, $_POST['Nazwisko']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $haslo = mysqli_real_escape_string($conn, $_POST['haslo']);
        $powtorz_haslo = mysqli_real_escape_string($conn, $_POST['powtorz_haslo']);
        $uro = mysqli_real_escape_string($conn, $_POST['uro']);

        $birthDate = new DateTime($uro);
        $today = new DateTime();

        if($birthDate->diff($today)->y<18){
            echo "<p style='color:red;'>Musisz mieć ukończone 18 lat, aby się zarejestrować!</p>";
        } else if (strlen($haslo) < 8) {
            echo "<p style='color:red;'>Hasło musi mieć co najmniej 8 znaków!</p>";
        } else if ($haslo !== $powtorz_haslo) {
            echo "<p style='color:red;'>Hasła nie są identyczne!</p>";
        } else {
            $hashed_password = password_hash($haslo, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (first_name, last_name, email, pass, registration_date) VALUES ('$imie', '$nazwisko', '$email', '$hashed_password', NOW())";
            
            try {
                mysqli_query($conn, $sql);
                echo "<p style='color:green;'>Konto założone! Możesz się teraz zalogować.</p>";
            } catch(mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) {
                    echo "<p style='color:red;'>Ten email jest już zajęty!</p>";
                } else {
                    echo "<p style='color:red;'>Błąd podczas rejestracji: " . $e->getMessage() . "</p>";
                }
            }
        }
    }

    ?>

    <form action="register" method="post">
        <label for="Imię">Imię:</label><br>
        <input type="text" id="Imię" name="Imię" required value=<?php echo isset($_POST['Imię']) ? htmlspecialchars($_POST['Imię']) : ' '; ?>>
        <br>
        <label for="Nazwisko">Nazwisko:</label><br>
        <input type="text" id="Nazwisko" name="Nazwisko" required value=<?php echo isset($_POST['Nazwisko']) ? htmlspecialchars($_POST['Nazwisko']) : ' '; ?>>
        <br>
        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" required value=<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ' '; ?>>
        <br>
        <label for="uro">Data urodzenia:</label><br>
        <input type="date" id="uro" name="uro" required placeholder=">18" min="18">
        <br>
        <label for="haslo">Hasło:</label><br>
        <input type="password" id="haslo" name="haslo" required>
        <br>
        <label for="powtorz_haslo">Powtórz hasło:</label><br>
        <input type="password" id="powtorz_haslo" name="powtorz_haslo" required>
        <br>
        <input type="checkbox" id="regulamin" name="regulamin" required>
        <label for="regulamin" class="regulamin-label">Zakładając konto, oświadczam, że przeczytałem i akceptuję <a href="regulamin" target="_blank">regulamin</a> oraz mam ukończone 18 lat</label>
        <br>
        <input type="submit" value="Załóż konto">
    </form>
    
</div>