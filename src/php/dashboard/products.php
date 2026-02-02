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
        
        // Atualizar produto pai
        $update_query = "UPDATE produtos SET estoque = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conexao, $update_query);
        mysqli_stmt_bind_param($update_stmt, "ii", $estoque_total, $id);
        mysqli_stmt_execute($update_stmt);
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
    echo "</div>";
}

// Forçar execução sem prepared statement para debug
$products = mysqli_query($conexao, $sql);

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
            position: absolute;
            top: 50%;
            right: 16px;
            transform: translateY(-50%);
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 10;
        }

        .action-btn {
            width: 30px;
            height: 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: all 0.2s ease;
            background: rgba(255, 255, 255, 0.8);
            color: #6b7280;
            backdrop-filter: blur(4px);
        }

        .action-btn:hover {
            transform: scale(1.1);
            color: white;
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
                    <h1>Produtos (<?php echo $total_products; ?>)</h1>
                    <a href="addproducts.php" class="add-product-btn">
                        <span class="material-symbols-sharp">add</span>
                        Adicionar Produto
                    </a>
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
                <?php 
                $has_products = false;
                mysqli_data_seek($products, 0); // Resetar ponteiro
                while ($product = mysqli_fetch_assoc($products)): 
                    $has_products = true;
                    
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