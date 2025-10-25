// Arquivo: script.js
// Lógica de navegação, validação, cálculo de total, e Checkout Pro.

// Variáveis Globais de Estado
let currentStep = 1;
const totalInput = document.getElementById('total-valor-final'); 
let pixMonitorInterval = null; 
let externalReferenceId = null; 
const btnCleanGlobal = document.getElementById('btn-clean'); // Referência ao botão global

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
    
    const nextButton = document.querySelector('.step[data-step="2"] .btn-next');
    if (nextButton) {
        nextButton.disabled = !isValid;
    }
}

function updateStep(newStep) {
    if (pixMonitorInterval) {
        clearInterval(pixMonitorInterval); 
    }
    
    // Controle do botão Limpar Tudo (Visível apenas do Passo 2 ao 5)
    if (btnCleanGlobal) {
        btnCleanGlobal.style.display = (newStep > 1 && newStep <= 5) ? 'block' : 'none';
    }
    
    // Oculta/Mostra os passos
    if (currentStep >= 1 && currentStep <= 5) {
        const oldStepElement = document.querySelector(`.step[data-step="${currentStep}"]`);
        if(oldStepElement) oldStepElement.classList.remove('active');
    }
    if (newStep >= 1 && newStep <= 5) {
        const newStepElement = document.querySelector(`.step[data-step="${newStep}"]`);
        if(newStepElement) newStepElement.classList.add('active');
        currentStep = newStep;
    }
    
    window.scrollTo(0, 0); 
    
    if (newStep === 5) {
        preencherRevisao();
        const formaPagamentoSelect = document.getElementById('forma-pagamento');
        if (formaPagamentoSelect) {
            handlePaymentChange(formaPagamentoSelect.value);
        }
    }
}

/**
 * Funçao auxiliar que encapsula a lógica de mudança de pagamento no Passo 5
 */
function handlePaymentChange(forma) {
    const isCheckoutPro = forma === 'pix_cartao';
    const isDinheiro = forma === 'dinheiro';
    const isCartao = forma === 'credito' || forma === 'debito';
    const valorTotal = parseFloat(totalInput.value);

    // Oculta todas as áreas de opção
    document.getElementById('area-pix-cartao').classList.add('hidden');
    document.getElementById('opcoes-dinheiro').classList.add('hidden');
    document.getElementById('opcoes-cartao').classList.add('hidden');
    
    const btnEnviar = document.getElementById('btn-enviar');
    const btnPagarCheckout = document.getElementById('btn-pagar-checkout'); // Novo ID

    // Reset de exibição dos botões
    if (btnEnviar) {
        btnEnviar.style.display = 'block'; 
        btnEnviar.disabled = true; // Desabilita por padrão
    }
    if (btnPagarCheckout) btnPagarCheckout.style.display = 'none'; 
    
    // Se a opção for "-- Selecione --" ou valor zero
    if (forma === "" || valorTotal <= 0) {
        return;
    }
    
    // Lógica para cada forma de pagamento
    if (isCheckoutPro) {
        document.getElementById('area-pix-cartao').classList.remove('hidden');
        if (btnEnviar) btnEnviar.style.display = 'none'; // Esconde o botão Enviar
        if (btnPagarCheckout) {
            btnPagarCheckout.style.display = 'block'; // Mostra o botão Checkout Pro
            btnPagarCheckout.disabled = false;
        }

    } else if (isDinheiro) {
        document.getElementById('opcoes-dinheiro').classList.remove('hidden');
        if (btnEnviar) btnEnviar.disabled = false; // Habilita Enviar
        
    } else if (isCartao) {
        document.getElementById('opcoes-cartao').classList.remove('hidden');
        if (btnEnviar) btnEnviar.disabled = false; // Habilita Enviar
    } 
}


function calcularTotal() {
    let total = 0;
    let totalItens = 0;
    const items = [];
    
    document.querySelectorAll('.item-qty').forEach(select => {
        const qty = parseInt(select.value, 10) || 0; 
        const itemElement = select.closest('.menu-item');
        
        const priceAttr = itemElement ? itemElement.getAttribute('data-price') : null;
        const price = parseFloat(priceAttr) || 0; 
        
        const name = select.getAttribute('data-name');
        
        if (qty > 0) {
            const subtotal = qty * price;
            total += subtotal;
            totalItens += qty;
            items.push({ name, qty, price, subtotal: subtotal.toFixed(2) });
        }
    });

    const btnRevisao = document.getElementById('btn-revisao');
    if (btnRevisao) btnRevisao.disabled = totalItens <= 0;

    // Atualiza exibições
    if (document.getElementById('total-itens')) document.getElementById('total-itens').textContent = totalItens;
    if (document.getElementById('total-valor-display')) document.getElementById('total-valor-display').textContent = `R$ ${total.toFixed(2).replace('.', ',')}`;
    if (document.getElementById('total-final-pagamento')) document.getElementById('total-final-pagamento').textContent = `R$ ${total.toFixed(2).replace('.', ',')}`;

    // Atualiza campos escondidos
    if (document.getElementById('total_itens_final')) document.getElementById('total_itens_final').value = totalItens;
    if (document.getElementById('total-valor-final')) document.getElementById('total-valor-final').value = total.toFixed(2);
    
    return items;
}

