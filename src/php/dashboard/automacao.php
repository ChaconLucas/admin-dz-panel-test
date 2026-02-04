<?php
session_start();

// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../../../PHP/login.php');
    exit();
}

// Incluir conexão e configurações
require_once '../../../config/config.php';
require_once 'helper-contador.php';

// Mensagens de resposta
$message = '';
$message_type = '';

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_whatsapp'])) {
            // Configuração WhatsApp
            $token = trim($_POST['whatsapp_token']);
            $instance = trim($_POST['whatsapp_instance']);
            $enabled = isset($_POST['whatsapp_enabled']) ? 1 : 0;
            
            $stmt = $conexao->prepare("
                INSERT INTO configuracoes_gerais (campo, valor, updated_at) 
                VALUES 
                ('whatsapp_token', ?, NOW()),
                ('whatsapp_instance', ?, NOW()),
                ('whatsapp_enabled', ?, NOW())
                ON DUPLICATE KEY UPDATE 
                valor = VALUES(valor), updated_at = NOW()
            ");
            $stmt->bind_param("sss", $token, $instance, $enabled);
            $stmt->execute();
            
            $message = 'Configurações do WhatsApp salvas com sucesso!';
            $message_type = 'success';
        }
        
        if (isset($_POST['save_stock'])) {
            // Configuração de Estoque
            $limite = intval($_POST['stock_critico_limite']);
            $email = trim($_POST['email_alertas']);
            $enabled = isset($_POST['alerta_stock_enabled']) ? 1 : 0;
            
            $stmt = $conexao->prepare("
                INSERT INTO configuracoes_gerais (campo, valor, updated_at) 
                VALUES 
                ('stock_critico_limite', ?, NOW()),
                ('email_alertas', ?, NOW()),
                ('alerta_stock_enabled', ?, NOW())
                ON DUPLICATE KEY UPDATE 
                valor = VALUES(valor), updated_at = NOW()
            ");
            $stmt->bind_param("sss", $limite, $email, $enabled);
            $stmt->execute();
            
            $message = 'Configurações de alertas de estoque salvas com sucesso!';
            $message_type = 'success';
        }
        
        if (isset($_POST['save_reports'])) {
            // Configuração de Relatórios
            $host = trim($_POST['smtp_host']);
            $email = trim($_POST['smtp_email']);
            $hora = $_POST['relatorio_diario_hora'];
            $enabled = isset($_POST['relatorio_diario_enabled']) ? 1 : 0;
            
            $stmt = $conexao->prepare("
                INSERT INTO configuracoes_gerais (campo, valor, updated_at) 
                VALUES 
                ('smtp_host', ?, NOW()),
                ('smtp_email', ?, NOW()),
                ('relatorio_diario_hora', ?, NOW()),
                ('relatorio_diario_enabled', ?, NOW())
                ON DUPLICATE KEY UPDATE 
                valor = VALUES(valor), updated_at = NOW()
            ");
            $stmt->bind_param("ssss", $host, $email, $hora, $enabled);
            $stmt->execute();
            
            $message = 'Configurações de relatórios salvas com sucesso!';
            $message_type = 'success';
        }
        
        if (isset($_POST['save_template'])) {
            // Salvar template
            $template = trim($_POST['template_alerta_stock']);
            
            $stmt = $conexao->prepare("
                INSERT INTO configuracoes_gerais (campo, valor, updated_at) 
                VALUES ('template_alerta_stock', ?, NOW())
                ON DUPLICATE KEY UPDATE 
                valor = VALUES(valor), updated_at = NOW()
            ");
            $stmt->bind_param("s", $template);
            $stmt->execute();
            
            $message = 'Template de alerta salvo com sucesso!';
            $message_type = 'success';
        }
        
    } catch (Exception $e) {
        $message = 'Erro ao salvar configurações: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Criar tabela configuracoes_gerais simples se não existir
$table_check = mysqli_query($conexao, "SHOW TABLES LIKE 'configuracoes_gerais'");
if (mysqli_num_rows($table_check) == 0) {
    // Criar tabela simples
    mysqli_query($conexao, "
        CREATE TABLE configuracoes_gerais (
            id INT AUTO_INCREMENT PRIMARY KEY,
            campo VARCHAR(100) UNIQUE NOT NULL,
            valor TEXT
        )
    ");
    $field_name = 'campo';
} else {
    // Verificar qual nome de coluna usar
    $column_check = mysqli_query($conexao, "SHOW COLUMNS FROM configuracoes_gerais LIKE 'campo'");
    if (mysqli_num_rows($column_check) > 0) {
        $field_name = 'campo';
    } else {
        $column_check = mysqli_query($conexao, "SHOW COLUMNS FROM configuracoes_gerais LIKE 'chave'");
        if (mysqli_num_rows($column_check) > 0) {
            $field_name = 'chave';
        } else {
            // Recriar tabela simples
            mysqli_query($conexao, "DROP TABLE configuracoes_gerais");
            mysqli_query($conexao, "
                CREATE TABLE configuracoes_gerais (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    campo VARCHAR(100) UNIQUE NOT NULL,
                    valor TEXT
                )
            ");
            $field_name = 'campo';
        }
    }
}

// Carregar configurações existentes da tabela usando o nome de campo correto
$configuracoes = [];
try {
    $result = mysqli_query($conexao, "SELECT $field_name as chave, valor FROM configuracoes_gerais");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $configuracoes[$row['chave']] = $row['valor'];
        }
    }
    
    // Se não houver configurações, criar padrões
    if (empty($configuracoes)) {
        $defaults = [
            'whatsapp_enabled' => '0',
            'trigger_stock' => '0',
            'relatorio_diario_enabled' => '0',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_porta' => '587',
            'smtp_email' => '',
            'smtp_senha' => '',
            'stock_critico_limite' => '5',
            'relatorio_diario_hora' => '09:00'
        ];
        
        foreach ($defaults as $campo => $valor) {
            $stmt = mysqli_prepare($conexao, "INSERT IGNORE INTO configuracoes_gerais ($field_name, valor) VALUES (?, ?)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ss", $campo, $valor);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            $configuracoes[$campo] = $valor;
        }
    }
    
    // Debug: mostrar configurações carregadas
    error_log("Configurações carregadas: " . print_r($configuracoes, true));
    
} catch (Exception $e) {
    error_log("Erro ao carregar configurações: " . $e->getMessage());
}

// Carregar templates de mensagem
$templates = [];
try {
    $result = $conexao->query("SELECT * FROM message_templates WHERE ativo = 1 ORDER BY nome");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $templates[] = $row;
        }
    }
} catch (Exception $e) {
    // Criar tabela se não existir
    $conexao->query("
        CREATE TABLE IF NOT EXISTS message_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            conteudo TEXT NOT NULL,
            ativo BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <!-- FONT AWESOME -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css" />
    <link rel="stylesheet" href="../../css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title>Configurações de Automação</title>
    <style>
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
        
        /* Estilos para os cartões de configuração */
        .config-card {
            background: var(--color-white);
            padding: var(--card-padding);
            border-radius: var(--card-border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .config-card:hover {
            border-color: var(--color-primary-variant);
            transform: translateY(-2px);
        }
        
        .config-card h3 {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            color: var(--color-dark);
            font-size: 1.2rem;
            border-bottom: 2px solid var(--color-light);
            padding-bottom: 0.75rem;
        }
        
        .config-card h3 .material-symbols-sharp {
            background: linear-gradient(135deg, var(--color-primary), var(--color-danger));
            color: white;
            padding: 0.5rem;
            border-radius: 50%;
            font-size: 1.2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--color-dark-variant);
            font-size: 0.9rem;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--color-info-light);
            border-radius: var(--border-radius-1);
            background: var(--color-white);
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(255, 20, 147, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background: linear-gradient(135deg, var(--color-primary), var(--color-danger));
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .form-toggle {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .btn-save {
            background: linear-gradient(135deg, var(--color-primary), var(--color-danger));
            color: white;
            padding: 0.875rem 2rem;
            border: none;
            border-radius: var(--border-radius-1);
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 20, 147, 0.3);
        }
        
        .btn-secondary {
            background: var(--color-dark-variant);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius-1);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: var(--color-dark);
        }
        
        .template-item {
            background: var(--color-light);
            padding: 1rem;
            border-radius: var(--border-radius-1);
            margin-bottom: 1rem;
            border-left: 4px solid var(--color-primary);
        }
        
        .template-item h4 {
            margin-bottom: 0.5rem;
            color: var(--color-dark);
        }
        
        .template-content {
            background: var(--color-white);
            padding: 0.75rem;
            border-radius: var(--border-radius-1);
            border: 1px solid var(--color-info-light);
            font-family: monospace;
            font-size: 0.85rem;
            line-height: 1.4;
            color: var(--color-dark-variant);
        }
        
        .variable-tag {
            background: var(--color-primary);
            color: white;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .variables-help {
            background: var(--color-info-light);
            padding: 1rem;
            border-radius: var(--border-radius-1);
            margin-top: 1rem;
        }
        
        .variables-help h4 {
            margin-bottom: 0.75rem;
            color: var(--color-info-dark);
        }
        
        /* Estilos para configurações de infraestrutura */
        :root {
            --rosa-vibrante: #ff00cc;
            --rosa-escuro: #d1006d;
            --roxo-claro: #8b5cf6;
            --cinza-claro: #f8f9fa;
            --branco: #ffffff;
        }

        .config-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            padding: 0;
            margin-bottom: 2rem;
        }

        .config-card {
            background: var(--branco);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .config-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
        }

        .card-icon.whatsapp { background: var(--rosa-vibrante); }
        .card-icon.stock { background: var(--rosa-escuro); }
        .card-icon.reports { background: var(--roxo-claro); }

        .card-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: var(--branco);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--rosa-vibrante);
            box-shadow: 0 0 0 3px rgba(255, 0, 204, 0.1);
        }

        .switch-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1.5rem 0;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 12px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .switch-container.active {
            background: rgba(255, 0, 204, 0.05);
            border-color: var(--rosa-vibrante);
        }

        .switch-label {
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .switch-label::before {
            content: '●';
            color: #cbd5e1;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .switch-container.active .switch-label::before {
            color: var(--rosa-vibrante);
            text-shadow: 0 0 8px rgba(255, 0, 204, 0.5);
        }

        .status-indicator {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-inactive {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .status-active {
            background: rgba(255, 0, 204, 0.1);
            color: var(--rosa-vibrante);
            border: 1px solid var(--rosa-vibrante);
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: .3s;
            border-radius: 30px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 24px;
            width: 24px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .slider:after {
            content: '✕';
            position: absolute;
            top: 50%;
            left: 8px;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 12px;
            font-weight: bold;
            transition: .3s;
        }

        input:checked + .slider {
            background: linear-gradient(135deg, var(--rosa-vibrante), var(--rosa-escuro));
            box-shadow: inset 0 2px 4px rgba(255, 0, 204, 0.3);
        }

        input:checked + .slider:before {
            transform: translateX(30px);
            box-shadow: 0 2px 12px rgba(255, 0, 204, 0.4);
        }

        input:checked + .slider:after {
            content: '✓';
            left: auto;
            right: 8px;
            color: white;
        }

        .btn-save {
            background: var(--rosa-vibrante);
            color: white;
            padding: 0.875rem 2rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-save:hover {
            background: var(--rosa-escuro);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 0, 204, 0.3);
        }

        .pills-container {
            margin: 1rem 0;
        }

        .pills-label {
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .pill {
            background: #f3f4f6;
            color: #374151;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }

        .pill:hover {
            background: var(--rosa-vibrante);
            color: white;
            transform: translateY(-1px);
            border-color: var(--rosa-vibrante);
        }

        .template-textarea {
            width: 100%;
            min-height: 100px;
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 0.9rem;
            resize: vertical;
            transition: all 0.3s ease;
            font-family: 'Segoe UI', sans-serif;
        }

        .template-textarea:focus {
            outline: none;
            border-color: var(--rosa-vibrante);
            box-shadow: 0 0 0 3px rgba(255, 0, 204, 0.1);
        }
        
        .btn-test {
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 0.5rem;
        }
        
        .btn-test:hover {
            background: linear-gradient(135deg, #e55a2b, #e8841a);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
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
              <a href="automacao.php" class="active">
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
        <h1 style="margin-bottom: 2rem;">Configurações de Automação</h1>

        <?php if ($message): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: '<?= $message_type ?>',
                    title: '<?= $message_type === "success" ? "Sucesso!" : "Erro!" ?>',
                    text: '<?= addslashes($message) ?>',
                    confirmButtonColor: '#ff00cc'
                });
            });
        </script>
        <?php endif; ?>

        <div class="config-container">
            <!-- 1. Configurações do WhatsApp -->
            <div class="config-card">
                <h3>
                    <span class="material-symbols-sharp">chat</span>
                    Configurações do WhatsApp
                </h3>
                
                <form method="POST" action="processa_automacao.php">
                    <input type="hidden" name="action" value="save_automation_config">
                    <div class="form-group">
                        <label for="whatsapp_token">Token da API WhatsApp</label>
                        <input type="text" id="whatsapp_token" name="whatsapp_token" 
                               value="<?= htmlspecialchars($configuracoes['whatsapp_token'] ?? '') ?>"
                               placeholder="Digite seu token da API WhatsApp">
                    </div>
                    
                    <div class="form-group">
                        <label for="whatsapp_instancia">ID da Instância</label>
                        <input type="text" id="whatsapp_instancia" name="whatsapp_instancia" 
                               value="<?= htmlspecialchars($configuracoes['whatsapp_instancia'] ?? '') ?>"
                               placeholder="Digite o ID da sua instância">
                    </div>
                    
                    <div class="switch-container <?= (isset($configuracoes['whatsapp_enabled']) && $configuracoes['whatsapp_enabled'] == '1') ? 'active' : '' ?>">
                        <span class="switch-label">
                            Ativar WhatsApp
                            <span class="status-indicator <?= (isset($configuracoes['whatsapp_enabled']) && $configuracoes['whatsapp_enabled'] == '1') ? 'status-active' : 'status-inactive' ?>">
                                <?= (isset($configuracoes['whatsapp_enabled']) && $configuracoes['whatsapp_enabled'] == '1') ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </span>
                        <label class="toggle-switch">
                            <input type="hidden" name="whatsapp_enabled" value="0">
                            <input type="checkbox" name="whatsapp_enabled" value="1"
                                   <?= (isset($configuracoes['whatsapp_enabled']) && ($configuracoes['whatsapp_enabled'] == '1' || $configuracoes['whatsapp_enabled'] === 1)) ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn-save">
                        <span class="material-symbols-sharp">save</span>
                        Salvar Configurações WhatsApp
                    </button>
                </form>
            </div>

            <!-- 2. Alertas de Inventário -->
            <div class="config-card">
                <h3>
                    <span class="material-symbols-sharp">inventory</span>
                    Alertas de Estoque
                </h3>
                
                <form method="POST" action="processa_automacao.php">
                    <input type="hidden" name="action" value="save_automation_config">
                    <div class="form-group">
                        <label for="stock_critico_limite">Limite Crítico de Estoque</label>
                        <input type="number" id="stock_critico_limite" name="stock_critico_limite" 
                               value="<?= htmlspecialchars($configuracoes['stock_critico_limite'] ?? '5') ?>"
                               placeholder="Quantidade mínima em estoque" min="1">
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_email">Email para Alertas</label>
                        <input type="email" id="smtp_email" name="smtp_email" 
                               value="<?= htmlspecialchars($configuracoes['smtp_email'] ?? 'dznailsofficial@gmail.com.br') ?>"
                               placeholder="dznailsofficial@gmail.com.br">
                    </div>
                    
                    <!-- Botão de Teste de E-mail -->
                    <div class="form-group">
                        <button type="button" class="btn-test" onclick="testarEmail()">
                            <span class="material-symbols-sharp">email</span>
                            Enviar E-mail de Teste Agora
                        </button>
                    </div>
                    
                    <div class="switch-container <?= (isset($configuracoes['trigger_stock']) && $configuracoes['trigger_stock'] == '1') ? 'active' : '' ?>">
                        <span class="switch-label">
                            Ativar Alertas de Estoque
                            <span class="status-indicator <?= (isset($configuracoes['trigger_stock']) && $configuracoes['trigger_stock'] == '1') ? 'status-active' : 'status-inactive' ?>">
                                <?= (isset($configuracoes['trigger_stock']) && $configuracoes['trigger_stock'] == '1') ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </span>
                        <label class="toggle-switch">
                            <input type="hidden" name="trigger_stock" value="0">
                            <input type="checkbox" name="trigger_stock" value="1"
                                   <?= (isset($configuracoes['trigger_stock']) && ($configuracoes['trigger_stock'] == '1' || $configuracoes['trigger_stock'] === 1)) ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn-save">
                        <span class="material-symbols-sharp">save</span>
                        Salvar Alertas de Estoque
                    </button>
                </form>
            </div>

            <!-- 3. Relatórios Automáticos -->
            <div class="config-card">
                <h3>
                    <span class="material-symbols-sharp">mail</span>
                    Configuração SMTP
                </h3>
                
                <form method="POST" action="processa_automacao.php">
                    <input type="hidden" name="action" value="save_automation_config">
                    <div class="form-group">
                        <label for="smtp_host">Host SMTP</label>
                        <input type="text" id="smtp_host" name="smtp_host" 
                               value="<?= htmlspecialchars($configuracoes['smtp_host'] ?? 'smtp.gmail.com') ?>"
                               placeholder="smtp.gmail.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_porta">Porta SMTP</label>
                        <input type="number" id="smtp_porta" name="smtp_porta" 
                               value="<?= htmlspecialchars($configuracoes['smtp_porta'] ?? '587') ?>"
                               placeholder="587">
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_senha">Senha SMTP</label>
                        <input type="password" id="smtp_senha" name="smtp_senha" 
                               value="<?= htmlspecialchars($configuracoes['smtp_senha'] ?? '') ?>"
                               placeholder="Senha do email SMTP">
                    </div>
                    
                    <div class="form-group">
                        <label for="relatorio_diario_hora">Horário de Envio dos Relatórios</label>
                        <input type="time" id="relatorio_diario_hora" name="relatorio_diario_hora" 
                               value="<?= $configuracoes['relatorio_diario_hora'] ?? '09:00' ?>">
                    </div>
                    
                    <div class="switch-container <?= (isset($configuracoes['relatorio_diario_enabled']) && $configuracoes['relatorio_diario_enabled'] == '1') ? 'active' : '' ?>">
                        <span class="switch-label">
                            Ativar Relatórios Automáticos
                            <span class="status-indicator <?= (isset($configuracoes['relatorio_diario_enabled']) && $configuracoes['relatorio_diario_enabled'] == '1') ? 'status-active' : 'status-inactive' ?>">
                                <?= (isset($configuracoes['relatorio_diario_enabled']) && $configuracoes['relatorio_diario_enabled'] == '1') ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </span>
                        <label class="toggle-switch">
                            <input type="hidden" name="relatorio_diario_enabled" value="0">
                            <input type="checkbox" name="relatorio_diario_enabled" value="1"
                                   <?= (isset($configuracoes['relatorio_diario_enabled']) && ($configuracoes['relatorio_diario_enabled'] == '1' || $configuracoes['relatorio_diario_enabled'] === 1)) ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn-save">
                        <span class="material-symbols-sharp">save</span>
                        Salvar Configuração SMTP
                    </button>
                </form>
            </div>

            <!-- 4. Template de Mensagem (Específico para Estoque) -->
            <div class="config-card">
                <h3>
                    <span class="material-symbols-sharp">edit_note</span>
                    Template de Alerta de Estoque
                </h3>
                
                <form method="POST" action="processa_automacao.php">
                    <input type="hidden" name="action" value="save_automation_config">
                    <div class="form-group">
                        <label for="stock_message">Template da Mensagem de Alerta</label>
                        
                        <!-- Pills (Tags) clicáveis -->
                        <div class="pills-container">
                            <div class="pills-label">Clique nas variáveis para inserir:</div>
                            <div class="pills">
                                <span class="pill" onclick="insertTag('stock_message', '{produto_nome}')">produto_nome</span>
                                <span class="pill" onclick="insertTag('stock_message', '{produto_codigo}')">produto_codigo</span>
                                <span class="pill" onclick="insertTag('stock_message', '{quantidade_atual}')">quantidade_atual</span>
                                <span class="pill" onclick="insertTag('stock_message', '{limite_critico}')">limite_critico</span>
                                <span class="pill" onclick="insertTag('stock_message', '{data_atual}')">data_atual</span>
                            </div>
                        </div>
                        
                        <textarea class="template-textarea" id="stock_message" name="stock_message" 
                                  placeholder="Digite o template para alertas de estoque..."><?= htmlspecialchars($configuracoes['stock_message'] ?? '⚠️ ALERTA DE ESTOQUE CRÍTICO!\n\nProduto: {produto_nome} (Cód: {produto_codigo})\nQuantidade atual: {quantidade_atual}\nLimite crítico: {limite_critico}\nData: {data_atual}\n\nFavor reabastecer urgentemente.') ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn-save">
                        <span class="material-symbols-sharp">save</span>
                        Salvar Template
                    </button>
                </form>
            </div>
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
<script src="../../js/dashboard.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('darkTheme');
    if (savedTheme === 'true') {
        document.body.classList.add('dark-theme-variables');
    }
});

// Função para testar envio de e-mail
function testarEmail() {
    const emailField = document.getElementById('smtp_email');
    const email = emailField.value;
    
    if (!email) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção!',
            text: 'Por favor, preencha o campo de e-mail antes de testar.',
            confirmButtonColor: '#ff00cc'
        });
        return;
    }
    
    // Mostrar loading
    Swal.fire({
        title: 'Enviando e-mail de teste...',
        text: 'Aguarde enquanto testamos a configuração SMTP',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Preparar dados para teste
    const formData = new FormData();
    formData.append('action', 'test_email');
    formData.append('email_destino', email);
    formData.append('smtp_host', document.getElementById('smtp_host').value || 'smtp.gmail.com');
    formData.append('smtp_porta', document.getElementById('smtp_porta').value || '587');
    formData.append('smtp_email', email);
    formData.append('smtp_senha', document.getElementById('smtp_senha').value);
    
    fetch('teste_email.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('📧 Email test response status:', response.status);
        console.log('📧 Email test content-type:', response.headers.get('content-type'));
        
        return response.text(); // Primeiro pegar como texto
    })
    .then(text => {
        console.log('📧 Email test raw response:', text);
        
        // Tentar fazer parse do JSON
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('📧 Resposta não é JSON válido:', text);
            throw new Error('Resposta inválida do servidor de email: ' + text.substring(0, 100));
        }
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '✅ E-mail Enviado!',
                text: data.message,
                confirmButtonColor: '#ff00cc',
                timer: 4000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: '❌ Erro no Envio!',
                text: data.message || 'Erro ao enviar e-mail. Verifique as configurações SMTP.',
                confirmButtonColor: '#ff00cc'
            });
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro de Conexão!',
            text: 'Erro ao conectar com o servidor. Tente novamente.',
            confirmButtonColor: '#ff00cc'
        });
    });
}

