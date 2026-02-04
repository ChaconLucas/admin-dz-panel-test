<?php
session_start();
// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../../../PHP/login.php');
    exit();
}

// Incluir contador de mensagens
require_once 'helper-contador.php';

// Incluir sistema de logs automático
require_once '../auto_log.php';

// Incluir sistema de email automático
require_once 'email_automatico.php';

// Incluir conexão com banco de dados
require_once '../../../config/config.php';

// Criar tabela clientes se não existir e garantir todas as colunas
if ($conexao) {
    // Criar tabela
    $createTableQuery = "CREATE TABLE IF NOT EXISTS clientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        cpf_cnpj VARCHAR(20),
        data_nascimento DATE,
        whatsapp VARCHAR(20),
        telefone VARCHAR(20),
        senha VARCHAR(255),
        cep VARCHAR(9),
        rua VARCHAR(255),
        numero VARCHAR(20),
        complemento VARCHAR(255),
        bairro VARCHAR(100),
        cidade VARCHAR(100),
        uf VARCHAR(2),
        notas_internas TEXT,
        status ENUM('Ativo', 'Inativo') DEFAULT 'Ativo',
        data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    mysqli_query($conexao, $createTableQuery);
    
    // Garantir que todas as colunas existam (para bancos existentes)
    $alterQueries = [
        "ALTER TABLE clientes ADD COLUMN IF NOT EXISTS cpf_cnpj VARCHAR(20)",
        "ALTER TABLE clientes ADD COLUMN IF NOT EXISTS data_nascimento DATE",
        "ALTER TABLE clientes ADD COLUMN IF NOT EXISTS cep VARCHAR(9)",
        "ALTER TABLE clientes ADD COLUMN IF NOT EXISTS rua VARCHAR(255)",
        "ALTER TABLE clientes ADD COLUMN IF NOT EXISTS numero VARCHAR(20)",
        "ALTER TABLE clientes ADD COLUMN IF NOT EXISTS complemento VARCHAR(255)",
        "ALTER TABLE clientes ADD COLUMN IF NOT EXISTS bairro VARCHAR(100)",
        "ALTER TABLE clientes ADD COLUMN IF NOT EXISTS cidade VARCHAR(100)",
        "ALTER TABLE clientes ADD COLUMN IF NOT EXISTS uf VARCHAR(2)",
        "ALTER TABLE clientes ADD COLUMN IF NOT EXISTS notas_internas TEXT"
    ];
    
    foreach ($alterQueries as $query) {
        mysqli_query($conexao, $query);
    }
}

$success_msg = '';
$error_msg = '';

// Recuperar mensagens da sessão
if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

