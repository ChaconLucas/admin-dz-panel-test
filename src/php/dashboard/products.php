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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    mysqli_query($conexao, $create_table);
}

// Processar atualização inline via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_field') {
    header('Content-Type: application/json');
    
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    // Validar dados básicos
    if (empty($field) || empty($value) || !$id) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }
    
    // Validar campo permitido
    $allowed_fields = ['preco', 'preco_promocional', 'estoque'];
    if (!in_array($field, $allowed_fields)) {
        echo json_encode(['success' => false, 'message' => 'Campo não permitido']);
        exit;
    }
    
    // Verificar se a coluna existe, se não, criar
    $columns_query = "SHOW COLUMNS FROM produtos";
    $columns_result = mysqli_query($conexao, $columns_query);
    $existing_columns = [];
    while ($col = mysqli_fetch_assoc($columns_result)) {
        $existing_columns[] = $col['Field'];
    }
    
    // Adicionar coluna se não existir
    if (!in_array($field, $existing_columns)) {
        $column_definitions = [
            'preco' => 'DECIMAL(10,2) DEFAULT 0',
            'preco_promocional' => 'DECIMAL(10,2) DEFAULT 0', 
            'estoque' => 'INT DEFAULT 0'
        ];
        
        if (isset($column_definitions[$field])) {
            $add_column_sql = "ALTER TABLE produtos ADD COLUMN `$field` " . $column_definitions[$field];
            mysqli_query($conexao, $add_column_sql);
        }
    }
    
    // Processar valor
    if ($field === 'estoque') {
        $value = max(0, (int)$value);
    } else {
        $value = max(0, (float)str_replace(',', '.', $value));
    }
    
    // Tentar atualizar
    try {
        $sql = "UPDATE produtos SET `$field` = ? WHERE id = ?";
        $stmt = mysqli_prepare($conexao, $sql);
        
        if ($stmt) {
            if ($field === 'estoque') {
                mysqli_stmt_bind_param($stmt, "ii", $value, $id);
            } else {
                mysqli_stmt_bind_param($stmt, "di", $value, $id);
            }
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => 'Atualizado com sucesso']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro na execução: ' . mysqli_stmt_error($stmt)]);
            }
            mysqli_stmt_close($stmt);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro na preparação: ' . mysqli_error($conexao)]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
    exit;
}

// Processar ações (delete, toggle status)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    switch ($action) {
        case 'delete':
            $sql = "DELETE FROM produtos WHERE id = ?";
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Produto excluído com sucesso!";
            }
            break;
            
        case 'toggle_status':
            $sql = "UPDATE produtos SET status = CASE 
                    WHEN status = 'ativo' THEN 'inativo' 
                    ELSE 'ativo' END WHERE id = ?";
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Status atualizado!";
            }
            break;
    }
}

// Filtros e busca
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Construir query
$where_conditions = ['1=1'];
$params = [];
$types = '';

if ($search) {
    $where_conditions[] = "(nome LIKE ? OR sku LIKE ? OR descricao LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= 'sss';
}

if ($category) {
    $where_conditions[] = "categoria = ?";
    $params[] = $category;
    $types .= 's';
}

if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_clause = implode(' AND ', $where_conditions);

// Query para contar total
$count_sql = "SELECT COUNT(*) as total FROM produtos WHERE $where_clause";
$count_stmt = mysqli_prepare($conexao, $count_sql);
if ($params) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$total_result = mysqli_stmt_get_result($count_stmt);
$total_products = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_products / $per_page);

// Query principal
$sql = "SELECT * FROM produtos WHERE $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$products = mysqli_stmt_get_result($stmt);

// Buscar categorias (verificar se coluna existe)
$check_column = "SHOW COLUMNS FROM produtos LIKE 'categoria'";
$column_exists = mysqli_query($conexao, $check_column);

