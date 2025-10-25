// Arquivo: script.js
// Lógica de navegação, validação, cálculo de total, e Checkout Pro.

// Variáveis Globais de Estado
let currentStep = 1;
const totalInput = document.getElementById('total-valor-final'); 
let pixMonitorInterval = null; 
let externalReferenceId = null; 

// =========================================================
// FUNÇÕES DE UTILIDADE E MÁSCARA
// =========================================================

function maskTelefone(value) {
    value = value.replace(/\D/g, ""); 
    value = value.replace(/^(\d{2})(\d)/g, "($1) $2"); 
    value = value.replace(/(\d{5})(\d)/, "$1-$2");
    if (value.length > 15) {
        value = value.substring(0, 15);
    }
    return value;
}

// =========================================================
// FUNÇÕES DE VALIDAÇÃO E NAVEGAÇÃO
// =========================================================

function validarPasso2() {
    const nome = document.getElementById('nome').value.trim();
    const email = document.getElementById('email').value.trim();
    const telefone = document.getElementById('telefone').value.trim();
    
    const regexTelefone = /^\(\d{2}\) \d{5}-\d{4}$/; 
    const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    const isValid = nome !== '' && 
                    email.match(regexEmail) && 
                    regexTelefone.test(telefone); 
    
    document.querySelector('.step[data-step="2"] .btn-next').disabled = !isValid;
}

function updateStep(newStep) {
    if (pixMonitorInterval) {
        clearInterval(pixMonitorInterval); 
    }
    
    document.querySelector(`.step[data-step="${currentStep}"]`).classList.remove('active');
    document.querySelector(`.step[data-step="${newStep}"]`).classList.add('active');
    currentStep = newStep;
    window.scrollTo(0, 0); 
    
    if (newStep === 5) {
        preencherRevisao();
        // Garante que o estado de pagamento é avaliado ao entrar no passo 5
        handlePaymentChange(document.getElementById('forma-pagamento').value);
    }
}

/**
 * Funçao auxiliar que encapsula a lógica de mudança de pagamento
 */
function handlePaymentChange(forma) {
    const isPix = forma === 'pix';
    const isDinheiro = forma === 'dinheiro';
    const isCartao = forma.includes('cartao');
    const valorTotal = parseFloat(totalInput.value);

    // Esconde todas as áreas de pagamento
    document.getElementById('area-pix').classList.add('hidden');
    document.getElementById('opcoes-dinheiro').classList.add('hidden');
    document.getElementById('opcoes-cartao').classList.add('hidden');

    const btnEnviar = document.getElementById('btn-enviar');
    const btnPagarPix = document.getElementById('btn-pagar-pix');

    // Estado padrão dos botões
    btnEnviar.style.display = 'block'; 
    btnPagarPix.style.display = 'none'; 
    
    // Se a opção for "-- Selecione --" ou valor zero, desabilita o envio
    if (forma === "" || valorTotal <= 0) {
        btnEnviar.disabled = true; 
        if (pixMonitorInterval) clearInterval(pixMonitorInterval);
        return;
    }
    
    // Lógica para cada forma de pagamento
    if (isPix) {
        document.getElementById('area-pix').classList.remove('hidden');
        btnEnviar.style.display = 'none'; // Esconde o botão Enviar padrão
        btnPagarPix.style.display = 'block'; // Mostra o botão Pagar com PIX
        btnPagarPix.disabled = false;
        if (pixMonitorInterval) clearInterval(pixMonitorInterval); 

    } else if (isDinheiro || isCartao) {
        if (isDinheiro) document.getElementById('opcoes-dinheiro').classList.remove('hidden');
        if (isCartao) document.getElementById('opcoes-cartao').classList.remove('hidden');
        btnEnviar.disabled = false; 
        if (pixMonitorInterval) clearInterval(pixMonitorInterval); 
    } 
}


