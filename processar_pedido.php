<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

try {
    // ============================================================
    // LÊ OS DADOS JSON ENVIADOS PELO FRONTEND
    // ============================================================
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);

    if (!$data || !isset($data['action'])) {
        throw new Exception("Requisição inválida ou parâmetro 'action' ausente.");
    }

    $action = $data['action'];

    // ============================================================
    // AÇÃO 1 - GERAR CHECKOUT PRO (PAGAMENTO ONLINE)
    // ============================================================
    if ($action === 'gerar_checkout_pro') {
        $valor = floatval($data['valor'] ?? 0);
        $nome = trim($data['nome'] ?? '');
        $email = trim($data['email'] ?? '');
        $itens = $data['itens'] ?? [];

        if ($valor <= 0 || empty($nome) || empty($email)) {
            throw new Exception("Dados insuficientes para gerar o pedido.");
        }

        // Gera um identificador único para o pedido
        $external_reference = uniqid('ref_', true);

        // ==============================
        // SALVA PEDIDO NO BANCO
        // ==============================
        $stmt = $conn->prepare("
            INSERT INTO pedidos (
                external_reference, mp_payment_id, metodo_pagamento, valor,
                nome_cliente, email_cliente, telefone, endereco, referencia, status
            ) VALUES (?, NULL, ?, ?, ?, ?, '', '', '', ?)
        ");
        $metodo_pagamento = 'pix_cartao';
        $status = 'Pendente';

        $stmt->bind_param(
            "ssdsss",
            $external_reference,
            $metodo_pagamento,
            $valor,
            $nome,
            $email,
            $status
        );

        if (!$stmt->execute()) {
            throw new Exception("Erro ao salvar pedido: " . $stmt->error);
        }

        // ==============================
        // SALVA ITENS NO BANCO
        // ==============================
        if (!empty($itens)) {
            $stmtItem = $conn->prepare("
                INSERT INTO pedidos_mp_itens (external_reference, item_nome, quantidade, preco_unitario)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($itens as $item) {
                $nomeItem = $item['name'] ?? '';
                $quantidade = intval($item['qty'] ?? 0);
                $preco = floatval($item['price'] ?? 0);

                if ($quantidade > 0 && $nomeItem !== '') {
                    $stmtItem->bind_param("ssid", $external_reference, $nomeItem, $quantidade, $preco);
                    $stmtItem->execute();
                }
            }
        }

        // ==============================
        // INTEGRAÇÃO COM MERCADO PAGO
        // ==============================
        require __DIR__ . '/vendor/autoload.php';
        MercadoPago\SDK::setAccessToken("APP_USR-7170515652730920-102314-0adadb09c0bc3daf5ddf303ada8b8a05-526901378");

        $preference = new MercadoPago\Preference();

        $mpItems = [];
        foreach ($itens as $item) {
            $mpItem = new MercadoPago\Item();
            $mpItem->title = $item['name'];
            $mpItem->quantity = intval($item['qty']);
            $mpItem->unit_price = floatval($item['price']);
            $mpItems[] = $mpItem;
        }

        $preference->items = $mpItems;
        $preference->external_reference = $external_reference;

        // ============================================================
        // USANDO UMA ÚNICA PÁGINA DE RETORNO PARA TODOS OS STATUS
        // ============================================================
        $retorno_url = "https://seusite.com/retorno_mercadopago.php"; // substitua pelo seu domínio real
        $preference->back_urls = [
            "success" => $retorno_url,
            "failure" => $retorno_url,
            "pending" => $retorno_url
        ];
        $preference->auto_return = "approved";

        $preference->save();

        $redirectUrl = $preference->init_point;

        echo json_encode([
            'success' => true,
            'message' => 'Checkout Pro gerado com sucesso.',
            'redirect_url' => $redirectUrl,
            'external_reference' => $external_reference
        ]);
        exit;
    }

    // ============================================================
    // AÇÃO 2 - FINALIZAR PEDIDO (DINHEIRO / CARTÃO NA ENTREGA)
    // ============================================================
    if ($action === 'finalizar_pedido') {
        $nome = trim($data['nome'] ?? '');
        $email = trim($data['email'] ?? '');
        $telefone = trim($data['telefone'] ?? '');
        $endereco = trim($data['endereco'] ?? '');
        $referencia = trim($data['referencia'] ?? '');
        $valor = floatval($data['total_valor_final'] ?? 0);
        $forma_pagamento = trim($data['forma_pagamento'] ?? '');
        $itens = $data['itens_pedido'] ?? [];

        if (empty($nome) || empty($forma_pagamento) || $valor <= 0) {
            throw new Exception("Dados insuficientes para salvar o pedido.");
        }

        $external_reference = uniqid('ref_', true);
        $status = 'Pendente';

        $stmt = $conn->prepare("
            INSERT INTO pedidos (
                external_reference, mp_payment_id, metodo_pagamento, valor,
                nome_cliente, email_cliente, telefone, endereco, referencia, status
            ) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssdssssss",
            $external_reference,
            $forma_pagamento,
            $valor,
            $nome,
            $email,
            $telefone,
            $endereco,
            $referencia,
            $status
        );

        if (!$stmt->execute()) {
            throw new Exception("Erro ao salvar pedido: " . $stmt->error);
        }

        if (!empty($itens)) {
            $stmtItem = $conn->prepare("
                INSERT INTO pedidos_mp_itens (external_reference, item_nome, quantidade, preco_unitario)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($itens as $item) {
                $nomeItem = $item['name'] ?? '';
                $quantidade = intval($item['qty'] ?? 0);
                $preco = floatval($item['price'] ?? 0);

                if ($quantidade > 0 && $nomeItem !== '') {
                    $stmtItem->bind_param("ssid", $external_reference, $nomeItem, $quantidade, $preco);
                    $stmtItem->execute();
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Pedido salvo com sucesso.',
            'external_reference' => $external_reference
        ]);
        exit;
    }

    // ============================================================
    // AÇÃO DESCONHECIDA
    // ============================================================
    throw new Exception("Ação não reconhecida: $action");

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
?>
