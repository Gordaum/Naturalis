<?php
session_start();
require_once 'config/database.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.html');
    exit();
}

// Verificar se foi passado o número do pedido
$numero_pedido = trim($_GET['pedido'] ?? '');
if (empty($numero_pedido)) {
    header('Location: carrinho.php');
    exit();
}

// Buscar dados do pedido
$pedido = null;
try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT 
            p.*,
            u.nome as cliente_nome,
            u.email as cliente_email
        FROM pedidos p
        JOIN usuarios u ON p.usuario_id = u.id
        WHERE p.numero_pedido = ? AND p.usuario_id = ?
    ");
    
    $stmt->execute([$numero_pedido, $_SESSION['usuario_id']]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
        header('Location: carrinho.php');
        exit();
    }
    
} catch (Exception $e) {
    error_log("Erro ao buscar pedido: " . $e->getMessage());
    header('Location: carrinho.php');
    exit();
}

// Dados do endereço de entrega
$endereco = json_decode($pedido['endereco_entrega'], true);

// Gerar QR Code PIX (simulado)
function gerarQRCodePIX($valor, $numero_pedido) {
    // Em um sistema real, você usaria uma API do seu banco ou processador de pagamento
    // Aqui vamos simular um QR Code PIX
    
    $chave_pix = "admin@naturallis.com"; // Chave PIX da loja (email, CPF, CNPJ ou telefone)
    $nome_recebedor = "NATURALLIS LTDA";
    $cidade = "SAO PAULO";
    
    // Payload PIX simplificado (em um sistema real seria mais complexo)
    $payload_pix = "00020126580014BR.GOV.BCB.PIX0136{$chave_pix}5204000053039865802BR5913{$nome_recebedor}6009{$cidade}62070503***6304";
    
    // URL para gerar QR Code usando serviço online gratuito
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($payload_pix);
    
    return [
        'url' => $qr_url,
        'payload' => $payload_pix,
        'chave' => $chave_pix
    ];
}

$qr_code = gerarQRCodePIX($pedido['valor_total'], $numero_pedido);

