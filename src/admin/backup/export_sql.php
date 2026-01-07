<?php
// src/admin/backup/export_sql.php
session_start();
require_once __DIR__ . '/../../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('forbidden'); }
if (!hash_equals($_SESSION['csrf_backup'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('csrf'); }

$dbname = 'leseecke';
$conn->set_charset('utf8mb4');

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="leseecke_backup_' . date('Ymd_His') . '.sql"');

echo "-- LibriLeves SQL Dump\n";
echo "-- Datenbank: {$dbname}\n";
echo "-- Erzeugt am: " . date('c') . "\n\n";
echo "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n";
echo "START TRANSACTION;\n\n";

$tables = [];
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_array()) { $tables[] = $row[0]; }

foreach ($tables as $table) {
    // DROP + CREATE
    $row = $conn->query("SHOW CREATE TABLE `{$table}`")->fetch_assoc();
    $create = $row['Create Table'] ?? $row['Create View'] ?? '';
    echo "DROP TABLE IF EXISTS `{$table}`;\n";
    if ($create) {
        echo $create . ";\n\n";
    } else {
        // falls View/Sonderfall – dann nur vorsichtshalber
        echo "-- WARN: Kein CREATE für {$table} gefunden\n\n";
    }

    // INSERTs
    $result = $conn->query("SELECT * FROM `{$table}`");
    if ($result && $result->num_rows > 0) {
        $columns = [];
        $fields = $result->fetch_fields();
        foreach ($fields as $f) { $columns[] = "`{$f->name}`"; }
        $colList = '(' . implode(',', $columns) . ')';

        $batch = [];
        $count = 0;
        $batchSize = 500;

        $result->data_seek(0);
        while ($row = $result->fetch_assoc()) {
            $vals = [];
            foreach ($row as $val) {
                if ($val === null) { $vals[] = "NULL"; }
                else { $vals[] = "'" . $conn->real_escape_string($val) . "'"; }
            }
            $batch[] = '(' . implode(',', $vals) . ')';
            $count++;

            if (count($batch) >= $batchSize) {
                echo "INSERT INTO `{$table}` {$colList} VALUES\n" . implode(",\n", $batch) . ";\n";
                $batch = [];
            }
        }
        if (!empty($batch)) {
            echo "INSERT INTO `{$table}` {$colList} VALUES\n" . implode(",\n", $batch) . ";\n";
        }
        echo "\n";
    }
}

echo "COMMIT;\n";
echo "SET FOREIGN_KEY_CHECKS=1;\n";
