<?php
/**
 * API para buscar mensagens de uma conversa em tempo real
 */
session_start();

// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Não autorizado']));
}

// Incluir sistema
require_once '../sistema.php';

header('Content-Type: application/json');

try {
    $conversa_id = isset($_GET['conversa_id']) ? (int)$_GET['conversa_id'] : 0;
    $ultima_mensagem_id = isset($_GET['ultima_id']) ? (int)$_GET['ultima_id'] : 0;
    
    if ($conversa_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID da conversa inválido']);
        exit;
    }
    
    // Buscar mensagens novas (apenas se ultima_mensagem_id for fornecido)
    if ($ultima_mensagem_id > 0) {
        $sql = "SELECT id, remetente, conteudo, timestamp, lida 
                FROM mensagens 
                WHERE conversa_id = ? AND id > ? 
                ORDER BY timestamp ASC";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("ii", $conversa_id, $ultima_mensagem_id);
    } else {
        // Buscar todas as mensagens da conversa
        $sql = "SELECT id, remetente, conteudo, timestamp, lida 
                FROM mensagens 
                WHERE conversa_id = ? 
                ORDER BY timestamp ASC";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("i", $conversa_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $mensagens = [];
    while ($row = $result->fetch_assoc()) {
        $mensagens[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'mensagens' => $mensagens,
        'total' => count($mensagens)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar mensagens: ' . $e->getMessage()
    ]);
}
?>