// Processar confirmação de pagamento (simulado)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar_pagamento'])) {
    try {
        $stmt = $conn->prepare("UPDATE pedidos SET status = 'confirmado' WHERE numero_pedido = ?");
        $stmt->execute([$numero_pedido]);
        
        $sucesso = "Pagamento confirmado! Seu pedido está sendo processado.";
    } catch (Exception $e) {
        error_log("Erro ao confirmar pagamento: " . $e->getMessage());
        $erro = "Erro ao confirmar pagamento.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💳 Pagamento - Pedido #<?php echo htmlspecialchars($numero_pedido); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .pagamento-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .pagamento-header {
            text-align: center;
            background: linear-gradient(135deg, #6aa84f, #28a745);
            color: white;
            padding: 2rem;
            border-radius: 12px 12px 0 0;
            margin-bottom: 0;
        }
        
        .pagamento-content {
            background: white;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            margin-top: 1rem;
        }
        
        .status-pendente {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-confirmado {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .pagamento-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }
        
        .qr-section {
            padding: 2rem;
            text-align: center;
            background: #f8f9fa;
            border-right: 1px solid #eee;
        }
        
        .detalhes-section {
            padding: 2rem;
        }
        
        .qr-container {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .qr-code img {
            max-width: 250px;
            width: 100%;
            border-radius: 8px;
            border: 3px solid #6aa84f;
        }
        
        .pix-info {
            background: #e8f5e8;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
            text-align: left;
        }
        
        .pix-chave {
            background: #f1f3f4;
            padding: 1rem;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            word-break: break-all;
            margin: 1rem 0;
            position: relative;
        }
        
        .copy-button {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: #6aa84f;
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
        }
        
        .pedido-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.25rem 0;
        }
        
        .info-row.total {
            font-size: 1.2rem;
            font-weight: bold;
            color: #6aa84f;
            border-top: 2px solid #6aa84f;
            padding-top: 0.75rem;
            margin-top: 1rem;
        }
        
        .endereco-entrega {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .btn-confirmar {
            width: 100%;
            background: #28a745;
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }
        
        .btn-confirmar:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-voltar {
            display: inline-block;
            color: #6c757d;
            text-decoration: none;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .btn-voltar:hover {
            color: #495057;
        }
        
        .pagamento-instrucoes {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .pagamento-instrucoes ol {
            margin: 0;
            padding-left: 1.2rem;
        }
        
        .tempo-expiracao {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 1rem;
            border-radius: 6px;
            text-align: center;
            margin-top: 1rem;
        }
        
        .countdown {
            font-size: 1.2rem;
            font-weight: bold;
            color: #856404;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .pagamento-grid {
                grid-template-columns: 1fr;
            }
            
            .qr-section {
                border-right: none;
                border-bottom: 1px solid #eee;
            }
            
            .pagamento-container {
                margin: 1rem;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="pagamento-container">
        <a href="index.php" class="btn-voltar">
            <i class="fas fa-home"></i> Voltar ao Início
        </a>
        
        <div class="pagamento-header">
            <h1><i class="fas fa-receipt"></i> Pedido Realizado com Sucesso!</h1>
            <h2>Pedido #<?php echo htmlspecialchars($numero_pedido); ?></h2>
            <div class="status-badge <?php echo $pedido['status'] == 'confirmado' ? 'status-confirmado' : 'status-pendente'; ?>">
                <?php if ($pedido['status'] == 'confirmado'): ?>
                    <i class="fas fa-check-circle"></i> Pagamento Confirmado
                <?php else: ?>
                    <i class="fas fa-clock"></i> Aguardando Pagamento
                <?php endif; ?>
            </div>
        </div>
        
        <div class="pagamento-content">
            <?php if (isset($sucesso)): ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($sucesso); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($erro)): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($erro); ?>
                </div>
            <?php endif; ?>
            
            <div class="pagamento-grid">
                <!-- Seção QR Code PIX -->
                <div class="qr-section">
                    <h3><i class="fab fa-pix"></i> Pagar com PIX</h3>
                    
                    <?php if ($pedido['forma_pagamento'] == 'pix'): ?>
                        <div class="qr-container">
                            <div class="qr-code">
                                <img src="<?php echo $qr_code['url']; ?>" alt="QR Code PIX">
                            </div>
                            
                            <div class="pix-info">
                                <h4><i class="fas fa-qrcode"></i> Como pagar:</h4>
                                
                                <div class="pagamento-instrucoes">
                                    <ol>
                                        <li>Abra o app do seu banco</li>
                                        <li>Escolha a opção PIX</li>
                                        <li>Escaneie o QR Code ou copie a chave</li>
                                        <li>Confirme o pagamento</li>
                                    </ol>
                                </div>
                                
                                <p><strong>Chave PIX:</strong></p>
                                <div class="pix-chave">
                                    <?php echo htmlspecialchars($qr_code['chave']); ?>
                                    <button class="copy-button" onclick="copiarChave()">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                                
                                <div class="tempo-expiracao">
                                    <i class="fas fa-clock"></i>
                                    <div>Este QR Code expira em:</div>
                                    <div class="countdown" id="countdown">30:00</div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($pedido['status'] == 'pendente'): ?>
                            <form method="post">
                                <button type="submit" name="confirmar_pagamento" class="btn-confirmar">
                                    <i class="fas fa-check-double"></i> Já Paguei - Confirmar Pagamento
                                </button>
                            </form>
                            <small style="color: #666; display: block; margin-top: 0.5rem;">
                                *Clique apenas após realizar o pagamento
                            </small>
                        <?php endif; ?>
                    
                    <?php else: ?>
                        <div style="padding: 2rem; color: #666;">
                            <i class="fas fa-info-circle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                            <p>Forma de pagamento: <strong><?php echo ucfirst(str_replace('_', ' ', $pedido['forma_pagamento'])); ?></strong></p>
                            <p>Instruções de pagamento serão enviadas para seu email.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Detalhes do Pedido -->
                <div class="detalhes-section">
                    <h3><i class="fas fa-clipboard-list"></i> Detalhes do Pedido</h3>
                    
                    <div class="pedido-info">
                        <div class="info-row">
                            <span>Data do Pedido:</span>
                            <span><?php echo date('d/m/Y H:i', strtotime($pedido['created_at'])); ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span>Cliente:</span>
                            <span><?php echo htmlspecialchars($pedido['cliente_nome']); ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span>Email:</span>
                            <span><?php echo htmlspecialchars($pedido['cliente_email']); ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span>Subtotal:</span>
                            <span>R$ <?php echo number_format($pedido['valor_subtotal'], 2, ',', '.'); ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span>Frete:</span>
                            <span>
                                <?php if ($pedido['valor_frete'] == 0): ?>
                                    <span style="color: #28a745; font-weight: bold;">GRÁTIS</span>
                                <?php else: ?>
                                    R$ <?php echo number_format($pedido['valor_frete'], 2, ',', '.'); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <?php if ($pedido['valor_desconto'] > 0): ?>
                            <div class="info-row">
                                <span>Desconto:</span>
                                <span style="color: #28a745;">-R$ <?php echo number_format($pedido['valor_desconto'], 2, ',', '.'); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="info-row total">
                            <span>TOTAL:</span>
                            <span>R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?></span>
                        </div>
                    </div>
                    
                    <div class="endereco-entrega">
                        <h4><i class="fas fa-shipping-fast"></i> Endereço de Entrega</h4>
                        <p>
                            <strong><?php echo htmlspecialchars($endereco['nome']); ?></strong><br>
                            <?php echo htmlspecialchars($endereco['rua']); ?>, <?php echo htmlspecialchars($endereco['numero']); ?>
                            <?php if ($endereco['complemento']): ?>
                                - <?php echo htmlspecialchars($endereco['complemento']); ?>
                            <?php endif; ?><br>
                            <?php echo htmlspecialchars($endereco['bairro']); ?><br>
                            <?php echo htmlspecialchars($endereco['cidade']); ?> - <?php echo htmlspecialchars($endereco['estado']); ?><br>
                            CEP: <?php echo htmlspecialchars($endereco['cep']); ?><br>
                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($endereco['telefone']); ?>
                        </p>
                        
                        <?php if ($pedido['data_entrega_prevista']): ?>
                            <p style="color: #6aa84f; font-weight: bold;">
                                <i class="fas fa-calendar-alt"></i>
                                Entrega prevista: <?php echo date('d/m/Y', strtotime($pedido['data_entrega_prevista'])); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($pedido['observacoes']): ?>
                        <div style="background: #fff; border: 1px solid #e9ecef; border-radius: 8px; padding: 1rem;">
                            <h4><i class="fas fa-comment"></i> Observações</h4>
                            <p><?php echo htmlspecialchars($pedido['observacoes']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Função para copiar chave PIX
        function copiarChave() {
            const chave = "<?php echo $qr_code['chave']; ?>";
            navigator.clipboard.writeText(chave).then(function() {
                alert('Chave PIX copiada para a área de transferência!');
            }).catch(function(err) {
                console.error('Erro ao copiar: ', err);
                // Fallback para navegadores mais antigos
                const textArea = document.createElement('textarea');
                textArea.value = chave;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Chave PIX copiada para a área de transferência!');
            });
        }
        
        // Countdown do QR Code
        let timeLeft = 30 * 60; // 30 minutos em segundos
        
        function updateCountdown() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            
            document.getElementById('countdown').textContent = 
                String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            
            if (timeLeft <= 0) {
                document.getElementById('countdown').textContent = "EXPIRADO";
                document.getElementById('countdown').style.color = '#dc3545';
                return;
            }
            
            timeLeft--;
        }
        
        // Atualizar countdown a cada segundo
        setInterval(updateCountdown, 1000);
        updateCountdown(); // Executar imediatamente
        
        // Simular verificação automática de pagamento (em um sistema real seria via webhook)
        <?php if ($pedido['status'] == 'pendente' && $pedido['forma_pagamento'] == 'pix'): ?>
        let checkPaymentInterval = setInterval(function() {
            // Em um sistema real, você faria uma requisição AJAX para verificar o status
            console.log('Verificando status do pagamento...');
        }, 10000); // Verificar a cada 10 segundos
        <?php endif; ?>
    </script>
</body>
</html>