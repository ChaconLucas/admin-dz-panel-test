<?php
/**
 * API para chat com IA usando o sistema existente
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Verificar se é OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Só aceitar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit();
}

// Incluir sistema existente
require_once __DIR__ . '/sistema.php';

// Processar dados da requisição
try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
        exit();
    }
    
    $action = $input['action'] ?? '';
    
    // Usar o sistema existente de chat
    handleClientAPI($chat_manager, $action);
    
} catch (Exception $e) {
    error_log("Erro no chat-api: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Erro interno do servidor'
    ]);
}
?>