<?php
session_start();
include('../db.php');

// Überprüfe, ob der Benutzer eingeloggt ist und Admin-Rechte hat
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Speichere die aktuelle URL in der Sitzung
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    // Wenn nicht, leite zur Login-Seite um
    header("Location: login.php");
    exit();
}

// Buch hinzufügen
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_book'])) {
    $titel = $_POST['titel'];
    $autor = $_POST['autor'];
    $isbn = $_POST['isbn'];
    $bildlink = $_POST['bildlink'];
    $barcode = $_POST['barcode']; // Barcode hinzufügen

    $sql = "INSERT INTO books (titel, autor, isbn, bildlink, barcode) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $titel, $autor, $isbn, $bildlink, $barcode);
    $stmt->execute();
    echo "Buch erfolgreich hinzugefügt.";
}

// Buch löschen
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_book'])) {
    $bookId = intval($_POST['book_id']);
    $sql = "DELETE FROM books WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $bookId);
    if ($stmt->execute()) {
        echo "Buch erfolgreich gelöscht.";
    } else {
        echo "Fehler beim Löschen des Buches: " . $conn->error;
    }
}

// Buch speichern (bearbeiten)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_book'])) {
    $bookId = intval($_POST['book_id']);
    $titel = $_POST['titel'] ?? ''; // Optional
    $autor = $_POST['autor'] ?? ''; // Optional
    $isbn = $_POST['isbn'] ?? ''; // Optional
    $bildlink = $_POST['bildlink'] ?? ''; // Optional
    $barcode = $_POST['barcode'] ?? ''; // Optional

    $sql = "UPDATE books SET titel = ?, autor = ?, isbn = ?, bildlink = ?, barcode = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $titel, $autor, $isbn, $bildlink, $barcode, $bookId);
    if ($stmt->execute()) {
        echo "Buch erfolgreich gespeichert.";
    } else {
        echo "Fehler beim Speichern des Buches: " . $conn->error;
    }
}