function preencherRevisao() {
    const items = calcularTotal(); 
    const revisaoDiv = document.getElementById('revisao-itens');
    if (!revisaoDiv) return;
    
    revisaoDiv.innerHTML = '<h3>Seu Pedido:</h3>';
    
    if (items.length === 0) {
        revisaoDiv.innerHTML += '<p style="color: var(--color-error);">Nenhum item selecionado!</p>';
        return;
    }
    
    items.forEach(item => {
        const p = document.createElement('p');
        // Usa a cor do texto secundário, assumindo que ela existe no style.css
        p.style.color = 'var(--color-text-secondary)'; 
        p.textContent = `${item.qty}x ${item.name} - R$ ${item.subtotal.replace('.', ',')}`;
        revisaoDiv.appendChild(p);
    });
}


// =========================================================
// LÓGICA DO CHECKOUT PRO (Redirecionamento)
// =========================================================

async function gerarCheckoutPro() {
    const btnPagarCheckout = document.getElementById('btn-pagar-checkout');
    if (!btnPagarCheckout) return;
    
    btnPagarCheckout.disabled = true;
    btnPagarCheckout.textContent = 'Gerando link de pagamento...';
    
    const valor = parseFloat(document.getElementById('total-valor-final').value);
    const nome = document.getElementById('nome').value;
    const email = document.getElementById('email').value;
    const itens = calcularTotal(); 
    
    if (valor <= 0) {
        alert('O total do pedido é zero. Adicione itens.');
        btnPagarCheckout.disabled = false;
        btnPagarCheckout.textContent = 'Pagar com Pix/Cartão no Mercado Pago';
        return;
    }

    try {
        const response = await fetch('processar_pedido.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'gerar_checkout_pro',
                valor: valor, 
                nome: nome, 
                email: email,
                itens: itens
            })
        });

        const data = await response.json();

        if (data.success) {
            externalReferenceId = data.external_reference; 
            // REDIRECIONAMENTO PARA O MERCADO PAGO
            window.location.href = data.redirect_url;
            
        } else {
            alert('Erro ao gerar o Checkout Pro: ' + data.message);
            btnPagarCheckout.disabled = false;
            btnPagarCheckout.textContent = 'Pagar com Pix/Cartão no Mercado Pago';
        }

    } catch (error) {
        console.error('Erro na requisição AJAX (gerar_checkout_pro):', error);
        alert('Erro de rede ou servidor ao gerar pagamento. Tente novamente.');
        btnPagarCheckout.disabled = false;
        btnPagarCheckout.textContent = 'Pagar com Pix/Cartão no Mercado Pago';
    }
}

// =========================================================
// LÓGICA DE CONFIRMAÇÃO FINAL
// =========================================================
function showConfirmationPage(formaPagamento) {
    const form = document.getElementById('pedidoForm');
    if (!form) return;

    // Oculta o formulário
    form.style.display = 'none'; 
    document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));

    // Oculta o botão Limpar Tudo
    if (btnCleanGlobal) {
        btnCleanGlobal.style.display = 'none';
    }

    const container = document.querySelector('.card-form-container'); 
    
    const confirmationDiv = document.createElement('div');
    confirmationDiv.className = 'confirmation-page'; 
    confirmationDiv.style.textAlign = 'center';

    let title = 'Pedido Recebido com Sucesso!';
    let message = '';
    
    if (formaPagamento === 'pix_cartao') {
        title = 'Pagamento Confirmado e Pedido Efetuado!';
        message = `<p style="color: var(--color-success); font-weight: 600;">Seu pagamento foi aprovado pelo Mercado Pago. Seu pedido será preparado imediatamente.</p>`;
    } else {
        const trocoNeeded = document.getElementById('preciso-troco') && document.getElementById('preciso-troco').checked;
        let trocoMessage = trocoNeeded ? ` (O entregador entrará em contato para combinar o troco)` : '';
        
        message = `<p>Seu pedido foi registrado e será preparado.</p>
                   <p>O pagamento de **R$ ${totalInput.value.replace('.', ',')}** será feito na entrega via **${formaPagamento.toUpperCase().replace('_', ' ')}**${trocoMessage}.</p>`;
    }
    
    message += `<p>Obrigado pela preferência. Entraremos em contato para confirmar o prazo de entrega.</p>`;

    confirmationDiv.innerHTML = `
        <h2 style="color: var(--color-primary); margin-bottom: 20px;">${title}</h2>
        ${message}
        <button onclick="window.location.reload()" class="btn-submit" style="margin-top: 20px;">Fazer Novo Pedido</button>
    `;

    container.appendChild(confirmationDiv);
    currentStep = 6; 
}


