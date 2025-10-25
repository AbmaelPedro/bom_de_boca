<?php
// O Webhook não retorna HTML ou JSON para o navegador. 
// Ele deve apenas processar a notificação e responder um status HTTP 200 OK.

// Inclui o arquivo de conexão com o banco de dados
require 'db_connect.php'; 

// Inclui o autoload do Composer para usar as classes do Mercado Pago, se necessário
// require 'vendor/autoload.php';

// =========================================================
// 1. OBTENÇÃO DOS DADOS DA NOTIFICAÇÃO
// =========================================================

// O Mercado Pago envia o conteúdo da notificação no corpo da requisição POST
$input = file_get_contents('php://input');
$notification_data = json_decode($input, true);

// Registra a notificação em um arquivo de log para debug
// É crucial para entender o que o MP está enviando
$log_file = 'webhook_log.txt';
file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Notificação Recebida:\n" . print_r($notification_data, true) . "\n---\n", FILE_APPEND);


// =========================================================
// 2. VERIFICAR SE É UMA NOTIFICAÇÃO VÁLIDA E DE PAGAMENTO
// =========================================================

// O MP envia diferentes tipos de notificações (pagamento, estorno, etc.)
// Focamos em 'payment'
if (empty($notification_data) || $notification_data['type'] !== 'payment') {
    // Se não for um tipo de pagamento, ignora e responde 200 OK
    http_response_code(200); 
    exit;
}

$resource_url = $notification_data['data']['id'] ?? null; 
$payment_id = $notification_data['data']['id'] ?? null; 

// Nota: A MP geralmente envia o ID do pagamento como 'data.id' no corpo JSON.
if (!$payment_id) {
    file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "ERRO: ID do pagamento não encontrado na notificação.\n---\n", FILE_APPEND);
    http_response_code(200); // Sempre retorna 200 para evitar que o MP tente enviar novamente
    exit;
}

// =========================================================
// 3. CONSULTAR O STATUS REAL DO PAGAMENTO NA API (Segurança)
// =========================================================

// Esta é a MELHOR PRÁTICA de segurança: não confie apenas no Webhook.
// Consulte o MP para confirmar o status.

// A SDK do MP precisa do Access Token novamente
use MercadoPago\MercadoPagoConfig;
MercadoPagoConfig::setAccessToken('SEU_ACCESS_TOKEN_AQUI'); // Use o mesmo token que usou em processar_pedido.php

try {
    $client = new MercadoPago\Client\Payment\PaymentClient();
    $payment = $client->get($payment_id);
    
    // Pega a referência externa que usamos para identificar o pedido no seu BD
    $external_reference = $payment->external_reference; 
    
    // Traduz o status do MP para o seu sistema
    $new_status = 'Desconhecido';

    switch ($payment->status) {
        case 'approved':
            $new_status = 'Pago';
            break;
        case 'pending':
            $new_status = 'Pendente';
            break;
        case 'in_process':
            $new_status = 'Em Processamento';
            break;
        case 'rejected':
        case 'cancelled':
        case 'refunded':
        case 'charged_back':
            $new_status = 'Cancelado/Rejeitado';
            break;
        default:
            $new_status = $payment->status;
            break;
    }

    // =========================================================
    // 4. ATUALIZAR O BANCO DE DADOS
    // =========================================================
    $conn = db_connect();

    $stmt = $conn->prepare("UPDATE pedidos SET status = ?, mp_payment_id = ? WHERE external_reference = ?");
    $stmt->bind_param("sss", $new_status, $payment_id, $external_reference);
    
    if ($stmt->execute()) {
        file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "SUCESSO: Pedido {$external_reference} atualizado para '{$new_status}' (MP ID: {$payment_id}).\n---\n", FILE_APPEND);
    } else {
        file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "ERRO BD: Falha ao atualizar pedido {$external_reference}. Erro: {$conn->error}\n---\n", FILE_APPEND);
    }

    $stmt->close();
    $conn->close();

} catch (\Exception $e) {
    file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "ERRO API/PHP: " . $e->getMessage() . "\n---\n", FILE_APPEND);
}

// =========================================================
// 5. RESPOSTA FINAL (OBRIGATÓRIO)
// =========================================================

// O Webhook deve SEMPRE retornar um código HTTP 200 OK para o Mercado Pago
// Para que o MP saiba que a notificação foi recebida com sucesso.
http_response_code(200); 

?>