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

// Desativa a exibição de erros críticos na saída para evitar JSON inválido
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// ====================================================\r\n
// DADOS DE CONFIGURAÇÃO OBRIGATÓRIOS
// ====================================================\r\n

// Seu Access Token (Chave Privada)
MercadoPagoConfig::setAccessToken('APP_USR-8765a7f1-8cd0-4412-a9d6-734e07bbe088'); 

// URL pública do seu arquivo de Webhook
$NOTIFICATION_URL = 'http://bomdeboca.wuaze.com/webhook_mp.php'; 

// URL de retorno para o formulário após o pagamento
$RETURN_URL = 'http://bomdeboca.wuaze.com/index.php';

// ====================================================\r\n
// FUNÇÕES: Salvar Pedidos no Banco de Dados
// ====================================================\r\n

/**
 * Insere um novo pedido na tabela principal 'pedidos_mp'.
 * @return bool Retorna true em sucesso, false em falha.
 */
function salvarPedido(mysqli $conn, $valor, $nome, $email, $externalReference, $status, $pagamento, $telefone, $endereco, $referencia) {
    // AJUSTADO PARA USAR SUA TABELA: pedidos_mp
    $sql = "INSERT INTO pedidos_mp (external_reference, valor_total, nome_cliente, email_cliente, telefone, endereco, referencia, status, pagamento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Erro no prepare (salvarPedido): " . $conn->error);
        return false;
    }

    // Assumindo que os campos existem na sua tabela pedidos_mp
    $stmt->bind_param("sdsssssss", $externalReference, $valor, $nome, $email, $telefone, $endereco, $referencia, $status, $pagamento);

    $success = $stmt->execute();
    if (!$success) {
        error_log("Erro ao executar (salvarPedido): " . $stmt->error);
    }
    
    $stmt->close();
    return $success;
}

/**
 * Insere os itens detalhados do pedido na tabela 'pedidos_mp_itens'.
 * @param array $itens Array de itens do pedido (name, qty, price).
 * @return bool Retorna true em sucesso, false em falha.
 */
function salvarItensPedido(mysqli $conn, $externalReference, array $itens) {
    if (empty($itens)) {
        return true; 
    }
    
    $success = true;
    // AJUSTADO PARA USAR TABELA DE ITENS: pedidos_mp_itens
    $sql = "INSERT INTO pedidos_mp_itens (external_reference, item_nome, quantidade, preco_unitario) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Erro no prepare (salvarItensPedido): " . $conn->error);
        // O erro de rede/servidor pode estar aqui, se a tabela não existir.
        return false; 
    }
    
    foreach ($itens as $item) {
        $itemNome = $item['name'];
        $quantidade = (int)$item['qty'];
        $precoUnitario = (float)$item['price'];
        
        $stmt->bind_param("ssis", $externalReference, $itemNome, $quantidade, $precoUnitario); 
        
        if (!$stmt->execute()) {
            error_log("Erro ao executar item: " . $stmt->error);
            $success = false;
        }
    }
    
    $stmt->close();
    return $success;
}


// ====================================================\r\n
// LÓGICA DE REQUISIÇÃO
// ====================================================\r\n

// Obtém os dados da requisição POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['action'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Ação não especificada.']));
}

$action = $data['action'];