// =========================================================
// INICIALIZAÇÃO E EVENT LISTENERS
// =========================================================

document.addEventListener('DOMContentLoaded', () => {
    
    // --- Lógica de Retorno do Mercado Pago (pós-redirecionamento) ---
    const urlParams = new URLSearchParams(window.location.search);
    const statusMP = urlParams.get('status');
    const refId = urlParams.get('ref');

    if (statusMP && refId) {
        if (statusMP === 'success') {
            showConfirmationPage('pix_cartao'); // Mostra resumo pós-pagamento MP
        } else if (statusMP === 'pending') {
             alert('⏳ Pagamento Pendente. Aguarde a confirmação do PIX.');
        } else if (statusMP === 'failure') {
             alert('❌ O pagamento falhou ou foi cancelado. Por favor, tente novamente ou escolha outra forma de pagamento.');
        }
        history.replaceState(null, '', window.location.pathname);
    }
    
    // --- Navegação ---
    document.querySelectorAll('.btn-next').forEach(button => {
        button.addEventListener('click', () => updateStep(currentStep + 1));
    });

    document.querySelectorAll('.btn-prev').forEach(button => {
        button.addEventListener('click', () => updateStep(currentStep - 1));
    });

    // --- Validação e Máscara ---
    const nomeInput = document.getElementById('nome');
    const emailInput = document.getElementById('email');
    const telefoneInput = document.getElementById('telefone');
    
    if (nomeInput) nomeInput.addEventListener('input', validarPasso2);
    if (emailInput) emailInput.addEventListener('input', validarPasso2);
    if (telefoneInput) telefoneInput.addEventListener('input', function(e) {
        e.target.value = maskTelefone(e.target.value);
        validarPasso2(); 
    });
    
    // --- Cálculo e Total (Passo 4 - Pedidos) ---
    document.querySelectorAll('.item-qty').forEach(select => {
        select.addEventListener('change', calcularTotal); 
        select.addEventListener('change', () => {
             if (currentStep === 5) {
                 preencherRevisao();
                 const formaPagamentoSelect = document.getElementById('forma-pagamento');
                 if (formaPagamentoSelect) {
                     handlePaymentChange(formaPagamentoSelect.value);
                 }
             }
        });
    });
    
    calcularTotal(); 

    // --- Lógica de Pagamento (Passo 5) ---
    const formaPagamentoSelect = document.getElementById('forma-pagamento');
    if (formaPagamentoSelect) {
        formaPagamentoSelect.addEventListener('change', (e) => {
            handlePaymentChange(e.target.value);
        });
    }
    
    // Listener para o botão Pagar com PIX/Cartão
    const btnPagarCheckout = document.getElementById('btn-pagar-checkout');
    if (btnPagarCheckout) {
        btnPagarCheckout.addEventListener('click', gerarCheckoutPro);
    }

    // --- Submissão Final do Formulário (Dinheiro/Cartão na entrega) ---
    document.getElementById('pedidoForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formaPagamento = document.getElementById('forma-pagamento').value;
        const btnEnviar = document.getElementById('btn-enviar');

        if (formaPagamento === 'pix_cartao') {
             alert('Por favor, use o botão "Pagar com Pix/Cartão no Mercado Pago" para o pagamento online.');
             return;
        }
        if (formaPagamento === "") {
             return;
        }

        if (btnEnviar) {
            btnEnviar.disabled = true;
            btnEnviar.textContent = 'Enviando...';
        }
        
        const pedidoData = {
            action: 'finalizar_pedido',
            forma_pagamento: formaPagamento,
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
                // Se der sucesso, mostra a página de confirmação
                showConfirmationPage(formaPagamento); 
            } else {
                alert('Erro ao finalizar pedido: ' + finalData.message);
                if (btnEnviar) {
                    btnEnviar.disabled = false;
                    btnEnviar.textContent = 'Finalizar Pedido (Pagamento na Entrega)';
                }
            }
        } catch (error) {
            console.error('Erro na requisição AJAX (finalizar_pedido):', error);
            alert('Erro de rede ou servidor ao finalizar pedido. Tente novamente.');
            if (btnEnviar) {
                btnEnviar.disabled = false;
                btnEnviar.textContent = 'Finalizar Pedido (Pagamento na Entrega)';
            }
        }
    });

    // --- Botão Limpar Pedido (Global) ---
    if (btnCleanGlobal) {
        btnCleanGlobal.addEventListener('click', () => {
            if (confirm("Tem certeza que deseja limpar todos os dados do pedido?")) {
                if (pixMonitorInterval) {
                    clearInterval(pixMonitorInterval);
                }
                window.location.reload();
            }
        });
    }

    // Inicia no primeiro passo e valida o passo 2
    updateStep(1);
    validarPasso2(); 
});