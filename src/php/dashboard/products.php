<?php
session_start();
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../../../PHP/login.php');
    exit();
}

require_once '../../../PHP/conexao.php';
require_once 'helper-contador.php';

// Incluir sistema de logs automático
require_once '../auto_log.php';

// Função para sincronizar estoque do produto pai com base nas variações
function sincronizarEstoquePai($conexao, $produto_id = null) {
    if ($produto_id) {
        // Sincronizar apenas um produto específico
        $produtos_query = "SELECT id FROM produtos WHERE id = ?";
        $stmt = mysqli_prepare($conexao, $produtos_query);
        mysqli_stmt_bind_param($stmt, "i", $produto_id);
    } else {
        // Sincronizar todos os produtos que têm variações
        $produtos_query = "SELECT DISTINCT produto_id as id FROM produto_variacoes";
        $stmt = mysqli_prepare($conexao, $produtos_query);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($produto = mysqli_fetch_assoc($result)) {
        $id = $produto['id'];
        
        // Calcular estoque total das variações
        $total_query = "SELECT SUM(estoque) as total_estoque FROM produto_variacoes WHERE produto_id = ?";
        $total_stmt = mysqli_prepare($conexao, $total_query);
        mysqli_stmt_bind_param($total_stmt, "i", $id);
        mysqli_stmt_execute($total_stmt);
        $total_result = mysqli_stmt_get_result($total_stmt);
        $total_data = mysqli_fetch_assoc($total_result);
        
        $estoque_total = $total_data['total_estoque'] ?: 0;
        
        // Atualizar produto pai com estoque e status inteligente
        $status = ($estoque_total == 0) ? 'inativo' : 'ativo';
        $update_query = "UPDATE produtos SET estoque = ?, status = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conexao, $update_query);
        mysqli_stmt_bind_param($update_stmt, "isi", $estoque_total, $status, $id);
        mysqli_stmt_execute($update_stmt);
        
        // Log da alteração automática de status se necessário
        if ($estoque_total == 0) {
            $name_query = "SELECT nome FROM produtos WHERE id = ?";
            $name_stmt = mysqli_prepare($conexao, $name_query);
            mysqli_stmt_bind_param($name_stmt, "i", $id);
            mysqli_stmt_execute($name_stmt);
            $name_result = mysqli_stmt_get_result($name_stmt);
            $name_data = mysqli_fetch_assoc($name_result);
            $produto_nome = $name_data['nome'] ?? "ID: $id";
            
            registrar_log_alteracao($conexao, 'status_automatico', $produto_nome, 'status', 'ativo', 'inativo - estoque zerado');
        }
    }
}

// Função para aplicar inteligência de estoque em produto individual
function aplicarInteligenciaEstoque($conexao, $produto_id, $novo_estoque) {
    // Verificar estoque atual
    $current_query = "SELECT status, nome FROM produtos WHERE id = ?";
    $current_stmt = mysqli_prepare($conexao, $current_query);
    mysqli_stmt_bind_param($current_stmt, "i", $produto_id);
    mysqli_stmt_execute($current_stmt);
    $current_result = mysqli_stmt_get_result($current_stmt);
    $current_data = mysqli_fetch_assoc($current_result);
    
    if (!$current_data) return;
    
    $status_atual = $current_data['status'];
    $produto_nome = $current_data['nome'];
    $novo_status = $status_atual;
    
    // Aplicar regras de inteligência
    if ($novo_estoque == 0 && $status_atual == 'ativo') {
        $novo_status = 'inativo';
    } elseif ($novo_estoque > 0 && $status_atual == 'inativo') {
        $novo_status = 'ativo';
    }
    
    // Atualizar status se necessário
    if ($novo_status !== $status_atual) {
        $update_query = "UPDATE produtos SET status = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conexao, $update_query);
        mysqli_stmt_bind_param($update_stmt, "si", $novo_status, $produto_id);
        mysqli_stmt_execute($update_stmt);
        
        // Registrar log da alteração automática
        registrar_log_alteracao($conexao, 'status_automatico', $produto_nome, 'status', $status_atual, "$novo_status - estoque: $novo_estoque");
    }
}

// Restauração manual de imagens removida

// Garantir que $nao_lidas existe
if (!isset($nao_lidas)) {
    $nao_lidas = 0;
    try {
        $result = mysqli_query($conexao, "SELECT COUNT(*) as total FROM mensagens WHERE lida = FALSE AND remetente != 'admin'");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $nao_lidas = $row['total'];
        }
    } catch (Exception $e) {
        $nao_lidas = 0;
    }
}

