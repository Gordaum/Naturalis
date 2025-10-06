<?php
// Iniciar a sessão para acessar os dados do usuário
session_start();
require_once 'config/database.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    // Se a requisição for AJAX, retorna erro em JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        http_response_code(401);
        echo json_encode(['erro' => 'Usuário não está logado']);
        exit();
    }
    // Se for acesso direto, redireciona para login
    header('Location: login.html');
    exit();
}

// Verificar se houve atualização com sucesso
$mensagem_sucesso = '';
if (isset($_GET['sucesso']) && $_GET['sucesso'] == '1') {
    $mensagem_sucesso = 'Perfil atualizado com sucesso!';
}

// Buscar dados completos do usuário no banco de dados
$usuario = [];

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT 
            nome, 
            email, 
            telefone, 
            cidade, 
            estado, 
            cep,
            DATE_FORMAT(data_cadastro, '%d/%m/%Y') as data_cadastro,
            DATE_FORMAT(ultimo_acesso, '%d/%m/%Y %H:%i') as ultimo_acesso,
            DATE_FORMAT(data_nascimento, '%d/%m/%Y') as data_nascimento
        FROM usuarios 
        WHERE id = ? AND ativo = 1
    ");
    
    $stmt->execute([$_SESSION['usuario_id']]);
    
    if ($stmt->rowCount() == 1) {
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Usuário não encontrado, fazer logout
        session_destroy();
        header('Location: login.html?erro=4');
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Erro ao buscar perfil: " . $e->getMessage());
    
    // Dados de fallback da sessão
    $usuario = [
        'nome' => $_SESSION['usuario'] ?? 'Usuário',
        'email' => $_SESSION['email'] ?? 'email@exemplo.com',
        'telefone' => null,
        'cidade' => null,
        'estado' => null,
        'cep' => null,
        'data_cadastro' => date('d/m/Y'),
        'ultimo_acesso' => date('d/m/Y H:i'),
        'data_nascimento' => null
    ];
}

// Se for requisição AJAX, retorna os dados em JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($usuario);
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Naturallis</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container {
            background-color: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
            margin: 2rem auto;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
        }

        .profile-picture {
            width: 120px;
            height: 120px;
            background-color: #6aa84f;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 3em;
        }

        .profile-info {
            background-color: #f4f8f4;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .info-group {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #dde5dd;
        }

        .info-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .info-label {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 0.3rem;
        }

        .info-value {
            color: #333;
            font-size: 1.1em;
            font-weight: 500;
        }

        .profile-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1.5rem;
        }



        .profile-actions button {
            padding: 0.75rem;
        }

        .profile-actions button.secondary {
            background-color: #fff;
            border: 2px solid #6aa84f;
            color: #6aa84f;
        }

        .profile-actions button.secondary:hover {
            background-color: #f4f8f4;
        }



        .logout-alt-button {
            background-color: #fd7e14;
            color: white;
            border: none;
            padding: 0.75rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            width: 100%;
        }

        .logout-alt-button:hover {
            background-color: #e8680e;
        }

        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }

        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <?php if ($mensagem_sucesso): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($mensagem_sucesso); ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-header">
            <div class="profile-picture">
                <i class="fas fa-user"></i>
            </div>
            <h2>Meu Perfil</h2>
        </div>

        <div class="profile-info">
            <div class="info-group">
                <div class="info-label">Nome</div>
                <div class="info-value"><?php echo htmlspecialchars($usuario['nome']); ?></div>
            </div>
            <div class="info-group">
                <div class="info-label">E-mail</div>
                <div class="info-value"><?php echo htmlspecialchars($usuario['email']); ?></div>
            </div>
            <div class="info-group">
                <div class="info-label">Data de Cadastro</div>
                <div class="info-value"><?php echo date('d/m/Y', strtotime($usuario['data_cadastro'])); ?></div>
            </div>
            <div class="info-group">
                <div class="info-label">Último Acesso</div>
                <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($usuario['ultimo_acesso'])); ?></div>
            </div>
        </div>

        <div class="profile-actions">
            <button onclick="window.location.href='editar_perfil.php'" class="secondary">
                <i class="fas fa-edit"></i> Editar Perfil
            </button>
            <button onclick="window.location.href='index.php'">
                <i class="fas fa-home"></i> Voltar para Home
            </button>
        </div>
        
        <form action="logout.php" method="post" style="margin-top: 1rem;">
            <button type="submit" class="logout-alt-button">
                <i class="fas fa-power-off"></i> Logout
            </button>
        </form>
    </div>
</body>
</html>