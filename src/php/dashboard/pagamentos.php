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

// Verificar e criar tabela payment_settings se não existir
function createPaymentSettingsTable() {
    global $conexao;
    
    // Verificar se a tabela existe
    $check_table = $conexao->query("SHOW TABLES LIKE 'payment_settings'");
    if ($check_table->num_rows == 0) {
        // Criar a tabela
        $sql = "CREATE TABLE payment_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            gateway_provider ENUM('mercadopago', 'stripe', 'paypal') DEFAULT 'mercadopago',
            gateway_active BOOLEAN DEFAULT FALSE,
            public_key TEXT,
            secret_key TEXT,
            client_id TEXT,
            client_secret TEXT,
            environment ENUM('sandbox', 'production') DEFAULT 'sandbox',
            method_pix BOOLEAN DEFAULT FALSE,
            method_credit_card BOOLEAN DEFAULT FALSE,
            method_boleto BOOLEAN DEFAULT FALSE,
            payment_currency ENUM('BRL', 'USD', 'EUR') DEFAULT 'BRL',
            payment_gateway_id VARCHAR(100),
            min_value_pix DECIMAL(10,2) DEFAULT 5.00,
            min_value_card DECIMAL(10,2) DEFAULT 10.00,
            min_value_boleto DECIMAL(10,2) DEFAULT 20.00,
            max_installments INT DEFAULT 12,
            free_installments INT DEFAULT 1,
            interest_rate DECIMAL(5,2) DEFAULT 2.50,
            payment_maintenance BOOLEAN DEFAULT FALSE,
            maintenance_message TEXT,
            logs_enabled BOOLEAN DEFAULT FALSE,
            logs_level ENUM('basico', 'detalhado') DEFAULT 'basico',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conexao->query($sql) === TRUE) {
            // Inserir configuração padrão
            $conexao->query("INSERT INTO payment_settings (id, gateway_provider, environment) VALUES (1, 'mercadopago', 'sandbox')");
            return "Tabela payment_settings criada com sucesso!";
        } else {
            return "Erro ao criar tabela: " . $conexao->error;
        }
    }
    return null;
}

// Criar tabela se necessário
$table_creation_message = createPaymentSettingsTable();

// Atualizar estrutura da tabela se necessário
function updatePaymentSettingsTable() {
    global $conexao;
    
    $updates_made = [];
    
    // Lista de novos campos para adicionar
    $new_columns = [
        'payment_currency' => "ENUM('BRL', 'USD', 'EUR') DEFAULT 'BRL'",
        'payment_gateway_id' => "VARCHAR(100)",
        'min_value_pix' => "DECIMAL(10,2) DEFAULT 5.00",
        'min_value_card' => "DECIMAL(10,2) DEFAULT 10.00", 
        'min_value_boleto' => "DECIMAL(10,2) DEFAULT 20.00",
        'max_installments' => "INT DEFAULT 12",
        'free_installments' => "INT DEFAULT 1",
        'interest_rate' => "DECIMAL(5,2) DEFAULT 2.50",
        'payment_maintenance' => "BOOLEAN DEFAULT FALSE",
        'maintenance_message' => "TEXT",
        'logs_enabled' => "BOOLEAN DEFAULT FALSE",
        'logs_level' => "ENUM('basico', 'detalhado') DEFAULT 'basico'"
    ];
    
    // Verificar quais colunas existem
    $result = $conexao->query("DESCRIBE payment_settings");
    $existing_columns = [];
    while ($row = $result->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
    }
    
    // Adicionar colunas que não existem
    foreach ($new_columns as $column_name => $column_def) {
        if (!in_array($column_name, $existing_columns)) {
            $sql = "ALTER TABLE payment_settings ADD COLUMN $column_name $column_def";
            if ($conexao->query($sql) === TRUE) {
                $updates_made[] = $column_name;
            }
        }
    }
    
    if (!empty($updates_made)) {
        return "Tabela atualizada com " . count($updates_made) . " novos campos.";
    }
    
    return null;
}

$table_update_message = updatePaymentSettingsTable();

// Limpar configurações antigas da tabela settings
function cleanOldPaymentSettings() {
    global $conexao;
    
    // Lista específica de chaves de pagamento para remover
    $payment_keys = [
        'payment_gateway',
        'payment_gateway_active', 
        'payment_public_key',
        'payment_access_token',
        'payment_client_id',
        'payment_client_secret',
        'payment_environment',
        'payment_method_pix',
        'payment_method_credit_card',
        'payment_method_boleto'
    ];
    
    // Verificar se existem configurações antigas
    $check = $conexao->query("SELECT COUNT(*) as count FROM settings WHERE setting_key LIKE 'payment_%'");
    $count = $check->fetch_assoc()['count'];
    
    if ($count > 0) {
        // Remover configurações específicas
        $keys_string = "'" . implode("','", $payment_keys) . "'";
        $sql = "DELETE FROM settings WHERE setting_key IN ($keys_string)";
        $conexao->query($sql);
        
        // Verificar quantas foram removidas
        $after_check = $conexao->query("SELECT COUNT(*) as count FROM settings WHERE setting_key LIKE 'payment_%'");
        $remaining = $after_check->fetch_assoc()['count'];
        
        $removed = $count - $remaining;
        return "Removidas $removed configurações antigas da tabela settings.";
    }
    return null;
}

