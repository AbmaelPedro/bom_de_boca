<?php
// Configurações do Banco de Dados no InfinityFree

// **ATENÇÃO: SUBSTITUA APENAS ESTAS 4 LINHAS**
define('DB_HOST', 'sql309.infinityfree.com'); 
define('DB_USER', 'if0_40240276');
define('DB_PASS', 'pedro190517');
define('DB_NAME', 'if0_40240276_pedidos_mp');

// Função para estabelecer a conexão
function db_connect() {
    // Usando a classe mysqli para conexão orientada a objetos
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Verifica se houve erro de conexão
    if ($conn->connect_error) {
        // Registra o erro em um log do servidor (melhor que exibir na tela)
        error_log("Erro de conexão com o banco de dados: " . $conn->connect_error);
        
        // Exibe uma mensagem genérica para o usuário
        http_response_code(500); 
        die(json_encode(['success' => false, 'message' => 'Erro interno de servidor.']));
    }

    // Define o charset para evitar problemas com acentuação
    $conn->set_charset("utf8mb4");

    return $conn;
}

// A conexão não será feita aqui. O db_connect() será chamado quando necessário.
?>