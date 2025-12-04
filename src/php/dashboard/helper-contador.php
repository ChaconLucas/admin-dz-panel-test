<?php
/**
 * Helper para contador de mensagens não lidas
 * Inclua este arquivo em todas as páginas do dashboard
 */

if (!isset($nao_lidas)) {
    $nao_lidas = 0;
    
    if (!isset($conexao)) {
        require_once '../sistema.php';
        global $conexao;
    }
    
    try {
        if (isset($conexao) && $conexao instanceof mysqli) {
            $result = $conexao->query("SELECT COUNT(*) as total FROM mensagens WHERE lida = FALSE AND remetente != 'admin'");
            $nao_lidas = $result ? $result->fetch_assoc()['total'] : 0;
        }
    } catch (Exception $e) {
        error_log("Erro ao contar mensagens não lidas: " . $e->getMessage());
        $nao_lidas = 0;
    }
}
?>