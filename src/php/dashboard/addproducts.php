<?php
session_start();
// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../../../PHP/login.php');
    exit();
}

// Incluir contador de mensagens e conexão
require_once 'helper-contador.php';
require_once '../../../PHP/conexao.php';

// Endpoint AJAX para buscar categorias
if (isset($_GET['action']) && $_GET['action'] == 'get_categories') {
    header('Content-Type: application/json');
    $search = $_GET['search'] ?? '';
    
    if ($search) {
        $sql = "SELECT nome FROM categorias WHERE nome LIKE ? ORDER BY nome LIMIT 10";
        $stmt = mysqli_prepare($conexao, $sql);
        $search_param = "%$search%";
        mysqli_stmt_bind_param($stmt, "s", $search_param);
    } else {
        $sql = "SELECT nome FROM categorias ORDER BY nome LIMIT 10";
        $stmt = mysqli_prepare($conexao, $sql);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $categories = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row['nome'];
    }
    
    echo json_encode($categories);
    exit;
}
// Verificar e criar tabela se necessário
$check_table = "SHOW TABLES LIKE 'produtos'";
$table_exists = mysqli_query($conexao, $check_table);

if (mysqli_num_rows($table_exists) == 0) {
    $create_table = "
    CREATE TABLE produtos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nome VARCHAR(255) NOT NULL,
        descricao TEXT,
        preco DECIMAL(10,2) NOT NULL,
        preco_promocional DECIMAL(10,2) NULL,
        categoria VARCHAR(100),
        subcategoria VARCHAR(100),
        marca VARCHAR(100),
        sku VARCHAR(50) UNIQUE,
        estoque INT DEFAULT 0,
        peso DECIMAL(8,3) NULL,
        dimensoes VARCHAR(100) NULL,
        imagens TEXT NULL,
        status ENUM('ativo', 'inativo', 'rascunho') DEFAULT 'ativo',
        destaque BOOLEAN DEFAULT FALSE,
        tags TEXT NULL,
        seo_title VARCHAR(255) NULL,
        seo_description TEXT NULL,
        video_url VARCHAR(500) NULL,
        garantia VARCHAR(100) NULL,
        origem VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    mysqli_query($conexao, $create_table);
} else {
    // Verificar e adicionar colunas se não existirem
    $columns_to_add = [
        'categoria' => 'ALTER TABLE produtos ADD COLUMN categoria VARCHAR(100)',
        'subcategoria' => 'ALTER TABLE produtos ADD COLUMN subcategoria VARCHAR(100)',
        'marca' => 'ALTER TABLE produtos ADD COLUMN marca VARCHAR(100)',
        'video_url' => 'ALTER TABLE produtos ADD COLUMN video_url VARCHAR(500)',
        'garantia' => 'ALTER TABLE produtos ADD COLUMN garantia VARCHAR(100)',
        'origem' => 'ALTER TABLE produtos ADD COLUMN origem VARCHAR(100)',
        'sku' => 'ALTER TABLE produtos ADD COLUMN sku VARCHAR(50) UNIQUE',
        'peso' => 'ALTER TABLE produtos ADD COLUMN peso DECIMAL(8,3)',
        'dimensoes' => 'ALTER TABLE produtos ADD COLUMN dimensoes VARCHAR(100)',
        'status' => "ALTER TABLE produtos ADD COLUMN status ENUM('ativo', 'inativo', 'rascunho') DEFAULT 'ativo'",
        'destaque' => 'ALTER TABLE produtos ADD COLUMN destaque BOOLEAN DEFAULT FALSE',
        'tags' => 'ALTER TABLE produtos ADD COLUMN tags TEXT',
        'seo_title' => 'ALTER TABLE produtos ADD COLUMN seo_title VARCHAR(255)',
        'seo_description' => 'ALTER TABLE produtos ADD COLUMN seo_description TEXT'
    ];
    
    foreach ($columns_to_add as $column => $sql) {
        $check_column = "SHOW COLUMNS FROM produtos LIKE '$column'";
        $column_exists = mysqli_query($conexao, $check_column);
        if (mysqli_num_rows($column_exists) == 0) {
            mysqli_query($conexao, $sql);
        }
    }
}

// Criar tabela de variações
$check_variations = "SHOW TABLES LIKE 'produto_variacoes'";
$variations_exists = mysqli_query($conexao, $check_variations);

if (mysqli_num_rows($variations_exists) == 0) {
    $create_variations = "
    CREATE TABLE produto_variacoes (
        id INT PRIMARY KEY AUTO_INCREMENT,
        produto_id INT NOT NULL,
        tipo VARCHAR(50) NOT NULL,
        valor VARCHAR(100) NOT NULL,
        preco_adicional DECIMAL(10,2) DEFAULT 0,
        estoque INT DEFAULT 0,
        sku_variacao VARCHAR(100) NULL,
        ativo BOOLEAN DEFAULT TRUE,
        FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
    )";
    mysqli_query($conexao, $create_variations);
}

// Verificar se é edição
$editing = isset($_GET['edit']) && is_numeric($_GET['edit']);
$produto_id = $editing ? (int)$_GET['edit'] : 0;
$produto = null;
$variacoes = [];

if ($editing) {
    $sql = "SELECT * FROM produtos WHERE id = ?";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, "i", $produto_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $produto = mysqli_fetch_assoc($result);
    
    if (!$produto) {
        header('Location: products.php');
        exit();
    }
    
    // Carregar variações do produto
    $sql_vars = "SELECT * FROM produto_variacoes WHERE produto_id = ? ORDER BY id";
    $stmt_vars = mysqli_prepare($conexao, $sql_vars);
    mysqli_stmt_bind_param($stmt_vars, "i", $produto_id);
    mysqli_stmt_execute($stmt_vars);
    $variacoes_result = mysqli_stmt_get_result($stmt_vars);
    $variacoes = [];
    while ($row = mysqli_fetch_assoc($variacoes_result)) {
        $variacoes[] = $row;
    }
}

// Criar tabela de categorias se não existir
$check_categories_table = "SHOW TABLES LIKE 'categorias'";
$categories_table_exists = mysqli_query($conexao, $check_categories_table);

