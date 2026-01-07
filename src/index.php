<?php
require_once __DIR__ . '/includes/security.php';
start_secure_session();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leseecke - Büchereiverwaltung</title>
    <link rel="stylesheet" href="style.css">
    <!-- Favicon/Manifest optional, falls noch nicht eingebunden -->
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/media/favicon.svg">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#5CA32D">
</head>
<body>
    <?php include 'menu.php'; ?>

    <h1>Willkommen in der Leseecke</h1>

    <!-- Schullogo unter der Überschrift (150px Breite/Höhe automatisch) -->
    <div style="text-align:center; margin-top:8px;">
        <img src="/media/schullogo.png" alt="Schullogo" width="150" style="height:auto;">
    </div>

    <h2>Hier kannst du nach Büchern suchen</h2>

    <form action="index.php" method="post">
        <input type="text" name="search" placeholder="Titel, Autor, ISBN oder Barcode eingeben">
        <input type="submit" value="Suchen">
    </form>

    <?php
    include 'db.php';

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $search_query = $_POST['search'] ?? '';
        $books = search_books($search_query, $conn);

        if (!empty($books)) {
            echo "<div class='buchsuche'>";
            foreach ($books as $book) {
                echo "<div class='book-result'>";
                $cover = !empty($book['bildlink']) ? $book['bildlink'] : 'nocover.png';
                echo "<img class='cover-thumb' src='" . htmlspecialchars($cover) . "' alt='Coverbild'>";
                echo "<div class='details'>";
                echo "<strong>Titel:</strong> " . htmlspecialchars($book['titel']) . "<br>";
                echo "<strong>Autor:</strong> " . htmlspecialchars($book['autor']) . "<br>";
                echo "<strong>ISBN:</strong> " . htmlspecialchars($book['isbn']) . "<br>";
                echo "<strong>Barcode:</strong> " . htmlspecialchars($book['barcode']) . "<br>";
                echo "<strong>Lesealter:</strong> " . htmlspecialchars($book['mindestalter']) . "<br>";
                $farbe = ($book['bestand'] === 'ausgeliehen') ? 'red' : 'green';
                echo "<strong>Bestand:</strong> <span style='color:$farbe'>" . htmlspecialchars($book['bestand']) . "</span><br>";
                echo "</div></div>";
            }
            echo "</div>";
        } else {
            echo "<p>Keine Buchdaten gefunden.</p>";
        }
    }

    function search_books($query, $conn) {
        $sql = "
            SELECT b.*,
                CASE WHEN l.book_id IS NOT NULL THEN 'ausgeliehen' ELSE 'verfügbar' END AS bestand
            FROM books b
            LEFT JOIN loans l ON b.id = l.book_id
            WHERE b.titel LIKE ? OR b.autor LIKE ? OR b.isbn LIKE ? OR b.barcode LIKE ?";

        $stmt = $conn->prepare($sql);
        $search_term = '%' . $query . '%';
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
