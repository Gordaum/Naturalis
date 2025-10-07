<?php
session_start();
require_once 'config/database.php';

$erro = '';
$sucesso = '';

// Verificar se o usuário está logado
$is_logged_in = isLoggedIn();

// Para a visualização da loja, admin sempre vê como usuário normal
// Admin só tem privilégios em páginas administrativas específicas, não na loja
$is_admin = false; // Forçar que admin veja como usuário normal na loja

// Debug (remover depois)
// echo "<!-- Debug: logado=" . ($is_logged_in ? 'sim' : 'não') . ", admin=" . ($is_admin ? 'sim' : 'não') . " -->";

// Verificar mensagem de sucesso via GET
if (isset($_GET['sucesso']) && $_GET['sucesso'] == '1') {
    $sucesso = 'Produto cadastrado com sucesso!';
}

// Processar adição ao carrinho (AJAX)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && 
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' &&
    isset($_POST['adicionar_carrinho'])) {
    
    header('Content-Type: application/json');
    
    // Verificar se o usuário está logado
    if (!$is_logged_in) {
        http_response_code(401);
        echo json_encode(['erro' => 'Você precisa estar logado para adicionar produtos ao carrinho.']);
        exit();
    }
    
    $produto_id = intval($_POST['produto_id'] ?? 0);
    $quantidade = intval($_POST['quantidade'] ?? 1);
    
    if ($produto_id <= 0) {
        http_response_code(400);
        echo json_encode(['erro' => 'ID do produto inválido.']);
        exit();
    }
    
    if ($quantidade <= 0) {
        $quantidade = 1;
    }
    
    try {
        $conn = getDBConnection();
        
        // Verificar se o produto existe e está ativo
        $stmt = $conn->prepare("SELECT id, nome, preco FROM produtos WHERE id = ? AND ativo = 1");
        $stmt->execute([$produto_id]);
        $produto = $stmt->fetch();
        
        if (!$produto) {
            http_response_code(404);
            echo json_encode(['erro' => 'Produto não encontrado.']);
            exit();
        }
        
        // Usar INSERT ON DUPLICATE KEY UPDATE para evitar duplicação
        $stmt = $conn->prepare("
            INSERT INTO carrinho (usuario_id, produto_id, quantidade, preco_unitario, created_at, updated_at) 
            VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                quantidade = quantidade + VALUES(quantidade),
                updated_at = NOW()
        ");
        
        $stmt->execute([$_SESSION['usuario_id'], $produto_id, $quantidade, $produto['preco']]);
        
        // Verificar se foi inserção ou atualização
        $stmt = $conn->prepare("SELECT quantidade FROM carrinho WHERE usuario_id = ? AND produto_id = ?");
        $stmt->execute([$_SESSION['usuario_id'], $produto_id]);
        $item_atual = $stmt->fetch();
        
        if ($item_atual['quantidade'] == $quantidade) {
            $mensagem = "Produto adicionado ao carrinho!";
        } else {
            $mensagem = "Quantidade atualizada no carrinho!";
        }
        
        // Contar itens no carrinho
        $stmt = $conn->prepare("SELECT SUM(quantidade) as total FROM carrinho WHERE usuario_id = ?");
        $stmt->execute([$_SESSION['usuario_id']]);
        $total_itens = $stmt->fetch()['total'] ?? 0;
        
        echo json_encode([
            'sucesso' => true,
            'mensagem' => $mensagem,
            'total_itens' => $total_itens
        ]);
        exit();
        
    } catch (PDOException $e) {
        error_log("Erro ao adicionar ao carrinho: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao adicionar produto ao carrinho.']);
        exit();
    }
}

// Se for um POST via AJAX para EXCLUIR produto (só para admins)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && 
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' &&
    isset($_POST['excluir_produto'])) {
    
    header('Content-Type: application/json');
    
    // Verificar se o usuário é admin
    if (!$is_admin) {
        http_response_code(403);
        echo json_encode(['erro' => 'Acesso negado. Apenas administradores podem excluir produtos.']);
        exit();
    }
    
    $produto_id = intval($_POST['produto_id'] ?? 0);
    
    if ($produto_id <= 0) {
        http_response_code(400);
        echo json_encode(['erro' => 'ID do produto é obrigatório.']);
        exit();
    }
    
    try {
        $conn = getDBConnection();
        
        // Log da tentativa de exclusão
        error_log("Tentativa de exclusão - Produto ID: $produto_id - " . date('Y-m-d H:i:s'));
        
        // Verificar se o produto existe E está ativo
        $stmt = $conn->prepare("SELECT id, nome, ativo FROM produtos WHERE id = ?");
        $stmt->execute([$produto_id]);
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$produto) {
            error_log("Produto não encontrado - ID: $produto_id");
            http_response_code(404);
            echo json_encode(['erro' => 'Produto não encontrado.']);
            exit();
        }
        
        if ($produto['ativo'] == 0) {
            error_log("Produto já inativo - ID: $produto_id");
            http_response_code(400);
            echo json_encode(['erro' => 'Produto já foi excluído anteriormente.']);
            exit();
        }
        
        // Exclusão lógica (marcar como inativo) ao invés de DELETE físico
        $stmt = $conn->prepare("UPDATE produtos SET ativo = 0, updated_at = NOW() WHERE id = ? AND ativo = 1");
        $resultado = $stmt->execute([$produto_id]);
        $linhas_afetadas = $stmt->rowCount();
        
        if ($resultado && $linhas_afetadas > 0) {
            error_log("Produto excluído com sucesso - ID: $produto_id, Nome: {$produto['nome']}");
            echo json_encode([
                'sucesso' => true,
                'mensagem' => 'Produto excluído com sucesso!'
            ]);
            exit();
        } else {
            error_log("Falha na exclusão - ID: $produto_id, Linhas afetadas: $linhas_afetadas");
            http_response_code(500);
            echo json_encode(['erro' => 'Erro ao excluir produto ou produto já foi excluído.']);
            exit();
        }
        
    } catch (PDOException $e) {
        error_log("Erro ao excluir produto: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao excluir produto: ' . $e->getMessage()]);
        exit();
    }
}

// Se for um POST via AJAX, processar e retornar JSON PRIMEIRO (só para admins)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && 
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' &&
    isset($_POST['adicionar_produto'])) {
    
    header('Content-Type: application/json');
    
    // Verificar se o usuário é admin
    if (!$is_admin) {
        http_response_code(403);
        echo json_encode(['erro' => 'Acesso negado. Apenas administradores podem adicionar produtos.']);
        exit();
    }
    
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $preco = floatval($_POST['preco'] ?? 0);
    $categoria_id = intval($_POST['categoria_id'] ?? 1);
    
    if (empty($nome)) {
        http_response_code(400);
        echo json_encode(['erro' => 'Nome do produto é obrigatório.']);
        exit();
    } elseif (empty($descricao)) {
        http_response_code(400);
        echo json_encode(['erro' => 'Descrição é obrigatória.']);
        exit();
    } elseif ($preco <= 0) {
        http_response_code(400);
        echo json_encode(['erro' => 'Preço deve ser maior que zero.']);
        exit();
    } else {
        try {
            $conn = getDBConnection();
            
            // Log da tentativa de inserção
            error_log("Tentativa de inserir produto: $nome - " . date('Y-m-d H:i:s'));
            
            // Verificar se já existe um produto com o mesmo nome nos últimos 30 segundos
            $stmt_verificar = $conn->prepare("
                SELECT COUNT(*) FROM produtos 
                WHERE nome = ? AND ativo = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
            ");
            $stmt_verificar->execute([$nome]);
            $recente = $stmt_verificar->fetchColumn();
            
            if ($recente > 0) {
                error_log("Produto duplicado detectado: $nome");
                http_response_code(400);
                echo json_encode(['erro' => 'Produto similar foi adicionado recentemente. Aguarde alguns segundos.']);
                exit();
            }
            
            $stmt = $conn->prepare("
                INSERT INTO produtos (nome, descricao, preco, categoria_id, estoque, ativo) 
                VALUES (?, ?, ?, ?, 10, 1)
            ");
            
            if ($stmt->execute([$nome, $descricao, $preco, $categoria_id])) {
                $novo_id = $conn->lastInsertId();
                error_log("Produto inserido com sucesso - ID: $novo_id");
                
                echo json_encode([
                    'sucesso' => true,
                    'mensagem' => 'Produto cadastrado com sucesso!',
                    'produto_id' => $novo_id
                ]);
                exit();
            } else {
                error_log("Falha na inserção do produto: $nome");
                http_response_code(500);
                echo json_encode(['erro' => 'Erro ao cadastrar produto.']);
                exit();
            }
            
        } catch (PDOException $e) {
            error_log("Erro ao cadastrar produto: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['erro' => 'Erro ao cadastrar produto: ' . $e->getMessage()]);
            exit();
        }
    }
}

// Processamento de adicionar produto já está implementado acima via AJAX

// Buscar categorias para o formulário
$categorias = [];
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, nome FROM categorias WHERE ativo = 1 ORDER BY nome");
    $stmt->execute();
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar categorias: " . $e->getMessage());
}

// Buscar produtos do banco de dados
$produtos = [];

try {
    $conn = getDBConnection();
    
    // Query com DISTINCT para evitar duplicação
    $stmt = $conn->prepare("
        SELECT DISTINCT p.id, p.nome, p.descricao, p.preco, p.preco_promocional, 
               p.estoque, p.ativo, p.created_at, c.nome as categoria_nome 
        FROM produtos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        WHERE p.ativo = 1
        ORDER BY p.id DESC
    ");
    
    $stmt->execute();
    $produtos_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filtrar duplicações por ID (proteção adicional)
    $produtos = [];
    $ids_processados = [];
    
    foreach ($produtos_raw as $produto) {
        if (!in_array($produto['id'], $ids_processados)) {
            // Processar preços promocionais
            if (isset($produto['preco_promocional']) && 
                $produto['preco_promocional'] && 
                $produto['preco_promocional'] < $produto['preco']) {
                $produto['tem_promocao'] = true;
                $produto['desconto_percentual'] = round((($produto['preco'] - $produto['preco_promocional']) / $produto['preco']) * 100);
            } else {
                $produto['tem_promocao'] = false;
                $produto['preco_promocional'] = null;
            }
            
            $produtos[] = $produto;
            $ids_processados[] = $produto['id'];
        }
    }
    
} catch (PDOException $e) {
    // Em caso de erro, usar produtos de exemplo
    error_log("Erro ao buscar produtos: " . $e->getMessage());
    
    $produtos = [
        [
            'id' => 1,
            'nome' => 'Shampoo Natural de Coco',
            'preco' => 29.90,
            'descricao' => 'Shampoo 100% natural e vegano com óleo de coco',
            'imagem' => 'img/shampoo-coco.jpg',
            'categoria_nome' => 'Cabelos',
            'vegano' => 1,
            'organico' => 1,
            'cruelty_free' => 1,
            'tem_promocao' => false
        ],
        // Sabonete de Lavanda removido para evitar duplicação com dados reais
        [
            'id' => 3,
            'nome' => 'Óleo Essencial de Tea Tree',
            'preco' => 45.00,
            'descricao' => 'Óleo essencial de melaleuca 100% puro',
            'imagem' => 'img/oleo-tea-tree.jpg',
            'categoria_nome' => 'Óleos Essenciais',
            'vegano' => 1,
            'organico' => 0,
            'cruelty_free' => 1,
            'tem_promocao' => false
        ]
    ];
}

// Retorna os produtos em formato JSON se for uma requisição AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($produtos);
    exit();
}


?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos - Naturallis</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .produtos-container {
            max-width: 1000px;
            margin: 2rem auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 2rem;
        }
        .produtos-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo-img {
            max-width: 120px;
            margin-bottom: 1rem;
            border-radius: 8px;
        }
        .add-form {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f4f8f4;
            border-radius: 8px;
        }
        .add-form input, .add-form select {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
        }
        .add-form button {
            background: #6aa84f;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }
        .add-form button:hover {
            background: #5a9042;
        }
        .produtos-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        .produtos-table th, .produtos-table td {
            border: 1px solid #e0e0e0;
            padding: 1rem;
            text-align: left;
        }
        .produtos-table th {
            background: #6aa84f;
            color: #fff;
        }
        .produtos-table tr:nth-child(even) {
            background: #f4f8f4;
        }
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .voltar-btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #6aa84f;
            color: #fff;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            margin-top: 1rem;
        }
        .voltar-btn:hover {
            background: #5a9042;
        }
        .delete-btn {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 0.5rem 1rem;
            cursor: pointer;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.3s;
        }
        .delete-btn:hover {
            background: #c82333;
        }
        .mensagem-ajax {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            display: none;
        }
        .mensagem-ajax.sucesso {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .mensagem-ajax.erro {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .info-loja {
            background: linear-gradient(135deg, #f4f8f4, #e8f5e8);
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
            border-left: 4px solid #6aa84f;
        }
        .info-loja h3 {
            color: #6aa84f;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        .info-loja p {
            color: #666;
            font-size: 1.1rem;
            line-height: 1.6;
            margin: 0;
        }
        .carrinho-header {
            position: absolute;
            top: 1rem;
            right: 1rem;
        }
        .carrinho-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            background: #6aa84f;
            color: white;
            text-decoration: none;
            border-radius: 25px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .carrinho-link:hover {
            background: #5a9042;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .carrinho-link i {
            margin-right: 0.5rem;
            font-size: 1.2rem;
        }
        .carrinho-contador {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            margin-left: 0.5rem;
            min-width: 20px;
            text-align: center;
            font-weight: bold;
        }
        .add-to-cart-btn {
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 0.5rem 1rem;
            cursor: pointer;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.3s;
        }
        .add-to-cart-btn:hover {
            background: #218838;
        }
        .add-to-cart-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        .produtos-header {
            position: relative;
        }
        @media (max-width: 768px) {
            .add-form {
                grid-template-columns: 1fr;
            }
            .info-loja {
                padding: 1.5rem;
            }
            .info-loja h3 {
                font-size: 1.3rem;
            }
            .carrinho-header {
                position: static;
                text-align: center;
                margin-top: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="produtos-container">
        <div class="produtos-header">
            <img src="naturallis.jpeg" alt="Naturallis Logo" class="logo-img">
            <h2>Produtos Veganos e Sustentáveis</h2>
            
            <!-- Ícone do carrinho (apenas para usuários logados não-admin) -->
            <?php if ($is_logged_in && !$is_admin): ?>
            <div class="carrinho-header">
                <a href="carrinho.php" class="carrinho-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="carrinho-texto">Carrinho</span>
                    <span id="carrinho-contador" class="carrinho-contador">0</span>
                </a>
            </div>
            <?php endif; ?>
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

        <!-- Formulário para adicionar produto (apenas para admins) -->
        <?php if ($is_admin): ?>
        <form id="form-adicionar-produto" class="add-form">
            <input type="text" id="nome" name="nome" placeholder="Nome do produto" required>
            <input type="text" id="descricao" name="descricao" placeholder="Descrição" required>
            <input type="number" id="preco" name="preco" placeholder="Preço" step="0.01" min="0" required>
            <select id="categoria_id" name="categoria_id" required>
                <option value="">Selecione a categoria</option>
                <?php foreach ($categorias as $categoria): ?>
                    <option value="<?php echo $categoria['id']; ?>">
                        <?php echo htmlspecialchars($categoria['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">
                <i class="fas fa-plus"></i> Adicionar Produto
            </button>
        </form>
        <?php else: ?>
        <div class="info-loja">
            <h3>🌿 Nossos Produtos Naturais</h3>
            <p>Explore nossa seleção de produtos veganos e sustentáveis. Cada produto é cuidadosamente selecionado para oferecer o melhor da natureza.</p>
        </div>
        <?php endif; ?>

        <table class="produtos-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Descrição</th>
                    <th>Categoria</th>
                    <th>Preço</th>
                    <th>Estoque</th>
                    <?php if ($is_logged_in): ?>
                    <th>Ações</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($produtos as $produto): ?>
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
            </tbody>
        </table>

        <a href="index.php" class="voltar-btn">
            <i class="fas fa-home"></i> Voltar para Home
        </a>
    </div>

    <!-- Div para mensagens AJAX -->
    <div id="mensagem-ajax" class="mensagem-ajax"></div>

    <script>
        function mostrarMensagem(texto, tipo = 'sucesso') {
            const mensagem = document.getElementById('mensagem-ajax');
            mensagem.textContent = texto;
            mensagem.className = `mensagem-ajax ${tipo}`;
            mensagem.style.display = 'block';
            
            setTimeout(() => {
                mensagem.style.display = 'none';
            }, 3000);
        }

        async function excluirProduto(id) {
            if (!confirm('Deseja realmente excluir este produto?')) {
                return;
            }

            // Prevenir múltiplas exclusões simultâneas
            const botao = document.querySelector(`button[onclick*="excluirProduto(${id})"]`);
            if (botao) {
                if (botao.disabled) {
                    console.log('Exclusão já em andamento para este produto');
                    return;
                }
                botao.disabled = true;
                botao.style.opacity = '0.5';
                botao.innerHTML = '🔄 Excluindo...';
            }

            try {
                const formData = new FormData();
                formData.append('excluir_produto', '1');
                formData.append('produto_id', id);

                const response = await fetch('produtos.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                if (response.ok) {
                    const result = await response.json();
                    if (result.sucesso) {
                        mostrarMensagem(result.mensagem);
                        
                        // Animação suave de remoção
                        const linha = document.getElementById(`produto-${id}`);
                        if (linha) {
                            linha.style.transition = 'all 0.3s ease';
                            linha.style.backgroundColor = '#ffcccc';
                            linha.style.opacity = '0.3';
                            
                            setTimeout(() => {
                                linha.remove();
                            }, 300);
                        } else {
                            console.log('Produto removido com sucesso');
                        }
                    } else {
                        mostrarMensagem(result.erro || 'Erro ao excluir produto', 'erro');
                    }
                } else {
                    const errorData = await response.json();
                    mostrarMensagem(errorData.erro || 'Erro ao excluir produto', 'erro');
                }
            } catch (error) {
                console.error('Erro na requisição:', error);
                mostrarMensagem('Erro de conexão com o servidor', 'erro');
            } finally {
                // Restaurar botão em caso de erro
                if (botao && botao.disabled) {
                    botao.disabled = false;
                    botao.style.opacity = '1';
                    botao.innerHTML = '<i class="fas fa-trash"></i> Excluir';
                }
            }
        }

        // Função para adicionar produto ao carrinho
        async function adicionarAoCarrinho(produtoId) {
            // Prevenir cliques duplos
            const botao = document.querySelector(`button[onclick="adicionarAoCarrinho(${produtoId})"]`);
            if (botao && botao.disabled) {
                return; // Já está processando
            }
            
            // Desabilitar botão temporariamente
            if (botao) {
                botao.disabled = true;
                botao.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adicionando...';
            }
            
            try {
                const formData = new FormData();
                formData.append('adicionar_carrinho', '1');
                formData.append('produto_id', produtoId);
                formData.append('quantidade', 1);

                const response = await fetch('produtos.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                if (response.ok) {
                    const result = await response.json();
                    if (result.sucesso) {
                        mostrarMensagem(result.mensagem);
                        // Atualizar contador do carrinho
                        const contador = document.getElementById('carrinho-contador');
                        if (contador) {
                            contador.textContent = result.total_itens || 0;
                        }
                    } else {
                        mostrarMensagem(result.erro || 'Erro ao adicionar ao carrinho', 'erro');
                    }
                } else {
                    const errorData = await response.json();
                    mostrarMensagem(errorData.erro || 'Erro ao adicionar ao carrinho', 'erro');
                }
            } catch (error) {
                console.error('Erro na requisição:', error);
                mostrarMensagem('Erro de conexão com o servidor', 'erro');
            } finally {
                // Reabilitar botão após 1 segundo
                if (botao) {
                    setTimeout(() => {
                        botao.disabled = false;
                        botao.innerHTML = '<i class="fas fa-cart-plus"></i> Adicionar';
                    }, 1000);
                }
            }
        }

        // Processar formulário de adicionar produto via AJAX
        document.addEventListener('DOMContentLoaded', function() {
            carregarContadorCarrinho();
            
            const formAdicionar = document.getElementById('form-adicionar-produto');
            if (formAdicionar) {
                let isSubmitting = false; // Flag para prevenir submissões múltiplas
                
                formAdicionar.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    // Prevenir submissão múltipla
                    if (isSubmitting) {
                        console.log('Submissão já em andamento, ignorando...');
                        return;
                    }
                    
                    isSubmitting = true;
                    
                    const formData = new FormData(this);
                    formData.append('adicionar_produto', '1');
                    
                    const botao = this.querySelector('button[type="submit"]');
                    const textoOriginal = botao.innerHTML;
                    botao.disabled = true;
                    botao.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adicionando...';
                    
                    try {
                        const response = await fetch('produtos.php', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        });

                        if (response.ok) {
                            const result = await response.json();
                            if (result.sucesso) {
                                mostrarMensagem('Produto adicionado com sucesso!');
                                this.reset(); // Limpar formulário
                            } else {
                                mostrarMensagem(result.erro || 'Erro ao adicionar produto', 'erro');
                            }
                        } else {
                            const errorData = await response.json();
                            mostrarMensagem(errorData.erro || 'Erro ao adicionar produto', 'erro');
                        }
                    } catch (error) {
                        console.error('Erro na requisição:', error);
                        mostrarMensagem('Erro de conexão com o servidor', 'erro');
                    } finally {
                        isSubmitting = false; // Liberar flag
                        botao.disabled = false;
                        botao.innerHTML = textoOriginal;
                    }
                });
            }
        });

        async function carregarContadorCarrinho() {
            try {
                const response = await fetch('get_carrinho_count.php');
                if (response.ok) {
                    const result = await response.json();
                    const contador = document.getElementById('carrinho-contador');
                    if (contador && result.total !== undefined) {
                        contador.textContent = result.total;
                    }
                }
            } catch (error) {
                console.error('Erro ao carregar contador:', error);
            }
        }

        let isUpdatingList = false; // Flag para evitar atualizações simultâneas

        async function atualizarListaProdutos() {
            // Evitar atualizações simultâneas que causam duplicação
            if (isUpdatingList) {
                console.log('Atualização já em andamento, ignorando...');
                return;
            }
            
            isUpdatingList = true;
            
            try {
                // Adicionar timestamp para evitar cache
                const timestamp = new Date().getTime();
                const response = await fetch(`get_produtos_lista.php?t=${timestamp}`);
                
                if (response.ok) {
                    const html = await response.text();
                    const tbody = document.querySelector('.produtos-table tbody');
                    if (tbody) {
                        // Salvar scroll position
                        const scrollPos = window.scrollY;
                        
                        // Limpar completamente antes de inserir novo conteúdo
                        tbody.innerHTML = '';
                        
                        // Pequeno delay para garantir que o DOM foi limpo
                        await new Promise(resolve => setTimeout(resolve, 50));
                        
                        // Inserir novo HTML
                        tbody.innerHTML = html;
                        
                        // Restaurar scroll position
                        window.scrollTo(0, scrollPos);
                        
                        console.log('Lista de produtos atualizada com sucesso');
                    }
                } else {
                    throw new Error('Falha na requisição');
                }
            } catch (error) {
                console.error('Erro ao atualizar lista:', error);
                // Se falhar, recarregar a página como fallback
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } finally {
                // Sempre liberar a flag, mesmo em caso de erro
                setTimeout(() => {
                    isUpdatingList = false;
                }, 500);
            }
        }
    </script>
</body>
</html>