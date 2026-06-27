<?php
if (!isset($_SESSION['user'])) {
    header("Location: profil");
    exit();
}

if (isset($_FILES['photo'])) {
    $file = $_FILES['photo'];
    $user_id = $_SESSION['user_id'];

    $target = "pictures/user_" . $user_id . "_profile";
    if (move_uploaded_file($file['tmp_name'], $target)) {
        $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE user_id = ?");
        $stmt->bind_param("si", $target, $user_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['profile_pic'] = $target;
    } else {
        echo "Błąd przy przenoszeniu pliku!";
    }
}


header("Location: profil");
exit();
?>