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
$password  = isset($_GET['password']) ? trim($_GET['password']) : '';

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

if ($mysqli->connect_errno) {
    echo json_encode(["status" => "error", "message" => "Eroare de conectare la baza de date MySQL!", "accounts" => []]);
    exit;
}

$mysqli->set_charset("utf8");

// --- ACTIUNEA 1: ASOCIERE / VERIFICARE SECURIZATA NICKNAME ---
if ($action === 'add' && !empty($new_nick)) {
    if (empty($google_id) && empty($email)) {
        echo json_encode(["status" => "error", "message" => "Trebuie sa fii logat cu Google pentru a asocia un cont!"]);
        $mysqli->close();
        exit;
    }

    // Verificam daca nickname-ul exista deja in baza de date MySQL
    $check_stmt = $mysqli->prepare("SELECT id, google_id, password, salt FROM accounts WHERE name = ? LIMIT 1");
    $check_stmt->bind_param("s", $new_nick);
    $check_stmt->execute();
    $res = $check_stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $existing_gid = $row['google_id'];
        $db_pass      = $row['password'];
        $db_salt      = $row['salt'];

        // Cazul 1: Contul apartine deja altui Google ID
        if (!empty($existing_gid) && $existing_gid !== $google_id) {
            echo json_encode(["status" => "error", "message" => "🔒 Securitate: Acest cont de joc este deja asociat altui cont Google!"]);
            $check_stmt->close();
            $mysqli->close();
            exit;
        }

        // Cazul 2: Contul este deja legat de acest Google ID
        if (!empty($existing_gid) && $existing_gid === $google_id) {
            echo json_encode(["status" => "success", "message" => "Contul este deja asociat cu succes la contul tau Google."]);
            $check_stmt->close();
            $mysqli->close();
            exit;
        }

        // Cazul 3: Contul exista pe server dar nu este inca legat de Google -> Cerem/Verificam Parola!
        if (empty($password)) {
            echo json_encode(["status" => "need_password", "message" => "Introdu parola contului din joc pentru a confirma ca iti aparține!"]);
            $check_stmt->close();
            $mysqli->close();
            exit;
        }

        // Verificam parola SHA256 cu salt
        $input_hash = strtoupper(hash('sha256', $password . $db_salt));

        // Daca nu se potriveste hash-ul SHA256, verificam si md5 / plain ca fallback
        if (strtoupper($input_hash) !== strtoupper($db_pass) && strtoupper(hash('sha256', $password)) !== strtoupper($db_pass) && $password !== $db_pass) {
            echo json_encode(["status" => "error", "message" => "❌ Parola incorecta! Nu s-a putut confirma proprietatea contului."]);
            $check_stmt->close();
            $mysqli->close();
            exit;
        }

        // Parola e corecta! Asociem contul la Google ID
        $update_stmt = $mysqli->prepare("UPDATE accounts SET google_id = ?, email = ? WHERE name = ?");
        $update_stmt->bind_param("sss", $google_id, $email, $new_nick);
        $update_stmt->execute();
        $update_stmt->close();

        echo json_encode(["status" => "success", "message" => "✅ Cont verificat si asociat cu succes la contul tau Google!"]);
    } else {
        // Cazul 4: Contul e nou (nu exista inca pe server) -> Se va creea la prima intrare
        echo json_encode(["status" => "success", "message" => "✨ Cont nou pregatit! Va fi creat si asociat la prima intrare pe server."]);
    }

    $check_stmt->close();
    $mysqli->close();
    exit;
}

// --- ACTIUNEA 2: PRELOARE LISTA CONTURI VERIFICATE ---
if (empty($email) && empty($google_id)) {
    echo json_encode(["status" => "error", "message" => "Parametri lipsa", "accounts" => []]);
    exit;
}

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
