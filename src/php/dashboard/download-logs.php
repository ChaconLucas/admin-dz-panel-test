<?php
session_start();
// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../../../PHP/login.php');
    exit();
}

require_once '../../../PHP/conexao.php';

try {
    // Buscar todos os logs
    $result = $conexao->query("SELECT admin_id, admin_nome, acao, data_hora, ip_address FROM admin_logs ORDER BY data_hora DESC");
    
    if (!$result) {
        throw new Exception("Erro ao buscar logs: " . $conexao->error);
    }
    
    // Configurar headers para download CSV
    $filename = 'logs_atividade_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    // Abrir output como arquivo CSV
    $output = fopen('php://output', 'w');
    
    // Adicionar BOM para UTF-8 (para Excel abrir corretamente)
    fputs($output, "\xEF\xBB\xBF");
    
    // Cabeçalhos das colunas
    fputcsv($output, [
        'ID do Log',
        'ID do Admin', 
        'Nome do Administrador',
        'Ação Realizada',
        'Data',
        'Hora',
        'Data/Hora Completa',
        'Endereço IP'
    ], ';');
    
    // Dados dos logs
    $counter = 1;
    while ($row = $result->fetch_assoc()) {
        $data_hora = new DateTime($row['data_hora']);
        
        fputcsv($output, [
            $counter,
            $row['admin_id'] ?? 'N/A',
            $row['admin_nome'],
            $row['acao'],
            $data_hora->format('d/m/Y'),
            $data_hora->format('H:i:s'),
            $data_hora->format('d/m/Y H:i:s'),
            $row['ip_address'] ?? 'N/A'
        ], ';');
        
        $counter++;
    }
    
    fclose($output);
    
} catch (Exception $e) {
    // Em caso de erro, redirecionar de volta
    $_SESSION['error_message'] = 'Erro ao gerar planilha: ' . $e->getMessage();
    header('Location: all-logs.php');
    exit();
}
?>