if (mysqli_num_rows($categories_table_exists) == 0) {
    $create_categories_table = "
    CREATE TABLE categorias (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nome VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    mysqli_query($conexao, $create_categories_table);
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);
    $preco = floatval($_POST['preco']);
    $preco_promocional = !empty($_POST['preco_promocional']) ? floatval($_POST['preco_promocional']) : null;
    $categoria = trim($_POST['categoria']);
    
    // Salvar categoria se não existir
    if (!empty($categoria)) {
        $check_cat = "SELECT id FROM categorias WHERE nome = ?";
        $stmt_check = mysqli_prepare($conexao, $check_cat);
        mysqli_stmt_bind_param($stmt_check, "s", $categoria);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        
        if (mysqli_num_rows($result_check) == 0) {
            $insert_cat = "INSERT INTO categorias (nome) VALUES (?)";
            $stmt_cat = mysqli_prepare($conexao, $insert_cat);
            mysqli_stmt_bind_param($stmt_cat, "s", $categoria);
            mysqli_stmt_execute($stmt_cat);
        }
    }
    $subcategoria = trim($_POST['subcategoria'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $sku = trim($_POST['sku']);
    $estoque = intval($_POST['estoque']);
    $peso = !empty($_POST['peso']) ? floatval($_POST['peso']) : null;
    $dimensoes = trim($_POST['dimensoes']);
    $status = $_POST['status'];
    $destaque = isset($_POST['destaque']) ? 1 : 0;
    $tags = trim($_POST['tags']);
    $seo_title = trim($_POST['seo_title']);
    $seo_description = trim($_POST['seo_description']);
    $video_url = trim($_POST['video_url'] ?? '');
    $garantia = trim($_POST['garantia'] ?? '');
    $origem = trim($_POST['origem'] ?? '');
    
    $errors = [];
    
    // Validações
    if (empty($nome)) $errors[] = "Nome é obrigatório";
    if ($preco <= 0) $errors[] = "Preço deve ser maior que zero";
    if ($estoque < 0) $errors[] = "Estoque não pode ser negativo";
    if ($preco_promocional && $preco_promocional >= $preco) {
        $errors[] = "Preço promocional deve ser menor que o preço normal";
    }
    
    // Verificar SKU único
    if ($sku) {
        $check_sku = "SELECT id FROM produtos WHERE sku = ? AND id != ?";
        $stmt_sku = mysqli_prepare($conexao, $check_sku);
        mysqli_stmt_bind_param($stmt_sku, "si", $sku, $produto_id);
        mysqli_stmt_execute($stmt_sku);
        if (mysqli_stmt_get_result($stmt_sku)->num_rows > 0) {
            $errors[] = "SKU já existe";
        }
    }
    
    // Processar imagens
    $imagens_array = ($produto && isset($produto['imagens'])) ? json_decode($produto['imagens'], true) ?? [] : [];
    
    // Processar imagens da galeria selecionadas
    if (!empty($_POST['gallery_images'])) {
        $gallery_images = json_decode($_POST['gallery_images'], true);
        if ($gallery_images && is_array($gallery_images)) {
            foreach ($gallery_images as $gallery_img) {
                // Extrair apenas o nome do arquivo do caminho
                if (isset($gallery_img['path'])) {
                    $filename = basename($gallery_img['path']);
                    if (!in_array($filename, $imagens_array)) {
                        $imagens_array[] = $filename;
                    }
                }
            }
        }
    }
    
    // Upload de novas imagens
    if (!empty($_FILES['imagens']['name'][0])) {
        $upload_dir = '../../../assets/images/produtos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        foreach ($_FILES['imagens']['name'] as $key => $filename) {
            if ($_FILES['imagens']['error'][$key] == 0) {
                $extensao = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($extensao, $extensoes_permitidas)) {
                    if ($_FILES['imagens']['size'][$key] <= 5000000) { // 5MB
                        $nome_arquivo = uniqid() . '.' . $extensao;
                        $caminho_arquivo = $upload_dir . $nome_arquivo;
                        
                        if (move_uploaded_file($_FILES['imagens']['tmp_name'][$key], $caminho_arquivo)) {
                            $imagens_array[] = $nome_arquivo;
                        }
                    } else {
                        $errors[] = "Imagem $filename muito grande (máx 5MB)";
                    }
                } else {
                    $errors[] = "Formato não permitido para $filename";
                }
            }
        }
    }
    
    // Remover imagens selecionadas
    if (!empty($_POST['remover_imagens'])) {
        foreach ($_POST['remover_imagens'] as $img_remover) {
            $key = array_search($img_remover, $imagens_array);
            if ($key !== false) {
                unset($imagens_array[$key]);
                // Remover arquivo físico
                $caminho_arquivo = '../../../assets/images/produtos/' . $img_remover;
                if (file_exists($caminho_arquivo)) {
                    unlink($caminho_arquivo);
                }
            }
        }
        $imagens_array = array_values($imagens_array); // Reindexar
    }
    
    $imagens_json = json_encode($imagens_array);
    
    if (empty($errors)) {
        // Verificar quais colunas existem na tabela antes de fazer o SQL
        $existing_columns = [];
        $check_structure = "SHOW COLUMNS FROM produtos";
        $structure_result = mysqli_query($conexao, $check_structure);
        while ($col = mysqli_fetch_assoc($structure_result)) {
            $existing_columns[] = $col['Field'];
        }
        
        // Campos obrigatórios que sempre devem existir
        $required_fields = ['nome', 'descricao', 'preco', 'imagens'];
        $update_fields = [];
        $insert_fields = [];
        $values_placeholders = [];
        $params = [];
        $types = '';
        
        // Construir SQL dinamicamente baseado nas colunas existentes
        $field_mapping = [
            'nome' => $nome,
            'descricao' => $descricao, 
            'preco' => $preco,
            'preco_promocional' => $preco_promocional,
            'categoria' => $categoria,
            'subcategoria' => $subcategoria,
            'marca' => $marca,
            'sku' => $sku,
            'estoque' => $estoque,
            'peso' => $peso,
            'dimensoes' => $dimensoes,
            'imagens' => $imagens_json,
            'status' => $status,
            'destaque' => $destaque,
            'tags' => $tags,
            'seo_title' => $seo_title,
            'seo_description' => $seo_description,
            'video_url' => $video_url,
            'garantia' => $garantia,
            'origem' => $origem
        ];
        
        foreach ($field_mapping as $field => $value) {
            if (in_array($field, $existing_columns)) {
                if ($editing) {
                    $update_fields[] = "$field = ?";
                } else {
                    $insert_fields[] = $field;
                    $values_placeholders[] = '?';
                }
                $params[] = $value;
                
                // Determinar tipo do parâmetro
                if (in_array($field, ['preco', 'preco_promocional', 'peso'])) {
                    $types .= 'd';
                } elseif (in_array($field, ['estoque', 'destaque'])) {
                    $types .= 'i';
                } else {
                    $types .= 's';
                }
            }
        }
        
        if ($editing) {
            $sql = "UPDATE produtos SET " . implode(', ', $update_fields) . " WHERE id = ?";
            $params[] = $produto_id;
            $types .= 'i';
        } else {
            $sql = "INSERT INTO produtos (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $values_placeholders) . ")";
        }
        
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        
        if (mysqli_stmt_execute($stmt)) {
            $produto_id = $editing ? $produto_id : mysqli_insert_id($conexao);
            
            // Processar variações
            if (isset($_POST['variations']) && is_array($_POST['variations'])) {
                // Verificar e criar tabela de variações
                $check_var_table = "SHOW TABLES LIKE 'produto_variacoes'";
                $var_table_exists = mysqli_query($conexao, $check_var_table);
                
                if (mysqli_num_rows($var_table_exists) == 0) {
                    $create_var_table = "
                    CREATE TABLE produto_variacoes (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        produto_id INT NOT NULL,
                        tipo VARCHAR(100),
                        valor VARCHAR(255),
                        sku VARCHAR(100),
                        preco_adicional DECIMAL(10,2) DEFAULT 0,
                        estoque INT DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )";
                    mysqli_query($conexao, $create_var_table);
                }
                
                // Verificar colunas existentes
                $var_columns_query = "SHOW COLUMNS FROM produto_variacoes";
                $var_columns_result = mysqli_query($conexao, $var_columns_query);
                $existing_var_columns = [];
                while ($col = mysqli_fetch_assoc($var_columns_result)) {
                    $existing_var_columns[] = $col['Field'];
                }
                
                // Adicionar colunas que faltam
                $required_var_columns = [
                    'tipo' => 'VARCHAR(100)',
                    'valor' => 'VARCHAR(255)',
                    'sku' => 'VARCHAR(100)',
                    'preco_adicional' => 'DECIMAL(10,2) DEFAULT 0',
                    'estoque' => 'INT DEFAULT 0'
                ];
                
                foreach ($required_var_columns as $column => $definition) {
                    if (!in_array($column, $existing_var_columns)) {
                        $add_var_column = "ALTER TABLE produto_variacoes ADD COLUMN $column $definition";
                        mysqli_query($conexao, $add_var_column);
                    }
                }
                
                // Remover constraint UNIQUE de sku_variacao se existir
                $remove_unique = "ALTER TABLE produto_variacoes DROP INDEX sku_variacao";
                mysqli_query($conexao, $remove_unique); // Ignora erro se não existir
                
                // Limpar variações existentes se editando
                if ($editing) {
                    $delete_vars = "DELETE FROM produto_variacoes WHERE produto_id = ?";
                    $stmt_delete = mysqli_prepare($conexao, $delete_vars);
                    mysqli_stmt_bind_param($stmt_delete, "i", $produto_id);
                    mysqli_stmt_execute($stmt_delete);
                }
                
                // Inserir novas variações
                $sql_var = "INSERT INTO produto_variacoes (produto_id, tipo, valor, sku, preco_adicional, estoque) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_var = mysqli_prepare($conexao, $sql_var);
                
                foreach ($_POST['variations'] as $key => $variation) {
                    // Verificar se é um índice numérico válido
                    if (is_numeric($key) && is_array($variation)) {
                        $tipo = trim($variation['tipo'] ?? '');
                        $valor = trim($variation['valor'] ?? '');
                        $sku_var = trim($variation['sku'] ?? '');
                        $preco_adicional = floatval($variation['preco_adicional'] ?? 0);
                        $estoque_var = intval($variation['estoque'] ?? 0);
                        
                        // Gerar SKU único se estiver vazio
                        if (empty($sku_var)) {
                            $sku_var = 'VAR-' . $produto_id . '-' . uniqid();
                        }
                        
                        // Só inserir se tipo e valor não estiverem vazios
                        if (!empty($tipo) && !empty($valor)) {
                            mysqli_stmt_bind_param($stmt_var, "isssdi", $produto_id, $tipo, $valor, $sku_var, $preco_adicional, $estoque_var);
                            mysqli_stmt_execute($stmt_var);
                        }
                    }
                }
            }
            
            $success = $editing ? "Produto atualizado com sucesso!" : "Produto criado com sucesso!";
            header("Location: products.php?success=" . urlencode($success));
            exit();
        } else {
            $errors[] = "Erro ao salvar produto: " . mysqli_error($conexao);
        }
    }
}

