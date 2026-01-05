<?php
session_start();
include('../../db.php');
session_start();
// Session-Nachrichten abfragen und danach löschen
$deletesuccess = isset($_SESSION['deletesuccess']) ? $_SESSION['deletesuccess'] : '';
$deleteerror = isset($_SESSION['deleteerror']) ? $_SESSION['deleteerror'] : '';
unset($_SESSION['deletesuccess'], $_SESSION['deleteerror']);



// Überprüfe, ob der Benutzer eingeloggt ist und Admin-Rechte hat
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit();
}

// Filter für die Bücher
$filterTerm = '';
if (isset($_GET['filterTerm'])) {
    $filterTerm = $_GET['filterTerm'];
}

// SQL-Abfrage mit Filter
$sql = "SELECT b.id, b.titel, b.autor, b.mindestalter, b.erscheinungsjahr, b.verlag, b.isbn, b.ort, b.barcode, b.bildlink, 
               CASE 
                   WHEN l.book_id IS NOT NULL THEN 'ausgeliehen' 
                   WHEN l.book_id IS NULL THEN 'verfügbar' 
                   ELSE 'unbekannt' 
               END AS bestand
        FROM books b
        LEFT JOIN loans l ON b.id = l.book_id
        WHERE b.titel LIKE ? OR b.autor LIKE ? OR b.isbn LIKE ? OR b.id LIKE ?";

$likeTerm = "%" . $filterTerm . "%";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $likeTerm, $likeTerm, $likeTerm, $likeTerm);
$stmt->execute();
$result = $stmt->get_result();
$books = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
include '../../menu.php';
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bestand anzeigen</title>
    <link rel="stylesheet" href="../../style.css">
    <script>
        function confirmDelete(bookId) {
            if (confirm("Sind Sie sicher, dass Sie dieses Buch löschen möchten?")) {
                // Wenn der Benutzer bestätigt, leite zur Lösch-URL weiter
                window.location.href = 'delete_book.php?book_id=' + bookId;
            }
        }
    </script>
</head>
<body>
    <br>
    <h1>Bestand anzeigen</h1>
    
    <!-- Falls eine Erfolgsmeldung übergeben wurde -->
    <?php if ($deletesuccess): ?>
        <div class="popup success">
            <?php echo htmlspecialchars($deletesuccess); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($deleteerror): ?>
        <div class="popup error">
            <?php echo htmlspecialchars($deleteerror); ?>
        </div>
    <?php endif; ?>

    <form action="" method="get">
        <input type="text" name="filterTerm" placeholder="Bücher filtern (Titel, Autor, ISBN, Barcode)" value="<?php echo htmlspecialchars($filterTerm); ?>">
        <input type="submit" value="Filtern">
    </form>

    <table class="loansTable">
        <thead>
            <tr>
                <th>Titel</th>
                <th>Autor</th>
                <th>Mindestalter</th>
                <th>Erscheinungsjahr</th>
                <th>Verlag</th>
                <th>ISBN</th>
                <th>Standort</th>
                <th>Barcode</th>
                <th>Bestand</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($books as $book): ?>
                <tr>
                    <td><?php echo htmlspecialchars($book['titel']); ?></td>
                    <td><?php echo htmlspecialchars($book['autor']); ?></td>
                    <td><?php echo htmlspecialchars($book['mindestalter']); ?></td>
                    <td><?php echo htmlspecialchars($book['erscheinungsjahr']); ?></td>
                    <td><?php echo htmlspecialchars($book['verlag']); ?></td>
                    <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                    <td><?php echo htmlspecialchars($book['ort']); ?></td>
                    <td><?php echo htmlspecialchars($book['barcode']); ?></td>
                    <td><?php echo htmlspecialchars($book['bestand']); ?></td>
                    <td>
                        <div style="display: inline-block; margin-right: 10px;">
                            <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $book['id']; ?>)" class="delete-button">Löschen</a>
                        </div>
                        <div style="display: inline-block;">
                            <a href="edit_book.php?id=<?php echo $book['id']; ?>" class="button">Bearbeiten</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<script>
    // Zeige das Popup (error oder success), wenn vorhanden, und blende es nach 6 Sekunden aus.
    const popup = document.querySelector('.popup');
    if (popup) {
        popup.style.display = 'block';
        setTimeout(() => {
            popup.style.display = 'none';
        }, 6000);
    }
</script>

</body>
</html>