<?php
header('Content-Type: application/json');
require_once '../../../PHP/conexao.php';

try {
    // Verificar se tabela existe
    $check_table = "SHOW TABLES LIKE 'produto_imagens'";
    $table_exists = mysqli_query($conexao, $check_table);
    
    if (mysqli_num_rows($table_exists) == 0) {
        echo json_encode(['success' => false, 'message' => 'Tabela não encontrada']);
        exit;
    }
    
    // Buscar imagens
    $sql = "SELECT id, nome, nome_arquivo, caminho, tamanho, tipo, created_at FROM produto_imagens ORDER BY created_at DESC";
    $result = mysqli_query($conexao, $sql);
    
    $images = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $images[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'images' => $images,
        'total' => count($images)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao buscar imagens: ' . $e->getMessage()
    ]);
}
?>