// Buscar categorias da nova tabela
$categorias_sql = "SELECT nome as categoria FROM categorias ORDER BY nome";
$categorias_result = mysqli_query($conexao, $categorias_sql);

// Se não há categorias na tabela, criar algumas padrão
if (mysqli_num_rows($categorias_result) == 0) {
    $default_categories = ['Eletrônicos', 'Roupas', 'Casa e Jardim', 'Livros', 'Esportes'];
    foreach ($default_categories as $cat) {
        $insert_default = "INSERT IGNORE INTO categorias (nome) VALUES (?)";
        $stmt_default = mysqli_prepare($conexao, $insert_default);
        mysqli_stmt_bind_param($stmt_default, "s", $cat);
        mysqli_stmt_execute($stmt_default);
    }
    // Buscar novamente após inserir
    $categorias_result = mysqli_query($conexao, $categorias_sql);
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../css/dashboard.css">

     <link
      href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp"
      rel="stylesheet"
    />

    <title><?php echo $editing ? 'Editar Produto' : 'Novo Produto'; ?> - D&Z Admin</title>
    <style>
      /* Estilos do formulário estilo Shopee */
      .form-container {
        max-width: 1200px;
        margin: 0 auto;
      }
      
      .form-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
      }
      
      .form-title {
        font-size: 1.8rem;
        font-weight: 600;
        color: var(--color-dark);
      }
      
      .form-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
      }
      
      .form-section {
        background: var(--color-white);
        border-radius: 12px;
        box-shadow: var(--box-shadow);
        padding: 2rem;
        margin-bottom: 1.5rem;
      }
      
      .section-title {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--color-dark);
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid var(--color-info-light);
      }
      
      .form-group {
        margin-bottom: 1.5rem;
      }
      
      .form-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--color-dark);
      }
      
      .required {
        color: var(--color-danger);
      }
      
      .form-input,
      .form-select,
      .form-textarea {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid var(--color-info-light);
        border-radius: 8px;
        font-size: 0.95rem;
        background: var(--color-background);
        transition: all 0.3s ease;
      }
      
      .form-input:focus,
      .form-select:focus,
      .form-textarea:focus {
        outline: none;
        border-color: var(--color-danger);
        background: var(--color-white);
        box-shadow: 0 0 0 3px rgba(255, 0, 212, 0.1);
      }
      
      .form-textarea {
        resize: vertical;
        min-height: 100px;
      }
      
      .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
      }
      
      .form-help {
        font-size: 0.85rem;
        color: var(--color-info-dark);
        margin-top: 0.25rem;
      }
      
      /* Upload de Imagens */
      .image-upload {
        border: 2px dashed var(--color-info-light);
        border-radius: 8px;
        padding: 2rem;
        text-align: center;
        background: var(--color-background);
        transition: all 0.3s ease;
        cursor: pointer;
      }
      
      .image-upload:hover {
        border-color: var(--color-danger);
        background: rgba(255, 0, 212, 0.05);
      }
      
      .image-upload.dragover {
        border-color: var(--color-danger);
        background: rgba(255, 0, 212, 0.1);
      }
      
      .upload-icon {
        font-size: 3rem;
        color: var(--color-info-dark);
        margin-bottom: 1rem;
      }
      
      .upload-text {
        color: var(--color-dark);
        font-weight: 500;
        margin-bottom: 0.5rem;
      }
      
      .upload-help {
        color: var(--color-info-dark);
        font-size: 0.9rem;
      }
      
      .file-input {
        display: none;
      }
      
      /* Abas de Imagens */
      .image-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1rem;
      }
      
      .tab-btn {
        padding: 0.75rem 1.5rem;
        border: 2px solid var(--color-light);
        background: white;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 500;
        transition: all 0.3s ease;
      }
      
      .tab-btn.active {
        background: var(--color-danger);
        color: white;
        border-color: var(--color-danger);
      }
      
      .tab-btn:hover:not(.active) {
        border-color: var(--color-danger);
        color: var(--color-danger);
      }
      
      .tab-content {
        display: none;
      }
      
      .tab-content.active {
        display: block;
      }
      
      /* Galeria Selector */
      .gallery-selector {
        max-height: 400px;
        overflow-y: auto;
        border: 2px dashed var(--color-light);
        border-radius: 8px;
        padding: 1rem;
      }
      
      .gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 1rem;
      }
      
      .gallery-item {
        position: relative;
        cursor: pointer;
        border-radius: 8px;
        overflow: hidden;
        transition: transform 0.3s ease;
      }
      
      .gallery-item:hover {
        transform: scale(1.05);
      }
      
      .gallery-item img {
        width: 100%;
        height: 100px;
        object-fit: cover;
      }
      
      .gallery-item.selected::after {
        content: '\2713';
        position: absolute;
        top: 5px;
        right: 5px;
        background: var(--color-success);
        color: white;
        width: 25px;
        height: 25px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
      }
      
      .gallery-item.selected {
        outline: 3px solid var(--color-success);
      }
      
      .gallery-loading {
        text-align: center;
        padding: 2rem;
        color: var(--color-info-dark);
      }

      /* Detalhes e Variações do Produto */
      .product-details-section {
        background: var(--color-white);
        border-radius: var(--card-border-radius);
        box-shadow: var(--box-shadow);
        padding: var(--card-padding);
        margin-bottom: 1.5rem;
      }

      .details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
      }

      .detail-card {
        background: var(--color-background);
        border-radius: var(--border-radius-2);
        padding: var(--padding-1);
        border-left: 4px solid var(--color-danger);
      }

      .detail-card h3 {
        color: var(--color-dark);
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }

      .detail-card .material-symbols-sharp {
        color: var(--color-danger);
        font-size: 1.2rem;
      }

      .detail-input {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--color-info-light);
        border-radius: var(--border-radius-1);
        background: var(--color-white);
        color: var(--color-dark);
        font-family: inherit;
      }

      .detail-input:focus {
        border-color: var(--color-danger);
        outline: none;
        box-shadow: 0 0 0 3px rgba(255, 0, 212, 0.1);
      }

      .detail-help {
        font-size: 0.8rem;
        color: var(--color-info-dark);
        margin-top: 0.3rem;
      }

      .variations-container {
        background: var(--color-white);
        border-radius: var(--card-border-radius);
        box-shadow: var(--box-shadow);
        padding: var(--card-padding);
      }

      .variations-help {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        background: var(--color-background);
        padding: var(--padding-1);
        border-radius: var(--border-radius-2);
        margin-bottom: 1.5rem;
        border-left: 4px solid var(--color-info-light);
      }

      .variations-help .material-symbols-sharp {
        color: var(--color-danger);
        margin-top: 0.1rem;
      }

      .variations-help p {
        margin: 0;
        color: var(--color-info-dark);
        font-size: 0.9rem;
        line-height: 1.4;
      }

      .variation-item {
        background: var(--color-background);
        border: 1px solid var(--color-info-light);
        border-radius: var(--border-radius-2);
        padding: var(--padding-1);
        margin-bottom: 1rem;
        position: relative;
        transition: all 0.3s ease;
      }

      .variation-item:hover {
        border-color: var(--color-danger);
        box-shadow: 0 4px 12px rgba(255, 0, 212, 0.1);
      }

      .variation-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--color-info-light);
      }

      .variation-title {
        font-weight: 600;
        color: var(--color-dark);
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }

      .variation-title .material-symbols-sharp {
        color: var(--color-danger);
      }

      .btn-remove-variation {
        background: var(--color-danger);
        color: var(--color-white);
        border: none;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
      }

      .btn-remove-variation:hover {
        background: #ff1a8a;
        transform: scale(1.1);
      }

      .btn-add-variation {
        background: var(--color-success);
        color: var(--color-dark);
        border: none;
        border-radius: var(--border-radius-2);
        padding: 0.75rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
        width: 100%;
        justify-content: center;
        font-family: inherit;
      }

      .btn-add-variation:hover {
        background: #2dd4a6;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(65, 241, 182, 0.3);
      }

      .variation-fields {
        display: grid;
        grid-template-columns: 2fr 2fr 1fr 1fr;
        gap: 1rem;
      }

      .variation-fields .form-input {
        padding: 0.75rem;
        border: 1px solid var(--color-info-light);
        border-radius: var(--border-radius-1);
        background: var(--color-white);
        color: var(--color-dark);
        font-family: inherit;
        transition: all 0.3s ease;
      }

      .variation-fields .form-input:focus {
        border-color: var(--color-danger);
        outline: none;
        box-shadow: 0 0 0 3px rgba(255, 0, 212, 0.1);
      }

      .variation-fields .form-label {
        font-size: 0.9rem;
        font-weight: 500;
        color: var(--color-dark);
        margin-bottom: 0.5rem;
        display: block;
      }

      @media (max-width: 768px) {
        .variation-fields {
          grid-template-columns: 1fr;
        }
      }
      
      /* Preview de Imagens */
      .images-preview {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
      }
      
      .image-preview {
        position: relative;
        aspect-ratio: 1;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid var(--color-info-light);
      }
      
      .image-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }
      
      .remove-image {
        position: absolute;
        top: 4px;
        right: 4px;
        background: var(--color-danger);
        color: white;
        border: none;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 0.8rem;
      }
      
      /* Checkbox personalizado */
      .checkbox-group {
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }
      
      .custom-checkbox {
        width: 18px;
        height: 18px;
        border: 2px solid var(--color-info-light);
        border-radius: 4px;
        background: var(--color-white);
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
      }
      
      .custom-checkbox.checked {
        background: var(--color-danger);
        border-color: var(--color-danger);
      }
      
      .custom-checkbox.checked::after {
        content: '✓';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: white;
        font-size: 0.8rem;
      }
      
      /* Botões de ação */
      .form-actions {
        grid-column: 1 / -1;
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        padding: 2rem;
        background: var(--color-white);
        border-radius: 12px;
        box-shadow: var(--box-shadow);
      }
      
      .btn {
        padding: 0.75rem 2rem;
        border: none;
        border-radius: 8px;
        font-size: 0.95rem;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
      }
      
      .btn-cancel {
        background: var(--color-light);
        color: var(--color-dark-variant);
      }
      
      .btn-cancel:hover {
        background: var(--color-info-light);
      }
      
      .btn-save {
        background: var(--color-danger);
        color: white;
      }
      
      .btn-save:hover {
        background: #e600b8;
        transform: translateY(-1px);
      }
      
      .btn-save:disabled {
        background: var(--color-info-light);
        cursor: not-allowed;
        transform: none;
      }
      
      /* Alertas */
      .alert {
        padding: 1rem 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
      }
      
      .alert-error {
        background: rgba(255, 0, 212, 0.1);
        border: 1px solid rgba(255, 0, 212, 0.3);
        color: var(--color-danger);
      }
      
      .alert ul {
        margin: 0;
        padding-left: 1rem;
      }
      
      /* Responsivo */
      @media (max-width: 768px) {
        .form-grid {
          grid-template-columns: 1fr;
        }
        
        .form-row {
          grid-template-columns: 1fr;
        }
        
        .form-actions {
          flex-direction: column;
        }
        
        .images-preview {
          grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        }
      }
    </style>
  </head>
  <body>
    
   <div class="container">
      <aside>
        <div class="top">
          <div class="logo">
            <img src="../../../assets/images/Logodz.png" />
                        <a href="index.php"><h2 class="danger">D&Z</h2></a>

          </div>

          <div class="close" id="close-btn">
            <span class="material-symbols-sharp">close</span>
          </div>
        </div>

        <div class="sidebar">
          <a href="index.php" class="panel">
            <span class="material-symbols-sharp">grid_view</span>
            <h3>Painel</h3>
          </a>

          <a href="customers.php" class="active">
            <span class="material-symbols-sharp">Groups</span>
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

          <a href="products.php">
            <span class="material-symbols-sharp">Inventory</span>
            <h3>Produtos</h3>
          </a>

          <a href="#">
            <span class="material-symbols-sharp">Report</span>
            <h3>Relatórios</h3>
          </a>

          <a href="settings.php">
            <span class="material-symbols-sharp">Settings</span>
            <h3>Configurações</h3>
          </a>

          <a href="addproducts.php">
            <span class="material-symbols-sharp">Add</span>
            <h3>Adicionar Produto</h3>
          </a>

          <a href="../../../PHP/logout.php">
            <span class="material-symbols-sharp">Logout</span>
            <h3>Sair</h3>
          </a>
        </div>
      </aside>

      <!----------FINAL ASIDE------------>
      <main>
        <div class="form-container">
          <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
              <span class="material-symbols-sharp">error</span>
              <div>
                <strong>Erro ao salvar produto:</strong>
                <ul>
                  <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          <?php endif; ?>

          <!-- Header -->
          <div class="form-header">
            <a href="products.php" style="color: var(--color-danger);">
              <span class="material-symbols-sharp">arrow_back</span>
            </a>
            <h1 class="form-title">
              <?php echo $editing ? 'Editar Produto' : 'Novo Produto'; ?>
            </h1>
          </div>

          <!-- Formulário -->
          <form method="POST" enctype="multipart/form-data" id="productForm">
            <div class="form-grid">
              
              <!-- Coluna Principal -->
              <div class="main-column">
                <!-- Informações Básicas -->
                <div class="form-section">
                  <h2 class="section-title">
                    <span class="material-symbols-sharp">info</span>
                    Informações Básicas
                  </h2>
                  
                  <div class="form-group">
                    <label class="form-label">
                      <span class="material-symbols-sharp">label</span>
                      Nome do Produto <span class="required">*</span>
                    </label>
                    <input type="text" name="nome" class="form-input" 
                           value="<?php echo htmlspecialchars(($produto['nome'] ?? '')); ?>" 
                           placeholder="Ex: Smartphone Samsung Galaxy..." required>
                    <div class="form-help">Nome completo e descritivo do produto</div>
                  </div>

                  <div class="form-group">
                    <label class="form-label">
                      <span class="material-symbols-sharp">description</span>
                      Descrição
                    </label>
                    <textarea name="descricao" class="form-textarea" 
                              placeholder="Descreva as características, benefícios e especificações do produto..."><?php echo htmlspecialchars(($produto['descricao'] ?? '')); ?></textarea>
                    <div class="form-help">Descrição detalhada que aparecerá na página do produto</div>
                  </div>

                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">
                        <span class="material-symbols-sharp">category</span>
                        Categoria <span class="required">*</span>
                      </label>
                      <input type="text" name="categoria" class="form-input" 
                             value="<?php echo htmlspecialchars(($produto['categoria'] ?? '')); ?>" 
                             placeholder="Ex: Eletrônicos" list="categorias" required>
                      <datalist id="categorias">
                        <?php while ($cat = mysqli_fetch_assoc($categorias_result)): ?>
                          <option value="<?php echo htmlspecialchars($cat['categoria']); ?>">
                        <?php endwhile; ?>
                      </datalist>
                    </div>

                    <div class="form-group">
                      <label class="form-label">
                        <span class="material-symbols-sharp">subdirectory_arrow_right</span>
                        Subcategoria
                      </label>
                      <input type="text" name="subcategoria" class="form-input" 
                             value="<?php echo htmlspecialchars(($produto['subcategoria'] ?? '')); ?>" 
                             placeholder="Ex: Smartphones">
                    </div>
                  </div>

                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">
                        <span class="material-symbols-sharp">branding_watermark</span>
                        Marca
                      </label>
                      <input type="text" name="marca" class="form-input" 
                             value="<?php echo htmlspecialchars(($produto['marca'] ?? '')); ?>" 
                             placeholder="Ex: Samsung, Apple, Nike...">
                    </div>

                    <div class="form-group">
                      <label class="form-label">
                        <span class="material-symbols-sharp">qr_code</span>
                        SKU (Código)
                      </label>
                      <input type="text" name="sku" class="form-input" 
                             value="<?php echo htmlspecialchars(($produto['sku'] ?? '')); ?>" 
                             placeholder="Ex: SAMS-GALA-001">
                      <div class="form-help">Código único do produto (opcional)</div>
                    </div>
                  </div>
                </div>

                <!-- Preço e Estoque -->
                <div class="form-section">
                  <h2 class="section-title">
                    <span class="material-symbols-sharp">attach_money</span>
                    Preço e Estoque
                  </h2>

                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">
                        Preço Normal <span class="required">*</span>
                      </label>
                      <input type="number" name="preco" class="form-input" step="0.01" min="0" 
                             value="<?php echo $produto['preco'] ?? ''; ?>" 
                             placeholder="0,00" required>
                    </div>

                    <div class="form-group">
                      <label class="form-label">
                        Preço Promocional
                      </label>
                      <input type="number" name="preco_promocional" class="form-input" step="0.01" min="0" 
                             value="<?php echo ($produto['preco_promocional'] ?? ''); ?>" 
                             placeholder="0,00">
                      <div class="form-help">Deixe vazio se não houver promoção</div>
                    </div>
                  </div>

                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">
                        <span class="material-symbols-sharp">inventory</span>
                        Quantidade em Estoque <span class="required">*</span>
                      </label>
                      <input type="number" name="estoque" class="form-input" min="0" 
                             value="<?php echo $produto['estoque'] ?? '0'; ?>" required>
                    </div>

                    <div class="form-group">
                      <label class="form-label">
                        <span class="material-symbols-sharp">scale</span>
                        Peso (kg)
                      </label>
                      <input type="number" name="peso" class="form-input" step="0.001" min="0" 
                             value="<?php echo $produto['peso'] ?? ''; ?>" 
                             placeholder="0,000">
                    </div>
                  </div>

                  <div class="form-group">
                    <label class="form-label">
                      <span class="material-symbols-sharp">straighten</span>
                      Dimensões (C x L x A)
                    </label>
                    <input type="text" name="dimensoes" class="form-input" 
                           value="<?php echo htmlspecialchars($produto['dimensoes'] ?? ''); ?>" 
                           placeholder="Ex: 15 x 10 x 5 cm">
                  </div>
                </div>

                <!-- Imagens -->
                <div class="form-section">
                  <h2 class="section-title">
                    <span class="material-symbols-sharp">image</span>
                    Imagens do Produto
                  </h2>

                  <div class="image-tabs">
                    <button type="button" class="tab-btn active" onclick="switchImageTab('upload')">
                      <span class="material-symbols-sharp">upload</span>
                      Upload Novo
                    </button>
                    <button type="button" class="tab-btn" onclick="switchImageTab('gallery')">
                      <span class="material-symbols-sharp">photo_library</span>
                      Da Galeria
                    </button>
                  </div>
                  
                  <!-- Upload Tab -->
                  <div id="uploadTab" class="tab-content active">
                    <div class="image-upload" onclick="document.getElementById('imagens').click()">
                      <div class="upload-icon">
                        <span class="material-symbols-sharp">cloud_upload</span>
                      </div>
                      <div class="upload-text">Clique ou arraste imagens aqui</div>
                      <div class="upload-help">Suporta JPG, PNG, GIF, WebP até 5MB cada</div>
                      <input type="file" id="imagens" name="imagens[]" class="file-input" 
                             multiple accept="image/*" onchange="previewImages(this)">
                    </div>
                  </div>
                  
                  <!-- Gallery Tab -->
                  <div id="galleryTab" class="tab-content">
                    <div class="gallery-selector" id="gallerySelector">
                      <div class="gallery-loading">Carregando imagens da galeria...</div>
                    </div>
                  </div>
                  
                  <input type="hidden" id="selectedGalleryImages" name="gallery_images" value="">

                  <?php if ($produto && !empty($produto['imagens'])): ?>
                    <?php $imagens_existentes = json_decode($produto['imagens'], true) ?? []; ?>
                    <?php if (!empty($imagens_existentes)): ?>
                      <div class="images-preview" id="existingImages">
                        <h4 style="grid-column: 1 / -1; margin: 1rem 0 0.5rem 0;">Imagens atuais:</h4>
                        <?php foreach ($imagens_existentes as $img): ?>
                          <div class="image-preview">
                            <img src="../../../assets/images/produtos/<?php echo htmlspecialchars($img); ?>" alt="Produto">
                            <label class="remove-image" title="Remover imagem">
                              <input type="checkbox" name="remover_imagens[]" 
                                     value="<?php echo htmlspecialchars($img); ?>" 
                                     style="display: none;" onchange="toggleImageRemoval(this)">
                              <span class="material-symbols-sharp">close</span>
                            </label>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>

                  <div class="images-preview" id="newImagesPreview"></div>
                </div>

                <!-- Detalhes do Produto -->
                <div class="product-details-section">
                  <h2 class="section-title">
                    <span class="material-symbols-sharp">info</span>
                    Detalhes do Produto
                  </h2>

                  <div class="details-grid">
                    <div class="detail-card">
                      <h3>
                        <span class="material-symbols-sharp">verified</span>
                        Garantia
                      </h3>
                      <input type="text" name="garantia" class="detail-input" 
                             value="<?php echo htmlspecialchars($produto['garantia'] ?? ''); ?>" 
                             placeholder="Ex: 12 meses, 2 anos, Vitalícia" list="garantias">
                      <div class="detail-help">Período de garantia oferecido</div>
                    </div>

                    <div class="detail-card">
                      <h3>
                        <span class="material-symbols-sharp">public</span>
                        Origem
                      </h3>
                      <input type="text" name="origem" class="detail-input" 
                             value="<?php echo htmlspecialchars($produto['origem'] ?? ''); ?>" 
                             placeholder="Ex: Nacional, Importado, China, EUA" list="origens">
                      <div class="detail-help">Origem do produto</div>
                    </div>
                  </div>

                  <div class="detail-card">
                    <h3>
                      <span class="material-symbols-sharp">play_circle</span>
                      Vídeo do Produto
                    </h3>
                    <input type="url" name="video_url" class="detail-input" 
                           value="<?php echo htmlspecialchars($produto['video_url'] ?? ''); ?>" 
                           placeholder="https://youtube.com/watch?v=... ou https://vimeo.com/...">
                    <div class="detail-help">URL do vídeo demonstrativo (YouTube, Vimeo, etc.)</div>
                  </div>
                </div>

                <!-- Variações do Produto -->
                <div class="variations-container">
                  <h2 class="section-title">
                    <span class="material-symbols-sharp">tune</span>
                    Variações do Produto
                  </h2>
                  
                  <div class="variations-help">
                    <span class="material-symbols-sharp">lightbulb</span>
                    <p>Adicione variações como tamanhos, cores, modelos, etc. Cada variação pode ter preço e estoque próprios.</p>
                  </div>
                  
                  <div id="variationsContainer">
                    <!-- Variações serão adicionadas aqui via JavaScript -->
                  </div>
                  
                  <button type="button" class="btn-add-variation" onclick="addVariation()">
                    <span class="material-symbols-sharp">add</span>
                    Adicionar Variação
                  </button>
                </div>
              </div>

              <!-- Coluna Lateral -->
              <div class="side-column">
                <!-- Status e Configurações -->
                <div class="form-section">
                  <h2 class="section-title">
                    <span class="material-symbols-sharp">settings</span>
                    Status e Configurações
                  </h2>

                  <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                      <option value="ativo" <?php echo ($produto['status'] ?? 'ativo') == 'ativo' ? 'selected' : ''; ?>>
                        ✅ Ativo (visível na loja)
                      </option>
                      <option value="inativo" <?php echo ($produto['status'] ?? '') == 'inativo' ? 'selected' : ''; ?>>
                        ❌ Inativo (oculto na loja)
                      </option>
                      <option value="rascunho" <?php echo ($produto['status'] ?? '') == 'rascunho' ? 'selected' : ''; ?>>
                        📝 Rascunho (em edição)
                      </option>
                    </select>
                  </div>

                  <div class="form-group">
                    <div class="checkbox-group">
                      <input type="checkbox" name="destaque" value="1" id="destaque" 
                             <?php echo ($produto['destaque'] ?? 0) ? 'checked' : ''; ?>>
                      <div class="custom-checkbox" onclick="toggleCheckbox('destaque')"></div>
                      <label for="destaque" class="form-label" style="margin: 0; cursor: pointer;">
                        <span class="material-symbols-sharp">star</span>
                        Produto em Destaque
                      </label>
                    </div>
                  </div>
                </div>

                <!-- Tags -->
                <div class="form-section">
                  <h2 class="section-title">
                    <span class="material-symbols-sharp">tag</span>
                    Tags
                  </h2>

                  <div class="form-group">
                    <textarea name="tags" class="form-textarea" 
                              placeholder="smartphone, android, samsung, celular"><?php echo htmlspecialchars($produto['tags'] ?? ''); ?></textarea>
                    <div class="form-help">Separe as tags por vírgulas</div>
                  </div>
                </div>

                <!-- SEO -->
                <div class="form-section">
                  <h2 class="section-title">
                    <span class="material-symbols-sharp">search</span>
                    SEO
                  </h2>

                  <div class="form-group">
                    <label class="form-label">Título SEO</label>
                    <input type="text" name="seo_title" class="form-input" 
                           value="<?php echo htmlspecialchars($produto['seo_title'] ?? ''); ?>" 
                           placeholder="Título para mecanismos de busca" maxlength="60">
                    <div class="form-help">Máximo 60 caracteres</div>
                  </div>

                  <div class="form-group">
                    <label class="form-label">Descrição SEO</label>
                    <textarea name="seo_description" class="form-textarea" 
                              placeholder="Descrição para mecanismos de busca" maxlength="160"><?php echo htmlspecialchars($produto['seo_description'] ?? ''); ?></textarea>
                    <div class="form-help">Máximo 160 caracteres</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Botões de Ação -->
            <div class="form-actions">
              <a href="products.php" class="btn btn-cancel">
                <span class="material-symbols-sharp">close</span>
                Cancelar
              </a>
              <button type="submit" class="btn btn-save" id="saveBtn">
                <span class="material-symbols-sharp">save</span>
                <?php echo $editing ? 'Atualizar Produto' : 'Salvar Produto'; ?>
              </button>
            </div>
          </form>
        </div>
      </main>

      <div class="right">
        <div class="top">
          <button id="menu-btn">
            <span class="material-symbols-sharp"> menu </span>
          </button>
          <div class="theme-toggler">
            <span class="material-symbols-sharp active"> wb_sunny </span
            ><span class="material-symbols-sharp"> bedtime </span>
          </div>
          <div class="profile">
            <div class="info">
              <p>Olá, <b><?= isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : 'Usuário'; ?></b></p>
              <small class="text-muted">Admin</small>
            </div>
            <div class="profile-photo">
              <img src="../../../assets/images/logo.png" alt="" />
            </div>
          </div>
        </div>
        <!------------------------FINAL TOP----------------------->


        



    