// Função para inserir tags nas mensagens
function insertTag(textareaId, tag) {
    const textarea = document.getElementById(textareaId);
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    
    const before = text.substring(0, start);
    const after = text.substring(end, text.length);
    
    textarea.value = before + tag + after;
    textarea.focus();
    textarea.setSelectionRange(start + tag.length, start + tag.length);
    
    // Animação visual da pill clicada
    event.target.style.transform = 'scale(1.1)';
    setTimeout(() => {
        event.target.style.transform = 'scale(1)';
    }, 200);
}

// Adicionar efeitos visuais aos toggle switches
document.querySelectorAll('.toggle-switch input').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const card = this.closest('.config-card');
        if (this.checked) {
            card.style.borderColor = '#ff00cc';
            card.style.boxShadow = '0 12px 35px rgba(255, 0, 204, 0.15)';
        } else {
            card.style.borderColor = '#e2e8f0';
            card.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.1)';
        }
    });
});

// Inicializar estado visual dos cards ativos
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 Inicializando configurações dos toggles...');
    
    // Função para aplicar estilo ativo nos cards
    function aplicarEstiloAtivo() {
        document.querySelectorAll('.toggle-switch input[type="checkbox"]').forEach(toggle => {
            const card = toggle.closest('.config-card');
            const switchContainer = toggle.closest('.switch-container');
            const statusIndicator = switchContainer ? switchContainer.querySelector('.status-indicator') : null;
            const isChecked = toggle.checked;
            
            console.log(`Toggle ${toggle.name}: ${isChecked ? 'ATIVO' : 'INATIVO'}`);
            
            if (card) {
                if (isChecked) {
                    card.style.borderColor = '#ff00cc';
                    card.style.boxShadow = '0 12px 35px rgba(255, 0, 204, 0.15)';
                    if (switchContainer) switchContainer.classList.add('active');
                    if (statusIndicator) {
                        statusIndicator.className = 'status-indicator status-active';
                        statusIndicator.textContent = 'Ativo';
                    }
                } else {
                    card.style.borderColor = '#e2e8f0';
                    card.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.1)';
                    if (switchContainer) switchContainer.classList.remove('active');
                    if (statusIndicator) {
                        statusIndicator.className = 'status-indicator status-inactive';
                        statusIndicator.textContent = 'Inativo';
                    }
                }
                
                console.log('Card estilizado:', card.querySelector('h3').innerText, isChecked ? 'ATIVO' : 'INATIVO');
            }
        });
    }
    
    // Aplicar estilo inicial após um pequeno delay
    setTimeout(aplicarEstiloAtivo, 200);
    
    // Tornar função global para uso em outros lugares
    window.aplicarEstiloAtivo = aplicarEstiloAtivo;
    
    // Aplicar estilo quando mudar o toggle
    document.querySelectorAll('.toggle-switch input[type="checkbox"]').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const card = this.closest('.config-card');
            const switchContainer = this.closest('.switch-container');
            const statusIndicator = switchContainer.querySelector('.status-indicator');
            
            console.log(`Toggle ${this.name} alterado para:`, this.checked ? 'ATIVO' : 'INATIVO');
            
            // Atualizar estilo do card
            if (this.checked) {
                card.style.borderColor = '#ff00cc';
                card.style.boxShadow = '0 12px 35px rgba(255, 0, 204, 0.15)';
                switchContainer.classList.add('active');
                statusIndicator.className = 'status-indicator status-active';
                statusIndicator.textContent = 'Ativo';
                console.log('✅ Card ativado via toggle:', card.querySelector('h3').innerText);
            } else {
                card.style.borderColor = '#e2e8f0';
                card.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.1)';
                switchContainer.classList.remove('active');
                statusIndicator.className = 'status-indicator status-inactive';
                statusIndicator.textContent = 'Inativo';
                console.log('❌ Card desativado via toggle:', card.querySelector('h3').innerText);
            }
        });
    });
    
    // Debug: Listar todos os toggles e seus estados
    console.log('📋 Estados atuais dos toggles:');
    document.querySelectorAll('.toggle-switch input[type="checkbox"]').forEach(toggle => {
        console.log(`- ${toggle.name || toggle.id}: ${toggle.checked ? '✅ ATIVO' : '❌ INATIVO'}`);
    });
});

