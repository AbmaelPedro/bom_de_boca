<?php
require_once 'db_connect.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

$statusFiltro = $_GET['status'] ?? '';
$busca = $_GET['busca'] ?? '';

$sql = "SELECT * FROM pedidos WHERE 1=1";
if ($statusFiltro !== '') {
    $sql .= " AND status = '" . $conn->real_escape_string($statusFiltro) . "'";
}
if ($busca !== '') {
    $busca = $conn->real_escape_string($busca);
    $sql .= " AND (nome_cliente LIKE '%$busca%' OR external_reference LIKE '%$busca%')";
}
$sql .= " ORDER BY data_criacao DESC";

$pedidos = $conn->query($sql);

while ($pedido = $pedidos->fetch_assoc()) {
    echo "<tr>
        <td>{$pedido['id']}</td>
        <td>" . htmlspecialchars($pedido['nome_cliente']) . "</td>
        <td>" . htmlspecialchars($pedido['email_cliente']) . "</td>
        <td>" . htmlspecialchars($pedido['telefone']) . "</td>
        <td>" . htmlspecialchars($pedido['endereco']) . "</td>
        <td>R$ " . number_format($pedido['valor'], 2, ',', '.') . "</td>
        <td class='status-" . str_replace([' ', '/', '-'], '_', $pedido['status']) . "'>{$pedido['status']}</td>
        <td>{$pedido['metodo_pagamento']}</td>
        <td>{$pedido['external_reference']}</td>
        <td>" . date('d/m/Y H:i', strtotime($pedido['data_criacao'])) . "</td>
    </tr>";

    $ref = $pedido['external_reference'];
    $itens = $conn->query("SELECT * FROM pedidos_mp_itens WHERE external_reference = '$ref'");
    if ($itens->num_rows > 0) {
        echo "<tr class='itens'><td colspan='10'><strong>Itens do Pedido:</strong><ul>";
        while ($item = $itens->fetch_assoc()) {
            echo "<li>" . htmlspecialchars($item['item_nome']) . " — {$item['quantidade']} un × R$ " . number_format($item['preco_unitario'], 2, ',', '.') . "</li>";
        }
        echo "</ul></td></tr>";
    }
}
?>
