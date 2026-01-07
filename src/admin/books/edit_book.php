<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/security.php';

start_secure_session();
require_admin();

$message = '';
$redirect = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_book'])) {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $message = "CSRF-Prüfung fehlgeschlagen.";
    } else {
        $bookId = (int)($_POST['book_id'] ?? 0);
        $titel = $_POST['titel'] ?? '';
        $autor = $_POST['autor'] ?? '';
        $mindestalter = $_POST['mindestalter'] ?? '';
        $erscheinungsjahr = $_POST['erscheinungsjahr'] ?? '';
        $verlag = $_POST['verlag'] ?? '';
        $isbn = $_POST['isbn'] ?? '';
        $ort = $_POST['ort'] ?? '';
        $barcode = $_POST['barcode'] ?? '';
        $bildlink = $_POST['bildlink'] ?? '';

        if (!empty($barcode)) {
            $checkSql = "SELECT COUNT(*) FROM books WHERE barcode = ? AND id != ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("si", $barcode, $bookId);
            $checkStmt->execute();
            $checkStmt->bind_result($count);
            $checkStmt->fetch();
            $checkStmt->close();

            if ($count > 0) {
                $suggestedBarcode = $isbn . '_001';
                for ($i = 1; $i <= 999; $i++) {
                    $newBarcode = $isbn . '_' . str_pad($i, 3, '0', STR_PAD_LEFT);
                    $suggestStmt = $conn->prepare("SELECT COUNT(*) FROM books WHERE barcode = ? AND id != ?");
                    $suggestStmt->bind_param("si", $newBarcode, $bookId);
                    $suggestStmt->execute();
                    $suggestStmt->bind_result($newCount);
                    $suggestStmt->fetch();
                    $suggestStmt->close();
                    if ($newCount == 0) {
                        $suggestedBarcode = $newBarcode;
                        break;
                    }
                }
                $message = "Der Barcode ist bereits vergeben. Vorschlag: " . $suggestedBarcode;
            } else {
                $sql = "UPDATE books SET titel = ?, autor = ?, mindestalter = ?, erscheinungsjahr = ?, verlag = ?, isbn = ?, ort = ?, barcode = ?, bildlink = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssissssssi", $titel, $autor, $mindestalter, $erscheinungsjahr, $verlag, $isbn, $ort, $barcode, $bildlink, $bookId);
                if ($stmt->execute()) {
                    $message = "Buch erfolgreich gespeichert.";
                    $redirect = true;
                } else {
                    $message = "Fehler beim Speichern des Buches: " . $conn->error;
                }
                $stmt->close();
            }
        } else {
            $sql = "UPDATE books SET titel = ?, autor = ?, mindestalter = ?, erscheinungsjahr = ?, verlag = ?, isbn = ?, ort = ?, bildlink = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssisssssi", $titel, $autor, $mindestalter, $erscheinungsjahr, $verlag, $isbn, $ort, $bildlink, $bookId);
            if ($stmt->execute()) {
                $message = "Buch erfolgreich gespeichert.";
                $redirect = true;
            } else {
                $message = "Fehler beim Speichern des Buches: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Buchdaten laden
$bookId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sql = "SELECT titel, autor, mindestalter, erscheinungsjahr, verlag, isbn, ort, barcode, bildlink FROM books WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bookId);
$stmt->execute();
$result = $stmt->get_result();
$book = $result->fetch_assoc();
$stmt->close();

include '../../menu.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buch bearbeiten</title>
    <link rel="stylesheet" href="../../style.css">
</head>
<body>
    <h1>Buch bearbeiten</h1>

    <?php if ($message): ?>
        <div class="popup <?php echo ($redirect ? 'success' : 'error'); ?>" style="display:block;"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form action="" method="POST">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
        <input type="hidden" name="book_id" value="<?php echo $bookId; ?>">

        <label for="titel">Titel</label>
        <input type="text" name="titel" id="titel" placeholder="Titel" value="<?php echo htmlspecialchars($book['titel']); ?>">

        <label for="autor">Autor</label>
        <input type="text" name="autor" id="autor" placeholder="Autor" value="<?php echo htmlspecialchars($book['autor']); ?>">

        <label for="mindestalter">Lesealter</label>
        <input type="text" name="mindestalter" id="mindestalter" placeholder="Lesealter" value="<?php echo htmlspecialchars($book['mindestalter']); ?>">

        <label for="erscheinungsjahr">Erscheinungsjahr</label>
        <input type="text" name="erscheinungsjahr" id="erscheinungsjahr" placeholder="Erscheinungsjahr" value="<?php echo htmlspecialchars($book['erscheinungsjahr']); ?>">

        <label for="verlag">Verlag</label>
        <input type="text" name="verlag" id="verlag" placeholder="Verlag" value="<?php echo htmlspecialchars($book['verlag']); ?>">

        <label for="isbn">ISBN</label>
        <input type="text" name="isbn" id="isbn" placeholder="ISBN" value="<?php echo htmlspecialchars($book['isbn']); ?>">

        <label for="ort">Standort</label>
        <input type="text" name="ort" id="ort" placeholder="Standort" value="<?php echo htmlspecialchars($book['ort']); ?>">

        <label for="barcode">Barcode</label>
        <input type="text" name="barcode" id="barcode" placeholder="Barcode" value="<?php echo htmlspecialchars($book['barcode']); ?>">

        <label for="bildlink">Cover</label>
        <input type="text" name="bildlink" id="bildlink" placeholder="Cover" value="<?php echo htmlspecialchars($book['bildlink']); ?>">

        <input type="submit" name="save_book" value="Speichern">
    </form>

    <script>
        const popup = document.querySelector('.popup');
        if (popup) {
            setTimeout(() => {
                popup.style.display = 'none';
                <?php if ($redirect): ?>
                window.location.href = 'view_books.php';
                <?php endif; ?>
            }, 6000);
        }
    </script>
</body>
</html>