$cleanup_message = cleanOldPaymentSettings();

// Função auxiliar para configurações extras
function savePaymentSetting($key, $value) {
    global $conexao;
    $stmt = $conexao->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
    $stmt->bind_param("sss", $key, $value, $value);
    return $stmt->execute();
}

function getPaymentSetting($key, $default = '') {
    global $conexao;
    $stmt = $conexao->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

// Função para salvar configurações de pagamento
function savePaymentSettings($data) {
    global $conexao;
    
    // SQL simplificado
    $sql = "INSERT INTO payment_settings (
        id, gateway_provider, gateway_active, public_key, secret_key, 
        client_id, client_secret, environment, method_pix, 
        method_credit_card, method_boleto, payment_currency, payment_gateway_id,
        min_value_pix, min_value_card, min_value_boleto, max_installments,
        free_installments, interest_rate, payment_maintenance, maintenance_message,
        logs_enabled, logs_level
    ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        gateway_provider = VALUES(gateway_provider),
        gateway_active = VALUES(gateway_active),
        public_key = VALUES(public_key),
        secret_key = VALUES(secret_key),
        client_id = VALUES(client_id),
        client_secret = VALUES(client_secret),
        environment = VALUES(environment),
        method_pix = VALUES(method_pix),
        method_credit_card = VALUES(method_credit_card),
        method_boleto = VALUES(method_boleto),
        payment_currency = VALUES(payment_currency),
        payment_gateway_id = VALUES(payment_gateway_id),
        min_value_pix = VALUES(min_value_pix),
        min_value_card = VALUES(min_value_card),
        min_value_boleto = VALUES(min_value_boleto),
        max_installments = VALUES(max_installments),
        free_installments = VALUES(free_installments),
        interest_rate = VALUES(interest_rate),
        payment_maintenance = VALUES(payment_maintenance),
        maintenance_message = VALUES(maintenance_message),
        logs_enabled = VALUES(logs_enabled),
        logs_level = VALUES(logs_level),
        updated_at = NOW()";
    
    $stmt = $conexao->prepare($sql);
    
    // 22 parâmetros (sem o id que é fixo como 1)
    return $stmt->bind_param("sisssssiiissdddiidisis",
        $data['gateway_provider'],      // s
        $data['gateway_active'],        // i
        $data['public_key'],           // s
        $data['secret_key'],           // s
        $data['client_id'],            // s
        $data['client_secret'],        // s
        $data['environment'],          // s
        $data['method_pix'],           // i
        $data['method_credit_card'],   // i
        $data['method_boleto'],        // i
        $data['payment_currency'],     // s
        $data['payment_gateway_id'],   // s
        $data['min_value_pix'],        // d
        $data['min_value_card'],       // d
        $data['min_value_boleto'],     // d
        $data['max_installments'],     // i
        $data['free_installments'],    // i
        $data['interest_rate'],        // d
        $data['payment_maintenance'],  // i
        $data['maintenance_message'],  // s
        $data['logs_enabled'],         // i
        $data['logs_level']            // s
    ) && $stmt->execute();
}

// Função para obter configurações de pagamento
function getPaymentSettings() {
    global $conexao;
    $stmt = $conexao->prepare("SELECT * FROM payment_settings WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row;
    }
    
    // Retornar configurações padrão se não existir
    return [
        'gateway_provider' => 'mercadopago',
        'gateway_active' => 0,
        'public_key' => '',
        'secret_key' => '',
        'client_id' => '',
        'client_secret' => '',
        'environment' => 'sandbox',
        'method_pix' => 0,
        'method_credit_card' => 0,
        'method_boleto' => 0,
        'payment_currency' => 'BRL',
        'payment_gateway_id' => '',
        'min_value_pix' => '5.00',
        'min_value_card' => '10.00',
        'min_value_boleto' => '20.00',
        'max_installments' => '12',
        'free_installments' => '1',
        'interest_rate' => '2.50',
        'payment_maintenance' => 0,
        'maintenance_message' => 'Pagamentos temporariamente indisponíveis. Tente novamente mais tarde.',
        'logs_enabled' => 0,
        'logs_level' => 'basico',
        'created_at' => null,
        'updated_at' => null
    ];
}

// Processar formulário
if ($_POST && isset($_POST['save_payment_config'])) {
    try {
        $payment_data = [
            'gateway_provider' => $_POST['payment_gateway'] ?? 'mercadopago',
            'gateway_active' => ($_POST['gateway_active'] ?? '0') === '1' ? 1 : 0,
            'public_key' => $_POST['public_key'] ?? '',
            'secret_key' => $_POST['access_token'] ?? '',
            'client_id' => $_POST['client_id'] ?? '',
            'client_secret' => $_POST['client_secret'] ?? '',
            'environment' => $_POST['environment'] ?? 'sandbox',
            'method_pix' => isset($_POST['method_pix']) ? 1 : 0,
            'method_credit_card' => isset($_POST['method_credit_card']) ? 1 : 0,
            'method_boleto' => isset($_POST['method_boleto']) ? 1 : 0,
            'payment_currency' => $_POST['payment_currency'] ?? 'BRL',
            'payment_gateway_id' => $_POST['payment_gateway_id'] ?? '',
            'min_value_pix' => (float)($_POST['min_value_pix'] ?? 5.00),
            'min_value_card' => (float)($_POST['min_value_card'] ?? 10.00),
            'min_value_boleto' => (float)($_POST['min_value_boleto'] ?? 20.00),
            'max_installments' => (int)($_POST['max_installments'] ?? 12),
            'free_installments' => (int)($_POST['free_installments'] ?? 1),
            'interest_rate' => (float)($_POST['interest_rate'] ?? 2.5),
            'payment_maintenance' => isset($_POST['payment_maintenance']) && $_POST['payment_maintenance'] === '1' ? 1 : 0,
            'maintenance_message' => $_POST['maintenance_message'] ?? '',
            'logs_enabled' => isset($_POST['logs_enabled']) ? 1 : 0,
            'logs_level' => $_POST['logs_level'] ?? 'basico'
        ];
        
        if (savePaymentSettings($payment_data)) {
            $success_message = "Configurações de pagamento salvas com sucesso!";
        } else {
            $error_message = "Erro ao salvar configurações no banco de dados.";
        }
    } catch (Exception $e) {
        $error_message = "Erro ao salvar configurações: " . $e->getMessage();
    }
}

// Carregar configurações atuais
$payment_config = getPaymentSettings();
$gateway = $payment_config['gateway_provider'];
$gateway_active = $payment_config['gateway_active'] == 1;
$public_key = $payment_config['public_key'];
$access_token = $payment_config['secret_key'];
$client_id = $payment_config['client_id'];
$client_secret = $payment_config['client_secret'];
$environment = $payment_config['environment'];
$method_pix = $payment_config['method_pix'] == 1;
$method_credit_card = $payment_config['method_credit_card'] == 1;
$method_boleto = $payment_config['method_boleto'] == 1;

// Carregar configurações adicionais da tabela principal
$payment_currency = $payment_config['payment_currency'] ?? 'BRL';
$payment_gateway_id = $payment_config['payment_gateway_id'] ?? '';
$min_value_pix = $payment_config['min_value_pix'] ?? '5.00';
$min_value_card = $payment_config['min_value_card'] ?? '10.00';
$min_value_boleto = $payment_config['min_value_boleto'] ?? '20.00';
$max_installments = $payment_config['max_installments'] ?? '12';
$free_installments = $payment_config['free_installments'] ?? '1';
$interest_rate = $payment_config['interest_rate'] ?? '2.5';
$payment_maintenance = ($payment_config['payment_maintenance'] ?? 0) == 1;
$maintenance_message = $payment_config['maintenance_message'] ?? 'Pagamentos temporariamente indisponíveis. Tente novamente mais tarde.';
$logs_enabled = ($payment_config['logs_enabled'] ?? 0) == 1;
$logs_level = $payment_config['logs_level'] ?? 'basico';

// Gerar URL do webhook
$webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/admin-teste/webhooks/pagamento.php";
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

    <title>Configurações de Pagamento</title>
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
              <a href="pagamentos.php" class="active">
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
        <h1>Configurações de Pagamento</h1>

        <!-- Mensagens de Feedback -->
        <?php if ($table_creation_message): ?>
          <div class="alert-success" id="table-message">
            <span class="material-symbols-sharp">build</span>
            <?= htmlspecialchars($table_creation_message) ?>
          </div>
        <?php endif; ?>

        <?php if ($table_update_message): ?>
          <div class="alert-success" id="table-update-message">
            <span class="material-symbols-sharp">update</span>
            <?= htmlspecialchars($table_update_message) ?>
          </div>
        <?php endif; ?>

        <?php if ($cleanup_message): ?>
          <div class="alert-success" id="cleanup-message">
            <span class="material-symbols-sharp">cleaning_services</span>
            <?= htmlspecialchars($cleanup_message) ?>
          </div>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
          <div class="alert-success" id="success-message">
            <span class="material-symbols-sharp">check_circle</span>
            <?= htmlspecialchars($success_message) ?>
          </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
          <div class="alert-error" id="error-message">
            <span class="material-symbols-sharp">error</span>
            <?= htmlspecialchars($error_message) ?>
          </div>
        <?php endif; ?>

        <form method="POST" class="payment-form">
          <!-- Gateway -->
          <div class="section">
            <h2><span class="material-symbols-sharp">payments</span> Gateway de Pagamento</h2>
            <div class="field">
              <label>Provedor</label>
              <select name="payment_gateway" required>
                <option value="mercadopago" <?= $gateway == 'mercadopago' ? 'selected' : '' ?>>Mercado Pago</option>
                <option value="stripe" <?= $gateway == 'stripe' ? 'selected' : '' ?>>Stripe</option>
                <option value="paypal" <?= $gateway == 'paypal' ? 'selected' : '' ?>>PayPal</option>
              </select>
            </div>
            <div class="field">
              <label>Status</label>
              <div class="toggle-group">
                <label class="toggle <?= $gateway_active ? 'active' : '' ?>">
                  <input type="radio" name="gateway_active" value="1" <?= $gateway_active ? 'checked' : '' ?>>
                  Ativo
                </label>
                <label class="toggle <?= !$gateway_active ? 'active' : '' ?>">
                  <input type="radio" name="gateway_active" value="0" <?= !$gateway_active ? 'checked' : '' ?>>
                  Inativo
                </label>
              </div>
            </div>
          </div>

          <!-- Credenciais -->
          <div class="section">
            <h2><span class="material-symbols-sharp">key</span> Credenciais</h2>
            <div class="form-row">
              <div class="form-group">
                <label for="public_key">Public Key <span class="required">*</span></label>
                <div class="password-field">
                  <input type="password" id="public_key" name="public_key" value="<?= htmlspecialchars($public_key) ?>" placeholder="pk_test_...">
                  <button type="button" class="toggle-password" data-target="public_key">
                    <span class="material-symbols-sharp">visibility</span>
                  </button>
                </div>
              </div>
              <div class="form-group">
                <label for="access_token">Secret Key <span class="required">*</span></label>
                <div class="password-field">
                  <input type="password" id="access_token" name="access_token" value="<?= htmlspecialchars($access_token) ?>" placeholder="sk_test_...">
                  <button type="button" class="toggle-password" data-target="access_token">
                    <span class="material-symbols-sharp">visibility</span>
                  </button>
                </div>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="client_id">Client ID</label>
                <div class="password-field">
                  <input type="password" id="client_id" name="client_id" value="<?= htmlspecialchars($client_id) ?>" placeholder="Opcional">
                  <button type="button" class="toggle-password" data-target="client_id">
                    <span class="material-symbols-sharp">visibility</span>
                  </button>
                </div>
              </div>
              <div class="form-group">
                <label for="client_secret">Client Secret</label>
                <div class="password-field">
                  <input type="password" id="client_secret" name="client_secret" value="<?= htmlspecialchars($client_secret) ?>" placeholder="Opcional">
                  <button type="button" class="toggle-password" data-target="client_secret">
                    <span class="material-symbols-sharp">visibility</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Ambiente -->
          <div class="section">
            <h2><span class="material-symbols-sharp">public</span> Ambiente</h2>
            <div class="radio-group">
              <label class="radio <?= $environment == 'sandbox' ? 'active' : '' ?>">
                <input type="radio" name="environment" value="sandbox" <?= $environment == 'sandbox' ? 'checked' : '' ?>>
                <div>
                  <strong>Sandbox</strong>
                  <small>Teste</small>
                </div>
              </label>
              <label class="radio <?= $environment == 'production' ? 'active' : '' ?>">
                <input type="radio" name="environment" value="production" <?= $environment == 'production' ? 'checked' : '' ?>>
                <div>
                  <strong>Produção</strong>
                  <small>Real</small>
                </div>
              </label>
            </div>
          </div>

          <!-- Métodos -->
          <div class="section">
            <h2><span class="material-symbols-sharp">credit_card</span> Métodos de Pagamento</h2>
            <div class="checkbox-group">
              <label class="checkbox">
                <input type="checkbox" name="method_pix" <?= $method_pix ? 'checked' : '' ?>>
                <span class="checkmark"></span>
                PIX
              </label>
              <label class="checkbox">
                <input type="checkbox" name="method_credit_card" <?= $method_credit_card ? 'checked' : '' ?>>
                <span class="checkmark"></span>
                Cartão de Crédito
              </label>
              <label class="checkbox">
                <input type="checkbox" name="method_boleto" <?= $method_boleto ? 'checked' : '' ?>>
                <span class="checkmark"></span>
                Boleto
              </label>
            </div>
          </div>

          <!-- Configurações Gerais -->
          <div class="section">
            <h2><span class="material-symbols-sharp">settings</span> Configurações Gerais</h2>
            <div class="form-row">
              <div class="form-group">
                <label for="payment_currency">Moeda Padrão</label>
                <select name="payment_currency" id="payment_currency">
                  <option value="BRL" <?= $payment_currency == 'BRL' ? 'selected' : '' ?>>Real (BRL)</option>
                  <option value="USD" <?= $payment_currency == 'USD' ? 'selected' : '' ?>>Dólar (USD)</option>
                  <option value="EUR" <?= $payment_currency == 'EUR' ? 'selected' : '' ?>>Euro (EUR)</option>
                </select>
              </div>
              <div class="form-group">
                <label for="payment_gateway_id">ID do Gateway</label>
                <input type="text" id="payment_gateway_id" name="payment_gateway_id" value="<?= htmlspecialchars($payment_gateway_id) ?>" placeholder="Ex: my_store_mp">
              </div>
            </div>
          </div>

          <!-- Valores Mínimos -->
          <div class="section">
            <h2><span class="material-symbols-sharp">attach_money</span> Valores Mínimos por Método</h2>
            <div class="form-row">
              <div class="form-group">
                <label for="min_value_pix">PIX (R$)</label>
                <input type="number" step="0.01" id="min_value_pix" name="min_value_pix" value="<?= htmlspecialchars($min_value_pix) ?>" placeholder="5.00">
              </div>
              <div class="form-group">
                <label for="min_value_card">Cartão de Crédito (R$)</label>
                <input type="number" step="0.01" id="min_value_card" name="min_value_card" value="<?= htmlspecialchars($min_value_card) ?>" placeholder="10.00">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="min_value_boleto">Boleto (R$)</label>
                <input type="number" step="0.01" id="min_value_boleto" name="min_value_boleto" value="<?= htmlspecialchars($min_value_boleto) ?>" placeholder="20.00">
              </div>
              <div class="form-group">
                <!-- Campo vazio para manter o grid -->
              </div>
            </div>
          </div>

          <!-- Parcelamento -->
          <div class="section">
            <h2><span class="material-symbols-sharp">credit_score</span> Parcelamento (Cartão)</h2>
            <div class="form-row">
              <div class="form-group">
                <label for="max_installments">Parcelas Máximas</label>
                <select name="max_installments" id="max_installments">
                  <option value="1" <?= $max_installments == '1' ? 'selected' : '' ?>>1x</option>
                  <option value="3" <?= $max_installments == '3' ? 'selected' : '' ?>>3x</option>
                  <option value="6" <?= $max_installments == '6' ? 'selected' : '' ?>>6x</option>
                  <option value="12" <?= $max_installments == '12' ? 'selected' : '' ?>>12x</option>
                  <option value="18" <?= $max_installments == '18' ? 'selected' : '' ?>>18x</option>
                  <option value="24" <?= $max_installments == '24' ? 'selected' : '' ?>>24x</option>
                </select>
              </div>
              <div class="form-group">
                <label for="free_installments">Sem Juros Até</label>
                <select name="free_installments" id="free_installments">
                  <option value="1" <?= $free_installments == '1' ? 'selected' : '' ?>>1x</option>
                  <option value="2" <?= $free_installments == '2' ? 'selected' : '' ?>>2x</option>
                  <option value="3" <?= $free_installments == '3' ? 'selected' : '' ?>>3x</option>
                  <option value="6" <?= $free_installments == '6' ? 'selected' : '' ?>>6x</option>
                  <option value="12" <?= $free_installments == '12' ? 'selected' : '' ?>>12x</option>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="interest_rate">Juros por Parcela (%)</label>
                <input type="number" step="0.1" id="interest_rate" name="interest_rate" value="<?= htmlspecialchars($interest_rate) ?>" placeholder="2.5">
              </div>
              <div class="form-group">
                <!-- Campo vazio para manter o grid -->
              </div>
            </div>
          </div>

          <!-- Webhook -->
          <div class="section">
            <h2><span class="material-symbols-sharp">webhook</span> Webhook</h2>
            <div class="field">
              <label for="webhook_url">URL de Notificação</label>
              <div class="webhook-field">
                <input type="text" id="webhook_url" value="<?= htmlspecialchars($webhook_url) ?>" readonly>
                <button type="button" class="copy-webhook-btn" onclick="copyWebhookUrl()">
                  <span class="material-symbols-sharp">content_copy</span>
                </button>
              </div>
              <small class="field-help">Copie esta URL e configure no painel do seu gateway de pagamento</small>
            </div>
          </div>

          <!-- Modo Manutenção -->
          <div class="section">
            <h2><span class="material-symbols-sharp">build</span> Modo Manutenção</h2>
            <div class="field">
              <label>Status dos Pagamentos</label>
              <div class="toggle-group">
                <label class="toggle <?= !$payment_maintenance ? 'active' : '' ?>">
                  <input type="radio" name="payment_maintenance" value="0" <?= !$payment_maintenance ? 'checked' : '' ?>>
                  Habilitado
                </label>
                <label class="toggle <?= $payment_maintenance ? 'active' : '' ?>">
                  <input type="radio" name="payment_maintenance" value="1" <?= $payment_maintenance ? 'checked' : '' ?>>
                  Manutenção
                </label>
              </div>
            </div>
            <div class="field">
              <label for="maintenance_message">Mensagem de Manutenção</label>
              <textarea id="maintenance_message" name="maintenance_message" rows="3" placeholder="Mensagem exibida quando os pagamentos estiverem em manutenção"><?= htmlspecialchars($maintenance_message) ?></textarea>
            </div>
          </div>

          <!-- Logs -->
          <div class="section">
            <h2><span class="material-symbols-sharp">description</span> Logs de Pagamento</h2>
            <div class="form-row">
              <div class="form-group">
                <label for="logs_level">Nível de Detalhamento</label>
                <select name="logs_level" id="logs_level">
                  <option value="basico" <?= $logs_level == 'basico' ? 'selected' : '' ?>>Básico</option>
                  <option value="detalhado" <?= $logs_level == 'detalhado' ? 'selected' : '' ?>>Detalhado</option>
                </select>
              </div>
              <div class="form-group logs-checkbox-group">
                <label class="checkbox logs-checkbox">
                  <input type="checkbox" name="logs_enabled" <?= $logs_enabled ? 'checked' : '' ?>>
                  <span class="checkmark"></span>
                  Ativar Logs de Pagamento
                </label>
              </div>
            </div>
          </div>

          <!-- Salvar -->
          <button type="submit" name="save_payment_config" class="save-btn">
            <span class="material-symbols-sharp">save</span>
            Salvar
          </button>
        </form>

        <style>
          /* Padrão Configurações - Pagamentos */
          .payment-form {
            max-width: 1200px;
            margin: 2rem 0;
          }

          .section {
            background: var(--color-white);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--box-shadow);
            border: 1px solid transparent;
          }

          .section h2 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            color: var(--color-primary);
            font-size: 1.3rem;
            font-weight: 600;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--color-info-light);
          }

          .form-group {
            margin-bottom: 1.5rem;
          }

          .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
          }

          .field label, .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--color-dark-variant);
            font-weight: 500;
          }

          /* TEMA CLARO - Texto sempre preto, ícones rosa */
          body:not(.dark-theme-variables) .section h2 {
            color: #000000 !important;
          }

          body:not(.dark-theme-variables) .section h2 .material-symbols-sharp {
            color: var(--color-primary) !important;
          }

          body:not(.dark-theme-variables) .field label,
          body:not(.dark-theme-variables) .form-group label,
          body:not(.dark-theme-variables) h1 {
            color: #000000 !important;
          }

          body:not(.dark-theme-variables) .field input,
          body:not(.dark-theme-variables) .field select,
          body:not(.dark-theme-variables) .form-group input,
          body:not(.dark-theme-variables) .form-group select {
            color: #000000 !important;
            background: #ffffff !important;
          }

          body:not(.dark-theme-variables) .toggle.active {
            color: #ffffff !important;
          }

          body:not(.dark-theme-variables) .toggle:not(.active) {
            color: #666 !important;
          }

          body:not(.dark-theme-variables) .checkbox {
            color: #000000 !important;
          }

          .required {
            color: var(--color-danger);
          }

          .field input, .field select, .form-group input, .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid var(--color-light);
            border-radius: 8px;
            background: var(--color-white);
            color: var(--color-dark);
            font-size: 1rem;
            transition: all 0.3s ease;
          }

          .field input:focus, .field select:focus, .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(255, 20, 147, 0.1);
          }

          /* Password Field */
          .password-field {
            position: relative;
          }

          .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--color-dark-variant);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 4px;
            transition: all 0.3s ease;
          }

          .toggle-password:hover {
            background: var(--color-light);
            color: var(--color-primary);
          }

          .dark-theme-variables .field input,
          .dark-theme-variables .field select,
          .dark-theme-variables input,
          .dark-theme-variables select {
            background: #2a2a2a !important;
            border: 1px solid #404040 !important;
            color: #ffffff !important;
          }

          .dark-theme-variables .field input:focus,
          .dark-theme-variables .field select:focus {
            background: #2a2a2a !important;
            border: 1px solid var(--color-primary) !important;
          }

          .field input:focus, .field select:focus {
            outline: none;
            border-color: var(--color-primary);
            background: white;
          }

          /* Grid para credenciais */
          .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
          }

          /* Input com botão de olho */
          .input-group {
            position: relative;
          }

          .eye-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            padding: 4px;
            border-radius: 6px;
            transition: all 0.2s ease;
          }

          .eye-btn:hover {
            color: var(--color-primary);
            background: #f5f5f5;
          }

          /* Toggle Status */
          .toggle-group {
            display: flex;
            gap: 0.5rem;
          }

          .toggle {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid #e0e0e0;
            background: #fafafa;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            color: #666;
            transition: all 0.2s ease;
          }

          .dark-theme-variables .toggle,
          .dark-theme-variables .radio,
          .dark-theme-variables .checkbox {
            background: #2a2a2a !important;
            border: 1px solid #404040 !important;
            color: #ffffff !important;
          }

          .toggle input {
            display: none;
          }

          .toggle:hover {
            border-color: var(--color-primary);
          }

          .toggle.active {
            background: var(--color-primary);
            border-color: var(--color-primary);
            color: white;
          }

          /* Radio Group */
          .radio-group {
            display: flex;
            gap: 0.5rem;
          }

          .radio {
            flex: 1;
            padding: 1rem;
            border: 1px solid #e0e0e0;
            background: #fafafa;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
          }



          .radio input {
            display: none;
          }

          .radio:hover {
            border-color: var(--color-primary);
          }

          .radio.active {
            background: var(--color-primary);
            border-color: var(--color-primary);
            color: white;
          }

          .radio strong {
            display: block;
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
          }

          .radio small {
            font-size: 0.8rem;
            opacity: 0.8;
          }

          /* Checkbox Group */
          .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
          }

          .checkbox {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border: 1px solid #e0e0e0;
            background: #fafafa;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 500;
            color: #666;
            transition: all 0.2s ease;
          }



          .checkbox input {
            display: none;
          }

          .checkbox:hover {
            border-color: var(--color-primary);
          }

          .checkbox input:checked + .checkmark {
            background: var(--color-primary);
            border-color: var(--color-primary);
          }

          .checkbox input:checked + .checkmark::after {
            opacity: 1;
          }

          .checkbox input:checked ~ span {
            color: var(--color-primary);
          }

          .checkmark {
            width: 20px;
            height: 20px;
            border: 2px solid #ddd;
            border-radius: 6px;
            background: white;
            position: relative;
            transition: all 0.2s ease;
          }

          .checkmark::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 12px;
            font-weight: bold;
            opacity: 0;
            transition: opacity 0.2s ease;
          }

          /* Botão Salvar */
          .save-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: var(--color-primary);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 1rem;
          }

          .save-btn:hover {
            background: #e01283;
            transform: translateY(-1px);
          }

          /* Alertas */
          .alert-success, .alert-error {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
          }

          .alert-success {
            background: #f0f9ff;
            color: #0369a1;
            border: 1px solid #bae6fd;
          }

          .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
          }

          /* Dark Mode */
          body.dark-theme-variables .section {
            background: var(--color-dark) !important;
            border-color: var(--color-dark-variant) !important;
          }

          body.dark-theme-variables .section h3 {
            color: var(--color-light) !important;
          }

          body.dark-theme-variables .field label {
            color: var(--color-light) !important;
          }

          body.dark-theme-variables .field input,
          body.dark-theme-variables .field select {
            background: var(--color-dark-variant) !important;
            border-color: var(--color-info-dark) !important;
            color: var(--color-light) !important;
          }

          body.dark-theme-variables .field input:focus,
          body.dark-theme-variables .field select:focus {
            background: var(--color-dark-variant) !important;
            border-color: var(--color-primary) !important;
          }

          body.dark-theme-variables .toggle,
          body.dark-theme-variables .radio {
            background: var(--color-dark-variant) !important;
            border-color: var(--color-info-dark) !important;
            color: var(--color-light) !important;
          }

          body.dark-theme-variables .toggle:hover,
          body.dark-theme-variables .radio:hover {
            border-color: var(--color-primary) !important;
          }

          /* Dark Theme */
          .dark-theme-variables .section {
            background: var(--color-dark);
            border-color: var(--color-dark-variant);
          }

          .dark-theme-variables .section h2 {
            color: var(--color-primary);
          }

          .dark-theme-variables .field label,
          .dark-theme-variables .form-group label {
            color: var(--color-light);
          }

          .dark-theme-variables .field input,
          .dark-theme-variables .field select,
          .dark-theme-variables .form-group input,
          .dark-theme-variables .form-group select {
            background: var(--color-dark-variant);
            border-color: var(--color-info-dark);
            color: var(--color-light);
          }

          .dark-theme-variables .toggle-password {
            color: var(--color-light);
          }

          .dark-theme-variables .toggle-password:hover {
            background: var(--color-info-dark);
            color: var(--color-primary);
          }

          .dark-theme-variables .toggle,
          .dark-theme-variables .radio,
          .dark-theme-variables .checkbox {
            background: var(--color-dark-variant);
            border-color: var(--color-info-dark);
            color: var(--color-light);
          }

          .dark-theme-variables .checkmark {
            background: var(--color-dark-variant);
            border-color: var(--color-info-dark);
          }

          .dark-theme-variables .eye-btn {
            color: var(--color-light);
          }

          /* === DARK THEME COMPLETO === */
          body.dark-theme-variables .section {
            background: #1e1e1e !important;
            border-color: #404040 !important;
          }

          body.dark-theme-variables .section h2 {
            color: #ffffff !important;
          }

          body.dark-theme-variables .section h2 .material-symbols-sharp {
            color: #ff1493 !important;
          }

          body.dark-theme-variables .field label,
          body.dark-theme-variables .form-group label {
            color: #ffffff !important;
          }

          body.dark-theme-variables .field input,
          body.dark-theme-variables .field select,
          body.dark-theme-variables .form-group input,
          body.dark-theme-variables .form-group select {
            background: #2a2a2a !important;
            border-color: #404040 !important;
            color: #ffffff !important;
          }

          body.dark-theme-variables .field input:focus,
          body.dark-theme-variables .field select:focus,
          body.dark-theme-variables .form-group input:focus,
          body.dark-theme-variables .form-group select:focus {
            border-color: #ff1493 !important;
            box-shadow: 0 0 0 3px rgba(255, 20, 147, 0.1) !important;
            background: #2a2a2a !important;
          }

          body.dark-theme-variables .toggle,
          body.dark-theme-variables .radio,
          body.dark-theme-variables .checkbox {
            background: #2a2a2a !important;
            border-color: #404040 !important;
            color: #ffffff !important;
          }

          body.dark-theme-variables .toggle.active,
          body.dark-theme-variables .radio.active {
            background: #ff1493 !important;
            border-color: #ff1493 !important;
            color: #ffffff !important;
          }

          body.dark-theme-variables .toggle:hover,
          body.dark-theme-variables .radio:hover,
          body.dark-theme-variables .checkbox:hover {
            border-color: #ff1493 !important;
          }

          body.dark-theme-variables .checkmark {
            background: #2a2a2a !important;
            border-color: #404040 !important;
          }

          body.dark-theme-variables .checkbox input:checked + .checkmark {
            background: #ff1493 !important;
            border-color: #ff1493 !important;
          }

          body.dark-theme-variables .toggle-password {
            color: #ffffff !important;
          }

          body.dark-theme-variables .toggle-password:hover {
            background: #404040 !important;
            color: #ff1493 !important;
          }

          body.dark-theme-variables .save-btn {
            background: #ff1493 !important;
            color: #ffffff !important;
          }

          body.dark-theme-variables .save-btn:hover {
            background: #e01283 !important;
          }

          /* === NOVOS CAMPOS === */
          
          /* Webhook field */
          .webhook-field {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
          }

          .webhook-field input {
            flex: 1;
            background: #f8f9fa !important;
            border: 2px dashed #ddd !important;
            font-family: monospace;
            font-size: 0.9rem;
          }

          .copy-webhook-btn {
            background: var(--color-primary);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0.8rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
          }

          .copy-webhook-btn:hover {
            background: #e01283;
            transform: scale(1.05);
          }

          .field-help {
            display: block;
            margin-top: 0.5rem;
            color: var(--color-dark-variant);
            font-size: 0.85rem;
            font-style: italic;
          }

          /* Textarea */
          textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid var(--color-light);
            border-radius: 8px;
            background: var(--color-white);
            color: var(--color-dark);
            font-size: 1rem;
            font-family: inherit;
            resize: vertical;
            min-height: 80px;
            transition: all 0.3s ease;
          }

          textarea:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(255, 20, 147, 0.1);
          }

          /* Dark theme para novos campos */
          body.dark-theme-variables .webhook-field input {
            background: #2a2a2a !important;
            border-color: #404040 !important;
            color: #ffffff !important;
          }

          body.dark-theme-variables .copy-webhook-btn {
            background: #ff1493 !important;
          }

          body.dark-theme-variables .copy-webhook-btn:hover {
            background: #e01283 !important;
          }

          body.dark-theme-variables .field-help {
            color: #ffffff !important;
          }

          body.dark-theme-variables textarea {
            background: #2a2a2a !important;
            border-color: #404040 !important;
            color: #ffffff !important;
          }

          body.dark-theme-variables textarea:focus {
            border-color: #ff1493 !important;
            box-shadow: 0 0 0 3px rgba(255, 20, 147, 0.1) !important;
          }

          /* Alinhamento dos logs */
          .logs-checkbox-group {
            display: flex;
            align-items: flex-end;
            padding-bottom: 0.2rem;
          }

          .logs-checkbox {
            margin-bottom: 0 !important;
          }

          /* Mobile */
          @media (max-width: 768px) {
            .form-row {
              grid-template-columns: 1fr;
            }
            
            .radio-group {
              flex-direction: column;
            }
            
            .checkbox-group {
              grid-template-columns: 1fr;
            }
            
            .toggle-group {
              flex-direction: column;
            }
            
            .section {
              padding: 1rem;
            }
            
            .payment-form {
              margin: 1rem 0;
            }
          }
        </style>

        <script>
          document.addEventListener('DOMContentLoaded', function() {
            // Toggle senha
            document.querySelectorAll('.toggle-password').forEach(btn => {
              btn.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const targetInput = document.getElementById(targetId);
                const icon = this.querySelector('.material-symbols-sharp');
                
                if (targetInput.type === 'password') {
                  targetInput.type = 'text';
                  icon.textContent = 'visibility_off';
                } else {
                  targetInput.type = 'password';
                  icon.textContent = 'visibility';
                }
              });
            });

            // Toggle status/radio buttons
            document.querySelectorAll('.toggle, .radio').forEach(label => {
              label.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                const name = radio.name;
                
                // Remove active de todos do mesmo grupo
                document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
                  r.closest('.toggle, .radio').classList.remove('active');
                });
                
                // Ativar o clicado
                this.classList.add('active');
              });
            });

            // Auto-hide mensagens
            setTimeout(() => {
              document.querySelectorAll('.alert-success, .alert-error').forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
              });
            }, 4000);
          });

          // Função para copiar URL do webhook
          function copyWebhookUrl() {
            const webhookInput = document.getElementById('webhook_url');
            webhookInput.select();
            webhookInput.setSelectionRange(0, 99999); // Para mobile
            
            try {
              document.execCommand('copy');
              
              // Feedback visual
              const button = document.querySelector('.copy-webhook-btn');
              const originalIcon = button.querySelector('.material-symbols-sharp');
              originalIcon.textContent = 'check';
              
              setTimeout(() => {
                originalIcon.textContent = 'content_copy';
              }, 2000);
              
            } catch (err) {
              console.error('Erro ao copiar:', err);
            }
          }
        </script>
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
    const savedTheme = localStorage.getItem('darkTheme');
    if (savedTheme === 'true') {
        document.body.classList.add('dark-theme-variables');
        console.log('Tema dark aplicado em pagamentos.php');
    }
});
</script>
 </body>
</html>