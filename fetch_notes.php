<?php
require_once 'config.php';

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

$lastId = (int)($_GET['last_id'] ?? 0);

$allNotes = fetchNotes();
$newNotes = array_values(array_filter($allNotes, function($n) use ($lastId) {
    return (int)$n['Id'] > $lastId;
}));

echo json_encode(['ok' => true, 'notes' => $newNotes]);
