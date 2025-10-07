<?php
// Limpeza completa de sessão
session_start();

// Limpar todas as variáveis de sessão
$_SESSION = array();

// Se for usado cookie de sessão, delete também
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir a sessão
session_destroy();

// Redirecionar para o login com mensagem
header('Location: login.html?logout=1');
exit();
?>