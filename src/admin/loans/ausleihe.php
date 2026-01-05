<?php
session_start();
include('../../db.php');

// Überprüfe, ob der Benutzer eingeloggt ist und Admin-Rechte hat
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI']; // Speichere die aktuelle URL
    header("Location: ../login.php");
    exit();
}

// Meldung aus der Session abrufen
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
unset($_SESSION['message']); // Nachricht nach dem Abrufen löschen

// Benutzer abrufen
$users = [];
$sql = "SELECT id, name, vorname FROM users";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Bücher abrufen mit optionaler Suche
$books = [];
$searchBook = isset($_GET['searchBook']) ? $_GET['searchBook'] : '';
$likeTerm = "%" . $searchBook . "%";
$sql = "SELECT id, titel, autor, barcode FROM books WHERE ? = '' OR titel LIKE ? OR isbn LIKE ? OR barcode LIKE ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $searchBook, $likeTerm, $likeTerm, $likeTerm);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $books[] = $row;
}

// Ausleihen
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['loan'])) {
    $user_id = $_POST['user_id'];
    $book_id = $_POST['book_id'];

    // Überprüfe, ob das Buch bereits ausgeliehen ist
    $checkLoan = "SELECT * FROM loans WHERE book_id = ? AND return_date > NOW()";
    $checkStmt = $conn->prepare($checkLoan);
    $checkStmt->bind_param("i", $book_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        $_SESSION['message'] = "Das Buch ist bereits ausgeliehen.";
        header('Location: ausleihe.php'); // Zurück zur Seite nach dem Eintrag
        exit();
    } else {
        // Ausleihe durchführen
        $loan_date = date('Y-m-d H:i:s');
        $return_date = date('Y-m-d H:i:s', strtotime('+4 weeks'));

        $sql = "INSERT INTO loans (user_id, book_id, loan_date, return_date) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $user_id, $book_id, $loan_date, $return_date);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Die Ausleihe war erfolgreich.";
        } else {
            $_SESSION['message'] = "Fehler beim Ausleihen des Buches: " . $conn->error;
        }

        header('Location: ausleihe.php'); // Zurück zur Seite nach dem Eintrag
        exit();
    }
}

// Ausgeliehene Bücher abrufen
$activeLoans = [];
$sql = "SELECT loans.loan_id, users.name, users.vorname, books.titel, books.barcode, loans.return_date 
        FROM loans 
        JOIN users ON loans.user_id = users.id 
        JOIN books ON loans.book_id = books.id 
        WHERE loans.return_date > NOW()";
$activeLoansResult = $conn->query($sql);
while ($row = $activeLoansResult->fetch_assoc()) {
    $activeLoans[] = $row;
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ausleihe - Bücherverwaltung</title>
    <link rel="stylesheet" href="../../style.css">
  
</head>
<body>
    <?php include '../../menu.php'; ?>
	<br>
    <h1>Neue Ausleihe eintragen</h1>

    

    <form action="" method="POST">
        <label for="user_id">Benutzer:</label>
        <input type="text" id="userSearch" placeholder="Suche Benutzer" onkeyup="searchUser()">
        <select name="user_id" id="user_id" required>
            <?php foreach ($users as $user): ?>
                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['vorname'] . ' ' . $user['name']); ?></option>
            <?php endforeach; ?>
        </select>
		<br>
		<br>
        <label for="book_id">Buch:</label>
        <input type="text" id="bookSearch" placeholder="Suche Buch, ISBN oder Barcode" onkeyup="searchBook()">
        <select name="book_id" id="book_id" required>
            <?php foreach ($books as $book): ?>
                <option value="<?php echo $book['id']; ?>"><?php echo htmlspecialchars($book['titel'] . ' ' . 'Barcode:' .  $book['barcode']); ?></option>
            <?php endforeach; ?>
        </select>
		<br>
		<br>
        <input type="submit" name="loan" value="Ausleihen">
    </form>
	
<!-- Meldungsbox -->
    <?php if ($message): ?>
        <div class="message-box">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
   
   <h2>Aktive Ausleihen</h2>
    

<table class="loansTable">
    <thead>
        <tr>
            <th>Titel</th>
            <th>Barcode</th>
            <th>Benutzer</th>
            <th>Rückgabedatum</th>
            <th>Rückgabe</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($activeLoans as $loan): ?>
            <tr>
                <td><?php echo htmlspecialchars($loan['titel']); ?></td>
                <td><?php echo htmlspecialchars($loan['barcode']); ?></td>
                <td><?php echo htmlspecialchars($loan['vorname'] . ' ' . $loan['name']); ?></td>
                <td><?php echo htmlspecialchars($loan['return_date']); ?></td>
                <td>
                    <form action="return.php" method="POST" class="return-form">
                        <input type="hidden" name="loan_id" value="<?php echo $loan['loan_id']; ?>">
                        <input type="submit" value="Rückgabe">
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>



    <!-- Popup für Meldungen -->
    <div id="popup" class="popup"></div>

    <script>
        // Funktion zur Suche nach Benutzern
        function searchUser() {
            var input = document.getElementById('userSearch').value.toLowerCase();
            var select = document.getElementById('user_id');
            for (var i = 0; i < select.options.length; i++) {
                var option = select.options[i];
                var text = option.text.toLowerCase();
                option.style.display = text.includes(input) ? 'block' : 'none';
            }
        }

        // Funktion zur Suche nach Büchern
        function searchBook() {
            var input = document.getElementById('bookSearch').value.toLowerCase();
            var select = document.getElementById('book_id');
            for (var i = 0; i < select.options.length; i++) {
                var option = select.options[i];
                var text = option.text.toLowerCase();
                option.style.display = text.includes(input) ? 'block' : 'none';
            }
        }

        // Funktion zur Anzeige des Popups
        function showPopup(message, isSuccess) {
            var popup = document.getElementById('popup');
            popup.textContent = message;
            popup.className = 'popup ' + (isSuccess ? 'success' : 'error');
            popup.style.display = 'block';

            setTimeout(function() {
                popup.style.display = 'none';
            }, 3000);
        }

        // Zeige das Popup bei Seitenladung, wenn eine Nachricht vorhanden ist
        <?php if ($message): ?>
            showPopup(<?php echo json_encode($message); ?>, <?php echo strpos($message, 'erfolgreich') !== false ? 'true' : 'false'; ?>);
        <?php endif; ?>
    </script>
</body>
</html>