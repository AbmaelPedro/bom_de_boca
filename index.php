<?php
// Arquivo: index.php
// O restante do arquivo continua sendo HTML puro,
// mas agora o servidor o processará como PHP, permitindo inclusão de lógica futura.
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos | Bom de Boca</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="card-form-container">
    <form id="pedidoForm"> 

        <div class="steps-container">

            <div class="step active" data-step="1">
                <div class="step-header">
                    <h1>Bem-vindo ao Bom de Boca!</h1>
                    <p>Faça seu pedido de churrasquinho agora mesmo.</p>
                </div>
                <button type="button" class="btn-next">Começar Pedido</button>
            </div>

            <div class="step" data-step="2">
                <div class="step-header">
                    <h2>Seus Dados</h2>
                    <p>Precisamos de suas informações para entrega.</p>
                </div>
                <label for="nome">Nome e Sobrenome</label>
                <input type="text" id="nome" name="nome" required>

                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" required placeholder="seu@email.com">

                <label for="telefone">Telefone com DDD</label>
                <input type="text" id="telefone" name="telefone" placeholder="(99) 99999-9999" required pattern="\(\d{2}\) \d{5}-\d{4}" maxlength="15">

                <button type="button" class="btn-prev">Voltar</button>
                <button type="button" class="btn-next" disabled>Próximo</button>
            </div>

            <div class="step" data-step="3">
                <div class="step-header">
                    <h2>Endereço de Entrega</h2>
                    <p>Entregamos apenas em sua cidade!</p>
                </div>
                <label for="endereco">Rua, Número e Bairro</label>
                <input type="text" id="endereco" name="endereco" required>

                <label for="referencia">Ponto de Referência</label>
                <input type="text" id="referencia" name="referencia" placeholder="Ex: Próximo à Praça Central">

                <button type="button" class="btn-prev">Voltar</button>
                <button type="button" class="btn-next">Próximo</button>
            </div>

            <div class="step" data-step="4">
                <div class="step-header">
                    <h2>Monte Seu Pedido</h2>
                    <p>R$ 18,00 por churrasquinho, seja ele simples ou misto!</p>
                </div>
                
                <div class="menu-item" data-price="18.00"> 
                    <label for="qtd_bovino">Bovino</label>
                    <select id="qtd_bovino" name="qtd_bovino" class="item-qty" data-name="Bovino">
                        <option value="0">0</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </div>
                
                <div class="menu-item" data-price="18.00">
                    <label for="qtd_suino">Suíno</label>
                    <select id="qtd_suino" name="qtd_suino" class="item-qty" data-name="Suíno">
                        <option value="0">0</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </div>
                <div class="menu-item" data-price="18.00">
                    <label for="qtd_frango">Frango</label>
                    <select id="qtd_frango" name="qtd_frango" class="item-qty" data-name="Frango">
                        <option value="0">0</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </div>
                <div class="menu-item" data-price="18.00">
                    <label for="qtd_bov_suino">Bovino + Suíno (Misto)</label>
                    <select id="qtd_bov_suino" name="qtd_bov_suino" class="item-qty" data-name="Bovino + Suíno">
                        <option value="0">0</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </div>
                <div class="menu-item" data-price="18.00">
                    <label for="qtd_bov_frango">Bovino + Frango (Misto)</label>
                    <select id="qtd_bov_frango" name="qtd_bov_frango" class="item-qty" data-name="Bovino + Frango">
                        <option value="0">0</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </div>
                <div class="menu-item" data-price="18.00">
                    <label for="qtd_suino_frango">Suíno + Frango (Misto)</label>
                    <select id="qtd_suino_frango" name="qtd_suino_frango" class="item-qty" data-name="Suíno + Frango">
                        <option value="0">0</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </div>
                
                <div class="total-summary">
                    <p>Total de Churrasquinhos: <span id="total-itens">0</span></p>
                    <p>Total a Pagar: <span id="total-valor-display">R$ 0,00</span></p> 
                    <input type="hidden" name="total_itens_final" id="total_itens_final">
                    <input type="hidden" name="total_valor_final" id="total-valor-final">
                </div>

                <button type="button" class="btn-prev">Voltar</button>
                <button type="button" class="btn-next" id="btn-revisao" disabled>Próximo</button>
            </div>

            <div class="step" data-step="5"> 
                <div class="step-header">
                    <h2>Forma de Pagamento</h2>
                    <p>Selecione como deseja pagar.</p>
                </div>

                <div id="revisao-itens" class="revisao-itens"></div> 
                <div class="total-summary">
                    <p>Total a Pagar: <span id="total-final-pagamento">R$ 0,00</span></p>
                </div>

                <label for="forma-pagamento">Selecione a Forma de Pagamento</label>
                <select id="forma-pagamento" name="forma_pagamento" required>
                    <option value="" selected disabled>-- Selecione --</option>
                    <option value="pix_cartao">Pix, Cartão</option> 
                    <option value="debito">Débito</option>
                    <option value="credito">Crédito</option>
                    <option value="dinheiro">Dinheiro</option>
                </select>

                <div id="area-pix-cartao" class="payment-option hidden">
                    <p class="payment-message">Você será redirecionado para o Mercado Pago para pagar com **PIX, Cartão de Crédito ou Débito**.</p>
                </div>

                <div id="opcoes-cartao" class="payment-option hidden">
                    <p class="payment-message">✅ O entregador levará a maquininha para pagamento no Cartão.</p>
                </div>

                <div id="opcoes-dinheiro" class="payment-option hidden">
                    <label>
                        <input type="checkbox" id="preciso-troco" name="levar_troco" value="sim">
                        Preciso de troco (O entregador entrará em contato para o valor).
                    </label>
                </div>
                
                <button type="button" class="btn-prev">Voltar</button>
                
                <button type="button" id="btn-pagar-checkout" class="btn-submit btn-primary" style="display: none;">Pagar com Pix/Cartão no Mercado Pago</button>
                
                <button type="submit" id="btn-enviar" class="btn-submit" disabled>Finalizar Pedido (Pagamento na Entrega)</button>
            </div>

        </div>
    </form>
    
    <button type="button" id="btn-clean" class="btn-clean" style="display: none;">Limpar Tudo</button>
</div>

<script src="script.js"></script>
</body>
</html>