// Buchsuche
$searchResults = [];
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['search'])) {
    $searchTerm = $_GET['searchTerm'];
    $sql = "SELECT id, titel, autor, isbn, bildlink, barcode FROM books WHERE titel LIKE ? OR autor LIKE ? OR isbn LIKE ? OR barcode LIKE ?";
    $stmt = $conn->prepare($sql);
    $likeTerm = "%" . $searchTerm . "%";
    $stmt->bind_param("ssss", $likeTerm, $likeTerm, $likeTerm, $likeTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $searchResults[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leseecke - Katalogisierung</title>
    <link rel="stylesheet" href="../style.css">
 
</head>
<body>
    <?php include '../menu.php'; ?>

    <h1>Bücherverwaltung</h1>
    <form action="" method="POST">
        <input type="text" name="titel" placeholder="Titel" required>
        <input type="text" name="autor" placeholder="Autor" required>
        <input type="text" name="isbn" placeholder="ISBN" required>
        <input type="text" name="bildlink" placeholder="Bildlink" required>
        <input type="text" name="barcode" placeholder="Barcode" required>
        <input type="submit" name="add_book" value="Buch hinzufügen">
    </form>

    <h1>Buch über die ISBN einpflegen</h1>
    <form action="add_book.php" method="post">
        <label for="isbn">ISBN:</label>
        <input type="text" id="isbn" name="isbn" required>
        <button type="submit">Buchdaten abrufen</button>
    </form>
   
    <h1>Nachrecherche</h1>
    <form action="" method="get">
        <label for="singleId">Einzelne ID:</label>
        <input type="number" id="singleId" name="singleId" min="1" placeholder="Geben Sie eine ID ein">
        <br><br>
        
        <label for="startId">ID-Bereich:</label>
        <input type="number" id="startId" name="startId" min="1" placeholder="Start-ID">
        <label for="endId">bis</label>
        <input type="number" id="endId" name="endId" min="1" placeholder="End-ID">
        <br><br>
        
        <input type="submit" value="Recherche starten">
    </form>

    <h1>Bestandspflege</h1>
    <form action="" method="get">
        <input type="text" name="searchTerm" placeholder="Titel, Autor, ISBN oder Barcode suchen" required>
        <input type="submit" name="search" value="Suche starten">
    </form>

    <?php if (!empty($searchResults)): ?>
        <h2>Suchergebnisse:</h2>
        <ul>
        <?php foreach ($searchResults as $book): ?>
            <li class="book-result">
                <img src="<?php echo htmlspecialchars($book['bildlink']); ?>" alt="Thumbnail">
                <div class="details">
                    <strong><?php echo htmlspecialchars($book['titel']); ?></strong>
                    <p>Autor: <?php echo htmlspecialchars($book['autor']); ?></p>
                    <p>ISBN: <?php echo htmlspecialchars($book['isbn']); ?></p>
                    <p>Barcode: <?php echo htmlspecialchars($book['barcode']); ?></p>
                    <div class="button-container">
                        <form action="" method="POST" style="display:inline;">
                            <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                            <button type="submit" name="delete_book">Löschen</button>
                        </form>
                        <button onclick="openModal(<?php echo $book['id']; ?>, '<?php echo htmlspecialchars($book['titel']); ?>', '<?php echo htmlspecialchars($book['autor']); ?>', '<?php echo htmlspecialchars($book['isbn']); ?>', '<?php echo htmlspecialchars($book['bildlink']); ?>', '<?php echo htmlspecialchars($book['barcode']); ?>')">Bearbeiten</button>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <!-- Modal für die Bearbeitung -->
    <div id="myModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Buch bearbeiten</h2>
            <form id="editForm" action="" method="POST">
                <input type="hidden" name="book_id" id="modalBookId" value="">
                <input type="text" name="titel" id="modalTitel" placeholder="Titel" required>
                <input type="text" name="autor" id="modalAutor" placeholder="Autor" required>
                <input type="text" name="isbn" id="modalIsbn" placeholder="ISBN" required>
                <input type="text" name="bildlink" id="modalBildlink" placeholder="Bildlink" required>
                <input type="text" name="barcode" id="modalBarcode" placeholder="Barcode" required>
                <input type="submit" name="save_book" value="Speichern">
            </form>
        </div>
    </div>

    <script>
        function openModal(id, titel, autor, isbn, bildlink, barcode) {
            document.getElementById("modalBookId").value = id;
            document.getElementById("modalTitel").value = titel;
            document.getElementById("modalAutor").value = autor;
            document.getElementById("modalIsbn").value = isbn;
            document.getElementById("modalBildlink").value = bildlink;
            document.getElementById("modalBarcode").value = barcode;
            document.getElementById("myModal").style.display = "block";
        }

        function closeModal() {
            document.getElementById("myModal").style.display = "none";
        }
    </script>

    <?php if (isset($_GET['singleId']) || (isset($_GET['startId']) && isset($_GET['endId']))): ?>
        <h2>Nachrecherche Ergebnisse:</h2>
        <?php
        // Nachrecherche Ergebnisse verarbeiten
        $ids = [];
        if (isset($_GET['singleId']) && !empty($_GET['singleId'])) {
            $ids[] = intval($_GET['singleId']);
        }
        if (isset($_GET['startId']) && isset($_GET['endId'])) {
            $startId = intval($_GET['startId']);
            $endId = intval($_GET['endId']);
            for ($id = $startId; $id <= $endId; $id++) {
                $ids[] = $id;
            }
        }

        if (!empty($ids)) {
            $idList = implode(',', array_map('intval', $ids)); // Sichere die IDs
            $sql = "SELECT id, titel, autor, isbn, bildlink, barcode FROM books WHERE id IN ($idList)";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<div class='book-result'>";
                    echo "<img src='" . htmlspecialchars($row['bildlink']) . "' alt='Thumbnail'>";
                    echo "<div class='details'>";
                    echo "<strong>" . htmlspecialchars($row['titel']) . "</strong>";
                    echo "<p>Autor: " . htmlspecialchars($row['autor']) . "</p>";
                    echo "<p>ISBN: " . htmlspecialchars($row['isbn']) . "</p>";
                    echo "<p>Barcode: " . htmlspecialchars($row['barcode']) . "</p>";
                    echo "<form action='' method='POST'>";
                    echo "<input type='hidden' name='book_id' value='" . $row['id'] . "'>";
                    echo "<button type='submit' name='delete_book'>Löschen</button>";
                    echo "</form>";
                    echo "</div>";
                    echo "</div>";
                }
            } else {
                echo "<p>Keine Bücher gefunden für die angegebene ID oder den ID-Bereich.</p>";
            }
        }
        ?>
    <?php endif; ?>
</body>
</html>