// AJAX para atualizar produtos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'update_product') {
        $id = (int)$_POST['id'];
        $field = $_POST['field'];
        $value = $_POST['value'];
        
        // BUSCAR DADOS ANTES DA ALTERAÇÃO (CRUCIAL!)
        $dados_antes = buscar_dados_atuais($conexao, 'produtos', $id, ['nome', 'preco', 'preco_promocional', 'estoque']);
        $produto_nome = $dados_antes['nome'] ?? "ID: $id";
        $valor_antigo = $dados_antes[$field] ?? 0;
        
        $allowed_fields = ['preco', 'preco_promocional', 'estoque'];
        if (!in_array($field, $allowed_fields)) {
            echo json_encode(['success' => false, 'message' => 'Campo não permitido']);
            exit;
        }
        
        // Processar valores
        if ($field === 'estoque') {
            $value = max(0, (int)$value);
        } else {
            $value = (float)str_replace(',', '.', $value);
            $value = max(0, $value);
        }
        
        // Se preço promocional for 0, definir como NULL para remover
        if ($field === 'preco_promocional' && $value == 0) {
            $value = NULL;
        }
        
        $sql = "UPDATE produtos SET `$field` = ? WHERE id = ?";
        $stmt = mysqli_prepare($conexao, $sql);
        
        if ($field === 'estoque') {
            mysqli_stmt_bind_param($stmt, "ii", $value, $id);
        } elseif ($field === 'preco_promocional' && $value === NULL) {
            // Para remover promoção
            $sql = "UPDATE produtos SET `$field` = NULL WHERE id = ?";
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, "i", $id);
        } else {
            mysqli_stmt_bind_param($stmt, "di", $value, $id);
        }
        
        $success = mysqli_stmt_execute($stmt);
        
        // Aplicar inteligência de estoque se o campo alterado foi estoque
        if ($success && $field === 'estoque') {
            aplicarInteligenciaEstoque($conexao, $id, $value);
        }
        
        // AGORA SIM: Gerar log com valores corretos (antes vs depois)
        if ($success) {
            // Registrar log com comparação correta
            if ($field === 'estoque') {
                registrar_log_alteracao($conexao, 'estoque', $produto_nome, $field, $valor_antigo, $value);
            } elseif ($field === 'preco') {
                registrar_log_alteracao($conexao, 'preco', $produto_nome, $field, $valor_antigo, $value);
            } elseif ($field === 'preco_promocional') {
                registrar_log_alteracao($conexao, 'preco_promocional', $produto_nome, $field, $valor_antigo, $value);
            }
        }
        
        echo json_encode(['success' => $success]);
        exit;
    }
    
    if ($_POST['action'] === 'update_variation_field') {
        // Log de depuração
        error_log("Update variation field - ID: " . $_POST['variation_id'] . ", Field: " . $_POST['field'] . ", Value: " . $_POST['value']);
        
        $variation_id = (int)$_POST['variation_id'];
        $field = $_POST['field'];
        $value = $_POST['value'];
        
        $allowed_fields = ['preco', 'preco_promocional', 'estoque'];
        if (!in_array($field, $allowed_fields)) {
            echo json_encode(['success' => false, 'message' => 'Campo não permitido']);
            exit;
        }
        
        // Processar valores
        if ($field === 'estoque') {
            $value = max(0, (int)$value);
        } elseif ($field === 'preco' || $field === 'preco_promocional') {
            if ($value === '' || $value === null) {
                $value = null; // NULL para herdar do produto pai
            } else {
                $value = (float)str_replace(',', '.', $value);
                if ($value < 0) {
                    echo json_encode(['success' => false, 'message' => 'Preço não pode ser negativo']);
                    exit;
                }
                // Se valor for 0, converter para NULL para herdar do pai
                if ($value == 0) {
                    $value = null;
                }
            }
        }
        
        $sql = "UPDATE produto_variacoes SET `$field` = ? WHERE id = ?";
        $stmt = mysqli_prepare($conexao, $sql);
        
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Erro na preparação da consulta: ' . mysqli_error($conexao)]);
            exit;
        }
        
        if ($field === 'estoque') {
            mysqli_stmt_bind_param($stmt, "ii", $value, $variation_id);
        } else {
            // Para preços, usar 'di' mesmo com NULL (MySQL aceita)
            mysqli_stmt_bind_param($stmt, "di", $value, $variation_id);
        }
        
        $success = mysqli_stmt_execute($stmt);
        
        if (!$success) {
            echo json_encode(['success' => false, 'message' => 'Erro ao executar query: ' . mysqli_stmt_error($stmt)]);
            exit;
        }
        
        // Se atualizou o estoque de uma variação, atualizar estoque total do produto pai
        if ($success && $field === 'estoque') {
            // Buscar o produto_id da variação
            $product_query = "SELECT produto_id FROM produto_variacoes WHERE id = ?";
            $product_stmt = mysqli_prepare($conexao, $product_query);
            mysqli_stmt_bind_param($product_stmt, "i", $variation_id);
            mysqli_stmt_execute($product_stmt);
            $product_result = mysqli_stmt_get_result($product_stmt);
            $product_data = mysqli_fetch_assoc($product_result);
            
            if ($product_data) {
                $produto_id = $product_data['produto_id'];
                
                // Calcular o estoque total de todas as variações do produto
                $total_query = "SELECT SUM(estoque) as total_estoque FROM produto_variacoes WHERE produto_id = ?";
                $total_stmt = mysqli_prepare($conexao, $total_query);
                mysqli_stmt_bind_param($total_stmt, "i", $produto_id);
                mysqli_stmt_execute($total_stmt);
                $total_result = mysqli_stmt_get_result($total_stmt);
                $total_data = mysqli_fetch_assoc($total_result);
                
                $estoque_total = $total_data['total_estoque'] ?: 0;
                
                // Atualizar o estoque do produto pai
                $update_parent_query = "UPDATE produtos SET estoque = ? WHERE id = ?";
                $update_parent_stmt = mysqli_prepare($conexao, $update_parent_query);
                mysqli_stmt_bind_param($update_parent_stmt, "ii", $estoque_total, $produto_id);
                mysqli_stmt_execute($update_parent_stmt);
            }
        }
        
        $response = ['success' => true, 'new_value' => $value];
        
        // Se o preço foi limpo (NULL), buscar preço do produto pai
        if ($success && $field === 'preco' && ($value === null || $value === '')) {
            $parent_query = "SELECT preco FROM produtos WHERE id = (SELECT produto_id FROM produto_variacoes WHERE id = ?)";
            $parent_stmt = mysqli_prepare($conexao, $parent_query);
            mysqli_stmt_bind_param($parent_stmt, "i", $variation_id);
            mysqli_stmt_execute($parent_stmt);
            $parent_result = mysqli_stmt_get_result($parent_stmt);
            $parent = mysqli_fetch_assoc($parent_result);
            
            if ($parent) {
                $response['parent_price'] = number_format($parent['preco'], 2, ',', '.');
            }
        }
        
        echo json_encode($response);
        exit;
    }

    if ($_POST['action'] === 'delete_product') {
        header('Content-Type: application/json');
        
        // Debug temporário
        error_log('DELETE PHP: Received POST data: ' . print_r($_POST, true));
        
        $id = (int)$_POST['id'];
        
        error_log('DELETE PHP: Original id: ' . var_export($_POST['id'], true) . ', Converted: ' . $id);
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => "ID inválido - Recebido: '" . $_POST['id'] . "', Convertido: $id"]);
            exit;
        }
        
        try {
            // Excluir variações primeiro (se existirem)
            $sql = "DELETE FROM produto_variacoes WHERE produto_id = ?";
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            
            // Excluir produto
            $sql = "DELETE FROM produtos WHERE id = ?";
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, "i", $id);
            $success = mysqli_stmt_execute($stmt);
            
            if ($success && mysqli_affected_rows($conexao) > 0) {
                echo json_encode(['success' => true, 'message' => 'Produto excluído com sucesso']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Produto não encontrado ou não foi possível excluir']);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
        }
        
        exit;
    }
    
    // ===== AÇÕES EM MASSA =====
    if ($_POST['action'] === 'bulk_stock_update') {
        $product_ids = $_POST['product_ids'] ?? [];
        $operation = $_POST['operation']; // 'add', 'subtract', 'set'
        $value = (int)$_POST['value'];
        
        if (empty($product_ids) || $value < 0) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            exit;
        }
        
        $success_count = 0;
        $total_count = count($product_ids);
        
        foreach ($product_ids as $id) {
            $id = (int)$id;
            
            // Buscar produto e verificar se tem variações
            $product_query = "SELECT estoque, nome FROM produtos WHERE id = ?";
            $product_stmt = mysqli_prepare($conexao, $product_query);
            mysqli_stmt_bind_param($product_stmt, "i", $id);
            mysqli_stmt_execute($product_stmt);
            $product_result = mysqli_stmt_get_result($product_stmt);
            $product_data = mysqli_fetch_assoc($product_result);
            
            if (!$product_data) continue;
            
            $product_name = $product_data['nome'];
            
            // Verificar se produto tem variações
            $variations_query = "SELECT id, estoque FROM produto_variacoes WHERE produto_id = ?";
            $variations_stmt = mysqli_prepare($conexao, $variations_query);
            mysqli_stmt_bind_param($variations_stmt, "i", $id);
            mysqli_stmt_execute($variations_stmt);
            $variations_result = mysqli_stmt_get_result($variations_stmt);
            $variations = mysqli_fetch_all($variations_result, MYSQLI_ASSOC);
            
            if (!empty($variations)) {
                // PRODUTO COM VARIAÇÕES: Aplicar alteração em todas as variações
                $variation_success = 0;
                
                foreach ($variations as $variation) {
                    $current_var_stock = (int)$variation['estoque'];
                    $new_var_stock = 0;
                    
                    switch ($operation) {
                        case 'add':
                            $new_var_stock = $current_var_stock + $value;
                            break;
                        case 'subtract':
                            $new_var_stock = max(0, $current_var_stock - $value);
                            break;
                        case 'set':
                            $new_var_stock = $value;
                            break;
                    }
                    
                    // Atualizar variação
                    $update_var_query = "UPDATE produto_variacoes SET estoque = ? WHERE id = ?";
                    $update_var_stmt = mysqli_prepare($conexao, $update_var_query);
                    mysqli_stmt_bind_param($update_var_stmt, "ii", $new_var_stock, $variation['id']);
                    
                    if (mysqli_stmt_execute($update_var_stmt)) {
                        $variation_success++;
                    }
                }
                
                if ($variation_success > 0) {
                    // Recalcular estoque do produto pai
                    sincronizarEstoquePai($conexao, $id);
                    $success_count++;
                    registrar_log_alteracao($conexao, 'estoque_massa_variacoes', $product_name, 'estoque', 'variações', "$operation $value em $variation_success variações");
                }
                
            } else {
                // PRODUTO SIMPLES (SEM VARIAÇÕES)
                $current_stock = (int)$product_data['estoque'];
                $new_stock = 0;
                
                switch ($operation) {
                    case 'add':
                        $new_stock = $current_stock + $value;
                        break;
                    case 'subtract':
                        $new_stock = max(0, $current_stock - $value);
                        break;
                    case 'set':
                        $new_stock = $value;
                        break;
                }
                
                // Atualizar produto simples
                $update_query = "UPDATE produtos SET estoque = ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conexao, $update_query);
                mysqli_stmt_bind_param($update_stmt, "ii", $new_stock, $id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    $success_count++;
                    
                    // Aplicar inteligência de estoque
                    aplicarInteligenciaEstoque($conexao, $id, $new_stock);
                    
                    // Registrar log
                    registrar_log_alteracao($conexao, 'estoque_massa', $product_name, 'estoque', $current_stock, $new_stock);
                }
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => "Estoque atualizado em $success_count de $total_count produtos"
        ]);
        exit;
    }
    
    if ($_POST['action'] === 'bulk_delete') {
        $product_ids = $_POST['product_ids'] ?? [];
        
        if (empty($product_ids)) {
            echo json_encode(['success' => false, 'message' => 'Nenhum produto selecionado']);
            exit;
        }
        
        $success_count = 0;
        $total_count = count($product_ids);
        
        foreach ($product_ids as $id) {
            $id = (int)$id;
            
            // Buscar nome do produto para log
            $name_query = "SELECT nome FROM produtos WHERE id = ?";
            $name_stmt = mysqli_prepare($conexao, $name_query);
            mysqli_stmt_bind_param($name_stmt, "i", $id);
            mysqli_stmt_execute($name_stmt);
            $name_result = mysqli_stmt_get_result($name_stmt);
            $name_data = mysqli_fetch_assoc($name_result);
            $product_name = $name_data['nome'] ?? "ID: $id";
            
            // Excluir variações
            $delete_variations = "DELETE FROM produto_variacoes WHERE produto_id = ?";
            $delete_var_stmt = mysqli_prepare($conexao, $delete_variations);
            mysqli_stmt_bind_param($delete_var_stmt, "i", $id);
            mysqli_stmt_execute($delete_var_stmt);
            
            // Excluir produto
            $delete_product = "DELETE FROM produtos WHERE id = ?";
            $delete_stmt = mysqli_prepare($conexao, $delete_product);
            mysqli_stmt_bind_param($delete_stmt, "i", $id);
            
            if (mysqli_stmt_execute($delete_stmt) && mysqli_affected_rows($conexao) > 0) {
                $success_count++;
                registrar_log_alteracao($conexao, 'exclusao_massa', $product_name, 'produto', 'ativo', 'excluído');
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => "$success_count produtos excluídos com sucesso"
        ]);
        exit;
    }
    
    if ($_POST['action'] === 'export_selected') {
        $product_ids = $_POST['product_ids'] ?? [];
        
        if (empty($product_ids)) {
            echo json_encode(['success' => false, 'message' => 'Nenhum produto selecionado']);
            exit;
        }
        
        // Criar consulta para produtos selecionados com variações
        $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
        $export_query = "SELECT 
            p.id, p.sku, p.nome, p.categoria, p.preco, p.preco_promocional, 
            p.estoque, p.status, p.descricao, p.imagem_principal
            FROM produtos p 
            WHERE p.id IN ($placeholders)
            ORDER BY p.nome";
        
        $export_stmt = mysqli_prepare($conexao, $export_query);
        mysqli_stmt_bind_param($export_stmt, str_repeat('i', count($product_ids)), ...$product_ids);
        mysqli_stmt_execute($export_stmt);
        $export_result = mysqli_stmt_get_result($export_stmt);
        
        $filename = 'produtos_selecionados_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Escrever BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Cabeçalho exato do modelo
        fputcsv($output, ['SKU', 'Nome', 'Categoria', 'Preço', 'Preço Promocional', 'Estoque', 'Status', 'Descrição', 'URL Imagem', 'Variações']);
        
        while ($row = mysqli_fetch_assoc($export_result)) {
            // Buscar variações do produto
            $var_query = "SELECT tipo, valor, estoque FROM produto_variacoes WHERE produto_id = ? ORDER BY tipo, valor";
            $var_stmt = mysqli_prepare($conexao, $var_query);
            mysqli_stmt_bind_param($var_stmt, "i", $row['id']);
            mysqli_stmt_execute($var_stmt);
            $var_result = mysqli_stmt_get_result($var_stmt);
            
            $variacoes_array = [];
            while ($var = mysqli_fetch_assoc($var_result)) {
                $variacoes_array[] = $var['tipo'] . ':' . $var['valor'] . '=' . $var['estoque'];
            }
            $variacoes_str = implode(';', $variacoes_array);
            
            // URL da imagem
            $image_url = '';
            if (!empty($row['imagem_principal'])) {
                $image_url = 'https://' . $_SERVER['HTTP_HOST'] . '/admin-teste/assets/images/produtos/' . $row['imagem_principal'];
            }
            
            // Formatar preços
            $preco_formatted = number_format($row['preco'], 2, ',', '');
            $preco_promocional_formatted = '';
            if (!empty($row['preco_promocional']) && $row['preco_promocional'] > 0) {
                $preco_promocional_formatted = number_format($row['preco_promocional'], 2, ',', '');
            }
            
            fputcsv($output, [
                $row['sku'],
                $row['nome'],
                $row['categoria'],
                $preco_formatted,
                $preco_promocional_formatted,
                $row['estoque'],
                $row['status'],
                $row['descricao'],
                $image_url,
                $variacoes_str
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    if ($_POST['action'] === 'export_all') {
        $export_query = "SELECT 
            p.id, p.sku, p.nome, p.categoria, p.preco, p.preco_promocional, 
            p.estoque, p.status, p.descricao, p.imagem_principal
            FROM produtos p 
            ORDER BY p.nome";
        
        $export_result = mysqli_query($conexao, $export_query);
        
        $filename = 'todos_produtos_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Escrever BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Cabeçalho exato do modelo
        fputcsv($output, ['SKU', 'Nome', 'Categoria', 'Preço', 'Preço Promocional', 'Estoque', 'Status', 'Descrição', 'URL Imagem', 'Variações']);
        
        while ($row = mysqli_fetch_assoc($export_result)) {
            // Buscar variações do produto
            $var_query = "SELECT tipo, valor, estoque FROM produto_variacoes WHERE produto_id = ? ORDER BY tipo, valor";
            $var_stmt = mysqli_prepare($conexao, $var_query);
            mysqli_stmt_bind_param($var_stmt, "i", $row['id']);
            mysqli_stmt_execute($var_stmt);
            $var_result = mysqli_stmt_get_result($var_stmt);
            
            $variacoes_array = [];
            while ($var = mysqli_fetch_assoc($var_result)) {
                $variacoes_array[] = $var['tipo'] . ':' . $var['valor'] . '=' . $var['estoque'];
            }
            $variacoes_str = implode(';', $variacoes_array);
            
            // URL da imagem
            $image_url = '';
            if (!empty($row['imagem_principal'])) {
                $image_url = 'https://' . $_SERVER['HTTP_HOST'] . '/admin-teste/assets/images/produtos/' . $row['imagem_principal'];
            }
            
            // Formatar preços
            $preco_formatted = number_format($row['preco'], 2, ',', '');
            $preco_promocional_formatted = '';
            if (!empty($row['preco_promocional']) && $row['preco_promocional'] > 0) {
                $preco_promocional_formatted = number_format($row['preco_promocional'], 2, ',', '');
            }
            
            fputcsv($output, [
                $row['sku'],
                $row['nome'],
                $row['categoria'],
                $preco_formatted,
                $preco_promocional_formatted,
                $row['estoque'],
                $row['status'],
                $row['descricao'],
                $image_url,
                $variacoes_str
            ]);
        }
        
        exit;
    }
    
    if ($_POST['action'] === 'import_products') {
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Erro no upload do arquivo']);
            exit;
        }
        
        $file_path = $_FILES['import_file']['tmp_name'];
        $file_extension = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, ['csv', 'xlsx', 'xls'])) {
            echo json_encode(['success' => false, 'message' => 'Formato não suportado. Use CSV ou Excel']);
            exit;
        }
        
        $imported = 0;
        $updated = 0;
        $errors = [];
        
        // Processar CSV com modelo específico
        if ($file_extension === 'csv') {
            if (($handle = fopen($file_path, "r")) !== FALSE) {
                // Definir encoding para UTF-8
                $first_line = fgets($handle);
                if (substr($first_line, 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf)) {
                    // Remove BOM UTF-8
                }
                rewind($handle);
                
                // Pular cabeçalho
                $header = fgetcsv($handle, 1000, ",");
                
                $linha_numero = 2; // Começar da linha 2 (após cabeçalho)
                
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    // Verificar se linha tem dados suficientes
                    if (count($data) < 3) {
                        $linha_numero++;
                        continue; // Pular linhas vazias
                    }
                    
                    // Mapear colunas exatas: SKU, Nome, Categoria, Preço, Preço Promocional, Estoque, Status, Descrição, URL Imagem, Variações
                    $sku = trim($data[0] ?? '');
                    $nome = trim($data[1] ?? '');
                    $categoria = trim($data[2] ?? '');
                    $preco_raw = trim($data[3] ?? '0');
                    $preco_promocional_raw = trim($data[4] ?? '');
                    $estoque = (int)($data[5] ?? 0);
                    $status = strtolower(trim($data[6] ?? 'ativo'));
                    $descricao = trim($data[7] ?? '');
                    $image_url = trim($data[8] ?? '');
                    $variacoes_str = trim($data[9] ?? '');
                    
                    // Limpar e converter preços
                    $preco = (float)str_replace([',', 'R$', ' '], ['.', '', ''], $preco_raw);
                    $preco_promocional = null;
                    if (!empty($preco_promocional_raw)) {
                        $preco_promocional = (float)str_replace([',', 'R$', ' '], ['.', '', ''], $preco_promocional_raw);
                        if ($preco_promocional <= 0) $preco_promocional = null;
                    }
                    
                    // Validações obrigatórias
                    if (empty($sku)) {
                        $errors[] = "Linha $linha_numero: SKU não pode estar vazio";
                        $linha_numero++;
                        continue;
                    }
                    
                    if (empty($nome)) {
                        $errors[] = "Linha $linha_numero: Nome não pode estar vazio";
                        $linha_numero++;
                        continue;
                    }
                    
                    if ($preco <= 0) {
                        $errors[] = "Linha $linha_numero: Preço deve ser maior que zero";
                        $linha_numero++;
                        continue;
                    }
                    
                    // Normalizar status
                    if (!in_array($status, ['ativo', 'inativo'])) {
                        $status = 'ativo';
                    }
                    
                    try {
                        mysqli_begin_transaction($conexao);
                        
                        // Verificar se produto existe pelo SKU
                        $check_query = "SELECT id FROM produtos WHERE sku = ?";
                        $check_stmt = mysqli_prepare($conexao, $check_query);
                        mysqli_stmt_bind_param($check_stmt, "s", $sku);
                        mysqli_stmt_execute($check_stmt);
                        $check_result = mysqli_stmt_get_result($check_stmt);
                        $existing = mysqli_fetch_assoc($check_result);
                        
                        // Processar imagem
                        $imagem_nome = '';
                        if (!empty($image_url)) {
                            $imagem_nome = processar_imagem_inteligente($image_url, $sku);
                        }
                        
                        if ($existing) {
                            // ATUALIZAR produto existente (não criar novo)
                            $product_id = $existing['id'];
                            
                            // Limpar variações antigas se há novas variações
                            if (!empty($variacoes_str)) {
                                $delete_vars = "DELETE FROM produto_variacoes WHERE produto_id = ?";
                                $delete_vars_stmt = mysqli_prepare($conexao, $delete_vars);
                                mysqli_stmt_bind_param($delete_vars_stmt, "i", $product_id);
                                mysqli_stmt_execute($delete_vars_stmt);
                            }
                            
                            $update_query = "UPDATE produtos SET 
                                nome = ?, categoria = ?, preco = ?, preco_promocional = ?, 
                                estoque = ?, status = ?, descricao = ?";
                            
                            $params = [$nome, $categoria, $preco, $preco_promocional, $estoque, $status, $descricao];
                            $types = "sssdiss";
                            
                            if (!empty($imagem_nome)) {
                                $update_query .= ", imagem_principal = ?";
                                $params[] = $imagem_nome;
                                $types .= "s";
                            }
                            
                            $update_query .= " WHERE id = ?";
                            $params[] = $product_id;
                            $types .= "i";
                            
                            $update_stmt = mysqli_prepare($conexao, $update_query);
                            mysqli_stmt_bind_param($update_stmt, $types, ...$params);
                            
                            if (mysqli_stmt_execute($update_stmt)) {
                                $updated++;
                                
                                // Processar variações se fornecidas
                                if (!empty($variacoes_str)) {
                                    processar_variacoes_importacao_melhorada($conexao, $product_id, $variacoes_str);
                                    // Recalcular estoque do produto pai
                                    sincronizarEstoquePai($conexao, $product_id);
                                } else {
                                    // Aplicar inteligência de estoque em produto simples
                                    aplicarInteligenciaEstoque($conexao, $product_id, $estoque);
                                }
                                
                                registrar_log_alteracao($conexao, 'importacao_atualizacao', $nome, 'produto', 'existente', 'atualizado via CSV');
                            }
                            
                        } else {
                            // CRIAR novo produto
                            $insert_query = "INSERT INTO produtos (
                                sku, nome, categoria, preco, preco_promocional, estoque, 
                                status, descricao, imagem_principal, created_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                            
                            $insert_stmt = mysqli_prepare($conexao, $insert_query);
                            mysqli_stmt_bind_param($insert_stmt, "sssdissss", 
                                $sku, $nome, $categoria, $preco, $preco_promocional, 
                                $estoque, $status, $descricao, $imagem_nome);
                            
                            if (mysqli_stmt_execute($insert_stmt)) {
                                $imported++;
                                $product_id = mysqli_insert_id($conexao);
                                
                                // Processar variações se fornecidas
                                if (!empty($variacoes_str)) {
                                    processar_variacoes_importacao_melhorada($conexao, $product_id, $variacoes_str);
                                    // Recalcular estoque do produto pai
                                    sincronizarEstoquePai($conexao, $product_id);
                                } else {
                                    // Aplicar inteligência de estoque em produto simples
                                    aplicarInteligenciaEstoque($conexao, $product_id, $estoque);
                                }
                                
                                registrar_log_alteracao($conexao, 'importacao_criacao', $nome, 'produto', 'novo', 'criado via CSV');
                            }
                        }
                        
                        mysqli_commit($conexao);
                        
                    } catch (Exception $e) {
                        mysqli_rollback($conexao);
                        $errors[] = "Linha $linha_numero: Erro ao processar - " . $e->getMessage();
                    }
                    
                    $linha_numero++;
                }
                fclose($handle);
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => "Importação concluída: $imported novos, $updated atualizados",
            'errors' => $errors
        ]);
        exit;
    }
}

