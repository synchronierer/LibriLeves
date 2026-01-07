<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/security.php';

start_secure_session();
require_admin();

// Session-Nachrichten
$deletesuccess = $_SESSION['deletesuccess'] ?? '';
$deleteerror   = $_SESSION['deleteerror'] ?? '';
unset($_SESSION['deletesuccess'], $_SESSION['deleteerror']);

// Filter
$filterTerm = $_GET['filterTerm'] ?? '';

// Verfügbarkeit: ausgeliehen, wenn irgendein loans-Eintrag existiert (ohne Datumsbedingung)
$sql = "SELECT b.id, b.titel, b.autor, b.mindestalter, b.erscheinungsjahr, b.verlag, b.isbn, b.ort, b.barcode, b.bildlink,
               CASE WHEN l.book_id IS NOT NULL THEN 'ausgeliehen' ELSE 'verfügbar' END AS bestand
        FROM books b
        LEFT JOIN loans l
          ON b.id = l.book_id
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
</head>
<body>
    <br>
    <h1>Bestand anzeigen</h1>

    <?php if ($deletesuccess): ?>
        <div class="popup success" style="display:block;"><?php echo htmlspecialchars($deletesuccess); ?></div>
    <?php endif; ?>
    <?php if ($deleteerror): ?>
        <div class="popup error" style="display:block;"><?php echo htmlspecialchars($deleteerror); ?></div>
    <?php endif; ?>

    <form action="" method="get">
        <input type="text" name="filterTerm" placeholder="Bücher filtern (Titel, Autor, ISBN, ID)" value="<?php echo htmlspecialchars($filterTerm); ?>">
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
                        <form action="delete_book.php" method="POST" class="delete-form" onsubmit="return confirm('Dieses Buch wirklich löschen?');" style="display:inline;">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <input type="hidden" name="book_id" value="<?php echo (int)$book['id']; ?>">
                            <button type="submit" class="delete-button">Löschen</button>
                        </form>
                        <a href="edit_book.php?id=<?php echo (int)$book['id']; ?>" class="button">Bearbeiten</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<script>
    setTimeout(() => {
        document.querySelectorAll('.popup').forEach(p => p.style.display = 'none');
    }, 6000);
</script>
</body>
</html>