if (mysqli_num_rows($column_exists) > 0) {
    $categories_sql = "SELECT DISTINCT categoria FROM produtos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria";
    $categories_result = mysqli_query($conexao, $categories_sql);
} else {
    // Criar resultado vazio se coluna não existir
    $categories_result = mysqli_query($conexao, "SELECT 'Eletrônicos' as categoria WHERE FALSE");
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

    <title>Produtos - D&Z Admin</title>
    <style>
      /* Estilos específicos para produtos */
      .products-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
      }
      
      .products-title {
        font-size: 1.8rem;
        font-weight: 600;
        color: var(--color-dark);
      }
      
      .btn-add-product {
        background: var(--color-primary);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
      }
      
      .btn-add-product:hover {
        background: var(--color-primary-variant);
        transform: translateY(-1px);
      }
      
      .filters-section {
        background: var(--color-white);
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: var(--box-shadow);
        margin-bottom: 1.5rem;
      }
      
      .filters-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr auto;
        gap: 1rem;
        align-items: end;
      }
      
      .filter-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--color-dark-variant);
        font-size: 0.9rem;
      }
      
      .filter-input {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid var(--color-info-light);
        border-radius: 8px;
        font-size: 0.9rem;
        background: var(--color-background);
      }
      
      .filter-input:focus {
        outline: none;
        border-color: var(--color-danger);
      }
      
      .btn-filter {
        padding: 0.75rem 1.5rem;
        background: var(--color-danger);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }
      
      .products-grid {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 2rem;
      }
      
      .product-card {
        background: var(--color-white);
        border-radius: 12px;
        box-shadow: var(--box-shadow);
        overflow: hidden;
        transition: all 0.3s ease;
        border: 1px solid transparent;
        display: flex;
        align-items: center;
        padding: 1rem;
        min-height: 100px;
      }
      
      .product-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        border-color: var(--color-danger);
      }
      
      .product-image {
        width: 80px;
        height: 80px;
        background: var(--color-background);
        position: relative;
        overflow: hidden;
        border-radius: 8px;
        flex-shrink: 0;
        margin-right: 1rem;
      }
      
      .product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }
      
      .no-image {
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--color-info-dark);
        font-size: 3rem;
      }
      
      .status-badge {
        position: static;
        padding: 2px 6px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        white-space: nowrap;
      }
      
      .status-badge.ativo {
        background: var(--color-success);
        color: white;
      }
      
      .status-badge.inativo {
        background: var(--color-danger);
        color: white;
      }
      
      .status-badge.rascunho {
        background: var(--color-warning);
        color: white;
      }
      
      .product-info {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0;
      }
      
      .product-details {
        flex: 1;
      }
      
      .product-name {
        font-size: 1rem;
        font-weight: 600;
        color: var(--color-dark);
        margin-bottom: 0.25rem;
        line-height: 1.3;
      }
      
      .product-sku {
        font-size: 0.75rem;
        color: var(--color-info-dark);
        margin-bottom: 0.25rem;
      }
      
      .product-price {
        font-size: 1rem;
        font-weight: 700;
        color: var(--color-success);
        margin-bottom: 0.25rem;
      }
      
      .product-stock {
        font-size: 0.8rem;
        color: var(--color-info-dark);
        margin-bottom: 0;
      }
      
      .product-stock.low {
        color: var(--color-danger);
        font-weight: 600;
      }
      
      .product-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        padding: 0;
        border: none;
      }
      
      .btn-action {
        padding: 0.4rem 0.8rem;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        white-space: nowrap;
      }
      
      .btn-edit {
        background: var(--color-danger);
        color: white;
      }
      
      .btn-toggle {
        background: var(--color-warning);
        color: white;
      }
      
      .btn-delete {
        background: var(--color-danger);
        color: white;
      }
      
      .pagination {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
      }
      
      .page-btn {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--color-info-light);
        background: var(--color-white);
        color: var(--color-dark);
        text-decoration: none;
        border-radius: 6px;
        transition: all 0.3s ease;
      }
      
      .page-btn:hover,
      .page-btn.active {
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
      }
      
      .empty-state {
        text-align: center;
        padding: 3rem;
        color: var(--color-info-dark);
      }
      
      .empty-state .material-symbols-sharp {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
      }
      
      /* Campos editáveis */
      .editable-price, .editable-stock {
        cursor: pointer;
        padding: 2px 4px;
        border-radius: 4px;
        transition: all 0.2s ease;
        border: 1px solid transparent;
      }
      
      .editable-price:hover, .editable-stock:hover {
        background: rgba(255, 0, 212, 0.1);
        border-color: var(--color-danger);
      }
      
      .editing {
        background: var(--color-white) !important;
        border: 1px solid var(--color-danger) !important;
        outline: none;
        padding: 2px 4px;
        border-radius: 4px;
        font-family: inherit;
        font-size: inherit;
        font-weight: inherit;
        color: inherit;
        min-width: 60px;
      }
      
      .saving {
        opacity: 0.6;
        pointer-events: none;
      }

      @media (max-width: 768px) {
        .filters-grid {
          grid-template-columns: 1fr;
        }
        
        .product-card {
          flex-direction: column;
          text-align: center;
        }
        
        .product-image {
          width: 60px;
          height: 60px;
          margin: 0 auto 0.5rem auto;
        }
        
        .product-info {
          flex-direction: column;
          gap: 0.5rem;
        }
        
        .products-header {
          flex-direction: column;
          gap: 1rem;
          align-items: stretch;
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
        <?php if (isset($success)): ?>
          <div class="alert alert-success">
            <span class="material-symbols-sharp">check_circle</span>
            <?php echo htmlspecialchars($success); ?>
          </div>
        <?php endif; ?>
        
        <!-- Header -->
        <div class="products-header">
          <h1 class="products-title">
            <span class="material-symbols-sharp">inventory</span>
            Produtos
          </h1>
          <div style="display: flex; gap: 1rem;">
            <a href="gallery.php" class="btn-add-product" style="background: var(--color-danger); text-decoration: none;">
              <span class="material-symbols-sharp">photo_library</span>
              Galeria de Imagens
            </a>
            <a href="addproducts.php" class="btn-add-product" style="background: var(--color-danger);">
              <span class="material-symbols-sharp">add</span>
              Novo Produto
            </a>
          </div>
        </div>

        <!-- Filtros -->
        <div class="filters-section">
          <form method="GET" action="products.php">
            <div class="filters-grid">
              <div class="filter-group">
                <label for="search">Buscar produtos</label>
                <input type="text" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Nome, SKU ou descrição..." 
                       class="filter-input">
              </div>
              
              <div class="filter-group">
                <label for="category">Categoria</label>
                <select id="category" name="category" class="filter-input">
                  <option value="">Todas as categorias</option>
                  <?php while ($cat = mysqli_fetch_assoc($categories_result)): ?>
                    <option value="<?php echo htmlspecialchars($cat['categoria']); ?>" 
                            <?php echo $category == $cat['categoria'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($cat['categoria']); ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>
              
              <div class="filter-group">
                <label for="status">Status</label>
                <select id="status" name="status" class="filter-input">
                  <option value="">Todos os status</option>
                  <option value="ativo" <?php echo $status_filter == 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                  <option value="inativo" <?php echo $status_filter == 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                  <option value="rascunho" <?php echo $status_filter == 'rascunho' ? 'selected' : ''; ?>>Rascunho</option>
                </select>
              </div>
              
              <button type="submit" class="btn-filter">
                <span class="material-symbols-sharp">search</span>
                Filtrar
              </button>
            </div>
          </form>
        </div>

        <!-- Grid de Produtos -->
        <div class="products-grid">
          <?php if (mysqli_num_rows($products) > 0): ?>
            <?php while ($product = mysqli_fetch_assoc($products)): ?>
              <div class="product-card">
                <div class="product-image">
                  <?php 
                  $imagens = ($product['imagens'] ?? null) ? json_decode($product['imagens'], true) : [];
                  $primeira_imagem = !empty($imagens) && is_array($imagens) ? $imagens[0] : null;
                  ?>
                  
                  <?php if ($primeira_imagem && file_exists("../../../assets/images/produtos/" . $primeira_imagem)): ?>
                    <img src="../../../assets/images/produtos/<?php echo htmlspecialchars($primeira_imagem); ?>" 
                         alt="<?php echo htmlspecialchars($product['nome']); ?>">
                  <?php else: ?>
                    <div class="no-image">
                      <span class="material-symbols-sharp">image</span>
                    </div>
                  <?php endif; ?>
                  
                </div>
                
                <div class="product-info">
                  <div class="product-details">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                      <h3 class="product-name" style="margin: 0;"><?php echo htmlspecialchars($product['nome'] ?? 'Produto sem nome'); ?></h3>
                      <div class="status-badge <?php echo $product['status'] ?? 'ativo'; ?>">
                        <?php echo ucfirst($product['status'] ?? 'ativo'); ?>
                      </div>
                    </div>
                    
                    <?php if (!empty($product['sku'])): ?>
                      <div class="product-sku">SKU: <?php echo htmlspecialchars($product['sku']); ?></div>
                    <?php endif; ?>
                    
                    <div class="product-price">
                      <?php if (!empty($product['preco_promocional']) && $product['preco_promocional'] > 0): ?>
                        <span style="text-decoration: line-through; color: var(--color-info-dark); font-size: 0.8rem;">
                          R$ <span class="editable-price" data-field="preco" data-id="<?php echo $product['id']; ?>" onclick="editField(this)"><?php echo number_format($product['preco'] ?? 0, 2, ',', '.'); ?></span>
                        </span>
                        R$ <span class="editable-price" data-field="preco_promocional" data-id="<?php echo $product['id']; ?>" onclick="editField(this)"><?php echo number_format($product['preco_promocional'], 2, ',', '.'); ?></span>
                      <?php else: ?>
                        R$ <span class="editable-price" data-field="preco" data-id="<?php echo $product['id']; ?>" onclick="editField(this)"><?php echo number_format($product['preco'] ?? 0, 2, ',', '.'); ?></span>
                      <?php endif; ?>
                    </div>
                    
                    <div class="product-stock <?php echo ($product['estoque'] ?? 0) <= 5 ? 'low' : ''; ?>">
                      <span class="material-symbols-sharp">inventory</span>
                      <span class="editable-stock" data-field="estoque" data-id="<?php echo $product['id']; ?>" onclick="editField(this)"><?php echo $product['estoque'] ?? 0; ?></span> em estoque
                    </div>
                  </div>
                  
                  <div class="product-actions">
                    <a href="addproducts.php?edit=<?php echo $product['id'] ?? 0; ?>" 
                       class="btn-action btn-edit" title="Editar">
                      <span class="material-symbols-sharp">edit</span>
                    </a>
                    
                    <a href="?action=toggle_status&id=<?php echo $product['id'] ?? 0; ?>" 
                       class="btn-action btn-toggle" 
                       title="<?php echo ($product['status'] ?? 'ativo') == 'ativo' ? 'Desativar' : 'Ativar'; ?>"
                       onclick="return confirm('Alterar status do produto?')">
                      <span class="material-symbols-sharp">
                        <?php echo ($product['status'] ?? 'ativo') == 'ativo' ? 'visibility_off' : 'visibility'; ?>
                      </span>
                    </a>
                    
                    <a href="?action=delete&id=<?php echo $product['id'] ?? 0; ?>" 
                       class="btn-action btn-delete" title="Excluir"
                       onclick="return confirm('Tem certeza que deseja excluir este produto?')">
                      <span class="material-symbols-sharp">delete</span>
                    </a>
                  </div>
                </div>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="empty-state" style="grid-column: 1 / -1;">
              <span class="material-symbols-sharp">inventory</span>
              <h3>Nenhum produto encontrado</h3>
              <p>
                <?php if ($search || $category || $status_filter): ?>
                  Não há produtos que correspondem aos filtros aplicados.
                <?php else: ?>
                  Comece criando seu primeiro produto.
                <?php endif; ?>
              </p>
              <br>
              <a href="addproducts.php" class="btn-add-product">
                <span class="material-symbols-sharp">add</span>
                Criar Produto
              </a>
            </div>
          <?php endif; ?>
        </div>

        <!-- Paginação -->
        <?php if ($total_pages > 1): ?>
          <div class="pagination">
            <?php if ($page > 1): ?>
              <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status_filter); ?>" 
                 class="page-btn">
                <span class="material-symbols-sharp">chevron_left</span>
              </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
              <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status_filter); ?>" 
                 class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
              </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
              <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status_filter); ?>" 
                 class="page-btn">
                <span class="material-symbols-sharp">chevron_right</span>
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        
        <div style="margin-top: 1rem; text-align: center; color: var(--color-info-dark);">
          Mostrando <?php echo min($total_products, ($page-1)*$per_page + 1); ?> - 
          <?php echo min($total_products, $page*$per_page); ?> de <?php echo $total_products; ?> produtos
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
function editField(element) {
    if (element.classList.contains('editing')) return;
    
    const originalValue = element.textContent.trim();
    const field = element.dataset.field;
    const id = element.dataset.id;
    
    console.log('editField called:', {originalValue, field, id});
    
    // Criar input
    const input = document.createElement('input');
    input.type = field === 'estoque' ? 'number' : 'text';
    input.value = field === 'estoque' ? originalValue : originalValue.replace(',', '.');
    input.className = 'editing';
    input.style.width = Math.max(60, originalValue.length * 8) + 'px';
    input.min = '0';
    input.step = field === 'estoque' ? '1' : '0.01';
    
    // Substituir elemento
    element.parentNode.replaceChild(input, element);
    input.focus();
    input.select();
    
    function saveField() {
        const newValue = input.value;
        if (newValue === originalValue || !newValue.trim()) {
            // Cancelar - restaurar elemento original
            if (input.parentNode) {
                input.parentNode.replaceChild(element, input);
            }
            return;
        }
        
        // Mostrar loading
        input.classList.add('saving');
        
        // Enviar para servidor
        console.log('Enviando:', {action: 'update_field', id, field, value: newValue});
        
        fetch(window.location.pathname, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_field&id=${id}&field=${field}&value=${encodeURIComponent(newValue)}`
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            console.log('Response text:', text);
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    // Atualizar elemento
                    if (field === 'estoque') {
                        element.textContent = newValue;
                    } else {
                        const numValue = parseFloat(newValue);
                        element.textContent = numValue.toLocaleString('pt-BR', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                    if (input.parentNode) {
                        input.parentNode.replaceChild(element, input);
                        
                        // Feedback visual
                        element.style.background = '#d4edda';
                        setTimeout(() => {
                            element.style.background = '';
                        }, 1000);
                    }
                } else {
                    alert('Erro ao salvar: ' + (data.message || 'Erro desconhecido'));
                    if (input.parentNode) {
                        input.parentNode.replaceChild(element, input);
                    }
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                alert('Erro no formato da resposta do servidor');
                if (input.parentNode) {
                    input.parentNode.replaceChild(element, input);
                }
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Erro na conexão: ' + error.message);
            if (input.parentNode) {
                input.parentNode.replaceChild(element, input);
            }
        });
    }
    
    input.addEventListener('blur', saveField);
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            saveField();
        } else if (e.key === 'Escape') {
            if (input.parentNode) {
                input.parentNode.replaceChild(element, input);
            }
        }
    });
}
</script>
 </body>
</html>









