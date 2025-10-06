<?php
session_start();
require_once 'config/database.php';

$erro = '';
$sucesso = '';

// Processar formulário de cadastro
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['password'] ?? '');
    $confirmar_senha = trim($_POST['confirm_password'] ?? '');
    
    // Validações
    if (empty($nome)) {
        $erro = "Nome é obrigatório.";
    } elseif (empty($email)) {
        $erro = "Email é obrigatório.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Email inválido.";
    } elseif (empty($senha)) {
        $erro = "Senha é obrigatória.";
    } elseif (strlen($senha) < 6) {
        $erro = "Senha deve ter pelo menos 6 caracteres.";
    } elseif ($senha !== $confirmar_senha) {
        $erro = "Senhas não coincidem.";
    } else {
        try {
            $conn = getDBConnection();
            
            // Verificar se o email já existe
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND ativo = 1");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $erro = "Este email já está cadastrado. Tente fazer login.";
            } else {
                // Hash da senha
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                
                // Inserir novo usuário
                $stmt = $conn->prepare("
                    INSERT INTO usuarios (nome, email, senha, role, ativo, created_at) 
                    VALUES (?, ?, ?, 'usuario', 1, NOW())
                ");
                
                $stmt->execute([$nome, $email, $senha_hash]);
                
                if ($stmt->rowCount() > 0) {
                    // Buscar o ID do usuário recém-criado
                    $usuario_id = $conn->lastInsertId();
                    
                    // Criar sessão do usuário
                    $_SESSION['usuario_id'] = $usuario_id;
                    $_SESSION['usuario'] = $nome;
                    $_SESSION['email'] = $email;
                    $_SESSION['role'] = 'usuario';
                    
                    // Redirecionar para a página inicial
                    header('Location: index.php?sucesso=cadastro');
                    exit();
                } else {
                    $erro = "Erro ao criar conta. Tente novamente.";
                }
            }
        } catch (PDOException $e) {
            error_log("Erro ao cadastrar usuário: " . $e->getMessage());
            $erro = "Erro interno. Tente novamente mais tarde.";
        }
    }
}

// Se chegou até aqui e há um erro, redirecionar para o cadastro com o erro
if (!empty($erro)) {
    // Codificar o erro para passar na URL
    $erro_encoded = urlencode($erro);
    header("Location: cadastro.html?erro=" . $erro_encoded);
    exit();
}

// Se não há POST e não há erro, redirecionar para cadastro
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: cadastro.html');
    exit();
}
?>