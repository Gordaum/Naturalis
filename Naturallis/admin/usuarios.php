<?php
session_start();
require_once '../config/database.php';

// Verificar se é admin
if (!isset($_SESSION['usuario_role']) || $_SESSION['usuario_role'] !== 'admin') {
    header('Location: ../login.html?erro=4');
    exit;
}

$conn = getDBConnection();
$message = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'toggle_status':
            try {
                $stmt = $conn->prepare("UPDATE usuarios SET ativo = !ativo WHERE id = ?");
                $stmt->execute([$_POST['user_id']]);
                $message = "✅ Status do usuário alterado com sucesso!";
            } catch (Exception $e) {
                $message = "❌ Erro ao alterar status: " . $e->getMessage();
            }
            break;
            
        case 'make_admin':
            try {
                $stmt = $conn->prepare("UPDATE usuarios SET role = 'admin' WHERE id = ?");
                $stmt->execute([$_POST['user_id']]);
                $message = "✅ Usuário promovido a administrador!";
            } catch (Exception $e) {
                $message = "❌ Erro ao promover usuário: " . $e->getMessage();
            }
            break;
            
        case 'remove_admin':
            try {
                // Verificar se não é o próprio usuário logado
                if ($_POST['user_id'] == $_SESSION['usuario_id']) {
                    $message = "❌ Você não pode remover seus próprios privilégios de admin!";
                } else {
                    $stmt = $conn->prepare("UPDATE usuarios SET role = 'usuario' WHERE id = ?");
                    $stmt->execute([$_POST['user_id']]);
                    $message = "✅ Privilégios de administrador removidos!";
                }
            } catch (Exception $e) {
                $message = "❌ Erro ao remover admin: " . $e->getMessage();
            }
            break;
            
        case 'delete_user':
            try {
                // Verificar se não é o próprio usuário logado
                if ($_POST['user_id'] == $_SESSION['usuario_id']) {
                    $message = "❌ Você não pode deletar sua própria conta!";
                } else {
                    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
                    $stmt->execute([$_POST['user_id']]);
                    $message = "✅ Usuário deletado com sucesso!";
                }
            } catch (Exception $e) {
                $message = "❌ Erro ao deletar usuário: " . $e->getMessage();
            }
            break;
    }
}

// Buscar todos os usuários
$stmt = $conn->query("
    SELECT id, nome, email, role, ativo, data_cadastro, ultimo_acesso,
           telefone, cidade, estado
    FROM usuarios 
    ORDER BY data_cadastro DESC
");
$usuarios = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Admin Naturallis</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .nav-buttons {
            padding: 20px 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        .nav-button {
            display: inline-block;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 25px;
            margin-right: 10px;
            transition: all 0.3s ease;
        }
        .nav-button:hover { background: #5a6268; transform: translateY(-2px); }
        .nav-button.dashboard { background: #28a745; }
        .content { padding: 30px; }
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        .message.success {
            background-color: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .message.error {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e9ecef; }
        th { background: #f8f9fa; font-weight: 600; color: #495057; }
        tr:hover { background-color: #f8f9fa; }
        .current-user { background-color: #fff3cd !important; border-left: 4px solid #ffc107; }
        .badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .badge.admin { background: #dc3545; color: white; }
        .badge.user { background: #28a745; color: white; }
        .badge.active { background: #28a745; color: white; }
        .badge.inactive { background: #6c757d; color: white; }
        .action-buttons { display: flex; gap: 5px; flex-wrap: wrap; }
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            text-decoration: none;
            color: white;
            transition: all 0.3s ease;
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .btn.toggle { background: #ffc107; }
        .btn.make-admin { background: #dc3545; }
        .btn.remove-admin { background: #fd7e14; }
        .btn.delete { background: #6c757d; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-users"></i> Gerenciar Usuários</h1>
            <p>Controle total sobre os usuários do sistema</p>
        </div>

        <div class="nav-buttons">
            <a href="dashboard.php" class="nav-button dashboard">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="gerenciar_produtos.php" class="nav-button">
                <i class="fas fa-box"></i> Produtos
            </a>
            <a href="../logout.php" class="nav-button">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo strpos($message, '✅') !== false ? 'success' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Cadastro</th>
                            <th>Último Acesso</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr <?php echo $usuario['id'] == $_SESSION['usuario_id'] ? 'class="current-user"' : ''; ?>>
                                <td><?php echo $usuario['id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($usuario['nome']); ?>
                                    <?php if ($usuario['id'] == $_SESSION['usuario_id']): ?>
                                        <small>(Você)</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                <td>
                                    <span class="badge <?php echo $usuario['role'] === 'admin' ? 'admin' : 'user'; ?>">
                                        <?php echo $usuario['role'] === 'admin' ? 'Admin' : 'Usuário'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $usuario['ativo'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $usuario['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($usuario['data_cadastro'])); ?></td>
                                <td>
                                    <?php echo $usuario['ultimo_acesso'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_acesso'])) : 'Nunca'; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- Toggle Status -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                                            <button type="submit" class="btn toggle" 
                                                    onclick="return confirm('Alterar status do usuário?')">
                                                <?php echo $usuario['ativo'] ? '🔒' : '🔓'; ?>
                                            </button>
                                        </form>

                                        <!-- Promover/Rebaixar admin -->
                                        <?php if ($usuario['role'] === 'usuario'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="make_admin">
                                                <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                                                <button type="submit" class="btn make-admin" 
                                                        onclick="return confirm('Promover usuário a administrador?')">
                                                    ⬆️
                                                </button>
                                            </form>
                                        <?php elseif ($usuario['id'] != $_SESSION['usuario_id']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="remove_admin">
                                                <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                                                <button type="submit" class="btn remove-admin" 
                                                        onclick="return confirm('Remover privilégios de admin?')">
                                                    ⬇️
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Deletar usuário -->
                                        <?php if ($usuario['id'] != $_SESSION['usuario_id']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                                                <button type="submit" class="btn delete" 
                                                        onclick="return confirm('ATENÇÃO: Deletar usuário?')">
                                                    🗑️
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>