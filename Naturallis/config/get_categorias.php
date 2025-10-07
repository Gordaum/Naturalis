<?php
header('Content-Type: application/json');
require_once 'database.php';

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, nome FROM categorias WHERE ativo = 1 ORDER BY nome");
    $stmt->execute();
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($categorias);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao buscar categorias: ' . $e->getMessage()]);
}
?>