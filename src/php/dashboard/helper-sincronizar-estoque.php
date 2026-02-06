<?php
/**
 * Helper para sincronização de estoque de produtos com variações
 */

function sincronizarEstoqueProdutosVariacoes($conexao) {
    // Buscar produtos que têm variações
    $produtos_com_variacoes_query = "SELECT DISTINCT produto_id FROM produto_variacoes";
    $result = mysqli_query($conexao, $produtos_com_variacoes_query);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $produto_id = $row['produto_id'];
            
            // Calcular estoque total das variações deste produto
            $total_estoque_query = "SELECT SUM(estoque) as total_estoque FROM produto_variacoes WHERE produto_id = ? AND ativo = 1";
            $total_stmt = mysqli_prepare($conexao, $total_estoque_query);
            mysqli_stmt_bind_param($total_stmt, 'i', $produto_id);
            mysqli_stmt_execute($total_stmt);
            $total_result = mysqli_stmt_get_result($total_stmt);
            $total_data = mysqli_fetch_assoc($total_result);
            
            $estoque_total = $total_data['total_estoque'] ?: 0;
            
            // Atualizar estoque do produto pai
            $update_query = "UPDATE produtos SET estoque = ? WHERE id = ?";
            $update_stmt = mysqli_prepare($conexao, $update_query);
            mysqli_stmt_bind_param($update_stmt, 'ii', $estoque_total, $produto_id);
            mysqli_stmt_execute($update_stmt);
            
            error_log("🔄 SINCRONIZAÇÃO: Produto ID $produto_id sincronizado com estoque total: $estoque_total");
        }
    }
}

function baixarEstoqueVariacao($conexao, $produto_id, $quantidade, $variacao_id = null) {
    // Se tem variação específica
    if ($variacao_id) {
        $update_var_query = "UPDATE produto_variacoes SET estoque = GREATEST(0, estoque - ?) WHERE id = ? AND produto_id = ?";
        $update_var_stmt = mysqli_prepare($conexao, $update_var_query);
        mysqli_stmt_bind_param($update_var_stmt, 'iii', $quantidade, $variacao_id, $produto_id);
        mysqli_stmt_execute($update_var_stmt);
        
        error_log("🔽 VARIAÇÃO: Produto ID $produto_id, Variação ID $variacao_id - Baixa de $quantidade unidades");
    } else {
        // Baixar proporcional de todas as variações ativas do produto
        $variacoes_query = "SELECT id, estoque FROM produto_variacoes WHERE produto_id = ? AND ativo = 1 AND estoque > 0 ORDER BY estoque DESC";
        $variacoes_stmt = mysqli_prepare($conexao, $variacoes_query);
        mysqli_stmt_bind_param($variacoes_stmt, 'i', $produto_id);
        mysqli_stmt_execute($variacoes_stmt);
        $variacoes_result = mysqli_stmt_get_result($variacoes_stmt);
        
        $quantidade_restante = $quantidade;
        while ($variacao = mysqli_fetch_assoc($variacoes_result) && $quantidade_restante > 0) {
            $estoque_variacao = $variacao['estoque'];
            $baixa_variacao = min($quantidade_restante, $estoque_variacao);
            
            $update_var_query = "UPDATE produto_variacoes SET estoque = estoque - ? WHERE id = ?";
            $update_var_stmt = mysqli_prepare($conexao, $update_var_query);
            mysqli_stmt_bind_param($update_var_stmt, 'ii', $baixa_variacao, $variacao['id']);
            mysqli_stmt_execute($update_var_stmt);
            
            $quantidade_restante -= $baixa_variacao;
            error_log("🔽 VARIAÇÃO AUTO: Variação ID {$variacao['id']} - Baixa de $baixa_variacao unidades");
        }
    }
    
    // Sincronizar estoque do produto pai
    sincronizarEstoqueProdutosVariacoes($conexao);
}

function estornarEstoqueVariacao($conexao, $produto_id, $quantidade, $variacao_id = null) {
    // Se tem variação específica
    if ($variacao_id) {
        $update_var_query = "UPDATE produto_variacoes SET estoque = estoque + ? WHERE id = ? AND produto_id = ?";
        $update_var_stmt = mysqli_prepare($conexao, $update_var_query);
        mysqli_stmt_bind_param($update_var_stmt, 'iii', $quantidade, $variacao_id, $produto_id);
        mysqli_stmt_execute($update_var_stmt);
        
        error_log("🔼 ESTORNO VARIAÇÃO: Produto ID $produto_id, Variação ID $variacao_id - Devolvendo $quantidade unidades");
    } else {
        // Distribuir proporcionalmente entre variações ativas
        $variacoes_query = "SELECT id FROM produto_variacoes WHERE produto_id = ? AND ativo = 1 ORDER BY ordem, id";
        $variacoes_stmt = mysqli_prepare($conexao, $variacoes_query);
        mysqli_stmt_bind_param($variacoes_stmt, 'i', $produto_id);
        mysqli_stmt_execute($variacoes_stmt);
        $variacoes_result = mysqli_stmt_get_result($variacoes_stmt);
        
        $variacoes_ids = [];
        while ($variacao = mysqli_fetch_assoc($variacoes_result)) {
            $variacoes_ids[] = $variacao['id'];
        }
        
        if (!empty($variacoes_ids)) {
            // Distribuir igualmente
            $quantidade_por_variacao = intval($quantidade / count($variacoes_ids));
            $resto = $quantidade % count($variacoes_ids);
            
            foreach ($variacoes_ids as $index => $var_id) {
                $qtd_variacao = $quantidade_por_variacao;
                // Adicionar resto nas primeiras variações
                if ($index < $resto) {
                    $qtd_variacao++;
                }
                
                $update_var_query = "UPDATE produto_variacoes SET estoque = estoque + ? WHERE id = ?";
                $update_var_stmt = mysqli_prepare($conexao, $update_var_query);
                mysqli_stmt_bind_param($update_var_stmt, 'ii', $qtd_variacao, $var_id);
                mysqli_stmt_execute($update_var_stmt);
                
                error_log("🔼 ESTORNO VARIAÇÃO AUTO: Variação ID $var_id - Devolvendo $qtd_variacao unidades");
            }
        }
    }
    
    // Sincronizar estoque do produto pai
    sincronizarEstoqueProdutosVariacoes($conexao);
}

