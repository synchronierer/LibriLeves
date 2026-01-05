<?php
session_start();
include('../db.php');
include('../menu.php');

// Buchsuche
$searchBook = isset($_GET['searchBook']) ? $_GET['searchBook'] : '';
$selectedBooks = isset($_SESSION['selectedBooks']) ? $_SESSION['selectedBooks'] : [];

// Benutzerauswahl
$selectedUserId = isset($_SESSION['selectedUserId']) ? $_SESSION['selectedUserId'] : '';

// Ausleihen
if (isset($_POST['loan'])) {
    $user_id = $_SESSION['selectedUserId'];
    $book_ids = $selectedBooks;

    foreach ($book_ids as $book_id) {
        // Ausleihe durchführen
        $loan_date = date('Y-m-d H:i:s');
        $return_date = date('Y-m-d H:i:s', strtotime('+4 weeks'));

        $sql = "INSERT INTO loans (user_id, book_id, loan_date, return_date) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $user_id, $book_id, $loan_date, $return_date);
        $stmt->execute();
    }

    // Auswahl zurücksetzen
    unset($_SESSION['selectedBooks']);
    unset($_SESSION['selectedUserId']);

    // Weiterleitung zur Übersicht der ausgeliehenen Bücher
    header('Location: ausleihe.php');
    exit();
}

// Aktive Ausleihen abrufen
$activeLoans = "SELECT book_id FROM loans WHERE return_date IS NULL";
$activeLoansResult = $conn->query($activeLoans);

$alreadyLoanedBooks = [];
while ($loan = $activeLoansResult->fetch_assoc()) {
    $alreadyLoanedBooks[] = $loan['book_id'];
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Bücher auswählen</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <h1>Bücher auswählen</h1>
    <form action="" method="GET">
        <input type="text" name="searchBook" placeholder="Titel, Autor oder ISBN" value="<?php echo htmlspecialchars($searchBook); ?>">
        <input type="submit" value="Suchen">
    </form>

    <h3>Suchergebnisse</h3>
    <form method="POST">
        <ul>
            <?php
            if ($searchBook) {
                $searchQuery = "SELECT * FROM books WHERE titel LIKE ? OR autor LIKE ? OR isbn LIKE ? OR barcode LIKE ?";
                $stmt = $conn->prepare($searchQuery);
                $searchTerm = '%' . $searchBook . '%';
                $stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
                $stmt->execute();
                $result = $stmt->get_result();

                while ($book = $result->fetch_assoc()) {
                    $checked = in_array($book['id'], $selectedBooks) ? 'checked' : '';
                    $disabled = in_array($book['id'], $alreadyLoanedBooks) ? 'disabled' : ''; // Disable if already loaned
                    $label = in_array($book['id'], $alreadyLoanedBooks) ? " (Bereits ausgeliehen)" : ""; // Hinweis

                    echo "<li>
                            <input type='checkbox' name='book_ids[]' value='{$book['id']}' $checked $disabled> 
                            {$book['titel']} von {$book['autor']} $label
                          </li>";
                }
            }
            ?>
        </ul>
        <input type="submit" name="loan" value="Ausleihen" <?php echo empty($selectedUserId) || empty($selectedBooks) ? 'disabled' : ''; ?>>
    </form>
</body>
</html>