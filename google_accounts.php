<?xml version="1.0" encoding="utf-8"?>
<?php
header('Content-Type: application/json; charset=utf-8');

// Configuration for MySQL Database gs112097
$db_host = "127.0.0.1";
$db_port = 3306;
$db_user = "gs112097";
$db_pass = "Sergiu199";
$db_name = "gs112097";

$email     = isset($_GET['email']) ? trim($_GET['email']) : '';
$google_id = isset($_GET['google_id']) ? trim($_GET['google_id']) : '';
$action    = isset($_GET['action']) ? trim($_GET['action']) : '';
$new_nick  = isset($_GET['nickname']) ? trim($_GET['nickname']) : '';

// Conectare la baza de date MySQL
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

if ($mysqli->connect_errno) {
    echo json_encode(["status" => "error", "message" => "Eroare conectare MySQL", "accounts" => []]);
    exit;
}

$mysqli->set_charset("utf8");

// Actiunea de adaugare/asociere cont nou Nickname la Google ID
if ($action === 'add' && !empty($new_nick)) {
    $stmt = $mysqli->prepare("UPDATE accounts SET google_id = ?, email = ? WHERE name = ?");
    if ($stmt) {
        $stmt->bind_param("sss", $google_id, $email, $new_nick);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(["status" => "success", "message" => "Cont asociat cu succes"]);
    $mysqli->close();
    exit;
}

if (empty($email) && empty($google_id)) {
    echo json_encode(["status" => "error", "message" => "Parametri lipsa", "accounts" => []]);
    exit;
}

// Cautam conturile de joc din tabela `accounts` potrivite cu Email-ul sau Google ID-ul
$query = "SELECT name, level, skin FROM accounts WHERE (google_id = ? AND google_id != '') OR (email = ? AND email != '')";
$stmt = $mysqli->prepare($query);

if ($stmt) {
    $stmt->bind_param("ss", $google_id, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    $accounts = [];
    while ($row = $result->fetch_assoc()) {
        $accounts[] = [
            "nickname" => $row['name'],
            "level"    => isset($row['level']) ? (int)$row['level'] : 1,
            "skin"     => isset($row['skin']) ? (int)$row['skin'] : 0
        ];
    }

    echo json_encode([
        "status"   => "success",
        "accounts" => $accounts
    ]);

    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Eroare interogare SQL", "accounts" => []]);
}

$mysqli->close();
?>
