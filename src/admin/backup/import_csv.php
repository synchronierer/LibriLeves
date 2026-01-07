<?php
// src/admin/backup/import_csv.php
session_start();
require_once __DIR__ . '/../../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('forbidden'); }
if (!hash_equals($_SESSION['csrf_backup'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('csrf'); }
if (!isset($_POST['ack'])) { http_response_code(400); exit('ack required'); }

// Tabellenliste lesen
$tables = [];
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_array()) { $tables[] = $row[0]; }

$conn->query("SET FOREIGN_KEY_CHECKS=0");

// alle Tabellen leeren (Struktur bleibt)
foreach ($tables as $t) { $conn->query("TRUNCATE TABLE `{$t}`"); }

function load_csv_into_table(mysqli $conn, string $table, string $filePath): array {
    $handle = fopen($filePath, 'r');
    if (!$handle) return ['ok'=>false,'msg'=>'datei nicht lesbar'];

    // BOM entfernen
    $first = fgets($handle);
    if ($first === false) { fclose($handle); return ['ok'=>false,'msg'=>'leere datei']; }
    $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
    $headers = str_getcsv($first, ';');
    if (!$headers || count($headers) === 0) { fclose($handle); return ['ok'=>false,'msg'=>'keine header']; }

    // Prepared Insert bauen
    $cols = array_map(function($h){ return '`' . trim($h) . '`'; }, $headers);
    $place = implode(',', array_fill(0, count($headers), '?'));
    $sql = "INSERT INTO `{$table}` (" . implode(',', $cols) . ") VALUES ($place)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { fclose($handle); return ['ok'=>false,'msg'=>'stmt fehlgeschlagen: '.$conn->error]; }

    // Typen dynamisch als string binden (einfach/robust)
    $types = str_repeat('s', count($headers));
    $bind = [];
    $bind[] = & $types;
    $values = array_fill(0, count($headers), null);
    for ($i=0; $i<count($headers); $i++) { $bind[] = & $values[$i]; }
    call_user_func_array([$stmt, 'bind_param'], $bind);

    // Restzeilen lesen
    $rownum = 1;
    while (($line = fgets($handle)) !== false) {
        $rownum++;
        $fields = str_getcsv($line, ';');
        if ($fields === null) continue;

        // Feldanzahl an Header anpassen (kürzen/auffüllen)
        if (count($fields) < count($headers)) {
            $fields = array_pad($fields, count($headers), null);
        } elseif (count($fields) > count($headers)) {
            $fields = array_slice($fields, 0, count($headers));
        }

        // Werte zuweisen (NULL-Handling)
        for ($i=0; $i<count($headers); $i++) {
            $v = $fields[$i];
            // leere Strings als NULL importieren? -> hier: leer bleibt leerer String
            $values[$i] = $v;
        }
        if (!$stmt->execute()) {
            // Du kannst hier Logging ergänzen
        }
    }
    fclose($handle);
    $stmt->close();
    return ['ok'=>true,'msg'=>'ok'];
}

// hochgeladene Dateien verarbeiten
if (!isset($_FILES['csvfiles'])) { $_SESSION['flash_error'] = "Keine Dateien übermittelt."; header('Location:index.php'); exit; }

$files = $_FILES['csvfiles'];
$count = is_array($files['name']) ? count($files['name']) : 0;
$handled = 0;

for ($i=0; $i<$count; $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) { continue; }
    $name = $files['name'][$i];
    $tmp  = $files['tmp_name'][$i];

    // Tabellenname aus Dateiname ableiten (basename ohne .csv)
    $base = pathinfo($name, PATHINFO_FILENAME);
    if (!in_array($base, $tables, true)) { continue; }

    $r = load_csv_into_table($conn, $base, $tmp);
    if ($r['ok']) $handled++;
}

$conn->query("SET FOREIGN_KEY_CHECKS=1");
$_SESSION['flash_success'] = "CSV-Import abgeschlossen. Verarbeitete Dateien: {$handled}.";
header('Location: index.php'); exit;
