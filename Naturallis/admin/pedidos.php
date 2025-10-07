<?php
session_start();
require_once '../config/database.php';

// Verificar se é admin
if (!isset($_SESSION['usuario_role']) || $_SESSION['usuario_role'] !== 'admin') {
    header('Location: ../login.html?erro=4');
    exit();
}

$conn = getDBConnection();
$message = '';

// Processar ações se necessário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_status':
            try {
                $pedido_id = $_POST['pedido_id'];
                $novo_status = $_POST['novo_status'];
                
                $stmt = $conn->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
                if ($stmt->execute([$novo_status, $pedido_id])) {
                    $message = "✅ Status do pedido atualizado com sucesso!";
                } else {
                    $message = "❌ Erro ao atualizar status do pedido.";
                }
            } catch (Exception $e) {
                $message = "❌ Erro: " . $e->getMessage();
            }
            break;
    }
}

// Buscar estatísticas de pedidos
$stats = [];
try {
    // Total de pedidos
    $stmt = $conn->query("SELECT COUNT(*) as total FROM pedidos");
    $stats['total'] = $stmt->fetch()['total'];
    
    // Pedidos por status
    $stmt = $conn->query("
        SELECT status, COUNT(*) as quantidade 
        FROM pedidos 
        GROUP BY status 
        ORDER BY quantidade DESC
    ");
    $stats['por_status'] = $stmt->fetchAll();
    
    // Total vendido
    $stmt = $conn->query("SELECT SUM(valor_total) as total_vendido FROM pedidos WHERE status != 'cancelado'");
    $stats['total_vendido'] = $stmt->fetch()['total_vendido'] ?? 0;
    
} catch (Exception $e) {
    $stats = ['total' => 0, 'por_status' => [], 'total_vendido' => 0];
}

// Buscar pedidos com informações dos usuários
$pedidos = [];
try {
    $stmt = $conn->query("
        SELECT 
            p.id,
            p.numero_pedido,
            p.status,
            p.valor_total,
            p.forma_pagamento,
            p.created_at,
            p.data_entrega_prevista,
            u.nome as cliente_nome,
            u.email as cliente_email,
            u.telefone as cliente_telefone
        FROM pedidos p
        LEFT JOIN usuarios u ON p.usuario_id = u.id
        ORDER BY p.created_at DESC
        LIMIT 50
    ");
    $pedidos = $stmt->fetchAll();
} catch (Exception $e) {
    $message = "❌ Erro ao carregar pedidos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Pedidos - Admin</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .back-btn { display: inline-block; background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-bottom: 20px; }
        .message { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .message.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .message.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .stat-number { font-size: 2.5em; font-weight: bold; color: #4CAF50; margin-bottom: 5px; }
        .stat-label { color: #666; font-size: 14px; }
        
        .pedidos-section { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .pedidos-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .pedidos-table th, .pedidos-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .pedidos-table th { background: #f8f9fa; font-weight: bold; }
        
        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .status-pendente { background: #fff3cd; color: #856404; }
        .status-confirmado { background: #d1ecf1; color: #0c5460; }
        .status-preparando { background: #cce7ff; color: #004085; }
        .status-enviado { background: #c3e6cb; color: #155724; }
        .status-entregue { background: #d4edda; color: #155724; }
        .status-cancelado { background: #f8d7da; color: #721c24; }
        
        .payment-badge { padding: 3px 6px; border-radius: 8px; font-size: 11px; }
        .payment-cartao_credito { background: #e3f2fd; color: #1976d2; }
        .payment-pix { background: #e8f5e8; color: #2e7d32; }
        .payment-boleto { background: #fff3e0; color: #f57c00; }
        
        .btn { padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        
        .status-select { padding: 5px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="admin-container">
        <a href="dashboard.php" class="back-btn">← Voltar ao Dashboard</a>
        
        <h1>📦 Gerenciar Pedidos</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '✅') !== false ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total de Pedidos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">R$ <?php echo number_format($stats['total_vendido'], 2, ',', '.'); ?></div>
                <div class="stat-label">Total Vendido</div>
            </div>
            <?php if (!empty($stats['por_status'])): ?>
                <?php foreach (array_slice($stats['por_status'], 0, 2) as $status_info): ?>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $status_info['quantidade']; ?></div>
                        <div class="stat-label"><?php echo ucfirst($status_info['status']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Lista de pedidos -->
        <div class="pedidos-section">
            <h2>📋 Lista de Pedidos (<?php echo count($pedidos); ?>)</h2>
            
            <?php if (count($pedidos) > 0): ?>
                <table class="pedidos-table">
                    <thead>
                        <tr>
                            <th>Nº Pedido</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Valor</th>
                            <th>Pagamento</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $pedido): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $pedido['numero_pedido']; ?></strong>
                                    <br><small>ID: <?php echo $pedido['id']; ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($pedido['cliente_nome']); ?></strong>
                                    <br><small><?php echo htmlspecialchars($pedido['cliente_email']); ?></small>
                                    <?php if ($pedido['cliente_telefone']): ?>
                                        <br><small>📞 <?php echo htmlspecialchars($pedido['cliente_telefone']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($pedido['created_at'])); ?>
                                    <br><small><?php echo date('H:i', strtotime($pedido['created_at'])); ?></small>
                                </td>
                                <td>
                                    <strong>R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?></strong>
                                </td>
                                <td>
                                    <span class="payment-badge payment-<?php echo $pedido['forma_pagamento']; ?>">
                                        <?php
                                        $formas_pagamento = [
                                            'cartao_credito' => 'Cartão Crédito',
                                            'cartao_debito' => 'Cartão Débito',
                                            'pix' => 'PIX',
                                            'boleto' => 'Boleto',
                                            'transferencia' => 'Transferência'
                                        ];
                                        echo $formas_pagamento[$pedido['forma_pagamento']] ?? $pedido['forma_pagamento'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $pedido['status']; ?>">
                                        <?php echo ucfirst($pedido['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="pedido_id" value="<?php echo $pedido['id']; ?>">
                                        
                                        <select name="novo_status" class="status-select" onchange="this.form.submit()">
                                            <option value="">Alterar status...</option>
                                            <option value="pendente" <?php echo $pedido['status'] == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                            <option value="confirmado" <?php echo $pedido['status'] == 'confirmado' ? 'selected' : ''; ?>>Confirmado</option>
                                            <option value="preparando" <?php echo $pedido['status'] == 'preparando' ? 'selected' : ''; ?>>Preparando</option>
                                            <option value="enviado" <?php echo $pedido['status'] == 'enviado' ? 'selected' : ''; ?>>Enviado</option>
                                            <option value="entregue" <?php echo $pedido['status'] == 'entregue' ? 'selected' : ''; ?>>Entregue</option>
                                            <option value="cancelado" <?php echo $pedido['status'] == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                                        </select>
                                    </form>
                                    
                                    <button class="btn btn-primary" onclick="verDetalhes(<?php echo $pedido['id']; ?>)" style="margin-top: 5px;">
                                        👁️ Ver Detalhes
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 4px;">
                    <h4>ℹ️ Status dos Pedidos:</h4>
                    <ul style="margin: 10px 0; columns: 2;">
                        <li><strong>Pendente:</strong> Aguardando confirmação</li>
                        <li><strong>Confirmado:</strong> Pedido confirmado</li>
                        <li><strong>Preparando:</strong> Em preparação</li>
                        <li><strong>Enviado:</strong> A caminho do cliente</li>
                        <li><strong>Entregue:</strong> Pedido finalizado</li>
                        <li><strong>Cancelado:</strong> Pedido cancelado</li>
                    </ul>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px;">
                    <h3>📦 Nenhum pedido encontrado</h3>
                    <p>Quando os clientes fizerem pedidos, eles aparecerão aqui.</p>
                    <p><em>Atualmente o sistema não possui um carrinho de compras funcional, mas a estrutura está pronta.</em></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function verDetalhes(pedidoId) {
            // Funcionalidade futura: abrir modal ou página com detalhes do pedido
            alert('Detalhes do pedido ID: ' + pedidoId + '\n\nEsta funcionalidade será implementada em versões futuras.\nPor enquanto, você pode ver as informações básicas na tabela.');
        }
    </script>
</body>
</html>