<script src="../../js/dashboard.js"></script>
<script>
let selectedGalleryImages = [];

// Função para trocar abas
function switchImageTab(tab) {
  // Remover classe active de todas as abas
  document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
  
  // Ativar aba selecionada
  event.target.classList.add('active');
  document.getElementById(tab + 'Tab').classList.add('active');
  
  // Carregar galeria se necessário
  if (tab === 'gallery') {
    loadGalleryImages();
  }
}

// Carregar imagens da galeria
async function loadGalleryImages() {
  const selector = document.getElementById('gallerySelector');
  try {
    const response = await fetch('gallery-api.php');
    const data = await response.json();
    
    if (data.success) {
      let html = '<div class="gallery-grid">';
      data.images.forEach(img => {
        html += `
          <div class="gallery-item" onclick="toggleGalleryImage('${img.nome_arquivo}', '${img.caminho}')" data-filename="${img.nome_arquivo}">
            <img src="../../../${img.caminho}" alt="${img.nome}">
          </div>
        `;
      });
      html += '</div>';
      selector.innerHTML = html;
    } else {
      selector.innerHTML = '<div class="gallery-loading">Erro ao carregar imagens</div>';
    }
  } catch (error) {
    selector.innerHTML = '<div class="gallery-loading">Nenhuma imagem encontrada na galeria</div>';
  }
}

