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

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_product':
                try {
                    $stmt = $conn->prepare("INSERT INTO produtos (nome, descricao, preco, categoria_id, estoque, ingredientes, modo_uso, vegano, organico, cruelty_free) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['nome'],
                        $_POST['descricao'],
                        $_POST['preco'],
                        $_POST['categoria_id'],
                        $_POST['estoque'],
                        $_POST['ingredientes'],
                        $_POST['modo_uso'],
                        isset($_POST['vegano']) ? 1 : 0,
                        isset($_POST['organico']) ? 1 : 0,
                        isset($_POST['cruelty_free']) ? 1 : 0
                    ]);
                    $message = "✅ Produto adicionado com sucesso!";
                } catch (Exception $e) {
                    $message = "❌ Erro ao adicionar produto: " . $e->getMessage();
                }
                break;
                
            case 'delete_product':
                try {
                    $product_id = $_POST['product_id'];
                    
                    // Verificar se o produto existe e seu status atual
                    $checkStmt = $conn->prepare("SELECT nome, ativo FROM produtos WHERE id = ?");
                    $checkStmt->execute([$product_id]);
                    
                    if ($checkStmt->rowCount() > 0) {
                        $produto = $checkStmt->fetch();
                        
                        // Verificar se já está inativo
                        if (!$produto['ativo']) {
                            $message = "⚠️ O produto '{$produto['nome']}' já estava removido/inativo!";
                        } else {
                            // Marcar como inativo apenas se estiver ativo
                            $stmt = $conn->prepare("UPDATE produtos SET ativo = 0 WHERE id = ?");
                            $result = $stmt->execute([$product_id]);
                            
                            if ($result && $stmt->rowCount() > 0) {
                                $message = "✅ Produto '{$produto['nome']}' removido com sucesso!";
                            } else {
                                $message = "❌ Erro inesperado ao remover o produto.";
                            }
                        }
                    } else {
                        $message = "❌ Produto não encontrado!";
                    }
                } catch (Exception $e) {
                    $message = "❌ Erro ao remover produto: " . $e->getMessage();
                }
                break;
                
            case 'delete_permanent':
                try {
                    $product_id = $_POST['product_id'];
                    
                    // Verificar se o produto existe
                    $checkStmt = $conn->prepare("SELECT nome FROM produtos WHERE id = ?");
                    $checkStmt->execute([$product_id]);
                    
                    if ($checkStmt->rowCount() > 0) {
                        $produto = $checkStmt->fetch();
                        
                        // Excluir permanentemente do banco de dados
                        $stmt = $conn->prepare("DELETE FROM produtos WHERE id = ?");
                        $result = $stmt->execute([$product_id]);
                        
                        if ($result && $stmt->rowCount() > 0) {
                            $message = "✅ Produto '{$produto['nome']}' excluído PERMANENTEMENTE do sistema!";
                        } else {
                            $message = "❌ Erro ao excluir produto permanentemente.";
                        }
                    } else {
                        $message = "❌ Produto não encontrado!";
                    }
                } catch (Exception $e) {
                    $message = "❌ Erro ao excluir produto: " . $e->getMessage();
                }
                break;
                
            case 'toggle_status':
                try {
                    $product_id = $_POST['product_id'];
                    
                    // Buscar status atual
                    $checkStmt = $conn->prepare("SELECT nome, ativo FROM produtos WHERE id = ?");
                    $checkStmt->execute([$product_id]);
                    
                    if ($checkStmt->rowCount() > 0) {
                        $produto = $checkStmt->fetch();
                        
                        $stmt = $conn->prepare("UPDATE produtos SET ativo = NOT ativo WHERE id = ?");
                        $result = $stmt->execute([$product_id]);
                        
                        if ($result && $stmt->rowCount() > 0) {
                            $novoStatus = $produto['ativo'] ? 'desativado' : 'ativado';
                            $message = "✅ Produto '{$produto['nome']}' {$novoStatus} com sucesso!";
                        } else {
                            $message = "⚠️ Status não foi alterado.";
                        }
                    } else {
                        $message = "❌ Produto não encontrado!";
                    }
                } catch (Exception $e) {
                    $message = "❌ Erro ao alterar status: " . $e->getMessage();
                }
                break;
        }
    }
}

