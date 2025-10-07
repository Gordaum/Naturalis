<?php
session_start();
require_once '../config/database.php';

// Verificar se é admin
if (!isset($_SESSION['usuario_role']) || $_SESSION['usuario_role'] !== 'admin') {
    header('Location: ../login.html?erro=4'); // Não autorizado
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - Naturallis</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .admin-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .admin-card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #4CAF50;
        }
        
        .admin-card h3 {
            color: #2E7D32;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .admin-card .number {
            font-size: 2.5em;
            font-weight: bold;
            color: #4CAF50;
            margin: 10px 0;
        }
        
        .admin-nav {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 30px;
        }
        
        .nav-button {
            display: block;
            background: #4CAF50;
            color: white;
            padding: 15px 20px;
            text-decoration: none;
            border-radius: 6px;
            text-align: center;
            font-weight: bold;
            transition: background 0.3s;
        }
        
        .nav-button:hover {
            background: #45a049;
        }
        
        .nav-button.danger {
            background: #f44336;
        }
        
        .nav-button.danger:hover {
            background: #da190b;
        }
        
        .logout-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #f44336;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <a href="../logout.php" class="logout-btn">🚪 Sair</a>
        
        <div class="admin-header">
            <h1>🛠️ Painel Administrativo</h1>
            <p>Bem-vindo, <?php echo htmlspecialchars($_SESSION['usuario']); ?>!</p>
        </div>

        <?php
        try {
            $conn = getDBConnection();
            
            // Estatísticas do sistema
            $stats = [];
            
            // Total de usuários
            $stmt = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE ativo = 1");
            $stats['usuarios'] = $stmt->fetch()['total'];
            
            // Total de produtos
            $stmt = $conn->query("SELECT COUNT(*) as total FROM produtos WHERE ativo = 1");
            $stats['produtos'] = $stmt->fetch()['total'];
            
            // Total de pedidos
            $stmt = $conn->query("SELECT COUNT(*) as total FROM pedidos");
            $stats['pedidos'] = $stmt->fetch()['total'];
            
            // Total de logins hoje
            $stmt = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE DATE(ultimo_acesso) = CURDATE()");
            $stats['logins_hoje'] = $stmt->fetch()['total'];
            
        } catch (Exception $e) {
            $stats = ['usuarios' => 0, 'produtos' => 0, 'pedidos' => 0, 'logins_hoje' => 0];
        }
        ?>

        <div class="admin-cards">
            <div class="admin-card">
                <h3>👥 Usuários Ativos</h3>
                <div class="number"><?php echo $stats['usuarios']; ?></div>
                <p>Total de usuários cadastrados no sistema</p>
            </div>
            
            <div class="admin-card">
                <h3>🌿 Produtos</h3>
                <div class="number"><?php echo $stats['produtos']; ?></div>
                <p>Produtos ativos no catálogo</p>
            </div>
            
            <div class="admin-card">
                <h3>📦 Pedidos</h3>
                <div class="number"><?php echo $stats['pedidos']; ?></div>
                <p>Total de pedidos realizados</p>
            </div>
            
            <div class="admin-card">
                <h3>🔐 Logins Hoje</h3>
                <div class="number"><?php echo $stats['logins_hoje']; ?></div>
                <p>Usuários que fizeram login hoje</p>
            </div>
        </div>

        <div class="admin-nav">
            <a href="gerenciar_produtos.php" class="nav-button">
                🌿 Gerenciar Produtos
            </a>
            <a href="logs_login.php" class="nav-button">
                📊 Logs de Login
            </a>
            <a href="usuarios.php" class="nav-button">
                👥 Gerenciar Usuários
            </a>
            <a href="pedidos.php" class="nav-button">
                📦 Ver Pedidos
            </a>
            <a href="../index.php" class="nav-button">
                🏠 Voltar ao Site
            </a>
        </div>
    </div>
</body>
</html>