// Alternar seleção de imagem da galeria
function toggleGalleryImage(filename, path) {
  const item = document.querySelector(`[data-filename="${filename}"]`);
  const index = selectedGalleryImages.findIndex(img => img.filename === filename);
  
  if (index > -1) {
    // Remover seleção
    selectedGalleryImages.splice(index, 1);
    item.classList.remove('selected');
  } else {
    // Adicionar seleção
    selectedGalleryImages.push({filename, path});
    item.classList.add('selected');
  }
  
  // Atualizar campo hidden
  document.getElementById('selectedGalleryImages').value = JSON.stringify(selectedGalleryImages);
  
  // Atualizar preview
  updateGalleryPreview();
}

// Atualizar preview das imagens da galeria
function updateGalleryPreview() {
  const preview = document.getElementById('imagePreview');
  let previewHtml = '';
  
  if (selectedGalleryImages.length > 0) {
    previewHtml = '<div class="images-preview">';
    selectedGalleryImages.forEach(img => {
      previewHtml += `
        <div class="image-preview">
          <img src="../../../${img.path}" alt="Selecionada">
          <div class="image-name">${img.filename}</div>
          <button type="button" class="remove-image" onclick="removeGalleryImage('${img.filename}')">
            <span class="material-symbols-sharp">close</span>
          </button>
        </div>
      `;
    });
    previewHtml += '</div>';
  }
  
  preview.innerHTML = previewHtml;
}