if (isset($_SESSION['error_msg'])) {
    $error_msg = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

// Processar ações POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_cliente':
        case 'update_cliente':
            $id = intval($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $cpf_cnpj = trim($_POST['cpf_cnpj'] ?? '');
            $data_nascimento = $_POST['data_nascimento'] ?? null;
            $whatsapp = trim($_POST['whatsapp'] ?? '');
            $telefone = trim($_POST['telefone'] ?? '');
            $senha = $_POST['senha'] ?? '';
            $cep = trim($_POST['cep'] ?? '');
            $rua = trim($_POST['rua'] ?? '');
            $numero = trim($_POST['numero'] ?? '');
            $complemento = trim($_POST['complemento'] ?? '');
            $bairro = trim($_POST['bairro'] ?? '');
            $cidade = trim($_POST['cidade'] ?? '');
            $uf = trim($_POST['uf'] ?? '');
            $notas_internas = trim($_POST['notas_internas'] ?? '');
            $status = $_POST['status'] ?? 'Ativo';
            
            // Validações
            if (empty($nome) || empty($email)) {
                $_SESSION['error_msg'] = "❌ Nome e email são obrigatórios!";
                header('Location: customers.php');
                exit();
            }
            
            if ($action == 'add_cliente') {
                // Verificar se email já existe
                $checkEmail = "SELECT id FROM clientes WHERE email = ?";
                $stmt = mysqli_prepare($conexao, $checkEmail);
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) > 0) {
                    $_SESSION['error_msg'] = "❌ Este email já está cadastrado!";
                    mysqli_stmt_close($stmt);
                    header('Location: customers.php');
                    exit();
                }
                mysqli_stmt_close($stmt);
                
                // Inserir novo cliente
                $senha_hash = !empty($senha) ? password_hash($senha, PASSWORD_DEFAULT) : null;
                
                $insertQuery = "INSERT INTO clientes (nome, email, cpf_cnpj, data_nascimento, whatsapp, telefone, senha, cep, rua, numero, complemento, bairro, cidade, uf, notas_internas, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conexao, $insertQuery);
                
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ssssssssssssssss", $nome, $email, $cpf_cnpj, $data_nascimento, $whatsapp, $telefone, $senha_hash, $cep, $rua, $numero, $complemento, $bairro, $cidade, $uf, $notas_internas, $status);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        registrar_log($conexao, "Adicionou novo cliente: $nome");
                        
                        // 🚀 DISPARAR EMAIL DE BOAS-VINDAS AUTOMATICAMENTE
                        $cliente_id = mysqli_insert_id($conexao);
                        enviarEmailAutomatico('novo_cliente', [
                            'cliente_id' => $cliente_id,
                            'nome' => $nome,
                            'email' => $email
                        ]);
                        
                        $_SESSION['success_msg'] = "✅ Cliente '$nome' adicionado com sucesso! Email de boas-vindas enviado.";
                    } else {
                        $_SESSION['error_msg'] = "❌ Erro ao adicionar cliente: " . mysqli_error($conexao);
                    }
                    
                    mysqli_stmt_close($stmt);
                }
            } else {
                // Atualizar cliente existente
                if (!empty($senha)) {
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $updateQuery = "UPDATE clientes SET nome = ?, email = ?, cpf_cnpj = ?, data_nascimento = ?, whatsapp = ?, telefone = ?, senha = ?, cep = ?, rua = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, uf = ?, notas_internas = ?, status = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conexao, $updateQuery);
                    mysqli_stmt_bind_param($stmt, "ssssssssssssssssi", $nome, $email, $cpf_cnpj, $data_nascimento, $whatsapp, $telefone, $senha_hash, $cep, $rua, $numero, $complemento, $bairro, $cidade, $uf, $notas_internas, $status, $id);
                } else {
                    $updateQuery = "UPDATE clientes SET nome = ?, email = ?, cpf_cnpj = ?, data_nascimento = ?, whatsapp = ?, telefone = ?, cep = ?, rua = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, uf = ?, notas_internas = ?, status = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conexao, $updateQuery);
                    mysqli_stmt_bind_param($stmt, "sssssssssssssssi", $nome, $email, $cpf_cnpj, $data_nascimento, $whatsapp, $telefone, $cep, $rua, $numero, $complemento, $bairro, $cidade, $uf, $notas_internas, $status, $id);
                }
                
                if ($stmt && mysqli_stmt_execute($stmt)) {
                    registrar_log($conexao, "Atualizou cliente: $nome (ID: $id)");
                    $_SESSION['success_msg'] = "✅ Cliente '$nome' atualizado com sucesso!";
                } else {
                    $_SESSION['error_msg'] = "❌ Erro ao atualizar cliente: " . mysqli_error($conexao);
                }
                
                if ($stmt) mysqli_stmt_close($stmt);
            }
            
            header('Location: customers.php');
            exit();
            break;
            
        case 'delete_cliente':
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                $nameQuery = "SELECT nome FROM clientes WHERE id = ?";
                $nameStmt = mysqli_prepare($conexao, $nameQuery);
                $clienteName = '';
                
                if ($nameStmt) {
                    mysqli_stmt_bind_param($nameStmt, "i", $id);
                    mysqli_stmt_execute($nameStmt);
                    $nameResult = mysqli_stmt_get_result($nameStmt);
                    if ($nameRow = mysqli_fetch_assoc($nameResult)) {
                        $clienteName = $nameRow['nome'];
                    }
                    mysqli_stmt_close($nameStmt);
                }
                
                $deleteQuery = "DELETE FROM clientes WHERE id = ?";
                $stmt = mysqli_prepare($conexao, $deleteQuery);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "i", $id);
                    if (mysqli_stmt_execute($stmt)) {
                        registrar_log($conexao, "Removeu cliente: $clienteName (ID: $id)");
                        $_SESSION['success_msg'] = "✅ Cliente removido com sucesso!";
                    } else {
                        $_SESSION['error_msg'] = "❌ Erro ao remover cliente: " . mysqli_error($conexao);
                    }
                    mysqli_stmt_close($stmt);
                }
            }
            header('Location: customers.php');
            exit();
            break;
    }
}