function calcularTotal() {
    let total = 0;
    let totalItens = 0;
    const items = [];
    
    document.querySelectorAll('.item-qty').forEach(select => {
        const qty = parseInt(select.value, 10) || 0; 
        const itemElement = select.closest('.menu-item');
        const price = parseFloat(itemElement.getAttribute('data-price')) || 0; 
        const name = select.getAttribute('data-name');
        
        if (qty > 0) {
            const subtotal = qty * price;
            total += subtotal;
            totalItens += qty;
            items.push({ name, qty, price, subtotal: subtotal.toFixed(2) });
        }
    });

    const btnRevisao = document.getElementById('btn-revisao');
    btnRevisao.disabled = totalItens <= 0;

    document.getElementById('total-itens').textContent = totalItens;
    document.getElementById('total-valor-display').textContent = `R$ ${total.toFixed(2).replace('.', ',')}`;
    document.getElementById('total-final-pagamento').textContent = `R$ ${total.toFixed(2).replace('.', ',')}`;

    document.getElementById('total_itens_final').value = total.toFixed(2);
    document.getElementById('total-valor-final').value = total.toFixed(2);
    
    return items;
}

function preencherRevisao() {
    const items = calcularTotal(); 
    const revisaoDiv = document.getElementById('revisao-itens');
    revisaoDiv.innerHTML = '<h3>Seu Pedido:</h3>';
    
    if (items.length === 0) {
        revisaoDiv.innerHTML += '<p style="color: var(--color-error);">Nenhum item selecionado!</p>';
        return;
    }
    
    items.forEach(item => {
        const p = document.createElement('p');
        p.textContent = `${item.qty}x ${item.name} - R$ ${item.subtotal.replace('.', ',')}`;
        revisaoDiv.appendChild(p);
    });
}


// =========================================================
// LÓGICA DO CHECKOUT PRO (Redirecionamento)
// =========================================================

async function gerarCheckoutPro() {
    const btnPagarPix = document.getElementById('btn-pagar-pix');
    btnPagarPix.disabled = true;
    btnPagarPix.textContent = 'Gerando link de pagamento...';
    
    const valor = parseFloat(document.getElementById('total-valor-final').value);
    const nome = document.getElementById('nome').value;
    const email = document.getElementById('email').value;
    const itens = calcularTotal(); 
    
    if (valor <= 0) {
        alert('O total do pedido é zero. Adicione itens.');
        btnPagarPix.disabled = false;
        btnPagarPix.textContent = 'Pagar com PIX no Mercado Pago';
        return;
    }

    try {
        const response = await fetch('processar_pedido.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'gerar_checkout_pro', // NOVA AÇÃO NO PHP
                valor: valor, 
                nome: nome, 
                email: email,
                itens: itens
            })
        });

        const data = await response.json();

        if (data.success) {
            externalReferenceId = data.external_reference; // Salva a ref gerada
            // REDIRECIONAMENTO PARA O MERCADO PAGO
            window.location.href = data.redirect_url;
            
        } else {
            // Se o backend PHP retornou um JSON com success: false
            alert('Erro ao gerar o Checkout Pro: ' + data.message);
            btnPagarPix.disabled = false;
            btnPagarPix.textContent = 'Pagar com PIX no Mercado Pago';
        }

    } catch (error) {
        console.error('Erro na requisição AJAX (gerar_checkout_pro):', error);
        // Este catch pega erros de rede ou servidor (PHP parou)
        alert('Erro de rede ou servidor ao gerar pagamento. Tente novamente.');
        btnPagarPix.disabled = false;
        btnPagarPix.textContent = 'Pagar com PIX no Mercado Pago';
    }
}

// =========================================================
// INICIALIZAÇÃO E EVENT LISTENERS
// =========================================================