// Função auxiliar para processar imagem (inteligente - URL externa vs caminho interno)
function processar_imagem_inteligente($image_path, $sku) {
    // Se já é um caminho interno (não contém http), apenas retornar
    if (!preg_match('/^https?:\/\//', $image_path)) {
        // Verificar se arquivo existe no diretório de uploads
        $uploads_dir = '../../../assets/images/produtos/';
        if (file_exists($uploads_dir . basename($image_path))) {
            return basename($image_path);
        }
        return ''; // Arquivo não existe
    }
    
    // É uma URL externa, baixar
    return baixar_imagem_url($image_path, $sku);
}

// Função auxiliar para baixar imagem de URL
function baixar_imagem_url($url, $sku) {
    $uploads_dir = '../../../assets/images/produtos/';
    
    if (!is_dir($uploads_dir)) {
        mkdir($uploads_dir, 0777, true);
    }
    
    // Tentar baixar imagem com timeout e headers
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);
    
    $image_data = @file_get_contents($url, false, $context);
    if ($image_data === false) return '';
    
    // Detectar extensão da URL ou do cabeçalho
    $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        // Tentar detectar pelo cabeçalho da imagem
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->buffer($image_data);
        
        switch ($mime_type) {
            case 'image/jpeg': $extension = 'jpg'; break;
            case 'image/png': $extension = 'png'; break;
            case 'image/gif': $extension = 'gif'; break;
            case 'image/webp': $extension = 'webp'; break;
            default: $extension = 'jpg'; // fallback
        }
    }
    
    // Sanitizar SKU para nome de arquivo
    $clean_sku = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sku);
    $filename = $clean_sku . '_' . time() . '.' . $extension;
    $filepath = $uploads_dir . $filename;
    
    if (file_put_contents($filepath, $image_data) !== false) {
        return $filename;
    }
    
    return '';
}

// Função melhorada para processar variações na importação
function processar_variacoes_importacao_melhorada($conexao, $produto_id, $variacoes_str) {
    if (empty($variacoes_str)) return;
    
    // Formato esperado: "cor:Vermelho Paixão=25;tamanho:M=15;cor:Azul Céu=30"
    $variacoes = array_filter(explode(';', $variacoes_str));
    
    foreach ($variacoes as $variacao) {
        $variacao = trim($variacao);
        if (empty($variacao) || strpos($variacao, '=') === false) continue;
        
        list($tipo_valor, $estoque_str) = explode('=', $variacao, 2);
        if (strpos($tipo_valor, ':') === false) continue;
        
        list($tipo, $valor) = explode(':', $tipo_valor, 2);
        $tipo = trim($tipo);
        $valor = trim($valor);
        $estoque = max(0, (int)trim($estoque_str));
        
        if (empty($tipo) || empty($valor)) continue;
        
        // Verificar se variação já existe
        $check_var = "SELECT id FROM produto_variacoes WHERE produto_id = ? AND tipo = ? AND valor = ?";
        $check_var_stmt = mysqli_prepare($conexao, $check_var);
        mysqli_stmt_bind_param($check_var_stmt, "iss", $produto_id, $tipo, $valor);
        mysqli_stmt_execute($check_var_stmt);
        $var_result = mysqli_stmt_get_result($check_var_stmt);
        
        if (mysqli_fetch_assoc($var_result)) {
            // Atualizar variação existente
            $update_var = "UPDATE produto_variacoes SET estoque = ? WHERE produto_id = ? AND tipo = ? AND valor = ?";
            $update_var_stmt = mysqli_prepare($conexao, $update_var);
            mysqli_stmt_bind_param($update_var_stmt, "iiss", $estoque, $produto_id, $tipo, $valor);
            mysqli_stmt_execute($update_var_stmt);
        } else {
            // Criar nova variação
            $insert_var = "INSERT INTO produto_variacoes (produto_id, tipo, valor, estoque, preco, preco_promocional) VALUES (?, ?, ?, ?, NULL, NULL)";
            $insert_var_stmt = mysqli_prepare($conexao, $insert_var);
            mysqli_stmt_bind_param($insert_var_stmt, "issi", $produto_id, $tipo, $valor, $estoque);
            mysqli_stmt_execute($insert_var_stmt);
        }
    }
}

// Buscar produtos com filtros
$search = $_GET['search'] ?? '';
$categoria = $_GET['categoria'] ?? '';
$status = $_GET['status'] ?? '';
$estoque = $_GET['estoque'] ?? '';

// Sincronizar estoques de produtos com variações
sincronizarEstoquePai($conexao);

// Estatísticas de estoque para o widget
$stats_disponivel = mysqli_fetch_assoc(mysqli_query($conexao, "SELECT COUNT(*) as total FROM produtos WHERE estoque > 10"))['total'] ?? 0;
$stats_baixo = mysqli_fetch_assoc(mysqli_query($conexao, "SELECT COUNT(*) as total FROM produtos WHERE estoque > 0 AND estoque <= 10"))['total'] ?? 0;
$stats_esgotado = mysqli_fetch_assoc(mysqli_query($conexao, "SELECT COUNT(*) as total FROM produtos WHERE estoque = 0"))['total'] ?? 0;

// Contar produtos com baixo estoque para o alerta (≤ 10)
$baixo_estoque_query = "SELECT COUNT(*) as total, GROUP_CONCAT(nome SEPARATOR ', ') as nomes 
                        FROM produtos 
                        WHERE estoque > 0 AND estoque <= 10 
                        ORDER BY estoque ASC 
                        LIMIT 5";
$baixo_estoque_result = mysqli_query($conexao, $baixo_estoque_query);
$baixo_estoque_data = mysqli_fetch_assoc($baixo_estoque_result);
$produtos_baixo_estoque = $baixo_estoque_data['total'] ?? 0;
$nomes_baixo_estoque = $baixo_estoque_data['nomes'] ?? '';

$sql = "SELECT p.*, c.nome as categoria_nome FROM produtos p LEFT JOIN categorias c ON p.categoria_id = c.id";
$conditions = [];
$params = [];
$types = '';

// Filtro de busca (nome, SKU, categoria ou subcategoria)
if ($search) {
    $conditions[] = "(p.nome LIKE ? OR p.sku LIKE ? OR p.categoria LIKE ? OR p.subcategoria LIKE ? OR c.nome LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "sssss";
}

// Filtro de categoria
if ($categoria) {
    $conditions[] = "p.categoria_id = ?";
    $params[] = (int)$categoria;
    $types .= "i";
}

// Filtro de status
if ($status) {
    if ($status === 'ativo') {
        $conditions[] = "p.ativo = 1";
    } elseif ($status === 'inativo') {
        $conditions[] = "p.ativo = 0";
    }
}