// ====================================================\r\n
// AÇÃO: GERAR CHECKOUT PRO (PIX, CARTÃO)
// ====================================================\r\n
if ($action === 'gerar_checkout_pro') {
    
    $conn = db_connect();
    
    if (!$conn) {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Falha na conexão com o BD ao gerar checkout.']));
    }

    $externalReference = 'MP-' . time() . rand(100, 999);
    
    // Mapeamento de dados de contato (mesmo que vazios)
    $valor = $data['valor'] ?? 0.00;
    $nome = $data['nome'] ?? 'Cliente MP';
    $email = $data['email'] ?? 'sem_email_mp@bomboca.com';
    $itens_pedido = $data['itens'] ?? [];
    
    // Para MP, endereço, telefone e referência ficam vazios inicialmente
    $telefone = '';
    $endereco = '';
    $referencia = '';

    // Tenta SALVAR O PEDIDO NO BANCO DE DADOS ANTES DE ENVIAR PARA O MP
    if (!salvarPedido($conn, $valor, $nome, $email, $externalReference, 'Pendente de Pagamento', 'pix_cartao', $telefone, $endereco, $referencia)) {
        $conn->close();
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Falha ao registrar pedido no BD (pré-MP).']));
    }
    
    // Tenta SALVAR OS ITENS do pedido
    if (!salvarItensPedido($conn, $externalReference, $itens_pedido)) {
        error_log("ATENÇÃO: Falha ao salvar itens para o pedido MP: " . $externalReference);
        // Não encerramos o processo, mas a ausência da tabela 'pedidos_mp_itens' PODE ESTAR CAUSANDO SEU ERRO DE REDE.
    }
    
    $conn->close();
    
    // LÓGICA DO CHECKOUT PRO
    $client = new PreferenceClient();
    
    try {
        $preference = $client->create([
            "items" => array_map(function($item) {
                return [
                    "title" => $item['name'],
                    "quantity" => $item['qty'],
                    "unit_price" => (float)$item['price'],
                    "currency_id" => "BRL",
                ];
            }, $itens_pedido),
            "payer" => [
                "name" => $nome,
                "email" => $email,
            ],
            "external_reference" => $externalReference,
            "back_urls" => [
                "success" => $RETURN_URL . "?status=success&ref=" . $externalReference,
                "pending" => $RETURN_URL . "?status=pending&ref=" . $externalReference,
                "failure" => $RETURN_URL . "?status=failure&ref=" . $externalReference,
            ],
            "notification_url" => $NOTIFICATION_URL,
            "auto_return" => "approved",
        ]);
        
        echo json_encode([
            'success' => true,
            'redirect_url' => $preference->init_point,
            'external_reference' => $externalReference
        ]);
        
    } catch (\Exception $e) {
        error_log("Erro Mercado Pago: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao criar preferência de pagamento no Mercado Pago.']);
    }
} 

// ====================================================\r\n
// AÇÃO: FINALIZAR PEDIDO (DINHEIRO, DÉBITO, CRÉDITO)
// ====================================================\r\n
else if ($action === 'finalizar_pedido') {

    $conn = db_connect();

    if (!$conn) {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Falha na conexão com o BD. Verifique db_connect.php.']));
    }
    
    // Mapeamento de dados recebidos do JS
    $pagamento = $data['forma_pagamento'] ?? 'dinheiro'; 
    $totalValorFinal = $data['total_valor_final'] ?? 0;
    
    $nome = $data['nome'] ?? 'Cliente';
    $email = $data['email'] ?? 'sem_email@bomboca.com';
    $telefone = $data['telefone'] ?? '';
    $endereco = $data['endereco'] ?? '';
    $referencia = $data['referencia'] ?? '';
    $itens_pedido = $data['itens_pedido'] ?? [];
    
    $externalReference = 'NAO-MP-' . time() . rand(100, 999);
    
    $status = 'Aguardando Pagamento na Entrega'; 

    // Tenta SALVAR O PEDIDO PRINCIPAL
    if (!salvarPedido($conn, $totalValorFinal, $nome, $email, $externalReference, $status, $pagamento, $telefone, $endereco, $referencia)) {
        $conn->close();
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Falha ao registrar pedido de Pagamento na Entrega no BD.']));
    }

    // Tenta SALVAR OS ITENS do pedido
    if (!salvarItensPedido($conn, $externalReference, $itens_pedido)) {
        error_log("ATENÇÃO: Falha ao salvar itens para o pedido: " . $externalReference);
        // Este erro será tratado como um log e o pedido principal será marcado como sucesso para o cliente.
    }

    $conn->close();
    
    // Retorna SUCESSO para o JavaScript
    echo json_encode(['success' => true, 'message' => 'Pedido finalizado com sucesso.']);
}

// ====================================================\r\n
// AÇÃO INVÁLIDA
// ====================================================\r\n
else {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Ação não reconhecida.']));
}
?>