function processarFluxoCompleto($conexao, $pedido_id, $status_config) {
    /**
     * Processa todas as funcionalidades do fluxo de status
     * @param object $conexao - Conexão com banco
     * @param int $pedido_id - ID do pedido
     * @param array $status_config - Configurações do status
     */
    
    if (!$status_config) return;
    
    // Buscar itens do pedido
    $itens_query = "SELECT produto_id, quantidade FROM itens_pedido WHERE pedido_id = ?";
    $itens_stmt = mysqli_prepare($conexao, $itens_query);
    if (!$itens_stmt) return;
    
    mysqli_stmt_bind_param($itens_stmt, 'i', $pedido_id);
    mysqli_stmt_execute($itens_stmt);
    $itens_result = mysqli_stmt_get_result($itens_stmt);
    
    // Processar cada item do pedido
    $itens = [];
    while ($item = mysqli_fetch_assoc($itens_result)) {
        $itens[] = $item;
    }
    
    // === BAIXA DE ESTOQUE ===
    if ($status_config['baixa_estoque'] == 1) {
        foreach ($itens as $item) {
            $produto_id = $item['produto_id'];
            $quantidade = $item['quantidade'];
            
            // Verificar se produto tem variações
            $tem_variacoes_query = "SELECT COUNT(*) as total FROM produto_variacoes WHERE produto_id = ? AND ativo = 1";
            $tem_variacoes_stmt = mysqli_prepare($conexao, $tem_variacoes_query);
            mysqli_stmt_bind_param($tem_variacoes_stmt, 'i', $produto_id);
            mysqli_stmt_execute($tem_variacoes_stmt);
            $tem_variacoes_result = mysqli_stmt_get_result($tem_variacoes_stmt);
            $tem_variacoes_data = mysqli_fetch_assoc($tem_variacoes_result);
            
            if ($tem_variacoes_data['total'] > 0) {
                // Produto com variações
                baixarEstoqueVariacao($conexao, $produto_id, $quantidade);
            } else {
                // Produto simples
                $update_estoque_query = "UPDATE produtos SET estoque = GREATEST(0, estoque - ?) WHERE id = ?";
                $update_estoque_stmt = mysqli_prepare($conexao, $update_estoque_query);
                mysqli_stmt_bind_param($update_estoque_stmt, 'ii', $quantidade, $produto_id);
                mysqli_stmt_execute($update_estoque_stmt);
            }
        }
    }
    
    // === ESTORNO DE ESTOQUE ===
    if ($status_config['estornar_estoque'] == 1) {
        foreach ($itens as $item) {
            $produto_id = $item['produto_id'];
            $quantidade = $item['quantidade'];
            
            // Verificar se produto tem variações
            $tem_variacoes_query = "SELECT COUNT(*) as total FROM produto_variacoes WHERE produto_id = ? AND ativo = 1";
            $tem_variacoes_stmt = mysqli_prepare($conexao, $tem_variacoes_query);
            mysqli_stmt_bind_param($tem_variacoes_stmt, 'i', $produto_id);
            mysqli_stmt_execute($tem_variacoes_stmt);
            $tem_variacoes_result = mysqli_stmt_get_result($tem_variacoes_stmt);
            $tem_variacoes_data = mysqli_fetch_assoc($tem_variacoes_result);
            
            if ($tem_variacoes_data['total'] > 0) {
                // Produto com variações
                estornarEstoqueVariacao($conexao, $produto_id, $quantidade);
            } else {
                // Produto simples
                $update_estoque_query = "UPDATE produtos SET estoque = estoque + ? WHERE id = ?";
                $update_estoque_stmt = mysqli_prepare($conexao, $update_estoque_query);
                mysqli_stmt_bind_param($update_estoque_stmt, 'ii', $quantidade, $produto_id);
                mysqli_stmt_execute($update_estoque_stmt);
            }
        }
    }
}
?>