<?php
// ============================================================
// Conexão com o Banco de Dados MySQL (InfinityFree)
// ============================================================

$servername = "sql309.infinityfree.com";
$username   = "if0_40240276";
$password   = "pedro190517";
$dbname     = "if0_40240276_pedidos_mp";

// Cria a conexão
$conn = new mysqli($servername, $username, $password, $dbname);

// Verifica falha na conexão
if ($conn->connect_errno) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro de conexão com o banco: " . $conn->connect_error
    ]);
    exit;
}

// Define charset para evitar erros de acentuação
$conn->set_charset("utf8mb4");
?>
