<?php
session_start();

// Processar ação
if (isset($_GET['acao'])) {
    switch ($_GET['acao']) {
        case 'admin':
            $_SESSION['usuario_id'] = 1;
            $_SESSION['usuario'] = 'Admin Sistema';
            $_SESSION['usuario_role'] = 'admin';
            $_SESSION['email'] = 'admin@naturallis.com';
            break;
            
        case 'usuario':
            $_SESSION['usuario_id'] = 2;
            $_SESSION['usuario'] = 'Usuário Teste';
            $_SESSION['usuario_role'] = 'usuario';
            $_SESSION['email'] = 'usuario@teste.com';
            break;
            
        case 'logout':
            session_destroy();
            session_start();
            break;
    }
    
    // Redirecionar para onde foi solicitado
    $redirect = $_GET['redirect'] ?? 'produtos.php';
    header('Location: ' . $redirect);
    exit();
}

// Se chegou aqui sem parâmetros, mostrar página de seleção
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trocar Perfil - Naturallis</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: #f5f5f5; 
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container { 
            background: white; 
            padding: 40px; 
            border-radius: 10px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
        }
        h1 { 
            color: #2c5530; 
            margin-bottom: 30px;
        }
        .perfil-btn {
            display: block;
            width: 100%;
            padding: 15px;
            margin: 15px 0;
            text-decoration: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            transition: transform 0.2s;
        }
        .perfil-btn:hover {
            transform: translateY(-2px);
        }
        .btn-admin {
            background: #dc3545;
            color: white;
        }
        .btn-usuario {
            background: #007bff;
            color: white;
        }
        .btn-logout {
            background: #6c757d;
            color: white;
        }
        .btn-voltar {
            background: #28a745;
            color: white;
        }
        .info {
            background: #e8f5e8;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Trocar Perfil de Acesso</h1>
        
        <div class="info">
            <p><strong>Página de Teste</strong></p>
            <p>Escolha o perfil para testar a aplicação:</p>
        </div>
        
        <a href="?acao=admin&redirect=produtos.php" class="perfil-btn btn-admin">
            👨‍💼 Entrar como Administrador
        </a>
        
        <a href="?acao=usuario&redirect=produtos.php" class="perfil-btn btn-usuario">
            👤 Entrar como Usuário Normal
        </a>
        
        <a href="?acao=logout&redirect=produtos.php" class="perfil-btn btn-logout">
            🚪 Fazer Logout (Visitante)
        </a>
        
        <hr style="margin: 30px 0;">
        
        <h3>🧪 Páginas de Teste</h3>
        
        <a href="?acao=admin&redirect=produtos_teste_limpo.php" class="perfil-btn btn-admin">
            👨‍💼 Admin → Página Teste Limpa
        </a>
        
        <a href="?acao=usuario&redirect=produtos_teste_limpo.php" class="perfil-btn btn-usuario">
            👤 Usuário → Página Teste Limpa
        </a>
        
        <hr style="margin: 30px 0;">
        
        <a href="produtos.php" class="perfil-btn btn-voltar">
            🏠 Voltar para Produtos
        </a>
    </div>
</body>
</html>