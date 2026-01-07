<?php
// src/admin/backup/index.php
session_start();
require_once __DIR__ . '/../../db.php';

// Admin-Check
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ../login.php'); exit;
}

// CSRF Token
if (empty($_SESSION['csrf_backup'])) {
    $_SESSION['csrf_backup'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_backup'];

// Tabellenliste für CSV
$tables = [];
$res = $conn->query("SHOW TABLES");
if ($res) {
    while ($row = $res->fetch_array()) {
        $tables[] = $row[0];
    }
}

include '../../menu.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Admin – Sicherung</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../../style.css">
  <style>
    .backup-wrap {
      max-width: 1200px;
      margin: 18px auto;
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 18px;
    }
    @media (max-width: 1000px) {
      .backup-wrap { grid-template-columns: 1fr; }
    }
    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 18px;
      box-shadow: var(--shadow);
      min-width: 0;
    }
    .card h2, .card h3 {
      text-align: left !important;
      margin: 0 0 8px 0;
    }
    .muted { color: var(--ink-muted); }
    .warn  { color: #b45309; }
    .btn-row {
      display: flex; flex-wrap: wrap; gap: 10px;
      align-items: center; margin: 10px 0 0 0;
    }
    .button.sm { padding: 8px 12px; font-size: 0.95rem; border-radius: 8px; }
    .delete-button.sm { padding: 8px 12px; font-size: 0.95rem; border-radius: 8px; }

    /* CSV-Tabellenliste als Grid */
    .table-list {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 10px;
      margin-top: 10px;
    }
    .table-item {
      display: flex; align-items: center; justify-content: space-between; gap: 8px;
      padding: 8px 10px; border: 1px solid var(--border); border-radius: 8px; background: #fff;
      min-width: 0;
    }
    .table-item .name {
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
      font-weight: 600; color: var(--ink);
    }
    .table-item .button.sm { white-space: nowrap; }
    .note { margin-top: 6px; }
    .file-input { max-width: 100%; }
  </style>
</head>
<body>
  <h1>Sicherung</h1>

  <div class="backup-wrap">

    <!-- Linke Karte: SQL (Export + Import) -->
    <section class="card">
      <h2>SQL – Export & Import</h2>
      <p class="muted">Kompletter Dump als SQL (DROP/CREATE/INSERT) und vollständiger Import (überschreibt die gesamte Datenbank).</p>

      <h3>Export (SQL)</h3>
      <form method="post" action="export_sql.php" class="btn-row">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="submit" class="button sm" value="SQL-Dump herunterladen">
      </form>

      <h3 style="margin-top:16px;">Import (SQL)</h3>
      <p class="warn"><strong>Achtung:</strong> Überschreibt die gesamte Datenbank. Alle Tabellen werden vorher gelöscht.</p>
      <form method="post" action="import_sql.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <input class="file-input" type="file" name="sqlfile" accept=".sql" required>
        <div class="btn-row">
          <label><input type="checkbox" name="ack" required> Bestätigen</label>
          <input type="submit" class="delete-button sm" value="SQL importieren (überschreibt alles)">
        </div>
      </form>
    </section>

    <!-- Rechte Karte: CSV (Export + Import) -->
    <section class="card">
      <h2>CSV – Export & Import</h2>
      <p class="muted">Pro Tabelle eine CSV mit Spaltenköpfen. Import leert die Tabelleninhalte und füllt aus den Dateien.</p>

      <h3>Export (CSV je Tabelle)</h3>
      <div class="table-list">
        <?php foreach ($tables as $t): ?>
          <div class="table-item">
            <span class="name"><?php echo htmlspecialchars($t); ?></span>
            <a class="button sm" href="export_csv.php?table=<?php echo urlencode($t); ?>" target="_blank">CSV herunterladen</a>
          </div>
        <?php endforeach; ?>
      </div>

      <h3 style="margin-top:16px;">Import (CSV)</h3>
      <p class="warn"><strong>Achtung:</strong> Leert die Tabellen und importiert aus den ausgewählten CSVs. Reihenfolge egal – Fremdschlüssel werden temporär deaktiviert.</p>
      <p class="muted note">Dateinamen: <code>tablename.csv</code> (erste Zeile = Spaltennamen). Mehrere Dateien wählbar.</p>
      <form method="post" action="import_csv.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <input class="file-input" type="file" name="csvfiles[]" accept=".csv,text/csv" multiple required>
        <div class="btn-row">
          <label><input type="checkbox" name="ack" required> Bestätigen</label>
          <input type="submit" class="delete-button sm" value="CSV importieren (Inhalte überschreiben)">
        </div>
      </form>
    </section>

  </div>
</body>
</html>
