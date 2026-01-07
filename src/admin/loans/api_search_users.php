<?php
// src/admin/loans/api_search_users.php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/security.php';

start_secure_session();
require_admin();

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['ok' => true, 'results' => []]);
    exit;
}

$like = '%' . $q . '%';
$sql = "SELECT id, name, vorname, email
        FROM users
        WHERE name LIKE ? OR vorname LIKE ? OR email LIKE ?
        ORDER BY vorname, name
        LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $like, $like, $like);
$stmt->execute();
$res = $stmt->get_result();

$results = [];
while ($row = $res->fetch_assoc()) {
    $results[] = [
        'id'      => (int)$row['id'],
        'name'    => $row['name'],
        'vorname' => $row['vorname'],
        'email'   => $row['email'],
    ];
}
$stmt->close();

echo json_encode(['ok' => true, 'results' => $results]);
