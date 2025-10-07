<?php
session_start();
require_once 'config/database.php';

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    header('Location: login.html');
    exit();
}

$erro = '';
$sucesso = '';

// Processar remoção de item do carrinho
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remover_item'])) {
    $carrinho_id = intval($_POST['carrinho_id'] ?? 0);
    
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("DELETE FROM carrinho WHERE id = ? AND usuario_id = ?");
        if ($stmt->execute([$carrinho_id, $_SESSION['usuario_id']])) {
            $sucesso = "Item removido do carrinho!";
        } else {
            $erro = "Erro ao remover item do carrinho.";
        }
    } catch (Exception $e) {
        error_log("Erro ao remover item: " . $e->getMessage());
        $erro = "Erro ao remover item do carrinho.";
    }
}

// Processar atualização de quantidade
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['atualizar_quantidade'])) {
    $carrinho_id = intval($_POST['carrinho_id'] ?? 0);
    $nova_quantidade = intval($_POST['quantidade'] ?? 1);
    
    if ($nova_quantidade < 1) {
        $nova_quantidade = 1;
    }
    
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("UPDATE carrinho SET quantidade = ?, updated_at = NOW() WHERE id = ? AND usuario_id = ?");
        if ($stmt->execute([$nova_quantidade, $carrinho_id, $_SESSION['usuario_id']])) {
            $sucesso = "Quantidade atualizada!";
        } else {
            $erro = "Erro ao atualizar quantidade.";
        }
    } catch (Exception $e) {
        error_log("Erro ao atualizar quantidade: " . $e->getMessage());
        $erro = "Erro ao atualizar quantidade.";
    }
}

// Buscar itens do carrinho
$itens_carrinho = [];
$total_carrinho = 0;

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT 
            c.id as carrinho_id,
            c.quantidade,
            c.preco_unitario,
            p.id as produto_id,
            p.nome,
            p.descricao,
            p.preco as preco_atual,
            p.estoque,
            (c.quantidade * c.preco_unitario) as subtotal
        FROM carrinho c
        JOIN produtos p ON c.produto_id = p.id
        WHERE c.usuario_id = ? AND p.ativo = 1
        ORDER BY c.created_at DESC
    ");
    
    $stmt->execute([$_SESSION['usuario_id']]);
    $itens_carrinho = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular total
    foreach ($itens_carrinho as $item) {
        $total_carrinho += $item['subtotal'];
    }
    
} catch (Exception $e) {
    error_log("Erro ao buscar carrinho: " . $e->getMessage());
    $erro = "Erro ao carregar carrinho.";
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛒 Carrinho - Naturallis</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .carrinho-container {
            max-width: 1000px;
            margin: 2rem auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 2rem;
            min-height: 70vh;
        }
        .carrinho-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #6aa84f;
        }
        .logo-img {
            max-width: 100px;
            margin-bottom: 1rem;
            border-radius: 8px;
        }
        .carrinho-vazio {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }
        .carrinho-vazio i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #ccc;
        }
        .item-carrinho {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 1rem;
            align-items: center;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: #f9f9f9;
        }
        .item-info h4 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }
        .item-info p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        .quantidade-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .quantidade-input {
            width: 60px;
            padding: 0.5rem;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn-pequeno {
            padding: 0.25rem 0.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
        }
        .btn-atualizar {
            background: #007bff;
            color: white;
        }
        .btn-remover {
            background: #dc3545;
            color: white;
        }
        .preco {
            font-weight: bold;
            color: #28a745;
        }
        .total-carrinho {
            background: #f4f8f4;
            padding: 2rem;
            border-radius: 8px;
            margin-top: 2rem;
            text-align: center;
        }
        .total-valor {
            font-size: 2rem;
            font-weight: bold;
            color: #6aa84f;
            margin-bottom: 1rem;
        }
        .btn-continuar, .btn-voltar {
            display: inline-block;
            padding: 1rem 2rem;
            margin: 0.5rem;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .btn-continuar {
            background: #28a745;
            color: white;
        }
        .btn-voltar {
            background: #6c757d;
            color: white;
        }
        .btn-continuar:hover, .btn-voltar:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
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
            .item-carrinho {
                grid-template-columns: 1fr;
                gap: 0.5rem;
                text-align: center;
            }
            .carrinho-container {
                margin: 1rem;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="carrinho-container">
        <div class="carrinho-header">
            <img src="naturallis.jpeg" alt="Naturallis Logo" class="logo-img">
            <h1><i class="fas fa-shopping-cart"></i> Meu Carrinho</h1>
            <p>Usuário: <strong><?php echo htmlspecialchars($_SESSION['usuario'] ?? 'N/A'); ?></strong></p>
        </div>

        <?php if ($erro): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($sucesso); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($itens_carrinho)): ?>
            <div class="carrinho-vazio">
                <i class="fas fa-shopping-cart"></i>
                <h3>Seu carrinho está vazio</h3>
                <p>Adicione alguns produtos para começar suas compras!</p>
                <a href="produtos.php" class="btn-continuar">
                    <i class="fas fa-store"></i> Ir às Compras
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($itens_carrinho as $item): ?>
                <div class="item-carrinho">
                    <div class="item-info">
                        <h4><?php echo htmlspecialchars($item['nome']); ?></h4>
                        <p><?php echo htmlspecialchars($item['descricao']); ?></p>
                        <?php if ($item['preco_atual'] != $item['preco_unitario']): ?>
                            <small style="color: #dc3545;">
                                Preço atual: R$ <?php echo number_format($item['preco_atual'], 2, ',', '.'); ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="quantidade-control">
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="carrinho_id" value="<?php echo $item['carrinho_id']; ?>">
                            <input type="number" name="quantidade" value="<?php echo $item['quantidade']; ?>" 
                                   min="1" max="<?php echo $item['estoque']; ?>" class="quantidade-input">
                            <button type="submit" name="atualizar_quantidade" class="btn-pequeno btn-atualizar">
                                <i class="fas fa-sync"></i>
                            </button>
                        </form>
                    </div>
                    
                    <div class="preco">
                        R$ <?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?>
                    </div>
                    
                    <div class="preco">
                        R$ <?php echo number_format($item['subtotal'], 2, ',', '.'); ?>
                    </div>
                    
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="carrinho_id" value="<?php echo $item['carrinho_id']; ?>">
                        <button type="submit" name="remover_item" class="btn-pequeno btn-remover"
                                onclick="return confirm('Deseja remover este item do carrinho?')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>

            <div class="total-carrinho">
                <div class="total-valor">
                    Total: R$ <?php echo number_format($total_carrinho, 2, ',', '.'); ?>
                </div>
                <p><?php echo count($itens_carrinho); ?> item(s) no carrinho</p>
                
                <a href="checkout.php" class="btn-continuar">
                    <i class="fas fa-credit-card"></i> Finalizar Compra
                </a>
                <a href="produtos.php" class="btn-voltar">
                    <i class="fas fa-store"></i> Continuar Comprando
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>