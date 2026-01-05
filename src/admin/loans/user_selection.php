<?php
session_start();
include('../db.php');
include('../menu.php');

$searchUser = isset($_GET['searchUser']) ? $_GET['searchUser'] : '';

// Benutzerauswahl speichern
if (isset($_POST['selectUser'])) {
    $_SESSION['selectedUserId'] = $_POST['user_id'];
    header('Location: book_selection.php'); // Weiterleitung zur Buchauswahl
    exit();
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Benutzer auswählen</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <h1>Benutzer auswählen</h1>
    <form action="" method="GET">
        <input type="text" name="searchUser" placeholder="Name oder E-Mail" value="<?php echo htmlspecialchars($searchUser); ?>">
        <input type="submit" value="Suchen">
    </form>

    <h3>Suchergebnisse</h3>
    <form method="POST">
        <ul>
            <?php
            if ($searchUser) {
                $searchUserQuery = "SELECT * FROM users WHERE name LIKE ? OR vorname LIKE ? OR email LIKE ?";
                $stmt = $conn->prepare($searchUserQuery);
                $searchUserTerm = '%' . $searchUser . '%';
                $stmt->bind_param("sss", $searchUserTerm, $searchUserTerm, $searchUserTerm);
                $stmt->execute();
                $userResult = $stmt->get_result();

                while ($user = $userResult->fetch_assoc()) {
                    echo "<li><input type='radio' name='user_id' value='{$user['id']}'> {$user['vorname']} {$user['name']}</li>";
                }
            }
            ?>
        </ul>
        <input type="submit" name="selectUser" value="Benutzer auswählen">
    </form>
</body>
</html>