document.addEventListener('DOMContentLoaded', () => {
    
    // --- Lógica de Retorno do Mercado Pago (pós-redirecionamento) ---
    const urlParams = new URLSearchParams(window.location.search);
    const statusMP = urlParams.get('status');
    const refId = urlParams.get('ref');

    if (statusMP) {
        let msg = '';
        if (statusMP === 'success') {
            msg = '✅ Pagamento Aprovado! Seu pedido será enviado para a cozinha.';
        } else if (statusMP === 'pending') {
            msg = '⏳ Pagamento Pendente. Estamos aguardando a confirmação do PIX.';
        } else if (statusMP === 'failure') {
            msg = '❌ O pagamento falhou ou foi cancelado. Por favor, tente novamente ou escolha outra forma de pagamento.';
        }
        
        alert(msg);
        
        // Limpa os parâmetros da URL após o alerta
        history.replaceState(null, '', window.location.pathname);
    }
    // ------------------------------------------------------------------

    // --- Navegação ---
    document.querySelectorAll('.btn-next').forEach(button => {
        button.addEventListener('click', () => updateStep(currentStep + 1));
    });

    document.querySelectorAll('.btn-prev').forEach(button => {
        button.addEventListener('click', () => updateStep(currentStep - 1));
    });

    // --- Validação e Máscara ---
    document.getElementById('nome').addEventListener('input', validarPasso2);
    document.getElementById('email').addEventListener('input', validarPasso2);
    document.getElementById('telefone').addEventListener('input', function(e) {
        e.target.value = maskTelefone(e.target.value);
        validarPasso2(); 
    });
    
    // --- Cálculo e Total (Passo 4 - Pedidos) ---
    document.querySelectorAll('.item-qty').forEach(select => {
        select.addEventListener('change', calcularTotal); 
        select.addEventListener('change', () => {
             if (currentStep === 5) {
                 preencherRevisao();
                 handlePaymentChange(document.getElementById('forma-pagamento').value);
             }
        });
    });
    
    calcularTotal(); 

    // --- Lógica de Pagamento (Passo 5) ---
    document.getElementById('forma-pagamento').addEventListener('change', (e) => {
        handlePaymentChange(e.target.value);
    });
    
    // NOVO: Listener para o botão Pagar com PIX
    document.getElementById('btn-pagar-pix').addEventListener('click', gerarCheckoutPro);

    // --- Submissão Final do Formulário (Dinheiro/Cartão) ---
    document.getElementById('pedidoForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formaPagamento = document.getElementById('forma-pagamento').value;
        const btnEnviar = document.getElementById('btn-enviar');

        // Impede submissão se for PIX (pois deve ser feito via botão dedicado)
        if (formaPagamento === 'pix') {
             alert('Para PIX, use o botão "Pagar com PIX no Mercado Pago".');
             return;
        }
        // Impede submissão se nada foi selecionado
        if (formaPagamento === "") {
             return;
        }

        btnEnviar.disabled = true;
        btnEnviar.textContent = 'Enviando...';
        
        const pedidoData = {
            action: 'finalizar_pedido',
            forma_pagamento: formaPagamento,
            external_reference: externalReferenceId, // Caso seja PIX já pago, usa a ref
            nome: document.getElementById('nome').value,
            email: document.getElementById('email').value,
            telefone: document.getElementById('telefone').value,
            endereco: document.getElementById('endereco').value,
            referencia: document.getElementById('referencia').value,
            total_valor_final: totalInput.value,
            total_itens_final: document.getElementById('total_itens_final').value,
            itens_pedido: calcularTotal()
        };

        try {
            const response = await fetch('processar_pedido.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(pedidoData)
            });
            const finalData = await response.json();

            if (finalData.success) {
                alert('🎉 Pedido Recebido com Sucesso! Um e-mail/contato de confirmação será enviado em breve.');
                window.location.reload(); 
            } else {
                alert('Erro ao finalizar pedido: ' + finalData.message);
                btnEnviar.disabled = false;
                btnEnviar.textContent = 'Enviar Pedido';
            }
        } catch (error) {
            console.error('Erro na requisição AJAX (finalizar_pedido):', error);
            alert('Erro de rede ao finalizar pedido. Tente novamente.');
            btnEnviar.disabled = false;
            btnEnviar.textContent = 'Enviar Pedido';
        }
    });

    // --- Botão Limpar Pedido ---
    document.getElementById('btn-clean').addEventListener('click', () => {
        if (pixMonitorInterval) {
            clearInterval(pixMonitorInterval);
        }
        window.location.reload();
    });

    updateStep(1);
    validarPasso2(); 
});