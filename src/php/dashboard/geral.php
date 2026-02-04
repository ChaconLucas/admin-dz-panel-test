<?php
session_start();
// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../../../PHP/login.php');
    exit();
}

// Incluir contador de mensagens
require_once 'helper-contador.php';

// Incluir conexão com banco
require_once '../../../PHP/conexao.php';

// GARANTIR que a tabela settings existe com estrutura correta APENAS se necessário
if ($conexao) {
    $tableExists = false;
    $hasCorrectStructure = false;
    
    // Verificar se a tabela existe
    $tableCheck = mysqli_query($conexao, "SHOW TABLES LIKE 'settings'");
    if ($tableCheck && mysqli_num_rows($tableCheck) > 0) {
        $tableExists = true;
        
        // Verificar se tem a estrutura correta (coluna setting_key)
        $columnCheck = mysqli_query($conexao, "SHOW COLUMNS FROM settings LIKE 'setting_key'");
        if ($columnCheck && mysqli_num_rows($columnCheck) > 0) {
            $hasCorrectStructure = true;
        }
    }
    
    // Só recriar se a tabela não existir ou tiver estrutura incorreta
    if (!$tableExists || !$hasCorrectStructure) {
        // Dropar apenas se existir com estrutura incorreta
        if ($tableExists && !$hasCorrectStructure) {
            mysqli_query($conexao, "DROP TABLE IF EXISTS settings");
        }
        
        // Criar tabela
        $createTable = "CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(255) UNIQUE NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        mysqli_query($conexao, $createTable);
        
        // Inserir configurações padrão apenas se a tabela estava vazia
        $countCheck = mysqli_query($conexao, "SELECT COUNT(*) as total FROM settings");
        if ($countCheck) {
            $countRow = mysqli_fetch_assoc($countCheck);
            if ($countRow['total'] == 0) {
                $defaults = [
                    'loja_pais' => 'Brasil',
                    'config_moeda' => 'BRL',
                    'config_idioma' => 'pt-BR', 
                    'config_fuso_horario' => 'America/Sao_Paulo',
                    'visual_cor_primaria' => '#ff1493',
                    'visual_cor_secundaria' => '#ffffff'
                ];
                
                foreach ($defaults as $key => $value) {
                    $insertQuery = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)";
                    $insertStmt = mysqli_prepare($conexao, $insertQuery);
                    if ($insertStmt) {
                        mysqli_stmt_bind_param($insertStmt, "ss", $key, $value);
                        mysqli_stmt_execute($insertStmt);
                        mysqli_stmt_close($insertStmt);
                    }
                }
            }
        }
    }
}

// Verificar se há mensagem de retorno após redirecionamento (TEMPORARIAMENTE DESABILITADO)
/* 
if (isset($_GET['saved']) && !empty($_GET['saved'])) {
    switch ($_GET['saved']) {
        case 'success':
            if (isset($_GET['count']) && $_GET['count'] > 0) {
                $message = "✅ Todas as configurações foram salvas com sucesso! (" . $_GET['count'] . " campos)";
                $message_type = "success";
            }
            break;
        case 'partial':
            if (isset($_GET['count']) && isset($_GET['total'])) {
                $message = "⚠️ Algumas configurações foram salvas: " . $_GET['count'] . " de " . $_GET['total'] . " campos";
                $message_type = "error";
            }
            break;
        case 'error':
            if (isset($_GET['total'])) {
                $message = "❌ Nenhuma configuração foi salva. Total de campos: " . $_GET['total'];
                $message_type = "error";
            }
            break;
    }
}
*/

// Função para obter configuração
function getSetting($key, $default = '') {
    global $conexao;
    
    try {
        // Verificar se a conexão está OK
        if (!$conexao) {
            return $default;
        }
        
        $stmt = mysqli_prepare($conexao, "SELECT setting_value FROM settings WHERE setting_key = ?");
        if (!$stmt) {
            error_log("Erro ao preparar statement: " . mysqli_error($conexao));
            return $default;
        }
        
        mysqli_stmt_bind_param($stmt, "s", $key);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['setting_value'];
        }
    } catch (Exception $e) {
        error_log("Erro em getSetting: " . $e->getMessage());
    }
    
    return $default;
}

