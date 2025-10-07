<?php
session_start();
require_once 'config/database.php';

$is_logged_in = isLoggedIn();
// Para a visualização da loja, admin sempre vê como usuário normal
$is_admin = false; // Forçar que admin veja como usuário normal na loja

// Buscar produtos do banco de dados
$produtos = [];

try {
    $conn = getDBConnection();
    
    // Query com DISTINCT para garantir que não há duplicação
    $stmt = $conn->prepare("
        SELECT DISTINCT p.id, p.nome, p.descricao, p.preco, p.preco_promocional, 
               p.estoque, p.ativo, c.nome as categoria_nome 
        FROM produtos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        WHERE p.ativo = 1
        ORDER BY p.id DESC
    ");
    
    $stmt->execute();
    $produtos_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filtrar duplicações por ID (extra segurança)
    $produtos = [];
    $ids_vistos = [];
    
    foreach ($produtos_raw as $produto) {
        if (!in_array($produto['id'], $ids_vistos)) {
            $produtos[] = $produto;
            $ids_vistos[] = $produto['id'];
        }
    }
    
    // Processar preços promocionais
    foreach ($produtos as &$produto) {
        if ($produto['preco_promocional'] && $produto['preco_promocional'] < $produto['preco']) {
            $produto['tem_promocao'] = true;
            $produto['desconto_percentual'] = round((($produto['preco'] - $produto['preco_promocional']) / $produto['preco']) * 100);
        } else {
            $produto['tem_promocao'] = false;
            $produto['preco_promocional'] = null;
        }
    }
    
} catch (PDOException $e) {
    error_log("Erro ao buscar produtos: " . $e->getMessage());
    $produtos = [];
}

// Retornar apenas o HTML dos produtos
foreach ($produtos as $produto): ?>
    <tr id="produto-<?php echo $produto['id']; ?>">
        <td><?php echo htmlspecialchars($produto['nome']); ?></td>
        <td><?php echo htmlspecialchars($produto['descricao']); ?></td>
        <td><?php echo htmlspecialchars($produto['categoria_nome'] ?? 'N/A'); ?></td>
        <td>R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></td>
        <td><?php echo $produto['estoque']; ?></td>
        <?php if ($is_logged_in): ?>
        <td>
            <?php if ($is_admin): ?>
                <!-- Botão de excluir para admin -->
                <button type="button" class="delete-btn" onclick="excluirProduto(<?php echo $produto['id']; ?>)">
                    <i class="fas fa-trash"></i> Excluir
                </button>
            <?php else: ?>
                <!-- Botão de adicionar ao carrinho para usuários comuns -->
                <button type="button" class="add-to-cart-btn" onclick="adicionarAoCarrinho(<?php echo $produto['id']; ?>)" 
                        <?php echo $produto['estoque'] <= 0 ? 'disabled' : ''; ?>>
                    <i class="fas fa-cart-plus"></i> 
                    <?php echo $produto['estoque'] <= 0 ? 'Sem Estoque' : 'Adicionar'; ?>
                </button>
            <?php endif; ?>
        </td>
        <?php endif; ?>
    </tr>
<?php endforeach; ?>

<?php if (empty($produtos)): ?>
    <tr>
        <td colspan="<?php echo $is_logged_in ? '6' : '5'; ?>" style="text-align: center; color: #666;">
            Nenhum produto cadastrado.
        </td>
    </tr>
<?php endif; ?>