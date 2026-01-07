<?php
// src/admin/backup/import_sql.php
session_start();
require_once __DIR__ . '/../../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('forbidden'); }
if (!hash_equals($_SESSION['csrf_backup'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('csrf'); }
if (!isset($_POST['ack'])) { http_response_code(400); exit('ack required'); }

if (!isset($_FILES['sqlfile']) || $_FILES['sqlfile']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400); exit('upload error');
}

$path = $_FILES['sqlfile']['tmp_name'];
$sql  = file_get_contents($path);
if ($sql === false || $sql === '') { http_response_code(400); exit('empty file'); }

// Alles droppen, dann Import
$conn->query("SET FOREIGN_KEY_CHECKS=0");
$tables = [];
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_array()) { $tables[] = $row[0]; }
foreach ($tables as $t) { $conn->query("DROP TABLE IF EXISTS `{$t}`"); }

// Mehrbefehls-Ausführung
$conn->multi_query($sql);
do { /* alle Ergebnisse abholen */ } while ($conn->more_results() && $conn->next_result());
$conn->query("SET FOREIGN_KEY_CHECKS=1");

$_SESSION['flash_success'] = "SQL-Import abgeschlossen.";
header('Location: index.php'); exit;
