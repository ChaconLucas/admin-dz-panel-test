<?php
/**
 * API para retornar contador de mensagens não lidas em tempo real
 */
session_start();

// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Não autorizado']));
}

// Incluir sistema
require_once '../sistema.php';
global $conexao;

header('Content-Type: application/json');

try {
    $nao_lidas = 0;
    if (isset($conexao) && $conexao instanceof mysqli) {
        $result = $conexao->query("SELECT COUNT(*) as total FROM mensagens WHERE lida = FALSE AND remetente != 'admin'");
        $nao_lidas = $result ? $result->fetch_assoc()['total'] : 0;
    }
    
    echo json_encode([
        'success' => true,
        'nao_lidas' => $nao_lidas
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao contar mensagens: ' . $e->getMessage()
    ]);
}
?>