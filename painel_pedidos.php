<?php
require_once 'db_connect.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel de Pedidos</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 20px; }
        h1 { color: #333; }
        .filtros { margin-bottom: 20px; }
        .filtros input, .filtros select, .filtros button {
            padding: 8px;
            margin-right: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 0 5px rgba(0,0,0,0.1); }
        th, td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
        th { background-color: #f9f9f9; }
        .status-Pago { color: green; font-weight: bold; }
        .status-Pendente { color: orange; font-weight: bold; }
        .status-Cancelado,
        .status-Cancelado_Rejeitado,
        .status-Rejeitado { color: red; font-weight: bold; }
        .status-Em_Processamento { color: blue; font-weight: bold; }
        .itens { background-color: #fafafa; }
        ul { margin: 5px 0; padding-left: 20px; }
    </style>
    <script>
        function atualizarPedidos() {
            const status = document.querySelector('[name="status"]').value;
            const busca = document.querySelector('[name="busca"]').value;
            fetch(`painel_ajax.php?status=${encodeURIComponent(status)}&busca=${encodeURIComponent(busca)}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('tabela-pedidos').innerHTML = html;
                });
        }

        setInterval(atualizarPedidos, 10000);
        window.onload = atualizarPedidos;
    </script>
</head>
<body>
    <h1>Painel de Pedidos</h1>

    <form method="get" class="filtros" onsubmit="event.preventDefault(); atualizarPedidos();">
        <input type="text" name="busca" placeholder="Buscar por cliente ou referência">
        <select name="status">
            <option value="">Todos os status</option>
            <option value="Pago">Pago</option>
            <option value="Pendente">Pendente</option>
            <option value="Cancelado/Rejeitado">Cancelado/Rejeitado</option>
        </select>
        <button type="submit">Filtrar</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Email</th>
                <th>Telefone</th>
                <th>Endereço</th>
                <th>Valor</th>
                <th>Status</th>
                <th>Pagamento</th>
                <th>Referência</th>
                <th>Data</th>
            </tr>
        </thead>
        <tbody id="tabela-pedidos">
            <!-- Conteúdo será carregado via AJAX -->
        </tbody>
    </table>
</body>
</html>
