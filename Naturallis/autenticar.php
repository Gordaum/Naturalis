<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        header('Location: login.html?erro=2'); // Campos vazios
        exit();
    }

    try {
        $conn = getDBConnection();
        
        // Buscar usuário pelo email
        $stmt = $conn->prepare("SELECT id, nome, email, senha, ativo, ultimo_acesso, role FROM usuarios WHERE email = ? AND ativo = 1");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() == 1) {
            $usuario = $stmt->fetch();
            
            // Verificar senha
            if (verifyPassword($senha, $usuario['senha'])) {
                // Login bem-sucedido
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario'] = $usuario['nome'];
                $_SESSION['email'] = $usuario['email'];
                $_SESSION['ultimo_acesso'] = $usuario['ultimo_acesso'];
                $_SESSION['usuario_role'] = $usuario['role'] ?? 'usuario';
                
                // Registrar log de login
                try {
                    $logStmt = $conn->prepare("
                        INSERT INTO login_logs (usuario_id, ip_address, user_agent, success) 
                        VALUES (?, ?, ?, 1)
                    ");
                    $logStmt->execute([
                        $usuario['id'],
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                    ]);
                } catch (Exception $e) {
                    error_log("Erro ao registrar log de login: " . $e->getMessage());
                }
                
                // Atualizar último acesso
                $updateStmt = $conn->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?");
                $updateStmt->execute([$usuario['id']]);
                
                // Redirecionar baseado no tipo de usuário
                if (isset($_SESSION['usuario_role']) && $_SESSION['usuario_role'] === 'admin') {
                    header('Location: admin/dashboard.php');
                } else {
                    header('Location: perfil.php');
                }
                exit();
            } else {
                // Senha incorreta
                header('Location: login.html?erro=1');
                exit();
            }
        } else {
            // Usuário não encontrado
            header('Location: login.html?erro=1');
            exit();
        }
        
    } catch (PDOException $e) {
        error_log("Erro no login: " . $e->getMessage());
        header('Location: login.html?erro=3'); // Erro interno
        exit();
    }
} else {
    header('Location: login.html');
    exit();
}
?>