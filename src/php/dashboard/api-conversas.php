<?php
/**
 * API para buscar lista de conversas atualizada em tempo real
 */
session_start();

// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Não autorizado']));
}

// Incluir sistema
require_once __DIR__ . '/../sistema.php';

header('Content-Type: application/json');

try {
    global $chat_manager;
    
    if (!isset($chat_manager)) {
        throw new Exception('ChatManager não foi inicializado');
    }
    
    // Buscar conversas atualizadas
    $conversas = $chat_manager->obterConversas();
    $stats = $chat_manager->obterEstatisticas();
    
    // Calcular totais
    $total_conversas = count($conversas);
    $nao_lidas_total = array_sum(array_column($conversas, 'nao_lidas'));
    $conversas_ativas = $stats['conversas_ativas'];
    $aguardando_humano = count(array_filter($conversas, fn($c) => $c['status'] == 'aguardando_humano'));
    $resolvidas = count(array_filter($conversas, fn($c) => $c['status'] == 'resolvida'));
    
    echo json_encode([
        'success' => true,
        'conversas' => $conversas,
        'stats' => [
            'total' => $total_conversas,
            'nao_lidas' => $nao_lidas_total,
            'ativas' => $conversas_ativas,
            'aguardando_humano' => $aguardando_humano,
            'resolvidas' => $resolvidas
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar conversas: ' . $e->getMessage()
    ]);
}
?>