// Filtro de estoque (apenas produto pai)
if ($estoque) {
    if ($estoque === 'disponivel') {
        $conditions[] = "p.estoque > 10";
    } elseif ($estoque === 'baixo') {
        $conditions[] = "(p.estoque > 0 AND p.estoque <= 10)";
    } elseif ($estoque === 'esgotado') {
        $conditions[] = "p.estoque = 0";
    }
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY p.id DESC";

// Debug temporário - mostrar na página
if (isset($_GET['debug'])) {
    echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;'>";
    echo "<h3>DEBUG FILTROS</h3>";
    echo "<p><strong>Parâmetros GET:</strong> " . htmlspecialchars(print_r($_GET, true)) . "</p>";
    echo "<p><strong>Filtro estoque:</strong> '$estoque'</p>";
    echo "<p><strong>Condições:</strong> " . htmlspecialchars(print_r($conditions, true)) . "</p>";
    echo "<p><strong>SQL final:</strong> " . htmlspecialchars($sql) . "</p>";
    echo "<p><strong>Parâmetros:</strong> " . htmlspecialchars(print_r($params, true)) . "</p>";
    echo "</div>";
}

// Executar query usando prepared statement se há parâmetros
if (!empty($params)) {
    $stmt = mysqli_prepare($conexao, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $products = mysqli_stmt_get_result($stmt);
    } else {
        $products = false;
    }
} else {
    // Executar query direta se não há parâmetros
    $products = mysqli_query($conexao, $sql);
}

if (!$products) {
    echo "<div style='color: red; background: #ffe6e6; padding: 10px;'>Erro SQL: " . mysqli_error($conexao) . "</div>";
}

if (isset($_GET['debug'])) {
    echo "<div style='background: #e6ffe6; padding: 10px; margin: 10px; border: 1px solid #ccc;'>";
    echo "<p><strong>Número de resultados:</strong> " . mysqli_num_rows($products) . "</p>";
    echo "</div>";
}

// Buscar categorias para filtros
$categorias_sql = "SELECT id, nome FROM categorias WHERE ativo = 1 ORDER BY nome";
$categorias_result = mysqli_query($conexao, $categorias_sql);
$categorias = [];
while ($cat = mysqli_fetch_assoc($categorias_result)) {
    $categorias[] = $cat;
}

$total_products = mysqli_num_rows($products);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos - D&Z Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp" rel="stylesheet">
    <link rel="stylesheet" href="../../css/dashboard.css">
    
    <!-- Aplicar tema imediatamente -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('darkTheme');
            if (savedTheme === 'true' || savedTheme === null) {
                document.body.classList.add('dark-theme-variables');
            } else {
                document.body.classList.remove('dark-theme-variables');
            }
        })();
    </script>
    
    <style>
        /* Forçar layout de grade correto */
        .container {
            grid-template-columns: 14rem auto 18rem !important;
        }
        
        .right .profile .info p {
            margin-bottom: 0.2rem !important;
        }
        
        .right .profile .profile-photo {
            width: 2.8rem !important;
            height: 2.8rem !important;
        }
        
        .right .profile .profile-photo img {
            width: 100% !important;
            height: 100% !important;
            border-radius: 50% !important;
            object-fit: cover !important;
        }
        
        .right .top button {
            display: none !important;
        }
        
        main {
            padding: 2rem 0;
        }
        
        /* Lista de produtos */
        .products-header {
            margin-bottom: 2rem;
        }
        
        .products-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }


        
        .add-product-btn {
            background: var(--color-primary);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .add-product-btn:hover {
            background: var(--color-primary-variant);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 0, 212, 0.3);
        }
        
        /* Barra de filtros */
        .filters-bar {
            background: var(--color-white);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: var(--box-shadow);
            margin-bottom: 1.5rem;
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: 2fr 180px 140px 140px auto;
            gap: 1rem;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        
        .filter-group label {
            font-size: 11px;
            color: var(--color-dark-variant);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 0.8rem;
            border: 1px solid var(--border-color) !important;
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-input) !important;
            color: var(--text-primary) !important;
            transition: all 0.2s ease;
            height: 44px;
            box-sizing: border-box;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--accent-pink) !important;
            box-shadow: 0 0 0 3px rgba(255, 20, 147, 0.2) !important;
        }
        
        .search-input {
            width: 100%;
        }
        
        .search-help {
            display: block;
            font-size: 0.75rem;
            color: #666;
            margin-top: 0.25rem;
            font-style: italic;
        }
        
        .category-select,
        .status-select,
        .stock-select {
            width: 100%;
        }
        
        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: end;
            padding-bottom: 0.8rem;
        }
        
        .btn-filter {
            padding: 0.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 18px;
            transition: opacity 0.2s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background: transparent;
        }
        
        .btn-search {
            color: #666;
        }
        
        .btn-clear {
            color: #666;
        }
        
        .btn-filter:hover {
            opacity: 0.7;
        }
        
        .btn-search:hover {
            color: var(--color-primary);
        }
        
        .btn-clear:hover {
            color: #d32f2f;
        }
        
        /* Alerta de baixo estoque */
        .low-stock-alert {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3);
            border-left: 4px solid #e65100;
        }
        
        .low-stock-alert .material-symbols-sharp {
            font-size: 24px;
            animation: pulse 2s infinite;
        }
        
        .low-stock-content h4 {
            margin: 0 0 0.25rem 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .low-stock-content p {
            margin: 0;
            font-size: 14px;
            opacity: 0.9;
            line-height: 1.3;
        }
        
        .low-stock-link {
            color: white;
            text-decoration: underline;
            margin-left: 0.5rem;
        }
        
        .low-stock-link:hover {
            color: #fff3e0;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        /* Dark mode */
        body.dark-theme-variables .low-stock-alert {
            background: linear-gradient(135deg, #f57c00, #e65100);
        }
        
        /* Widget de Controle de Estoque - Canto Direito */
        .stock-control-widget {
            position: fixed;
            top: 50%;
            right: 20px;
            transform: translateY(-50%);
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            padding: 1.2rem;
            width: 200px;
            z-index: 1000;
            border: 1px solid #e5e5e5;
        }
        
        .stock-widget-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .stock-widget-header h4 {
            margin: 0;
            font-size: 14px;
            color: #333;
            font-weight: 600;
        }
        
        .stock-widget-icon {
            font-size: 18px !important;
            color: #666;
        }
        
        .stock-stat {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0;
            font-size: 13px;
        }
        
        .stock-stat-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #555;
        }
        
        .stock-stat-number {
            font-weight: 600;
            font-size: 14px;
        }
        
        .stock-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        
        .dot-available { background: #00d4aa; }
        .dot-low { background: #ff6b35; }
        .dot-out { background: #e91e63; }
        
        .stock-stat.clickable {
            cursor: pointer;
            padding: 0.5rem;
            margin: 0 -0.5rem;
            border-radius: 8px;
            transition: background 0.2s ease;
        }
        
        .stock-stat.clickable:hover {
            background: #f8f9fa;
        }
        
        /* Fechar alerta */
        .alert-close {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            opacity: 0.8;
            font-size: 18px;
            padding: 4px;
            margin-left: auto;
        }
        
        .alert-close:hover {
            opacity: 1;
        }
        
        /* Dark mode para widget */
        body.dark-theme-variables .stock-control-widget {
            background: var(--color-white);
            border-color: var(--color-light);
            box-shadow: 0 8px 32px rgba(255, 255, 255, 0.1);
        }
        
        body.dark-theme-variables .stock-widget-header {
            border-color: var(--color-light);
        }
        
        body.dark-theme-variables .stock-widget-header h4 {
            color: var(--color-dark);
        }
        
        body.dark-theme-variables .stock-widget-icon {
            color: var(--color-dark-variant);
        }
        
        body.dark-theme-variables .stock-stat-label {
            color: var(--color-dark-variant);
        }
        
        body.dark-theme-variables .stock-stat-number {
            color: var(--color-dark);
        }
        
        body.dark-theme-variables .stock-stat.clickable:hover {
            background: var(--color-light);
        }
        
        /* Mensagem sem produtos */
        .no-products {
            text-align: center;
            padding: 3rem;
            color: var(--color-dark-variant);
        }
        
        .no-products-icon {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
        }
        
        .no-products h3 {
            margin-bottom: 0.5rem;
            color: var(--color-dark);
        }
        
        .no-products p {
            font-size: 14px;
            line-height: 1.4;
        }
        
        .no-products a {
            color: var(--color-primary);
            text-decoration: none;
        }
        
        .no-products a:hover {
            text-decoration: underline;
        }
        
        .products-list {
            background: var(--color-white);
            border-radius: 1rem;
            padding: 1rem;
            box-shadow: var(--box-shadow);
        }
        
        .product-item {
            display: grid;
            grid-template-columns: 60px 1fr auto auto auto auto;
            gap: 1rem;
            align-items: center;
            padding: 1rem;
            border: 1px solid var(--color-primary);
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .product-item:last-child {
            margin-bottom: 0;
        }        .product-image {
            width: 50px;
            height: 50px;
            background: #f5f5f5;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .product-info h4 {
            margin: 0 0 0.5rem 0;
            color: var(--color-dark);
        }
        
        .editable {
            padding: 4px 8px;
            border: 1px solid transparent;
            border-radius: 4px;
            cursor: pointer;
            min-width: 60px;
            text-align: center;
            display: inline-block;
        }
        
        .editable:hover {
            background: #f0f0f0;
            border-color: var(--color-primary);
        }
        
        /* Estilos de preço melhorados */
        .price-promo {
            color: var(--color-danger);
            font-weight: 700;
            font-size: 1.1em;
        }
        
        .price-original {
            text-decoration: line-through;
            color: #999;
            font-size: 0.85em;
            opacity: 0.7;
        }
        
        /* Indicadores de Estoque com Alertas */
        .stock-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 6px 8px;
            border-radius: 6px;
            min-width: 80px;
            justify-content: center;
        }
        
        .stock-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--color-success);
        }
        
        /* Estados de Estoque com Alertas Visuais */
        .stock-indicator.stock-ok {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid var(--color-success);
        }
        
        .stock-indicator.stock-low {
            background: rgba(255, 187, 85, 0.2);
            border: 1px solid var(--color-warning);
            animation: pulse-warning 2s infinite;
        }
        
        .stock-indicator.stock-low .stock-dot {
            background: var(--color-warning);
        }
        
        .stock-indicator.stock-out {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid var(--color-danger);
            animation: pulse-danger 1.5s infinite;
        }
        
        .stock-indicator.stock-out .stock-dot {
            background: var(--color-danger);
        }
        
        /* Animações de Alerta */
        @keyframes pulse-warning {
            0%, 100% { 
                transform: scale(1); 
                box-shadow: 0 0 5px rgba(255, 187, 85, 0.3);
            }
            50% { 
                transform: scale(1.05); 
                box-shadow: 0 0 10px rgba(255, 187, 85, 0.6);
            }
        }
        
        @keyframes pulse-danger {
            0%, 100% { 
                transform: scale(1); 
                box-shadow: 0 0 5px rgba(244, 67, 54, 0.4);
            }
            50% { 
                transform: scale(1.08); 
                box-shadow: 0 0 15px rgba(244, 67, 54, 0.8);
            }
        }

        /* === THEME TOGGLER STYLES (Idêntico ao dashboard.css) === */
        .right .theme-toggler {
            background: var(--color-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 1.6rem;
            width: 4.2rem;
            cursor: pointer;
            border-radius: var(--border-radius-3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .right .theme-toggler:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .right .theme-toggler span {
            font-size: 1.2rem;
            width: 50%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--border-radius-3);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            transform: scale(0.95) rotate(0deg);
        }

        .right .theme-toggler span.active {
            background: var(--color-danger);
            color: white;
            transform: scale(1);
            box-shadow: 0 4px 8px rgba(255, 0, 212, 0.3);
        }
        
        /* Botões de Ação - Lado Direito */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: center;
            justify-content: flex-start;
            padding: 6px;
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }

        /* Container do produto com posição relativa */
        .product-item {
            position: relative !important;
            overflow: visible;
        }
        
        /* Botões modernos minimalistas */
        .product-actions {
            display: flex;
            gap: 4px;
            align-items: center;
            justify-content: flex-end;
            padding: 0;
        }

        .action-btn {
            width: 26px;
            height: 26px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: all 0.15s ease;
            background: transparent;
            color: var(--color-info-dark);
            opacity: 0.6;
        }

        .action-btn:hover {
            opacity: 1;
            transform: scale(1.05);
        }

        .edit-btn:hover {
            color: #3b82f6;
        }

        .delete-btn:hover {
            color: #ef4444;
        }

        .action-btn .material-symbols-sharp {
            font-size: 16px;
        }

        .edit-btn:hover {
            background: #8b5cf6;
        }

        .delete-btn:hover {
            background: #ff1493;
        }



        /* Modo escuro minimalista */
        body.dark-theme-variables .action-btn {
            background: rgba(0, 0, 0, 0.3);
            color: #9ca3af;
        }

        body.dark-theme-variables .edit-btn:hover {
            background: #8b5cf6;
            color: white;
        }

        body.dark-theme-variables .delete-btn:hover {
            background: #ff1493;
            color: white;
        }

        /* Bordas de card de produto no modo escuro */
        body.dark-theme-variables .product-item {
            border: 1px solid var(--color-light);
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        body.dark-theme-variables .product-item:last-child {
            margin-bottom: 0;
        }

        /* === NOTIFICAÇÕES TOAST MODERNAS === */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            min-width: 300px;
            max-width: 500px;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 4px solid;
        }

        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .toast-success {
            background: rgba(76, 175, 80, 0.95);
            border-left-color: #4CAF50;
            color: white;
        }

        .toast-error {
            background: rgba(244, 67, 54, 0.95);
            border-left-color: #f44336;
            color: white;
        }

        .toast-warning {
            background: rgba(255, 193, 7, 0.95);
            border-left-color: #FFC107;
            color: #333;
        }

        .toast-info {
            background: rgba(33, 150, 243, 0.95);
            border-left-color: #2196F3;
            color: white;
        }

        .toast-icon {
            font-size: 20px;
            flex-shrink: 0;
        }

        .toast-message {
            flex: 1;
            line-height: 1.4;
        }

        .toast-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            opacity: 0.7;
            transition: opacity 0.2s ease;
        }

        .toast-close:hover {
            opacity: 1;
        }

        /* Dark mode compatibility */
        body.dark-theme-variables .toast-success {
            background: rgba(76, 175, 80, 0.9);
        }

        body.dark-theme-variables .toast-error {
            background: rgba(244, 67, 54, 0.9);
        }

        body.dark-theme-variables .toast-warning {
            background: rgba(255, 193, 7, 0.9);
            color: #000;
        }

        body.dark-theme-variables .toast-info {
            background: rgba(33, 150, 243, 0.9);
        }
        
        /* Design responsivo */
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr !important;
            }
            
            .products-title {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .add-product-btn {
                justify-content: center;
            }
            
            .filters-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .product-item {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .action-buttons {
                justify-content: center;
                margin-top: 0.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- SIDEBAR -->
        <aside>
            <div class="top">
                <div class="logo">
                    <img src="../../../assets/images/Logodz.png" alt="Logo">
                    <a href="index.php"><h2 class="danger">D&Z</h2></a>
                </div>
                <div class="close" id="close-btn">
                    <span class="material-symbols-sharp">close</span>
                </div>
            </div>

            <div class="sidebar">
                <a href="index.php">
                    <span class="material-symbols-sharp">grid_view</span>
                    <h3>Painel</h3>
                </a>
                <a href="customers.php">
                    <span class="material-symbols-sharp">group</span>
                    <h3>Clientes</h3>
                </a>
                <a href="orders.php">
                    <span class="material-symbols-sharp">Orders</span>
                    <h3>Pedidos</h3>
                </a>

                <a href="analytics.php">
                    <span class="material-symbols-sharp">Insights</span>
                    <h3>Gráficos</h3>
                </a>
                <a href="menssage.php">
                    <span class="material-symbols-sharp">Mail</span>
                    <h3>Mensagens</h3>
                    <span class="message-count"><?php echo $nao_lidas; ?></span>
                </a>
                <a href="products.php" class="active">
                    <span class="material-symbols-sharp">Inventory</span>
                    <h3>Produtos</h3>
                </a>
                <a href="gestao-fluxo.php">
                    <span class="material-symbols-sharp">account_tree</span>
                    <h3>Gestão de Fluxo</h3>
                </a>
                <div class="menu-item-container">
                  <a href="geral.php" class="menu-item-with-submenu">
                      <span class="material-symbols-sharp">Settings</span>
                      <h3>Configurações</h3>
                  </a>
                  
                  <div class="submenu">
                    <a href="geral.php">
                      <span class="material-symbols-sharp">tune</span>
                      <h3>Geral</h3>
                    </a>
                    <a href="pagamentos.php">
                      <span class="material-symbols-sharp">payments</span>
                      <h3>Pagamentos</h3>
                    </a>
                    <a href="frete.php">
                      <span class="material-symbols-sharp">local_shipping</span>
                      <h3>Frete</h3>
                    </a>
                    <a href="automacao.php">
                      <span class="material-symbols-sharp">automation</span>
                      <h3>Automação</h3>
                    </a>
                    <a href="metricas.php">
                      <span class="material-symbols-sharp">analytics</span>
                      <h3>Métricas</h3>
                    </a>
                    <a href="settings.php">
                      <span class="material-symbols-sharp">group</span>
                      <h3>Usuários</h3>
                    </a>
                  </div>
                </div>
                
                <a href="revendedores.php">
                    <span class="material-symbols-sharp">handshake</span>
                    <h3>Revendedores</h3>
                </a>
                <a href="../../../PHP/logout.php">
                    <span class="material-symbols-sharp">Logout</span>
                    <h3>Sair</h3>
                </a>
            </div>
        </aside>

        <!-- CONTEÚDO PRINCIPAL -->
        <main>
            <div class="products-header">
                <div class="products-title">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <h1>Produtos (<?php echo $total_products; ?>)</h1>
                        <div class="bulk-selection-controls" style="display: none;">
                            <span id="selected-count" style="color: var(--color-primary); font-weight: 600;">0 selecionados</span>
                        </div>
                    </div>
                    <div class="header-actions" style="display: flex; gap: 1rem; align-items: center;">
                        <button id="exportAllBtn" class="secondary-btn" onclick="exportAllProducts()">
                            <span class="material-symbols-sharp">download</span>
                            Exportar Tudo
                        </button>
                        <button id="importBtn" class="secondary-btn" onclick="openImportModal()">
                            <span class="material-symbols-sharp">upload</span>
                            Importar Planilha
                        </button>
                        <a href="addproducts.php" class="add-product-btn">
                            <span class="material-symbols-sharp">add</span>
                            Adicionar Produto
                        </a>
                    </div>
                </div>
                
                <!-- Barra de filtros -->
                <div class="filters-bar">
                    <form method="GET" class="filters-form">
                        <div class="filters-row">
                            <div class="filter-group">
                                <label>Buscar</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Nome, SKU, categoria ou subcategoria..." class="search-input">
                            </div>
                            
                            <div class="filter-group">
                                <label>Categoria</label>
                                <select name="categoria" class="category-select">
                                    <option value="">Todas</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" 
                                                <?php echo $categoria == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label>Status</label>
                                <select name="status" class="status-select">
                                    <option value="">Todos</option>
                                    <option value="ativo" <?php echo $status === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                                    <option value="inativo" <?php echo $status === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label>Estoque</label>
                                <select name="estoque" class="stock-select">
                                    <option value="">Todos</option>
                                    <option value="disponivel" <?php echo $estoque === 'disponivel' ? 'selected' : ''; ?>>Disponível</option>
                                    <option value="baixo" <?php echo $estoque === 'baixo' ? 'selected' : ''; ?>>Baixo</option>
                                    <option value="esgotado" <?php echo $estoque === 'esgotado' ? 'selected' : ''; ?>>Esgotado</option>
                                </select>
                            </div>
                            
                            <div class="filter-buttons">
                                <button type="submit" class="btn-filter btn-search">
                                    <span class="material-symbols-sharp">search</span>
                                </button>
                                <a href="products.php" class="btn-filter btn-clear">
                                    <span class="material-symbols-sharp">clear</span>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Widget de controle de estoque -->
                <div class="stock-control-widget">
                    <div class="stock-widget-header">
                        <span class="material-symbols-sharp stock-widget-icon">inventory_2</span>
                        <h4>Controle de Estoque</h4>
                    </div>
                    
                    <div class="stock-stat clickable" onclick="window.location.href='?estoque=baixo';" title="Ver produtos com baixo estoque" style="color: #ff6b35;">
                        <div class="stock-stat-label">
                            <div class="stock-dot dot-low"></div>
                            <span>Baixo Estoque</span>
                        </div>
                        <span class="stock-stat-number" style="color: #ff6b35;"><?php echo $stats_baixo; ?></span>
                    </div>
                    
                    <div class="stock-stat clickable" onclick="window.location.href='?estoque=esgotado';" title="Ver produtos esgotados" style="color: #e91e63;">
                        <div class="stock-stat-label">
                            <div class="stock-dot dot-out"></div>
                            <span>Esgotado</span>
                        </div>
                        <span class="stock-stat-number" style="color: #e91e63;"><?php echo $stats_esgotado; ?></span>
                    </div>
                </div>
            </div>

            <div class="products-list">
                <!-- Cabeçalho com Selecionar Todos -->
                <?php 
                // Verificar se há produtos primeiro
                mysqli_data_seek($products, 0); // Resetar ponteiro
                $has_products = mysqli_num_rows($products) > 0;
                ?>
                <?php if ($has_products): ?>
                <div class="products-list-header">
                    <div class="select-all-container">
                        <label class="bulk-checkbox-container">
                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()">
                            <span class="bulk-checkbox"></span>
                            <span class="select-all-text">Selecionar Todos</span>
                        </label>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php 
                mysqli_data_seek($products, 0); // Resetar ponteiro novamente para os produtos
                while ($product = mysqli_fetch_assoc($products)):
                    
                    // Debug temporário - mostrar dados do produto
                    if (isset($_GET['debug'])) {
                        echo "<div style='background: #fff3cd; padding: 5px; margin: 5px; border: 1px solid #856404;'>";
                        echo "<strong>Produto ID {$product['id']}:</strong> ";
                        echo "Nome: {$product['nome']}, ";
                        echo "Estoque DB: {$product['estoque']}";
                        echo "</div>";
                    }
                    
                    // Buscar variações do produto
                    $variations_query = "SELECT * FROM produto_variacoes WHERE produto_id = ? ORDER BY tipo, valor";
                    $variations_stmt = mysqli_prepare($conexao, $variations_query);
                    mysqli_stmt_bind_param($variations_stmt, "i", $product['id']);
                    mysqli_stmt_execute($variations_stmt);
                    $variations_result = mysqli_stmt_get_result($variations_stmt);
                    $variations = [];
                    while ($var = mysqli_fetch_assoc($variations_result)) {
                        $variations[] = $var;
                    }
                ?>
                    <div class="product-item" data-product-id="<?php echo $product['id']; ?>">
                        <!-- Checkbox para seleção -->
                        <div class="product-selection">
                            <label class="bulk-checkbox-container">
                                <input type="checkbox" class="product-checkbox" value="<?php echo $product['id']; ?>" onchange="updateSelection()">
                                <span class="bulk-checkbox"></span>
                            </label>
                        </div>
                        
                        <!-- Imagem -->
                        <div class="product-image">
                            <?php if (!empty($product['imagem_principal'])): ?>
                                <img src="../../../assets/images/produtos/<?php echo $product['imagem_principal']; ?>" 
                                     alt="<?php echo htmlspecialchars($product['nome']); ?>"
                                     onerror='this.parentElement.innerHTML="<span class=&quot;material-symbols-sharp&quot; style=&quot;color: #ccc;&quot;>broken_image</span>";'>
                            <?php else: ?>
                                <span class="material-symbols-sharp" style="color: #ccc;">image</span>
                            <?php endif; ?>
                        </div>

                        <!-- Informações -->
                        <div class="product-info">
                                    <h4><?php echo htmlspecialchars($product['nome']); ?></h4>
                                    <?php if ($product['sku']): ?>
                                        <small style="color: #666;">SKU: <?php echo htmlspecialchars($product['sku']); ?></small>
                                    <?php endif; ?>
                                </div>

                        <!-- Preços -->
                        <div class="product-prices">
                            <?php 
                            $has_promo = !empty($product['preco_promocional']) && $product['preco_promocional'] > 0;
                            ?>
                            
                            <?php if ($has_promo): ?>
                                <!-- Preço original riscado -->
                                <span class="price-original editable" onclick="editField(this, 'preco', <?php echo $product['id']; ?>)" title="Preço original">
                                    R$ <?php echo number_format($product['preco'], 2, ',', '.'); ?>
                                </span>
                                <!-- Preço promocional em destaque -->
                                <span class="price-promo editable" onclick="editField(this, 'preco_promocional', <?php echo $product['id']; ?>)" title="Preço promocional">
                                    R$ <?php echo number_format($product['preco_promocional'], 2, ',', '.'); ?>
                                </span>
                            <?php else: ?>
                                <!-- Preço normal -->
                                <span class="price-main editable" onclick="editField(this, 'preco', <?php echo $product['id']; ?>)" title="Preço normal - clique para editar">
                                    R$ <?php echo number_format($product['preco'], 2, ',', '.'); ?>
                                </span>
                                <?php if ($product['preco_promocional'] > 0): ?>
                                    <span class="price-promo-add editable" onclick="editField(this, 'preco_promocional', <?php echo $product['id']; ?>)" title="Preço promocional - clique para editar">
                                        💰 R$ <?php echo number_format($product['preco_promocional'], 2, ',', '.'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="price-add-btn editable" onclick="editField(this, 'preco_promocional', <?php echo $product['id']; ?>)" title="Adicionar preço promocional - clique aqui">
                                        + Promoção
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Estoque com alertas -->
                        <div class="stock-container">
                            <?php 
                            // Calcular estoque total (produto + variações)
                            $stock_total = (int)$product['estoque'];
                            if (!empty($variations)) {
                                $variation_stock = 0;
                                foreach ($variations as $var) {
                                    $variation_stock += (int)$var['estoque'];
                                }
                                $stock_total = $variation_stock; // Se tem variações, mostrar soma das variações
                            }
                            
                            $stock_status = 'ok';
                            $stock_color = '#00d4aa';
                            $stock_icon = 'check_circle';
                            
                            if ($stock_total == 0) {
                                $stock_status = 'out';
                                $stock_color = '#e91e63';
                                $stock_icon = 'cancel';
                            } elseif ($stock_total <= 10) {
                                $stock_status = 'low';
                                $stock_color = '#ff6b35';
                                $stock_icon = 'warning';
                            }
                            ?>
                            
                            <div class="stock-badge stock-<?php echo $stock_status; ?>" style="border-color: <?php echo $stock_color; ?>; color: <?php echo $stock_color; ?>;">
                                <span class="material-symbols-sharp" style="font-size: 14px;"><?php echo $stock_icon; ?></span>
                                <?php if (!empty($variations)): ?>
                                    <span class="stock-number" title="Estoque total das variações"><?php echo $stock_total; ?></span>
                                <?php else: ?>
                                    <span class="stock-number editable" onclick="editField(this, 'estoque', <?php echo $product['id']; ?>)" title="Clique para editar estoque"><?php echo $stock_total; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Botões de ação -->
                        <div class="product-actions">
                            <button class="action-btn edit-btn" onclick="editProduct(<?php echo $product['id']; ?>)" title="Editar produto">
                                <span class="material-symbols-sharp">edit</span>
                            </button>
                            
                            <button class="action-btn delete-btn" onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo addslashes($product['nome']); ?>')" title="Excluir produto">
                                <span class="material-symbols-sharp">delete</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Variações do Produto - Embaixo -->
                    <?php if (!empty($variations)): ?>
                    <div class="product-variations-section">
                        <div class="variations-header-compact" onclick="toggleVariations(<?php echo $product['id']; ?>)">
                            <span class="variations-title-compact">
                                <span class="material-symbols-sharp">tune</span>
                                Variações (<?php echo count($variations); ?>)
                            </span>
                            <span class="variations-arrow-compact" id="arrow-<?php echo $product['id']; ?>">
                                <span class="material-symbols-sharp">expand_more</span>
                            </span>
                        </div>
                        <div class="variations-grid collapsed" id="variations-grid-<?php echo $product['id']; ?>">
                            <?php foreach ($variations as $variation): ?>
                            <div class="variation-row" onclick="event.stopPropagation(); selectVariation(<?php echo $product['id']; ?>, <?php echo $variation['id']; ?>, '<?php echo $variation['imagem']; ?>')">
                                <div class="variation-image-small">
                                    <?php if (!empty($variation['imagem']) && file_exists("../../../assets/images/produtos/" . $variation['imagem'])): ?>
                                        <img src="../../../assets/images/produtos/<?php echo $variation['imagem']; ?>" 
                                             alt="<?php echo $variation['tipo'] . ': ' . $variation['valor']; ?>" 
                                             class="variation-thumb-small">
                                    <?php else: ?>
                                        <div class="variation-no-image-small">
                                            <span class="material-symbols-sharp">image</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="variation-compact">
                                    <div class="variation-basic-info">
                                        <span class="variation-type"><?php echo $variation['tipo']; ?>:</span>
                                        <span class="variation-name"><?php echo $variation['valor']; ?></span>
                                    </div>
                                    
                                    <div class="variation-data">
                                        <?php 
                                        $is_inherited = empty($variation['preco']) || $variation['preco'] == 0 || $variation['preco'] == $product['preco'];
                                        $display_price = ($variation['preco'] && $variation['preco'] > 0) ? $variation['preco'] : $product['preco'];
                                        $has_promo = !empty($variation['preco_promocional']) && $variation['preco_promocional'] > 0;
                                        ?>
                                        
                                        <div class="price-section-inline">
                                            <?php if ($has_promo): ?>
                                                <span class="price-original-inline">R$ <?php echo number_format($display_price, 2, ',', '.'); ?></span>
                                                <span class="editable price-promo-inline" 
                                                      onclick="event.stopPropagation(); editVariationField(this, 'preco_promocional', <?php echo $variation['id']; ?>)">
                                                    R$ <?php echo number_format($variation['preco_promocional'], 2, ',', '.'); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="editable price-main-inline <?php echo $is_inherited ? 'inherited' : 'custom'; ?>" 
                                                      onclick="event.stopPropagation(); editVariationField(this, 'preco', <?php echo $variation['id']; ?>)">
                                                    R$ <?php echo number_format($display_price, 2, ',', '.'); ?>
                                                    <?php if ($is_inherited): ?><small class="inherited-tag">h</small><?php endif; ?>
                                                </span>
                                                <span class="editable add-promo-inline" 
                                                      onclick="event.stopPropagation(); editVariationField(this, 'preco_promocional', <?php echo $variation['id']; ?>)">
                                                    +P
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="stock-section-inline">
                                            <span class="stock-label-inline">Est:</span>
                                            <span class="editable stock-value-inline" 
                                                  onclick="event.stopPropagation(); editVariationField(this, 'estoque', <?php echo $variation['id']; ?>)">
                                                <?php echo $variation['estoque']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endwhile; ?>
                
                <?php if (!$has_products): ?>
                    <div class="no-products">
                        <div class="no-products-icon">
                            <span class="material-symbols-sharp">inventory_2</span>
                        </div>
                        <h3>Nenhum produto encontrado</h3>
                        <p>
                            <?php if ($search || $categoria || $status || $estoque): ?>
                                Tente ajustar os filtros ou <a href="products.php">limpar a pesquisa</a>
                            <?php else: ?>
                                Nenhum produto cadastrado ainda.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <!-- BARRA DE AÇÕES EM MASSA (FLUTUANTE) -->
        <div id="bulkActionBar" class="bulk-action-bar">
            <div class="bulk-action-content">
                <div class="bulk-action-info">
                    <span id="bulkSelectedCount">0</span> produtos selecionados
                </div>
                <div class="bulk-actions">
                    <button class="bulk-btn stock-btn" onclick="openBulkStockModal()">
                        <span class="material-symbols-sharp">inventory</span>
                        Alterar Estoque
                    </button>
                    <button class="bulk-btn export-btn" onclick="exportSelectedProducts()">
                        <span class="material-symbols-sharp">download</span>
                        Exportar Selecionados
                    </button>
                    <button class="bulk-btn delete-btn" onclick="confirmBulkDelete()">
                        <span class="material-symbols-sharp">delete</span>
                        Excluir Selecionados
                    </button>
                    <button class="bulk-btn cancel-btn" onclick="clearAllSelections()">
                        <span class="material-symbols-sharp">close</span>
                        Cancelar
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL PARA IMPORTAÇÃO -->
        <div id="importModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Importar Produtos</h3>
                    <button class="modal-close" onclick="closeImportModal()">
                        <span class="material-symbols-sharp">close</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="import-instructions">
                        <h4>📋 Formato da Planilha</h4>
                        <p>Sua planilha deve conter as seguintes colunas (nesta ordem):</p>
                        <div class="format-table">
                            <div class="format-row">
                                <span class="col-name">SKU</span>
                                <span class="col-desc">Código único (obrigatório)</span>
                            </div>
                            <div class="format-row">
                                <span class="col-name">Nome</span>
                                <span class="col-desc">Nome do produto (obrigatório)</span>
                            </div>
                            <div class="format-row">
                                <span class="col-name">Categoria</span>
                                <span class="col-desc">Categoria do produto</span>
                            </div>
                            <div class="format-row">
                                <span class="col-name">Preço</span>
                                <span class="col-desc">Preço normal (obrigatório)</span>
                            </div>
                            <div class="format-row">
                                <span class="col-name">Preço Promocional</span>
                                <span class="col-desc">Preço em promoção (opcional)</span>
                            </div>
                            <div class="format-row">
                                <span class="col-name">Estoque</span>
                                <span class="col-desc">Quantidade em estoque</span>
                            </div>
                            <div class="format-row">
                                <span class="col-name">Status</span>
                                <span class="col-desc">ativo/inativo (opcional)</span>
                            </div>
                            <div class="format-row">
                                <span class="col-name">Descrição</span>
                                <span class="col-desc">Descrição do produto (opcional)</span>
                            </div>
                            <div class="format-row">
                                <span class="col-name">URL Imagem</span>
                                <span class="col-desc">Link da imagem (opcional)</span>
                            </div>
                            <div class="format-row">
                                <span class="col-name">Variações</span>
                                <span class="col-desc">Formato: Cor:Azul=10;Tamanho:M=5</span>
                            </div>
                        </div>
                    </div>
                    
                    <form id="importForm" enctype="multipart/form-data">
                        <div class="file-upload-area" onclick="document.getElementById('importFile').click()">
                            <span class="material-symbols-sharp">cloud_upload</span>
                            <p>Clique para selecionar arquivo CSV ou Excel</p>
                            <small>Formatos aceitos: .csv, .xlsx, .xls</small>
                            <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display: none;">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn-secondary" onclick="closeImportModal()">Cancelar</button>
                    <button class="btn-primary" onclick="processImport()" id="importButton" disabled>
                        <span class="material-symbols-sharp">upload</span>
                        Importar
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL PARA ALTERAR ESTOQUE EM MASSA -->
        <div id="bulkStockModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Alterar Estoque em Massa</h3>
                    <button class="modal-close" onclick="closeBulkStockModal()">
                        <span class="material-symbols-sharp">close</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="stock-operation-selector">
                        <label class="radio-option">
                            <input type="radio" name="stockOperation" value="add" checked>
                            <span class="radio-custom"></span>
                            <span class="radio-text">
                                <strong>Somar</strong> - Adicionar unidades ao estoque atual
                            </span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="stockOperation" value="subtract">
                            <span class="radio-custom"></span>
                            <span class="radio-text">
                                <strong>Subtrair</strong> - Remover unidades do estoque atual
                            </span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="stockOperation" value="set">
                            <span class="radio-custom"></span>
                            <span class="radio-text">
                                <strong>Definir</strong> - Substituir estoque por valor exato
                            </span>
                        </label>
                    </div>
                    
                    <div class="stock-input-group">
                        <label for="stockValue">Valor:</label>
                        <input type="number" id="stockValue" min="0" value="1" class="stock-input">
                    </div>
                    
                    <div class="stock-preview" id="stockPreview">
                        <!-- Preview será preenchido via JavaScript -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-secondary" onclick="closeBulkStockModal()">Cancelar</button>
                    <button class="btn-primary" onclick="executeBulkStockUpdate()">
                        <span class="material-symbols-sharp">check</span>
                        Aplicar Alteração
                    </button>
                </div>
            </div>
        </div>

        <!-- PAINEL DIREITO -->
        <div class="right">
            <div class="top">
                <button id="menu-btn">
                    <span class="material-symbols-sharp">menu</span>
                </button>
                <div class="theme-toggler">
                    <span class="material-symbols-sharp">light_mode</span>
                    <span class="material-symbols-sharp">dark_mode</span>
                </div>
                <div class="profile">
                    <div class="info">
                        <?php 
                        // Usar o nome do usuário da sessão
                        $usuario_nome = isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : 'Admin';
                        echo '<p>Olá, <b>' . htmlspecialchars($usuario_nome) . '</b></p>';
                        ?>
                        <small class="text-muted">Admin</small>
                    </div>
                    <div class="profile-photo">
                        <img src="../../../assets/images/logo_redondo.png" alt="Profile">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* === VARIÁVEIS DASHBOARD COMPATÍVEIS === */
        :root {
            --color-primary: #ff00d4;
            --color-danger: #ff00d4;
            --color-success: #41f1b6;
            --color-warning: #ffbb55;
            --color-white: #fff;
            --color-info-dark: #7d8da1;
            --color-info-light: #dce1eb;
            --color-dark: #363949;
            --color-light: rgba(132, 139, 200, 0.18);
            --color-primary-variant: #e600b8;
            --color-dark-variant: #677483;
            --color-background: #f6f6f9;
            --color-baby-pink: #ffccf9;

            --card-border-radius: 2rem;
            --border-radius-1: 0.4rem;
            --border-radius-2: 0.8rem;
            --border-radius-3: 1.2rem;

            --card-padding: 1.8rem;
            --padding-1: 1.2rem;

            --box-shadow: 0 2rem 3rem var(--color-light);
            
            /* Mapeamento para compatibilidade */
            --bg-primary: var(--color-background);
            --bg-secondary: var(--color-white);
            --bg-card: var(--color-white);
            --bg-input: var(--color-white);
            --text-primary: var(--color-dark);
            --text-secondary: var(--color-dark-variant);
            --text-muted: var(--color-info-dark);
            --border-color: var(--color-light);
            --shadow: var(--box-shadow);
            
            /* Accent Colors do sistema */
            --accent-pink: #ff00d4;
            --accent-green: #41f1b6;
            --accent-purple: #8b5cf6;
        }

        /* Dark Theme igual ao dashboard */
        body.dark-theme-variables {
            --color-background: #181a1e;
            --color-white: #202528;
            --color-dark: #edeffd;
            --color-dark-variant: #a3bdcc;
            --color-light: rgba(0, 0, 0, 0.4);
            --box-shadow: 0 2rem 3rem var(--color-light);
            
            /* Atualizar mapeamento para dark mode */
            --bg-primary: var(--color-background);
            --bg-secondary: var(--color-white);
            --bg-card: var(--color-white);
            --bg-input: var(--color-white);
            --text-primary: var(--color-dark);
            --text-secondary: var(--color-dark-variant);
            --text-muted: var(--color-info-dark);
            --border-color: var(--color-light);
            --shadow: var(--box-shadow);
        }

        /* === GLOBAL DARK MODE === */
        body {
            background: var(--bg-primary) !important;
            color: var(--text-primary) !important;
        }

        * {
            scrollbar-width: thin;
            scrollbar-color: var(--border-color) var(--bg-secondary);
        }

        *::-webkit-scrollbar {
            width: 6px;
        }

        *::-webkit-scrollbar-track {
            background: var(--bg-secondary);
        }

        *::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 3px;
        }

        *::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }

        /* Layout melhorado do card superior */
        .product-item {
            display: grid;
            grid-template-columns: 48px 2fr 100px 70px 100px auto;
            gap: 12px;
            align-items: center;
            padding: 10px 12px;
            background: var(--bg-card) !important;
            border-radius: 6px;
            border: 1px solid var(--border-color) !important;
            margin-bottom: 6px;
            transition: all 0.2s ease;
        }

        .product-item:hover {
            border-color: var(--border-hover) !important;
            box-shadow: 0 2px 8px rgba(255, 20, 147, 0.25) !important;
        }

        .product-image {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background: var(--bg-secondary) !important;
            border: 1px solid var(--border-color) !important;
            overflow: hidden;
            flex-shrink: 0;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-info {
            min-width: 0;
            padding-right: 6px;
        }

        .product-info h4 {
            margin: 0 0 2px 0;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary) !important;
            line-height: 1.2;
        }

        .product-info small {
            font-size: 11px;
            color: var(--text-secondary) !important;
        }

        .product-prices {
            display: flex;
            flex-direction: column;
            gap: 2px;
            align-items: center;
            min-width: 100px;
            padding: 0 4px;
        }

        .price-main {
            font-weight: 600;
            color: #1a1a2e;
            font-size: 13px;
        }

        .price-original {
            font-size: 11px;
            color: #94a3b8;
            text-decoration: line-through;
        }

        .price-promo {
            font-weight: 700;
            color: #e91e63;
            font-size: 13px;
        }

        .price-promo-add {
            font-size: 10px;
            color: #00d4aa;
            background: rgba(0, 212, 170, 0.1);
            padding: 2px 4px;
            border-radius: 3px;
        }

        .price-add-btn {
            font-size: 10px;
            color: #8b5cf6;
            border: 1px dashed #8b5cf6;
            padding: 2px 4px;
            border-radius: 3px;
            cursor: pointer;
        }

        .stock-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-width: 70px;
            padding: 0 4px;
        }

        .stock-badge {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 12px;
            border: 1px solid;
            background: var(--bg-secondary) !important;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .stock-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .stock-number {
            min-width: 20px;
            text-align: center;
        }

        .stock-ok {
            background: rgba(0, 212, 170, 0.1);
        }

        .stock-low {
            background: rgba(255, 107, 53, 0.1);
        }

        .stock-out {
            background: rgba(233, 30, 99, 0.1);
        }



        .product-variations {
            margin: 10px 0;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            overflow: hidden;
        }

        .variations-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            user-select: none;
        }

        .variations-header:hover {
            background: #e9ecef;
        }

        .variations-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #666;
        }

        .variations-arrow {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #fff;
            transition: transform 0.3s ease, background-color 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .variations-arrow:hover {
            background: #f0f0f0;
        }

        .variations-arrow .material-symbols-sharp {
            font-size: 18px;
            color: #666;
        }



        .variation-thumb {
            width: 24px;
            height: 24px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .variation-no-image {
            width: 24px;
            height: 24px;
            background: #f0f0f0;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
        }

        .variation-no-image .material-symbols-sharp {
            font-size: 14px;
        }

        .variation-info {
            display: flex;
            flex-direction: column;
        }

        .variation-label {
            font-weight: 600;
            color: #333;
        }

        .variation-value {
            color: #666;
        }

        .variation-price {
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 12px;
            background: #e3f2fd;
            color: #1565c0;
            margin-top: 2px;
        }

        /* Animação suave para troca de imagem */
        .product-image img {
            transition: transform 0.15s ease;
        }



        .variation-details {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #e9ecef;
        }

        .variation-field {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }

        .variation-field label {
            font-size: 10px;
            color: #6c757d;
            font-weight: 600;
        }

        .variation-editable {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid transparent;
            cursor: pointer;
            font-size: 10px;
            font-weight: 600;
            min-width: 45px;
            text-align: center;
            transition: all 0.2s ease;
        }

        .variation-editable:hover {
            background: #e9ecef;
            border-color: #6c757d;
            transform: translateY(-1px);
        }

        .variation-editable.promo {
            color: #e91e63;
            font-weight: bold;
        }

        .variation-editable.add-promo {
            color: #9e9e9e;
            font-style: italic;
            font-size: 10px;
            padding: 2px 6px;
        }

        .variation-editable.add-promo:hover {
            color: #4caf50;
            background: rgba(76, 175, 80, 0.1);
        }

        .variation-editable.inherited {
            color: #757575;
            border: 1px dashed #ccc;
            font-style: italic;
        }

        .variation-editable.inherited:hover {
            color: #4caf50;
            border-color: #4caf50;
            background: rgba(76, 175, 80, 0.05);
        }

        .variation-editable.custom {
            color: #1976d2;
            font-weight: bold;
            border: 1px solid #e3f2fd;
            background: rgba(25, 118, 210, 0.05);
        }

        .price-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }

        .original-price.crossed-out {
            font-size: 10px;
            color: #999;
            text-decoration: line-through;
            text-decoration-color: #e91e63;
            text-decoration-thickness: 2px;
        }

        .variation-editable.current-price {
            color: #e91e63;
            font-weight: bold;
            font-size: 12px;
        }

        .variation-editable.promo.current-price {
            background: rgba(233, 30, 99, 0.1);
            border: 1px solid #e91e63;
        }



        /* Layout moderno dos campos - Compacto */
        .variation-field {
            background: #f8fafc !important;
            border-radius: 4px !important;
            padding: 4px 6px !important;
            text-align: center !important;
            border: 1px solid #f1f5f9 !important;
            margin-bottom: 4px !important;
        }

        .variation-field label {
            font-size: 10px !important;
            font-weight: 600 !important;
            color: #64748b !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            display: block !important;
            margin-bottom: 4px !important;
        }

        /* Botões editáveis modernos */
        .variation-editable {
            display: inline-block !important;
            padding: 4px 8px !important;
            border-radius: 4px !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            transition: all 0.2s ease !important;
            cursor: pointer !important;
            border: 1px solid transparent !important;
            min-width: 60px !important;
        }

        .variation-editable:hover {
            background: #e2e8f0 !important;
            border-color: #4f46e5 !important;
            transform: scale(1.02) !important;
        }

        .variation-editable.inherited {
            color: #64748b !important;
            background: #f1f5f9 !important;
            font-style: italic !important;
            border: 1px dashed #cbd5e1 !important;
        }

        .variation-editable.custom {
            color: #4f46e5 !important;
            background: #eef2ff !important;
            border: 1px solid #c7d2fe !important;
        }

        .variation-editable.add-promo {
            color: #94a3b8 !important;
            font-size: 10px !important;
            padding: 3px 8px !important;
            background: transparent !important;
            border: 1px dashed #cbd5e1 !important;
        }

        .variation-editable.add-promo:hover {
            color: #4f46e5 !important;
            border-color: #4f46e5 !important;
            background: #f8fafc !important;
        }

        /* Preços com promoção */
        .original-price.crossed-out {
            font-size: 10px !important;
            color: #94a3b8 !important;
            text-decoration: line-through !important;
            text-decoration-color: #ef4444 !important;
            text-decoration-thickness: 1px !important;
            margin-bottom: 2px !important;
            display: block !important;
        }

        .variation-editable.current-price {
            color: #ef4444 !important;
            font-weight: 700 !important;
            background: #fef2f2 !important;
            border: 1px solid #fecaca !important;
        }



        /* Cabeçalho das variações - Compacto */
        .variations-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 12px;
            font-weight: 500;
            color: #475569;
        }

        .variations-header:hover {
            background: #e2e8f0;
            border-color: #4f46e5;
            color: #4f46e5;
        }

        .variations-title {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .variations-title .material-symbols-sharp {
            font-size: 16px;
        }

        .variations-arrow .material-symbols-sharp {
            font-size: 18px;
            transition: transform 0.2s ease;
        }

        .variations-arrow.rotated .material-symbols-sharp {
            transform: rotate(180deg);
        }

        /* Layout compacto das variações */
        .variation-compact {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }

        .variation-basic-info {
            display: flex;
            align-items: center;
            gap: 4px;
            min-width: 120px;
        }

        .variation-type {
            font-size: 10px;
            font-weight: 600;
            color: var(--text-muted) !important;
            text-transform: uppercase;
        }

        .variation-name {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-primary) !important;
        }

        .variation-data {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Preços e estoque inline */
        .price-section-inline {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .price-main-inline,
        .price-promo-inline {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .price-main-inline.inherited {
            color: #64748b;
            background: #f1f5f9;
            border: 1px dashed #cbd5e1;
        }

        .price-main-inline.custom {
            color: #4f46e5;
            background: #eef2ff;
            border: 1px solid #c7d2fe;
        }

        .price-promo-inline {
            color: #ef4444;
            background: #fef2f2;
            border: 1px solid #fecaca;
        }

        .price-original-inline {
            font-size: 10px;
            color: #94a3b8;
            text-decoration: line-through;
            text-decoration-color: #ef4444;
        }

        .add-promo-inline {
            font-size: 9px;
            color: #94a3b8;
            background: transparent;
            border: 1px dashed #cbd5e1;
            padding: 2px 4px;
            border-radius: 3px;
            cursor: pointer;
        }

        .add-promo-inline:hover {
            color: #4f46e5;
            border-color: #4f46e5;
        }

        .inherited-tag {
            font-size: 8px;
            color: #94a3b8;
            margin-left: 2px;
        }

        .stock-section-inline {
            display: flex;
            align-items: center;
            gap: 2px;
        }

        .stock-label-inline {
            font-size: 10px;
            color: var(--text-muted) !important;
            font-weight: 600;
        }

        .stock-value-inline {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-primary) !important;
            padding: 2px 6px;
            border-radius: 3px;
            background: var(--bg-input) !important;
            border: 1px solid var(--border-color) !important;
            cursor: pointer;
            min-width: 25px;
            text-align: center;
        }

        .stock-value-inline:hover {
            border-color: var(--accent-purple) !important;
            background: rgba(139, 92, 246, 0.1) !important;
        }

        /* Grid das variações */
        .variations-grid {
            display: grid;
            gap: 6px;
            margin-top: 8px;
        }

        .variation-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            background: var(--bg-card) !important;
            border: 1px solid var(--border-color) !important;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .variation-row:hover {
            border-color: var(--accent-pink) !important;
            background: rgba(255, 20, 147, 0.05) !important;
            box-shadow: 0 1px 4px rgba(255, 20, 147, 0.2) !important;
        }

        .variation-image-small {
            flex-shrink: 0;
        }

        .variation-thumb-small {
            width: 32px;
            height: 32px;
            border-radius: 4px;
            object-fit: cover;
        }

        .variation-no-image-small {
            width: 32px;
            height: 32px;
            background: var(--bg-secondary) !important;
            border: 1px dashed var(--border-color) !important;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted) !important;
            font-size: 14px;
        }

        /* Layout moderno das variações */
        .variation-info {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 16px;
            align-items: start;
        }

        .variation-thumb {
            width: 48px;
            height: 48px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid #e1e5e9;
        }

        .variation-no-image {
            width: 48px;
            height: 48px;
            border-radius: 6px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 20px;
        }

        .variation-label {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 4px;
        }

        .variation-value {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            display: block;
            margin-bottom: 12px;
        }

        .variation-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 12px;
            margin-top: 8px;
        }

        .variation-field {
            background: #f8fafc;
            border-radius: 6px;
            padding: 8px 10px;
            text-align: center;
        }

        .variation-field label {
            font-size: 10px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 4px;
        }

        .variation-editable {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .variation-editable:hover {
            background: #e2e8f0;
            border-color: #4f46e5;
        }

        .variation-editable.inherited {
            color: #64748b;
            background: #f1f5f9;
            font-style: italic;
            border: 1px dashed #cbd5e1;
        }

        .variation-editable.custom {
            color: #4f46e5;
            background: #eef2ff;
            border: 1px solid #c7d2fe;
        }

        .variation-editable.add-promo {
            color: #94a3b8;
            font-size: 10px;
            padding: 2px 6px;
            background: transparent;
            border: 1px dashed #cbd5e1;
        }

        .variation-editable.add-promo:hover {
            color: #4f46e5;
            border-color: #4f46e5;
        }

        .price-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }

        .original-price.crossed-out {
            font-size: 10px;
            color: #94a3b8;
            text-decoration: line-through;
            text-decoration-color: #ef4444;
            text-decoration-thickness: 1px;
        }

        .variation-editable.current-price {
            color: #ef4444;
            font-weight: 700;
            background: #fef2f2;
            border: 1px solid #fecaca;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .variation-details {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .variation-info {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .variation-thumb,
            .variation-no-image {
                width: 40px;
                height: 40px;
            }
        }

        /* Melhorar layout das variações */
        .variation-item {
            min-width: 180px;
            max-width: 200px;
            flex: 1 1 180px;
        }

        /* Melhorar responsividade das variações */
        @media (max-width: 768px) {
            .variations-list.expanded {
                max-height: 300px;
            }
            
            .variation-item {
                flex: 1 1 calc(50% - 4px);
                min-width: 160px;
            }
            
            .variation-field {
                font-size: 9px;
            }
            
            .variation-editable {
                font-size: 9px;
                padding: 1px 4px;
            }
        }

        /* Seção de variações separada embaixo */
        .product-variations-section {
            margin-top: 8px;
            margin-bottom: 16px;
            background: var(--bg-secondary) !important;
            border: 1px solid var(--border-color) !important;
            border-radius: 8px;
            overflow: hidden;
        }

        .variations-header-compact {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            background: var(--bg-card) !important;
            border-bottom: 1px solid var(--border-color) !important;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-secondary) !important;
        }

        .variations-header-compact:hover {
            background: rgba(255, 20, 147, 0.1) !important;
            color: var(--accent-pink) !important;
            border-color: var(--accent-pink) !important;
        }

        .variations-title-compact {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .variations-title-compact .material-symbols-sharp {
            font-size: 16px;
        }

        .variations-arrow-compact .material-symbols-sharp {
            font-size: 18px;
            transition: transform 0.2s ease;
        }

        .variations-arrow-compact.rotated .material-symbols-sharp {
            transform: rotate(180deg);
        }

        .variations-grid.collapsed {
            display: none;
        }

        .variations-grid {
            transition: opacity 0.2s ease, transform 0.2s ease;
            opacity: 1;
            transform: translateY(0);
        }

        /* === ESTILOS PARA SELEÇÃO EM MASSA === */
        .products-list-header {
            background: var(--color-background);
            border: 1px solid var(--color-primary);
            border-radius: 1rem 1rem 0 0;
            padding: 1rem;
            border-bottom: none;
        }
        
        .select-all-container {
            display: flex;
            align-items: center;
        }
        
        .bulk-checkbox-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            user-select: none;
        }
        
        .bulk-checkbox {
            width: 20px;
            height: 20px;
            border: 2px solid var(--color-primary);
            border-radius: 4px;
            position: relative;
            transition: all 0.2s ease;
        }
        
        .bulk-checkbox-container input[type="checkbox"] {
            display: none;
        }
        
        .bulk-checkbox-container input[type="checkbox"]:checked + .bulk-checkbox {
            background: var(--color-primary);
            border-color: var(--color-primary);
        }
        
        .bulk-checkbox-container input[type="checkbox"]:checked + .bulk-checkbox::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
        
        .select-all-text {
            font-weight: 600;
            color: var(--color-dark);
        }
        
        .product-selection {
            padding: 0 0.5rem;
        }
        
        /* Ajustar grid da lista para incluir checkbox */
        .product-item {
            display: grid;
            grid-template-columns: 50px 60px 1fr auto auto auto 80px;
            gap: 1rem;
            align-items: center;
            padding: 1rem;
            border: 1px solid var(--color-primary);
            border-top: none;
            position: relative;
        }
        
        .product-item:last-child {
            border-radius: 0 0 1rem 1rem;
        }
        
        /* === BARRA DE AÇÕES EM MASSA === */
        .bulk-action-bar {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            background: var(--color-white);
            border: 2px solid var(--color-primary);
            border-radius: 1rem;
            padding: 1rem 1.5rem;
            box-shadow: 0 10px 40px rgba(255, 0, 212, 0.2);
            z-index: 1000;
            transition: all 0.3s ease;
            opacity: 0;
            visibility: hidden;
            transform: translateX(-50%) translateY(20px);
        }
        
        .bulk-action-bar.show {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
        }
        
        .bulk-action-content {
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        
        .bulk-action-info {
            font-weight: 600;
            color: var(--color-dark);
            min-width: 120px;
        }
        
        .bulk-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        
        .bulk-btn {
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            font-size: 14px;
        }
        
        .bulk-btn .material-symbols-sharp {
            font-size: 18px;
        }
        
        .stock-btn {
            background: #3b82f6;
            color: white;
        }
        
        .stock-btn:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }
        
        .export-btn {
            background: #10b981;
            color: white;
        }
        
        .export-btn:hover {
            background: #059669;
            transform: translateY(-1px);
        }
        
        .delete-btn {
            background: #ef4444;
            color: white;
        }
        
        .delete-btn:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }
        
        .cancel-btn {
            background: #6b7280;
            color: white;
        }
        
        .cancel-btn:hover {
            background: #4b5563;
            transform: translateY(-1px);
        }
        
        /* === BOTÕES DO CABEÇALHO === */
        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .secondary-btn {
            padding: 0.75rem 1rem;
            background: var(--color-background);
            border: 2px solid var(--color-primary);
            color: var(--color-primary);
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .secondary-btn:hover {
            background: var(--color-primary);
            color: white;
            transform: translateY(-1px);
        }
        
        /* === MODAIS === */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            background: var(--color-white);
            border-radius: 1rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }
        
        .modal-overlay.show .modal-content {
            transform: scale(1);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid var(--color-light);
        }
        
        .modal-header h3 {
            margin: 0;
            color: var(--color-dark);
            font-size: 1.5rem;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--color-info-dark);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.2s ease;
        }
        
        .modal-close:hover {
            background: var(--color-light);
            color: var(--color-dark);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--color-light);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        /* === MODAL DE IMPORTAÇÃO === */
        .import-instructions h4 {
            color: var(--color-primary);
            margin-bottom: 0.5rem;
        }
        
        .format-table {
            background: var(--color-background);
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .format-row {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 1rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--color-light);
        }
        
        .format-row:last-child {
            border-bottom: none;
        }
        
        .col-name {
            font-weight: 600;
            color: var(--color-dark);
        }
        
        .col-desc {
            color: var(--color-info-dark);
            font-size: 14px;
        }
        
        .file-upload-area {
            border: 2px dashed var(--color-primary);
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 1rem;
        }
        
        .file-upload-area:hover {
            background: rgba(255, 0, 212, 0.05);
            border-color: var(--color-primary-variant);
        }
        
        .file-upload-area .material-symbols-sharp {
            font-size: 3rem;
            color: var(--color-primary);
            margin-bottom: 0.5rem;
        }
        
        .file-upload-area p {
            margin: 0 0 0.5rem 0;
            font-weight: 600;
            color: var(--color-dark);
        }
        
        .file-upload-area small {
            color: var(--color-info-dark);
        }
        
        /* === MODAL DE ESTOQUE === */
        .stock-operation-selector {
            margin-bottom: 1.5rem;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 2px solid var(--color-light);
            border-radius: 8px;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .radio-option:hover {
            border-color: var(--color-primary);
            background: rgba(255, 0, 212, 0.02);
        }
        
        .radio-option input[type="radio"] {
            display: none;
        }
        
        .radio-custom {
            width: 20px;
            height: 20px;
            border: 2px solid var(--color-info-dark);
            border-radius: 50%;
            position: relative;
            transition: all 0.2s ease;
        }
        
        .radio-option input[type="radio"]:checked + .radio-custom {
            border-color: var(--color-primary);
            background: var(--color-primary);
        }
        
        .radio-option input[type="radio"]:checked + .radio-custom::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
        }
        
        .radio-option input[type="radio"]:checked ~ .radio-text {
            color: var(--color-primary);
        }
        
        .radio-text {
            flex: 1;
        }
        
        .stock-input-group {
            margin-bottom: 1.5rem;
        }
        
        .stock-input-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--color-dark);
        }
        
        .stock-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--color-light);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.2s ease;
        }
        
        .stock-input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(255, 0, 212, 0.1);
        }
        
        .stock-preview {
            background: var(--color-background);
            border-radius: 8px;
            padding: 1rem;
            border-left: 4px solid var(--color-primary);
        }
        
        /* === BOTÕES DOS MODAIS === */
        .btn-primary, .btn-secondary {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--color-primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--color-primary-variant);
            transform: translateY(-1px);
        }
        
        .btn-primary:disabled {
            background: var(--color-info-dark);
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: var(--color-background);
            color: var(--color-dark);
            border: 2px solid var(--color-light);
        }
        
        .btn-secondary:hover {
            border-color: var(--color-primary);
            color: var(--color-primary);
            transform: translateY(-1px);
        }
    </style>

    <script src="../../js/dashboard.js"></script>
    <script>
        // Função para editar campo
        function editField(element, field, productId) {
            let currentValue = element.textContent.replace('R$ ', '').replace(',', '.');
            
            const input = document.createElement('input');
            input.type = field === 'estoque' ? 'number' : 'number';
            input.step = field === 'estoque' ? '1' : '0.01';
            input.min = '0';
            input.value = currentValue;
            input.style.width = field === 'estoque' ? '60px' : '80px';
            input.style.padding = '6px';
            input.style.border = '2px solid var(--accent-pink)';
            input.style.borderRadius = '4px';
            input.style.textAlign = 'center';
            input.style.fontSize = '14px';
            input.style.fontWeight = 'bold';
            input.style.background = 'var(--bg-input)';
            input.style.color = 'var(--text-primary)';
            
            // Placeholder informativo
            if (field === 'preco_promocional') {
                input.placeholder = 'Digite 0 para remover';
            } else if (field === 'estoque') {
                input.placeholder = 'Pode ser 0';
            }
            
            element.parentNode.replaceChild(input, element);
            input.focus();
            input.select();
            
            let isSaving = false;
            
            function saveValue() {
                if (isSaving) return;
                isSaving = true;
                
                let newValue = parseFloat(input.value) || 0;
                
                // Validações específicas
                if (field === 'estoque') {
                    newValue = Math.max(0, Math.floor(newValue));
                } else {
                    newValue = Math.max(0, newValue);
                }
                
                fetch('products.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=update_product&id=${productId}&field=${field}&value=${newValue}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        showError('Erro ao atualizar. Tente novamente.');
                        isSaving = false;
                        location.reload();
                    }
                })
                .catch(error => {
                    showError('Erro de conexão. Verifique sua internet.');
                    isSaving = false;
                    location.reload();
                });
            }
            
            input.addEventListener('blur', saveValue);
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    input.removeEventListener('blur', saveValue); // Remove blur para evitar duplo disparo
                    saveValue();
                } else if (e.key === 'Escape') {
                    location.reload();
                }
            });
        }
        

        
        // Função para editar produto
        function editProduct(productId) {
            window.location.href = `addproducts.php?edit=${productId}`;
        }
        
        // === MODAL DE EXCLUSÃO CUSTOMIZADO ===
        window.productToDelete = null;
        
        function deleteProduct(productId, productName) {
            console.log('DELETE: Setting productId to:', productId, 'Type:', typeof productId);
            window.productToDelete = parseInt(productId);
            console.log('DELETE: productToDelete set to:', window.productToDelete);
            
            // Também armazenar no botão como backup
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            confirmBtn.setAttribute('data-product-id', productId);
            
            document.getElementById('productName').textContent = productName;
            document.getElementById('deleteModalOverlay').classList.add('show');
        }
        
        // Funções para fechar modal
        function closeDeleteModal() {
            document.getElementById('deleteModalOverlay').classList.remove('show');
            window.productToDelete = null;
        }
        
        // Ouvintes de eventos para modal (consolidados em um único DOMContentLoaded)
        document.addEventListener('DOMContentLoaded', function() {
            // Botões de fechar modal
            document.getElementById('modalCloseBtn').addEventListener('click', closeDeleteModal);
            document.getElementById('cancelBtn').addEventListener('click', closeDeleteModal);
            
            // Fechar ao clicar na sobreposição
            document.getElementById('deleteModalOverlay').addEventListener('click', function(e) {
                if (e.target === this) closeDeleteModal();
            });
            
            // ESC key to close modal
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && document.getElementById('deleteModalOverlay').classList.contains('show')) {
                    closeDeleteModal();
                }
            });
            
            // Delete confirmation button
            document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
                console.log('CONFIRM: window.productToDelete:', window.productToDelete);
                
                // Backup: pegar do atributo se a variável for perdida
                let productId = window.productToDelete;
                if (!productId || productId === null) {
                    productId = parseInt(this.getAttribute('data-product-id'));
                    console.log('CONFIRM: Using backup ID from attribute:', productId);
                }
                
                if (productId && productId > 0) {
                    closeDeleteModal();
                    
                    // Add loading state to button
                    const btn = this;
                    const originalHtml = btn.innerHTML;
                    btn.innerHTML = '<span class="loading-spinner"></span>Excluindo...';
                    btn.disabled = true;
                    
                    const params = new URLSearchParams();
                    params.append('action', 'delete_product');
                    params.append('id', productId);
                    
                    console.log('SEND: Sending productId:', productId);
                    
                    fetch('products.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: params
                    })
                    .then(response => {
                        console.log('Response status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Response data:', data);
                        if (data.success) {
                            showSuccess(data.message || 'Produto excluído com sucesso!');
                            // Remover o produto da tela imediatamente
                            const productRow = document.querySelector(`[data-product-id="${productId}"]`);
                            if (productRow) {
                                productRow.style.transition = 'opacity 0.3s ease';
                                productRow.style.opacity = '0';
                                setTimeout(() => {
                                    productRow.remove();
                                }, 300);
                            }
                            // Recarregar mais rápido
                            setTimeout(() => location.reload(), 800);
                        } else {
                            showError(data.error || 'Erro ao excluir produto. Tente novamente.');
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        showError('Erro de conexão. Tente novamente.');
                    })
                    .finally(() => {
                        btn.innerHTML = originalHtml;
                        btn.disabled = false;
                    });
                }
            });
        });

        // Função para toggle das variações - Nova estrutura
        function toggleVariations(productId) {
            console.log('Toggling variations for product:', productId);
            
            const variationsGrid = document.getElementById(`variations-grid-${productId}`);
            const arrow = document.getElementById(`arrow-${productId}`);
            
            console.log('Found elements:', {grid: !!variationsGrid, arrow: !!arrow});
            
            if (variationsGrid && arrow) {
                if (variationsGrid.style.display === 'none' || !variationsGrid.style.display) {
                    // Expandir
                    variationsGrid.style.display = 'grid';
                    arrow.classList.add('rotated');
                    console.log('Expanded variations grid');
                } else {
                    // Recolher
                    variationsGrid.style.display = 'none';
                    arrow.classList.remove('rotated');
                    console.log('Collapsed variations grid');
                }
            } else {
                console.error('Could not find variations grid or arrow for product:', productId);
            }
        }

        // Função para editar campos das variações
        function editVariationField(element, field, variationId) {
            let currentValue = element.textContent.trim();
            
            // Verificar se é o botão de adicionar promoção
            if (currentValue === '+ Adicionar') {
                currentValue = '';
            } else {
                // Remover texto 'herdado' se existir
                currentValue = currentValue.replace('herdado', '').trim();
                currentValue = currentValue.replace('R$ ', '').replace(/\./g, '').replace(',', '.');
            }
            
            const input = document.createElement('input');
            input.type = field === 'estoque' ? 'number' : 'text';
            input.value = currentValue;
            input.style.width = '80px';
            input.style.padding = '4px';
            input.style.border = '2px solid var(--color-primary)';
            input.style.borderRadius = '4px';
            input.style.textAlign = 'center';
            input.style.fontSize = '11px';
            input.style.fontWeight = 'bold';
            
            if (field === 'preco' || field === 'preco_promocional') {
                input.placeholder = 'Ex: 10,99';
            }
            
            element.parentNode.replaceChild(input, element);
            input.focus();
            input.select();
            
            function saveVariationValue() {
                let newValue = input.value.trim();
                
                // Validação para preços
                if (field === 'preco' || field === 'preco_promocional') {
                    if (newValue === '') {
                        // Permitir limpar ambos os campos para herdar do produto pai
                        newValue = null;
                    } else {
                        // Validar formato de preço
                        if (!/^\d+([.,]\d{1,2})?$/.test(newValue)) {
                            showWarning('Digite um preço válido (ex: 10,99) ou deixe vazio para herdar do produto');
                            input.focus();
                            return;
                        }
                        newValue = parseFloat(newValue.replace(',', '.')).toFixed(2);
                    }
                } else if (field === 'estoque') {
                    if (!/^\d+$/.test(newValue)) {
                        showWarning('Digite um número válido para o estoque');
                        input.focus();
                        return;
                    }
                    newValue = parseInt(newValue);
                }
                
                fetch('products.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=update_variation_field&variation_id=${variationId}&field=${field}&value=${newValue || ''}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro HTTP: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Simplesmente recarregar a página para evitar problemas de sincronização
                        location.reload();
                    } else {
                        console.error('Erro do servidor:', data.message);
                        showError('Erro ao salvar: ' + (data.message || 'Erro desconhecido'));
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error('Erro de rede:', error);
                    showError('Erro de conexão. Verifique sua internet e tente novamente.');
                    location.reload();
                });
            }
            
            input.addEventListener('blur', saveVariationValue);
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    saveVariationValue();
                } else if (e.key === 'Escape') {
                    location.reload();
                }
            });
        }

        // Função para trocar imagem principal ao clicar em variação
        function selectVariation(productId, variationId, variationImage) {
            if (variationImage) {
                const productImg = document.querySelector(`[data-product-id="${productId}"] .product-image img`);
                if (productImg) {
                    productImg.src = `../../../assets/images/produtos/${variationImage}`;
                    productImg.alt = `Variação do produto ${productId}`;
                    console.log('Imagem trocada para variação:', variationImage);
                    
                    // Feedback visual de que a imagem mudou
                    productImg.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        productImg.style.transform = 'scale(1)';
                    }, 150);
                }
            }
        }

        // O dashboard.js já cuida do tema - sem duplicação
        
        // === NOTIFICAÇÕES TOAST MODERNAS ===
        function createToast(message, type = 'info', duration = 4000) {
            const container = document.getElementById('toastContainer') || createToastContainer();
            
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            const icons = {
                success: 'check_circle',
                error: 'error',
                warning: 'warning',
                info: 'info'
            };
            
            toast.innerHTML = `
                <span class="material-symbols-sharp toast-icon">${icons[type]}</span>
                <div class="toast-message">${message}</div>
                <button class="toast-close">
                    <span class="material-symbols-sharp">close</span>
                </button>
            `;
            
            // Funcionalidade do botão fechar
            toast.querySelector('.toast-close').addEventListener('click', () => {
                hideToast(toast);
            });
            
            container.appendChild(toast);
            
            // Show toast with animation
            setTimeout(() => toast.classList.add('show'), 100);
            
            // Auto remove after duration
            if (duration > 0) {
                setTimeout(() => hideToast(toast), duration);
            }
            
            return toast;
        }
        
        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
            return container;
        }
        
        function hideToast(toast) {
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 400);
        }
        
        // Replace all alert() calls with modern toasts
        window.alert = function(message) {
            createToast(message, 'info');
        };
        
        // Funções utilitárias para diferentes tipos de toast
        window.showSuccess = function(message) {
            createToast(message, 'success');
        };
        
        window.showError = function(message) {
            createToast(message, 'error');
        };
        
        window.showWarning = function(message) {
            createToast(message, 'warning');
        };
        
        window.showInfo = function(message) {
            createToast(message, 'info');
        };

        // ===== FUNCIONALIDADES DE SELEÇÃO EM MASSA =====
        
        let selectedProducts = new Set();

        // Toggle de seleção de todos os produtos
        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const productCheckboxes = document.querySelectorAll('.product-checkbox');
            
            if (selectAllCheckbox.checked) {
                productCheckboxes.forEach(checkbox => {
                    checkbox.checked = true;
                    selectedProducts.add(checkbox.value);
                });
            } else {
                productCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                selectedProducts.clear();
            }
            
            updateSelection();
        }

        // Atualizar seleção quando checkbox individual é alterado
        function updateSelection() {
            const productCheckboxes = document.querySelectorAll('.product-checkbox');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            
            selectedProducts.clear();
            
            let checkedCount = 0;
            productCheckboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    selectedProducts.add(checkbox.value);
                    checkedCount++;
                }
            });
            
            // Atualizar estado do "Selecionar Todos"
            if (checkedCount === 0) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = false;
            } else if (checkedCount === productCheckboxes.length) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = true;
            } else {
                selectAllCheckbox.indeterminate = true;
            }
            
            // Mostrar/ocultar barra de ações
            const bulkActionBar = document.getElementById('bulkActionBar');
            const selectedCountDisplay = document.getElementById('bulkSelectedCount');
            
            if (selectedProducts.size > 0) {
                selectedCountDisplay.textContent = selectedProducts.size;
                bulkActionBar.classList.add('show');
            } else {
                bulkActionBar.classList.remove('show');
            }
        }

        // Limpar todas as seleções
        function clearAllSelections() {
            selectedProducts.clear();
            document.querySelectorAll('.product-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAllCheckbox').checked = false;
            document.getElementById('selectAllCheckbox').indeterminate = false;
            document.getElementById('bulkActionBar').classList.remove('show');
        }

        // ===== MODAL DE ESTOQUE EM MASSA =====
        
        function openBulkStockModal() {
            if (selectedProducts.size === 0) {
                showError('Nenhum produto selecionado');
                return;
            }
            
            document.getElementById('bulkStockModal').classList.add('show');
            updateStockPreview();
        }

        function closeBulkStockModal() {
            document.getElementById('bulkStockModal').classList.remove('show');
            document.getElementById('stockValue').value = 1;
            document.querySelector('input[name="stockOperation"][value="add"]').checked = true;
        }

        function updateStockPreview() {
            const operation = document.querySelector('input[name="stockOperation"]:checked').value;
            const value = document.getElementById('stockValue').value || 0;
            const preview = document.getElementById('stockPreview');
            
            let operationText = '';
            switch(operation) {
                case 'add': operationText = `Somar ${value} unidades`; break;
                case 'subtract': operationText = `Subtrair ${value} unidades`; break;
                case 'set': operationText = `Definir estoque como ${value} unidades`; break;
            }
            
            preview.innerHTML = `
                <h4>Preview da Operação</h4>
                <p><strong>Ação:</strong> ${operationText}</p>
                <p><strong>Produtos afetados:</strong> ${selectedProducts.size}</p>
            `;
        }

        function executeBulkStockUpdate() {
            if (selectedProducts.size === 0) {
                showError('Nenhum produto selecionado');
                return;
            }
            
            const operation = document.querySelector('input[name="stockOperation"]:checked').value;
            const value = parseInt(document.getElementById('stockValue').value) || 0;
            
            if (value < 0) {
                showError('Valor deve ser maior ou igual a zero');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'bulk_stock_update');
            formData.append('operation', operation);
            formData.append('value', value);
            
            selectedProducts.forEach(productId => {
                formData.append('product_ids[]', productId);
            });
            
            fetch('products.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(data.message);
                    closeBulkStockModal();
                    clearAllSelections();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showError(data.message || 'Erro ao atualizar estoque');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showError('Erro de conexão');
            });
        }

        // ===== EXPORTAÇÃO =====
        
        function exportSelectedProducts() {
            if (selectedProducts.size === 0) {
                showError('Nenhum produto selecionado');
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'products.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'export_selected';
            form.appendChild(actionInput);
            
            selectedProducts.forEach(productId => {
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'product_ids[]';
                idInput.value = productId;
                form.appendChild(idInput);
            });
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
            
            showInfo(`Exportando ${selectedProducts.size} produtos...`);
        }

        function exportAllProducts() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'products.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'export_all';
            form.appendChild(actionInput);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
            
            showInfo('Exportando todos os produtos...');
        }

        // ===== EXCLUSÃO EM MASSA =====
        
        function confirmBulkDelete() {
            if (selectedProducts.size === 0) {
                showError('Nenhum produto selecionado');
                return;
            }
            
            const message = `Tem certeza que deseja excluir ${selectedProducts.size} produto(s) selecionado(s)?\\n\\nEsta ação não pode ser desfeita.`;
            
            if (confirm(message)) {
                executeBulkDelete();
            }
        }

        function executeBulkDelete() {
            const formData = new FormData();
            formData.append('action', 'bulk_delete');
            
            selectedProducts.forEach(productId => {
                formData.append('product_ids[]', productId);
            });
            
            fetch('products.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(data.message);
                    clearAllSelections();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showError(data.message || 'Erro ao excluir produtos');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showError('Erro de conexão');
            });
        }

        // ===== IMPORTAÇÃO =====
        
        function openImportModal() {
            document.getElementById('importModal').classList.add('show');
        }

        function closeImportModal() {
            document.getElementById('importModal').classList.remove('show');
            document.getElementById('importFile').value = '';
            document.getElementById('importButton').disabled = true;
        }

        function processImport() {
            const fileInput = document.getElementById('importFile');
            if (!fileInput.files[0]) {
                showError('Selecione um arquivo para importar');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'import_products');
            formData.append('import_file', fileInput.files[0]);
            
            const importButton = document.getElementById('importButton');
            const originalText = importButton.innerHTML;
            importButton.innerHTML = '<span class="material-symbols-sharp">sync</span> Importando...';
            importButton.disabled = true;
            
            fetch('products.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(data.message);
                    if (data.errors && data.errors.length > 0) {
                        console.log('Erros de importação:', data.errors);
                    }
                    closeImportModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showError(data.message || 'Erro na importação');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showError('Erro de conexão');
            })
            .finally(() => {
                importButton.innerHTML = originalText;
                importButton.disabled = false;
            });
        }

        // ===== EVENT LISTENERS =====
        
        // Arquivo de importação selecionado
        document.addEventListener('DOMContentLoaded', function() {
            const importFile = document.getElementById('importFile');
            const importButton = document.getElementById('importButton');
            
            if (importFile) {
                importFile.addEventListener('change', function() {
                    importButton.disabled = !this.files[0];
                });
            }
            
            // Listeners para atualizar preview do estoque
            const stockRadios = document.querySelectorAll('input[name="stockOperation"]');
            const stockInput = document.getElementById('stockValue');
            
            stockRadios.forEach(radio => {
                radio.addEventListener('change', updateStockPreview);
            });
            
            if (stockInput) {
                stockInput.addEventListener('input', updateStockPreview);
            }
            
            // Fechar modais clicando fora
            document.querySelectorAll('.modal-overlay').forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.classList.remove('show');
                    }
                });
            });
        });
    </script>

    <!-- Custom Delete Modal -->
    <div class="custom-modal-overlay" id="deleteModalOverlay">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <div class="modal-icon-wrapper">
                    <span class="material-symbols-sharp modal-icon">remove</span>
                </div>
                <div class="modal-title-wrapper">
                    <h3 class="modal-title">Confirmar Exclusão</h3>
                    <p class="modal-subtitle">Esta ação não pode ser desfeita</p>
                </div>
                <button class="modal-close-btn" id="modalCloseBtn">
                    <span class="material-symbols-sharp">close</span>
                </button>
            </div>
            <div class="custom-modal-body">
                <p class="modal-message">Tem certeza que deseja excluir o produto <strong id="productName"></strong>?</p>
                <div class="modal-warning">
                    <span class="material-symbols-sharp warning-icon">warning</span>
                    <p>Todos os dados do produto, incluindo variações e imagens, serão removidos permanentemente.</p>
                </div>
            </div>
            <div class="custom-modal-footer">
                <button class="btn-cancel" id="cancelBtn">Cancelar</button>
                <button class="btn-delete" id="confirmDeleteBtn">
                    <span class="material-symbols-sharp">remove</span>
                    Excluir Produto
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>
    
    <style>
        /* === ESTILOS DO MODAL CUSTOMIZADO === */
        .custom-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .custom-modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        
        .custom-modal {
            background: var(--color-white);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 480px;
            width: 90%;
            max-height: 90vh;
            overflow: hidden;
            transform: scale(0.7) translateY(-50px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .custom-modal-overlay.show .custom-modal {
            transform: scale(1) translateY(0);
        }
        
        .custom-modal-header {
            display: flex;
            align-items: flex-start;
            padding: 24px;
            border-bottom: 1px solid var(--color-light);
        }
        
        .modal-icon-wrapper {
            background: rgba(244, 67, 54, 0.1);
            border-radius: 12px;
            padding: 12px;
            margin-right: 16px;
        }
        
        .modal-icon {
            color: #f44336;
            font-size: 24px;
        }
        
        .modal-title-wrapper {
            flex: 1;
        }
        
        .modal-title {
            margin: 0 0 4px 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--color-dark);
        }
        
        .modal-subtitle {
            margin: 0;
            font-size: 14px;
            color: var(--color-info-dark);
        }
        
        .modal-close-btn {
            background: none;
            border: none;
            color: var(--color-info-dark);
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .modal-close-btn:hover {
            background: var(--color-light);
            color: var(--color-dark);
        }
        
        .custom-modal-body {
            padding: 0 24px 24px 24px;
        }
        
        .modal-message {
            margin: 0 0 16px 0;
            color: var(--color-dark-variant);
            line-height: 1.5;
        }
        
        .modal-warning {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 8px;
            padding: 12px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        
        .warning-icon {
            color: #FFC107;
            font-size: 18px;
            margin-top: 1px;
        }
        
        .modal-warning p {
            margin: 0;
            font-size: 13px;
            color: var(--color-dark-variant);
            line-height: 1.4;
        }
        
        .custom-modal-footer {
            display: flex;
            gap: 12px;
            padding: 0 24px 24px 24px;
        }
        
        .btn-cancel, .btn-delete {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            border: none;
        }
        
        .btn-cancel {
            background: var(--color-light);
            color: var(--color-dark-variant);
            flex: 1;
        }
        
        .btn-cancel:hover {
            background: var(--color-info-light);
        }
        
        .btn-delete {
            background: #f44336;
            color: white;
            flex: 1;
        }
        
        .btn-delete:hover {
            background: #d32f2f;
        }
        
        .btn-delete:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .loading-spinner {
            width: 12px;
            height: 12px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 6px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Dark mode compatibility */
        body.dark-theme-variables .custom-modal {
            background: var(--color-white);
        }
        
        body.dark-theme-variables .custom-modal-header {
            border-bottom-color: var(--color-light);
        }
        
        body.dark-theme-variables .modal-title {
            color: var(--color-dark);
        }
        
        body.dark-theme-variables .modal-message {
            color: var(--color-dark-variant);
        }
    </style>
</body>
</html>