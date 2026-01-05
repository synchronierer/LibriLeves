<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leseecke - Büchereiverwaltung</title>
    <link rel="stylesheet" href="style.css">
    </style>
</head>
<body>
	<?php include 'menu.php'; ?>
    
    <h1>Willkommen in der Leseecke</h1>

    <h2>Hier kannst du nach Büchern suchen</h2>
    <form action="index.php" method="post">
        <input type="text" name="search" placeholder="Titel, Autor, ISBN oder Barcode eingeben">
        <input type="submit" value="Suchen">
    </form>

    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $search_query = $_POST['search'];
        $books = search_books($search_query);

        if (!empty($books)) {
            foreach ($books as $book) {
                echo "<div class='book-result'>";
                if (!empty($book['bildlink'])) {
                    echo "<img src='" . htmlspecialchars($book['bildlink']) . "' alt='Coverbild'>";
                } else {
                    echo "<img src='nocover.png' alt='Platzhalterbild'>"; // Optional: Platzhalterbild, wenn kein Cover vorhanden
                }
                echo "<div class='details'>";
                echo "<strong>Titel:</strong> " . htmlspecialchars($book['titel']) . "<br>";
                echo "<strong>Autor:</strong> " . htmlspecialchars($book['autor']) . "<br>";
                echo "<strong>ISBN:</strong> " . htmlspecialchars($book['isbn']) . "<br>";
                echo "<strong>Barcode:</strong> " . htmlspecialchars($book['barcode']) . "<br>";
                echo "</div>";
                echo "</div>";
            }
        } else {
            echo "<p>Keine Buchdaten gefunden.</p>";
        }
    }

    function search_books($query) {
        $servername = "localhost";
        $username = "phpmyadmin";
        $password = "Schule!2025";
        $dbname = "leseecke";

        // Verbindung erstellen
        $conn = new mysqli($servername, $username, $password, $dbname);

        // Verbindung prüfen
        if ($conn->connect_error) {
            die("Verbindung fehlgeschlagen: " . $conn->connect_error);
        }

        // Suche nach Titel, Autor oder ISBN
        $sql = "SELECT * FROM books WHERE titel LIKE ? OR autor LIKE ? OR isbn LIKE ? OR barcode LIKE ?";
        $stmt = $conn->prepare($sql);
        $search_term = "%$query%";
        $stmt->bind_param("ssss", $search_term, $search_term, $search_term, $search_term);
        $stmt->execute();
        $result = $stmt->get_result();

        $books = [];
        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }

        $stmt->close();
        $conn->close();

        return $books;
    }
    ?>
</body>
</html>