// Remover imagem da galeria selecionada
function removeGalleryImage(filename) {
  const index = selectedGalleryImages.findIndex(img => img.filename === filename);
  if (index > -1) {
    selectedGalleryImages.splice(index, 1);
    document.getElementById('selectedGalleryImages').value = JSON.stringify(selectedGalleryImages);
    
    // Atualizar UI
    const item = document.querySelector(`[data-filename="${filename}"]`);
    if (item) item.classList.remove('selected');
    
    updateGalleryPreview();
  }
}

// Gerenciar variações
let variationCounter = 0;

function addVariation() {
  variationCounter++;
  
  const container = document.getElementById('variationsContainer');
  
  // Criar elemento DOM ao invés de innerHTML para evitar problemas
  const variationDiv = document.createElement('div');
  variationDiv.className = 'variation-item';
  variationDiv.id = `variation_${variationCounter}`;
  
  variationDiv.innerHTML = `
    <div class="variation-header">
      <div class="variation-title">
        <span class="material-symbols-sharp">tune</span>
        Variação #${variationCounter}
      </div>
      <button type="button" class="btn-remove-variation" onclick="removeVariation(${variationCounter})" title="Remover variação">
        <span class="material-symbols-sharp">close</span>
      </button>
    </div>
    
    <div class="variation-fields">
      <div class="form-group">
        <label class="form-label">Tipo</label>
        <select name="variations[${variationCounter}][tipo]" class="form-input" required>
          <option value="">Selecione o tipo</option>
          <option value="cor">Cor</option>
          <option value="tamanho">Tamanho</option>
          <option value="modelo">Modelo</option>
          <option value="material">Material</option>
          <option value="voltagem">Voltagem</option>
          <option value="capacidade">Capacidade</option>
          <option value="outro">Outro</option>
        </select>
      </div>
      
      <div class="form-group">
        <label class="form-label">Valor</label>
        <input type="text" name="variations[${variationCounter}][valor]" class="form-input" 
               placeholder="Ex: Azul, M, 110V..." required>
      </div>
      
      <div class="form-group">
        <label class="form-label">SKU da Variação</label>
        <input type="text" name="variations[${variationCounter}][sku]" class="form-input" 
               placeholder="Ex: PROD-001-AZ" title="SKU específico desta variação">
      </div>
      
      <div class="form-group">
        <label class="form-label">Preço +/-</label>
        <input type="number" name="variations[${variationCounter}][preco_adicional]" 
               class="form-input" step="0.01" value="0" 
               placeholder="0.00" title="Valor adicional ou desconto">
      </div>
      
      <div class="form-group">
        <label class="form-label">Estoque</label>
        <input type="number" name="variations[${variationCounter}][estoque]" 
               class="form-input" min="0" value="0" placeholder="0">
      </div>
    </div>
  `;
  
  container.appendChild(variationDiv);
  console.log('Variação adicionada:', variationCounter);
}

