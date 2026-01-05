<?php
session_start();
include('../../db.php');

// Überprüfe, ob der Benutzer eingeloggt ist und Admin-Rechte hat
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Speichere die aktuelle URL in der Sitzung
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    // Wenn nicht, leite zur Login-Seite um
    header("Location: ../login.php");
    exit();
}

// Benutzer löschen
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    $userId = intval($_POST['user_id']);
    $sql = "DELETE FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    if ($stmt->execute()) {
        echo "Benutzer erfolgreich gelöscht.";
    } else {
        echo "Fehler beim Löschen des Benutzers: " . $conn->error;
    }
}

// Benutzer speichern (bearbeiten)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_user'])) {
    $userId = intval($_POST['user_id']);
    $name = $_POST['name'] ?? ''; // Optional
    $vorname = $_POST['vorname'] ?? ''; // Optional
    $email = $_POST['email'] ?? ''; // Optional
    $password = $_POST['password'] ?? ''; // Optional
    $benutzertyp = $_POST['benutzertyp'] ?? ''; // Optional

    // Wenn ein Passwort eingegeben wird, hashen
    if (!empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET name = ?, vorname = ?, email = ?, password = ?, benutzertyp = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $name, $vorname, $email, $hashedPassword, $benutzertyp, $userId);
    } else {
        // Wenn kein Passwort eingegeben wird, nur die anderen Felder aktualisieren
        $sql = "UPDATE users SET name = ?, vorname = ?, email = ?, benutzertyp = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $name, $vorname, $email, $benutzertyp, $userId);
    }

    if ($stmt->execute()) {
        echo "Benutzer erfolgreich gespeichert.";
    } else {
        echo "Fehler beim Speichern des Benutzers: " . $conn->error;
    }
}

// Benutzer neu anlegen
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $name = $_POST['name'];
    $vorname = $_POST['vorname'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $benutzertyp = $_POST['benutzertyp'];

    // Passwort hashen
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (name, vorname, email, password, benutzertyp) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $name, $vorname, $email, $hashedPassword, $benutzertyp);
    $stmt->execute();
    echo "Benutzer erfolgreich hinzugefügt.";
}

// Benutzer abrufen
$users = [];
$searchTerm = '';
if (isset($_GET['searchTerm'])) {
    $searchTerm = $_GET['searchTerm'];
    $sql = "SELECT id, name, vorname, email, benutzertyp FROM users WHERE name LIKE ? OR vorname LIKE ? OR email LIKE ?";
    $stmt = $conn->prepare($sql);
    $likeTerm = "%" . $searchTerm . "%";
    $stmt->bind_param("sss", $likeTerm, $likeTerm, $likeTerm);
} else {
    $sql = "SELECT id, name, vorname, email, benutzertyp FROM users";
    $stmt = $conn->prepare($sql);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leseecke - Benutzerverwaltung</title>
    <link rel="stylesheet" href="../../style.css">
    <style>
        /* Benutzerergebnisse */
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px auto;
        }
        .user-table th, .user-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .user-table th {
            background-color: #ffeb3b; /* Helles Gelb */
            cursor: pointer; /* Zeiger für sortierbare Spalten */
        }
        .user-table tr:hover {
            background-color: #f1f1f1; /* Heller Hintergrund beim Hover */
        }
        .modal {
            display: none; /* Versteckt das Modal */
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgb(0,0,0);
            background-color: rgba(0,0,0,0.4);
            padding-top: 60px;
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php include '../../menu.php'; ?>

    <h1>Benutzerverwaltung</h1>

    <h2>Benutzer anlegen</h2>
    <form action="" method="POST">
        <label for="name">Name:</label>
        <input type="text" name="name" placeholder="Name" required>
        <label for="vorname">Vorname:</label>
        <input type="text" name="vorname" placeholder="Vorname" required>
        <label for="email">E-Mail:</label>
        <input type="email" name="email" placeholder="E-Mail" required>
        <label for="password">Passwort:</label>
        <input type="password" name="password" placeholder="Passwort" required>
        <label for="benutzertyp">Benutzertyp:</label>
		<br>
        <select name="benutzertyp" class="button" required>
            <option value="admin">Admin</option>
            <option value="leser">Leser</option>
        </select>
		<br>
		<br>
        <input type="submit" name="add_user" value="Benutzer hinzufügen">
    </form>

    <h2>Benutzerliste</h2>
    <form action="" method="GET">
        <input type="text" name="searchTerm" placeholder="Suche nach Name, Vorname oder E-Mail" value="<?php echo htmlspecialchars($searchTerm); ?>">
        <input type="submit" value="Suchen">
    </form>
    <table class="user-table">
        <thead>
            <tr>
                <th onclick="sortTable(0)">ID</th>
                <th onclick="sortTable(1)">Name</th>
                <th onclick="sortTable(2)">Vorname</th>
                <th onclick="sortTable(3)">E-Mail</th>
                <th onclick="sortTable(4)">Benutzertyp</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['vorname']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['benutzertyp']); ?></td>
                    <td>
                        <button onclick="openModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>', '<?php echo htmlspecialchars($user['vorname']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo htmlspecialchars($user['benutzertyp']); ?>')" class="button">Bearbeiten</button>
                        <form action="" method="POST" class="delete-form" style="display:inline;">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <button type="submit" name="delete_user" class="delete-button">Löschen</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Modal für die Bearbeitung -->
    <div id="myModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Benutzer bearbeiten</h2>
            <form id="editForm" action="" method="POST">
                <input type="hidden" name="user_id" id="modalUserId" value="">
                <label for="modalName">Name:</label>
                <input type="text" name="name" id="modalName" placeholder="Name" required>
                <label for="modalVorname">Vorname:</label>
                <input type="text" name="vorname" id="modalVorname" placeholder="Vorname" required><br>
                <label for="modalEmail">E-Mail:</label>
                <input type="email" name="email" id="modalEmail" placeholder="E-Mail" required>
                <label for="modalPassword">Passwort:</label>
                <input type="password" name="password" id="modalPassword" placeholder="Passwort (optional)">
                <label for="modalBenutzertyp">Benutzertyp:</label>
                <select name="benutzertyp" id="modalBenutzertyp" required>
                    <option value="admin">Admin</option>
                    <option value="leser">Leser</option>
                </select>
                <input type="submit" name="save_user" value="Speichern">
            </form>
        </div>
    </div>

    <script>
        function openModal(id, name, vorname, email, benutzertyp) {
            document.getElementById("modalUserId").value = id;
            document.getElementById("modalName").value = name;
            document.getElementById("modalVorname").value = vorname;
            document.getElementById("modalEmail").value = email;
            document.getElementById("modalBenutzertyp").value = benutzertyp;
            document.getElementById("myModal").style.display = "block";
        }

        function closeModal() {
            document.getElementById("myModal").style.display = "none";
        }

        function sortTable(columnIndex) {
            const table = document.querySelector(".user-table tbody");
            const rows = Array.from(table.rows);
            const isAscending = table.dataset.sortOrder === 'asc';

            rows.sort((a, b) => {
                const aText = a.cells[columnIndex].innerText;
                const bText = b.cells[columnIndex].innerText;

                return isAscending ? aText.localeCompare(bText) : bText.localeCompare(aText);
            });

            rows.forEach(row => table.appendChild(row)); // Füge die sortierten Zeilen zurück in die Tabelle ein
            table.dataset.sortOrder = isAscending ? 'desc' : 'asc'; // Aktualisiere die Sortierreihenfolge
        }
    </script>
</body>
</html>
