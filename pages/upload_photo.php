<?php
if (!isset($_SESSION['user'])) {
    header("Location: profil");
    exit();
}

if (isset($_FILES['photo'])) {

    $user_id = $_SESSION['user_id'];
    $newname = basename($file['name']);
    $target = "pictures/user_" . $user_id . "_profile_". $newname;

    $result = mysqli_query($conn, "SELECT profile_pic FROM users WHERE user_id=$user_id");
    $row = mysqli_fetch_assoc($result); 
    if ($row) {
        $file = $row['profile_pic']; 
        if (file_exists($file)) {
            unlink($file);
        }
        $_SESSION['profile_pic'] = '';
    }
    
    $file = $_FILES['photo'];

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