<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    echo json_encode(['total' => 0]);
    exit();
}

try {
    $conn = getDBConnection();
    
    // Contar itens no carrinho
    $stmt = $conn->prepare("SELECT SUM(quantidade) as total FROM carrinho WHERE usuario_id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $total = $stmt->fetch()['total'] ?? 0;
    
    echo json_encode(['total' => (int)$total]);
    
} catch (Exception $e) {
    error_log("Erro ao contar carrinho: " . $e->getMessage());
    echo json_encode(['total' => 0]);
}
?>