function removeVariation(id) {
  const variation = document.getElementById(`variation_${id}`);
  if (variation) {
    variation.remove();
  }
}

// Auto-complete para campos
function setupAutocomplete() {
  // Categorias populares
  const categorias = ['Eletrônicos', 'Roupas', 'Casa e Jardim', 'Esportes', 'Livros', 'Beleza', 'Automóveis', 'Brinquedos'];
  
  // Marcas populares 
  const marcas = ['Samsung', 'Apple', 'Nike', 'Adidas', 'Sony', 'LG', 'Microsoft', 'Dell', 'HP'];
  
  // Garantias comuns
  const garantias = ['3 meses', '6 meses', '12 meses', '2 anos', '3 anos', 'Vitalícia', 'Sem garantia'];
  
  // Origens
  const origens = ['Nacional', 'Importado', 'China', 'EUA', 'Alemanha', 'Japão', 'Coreia do Sul'];
  
  // Adicionar datalists se não existirem
  addDatalist('marcas', marcas);
  addDatalist('garantias', garantias);  
  addDatalist('origens', origens);
  
  // Conectar aos inputs
  document.querySelector('input[name="marca"]').setAttribute('list', 'marcas');
  document.querySelector('input[name="garantia"]').setAttribute('list', 'garantias');
  document.querySelector('input[name="origem"]').setAttribute('list', 'origens');
}

function addDatalist(id, options) {
  if (!document.getElementById(id)) {
    const datalist = document.createElement('datalist');
    datalist.id = id;
    
    options.forEach(option => {
      const opt = document.createElement('option');
      opt.value = option;
      datalist.appendChild(opt);
    });
    
    document.body.appendChild(datalist);
  }
}

// Validação em tempo real
function setupValidation() {
  const precoNormal = document.querySelector('input[name="preco"]');
  const precoPromocional = document.querySelector('input[name="preco_promocional"]');
  
  if (precoPromocional) {
    precoPromocional.addEventListener('input', function() {
      const normal = parseFloat(precoNormal.value);
      const promocional = parseFloat(this.value);
      
      if (promocional && promocional >= normal) {
        this.setCustomValidity('Preço promocional deve ser menor que o preço normal');
        this.style.borderColor = 'var(--color-danger)';
      } else {
        this.setCustomValidity('');
        this.style.borderColor = '';
      }
    });
  }
}

// Inicializar funcionalidades ao carregar
document.addEventListener('DOMContentLoaded', function() {
  setupAutocomplete();
  setupValidation();
  
  // Debug do formulário
  const form = document.getElementById('productForm');
  if (form) {
    form.addEventListener('submit', function(e) {
      console.log('Form submetido');
      const variations = document.querySelectorAll('[name^="variations["]');
      console.log('Variações encontradas:', variations.length);
    });
  }
  
  // Carregar variações existentes se editando
  <?php if ($editing && isset($variacoes) && !empty($variacoes)): ?>
    setTimeout(function() {
      <?php foreach ($variacoes as $variacao): ?>
        loadExistingVariation(
          '<?php echo addslashes($variacao['tipo']); ?>',
          '<?php echo addslashes($variacao['valor']); ?>',
          '<?php echo addslashes($variacao['sku'] ?? ''); ?>',
          <?php echo floatval($variacao['preco_adicional']); ?>,
          <?php echo intval($variacao['estoque']); ?>
        );
      <?php endforeach; ?>
    }, 100);
  <?php endif; ?>
});

// Função para carregar variação existente
function loadExistingVariation(tipo, valor, sku, precoAdicional, estoque) {
  variationCounter++;
  
  const container = document.getElementById('variationsContainer');
  
  // Criar elemento DOM
  const variationDiv = document.createElement('div');
  variationDiv.className = 'variation-item';
  variationDiv.id = `variation_${variationCounter}`;
  
  variationDiv.innerHTML = `
    <div class="variation-header">
      <div class="variation-title">
        <span class="material-symbols-sharp">tune</span>
        Variação #${variationCounter}
      </div>
      <button type="button" class="btn-remove-variation" onclick="removeVariation(${variationCounter})" title="Remover variação">
        <span class="material-symbols-sharp">close</span>
      </button>
    </div>
    
    <div class="variation-fields">
      <div class="form-group">
        <label class="form-label">Tipo</label>
        <select name="variations[${variationCounter}][tipo]" class="form-input" required>
          <option value="">Selecione o tipo</option>
          <option value="cor" ${tipo === 'cor' ? 'selected' : ''}>Cor</option>
          <option value="tamanho" ${tipo === 'tamanho' ? 'selected' : ''}>Tamanho</option>
          <option value="modelo" ${tipo === 'modelo' ? 'selected' : ''}>Modelo</option>
          <option value="material" ${tipo === 'material' ? 'selected' : ''}>Material</option>
          <option value="voltagem" ${tipo === 'voltagem' ? 'selected' : ''}>Voltagem</option>
          <option value="capacidade" ${tipo === 'capacidade' ? 'selected' : ''}>Capacidade</option>
          <option value="outro" ${tipo === 'outro' ? 'selected' : ''}>Outro</option>
        </select>
      </div>
      
      <div class="form-group">
        <label class="form-label">Valor</label>
        <input type="text" name="variations[${variationCounter}][valor]" class="form-input" 
               placeholder="Ex: Azul, M, 110V..." value="${valor}" required>
      </div>
      
      <div class="form-group">
        <label class="form-label">SKU da Variação</label>
        <input type="text" name="variations[${variationCounter}][sku]" class="form-input" 
               placeholder="Ex: PROD-001-AZ" value="${sku || ''}" title="SKU específico desta variação">
      </div>
      
      <div class="form-group">
        <label class="form-label">Preço +/-</label>
        <input type="number" name="variations[${variationCounter}][preco_adicional]" 
               class="form-input" step="0.01" value="${precoAdicional}" 
               placeholder="0.00" title="Valor adicional ou desconto">
      </div>
      
      <div class="form-group">
        <label class="form-label">Estoque</label>
        <input type="number" name="variations[${variationCounter}][estoque]" 
               class="form-input" min="0" value="${estoque}" placeholder="0">
      </div>
    </div>
  `;
  
  container.appendChild(variationDiv);
  console.log('Variação existente carregada:', {tipo, valor, sku, precoAdicional, estoque});
}

