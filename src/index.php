<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leseecke - Büchereiverwaltung</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
	<?php include 'menu.php'; ?>
	
    <!-- Bild in der oberen rechten Ecke -->
    <img src="kinder_bunt.png" alt="Kinder Bunt" class="top-right-image">
	
    <h1>Willkommen in der Leseecke</h1>
	    <h1>der IGS am Nanstein</h1>

    <h2>Hier kannst du nach Büchern suchen</h2>
    <form action="index.php" method="post">
        <input type="text" name="search" placeholder="Titel, Autor, ISBN oder Barcode eingeben">
        <input type="submit" value="Suchen">
    </form>

    <?php
    include 'db.php'; // Einfügen der Datenbankverbindung

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $search_query = $_POST['search'];
        $books = search_books($search_query, $conn); // Verbindung als Parameter übergeben

        if (!empty($books)) {
    echo "<div class='buchsuche'>"; // Buchsuche-Klasse hinzufügen
    foreach ($books as $book) {
        echo "<div class='book-result'>";
        if (!empty($book['bildlink'])) {
            echo "<img src='" . htmlspecialchars($book['bildlink']) . "' alt='Coverbild'>";
        } else {
            echo "<img src='nocover.png' alt='Platzhalterbild'>";
        }
        echo "<div class='details'>";
        echo "<strong>Titel:</strong> " . htmlspecialchars($book['titel']) . "<br>";
        echo "<strong>Autor:</strong> " . htmlspecialchars($book['autor']) . "<br>";
        echo "<strong>ISBN:</strong> " . htmlspecialchars($book['isbn']) . "<br>";
        echo "<strong>Barcode:</strong> " . htmlspecialchars($book['barcode']) . "<br>";
        echo "<strong>Lesealter:</strong> " . htmlspecialchars($book['mindestalter']) . "<br>";
        echo "<strong>Bestand:</strong> <span style='color:" . ($book['bestand'] === 'ausgeliehen' ? 'red' : 'green') . "'>" . htmlspecialchars($book['bestand']) . "</span><br>";
        echo "</div>";
        echo "</div>";
    }
    echo "</div>"; // Buchsuche-Klasse schließen
} else {
    echo "<p>Keine Buchdaten gefunden.</p>";
}
    }

    function search_books($query, $conn) {
    // Suche nach Titel, Autor, ISBN und Mindestalter
    $sql = "
        SELECT b.*, 
            CASE 
                WHEN l.book_id IS NOT NULL THEN 'ausgeliehen' 
                ELSE 'verfügbar' 
            END AS bestand
        FROM books b
        LEFT JOIN loans l ON b.id = l.book_id  -- Korrekte Referenzierung
        WHERE b.titel LIKE ? OR b.autor LIKE ? OR b.isbn LIKE ? OR b.barcode LIKE ?";
    
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

    return $books;
}
    ?>
</body>
</html>
