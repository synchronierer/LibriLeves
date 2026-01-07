<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/security.php';

start_secure_session();
require_admin();

include '../../menu.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leseecke - Bücherverwaltung</title>
    <link rel="stylesheet" href="../../style.css">
</head>
<body>
    <h1>Bücherverwaltung</h1>

    <div class="button-container centered">
        <a href="add_via_isbn.php" class="button">Buch hinzufügen</a>
        <a href="view_books.php" class="button">Bestand pflegen</a>
        <a href="search_books.php" class="button">Automatische Nachrecherche</a>
    </div>
</body>
</html>
