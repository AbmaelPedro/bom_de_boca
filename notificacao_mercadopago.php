<?php
require_once 'db_connect.php';
require __DIR__ . '/vendor/autoload.php'; // SDK do Mercado Pago

// Configura o Access Token
MercadoPago\SDK::setAccessToken("SEU_ACCESS_TOKEN_AQUI");

// ==============================
// RECEBE OS DADOS DA NOTIFICAÇÃO
// ==============================
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Verifica se veio o ID do pagamento
$payment_id = $data['data']['id'] ?? null;

if (!$payment_id) {
    http_response_code(400);
    echo "ID de pagamento não recebido.";
    exit;
}

// ==============================
// BUSCA O PAGAMENTO NO MERCADO PAGO
// ==============================
try {
    $payment = MercadoPago\Payment::find_by_id($payment_id);
} catch (Exception $e) {
    http_response_code(500);
    echo "Erro ao buscar pagamento: " . $e->getMessage();
    exit;
}

if (!$payment) {
    http_response_code(404);
    echo "Pagamento não encontrado.";
    exit;
}

// ==============================
// EXTRAI DADOS DO PAGAMENTO
// ==============================
$status = $payment->status;
$external_reference = $payment->external_reference;
$payment_type = $payment->payment_type_id;
$payment_method = $payment->payment_method_id;

// ==============================
// ATUALIZA O PEDIDO NO BANCO
// ==============================
$stmt = $conn->prepare("
    UPDATE pedidos
    SET status = ?, mp_payment_id = ?, metodo_pagamento = ?
    WHERE external_reference = ?
");
$stmt->bind_param("ssss", $status, $payment_id, $payment_type, $external_reference);
$stmt->execute();

// ==============================
// REGISTRA LOG (OPCIONAL)
// ==============================
file_put_contents('logs/ipn_log.txt', date('Y-m-d H:i:s') . " | Pedido: $external_reference | Status: $status | ID: $payment_id\n", FILE_APPEND);

// ==============================
// RETORNA SUCESSO PARA O MERCADO PAGO
// ==============================
http_response_code(200);
echo "Notificação processada com sucesso.";
?>
