<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leseecke - Dashboard</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .book-result {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .book-result img {
            max-width: 100px;
            margin-right: 20px;
        }
        .book-result .details {
            display: flex;
            flex-direction: column;
        }
    </style>
</head>
<body>
 <?php include '../menu.php'; ?>
   
   
    <h1>Admin Dashboard</h1>
    <h2>Bereiche</h2>
    <ul>
        <li><a href="benutzerverwaltung.php">Benutzerverwaltung</a></li>
        <li><a href="buecherverwaltung.php">Bücherverwaltung</a></li>
        <li><a href="ausleihe.php">Ausleihe</a></li>
    </ul>
    <a href="logout.php">Logout</a>
</body>
</html>