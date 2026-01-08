<?php
// src/admin/books/view_books.php
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
        WHERE b.titel LIKE ? OR b.autor LIKE ? OR b.isbn LIKE ? OR b.id LIKE ?
        GROUP BY b.id";
$likeTerm = '%' . $filterTerm . '%';
$stmt = $conn->prepare($sql);
$stmt->bind_param('ssss', $likeTerm, $likeTerm, $likeTerm, $likeTerm);
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
    <style>
        /* Seite */
        .page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 10px 12px 40px;
        }
        .page h1 { text-align: center; }

        /* Meldungen fix anzeigen */
        .popup { display: block; }

        /* Filter */
        .filter-form {
            max-width: 900px;
            margin: 10px auto 18px;
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
        }
        .filter-form input[type="text"] {
            flex: 1 1 360px;
        }

        /* Raster */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 14px;
        }

        /* Karte */
        .card {
            position: relative;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,.06);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 100%;
        }

        /* Status-Badge oben rechts */
        .badge {
            position: absolute;
            top: 10px; right: 10px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.85em;
            font-weight: 700;
        }
        .badge.available { background: #e6f4ea; color: #137333; }
        .badge.loaned    { background: #fde7e9; color: #c5221f; }

        /* Cover */
        .cover {
            width: 100%;
            height: 180px;
            background: #f7f7f7;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid #eee;
        }
        .cover img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: block;
        }
        .cover .no-cover {
            color: #999;
            font-size: 0.95em;
        }

        /* Inhalt */
        .content {
            padding: 12px 12px 4px;
            display: grid;
            gap: 6px;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .title {
            font-weight: 700;
            font-size: 1.05em;
            color: #333;
        }
        .line { color: #444; font-size: 0.95em; }
        .label { color: #666; font-weight: 600; }

        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }

        /* Aktionen */
        .actions {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: flex-end;
            padding: 10px 12px 12px;
            border-top: 1px solid #eee;
            margin-top: auto; /* an den unteren Rand der Karte drücken */
            background: #fff;
        }

        /* Kleinere Screens: Inhalt etwas kompakter */
        @media (max-width: 480px) {
            .cover { height: 160px; }
            .title { font-size: 1em; }
            .line { font-size: 0.92em; }
        }
    </style>
</head>
<body>
    <div class="page">
        <h1>Bestand anzeigen</h1>

        <?php if ($deletesuccess): ?>
            <div class="popup success"><?php echo htmlspecialchars($deletesuccess); ?></div>
        <?php endif; ?>
        <?php if ($deleteerror): ?>
            <div class="popup error"><?php echo htmlspecialchars($deleteerror); ?></div>
        <?php endif; ?>

        <form action="" method="get" class="filter-form">
            <input type="text" name="filterTerm" placeholder="Bücher filtern (Titel, Autor, ISBN, ID)" value="<?php echo htmlspecialchars($filterTerm); ?>">
            <input type="submit" value="Filtern">
        </form>

        <?php if (empty($books)): ?>
            <p style="text-align:center; color:#666;">Keine Bücher gefunden.</p>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($books as $book): ?>
                    <?php
                        $isLoaned = ($book['bestand'] === 'ausgeliehen');
                        $badgeCls = $isLoaned ? 'loaned' : 'available';
                        $badgeLbl = $isLoaned ? 'ausgeliehen' : 'verfügbar';
                        $bild = trim((string)($book['bildlink'] ?? ''));
                        $hasCover = $bild !== '';
                    ?>
                    <div class="card">
                        <span class="badge <?php echo $badgeCls; ?>"><?php echo htmlspecialchars($badgeLbl); ?></span>

                        <div class="cover">
                            <?php if ($hasCover): ?>
                                <img src="<?php echo htmlspecialchars($bild); ?>" alt="Cover">
                            <?php else: ?>
                                <div class="no-cover">Kein Cover vorhanden</div>
                            <?php endif; ?>
                        </div>

                        <div class="content">
                            <div class="title"><?php echo htmlspecialchars($book['titel']); ?></div>
                            <div class="line"><span class="label">Autor:</span> <?php echo htmlspecialchars($book['autor']); ?></div>
                            <div class="line"><span class="label">Mindestalter:</span> <?php echo htmlspecialchars($book['mindestalter']); ?></div>
                            <div class="line"><span class="label">Erscheinungsjahr:</span> <?php echo htmlspecialchars($book['erscheinungsjahr']); ?></div>
                            <div class="line"><span class="label">Verlag:</span> <?php echo htmlspecialchars($book['verlag']); ?></div>
                            <div class="line mono"><span class="label">ISBN:</span> <?php echo htmlspecialchars($book['isbn']); ?></div>
                            <div class="line"><span class="label">Standort:</span> <?php echo htmlspecialchars($book['ort']); ?></div>
                            <div class="line mono"><span class="label">Barcode:</span> <?php echo htmlspecialchars($book['barcode']); ?></div>
                        </div>

                        <div class="actions">
                            <form action="delete_book.php" method="POST" class="delete-form" onsubmit="return confirm('Dieses Buch wirklich löschen?');" style="display:inline;">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                <input type="hidden" name="book_id" value="<?php echo (int)$book['id']; ?>">
                                <button type="submit" class="delete-button">Löschen</button>
                            </form>
                            <a href="edit_book.php?id=<?php echo (int)$book['id']; ?>" class="button">Bearbeiten</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Popups automatisch ausblenden
        setTimeout(() => {
            document.querySelectorAll('.popup').forEach(p => p.style.display = 'none');
        }, 6000);
    </script>
</body>
</html>