// Animação de hover nos botões
document.querySelectorAll('.btn-save').forEach(btn => {
    btn.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-2px)';
    });
    
    btn.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});

// Interceptar envio dos formulários para mostrar resposta visual
document.addEventListener('DOMContentLoaded', function() {
    // Aguardar que tudo carregue antes de adicionar os listeners
    setTimeout(function() {
        document.querySelectorAll('form').forEach((form, index) => {
            console.log(`Form ${index + 1} encontrado:`, form.action);
            
            form.addEventListener('submit', function(e) {
                e.preventDefault(); // Impedir envio padrão
                e.stopPropagation(); // Impedir propagação
                
                console.log(`Formulário ${index + 1} interceptado!`); // Debug
                
                const formData = new FormData(this);
                const button = this.querySelector('.btn-save');
                const originalText = button.innerHTML;
                const actionUrl = this.getAttribute('action') || 'processa_automacao.php';
                
                // Log para debug
                console.log(`📝 Formulário ${index + 1} - URL:`, actionUrl);
                console.log(`📋 Dados do formulário:`, Array.from(formData.entries()).map(([key, value]) => `${key}: ${value}`).join(', '));
                
                // Mostrar loading no botão
                button.innerHTML = '<span class="material-symbols-sharp">hourglass_empty</span> Salvando...';
                button.disabled = true;
                
                fetch(actionUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Content-Type:', response.headers.get('content-type'));
                    
                    return response.text(); // Primeiro pegar como texto
                })
                .then(text => {
                    console.log('Response text:', text);
                    
                    // Tentar fazer parse do JSON
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        // Se não for JSON válido, tratar como erro
                        console.error('Resposta não é JSON válido:', text);
                        throw new Error('Resposta inválida do servidor: ' + text.substring(0, 100));
                    }
                    
                    console.log('Data parsed:', data);
                    
                    // Restaurar botão
                    button.innerHTML = originalText;
                    button.disabled = false;
                    
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: data.message,
                            confirmButtonColor: '#ff00cc',
                            timer: 3000,
                            showConfirmButton: false
                        });
                        
                        // Atualizar indicadores visuais se necessário
                        setTimeout(() => {
                            if (typeof window.aplicarEstiloAtivo === 'function') {
                                window.aplicarEstiloAtivo();
                            }
                        }, 500);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: data.message,
                            confirmButtonColor: '#ff00cc'
                        });
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    
                    // Restaurar botão
                    button.innerHTML = originalText;
                    button.disabled = false;
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Erro de conexão. Tente novamente.',
                        confirmButtonColor: '#ff00cc'
                    });
                });
                
                return false; // Garantir que não submete
            });
        });
    }, 500); // Aguardar 500ms para garantir que tudo carregou
});
</script>
 </body>
</html>