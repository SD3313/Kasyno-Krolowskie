
<div class="login-container">
    <h1> Logowanie </h1>
    <p>Witaj w Kasynie Królewskim! Zaloguj się, aby kontynuować.</p>
<form action="login" method="post">
    <label for="email">Wpisz adres e-mail:</label><br>
    <input type="email" id="email" name="email" required value=<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ' '; ?>>
    <br>
    <label for="haslo">Wpisz hasło :</label><br>
    <input type="password" id="haslo" name="haslo" required>
    <br>
    <input type="submit" value="Zaloguj się">

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $haslo = mysqli_real_escape_string($conn, $_POST['haslo']);
    

    $hashed_password = password_hash($haslo, PASSWORD_DEFAULT);
    $sql = "SELECT * FROM users WHERE email = '$email'";
      
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);
        if (password_verify($haslo, $row['pass'])) {
            echo "<p style='color:green;'>Zalogowano pomyślnie!</p>";
            $_SESSION['user'] = $row['first_name'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['user_id'] = $row['user_id'];
            header("Location: home");
            exit();
        } else {
            echo "<p style='color:red;'>Błędne hasło.</p>";
        }
    } else {
        echo "<p style='color:red;'>Użytkownik o takim emailu nie istnieje.</p>";
    }

}
?>
</form>
</div>