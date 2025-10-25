<?php
// Arquivo: processar_pedido.php

// Inclua o autoload do Composer (necessário para a SDK do Mercado Pago)
require 'vendor/autoload.php';
// Inclua o arquivo de conexão com o banco de dados
require 'db_connect.php'; 

use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

// Define o cabeçalho para JSON
header('Content-Type: application/json');

// ====================================================
// DADOS DE CONFIGURAÇÃO OBRIGATÓRIOS
// ====================================================

// Seu Access Token (Chave Privada)
MercadoPagoConfig::setAccessToken('APP_USR-8765a7f1-8cd0-4412-a9d6-734e07bbe088'); 

// URL pública do seu arquivo de Webhook
$NOTIFICATION_URL = 'http://bomdeboca.wuaze.com/webhook_mp.php'; 

// URL de retorno para o formulário após o pagamento
$RETURN_URL = 'http://bomdeboca.wuaze.com/index.php';

// ====================================================
// FUNÇÃO: Salvar Pedido no Banco de Dados
// ====================================================
/**
 * Insere um novo pedido no banco de dados.
 */
function salvarPedido(mysqli $conn, $valor, $nome, $email, $externalReference, $status, $pagamento) {
    
    // Assume que a tabela pedidos possui as colunas external_reference, valor, nome_cliente, email_cliente, status, forma_pagamento
    $stmt = $conn->prepare("INSERT INTO pedidos (external_reference, valor, nome_cliente, email_cliente, status, forma_pagamento) VALUES (?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        error_log("Erro na preparação da query SQL: " . $conn->error);
        return false;
    }

    $stmt->bind_param("sdssss", $externalReference, $valor, $nome, $email, $status, $pagamento); 
    
    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        error_log("Erro ao salvar pedido no BD: " . $stmt->error);
        $stmt->close();
        return false;
    }
}

// ====================================================
// PONTO DE ENTRADA: REQUISIÇÃO AJAX
// ====================================================

$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Verifica se a decodificação JSON foi bem-sucedida e a ação está definida
if (!isset($data['action'])) {
    http_response_code(400); 
    die(json_encode(['success' => false, 'message' => 'Ação não especificada ou dados de entrada inválidos.']));
}

// ----------------------------------------------------
// AÇÃO: GERAR CHECKOUT PRO (Cria o link de pagamento - Substitui o gerar_pix)
// ----------------------------------------------------
if ($data['action'] === 'gerar_checkout_pro') {
    
    $conn = db_connect(); 
    if (!$conn) {
        http_response_code(500); 
        die(json_encode(['success' => false, 'message' => 'Falha na conexão com o banco de dados (db_connect).']));
    }

    $valor = $data['valor'] ?? 0;
    $nome = $data['nome'] ?? 'Cliente Desconhecido';
    $email = $data['email'] ?? 'erro_email@bomboca.com'; 
    $itens_pedido = $data['itens'] ?? [];
    
    // 1. Geração de Referência
    $externalReference = 'PED-' . time() . rand(100, 999); 
    $titulo_item = count($itens_pedido) . " Itens do Pedido Bom de Boca";
    
    // 2. Criação dos Itens para a API do MP (Simplificado)
    $items_mp = [
        [
            "title" => $titulo_item,
            "quantity" => 1,
            "unit_price" => (float)number_format($valor, 2, '.', ''),
            "currency_id" => "BRL",
        ]
    ];

    try {
        $client = new PreferenceClient();
        
        $preference = $client->create([
            "body" => [
                "items" => $items_mp,
                "payer" => [
                    "name" => $nome,
                    "email" => $email,
                ],
                "external_reference" => $externalReference,
                "notification_url" => $NOTIFICATION_URL,
                
                // URLs para o retorno APÓS o pagamento
                "back_urls" => [
                    "success" => "{$RETURN_URL}?status=success&ref={$externalReference}",
                    "failure" => "{$RETURN_URL}?status=failure&ref={$externalReference}",
                    "pending" => "{$RETURN_URL}?status=pending&ref={$externalReference}",
                ],
                // Redireciona imediatamente após sucesso
                "auto_return" => "approved", 
            ]
        ]);
        
        // 3. Salvar o pedido no seu BD (Status 'Aguardando MP')
        if (!salvarPedido($conn, $valor, $nome, $email, $externalReference, 'Aguardando MP', 'pix_checkout')) {
             // Se falhar ao salvar, notifica no log, mas ainda retorna o link (o webhook pode salvar depois)
             error_log("AVISO: Falha ao salvar pedido 'Aguardando MP' no BD, mas link gerado.");
        }

        $conn->close();
        
        // Retorna o link para o JavaScript
        echo json_encode([
            'success' => true,
            'redirect_url' => $preference->init_point, 
            'external_reference' => $externalReference
        ]);
        exit;

    } catch (\Exception $e) {
        $conn->close();
        // Erro crítico na comunicação com a API (cURL/Token/Firewall)
        error_log("ERRO CRÍTICO Checkout Pro: " . $e->getMessage());
        http_response_code(500); 
        die(json_encode(['success' => false, 'message' => 'Erro crítico ao criar pagamento. Verifique o Access Token e o Firewall do seu servidor.']));
    }
} 

// ----------------------------------------------------
// AÇÃO: VERIFICAR STATUS DO PEDIDO (Polling)
// ----------------------------------------------------
elseif ($data['action'] === 'check_pix_status') {
    $conn = db_connect(); 
    $externalReference = $data['external_reference'] ?? null; 

    $status_pedido = 'Erro';

    if ($conn && $externalReference) {
        $stmt = $conn->prepare("SELECT status FROM pedidos WHERE external_reference = ?");
        $stmt->bind_param("s", $externalReference);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $status_pedido = $row['status'];
        }
        $stmt->close();
        $conn->close(); 
    }
    
    echo json_encode(['status' => $status_pedido]);
    exit;
}

// ----------------------------------------------------
// AÇÃO: SUBMISSÃO FINAL DO FORMULÁRIO (Dinheiro/Cartão)
// ----------------------------------------------------
elseif ($data['action'] === 'finalizar_pedido') {
    
    $conn = db_connect();
    
    if (!$conn) {
        http_response_code(500); 
        die(json_encode(['success' => false, 'message' => 'Falha na conexão com o BD ao finalizar pedido.']));
    }
    
    $pagamento = $data['forma_pagamento'] ?? 'dinheiro';
    $externalReference = $data['external_reference'] ?? null;
    $totalValorFinal = $data['total_valor_final'] ?? 0;
    
    $nome = $data['nome'] ?? 'Cliente';
    $email = $data['email'] ?? 'sem_email@bomboca.com';

    // Se a forma de pagamento NÃO for PIX, registra o pedido no BD.
    if ($pagamento !== 'pix') {
        
        $status = 'Aguardando Pagamento na Entrega'; 

        // Se a referência for nula (sempre será para Dinheiro/Cartão), cria uma nova
        if (empty($externalReference)) {
            $externalReference = 'NAO-MP-' . time() . rand(100, 999);
        }

        if (!salvarPedido($conn, $totalValorFinal, $nome, $email, $externalReference, $status, $pagamento)) {
            $conn->close();
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Falha ao registrar pedido de Dinheiro/Cartão no BD.']));
        }
    }
    
    // NOTA: O pedido PIX/Checkout Pro JÁ ESTÁ SALVO na ação 'gerar_checkout_pro'.

    $conn->close();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Pedido finalizado e registrado com sucesso!'
    ]);
    exit;
}

// Resposta padrão para qualquer requisição inválida ou desconhecida
http_response_code(400); 
echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
?>