// Função para salvar configuração
function saveSetting($key, $value) {
    global $conexao;
    
    if (!$conexao) {
        return false;
    }
    
    // Usar INSERT com ON DUPLICATE KEY UPDATE (mais simples)
    $query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
    
    $stmt = mysqli_prepare($conexao, $query);
    if (!$stmt) {
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "ss", $key, $value);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

// Processar formulário se enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['loja_nome'])) {
    $saved_count = 0;
    $total_fields = 0;
    
    // Lista de campos para salvar
    $fields = [
        'loja_nome', 'loja_cnpj', 'loja_email', 'loja_whatsapp', 
        'loja_cidade', 'loja_estado', 'loja_pais',
        'endereco_cep', 'endereco_logradouro', 'endereco_numero', 
        'endereco_complemento', 'endereco_bairro', 'endereco_cidade', 'endereco_estado',
        'visual_cor_primaria', 'visual_cor_secundaria',
        'config_moeda', 'config_idioma', 'config_fuso_horario'
    ];
    
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $total_fields++;
            if (saveSetting($field, $_POST[$field])) {
                $saved_count++;
            }
        }
    }
    
    // Upload de logo
    if (isset($_FILES['visual_logo']) && $_FILES['visual_logo']['error'] == 0) {
        $upload_dir = '../../../uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($_FILES['visual_logo']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array(strtolower($file_extension), $allowed_extensions)) {
            $filename = 'logo.' . $file_extension;
            $upload_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['visual_logo']['tmp_name'], $upload_path)) {
                saveSetting('visual_logo', $upload_path);
            } else {
                $errors[] = "Erro ao fazer upload do logo";
            }
        } else {
            $errors[] = "Tipo de arquivo não permitido para logo";
        }
    }
    
    // Redirecionar com mensagem de sucesso para evitar reenvio do formulário
    if ($saved_count == $total_fields && $saved_count > 0) {
        header("Location: geral.php?saved=success&count=$saved_count");
        exit();
    } elseif ($saved_count > 0) {
        header("Location: geral.php?saved=partial&count=$saved_count&total=$total_fields");
        exit();
    } else {
        header("Location: geral.php?saved=error&total=$total_fields");
        exit();
    }
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

    <title>Configurações Gerais</title>
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

        /* Estilos específicos da página Geral */
        .message {
            padding: 1rem;
            border-radius: var(--border-radius-1);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .message.success {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid #4caf50;
            color: #2e7d32;
        }
        
        .message.error {
            background: rgba(244, 67, 54, 0.1);
            border: 1px solid #f44336;
            color: #c62828;
        }

        .settings-form {
            max-width: 100%;
            display: block;
            visibility: visible;
        }

        .form-section {
            background: var(--color-white);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            display: block;
            position: relative;
            z-index: 1;
        }

        .form-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .form-section h2 {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            color: var(--color-dark);
            margin-bottom: 2rem;
            font-size: 1.3rem;
            font-weight: 600;
            border-bottom: 1px solid var(--color-light);
            padding-bottom: 1.2rem;
        }

        .form-section h2 span {
            color: var(--color-primary);
            font-size: 1.4rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            align-items: start;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        .form-group-wide {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-weight: 600;
            color: var(--color-dark);
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select {
            padding: 1rem;
            border: 1px solid var(--color-info-dark);
            border-radius: var(--border-radius-1);
            font-size: 0.95rem;
            transition: all 300ms ease;
            background: var(--color-background);
            color: var(--color-dark);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--color-primary);
            background: var(--color-white);
        }

        .color-input-group {
            display: flex;
            gap: 0.8rem;
            align-items: center;
        }

        .color-input-group input[type="color"] {
            width: 60px;
            height: 50px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .color-input-group .color-hex {
            flex: 1;
            background: var(--color-background);
            cursor: not-allowed;
        }

        .logo-upload {
            position: relative;
        }

        .logo-preview {
            width: 200px;
            height: 120px;
            border: 2px dashed var(--color-info-dark);
            border-radius: var(--border-radius-1);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 300ms ease;
            overflow: hidden;
            position: relative;
        }

        .logo-preview:hover {
            border-color: var(--color-primary);
            background: rgba(255, 20, 147, 0.05);
        }

        .logo-preview img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .logo-preview span.material-symbols-sharp {
            font-size: 2rem;
            color: var(--color-dark-variant);
            margin-bottom: 0.5rem;
        }

        .logo-upload input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            z-index: 10;
        }

        .form-actions {
            text-align: center;
            margin: 4rem 0;
            padding: 3rem 2rem;
        }

        .btn-save {
            background: var(--color-primary);
            color: white;
            border: none;
            padding: 1rem 2.5rem;
            border-radius: var(--border-radius-2);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            transition: all 300ms ease;
            min-width: 200px;
            justify-content: center;
        }

        .btn-save:hover {
            background: var(--color-primary-variant);
            transform: translateY(-2px);
        }

        .btn-save:active {
            transform: translateY(-1px);
        }

        /* Tema escuro - Seletores mais específicos */
        body.dark-theme-variables .form-section {
            background: #1e1e1e !important;
            color: var(--color-white) !important;
            border: 1px solid #333 !important;
        }

        body.dark-theme-variables .form-section h2 {
            color: #ffffff !important;
            border-color: #333 !important;
        }

        body.dark-theme-variables .form-section h2 span {
            color: var(--color-primary) !important;
        }

        body.dark-theme-variables .form-group label {
            color: #ffffff !important;
        }

        body.dark-theme-variables label {
            color: #ffffff !important;
        }

        body.dark-theme-variables .form-group input,
        body.dark-theme-variables .form-group select {
            background: #2c2c2c !important;
            border: 1px solid #404040 !important;
            color: #ffffff !important;
        }

        body.dark-theme-variables .form-group input:focus,
        body.dark-theme-variables .form-group select:focus {
            background: #1a1a1a !important;
            border-color: var(--color-primary) !important;
            color: #ffffff !important;
            box-shadow: 0 0 0 2px rgba(255, 20, 147, 0.2) !important;
        }

        body.dark-theme-variables .form-group input::placeholder {
            color: #888 !important;
        }

        body.dark-theme-variables .color-input-group .color-hex {
            background: #2c2c2c !important;
            color: var(--color-white) !important;
            border: 1px solid #404040 !important;
        }

        body.dark-theme-variables .logo-preview {
            border: 2px dashed #404040 !important;
            background: #2c2c2c !important;
            color: var(--color-white) !important;
        }

        body.dark-theme-variables .logo-preview:hover {
            border-color: var(--color-primary) !important;
            background: #333 !important;
        }

        body.dark-theme-variables .message.success {
            background: rgba(76, 175, 80, 0.2) !important;
            border-color: #4caf50 !important;
            color: #81c784 !important;
        }

        body.dark-theme-variables .message.error {
            background: rgba(244, 67, 54, 0.2) !important;
            border-color: #f44336 !important;
            color: #ef5350 !important;
        }

        body.dark-theme-variables .btn-save {
            background: var(--color-primary) !important;
            color: var(--color-white) !important;
        }

        body.dark-theme-variables .btn-save:hover {
            background: var(--color-primary-variant) !important;
        }



        /* Garantir visibilidade do conteúdo */
        main {
            display: block;
            visibility: visible;
            opacity: 1;
        }
        
        .settings-form {
            display: block;
            width: 100%;
        }
        
        .form-section {
            background: var(--color-white);
            border-radius: var(--card-border-radius);
            padding: 2.5rem;
            margin-bottom: 2.5rem;
            box-shadow: var(--box-shadow);
            transition: all 300ms ease;
        }
        
        /* Responsivo */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-section {
                padding: 1.5rem;
            }
            
            .color-input-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .logo-preview {
                width: 100%;
                height: 150px;
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
              <a href="geral.php" class="active">
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
        <h1>Configurações Gerais</h1>
        
        <?php if (isset($message)): ?>
        <div class="message <?= $message_type ?>">
          <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        

        

        

        
        <form method="POST" enctype="multipart/form-data" class="settings-form">
          
          <!-- SEÇÃO 1: INFORMAÇÕES DA LOJA -->
          <div class="form-section">
            <h2><span class="material-symbols-sharp">store</span> Informações da Loja</h2>
            <div class="form-grid">
              <div class="form-group">
                <label for="loja_nome">Nome da Loja *</label>
                <input type="text" id="loja_nome" name="loja_nome" value="<?= htmlspecialchars(getSetting('loja_nome')) ?>" required>
              </div>
              
              <div class="form-group">
                <label for="loja_cnpj">CNPJ/CPF</label>
                <input type="text" id="loja_cnpj" name="loja_cnpj" value="<?= htmlspecialchars(getSetting('loja_cnpj')) ?>" class="cnpj-cpf-mask">
              </div>
              
              <div class="form-group">
                <label for="loja_email">E-mail Principal *</label>
                <input type="email" id="loja_email" name="loja_email" value="<?= htmlspecialchars(getSetting('loja_email')) ?>" required>
              </div>
              
              <div class="form-group">
                <label for="loja_whatsapp">WhatsApp</label>
                <input type="text" id="loja_whatsapp" name="loja_whatsapp" value="<?= htmlspecialchars(getSetting('loja_whatsapp')) ?>" class="phone-mask" placeholder="(11) 99999-9999">
              </div>
              
              <div class="form-group">
                <label for="loja_cidade">Cidade</label>
                <input type="text" id="loja_cidade" name="loja_cidade" value="<?= htmlspecialchars(getSetting('loja_cidade')) ?>">
              </div>
              
              <div class="form-group">
                <label for="loja_estado">Estado</label>
                <select id="loja_estado" name="loja_estado">
                  <option value="">Selecione...</option>
                  <option value="SP" <?= getSetting('loja_estado') == 'SP' ? 'selected' : '' ?>>São Paulo</option>
                  <option value="RJ" <?= getSetting('loja_estado') == 'RJ' ? 'selected' : '' ?>>Rio de Janeiro</option>
                  <option value="MG" <?= getSetting('loja_estado') == 'MG' ? 'selected' : '' ?>>Minas Gerais</option>
                  <option value="RS" <?= getSetting('loja_estado') == 'RS' ? 'selected' : '' ?>>Rio Grande do Sul</option>
                  <option value="PR" <?= getSetting('loja_estado') == 'PR' ? 'selected' : '' ?>>Paraná</option>
                  <option value="SC" <?= getSetting('loja_estado') == 'SC' ? 'selected' : '' ?>>Santa Catarina</option>
                  <option value="BA" <?= getSetting('loja_estado') == 'BA' ? 'selected' : '' ?>>Bahia</option>
                  <option value="GO" <?= getSetting('loja_estado') == 'GO' ? 'selected' : '' ?>>Goiás</option>
                  <option value="PE" <?= getSetting('loja_estado') == 'PE' ? 'selected' : '' ?>>Pernambuco</option>
                  <option value="CE" <?= getSetting('loja_estado') == 'CE' ? 'selected' : '' ?>>Ceará</option>
                </select>
              </div>
              
              <div class="form-group">
                <label for="loja_pais">País</label>
                <input type="text" id="loja_pais" name="loja_pais" value="<?= htmlspecialchars(getSetting('loja_pais', 'Brasil')) ?>">
              </div>
            </div>
          </div>

          <!-- SEÇÃO 2: ENDEREÇO DA LOJA -->
          <div class="form-section">
            <h2><span class="material-symbols-sharp">location_on</span> Endereço da Loja</h2>
            <div class="form-grid">
              <div class="form-group">
                <label for="endereco_cep">CEP</label>
                <input type="text" id="endereco_cep" name="endereco_cep" value="<?= htmlspecialchars(getSetting('endereco_cep')) ?>" class="cep-mask" placeholder="00000-000">
              </div>
              
              <div class="form-group form-group-wide">
                <label for="endereco_logradouro">Endereço</label>
                <input type="text" id="endereco_logradouro" name="endereco_logradouro" value="<?= htmlspecialchars(getSetting('endereco_logradouro')) ?>">
              </div>
              
              <div class="form-group">
                <label for="endereco_numero">Número</label>
                <input type="text" id="endereco_numero" name="endereco_numero" value="<?= htmlspecialchars(getSetting('endereco_numero')) ?>">
              </div>
              
              <div class="form-group">
                <label for="endereco_complemento">Complemento</label>
                <input type="text" id="endereco_complemento" name="endereco_complemento" value="<?= htmlspecialchars(getSetting('endereco_complemento')) ?>">
              </div>
              
              <div class="form-group">
                <label for="endereco_bairro">Bairro</label>
                <input type="text" id="endereco_bairro" name="endereco_bairro" value="<?= htmlspecialchars(getSetting('endereco_bairro')) ?>">
              </div>
              
              <div class="form-group">
                <label for="endereco_cidade">Cidade</label>
                <input type="text" id="endereco_cidade" name="endereco_cidade" value="<?= htmlspecialchars(getSetting('endereco_cidade')) ?>">
              </div>
              
              <div class="form-group">
                <label for="endereco_estado">Estado</label>
                <select id="endereco_estado" name="endereco_estado">
                  <option value="">Selecione...</option>
                  <option value="SP" <?= getSetting('endereco_estado') == 'SP' ? 'selected' : '' ?>>São Paulo</option>
                  <option value="RJ" <?= getSetting('endereco_estado') == 'RJ' ? 'selected' : '' ?>>Rio de Janeiro</option>
                  <option value="MG" <?= getSetting('endereco_estado') == 'MG' ? 'selected' : '' ?>>Minas Gerais</option>
                  <option value="RS" <?= getSetting('endereco_estado') == 'RS' ? 'selected' : '' ?>>Rio Grande do Sul</option>
                  <option value="PR" <?= getSetting('endereco_estado') == 'PR' ? 'selected' : '' ?>>Paraná</option>
                  <option value="SC" <?= getSetting('endereco_estado') == 'SC' ? 'selected' : '' ?>>Santa Catarina</option>
                  <option value="BA" <?= getSetting('endereco_estado') == 'BA' ? 'selected' : '' ?>>Bahia</option>
                  <option value="GO" <?= getSetting('endereco_estado') == 'GO' ? 'selected' : '' ?>>Goiás</option>
                  <option value="PE" <?= getSetting('endereco_estado') == 'PE' ? 'selected' : '' ?>>Pernambuco</option>
                  <option value="CE" <?= getSetting('endereco_estado') == 'CE' ? 'selected' : '' ?>>Ceará</option>
                </select>
              </div>
            </div>
          </div>

          <!-- SEÇÃO 3: IDENTIDADE VISUAL -->
          <div class="form-section">
            <h2><span class="material-symbols-sharp">palette</span> Identidade Visual</h2>
            <div class="form-grid">
              <div class="form-group logo-upload">
                <label for="visual_logo">Logo da Loja</label>
                <div class="logo-preview" id="logoPreview">
                  <input type="file" id="visual_logo" name="visual_logo" accept=".jpg,.jpeg,.png,.gif">
                  <?php 
                  $logo = getSetting('visual_logo');
                  if ($logo && file_exists($logo)): 
                  ?>
                    <img src="<?= htmlspecialchars($logo) ?>" alt="Logo atual">
                  <?php else: ?>
                    <span class="material-symbols-sharp">image</span>
                    <span>Clique para selecionar logo</span>
                  <?php endif; ?>
                </div>
              </div>
              
              <div class="form-group">
                <label for="visual_cor_primaria">Cor Primária</label>
                <div class="color-input-group">
                  <input type="color" id="visual_cor_primaria" name="visual_cor_primaria" value="<?= htmlspecialchars(getSetting('visual_cor_primaria', '#ff1493')) ?>">
                  <input type="text" class="color-hex" value="<?= htmlspecialchars(getSetting('visual_cor_primaria', '#ff1493')) ?>" readonly>
                </div>
              </div>
              
              <div class="form-group">
                <label for="visual_cor_secundaria">Cor Secundária</label>
                <div class="color-input-group">
                  <input type="color" id="visual_cor_secundaria" name="visual_cor_secundaria" value="<?= htmlspecialchars(getSetting('visual_cor_secundaria', '#ffffff')) ?>">
                  <input type="text" class="color-hex" value="<?= htmlspecialchars(getSetting('visual_cor_secundaria', '#ffffff')) ?>" readonly>
                </div>
              </div>
            </div>
          </div>

          <!-- SEÇÃO 4: CONFIGURAÇÕES GERAIS -->
          <div class="form-section">
            <h2><span class="material-symbols-sharp">settings</span> Configurações Gerais</h2>
            <div class="form-grid">
              <div class="form-group">
                <label for="config_moeda">Moeda</label>
                <select id="config_moeda" name="config_moeda">
                  <option value="BRL" <?= getSetting('config_moeda', 'BRL') == 'BRL' ? 'selected' : '' ?>>Real Brasileiro (BRL)</option>
                  <option value="USD" <?= getSetting('config_moeda') == 'USD' ? 'selected' : '' ?>>Dólar Americano (USD)</option>
                  <option value="EUR" <?= getSetting('config_moeda') == 'EUR' ? 'selected' : '' ?>>Euro (EUR)</option>
                </select>
              </div>
              
              <div class="form-group">
                <label for="config_idioma">Idioma</label>
                <select id="config_idioma" name="config_idioma">
                  <option value="pt-BR" <?= getSetting('config_idioma', 'pt-BR') == 'pt-BR' ? 'selected' : '' ?>>Português (Brasil)</option>
                  <option value="en-US" <?= getSetting('config_idioma') == 'en-US' ? 'selected' : '' ?>>Inglês (EUA)</option>
                  <option value="es-ES" <?= getSetting('config_idioma') == 'es-ES' ? 'selected' : '' ?>>Espanhol</option>
                </select>
              </div>
              
              <div class="form-group">
                <label for="config_fuso_horario">Fuso Horário</label>
                <select id="config_fuso_horario" name="config_fuso_horario">
                  <option value="America/Sao_Paulo" <?= getSetting('config_fuso_horario', 'America/Sao_Paulo') == 'America/Sao_Paulo' ? 'selected' : '' ?>>São Paulo (GMT-3)</option>
                  <option value="America/New_York" <?= getSetting('config_fuso_horario') == 'America/New_York' ? 'selected' : '' ?>>Nova York (GMT-5)</option>
                  <option value="Europe/London" <?= getSetting('config_fuso_horario') == 'Europe/London' ? 'selected' : '' ?>>Londres (GMT+0)</option>
                </select>
              </div>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-save">
              <span class="material-symbols-sharp">save</span>
              Salvar Configurações
            </button>
          </div>
        </form>
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
document.addEventListener('DOMContentLoaded', function() {
    // Limpar URL imediatamente se não houver mensagem válida para mostrar
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('saved')) {
        const messageExists = document.querySelector('.message');
        if (!messageExists) {
            // Se há parâmetro 'saved' mas não há mensagem, limpar URL
            const newUrl = window.location.pathname;
            window.history.replaceState({}, document.title, newUrl);
        }
    }
    
    // Aplicar tema escuro se estiver ativo
    const savedTheme = localStorage.getItem('darkTheme');
    if (savedTheme === 'true') {
        document.body.classList.add('dark-theme-variables');
    }
    
    // Detectar mudanças do tema durante o uso
    const themeObserver = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'class') {
                const isDark = document.body.classList.contains('dark-theme-variables');
                console.log('Tema alterado para:', isDark ? 'dark' : 'light');
            }
        });
    });
    
    themeObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });
    
    // Garantir que o conteúdo esteja visível
    const main = document.querySelector('main');
    const form = document.querySelector('.settings-form');
    
    if (main) {
        main.style.display = 'block';
        main.style.visibility = 'visible';
    }
    
    if (form) {
        form.style.display = 'block';
        form.style.visibility = 'visible';
    }

    // Máscaras de entrada
    function applyMask(input, mask) {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            let formatted = '';
            
            if (mask === 'phone') {
                // (11) 99999-9999
                formatted = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                if (value.length <= 10) {
                    formatted = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
                }
            } else if (mask === 'cep') {
                // 00000-000
                formatted = value.replace(/(\d{5})(\d{3})/, '$1-$2');
            } else if (mask === 'cnpj-cpf') {
                if (value.length <= 11) {
                    // CPF: 000.000.000-00
                    formatted = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                } else {
                    // CNPJ: 00.000.000/0000-00
                    formatted = value.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
                }
            }
            
            e.target.value = formatted;
        });
    }

    // Aplicar máscaras
    const phoneInputs = document.querySelectorAll('.phone-mask');
    phoneInputs.forEach(input => applyMask(input, 'phone'));

    const cepInputs = document.querySelectorAll('.cep-mask');
    cepInputs.forEach(input => applyMask(input, 'cep'));

    const cnpjCpfInputs = document.querySelectorAll('.cnpj-cpf-mask');
    cnpjCpfInputs.forEach(input => applyMask(input, 'cnpj-cpf'));

    // Preview de logo
    const logoInput = document.getElementById('visual_logo');
    const logoPreview = document.getElementById('logoPreview');

    logoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Verificar se é imagem
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                alert('Por favor, selecione apenas arquivos de imagem (JPG, PNG, GIF)');
                this.value = '';
                return;
            }
            
            // Preview da imagem
            const reader = new FileReader();
            reader.onload = function(e) {
                logoPreview.innerHTML = '<input type="file" id="visual_logo" name="visual_logo" accept=".jpg,.jpeg,.png,.gif"><img src="' + e.target.result + '" alt="Preview do logo" style="width: 100%; height: 100%; object-fit: cover;">';
                // Reativar o input após mudança do innerHTML
                const newInput = logoPreview.querySelector('input[type="file"]');
                newInput.addEventListener('change', arguments.callee);
            };
            reader.readAsDataURL(file);
        }
    });

    // Sincronizar seletor de cor com campo texto
    function syncColorInputs() {
        const colorInputs = document.querySelectorAll('input[type="color"]');
        colorInputs.forEach(colorInput => {
            const hexInput = colorInput.parentElement.querySelector('.color-hex');
            
            colorInput.addEventListener('input', function(e) {
                hexInput.value = e.target.value;
            });
        });
    }

    syncColorInputs();

    // Auto-preenchimento de endereço por CEP (ViaCEP API)
    const cepInput = document.getElementById('endereco_cep');
    if (cepInput) {
        cepInput.addEventListener('blur', function() {
            const cep = this.value.replace(/\D/g, '');
            
            if (cep.length === 8) {
                // Indicador de carregamento
                const logradouroInput = document.getElementById('endereco_logradouro');
                logradouroInput.value = 'Carregando...';
                
                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(response => response.json())
                    .then(data => {
                        if (!data.erro) {
                            document.getElementById('endereco_logradouro').value = data.logradouro || '';
                            document.getElementById('endereco_bairro').value = data.bairro || '';
                            document.getElementById('endereco_cidade').value = data.localidade || '';
                            document.getElementById('endereco_estado').value = data.uf || '';
                        } else {
                            logradouroInput.value = '';
                            alert('CEP não encontrado!');
                        }
                    })
                    .catch(error => {
                        logradouroInput.value = '';
                        console.error('Erro ao buscar CEP:', error);
                    });
            }
        });
    }

    // Feedback visual ao salvar
    if (form) {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('.btn-save');
            submitBtn.innerHTML = '<span class="material-symbols-sharp">hourglass_empty</span> Salvando...';
            submitBtn.disabled = true;
        });
    }

    // Auto-hide mensagens após 5 segundos e limpar URL
    const messages = document.querySelectorAll('.message');
    messages.forEach(message => {
        setTimeout(() => {
            message.style.opacity = '0';
            setTimeout(() => {
                message.remove();
                // Limpar parâmetros da URL após a mensagem desaparecer
                if (window.location.search.includes('saved=')) {
                    const newUrl = window.location.pathname;
                    window.history.replaceState({}, document.title, newUrl);
                }
            }, 300);
        }, 5000);
    });

    // Validação em tempo real
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    const emailInput = document.getElementById('loja_email');
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            if (this.value && !validateEmail(this.value)) {
                this.style.borderColor = '#f44336';
                this.focus();
                alert('Por favor, insira um e-mail válido');
            } else {
                this.style.borderColor = '';
            }
        });
    }
});
</script>
 </body>
</html>