// Buscar clientes
$search = $_GET['search'] ?? '';
$clientesList = [];

// Primeiro, contar total de clientes no banco (sem filtro)
$total_sql = "SELECT COUNT(*) as total FROM clientes";
$total_result = mysqli_query($conexao, $total_sql);
$total_row = mysqli_fetch_assoc($total_result);
$total_clients = $total_row['total'];

try {
    $query = "SELECT * FROM clientes";
    if (!empty($search)) {
        $query .= " WHERE nome LIKE ? OR email LIKE ? OR whatsapp LIKE ? OR cidade LIKE ?";
    }
    $query .= " ORDER BY data_cadastro DESC";
    
    $stmt = mysqli_prepare($conexao, $query);
    if (!empty($search)) {
        $searchParam = "%$search%";
        mysqli_stmt_bind_param($stmt, "ssss", $searchParam, $searchParam, $searchParam, $searchParam);
    }
    
    if ($stmt && mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $clientesList[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
} catch (Exception $e) {
    error_log("Erro ao buscar clientes: " . $e->getMessage());
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

    <title>Gestão de Clientes - Dashboard</title>
    <style>
        /* Garantir que todos os ícones ativos tenham a mesma aparência */
        aside .sidebar a.active {
            background: rgba(255, 20, 147, 0.15) !important;
            color: #ff1493 !important;
            margin-left: 1.5rem !important;
            margin-right: 0.5rem !important;
            position: relative !important;
            border-left: 5px solid #ff1493 !important;
            border-radius: 0 8px 8px 0 !important;
        }
        
        aside .sidebar a.active span {
            color: #ff1493 !important;
            font-weight: 600 !important;
            font-size: 1.1em !important;
            transform: scale(1.1) !important;
        }
        
        aside .sidebar a.active h3 {
            color: #ff1493 !important;
            font-weight: 600 !important;
        }
        
        /* Estilos específicos para Gestão de Clientes */
        .search-container {
            background: var(--color-white);
            border-radius: var(--card-border-radius);
            padding: 1.5rem;
            margin: 1rem 0 2rem 0;
            box-shadow: var(--box-shadow);
            position: relative;
        }
        
        .search-wrapper {
            position: relative;
            width: 100%;
        }
        
        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid var(--color-info-light);
            border-radius: var(--border-radius-2);
            font-size: 1rem;
            background: var(--color-white);
            color: var(--color-dark);
            box-sizing: border-box;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            border-color: #ff1493;
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 20, 147, 0.1);
        }
        
        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-info-dark);
            pointer-events: none;
        }
        
        .clients-table {
            background: var(--color-white);
            border-radius: var(--card-border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }
        
        .table-header {
            background: linear-gradient(135deg, #ff1493, #ff00d4);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .clients-grid {
            display: grid;
            grid-template-columns: 60px 1fr 150px 150px 100px 120px;
            gap: 1rem;
            padding: 1rem;
            align-items: center;
            border-bottom: 1px solid var(--color-info-light);
        }
        
        .clients-grid:last-child {
            border-bottom: none;
        }
        
        .clients-grid:hover {
            background: rgba(255, 20, 147, 0.05);
        }
        
        .client-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ff1493, #ff00d4);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
        }
        
        .status-ativo { background: rgba(34, 197, 94, 0.1); color: var(--color-success); }
        .status-inativo { background: rgba(156, 163, 175, 0.1); color: var(--color-info-dark); }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-action {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: var(--border-radius-1);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .btn-edit { background: rgba(34, 197, 94, 0.1); color: var(--color-success); }
        .btn-delete { background: rgba(239, 68, 68, 0.1); color: var(--color-danger); }
        .btn-action:hover { transform: scale(1.1); }
        
        .whatsapp-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #25d366;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius-2);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .whatsapp-link:hover {
            background: #20ba5a;
            transform: scale(1.05);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--color-info-dark);
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: var(--color-white);
            border-radius: var(--card-border-radius);
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
        }
        
        .modal-sidebar {
            background: var(--color-background);
            width: 200px;
            padding: 2rem 0;
            border-right: 1px solid var(--color-info-light);
        }
        
        .modal-nav-item {
            padding: 1rem 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--color-dark);
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .modal-nav-item:hover {
            background: rgba(255, 20, 147, 0.05);
        }
        
        .modal-nav-item.active {
            background: rgba(255, 20, 147, 0.1);
            border-left-color: #ff1493;
            color: #ff1493;
        }
        
        .modal-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #ff1493, #ff00d4);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 2rem;
            flex: 1;
            overflow-y: auto;
            max-height: 60vh;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: var(--color-dark);
            margin-bottom: 0.5rem;
        }
        
        .form-input, .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--color-info-light);
            border-radius: var(--border-radius-1);
            background: var(--color-white);
            font-size: 1rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--color-info-light);
            border-radius: var(--border-radius-1);
            background: var(--color-white);
            font-size: 1rem;
            resize: vertical;
            min-height: 120px;
        }
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--color-info-light);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        /* Modo Dark - apenas para campo de busca */
        .dark-theme-variables .search-container .search-input {
            background: #1a1a1a !important;
            color: #ffffff !important;
            border-color: #4a4a4a !important;
        }
        
        .dark-theme-variables .search-container .search-input::placeholder {
            color: #888888 !important;
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
            <span class="message-count"><?= $nao_lidas; ?></span>
          </a>

          <a href="products.php">
            <span class="material-symbols-sharp">Inventory</span>
            <h3>Produtos</h3>
          </a>

          <a href="cupons.php">
            <span class="material-symbols-sharp">sell</span>
            <h3>Cupons</h3>
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

      <!----------FINAL ASIDE------------>
      <main>
        <h1>Gestão de Clientes</h1>
        
        <!-- Header com contador e botão adicionar -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin: 1rem 0;">
            <div class="date">
                <span style="display: flex; align-items: center; gap: 0.5rem;">
                    <span class="material-symbols-sharp">group</span>
                    <?= count($clientesList) ?> cliente(s) encontrado(s)
                </span>
            </div>
            <button onclick="openModal('add')" style="background: linear-gradient(135deg, #ff1493, #ff00d4); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: var(--border-radius-2); cursor: pointer; display: flex; align-items: center; gap: 0.5rem; font-weight: 600;">
                <span class="material-symbols-sharp">add</span>
                Novo Cliente
            </button>
        </div>

        <!-- Mensagens de feedback -->
        <?php if ($success_msg): ?>
            <div style="background: var(--color-white); border: 2px solid var(--color-success); color: var(--color-success); padding: 1rem; border-radius: var(--card-border-radius); margin: 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
                <span class="material-symbols-sharp">check_circle</span>
                <?= $success_msg ?>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div style="background: var(--color-white); border: 2px solid var(--color-danger); color: var(--color-danger); padding: 1rem; border-radius: var(--card-border-radius); margin: 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
                <span class="material-symbols-sharp">error</span>
                <?= $error_msg ?>
            </div>
        <?php endif; ?>

        <!-- Busca -->
        <div class="search-container">
            <div class="search-wrapper">
                <span class="material-symbols-sharp search-icon">search</span>
                <input type="text" class="search-input" id="searchInput" placeholder="Buscar por nome, email, WhatsApp ou cidade..." value="<?= htmlspecialchars($search) ?>" onkeyup="searchClients(this.value)">
            </div>
        </div>

        <!-- Tabela de Clientes -->
        <div class="clients-table">
            <div class="table-header">
                <h2 style="margin: 0;">Lista de Clientes</h2>
                <?php if (!empty($search)): ?>
                    <span style="opacity: 0.9;">Mostrando <?= count($clientesList) ?> de <?= $total_clients ?> clientes</span>
                <?php else: ?>
                    <span style="opacity: 0.9;">Total: <?= $total_clients ?> clientes</span>
                <?php endif; ?>
            </div>

            <!-- Headers da tabela -->
            <div class="clients-grid" style="font-weight: 600; background: var(--color-background);">
                <div></div>
                <div>Cliente</div>
                <div>WhatsApp</div>
                <div>Localidade</div>
                <div>Status</div>
                <div style="text-align: center;">Ações</div>
            </div>

            <!-- Lista de clientes -->
            <?php if (empty($clientesList)): ?>
                <?php if ($total_clients == 0): ?>
                    <!-- Banco vazio - primeiro cliente -->
                    <div class="empty-state">
                        <span class="material-symbols-sharp" style="font-size: 4rem; margin-bottom: 1rem; display: block; opacity: 0.5;">group_off</span>
                        <h3>Nenhum cliente cadastrado</h3>
                        <p>Adicione seu primeiro cliente para começar a gerenciar sua base de dados.</p>
                        <button onclick="openModal('add')" style="background: linear-gradient(135deg, #ff1493, #ff00d4); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: var(--border-radius-2); cursor: pointer; margin-top: 1rem;">
                            <span class="material-symbols-sharp" style="margin-right: 0.5rem;">add</span>
                            Adicionar Primeiro Cliente
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Busca sem resultados -->
                    <div class="empty-state">
                        <span class="material-symbols-sharp" style="font-size: 4rem; margin-bottom: 1rem; display: block; opacity: 0.5;">search_off</span>
                        <h3>Nenhum cliente encontrado</h3>
                        <p>Sua busca por "<?= htmlspecialchars($search) ?>" não retornou resultados.</p>
                        <p style="opacity: 0.7; margin-top: 0.5rem;">Tente usar outros termos ou <a href="customers.php" style="color: #ff1493; text-decoration: none;">visualizar todos os clientes</a>.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <?php foreach ($clientesList as $cliente): ?>
                    <div class="clients-grid">
                        <div class="client-avatar">
                            <?= strtoupper(substr($cliente['nome'], 0, 2)) ?>
                        </div>
                        <div>
                            <div style="font-weight: 600; color: var(--color-dark);"><?= htmlspecialchars($cliente['nome']) ?></div>
                            <div style="font-size: 0.85rem; color: var(--color-info-dark);"><?= htmlspecialchars($cliente['email']) ?></div>
                        </div>
                        <div>
                            <?php if (!empty($cliente['whatsapp'])): ?>
                                <a href="https://wa.me/55<?= preg_replace('/[^0-9]/', '', $cliente['whatsapp']) ?>" target="_blank" class="whatsapp-link">
                                    <span class="material-symbols-sharp" style="font-size: 16px;">call</span>
                                    <?= htmlspecialchars($cliente['whatsapp']) ?>
                                </a>
                            <?php else: ?>
                                <span style="color: var(--color-info-dark); font-size: 0.85rem;">Não informado</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if (!empty($cliente['cidade']) && !empty($cliente['uf'])): ?>
                                <div style="font-weight: 500;"><?= htmlspecialchars($cliente['cidade']) ?></div>
                                <div style="font-size: 0.85rem; color: var(--color-info-dark);"><?= htmlspecialchars($cliente['uf']) ?></div>
                            <?php else: ?>
                                <span style="color: var(--color-info-dark); font-size: 0.85rem;">Não informado</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="status-badge status-<?= strtolower($cliente['status']) ?>">
                                <?= $cliente['status'] ?>
                            </span>
                        </div>
                        <div class="action-buttons">
                            <button class="btn-action btn-edit" onclick="editClient(<?= $cliente['id'] ?>)" title="Editar">
                                <span class="material-symbols-sharp" style="font-size: 16px;">edit</span>
                            </button>
                            <button class="btn-action btn-delete" onclick="deleteClient(<?= $cliente['id'] ?>, '<?= addslashes($cliente['nome']) ?>')" title="Excluir">
                                <span class="material-symbols-sharp" style="font-size: 16px;">delete</span>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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



    </div>

    <!-- Modal para Gestão de Clientes -->
    <div id="clientModal" class="modal-overlay">
        <div class="modal-content">
            <!-- Sidebar do Modal -->
            <div class="modal-sidebar">
                <div class="modal-nav-item active" onclick="switchTab('dados-pessoais')">
                    <span class="material-symbols-sharp">person</span>
                    <span>Dados Pessoais</span>
                </div>
                <div class="modal-nav-item" onclick="switchTab('endereco')">
                    <span class="material-symbols-sharp">location_on</span>
                    <span>Endereço</span>
                </div>
                <div class="modal-nav-item" onclick="switchTab('notas-crm')">
                    <span class="material-symbols-sharp">note</span>
                    <span>Notas/CRM</span>
                </div>
                <div class="modal-nav-item" onclick="switchTab('historico')">
                    <span class="material-symbols-sharp">history</span>
                    <span>Histórico</span>
                </div>
            </div>

            <!-- Conteúdo Principal do Modal -->
            <div class="modal-main">
                <div class="modal-header">
                    <h2 id="modalTitle">Novo Cliente</h2>
                    <button onclick="closeModal()" style="background: none; border: none; cursor: pointer; color: white; font-size: 1.5rem;">
                        <span class="material-symbols-sharp">close</span>
                    </button>
                </div>

                <form id="clientForm" method="POST">
                    <input type="hidden" name="action" value="add_cliente" id="formAction">
                    <input type="hidden" name="id" value="" id="clientId">

                    <div class="modal-body">
                        <!-- Aba Dados Pessoais -->
                        <div id="dados-pessoais" class="tab-content active">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Nome Completo *</label>
                                    <input type="text" name="nome" id="clientNome" class="form-input" required placeholder="Nome completo do cliente">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email *</label>
                                    <input type="email" name="email" id="clientEmail" class="form-input" required placeholder="email@exemplo.com">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">CPF/CNPJ</label>
                                    <input type="text" name="cpf_cnpj" id="clientCpfCnpj" class="form-input" placeholder="000.000.000-00" maxlength="18" onkeyup="formatCpfCnpj(this)">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Data de Nascimento</label>
                                    <input type="date" name="data_nascimento" id="clientDataNascimento" class="form-input">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">WhatsApp</label>
                                    <input type="tel" name="whatsapp" id="clientWhatsapp" class="form-input" placeholder="(11) 99999-9999" maxlength="15" onkeyup="formatPhone(this)">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Telefone</label>
                                    <input type="tel" name="telefone" id="clientTelefone" class="form-input" placeholder="(11) 3333-3333" maxlength="14" onkeyup="formatPhone(this)">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Senha (opcional)</label>
                                    <input type="password" name="senha" id="clientSenha" class="form-input" placeholder="Senha de acesso">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Status</label>
                                    <select name="status" id="clientStatus" class="form-select">
                                        <option value="Ativo">Ativo</option>
                                        <option value="Inativo">Inativo</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Aba Endereço -->
                        <div id="endereco" class="tab-content">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">CEP</label>
                                    <input type="text" name="cep" id="clientCep" class="form-input" placeholder="00000-000" maxlength="9" onkeyup="formatCep(this); searchCep(this.value)">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">UF</label>
                                    <input type="text" name="uf" id="clientUf" class="form-input" placeholder="SP" maxlength="2" style="text-transform: uppercase;">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Rua/Logradouro</label>
                                <input type="text" name="rua" id="clientRua" class="form-input" placeholder="Rua, Avenida, etc.">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Número</label>
                                    <input type="text" name="numero" id="clientNumero" class="form-input" placeholder="123">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Complemento</label>
                                    <input type="text" name="complemento" id="clientComplemento" class="form-input" placeholder="Apto 45, Bloco B...">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Bairro</label>
                                    <input type="text" name="bairro" id="clientBairro" class="form-input" placeholder="Nome do bairro">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Cidade</label>
                                    <input type="text" name="cidade" id="clientCidade" class="form-input" placeholder="Nome da cidade">
                                </div>
                            </div>
                        </div>

                        <!-- Aba Notas/CRM -->
                        <div id="notas-crm" class="tab-content">
                            <div class="form-group">
                                <label class="form-label">Notas Internas / CRM</label>
                                <textarea name="notas_internas" id="clientNotas" class="form-textarea" rows="8" placeholder="Adicione observações importantes sobre o cliente, histórico de atendimento, preferências, etc.&#10;&#10;Exemplo:&#10;- Cliente preferencial, sempre compra produtos premium&#10;- Gosta de receber ofertas via WhatsApp&#10;- Aniversário em dezembro - enviar promoção especial"></textarea>
                            </div>
                            <div style="background: rgba(59, 130, 246, 0.1); padding: 1rem; border-radius: var(--border-radius-1); border-left: 4px solid #3b82f6;">
                                <p style="margin: 0; font-size: 0.9rem; color: var(--color-dark);">
                                    <strong>💡 Dica:</strong> Use este espaço para registrar informações importantes que podem ajudar no atendimento futuro.
                                </p>
                            </div>
                        </div>

                        <!-- Aba Histórico -->
                        <div id="historico" class="tab-content">
                            <div style="text-align: center; padding: 3rem 1rem; color: var(--color-info-dark);">
                                <span class="material-symbols-sharp" style="font-size: 4rem; margin-bottom: 1rem; display: block; opacity: 0.5;">history</span>
                                <h3 style="margin: 0 0 0.5rem 0;">Histórico de Pedidos</h3>
                                <p style="margin: 0 0 1.5rem 0;">Esta seção será implementada em breve e mostrará:</p>
                                <ul style="text-align: left; max-width: 400px; margin: 0 auto; color: var(--color-info-dark);">
                                    <li>Histórico completo de pedidos</li>
                                    <li>Valores totais de compras</li>
                                    <li>Produtos mais comprados</li>
                                    <li>Frequência de compras</li>
                                    <li>Status dos pedidos em andamento</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" onclick="closeModal()" style="background: var(--color-light); color: var(--color-dark); border: none; padding: 0.75rem 1.5rem; border-radius: var(--border-radius-2); cursor: pointer; font-weight: 600;">
                            Cancelar
                        </button>
                        <button type="submit" style="background: linear-gradient(135deg, #ff1493, #ff00d4); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: var(--border-radius-2); cursor: pointer; font-weight: 600;">
                            <span id="submitText">Adicionar Cliente</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script src="../../js/dashboard.js"></script>
<script>
// Configurações globais
let currentMode = 'add';
let currentClientId = null;

// Aplicar tema salvo
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Gestão de Clientes carregada');
    
    const savedTheme = localStorage.getItem('darkTheme');
    if (savedTheme === 'true') {
        document.body.classList.add('dark-theme-variables');
    }
});