// Buscar produtos
$produtos = [];
try {
    $stmt = $conn->query("
        SELECT p.*, c.nome as categoria_nome 
        FROM produtos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        ORDER BY p.ativo DESC, p.created_at DESC
    ");
    $produtos = $stmt->fetchAll();
} catch (Exception $e) {
    $message = "❌ Erro ao carregar produtos: " . $e->getMessage();
}

// Buscar categorias para o formulário
$categorias = [];
try {
    $stmt = $conn->query("SELECT * FROM categorias WHERE ativo = 1 ORDER BY nome");
    $categorias = $stmt->fetchAll();
} catch (Exception $e) {
    // Ignorar erro
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Produtos - Admin</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .back-btn { display: inline-block; background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-bottom: 20px; }
        .message { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .message.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .message.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .form-section { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: bold; margin-bottom: 5px; }
        .form-group input, .form-group textarea, .form-group select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .checkbox-group { display: flex; gap: 20px; align-items: center; }
        .checkbox-group label { display: flex; align-items: center; gap: 5px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #4CAF50; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #f44336; color: white; }
        .btn-warning { background: #ff9800; color: white; }
        .products-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .products-table th, .products-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .products-table th { background: #f8f9fa; font-weight: bold; }
        .product-actions { display: flex; gap: 5px; flex-wrap: wrap; }
        .status-badge { padding: 3px 8px; border-radius: 12px; font-size: 12px; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .produto-inativo { background-color: #f8f9fa; opacity: 0.7; }
    </style>
</head>
<body>
    <div class="admin-container">
        <a href="dashboard.php" class="back-btn">← Voltar ao Dashboard</a>
        
        <h1>🌿 Gerenciador de Produtos</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '✅') !== false ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Formulário para adicionar produto -->
        <div class="form-section">
            <h2>➕ Adicionar Novo Produto</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_product">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="nome">Nome do Produto *</label>
                        <input type="text" id="nome" name="nome" required>
                    </div>
                    <div class="form-group">
                        <label for="categoria_id">Categoria *</label>
                        <select id="categoria_id" name="categoria_id" required>
                            <option value="">Selecione uma categoria</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['id']; ?>"><?php echo htmlspecialchars($categoria['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="preco">Preço (R$) *</label>
                        <input type="number" step="0.01" id="preco" name="preco" required>
                    </div>
                    <div class="form-group">
                        <label for="estoque">Estoque *</label>
                        <input type="number" id="estoque" name="estoque" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="descricao">Descrição</label>
                    <textarea id="descricao" name="descricao" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="ingredientes">Ingredientes</label>
                    <textarea id="ingredientes" name="ingredientes" rows="2"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="modo_uso">Modo de Uso</label>
                    <textarea id="modo_uso" name="modo_uso" rows="2"></textarea>
                </div>
                
                <div class="checkbox-group">
                    <label><input type="checkbox" name="vegano" checked> Vegano</label>
                    <label><input type="checkbox" name="organico"> Orgânico</label>
                    <label><input type="checkbox" name="cruelty_free" checked> Cruelty Free</label>
                </div>
                
                <br>
                <button type="submit" class="btn btn-primary">➕ Adicionar Produto</button>
            </form>
        </div>

        <!-- Lista de produtos -->
        <div class="form-section">
            <h2>📦 Produtos Cadastrados (<?php echo count($produtos); ?>)</h2>
            
            <?php if (count($produtos) > 0): ?>
                <table class="products-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Preço</th>
                            <th>Estoque</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos as $produto): ?>
                            <tr <?php echo !$produto['ativo'] ? 'class="produto-inativo"' : ''; ?>>
                                <td><?php echo $produto['id']; ?></td>
                                <td><?php echo htmlspecialchars($produto['nome']); ?></td>
                                <td><?php echo htmlspecialchars($produto['categoria_nome'] ?? 'Sem categoria'); ?></td>
                                <td>R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></td>
                                <td><?php echo $produto['estoque']; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $produto['ativo'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $produto['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td class="product-actions">
                                    <?php if ($produto['ativo']): ?>
                                        <!-- Produto ativo: mostrar opções de desativar e remover -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="product_id" value="<?php echo $produto['id']; ?>">
                                            <button type="submit" class="btn btn-warning" onclick="return confirm('Desativar produto temporariamente?')">
                                                🔒 Desativar
                                            </button>
                                        </form>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="product_id" value="<?php echo $produto['id']; ?>">
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja REMOVER este produto?\nEsta ação irá desativá-lo!')">
                                                🗑️ Remover
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <!-- Produto inativo: mostrar opções de reativar ou excluir permanentemente -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="product_id" value="<?php echo $produto['id']; ?>">
                                            <button type="submit" class="btn btn-success" onclick="return confirm('Reativar este produto?')">
                                                🔓 Reativar
                                            </button>
                                        </form>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_permanent">
                                            <input type="hidden" name="product_id" value="<?php echo $produto['id']; ?>">
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('⚠️ ATENÇÃO: Esta ação irá EXCLUIR PERMANENTEMENTE este produto do banco de dados!\n\nEsta operação NÃO PODE ser desfeita!\n\nTem certeza que deseja continuar?')">
                                                🗑️ Excluir Definitivamente
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 4px;">
                    <h4>ℹ️ Informações sobre as Ações:</h4>
                    <ul style="margin: 10px 0;">
                        <li><strong>🔒 Desativar:</strong> Produto fica inativo mas permanece no banco (pode ser reativado)</li>
                        <li><strong>🗑️ Remover:</strong> Desativa o produto (mesmo que "Desativar")</li>
                        <li><strong>🔓 Reativar:</strong> Produto inativo volta a ficar ativo</li>
                        <li><strong>🗑️ Excluir Definitivamente:</strong> Remove PERMANENTEMENTE do banco (não pode ser desfeito)</li>
                    </ul>
                </div>
            <?php else: ?>
                <p>Nenhum produto cadastrado ainda.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>