<?php
session_start();
require_once 'config/database.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.html');
    exit();
}

$erro = '';
$sucesso = '';

// Processar finalização da compra
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['finalizar_pedido'])) {
    $forma_pagamento = trim($_POST['forma_pagamento'] ?? '');
    $endereco_entrega = [
        'nome' => trim($_POST['nome'] ?? ''),
        'telefone' => trim($_POST['telefone'] ?? ''),
        'cep' => trim($_POST['cep'] ?? ''),
        'rua' => trim($_POST['rua'] ?? ''),
        'numero' => trim($_POST['numero'] ?? ''),
        'complemento' => trim($_POST['complemento'] ?? ''),
        'bairro' => trim($_POST['bairro'] ?? ''),
        'cidade' => trim($_POST['cidade'] ?? ''),
        'estado' => trim($_POST['estado'] ?? '')
    ];
    $observacoes = trim($_POST['observacoes'] ?? '');
    
    // Validações
    if (empty($forma_pagamento)) {
        $erro = "Selecione uma forma de pagamento.";
    } elseif (empty($endereco_entrega['nome'])) {
        $erro = "Nome completo é obrigatório.";
    } elseif (empty($endereco_entrega['cep'])) {
        $erro = "CEP é obrigatório.";
    } else {
        try {
            $conn = getDBConnection();
            $conn->beginTransaction();
            
            // Buscar itens do carrinho
            $stmt = $conn->prepare("
                SELECT 
                    c.produto_id,
                    c.quantidade,
                    c.preco_unitario,
                    p.nome,
                    p.estoque,
                    (c.quantidade * c.preco_unitario) as subtotal
                FROM carrinho c
                JOIN produtos p ON c.produto_id = p.id
                WHERE c.usuario_id = ? AND p.ativo = 1
            ");
            
            $stmt->execute([$_SESSION['usuario_id']]);
            $itens_carrinho = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($itens_carrinho)) {
                throw new Exception("Carrinho vazio. Adicione produtos antes de finalizar.");
            }
            
            // Verificar estoque
            foreach ($itens_carrinho as $item) {
                if ($item['quantidade'] > $item['estoque']) {
                    throw new Exception("Produto '{$item['nome']}' não tem estoque suficiente.");
                }
            }
            
            // Calcular valores
            $valor_subtotal = 0;
            foreach ($itens_carrinho as $item) {
                $valor_subtotal += $item['subtotal'];
            }
            
            $valor_frete = ($valor_subtotal >= 100) ? 0 : 15.90; // Frete grátis acima de R$ 100
            $valor_desconto = 0; // Pode implementar cupons depois
            $valor_total = $valor_subtotal + $valor_frete - $valor_desconto;
            
            // Gerar número do pedido
            $ano_atual = date('Y');
            $stmt = $conn->prepare("SELECT COUNT(*) + 1 as contador FROM pedidos WHERE YEAR(created_at) = ?");
            $stmt->execute([$ano_atual]);
            $contador = $stmt->fetch()['contador'];
            $numero_pedido = 'NL' . $ano_atual . str_pad($contador, 6, '0', STR_PAD_LEFT);
            
            // Inserir pedido
            $stmt = $conn->prepare("
                INSERT INTO pedidos (
                    usuario_id, numero_pedido, status, valor_subtotal, 
                    valor_frete, valor_desconto, valor_total, forma_pagamento,
                    endereco_entrega, observacoes, data_entrega_prevista
                ) VALUES (?, ?, 'pendente', ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
            ");
            
            $stmt->execute([
                $_SESSION['usuario_id'],
                $numero_pedido,
                $valor_subtotal,
                $valor_frete,
                $valor_desconto,
                $valor_total,
                $forma_pagamento,
                json_encode($endereco_entrega),
                $observacoes
            ]);
            
            $pedido_id = $conn->lastInsertId();
            
            // Atualizar estoque dos produtos
            foreach ($itens_carrinho as $item) {
                $stmt = $conn->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?");
                $stmt->execute([$item['quantidade'], $item['produto_id']]);
            }
            
            // Limpar carrinho
            $stmt = $conn->prepare("DELETE FROM carrinho WHERE usuario_id = ?");
            $stmt->execute([$_SESSION['usuario_id']]);
            
            $conn->commit();
            
            // Redirecionar para página de pagamento
            header("Location: pagamento.php?pedido=" . urlencode($numero_pedido));
            exit();
            
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("Erro ao finalizar pedido: " . $e->getMessage());
            $erro = $e->getMessage();
        }
    }
}

// Buscar dados do usuário para pré-preenchimento
$usuario_dados = [];
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT nome, telefone, cidade, estado, cep FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $usuario_dados = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    error_log("Erro ao buscar dados do usuário: " . $e->getMessage());
}

// Buscar itens do carrinho para exibição
$itens_carrinho = [];
$valor_subtotal = 0;

try {
    $stmt = $conn->prepare("
        SELECT 
            c.quantidade,
            c.preco_unitario,
            p.nome,
            (c.quantidade * c.preco_unitario) as subtotal
        FROM carrinho c
        JOIN produtos p ON c.produto_id = p.id
        WHERE c.usuario_id = ? AND p.ativo = 1
        ORDER BY c.created_at DESC
    ");
    
    $stmt->execute([$_SESSION['usuario_id']]);
    $itens_carrinho = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($itens_carrinho as $item) {
        $valor_subtotal += $item['subtotal'];
    }
    
} catch (Exception $e) {
    error_log("Erro ao buscar carrinho: " . $e->getMessage());
    $erro = "Erro ao carregar carrinho.";
}

// Calcular frete e total
$valor_frete = ($valor_subtotal >= 100) ? 0 : 15.90;
$valor_total = $valor_subtotal + $valor_frete;

// Se carrinho vazio, redirecionar
if (empty($itens_carrinho)) {
    header('Location: carrinho.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💳 Checkout - Naturallis</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .checkout-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .checkout-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        .checkout-form {
            background: #fff;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .resumo-pedido {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 2rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .form-section h3 {
            color: #6aa84f;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #6aa84f;
            outline: none;
            box-shadow: 0 0 0 2px rgba(106, 168, 79, 0.2);
        }
        
        .payment-options {
            display: grid;
            gap: 1rem;
        }
        
        .payment-option {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .payment-option:hover {
            border-color: #6aa84f;
            background-color: #f8fff8;
        }
        
        .payment-option.selected {
            border-color: #6aa84f;
            background-color: #f0f8f0;
        }
        
        .payment-option input {
            margin-right: 1rem;
        }
        
        .payment-icon {
            font-size: 1.5rem;
            margin-right: 1rem;
            width: 30px;
        }
        
        .item-resumo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .item-resumo:last-child {
            border-bottom: none;
        }
        
        .valor-linha {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.25rem 0;
        }
        
        .valor-total {
            display: flex;
            justify-content: space-between;
            font-size: 1.2rem;
            font-weight: bold;
            color: #6aa84f;
            padding-top: 1rem;
            border-top: 2px solid #6aa84f;
            margin-top: 1rem;
        }
        
        .btn-finalizar {
            width: 100%;
            background: linear-gradient(135deg, #6aa84f, #28a745);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }
        
        .btn-finalizar:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
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
        
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .frete-gratis {
            color: #28a745;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .checkout-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .checkout-container {
                margin: 1rem auto;
            }
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <a href="carrinho.php" class="btn-voltar">
            <i class="fas fa-arrow-left"></i> Voltar ao Carrinho
        </a>
        
        <h1 style="text-align: center; color: #6aa84f; margin-bottom: 2rem;">
            <i class="fas fa-credit-card"></i> Finalizar Compra
        </h1>
        
        <?php if ($erro): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>
        
        <div class="checkout-grid">
            <div class="checkout-form">
                <form method="post">
                    <!-- Dados de Entrega -->
                    <div class="form-section">
                        <h3><i class="fas fa-shipping-fast"></i> Dados de Entrega</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nome">Nome Completo *</label>
                                <input type="text" id="nome" name="nome" required 
                                       value="<?php echo htmlspecialchars($usuario_dados['nome'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="telefone">Telefone *</label>
                                <input type="tel" id="telefone" name="telefone" required
                                       value="<?php echo htmlspecialchars($usuario_dados['telefone'] ?? ''); ?>"
                                       placeholder="(11) 99999-9999">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="cep">CEP *</label>
                                <input type="text" id="cep" name="cep" required
                                       value="<?php echo htmlspecialchars($usuario_dados['cep'] ?? ''); ?>"
                                       placeholder="12345-678">
                            </div>
                            
                            <div class="form-group">
                                <label for="rua">Endereço *</label>
                                <input type="text" id="rua" name="rua" required placeholder="Rua, Avenida...">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="numero">Número *</label>
                                <input type="text" id="numero" name="numero" required placeholder="123">
                            </div>
                            
                            <div class="form-group">
                                <label for="complemento">Complemento</label>
                                <input type="text" id="complemento" name="complemento" placeholder="Apto, Bloco...">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="bairro">Bairro *</label>
                                <input type="text" id="bairro" name="bairro" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="cidade">Cidade *</label>
                                <input type="text" id="cidade" name="cidade" required
                                       value="<?php echo htmlspecialchars($usuario_dados['cidade'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="estado">Estado *</label>
                            <select id="estado" name="estado" required>
                                <option value="">Selecione...</option>
                                <?php 
                                $estados = [
                                    'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
                                    'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
                                    'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
                                    'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
                                    'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
                                    'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
                                    'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
                                ];
                                foreach ($estados as $sigla => $nome) {
                                    $selected = ($usuario_dados['estado'] ?? '') == $sigla ? 'selected' : '';
                                    echo "<option value=\"$sigla\" $selected>$nome</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Forma de Pagamento -->
                    <div class="form-section">
                        <h3><i class="fas fa-credit-card"></i> Forma de Pagamento</h3>
                        
                        <div class="payment-options">
                            <label class="payment-option" onclick="selectPayment(this)">
                                <input type="radio" name="forma_pagamento" value="pix" required>
                                <i class="fas fa-qrcode payment-icon" style="color: #32bcad;"></i>
                                <div>
                                    <strong>PIX</strong>
                                    <div style="font-size: 0.9rem; color: #666;">Aprovação instantânea</div>
                                </div>
                            </label>
                            
                            <label class="payment-option" onclick="selectPayment(this)">
                                <input type="radio" name="forma_pagamento" value="cartao_credito">
                                <i class="fas fa-credit-card payment-icon" style="color: #007bff;"></i>
                                <div>
                                    <strong>Cartão de Crédito</strong>
                                    <div style="font-size: 0.9rem; color: #666;">Parcelamento disponível</div>
                                </div>
                            </label>
                            
                            <label class="payment-option" onclick="selectPayment(this)">
                                <input type="radio" name="forma_pagamento" value="boleto">
                                <i class="fas fa-barcode payment-icon" style="color: #ffc107;"></i>
                                <div>
                                    <strong>Boleto Bancário</strong>
                                    <div style="font-size: 0.9rem; color: #666;">Vencimento em 3 dias úteis</div>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Observações -->
                    <div class="form-section">
                        <h3><i class="fas fa-comment"></i> Observações (Opcional)</h3>
                        
                        <div class="form-group">
                            <textarea name="observacoes" rows="3" 
                                      placeholder="Comentários sobre a entrega, preferências, etc..."></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" name="finalizar_pedido" class="btn-finalizar">
                        <i class="fas fa-check-circle"></i> Finalizar Pedido
                    </button>
                </form>
            </div>
            
            <!-- Resumo do Pedido -->
            <div class="resumo-pedido">
                <h3 style="color: #6aa84f; margin-bottom: 1rem;">
                    <i class="fas fa-receipt"></i> Resumo do Pedido
                </h3>
                
                <?php foreach ($itens_carrinho as $item): ?>
                    <div class="item-resumo">
                        <div>
                            <strong><?php echo htmlspecialchars($item['nome']); ?></strong>
                            <br>
                            <small>Qtd: <?php echo $item['quantidade']; ?> x R$ <?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?></small>
                        </div>
                        <div>R$ <?php echo number_format($item['subtotal'], 2, ',', '.'); ?></div>
                    </div>
                <?php endforeach; ?>
                
                <hr style="margin: 1rem 0;">
                
                <div class="valor-linha">
                    <span>Subtotal:</span>
                    <span>R$ <?php echo number_format($valor_subtotal, 2, ',', '.'); ?></span>
                </div>
                
                <div class="valor-linha">
                    <span>Frete:</span>
                    <span class="<?php echo $valor_frete == 0 ? 'frete-gratis' : ''; ?>">
                        <?php if ($valor_frete == 0): ?>
                            GRÁTIS
                        <?php else: ?>
                            R$ <?php echo number_format($valor_frete, 2, ',', '.'); ?>
                        <?php endif; ?>
                    </span>
                </div>
                
                <?php if ($valor_frete == 0): ?>
                    <div style="color: #28a745; font-size: 0.9rem; margin-top: 0.5rem;">
                        <i class="fas fa-gift"></i> Frete grátis para compras acima de R$ 100!
                    </div>
                <?php endif; ?>
                
                <div class="valor-total">
                    <span>TOTAL:</span>
                    <span>R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function selectPayment(element) {
            // Remove seleção anterior
            document.querySelectorAll('.payment-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Adiciona seleção atual
            element.classList.add('selected');
            element.querySelector('input[type="radio"]').checked = true;
        }
        
        // Máscara para CEP
        document.getElementById('cep').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 8) {
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
                e.target.value = value;
            }
        });
        
        // Máscara para telefone
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                if (value.length <= 10) {
                    value = value.replace(/(\d{2})(\d{4})(\d)/, '($1) $2-$3');
                } else {
                    value = value.replace(/(\d{2})(\d{5})(\d)/, '($1) $2-$3');
                }
                e.target.value = value;
            }
        });
    </script>
</body>
</html>