// Preview de imagens
function previewImages(input) {
  const preview = document.getElementById('imagePreview');
  // Limpar seleção da galeria se upload for usado
  selectedGalleryImages = [];
  document.getElementById('selectedGalleryImages').value = '';
  preview.innerHTML = '';
  
  const newPreview = document.getElementById('newImagesPreview');
  
  if (input.files) {
    Array.from(input.files).forEach((file, index) => {
      if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
          const imageDiv = document.createElement('div');
          imageDiv.className = 'image-preview';
          
          imageDiv.innerHTML = `
            <img src="${e.target.result}" alt="Preview ${index + 1}">
            <button type="button" class="remove-image" onclick="removeNewImage(this)" title="Remover">
              <span class="material-symbols-sharp">close</span>
            </button>
          `;
          
          preview.appendChild(imageDiv);
        };
        
        reader.readAsDataURL(file);
      }
    });
  }
}

// Remover nova imagem do preview
function removeNewImage(button) {
  button.closest('.image-preview').remove();
}

// Toggle de remoção de imagens existentes
function toggleImageRemoval(checkbox) {
  const preview = checkbox.closest('.image-preview');
  if (checkbox.checked) {
    preview.style.opacity = '0.5';
    preview.style.filter = 'grayscale(100%)';
  } else {
    preview.style.opacity = '1';
    preview.style.filter = 'none';
  }
}

// Custom checkbox
function toggleCheckbox(id) {
  const checkbox = document.getElementById(id);
  const customCheckbox = checkbox.nextElementSibling;
  
  checkbox.checked = !checkbox.checked;
  
  if (checkbox.checked) {
    customCheckbox.classList.add('checked');
  } else {
    customCheckbox.classList.remove('checked');
  }
}

// Inicializar checkboxes customizados
document.addEventListener('DOMContentLoaded', function() {
  const checkboxes = document.querySelectorAll('input[type="checkbox"]');
  checkboxes.forEach(checkbox => {
    const customCheckbox = checkbox.nextElementSibling;
    if (customCheckbox && customCheckbox.classList.contains('custom-checkbox')) {
      if (checkbox.checked) {
        customCheckbox.classList.add('checked');
      }
    }
  });
});

// Drag & Drop para imagens
const imageUpload = document.querySelector('.image-upload');
const fileInput = document.getElementById('imagens');

imageUpload.addEventListener('dragover', function(e) {
  e.preventDefault();
  this.classList.add('dragover');
});

imageUpload.addEventListener('dragleave', function() {
  this.classList.remove('dragover');
});

imageUpload.addEventListener('drop', function(e) {
  e.preventDefault();
  this.classList.remove('dragover');
  
  const files = e.dataTransfer.files;
  if (files.length > 0) {
    fileInput.files = files;
    previewImages(fileInput);
  }
});

// Validação de preços
document.querySelector('input[name="preco_promocional"]').addEventListener('input', function() {
  const precoNormal = parseFloat(document.querySelector('input[name="preco"]').value) || 0;
  const precoPromocional = parseFloat(this.value) || 0;
  
  if (precoPromocional > 0 && precoPromocional >= precoNormal) {
    this.setCustomValidity('O preço promocional deve ser menor que o preço normal');
  } else {
    this.setCustomValidity('');
  }
});

// Auto-gerar SKU baseado no nome (opcional)
document.querySelector('input[name="nome"]').addEventListener('input', function() {
  const skuField = document.querySelector('input[name="sku"]');
  if (!skuField.value) {
    const sku = this.value
      .toUpperCase()
      .replace(/[^A-Z0-9]/g, '-')
      .replace(/-+/g, '-')
      .substring(0, 20)
      .replace(/^-|-$/g, '');
    
    skuField.placeholder = sku ? `Sugestão: ${sku}` : 'Ex: PRODUTO-001';
  }
});

// Contador de caracteres para SEO
function setupCharCounter(inputId, maxLength) {
  const input = document.querySelector(`input[name="${inputId}"], textarea[name="${inputId}"]`);
  const help = input.closest('.form-group').querySelector('.form-help');
  
  input.addEventListener('input', function() {
    const remaining = maxLength - this.value.length;
    help.textContent = `${remaining} caracteres restantes`;
    
    if (remaining < 10) {
      help.style.color = 'var(--color-danger)';
    } else if (remaining < 30) {
      help.style.color = 'var(--color-warning)';
    } else {
      help.style.color = 'var(--color-info-dark)';
    }
  });
}

setupCharCounter('seo_title', 60);
setupCharCounter('seo_description', 160);

// Salvar como rascunho
document.getElementById('productForm').addEventListener('submit', function(e) {
  const saveBtn = document.getElementById('saveBtn');
  saveBtn.disabled = true;
  saveBtn.innerHTML = '<span class="material-symbols-sharp">sync</span> Salvando...';
});

// Atalhos de teclado
document.addEventListener('keydown', function(e) {
  // Ctrl+S para salvar
  if (e.ctrlKey && e.key === 's') {
    e.preventDefault();
    document.getElementById('productForm').submit();
  }
  
  // Esc para cancelar
  if (e.key === 'Escape') {
    if (confirm('Deseja cancelar e voltar à lista de produtos?')) {
      window.location.href = 'products.php';
    }
  }
});

// Auto-save rascunho (a cada 30 segundos)
let autoSaveTimeout;
const formInputs = document.querySelectorAll('input, textarea, select');

formInputs.forEach(input => {
  input.addEventListener('input', function() {
    clearTimeout(autoSaveTimeout);
    autoSaveTimeout = setTimeout(saveAsDraft, 30000);
  });
});

function saveAsDraft() {
  // Implementar salvamento automático como rascunho se necessário
  console.log('Auto-save: Produto salvo como rascunho');
}

// Autocomplete para categorias
const categoriaInput = document.querySelector('input[name="categoria"]');
if (categoriaInput) {
    let autocompleteTimeout;
    
    categoriaInput.addEventListener('input', function() {
        clearTimeout(autocompleteTimeout);
        const searchTerm = this.value;
        
        if (searchTerm.length >= 2) {
            autocompleteTimeout = setTimeout(() => {
                fetch(`addproducts.php?action=get_categories&search=${encodeURIComponent(searchTerm)}`)
                    .then(response => response.json())
                    .then(categories => {
                        updateDatalist('categorias', categories);
                    })
                    .catch(error => console.error('Erro ao buscar categorias:', error));
            }, 300);
        }
    });
}

function updateDatalist(datalistId, options) {
    const datalist = document.getElementById(datalistId);
    if (datalist) {
        datalist.innerHTML = '';
        options.forEach(option => {
            const optionElement = document.createElement('option');
            optionElement.value = option;
            datalist.appendChild(optionElement);
        });
    }
}
</script>
 </body>
</html>