// Busca em tempo real
function searchClients(searchTerm) {
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(() => {
        if (searchTerm.trim() === '') {
            window.location.href = 'customers.php';
        } else {
            window.location.href = `customers.php?search=${encodeURIComponent(searchTerm)}`;
        }
    }, 500);
}

// Controle de abas do modal
function switchTab(tabName) {
    // Remover ativo de todas as abas
    document.querySelectorAll('.modal-nav-item').forEach(item => item.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    // Ativar aba selecionada
    document.querySelector(`[onclick="switchTab('${tabName}')"]`).classList.add('active');
    document.getElementById(tabName).classList.add('active');
}

// Abrir modal
function openModal(mode, clientId = null) {
    currentMode = mode;
    currentClientId = clientId;
    
    if (mode === 'add') {
        resetForm();
        document.getElementById('modalTitle').textContent = 'Novo Cliente';
        document.getElementById('formAction').value = 'add_cliente';
        document.getElementById('submitText').textContent = 'Adicionar Cliente';
    } else if (mode === 'edit') {
        loadClientData(clientId);
        document.getElementById('modalTitle').textContent = 'Editar Cliente';
        document.getElementById('formAction').value = 'update_cliente';
        document.getElementById('submitText').textContent = 'Salvar Alterações';
    }
    
    switchTab('dados-pessoais');
    document.getElementById('clientModal').style.display = 'flex';
}

// Editar cliente
function editClient(id) {
    <?php foreach ($clientesList as $cliente): ?>
        if (<?= $cliente['id'] ?> === id) {
            fillFormWithData({
                id: <?= $cliente['id'] ?>,
                nome: <?= json_encode($cliente['nome']) ?>,
                email: <?= json_encode($cliente['email']) ?>,
                cpf_cnpj: <?= json_encode($cliente['cpf_cnpj'] ?? '') ?>,
                data_nascimento: <?= json_encode($cliente['data_nascimento'] ?? '') ?>,
                whatsapp: <?= json_encode($cliente['whatsapp'] ?? '') ?>,
                telefone: <?= json_encode($cliente['telefone'] ?? '') ?>,
                cep: <?= json_encode($cliente['cep'] ?? '') ?>,
                rua: <?= json_encode($cliente['rua'] ?? '') ?>,
                numero: <?= json_encode($cliente['numero'] ?? '') ?>,
                complemento: <?= json_encode($cliente['complemento'] ?? '') ?>,
                bairro: <?= json_encode($cliente['bairro'] ?? '') ?>,
                cidade: <?= json_encode($cliente['cidade'] ?? '') ?>,
                uf: <?= json_encode($cliente['uf'] ?? '') ?>,
                notas_internas: <?= json_encode($cliente['notas_internas'] ?? '') ?>,
                status: <?= json_encode($cliente['status']) ?>
            });
            
            document.getElementById('modalTitle').textContent = 'Editar Cliente';
            document.getElementById('formAction').value = 'update_cliente';
            document.getElementById('clientId').value = id;
            document.getElementById('submitText').textContent = 'Salvar Alterações';
            
            switchTab('dados-pessoais');
            document.getElementById('clientModal').style.display = 'flex';
        }
    <?php endforeach; ?>
}

// Excluir cliente
function deleteClient(id, nome) {
    if (confirm(`Tem certeza que deseja excluir o cliente "${nome}"?\n\nEsta ação não pode ser desfeita.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.name = 'action';
        actionInput.value = 'delete_cliente';
        
        const idInput = document.createElement('input');
        idInput.name = 'id';
        idInput.value = id;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Preencher formulário
function fillFormWithData(data) {
    document.getElementById('clientNome').value = data.nome || '';
    document.getElementById('clientEmail').value = data.email || '';
    document.getElementById('clientCpfCnpj').value = data.cpf_cnpj || '';
    document.getElementById('clientDataNascimento').value = data.data_nascimento || '';
    document.getElementById('clientWhatsapp').value = data.whatsapp || '';
    document.getElementById('clientTelefone').value = data.telefone || '';
    document.getElementById('clientCep').value = data.cep || '';
    document.getElementById('clientRua').value = data.rua || '';
    document.getElementById('clientNumero').value = data.numero || '';
    document.getElementById('clientComplemento').value = data.complemento || '';
    document.getElementById('clientBairro').value = data.bairro || '';
    document.getElementById('clientCidade').value = data.cidade || '';
    document.getElementById('clientUf').value = data.uf || '';
    document.getElementById('clientNotas').value = data.notas_internas || '';
    document.getElementById('clientStatus').value = data.status || 'Ativo';
}

// Reset do formulário
function resetForm() {
    document.getElementById('clientForm').reset();
    document.getElementById('clientId').value = '';
}

// Fechar modal
function closeModal() {
    document.getElementById('clientModal').style.display = 'none';
    resetForm();
}

// Fechar modal clicando fora
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('clientModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    }
});

// Formatações
function formatCpfCnpj(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.length <= 11) {
        // CPF: 000.000.000-00
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    } else {
        // CNPJ: 00.000.000/0000-00
        value = value.replace(/^(\d{2})(\d)/, '$1.$2');
        value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
        value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
        value = value.replace(/(\d{4})(\d)/, '$1-$2');
    }
    
    input.value = value;
}

function formatPhone(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.length <= 10) {
        // Telefone fixo: (11) 3333-3333
        value = value.replace(/^(\d{2})(\d{4})(\d{4})$/, '($1) $2-$3');
    } else {
        // Celular: (11) 99999-9999
        value = value.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
    }
    
    input.value = value;
}

function formatCep(input) {
    let value = input.value.replace(/\D/g, '');
    value = value.replace(/^(\d{5})(\d{3})$/, '$1-$2');
    input.value = value;
}

// Busca CEP via ViaCEP
async function searchCep(cep) {
    const cleanCep = cep.replace(/\D/g, '');
    
    if (cleanCep.length === 8) {
        try {
            const response = await fetch(`https://viacep.com.br/ws/${cleanCep}/json/`);
            const data = await response.json();
            
            if (!data.erro) {
                document.getElementById('clientRua').value = data.logradouro || '';
                document.getElementById('clientBairro').value = data.bairro || '';
                document.getElementById('clientCidade').value = data.localidade || '';
                document.getElementById('clientUf').value = data.uf || '';
                
                // Focus no campo número
                document.getElementById('clientNumero').focus();
            }
        } catch (error) {
            console.log('Erro ao buscar CEP:', error);
        }
    }
}
</script>
 </body>
</html>









