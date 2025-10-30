<?php
require_once 'db_connect.php';
require __DIR__ . '/vendor/autoload.php'; // SDK do Mercado Pago

// Configura o Access Token
MercadoPago\SDK::setAccessToken("APP_USR-7170515652730920-102314-0adadb09c0bc3daf5ddf303ada8b8a05-526901378");

// Recebe o external_reference enviado pelo Checkout Pro
$external_reference = $_GET['external_reference'] ?? '';

if (!$external_reference) {
    echo "Pedido não informado.";
    exit;
}

// ==============================
// BUSCA O PAGAMENTO NO MERCADO PAGO
// ==============================
$search = MercadoPago\Payment::search([
    "qs" => ["external_reference" => $external_reference]
]);

$payments = $search["results"] ?? [];

if (empty($payments)) {
    echo "Nenhum pagamento encontrado para este pedido.";
    exit;
}

// Considera o primeiro pagamento retornado
$payment = $payments[0];
$status = $payment->status; // approved, pending, rejected etc.

// ==============================
// ATUALIZA O STATUS NO BANCO
// ==============================
$stmt = $conn->prepare("UPDATE pedidos SET status=? WHERE external_reference=?");
$stmt->bind_param("ss", $status, $external_reference);
$stmt->execute();

// ==============================
// MOSTRA MENSAGEM PARA O USUÁRIO
// ==============================
switch ($status) {
    case "approved":
        $msg = "Pagamento aprovado! Obrigado pelo seu pedido.";
        break;
    case "pending":
        $msg = "Pagamento pendente. Assim que confirmarmos, seu pedido será processado.";
        break;
    case "rejected":
        $msg = "Pagamento rejeitado. Tente novamente ou entre em contato conosco.";
        break;
    default:
        $msg = "Status do pagamento: {$status}.";
        break;
}

echo "<h2>{$msg}</h2>";
echo "<p>Pedido: {$external_reference}</p>";
?>
