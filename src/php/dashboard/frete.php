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

// Verificar e criar tabela freight_integrations se não existir
function createFreightIntegrationsTable() {
    global $conexao;
    
    // Verificar se a tabela existe
    $check_table = $conexao->query("SHOW TABLES LIKE 'freight_integrations'");
    if ($check_table->num_rows == 0) {
        // Criar a tabela
        $sql = "CREATE TABLE freight_integrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider_name VARCHAR(100) NOT NULL,
            provider_slug VARCHAR(50) NOT NULL UNIQUE,
            active BOOLEAN DEFAULT FALSE,
            environment ENUM('sandbox', 'production') DEFAULT 'sandbox',
            api_key TEXT,
            token TEXT,
            secret_key TEXT,
            timeout INT DEFAULT 30,
            priority INT DEFAULT 1,
            webhook_url VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conexao->query($sql) === TRUE) {
            // Inserir integrações padrão
            $default_providers = [
                ['Melhor Envio', 'melhor_envio', 0, 'sandbox', '', '', '', 30, 1]
            ];
            
            foreach ($default_providers as $provider) {
                $stmt = $conexao->prepare("INSERT INTO freight_integrations (provider_name, provider_slug, active, environment, api_key, token, secret_key, timeout, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssissssii", $provider[0], $provider[1], $provider[2], $provider[3], $provider[4], $provider[5], $provider[6], $provider[7], $provider[8]);
                $stmt->execute();
            }
            
            return "Tabela freight_integrations criada com sucesso!";
        } else {
            return "Erro ao criar tabela: " . $conexao->error;
        }
    }
    return null;
}

// Criar tabela de serviços de frete
function createFreightServicesTable() {
    global $conexao;
    
    $check_table = $conexao->query("SHOW TABLES LIKE 'freight_services'");
    if ($check_table->num_rows == 0) {
        $sql = "CREATE TABLE freight_services (
            id INT AUTO_INCREMENT PRIMARY KEY,
            integration_id INT,
            service_code VARCHAR(50) NOT NULL,
            service_name VARCHAR(100) NOT NULL,
            company_name VARCHAR(100),
            active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (integration_id) REFERENCES freight_integrations(id) ON DELETE CASCADE
        )";
        
        if ($conexao->query($sql) === TRUE) {
            // Inserir serviços padrão do Melhor Envio
            $melhor_envio_id = null;
            $result = $conexao->query("SELECT id FROM freight_integrations WHERE provider_slug = 'melhor_envio'");
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $melhor_envio_id = $row['id'];
                
                $default_services = [
                    ['1', 'PAC', 'Correios', 1],
                    ['2', 'SEDEX', 'Correios', 1],
                    ['17', 'Expresso', 'Azul Cargo Express', 0],
                    ['20', 'Rodoviário', 'Braspress', 0],
                    ['25', 'Normal', 'Loggi', 0]
                ];
                
                foreach ($default_services as $service) {
                    $stmt = $conexao->prepare("INSERT INTO freight_services (integration_id, service_code, service_name, company_name, active) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssi", $melhor_envio_id, $service[0], $service[1], $service[2], $service[3]);
                    $stmt->execute();
                }
            }
            
            // Verificar se existe integração Jadlog e adicionar serviços
            $jadlog_id = null;
            $result = $conexao->query("SELECT id FROM freight_integrations WHERE provider_slug = 'jadlog'");
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $jadlog_id = $row['id'];
                
                // Verificar se já existem serviços para Jadlog
                $check_jadlog = $conexao->query("SELECT COUNT(*) as count FROM freight_services WHERE integration_id = $jadlog_id");
                $jadlog_count = $check_jadlog->fetch_assoc()['count'];
                
                if ($jadlog_count == 0) {
                    $jadlog_services = [
                        ['0', '.Package Econômico', 'Jadlog', 1],
                        ['3', '.Package', 'Jadlog', 1],
                        ['4', '.Package Cargo', 'Jadlog', 0],
                        ['5', 'Rodoviário', 'Jadlog', 0],
                        ['7', 'Corporate', 'Jadlog', 0],
                        ['9', 'Com Agendamento', 'Jadlog', 0],
                        ['10', 'DOC', 'Jadlog', 0],
                        ['12', '.Package Leve', 'Jadlog', 0]
                    ];
                    
                    foreach ($jadlog_services as $service) {
                        $stmt = $conexao->prepare("INSERT INTO freight_services (integration_id, service_code, service_name, company_name, active) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("isssi", $jadlog_id, $service[0], $service[1], $service[2], $service[3]);
                        $stmt->execute();
                    }
                }
            } else {
                // Se não existe integração Jadlog, vamos garantir que ela exista
                $check_jadlog_integration = $conexao->query("SELECT id FROM freight_integrations WHERE provider_slug = 'jadlog'");
                if ($check_jadlog_integration->num_rows == 0) {
                    // Criar integração Jadlog se não existir
                    $stmt = $conexao->prepare("INSERT INTO freight_integrations (provider_name, provider_slug, active, environment, api_key, token, secret_key, timeout, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $provider_data = ['Jadlog', 'jadlog', 0, 'production', '', '', '', 30, 6];
                    $stmt->bind_param("ssissssii", $provider_data[0], $provider_data[1], $provider_data[2], $provider_data[3], $provider_data[4], $provider_data[5], $provider_data[6], $provider_data[7], $provider_data[8]);
                    if ($stmt->execute()) {
                        $jadlog_id = $conexao->insert_id;
                        
                        // Adicionar serviços da Jadlog
                        $jadlog_services = [
                            ['0', '.Package Econômico', 'Jadlog', 1],
                            ['3', '.Package', 'Jadlog', 1],
                            ['4', '.Package Cargo', 'Jadlog', 0],
                            ['5', 'Rodoviário', 'Jadlog', 0],
                            ['7', 'Corporate', 'Jadlog', 0],
                            ['9', 'Com Agendamento', 'Jadlog', 0],
                            ['10', 'DOC', 'Jadlog', 0],
                            ['12', '.Package Leve', 'Jadlog', 0]
                        ];
                        
                        foreach ($jadlog_services as $service) {
                            $stmt = $conexao->prepare("INSERT INTO freight_services (integration_id, service_code, service_name, company_name, active) VALUES (?, ?, ?, ?, ?)");
                            $stmt->bind_param("isssi", $jadlog_id, $service[0], $service[1], $service[2], $service[3]);
                            $stmt->execute();
                        }
                    }
                }
            }
            
            return "Tabela freight_services criada com sucesso!";
        } else {
            return "Erro ao criar tabela: " . $conexao->error;
        }
    }
    return null;
}

// Criar tabela de configurações globais de frete
function createFreightSettingsTable() {
    global $conexao;
    
    $check_table = $conexao->query("SHOW TABLES LIKE 'freight_settings'");
    if ($check_table->num_rows == 0) {
        $sql = "CREATE TABLE freight_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            origin_zipcode VARCHAR(9) NOT NULL DEFAULT '00000-000',
            default_weight DECIMAL(8,3) DEFAULT 0.500,
            default_height DECIMAL(8,2) DEFAULT 20.00,
            default_width DECIMAL(8,2) DEFAULT 30.00,
            default_length DECIMAL(8,2) DEFAULT 40.00,
            margin_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
            margin_value DECIMAL(10,2) DEFAULT 0.00,
            currency VARCHAR(3) DEFAULT 'BRL',
            rounding_type ENUM('floor', 'ceil', 'round') DEFAULT 'round',
            calculation_mode ENUM('lowest_price', 'lowest_time', 'priority') DEFAULT 'lowest_price',
            free_shipping_threshold DECIMAL(10,2) DEFAULT 0.00,
            minimum_order_value DECIMAL(10,2) DEFAULT 0.00,
            maximum_freight_value DECIMAL(10,2) DEFAULT 999.99,
            fallback_enabled BOOLEAN DEFAULT TRUE,
            fallback_value DECIMAL(10,2) DEFAULT 15.00,
            fallback_message TEXT DEFAULT 'Prazo de entrega: 3 a 7 dias úteis',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conexao->query($sql) === TRUE) {
            // Inserir configuração padrão
            $conexao->query("INSERT INTO freight_settings (id) VALUES (1)");
            return "Tabela freight_settings criada com sucesso!";
        } else {
            return "Erro ao criar tabela: " . $conexao->error;
        }
    }
    return null;
}

// Criar tabelas e configurações
createFreightIntegrationsTable();
createFreightSettingsTable();
createFreightServicesTable();
configurarTokenMelhorEnvio();
garantirServicosMelhorEnvio();

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Atualizar integração
    if (isset($_POST['update_integration'])) {
        $id = (int)$_POST['integration_id'];
        $active = isset($_POST['active']) ? 1 : 0;
        $environment = $_POST['environment'];
        $api_key = trim($_POST['api_key']);
        $token = trim($_POST['token']);
        $secret_key = trim($_POST['secret_key']);
        $timeout = (int)$_POST['timeout'];
        $priority = (int)$_POST['priority'];
        $webhook_url = trim($_POST['webhook_url']);
        
        $stmt = $conexao->prepare("UPDATE freight_integrations SET active = ?, environment = ?, api_key = ?, token = ?, secret_key = ?, timeout = ?, priority = ?, webhook_url = ? WHERE id = ?");
        $stmt->bind_param("issssiisi", $active, $environment, $api_key, $token, $secret_key, $timeout, $priority, $webhook_url, $id);
        
        if ($stmt->execute()) {
            $_SESSION['mensagem'] = 'Integração atualizada com sucesso!';
        } else {
            $_SESSION['mensagem'] = 'Erro ao atualizar integração.';
        }
        
        header('Location: frete.php');
        exit();
    }
    
    // Atualizar configurações globais
    if (isset($_POST['update_settings'])) {
        $origin_zipcode = trim($_POST['origin_zipcode']);
        $default_weight = (float)$_POST['default_weight'];
        $default_height = (float)$_POST['default_height'];
        $default_width = (float)$_POST['default_width'];
        $default_length = (float)$_POST['default_length'];
        $margin_type = $_POST['margin_type'];
        $margin_value = (float)$_POST['margin_value'];
        $currency = $_POST['currency'];
        $rounding_type = $_POST['rounding_type'];
        $calculation_mode = $_POST['calculation_mode'];
        $free_shipping_threshold = (float)$_POST['free_shipping_threshold'];
        $minimum_order_value = (float)$_POST['minimum_order_value'];
        $default_product_value = (float)$_POST['default_product_value'];
        $maximum_freight_value = (float)$_POST['maximum_freight_value'];
        $fallback_enabled = isset($_POST['fallback_enabled']) ? 1 : 0;
        $fallback_value = (float)$_POST['fallback_value'];
        $fallback_message = trim($_POST['fallback_message']);
        
        // Verificar se já existe configuração
        $check = $conexao->query("SELECT id FROM freight_settings WHERE id = 1");
        if ($check->num_rows > 0) {
            $stmt = $conexao->prepare("UPDATE freight_settings SET origin_zipcode = ?, default_weight = ?, default_height = ?, default_width = ?, default_length = ?, margin_type = ?, margin_value = ?, currency = ?, rounding_type = ?, calculation_mode = ?, free_shipping_threshold = ?, minimum_order_value = ?, default_product_value = ?, maximum_freight_value = ?, fallback_enabled = ?, fallback_value = ?, fallback_message = ? WHERE id = 1");
        } else {
            $stmt = $conexao->prepare("INSERT INTO freight_settings (id, origin_zipcode, default_weight, default_height, default_width, default_length, margin_type, margin_value, currency, rounding_type, calculation_mode, free_shipping_threshold, minimum_order_value, default_product_value, maximum_freight_value, fallback_enabled, fallback_value, fallback_message) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        }
        
        $stmt->bind_param("sdddssdsssddddids", $origin_zipcode, $default_weight, $default_height, $default_width, $default_length, $margin_type, $margin_value, $currency, $rounding_type, $calculation_mode, $free_shipping_threshold, $minimum_order_value, $default_product_value, $maximum_freight_value, $fallback_enabled, $fallback_value, $fallback_message);
        
        if ($stmt->execute()) {
            $_SESSION['mensagem'] = 'Configurações atualizadas com sucesso!';
        } else {
            $_SESSION['mensagem'] = 'Erro ao atualizar configurações.';
        }
        
        header('Location: frete.php');
        exit();
    }
    
    // Atualizar serviços
    if (isset($_POST['update_services'])) {
        // Primeiro, desativar todos os serviços
        $conexao->query("UPDATE freight_services SET active = 0");
        
        // Depois, ativar apenas os selecionados
        if (isset($_POST['services']) && is_array($_POST['services'])) {
            foreach ($_POST['services'] as $service_id) {
                $service_id = (int)$service_id;
                $stmt = $conexao->prepare("UPDATE freight_services SET active = 1 WHERE id = ?");
                $stmt->bind_param("i", $service_id);
                $stmt->execute();
            }
        }
        
        $_SESSION['mensagem'] = 'Serviços atualizados com sucesso!';
        header('Location: frete.php');
        exit();
    }
}

// Buscar integrações
$integrations_result = $conexao->query("SELECT * FROM freight_integrations ORDER BY priority ASC");

// Buscar configurações globais
$settings_result = $conexao->query("SELECT * FROM freight_settings WHERE id = 1");
$settings = $settings_result ? $settings_result->fetch_assoc() : [];

// Buscar serviços de frete
$services_result = $conexao->query("SELECT fs.*, fi.provider_name FROM freight_services fs JOIN freight_integrations fi ON fs.integration_id = fi.id ORDER BY fi.provider_name, fs.service_name");
$services = [];
if ($services_result && $services_result->num_rows > 0) {
    while ($row = $services_result->fetch_assoc()) {
        $services[] = $row;
    }
}

// Configurar token do Melhor Envio automaticamente (apenas uma vez)
function configurarTokenMelhorEnvio() {
    global $conexao;
    
    // IMPORTANTE: Em produção, mover este token para arquivo .env ou variável de ambiente
    // Para fins de demonstração, token está aqui. REMOVER antes de commit público!
    $token_melhor_envio = "SUBSTITUA_PELO_SEU_TOKEN_AQUI";
    
    // Verificar se o Melhor Envio já tem token configurado
    $check = $conexao->query("SELECT token FROM freight_integrations WHERE provider_slug = 'melhor_envio'");
    if ($check && $check->num_rows > 0) {
        $row = $check->fetch_assoc();
        if (empty($row['token'])) {
            // Configurar token e ativar a integração
            $stmt = $conexao->prepare("UPDATE freight_integrations SET token = ?, active = 1, environment = 'production' WHERE provider_slug = 'melhor_envio'");
            $stmt->bind_param("s", $token_melhor_envio);
            $stmt->execute();
            
            return "Token do Melhor Envio configurado automaticamente!";
        }
    }
    return null;
}

// Função para garantir que os serviços do Melhor Envio existam
function garantirServicosMelhorEnvio() {
    global $conexao;
    
    // Verificar se já existem serviços do Melhor Envio
    $count = $conexao->query("SELECT COUNT(*) as total FROM freight_services fs 
                             JOIN freight_integrations fi ON fs.integration_id = fi.id 
                             WHERE fi.provider_slug = 'melhor_envio'")->fetch_assoc()['total'];
    
    if ($count == 0) {
        // Buscar ID da integração Melhor Envio
        $result = $conexao->query("SELECT id FROM freight_integrations WHERE provider_slug = 'melhor_envio'");
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $melhor_envio_id = $row['id'];
            
            // Criar apenas serviços ativos e necessários
            $melhor_envio_services = [
                ['1', 'PAC', 'Correios', 1],
                ['2', 'SEDEX', 'Correios', 1],
                ['3', '.Package (Mini Envios)', 'Jadlog', 1],
                ['4', '.Com', 'Jadlog', 1]
            ];
            
            foreach ($melhor_envio_services as $service) {
                $stmt = $conexao->prepare("INSERT INTO freight_services (integration_id, service_code, service_name, company_name, active) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("isssi", $melhor_envio_id, $service[0], $service[1], $service[2], $service[3]);
                $stmt->execute();
            }
            
            return "Serviços do Melhor Envio configurados!";
        }
    }
    return null;
}

$melhor_envio_services_message = garantirServicosMelhorEnvio();
?>
<!DOCTYPE html>
<html lang="pt-BR">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../css/dashboard.css">

     <link
      href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp"
      rel="stylesheet"
    />

    <title>Configurações de Frete e Integrações</title>
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

        /* Estilos específicos para a tela de frete */
        .freight-section {
            background: var(--color-white);
            padding: var(--card-padding);
            border-radius: var(--card-border-radius);
            margin-bottom: 2rem;
            box-shadow: var(--box-shadow);
            transition: box-shadow 0.3s ease;
        }

        .freight-section:hover {
            box-shadow: 0 3rem 4rem var(--color-light);
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--color-light);
        }

        .section-header span.material-symbols-sharp {
            font-size: 2rem;
            margin-right: 1rem;
            color: var(--color-primary);
        }

        .section-header h2 {
            color: var(--color-dark);
            margin: 0;
        }

        .integration-card {
            border: 1px solid var(--color-light);
            border-radius: var(--border-radius-2);
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: var(--color-white);
            transition: all 0.3s ease;
        }

        .integration-card:hover {
            border-color: var(--color-primary);
            transform: translateY(-2px);
        }

        .integration-card.active {
            border-color: var(--color-success);
            background: rgba(65, 241, 182, 0.05);
        }

        .integration-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .integration-name {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--color-dark);
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
            background-color: var(--color-success);
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--color-dark);
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.8rem;
            border: 1px solid var(--color-light);
            border-radius: var(--border-radius-1);
            font-size: 0.9rem;
            background: var(--color-white);
            color: var(--color-dark);
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 2px rgba(255, 0, 212, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border-radius: var(--border-radius-1);
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--color-primary);
            color: var(--color-white);
        }

        .btn-primary:hover {
            background: var(--color-primary-variant);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 0, 212, 0.3);
        }

        .btn-success {
            background: var(--color-success);
            color: var(--color-white);
        }

        .btn-success:hover {
            background: #2dd4aa;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(65, 241, 182, 0.3);
        }

        .btn-warning {
            background: var(--color-warning);
            color: var(--color-white);
        }

        .btn-warning:hover {
            background: #e6a347;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 187, 85, 0.3);
        }

        .simulation-container {
            background: var(--color-background);
            padding: 1.5rem;
            border-radius: var(--border-radius-2);
            border: 1px solid var(--color-light);
        }

        .simulation-result {
            margin-top: 1.5rem;
            padding: 1rem;
            border-radius: var(--border-radius-1);
            background: var(--color-white);
            border-left: 4px solid var(--color-success);
            display: none;
        }

        .simulation-result.error {
            border-left-color: var(--color-danger);
        }

        .alert {
            padding: 1rem;
            border-radius: var(--border-radius-1);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: rgba(65, 241, 182, 0.1);
            border: 1px solid var(--color-success);
            color: var(--color-success);
        }

        .alert-danger {
            background: rgba(255, 0, 212, 0.1);
            border: 1px solid var(--color-danger);
            color: var(--color-danger);
        }

        .tooltip {
            position: relative;
            display: inline-block;
            cursor: help;
            margin-left: 0.5rem;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 220px;
            background-color: var(--color-dark);
            color: var(--color-white);
            text-align: center;
            border-radius: var(--border-radius-1);
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -110px;
            font-size: 0.75rem;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        .priority-badge {
            background: var(--color-primary);
            color: var(--color-white);
            padding: 0.25rem 0.5rem;
            border-radius: var(--border-radius-1);
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: var(--border-radius-1);
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-active {
            background: rgba(65, 241, 182, 0.2);
            color: var(--color-success);
        }

        .status-inactive {
            background: rgba(132, 139, 200, 0.2);
            color: var(--color-info-dark);
        }

        .service-checkbox-container {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 1px solid var(--color-light);
            border-radius: var(--border-radius-1);
            background: var(--color-white);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .service-checkbox-container:hover {
            border-color: var(--color-primary);
            transform: translateY(-1px);
        }

        .service-checkbox-container.active {
            border-color: var(--color-success);
            background: rgba(65, 241, 182, 0.05);
        }

        .service-checkbox-container input[type="checkbox"] {
            margin-right: 0.8rem;
            transform: scale(1.2);
            cursor: pointer;
        }

        .service-label {
            margin: 0;
            cursor: pointer;
            flex: 1;
        }

        .service-name {
            font-weight: 600;
            color: var(--color-dark);
            margin-bottom: 0.25rem;
        }

        .service-details {
            font-size: 0.8rem;
            color: var(--color-info-dark);
        }

        .provider-section {
            grid-column: 1 / -1;
            margin: 1.5rem 0 1rem 0;
        }

        .provider-title {
            color: var(--color-primary);
            border-bottom: 2px solid var(--color-light);
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }

        .services-grid {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .integration-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
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
              <a href="frete.php" class="active">
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
        <h1>Configurações de Frete e Integrações Logísticas</h1>

        <!-- Mensagens de feedback -->
        <?php if (isset($_SESSION['mensagem'])): ?>
            <div class="alert alert-success">
                <span class="material-symbols-sharp">check_circle</span>
                <?= $_SESSION['mensagem']; ?>
            </div>
            <?php unset($_SESSION['mensagem']); ?>
        <?php endif; ?>

        <!-- Seção 1: Integrações de Frete -->
        <div class="freight-section">
            <div class="section-header">
                <span class="material-symbols-sharp">api</span>
                <h2>Integrações de Frete</h2>
            </div>

            <?php if ($integrations_result && $integrations_result->num_rows > 0): ?>
                <?php while ($integration = $integrations_result->fetch_assoc()): ?>
                    <div class="integration-card <?= $integration['active'] ? 'active' : ''; ?>">
                        <form method="POST" action="">
                            <input type="hidden" name="integration_id" value="<?= $integration['id']; ?>">
                            <input type="hidden" name="update_integration" value="1">
                            
                            <div class="integration-header">
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <span class="integration-name"><?= htmlspecialchars($integration['provider_name']); ?></span>
                                    <span class="priority-badge">Prioridade: <?= $integration['priority']; ?></span>
                                    <span class="status-badge <?= $integration['active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?= $integration['active'] ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="active" <?= $integration['active'] ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="environment_<?= $integration['id']; ?>">
                                        Ambiente
                                        <span class="tooltip">
                                            <span class="material-symbols-sharp" style="font-size: 1rem;">help</span>
                                            <span class="tooltiptext">Sandbox para testes, Production para uso real</span>
                                        </span>
                                    </label>
                                    <select name="environment" id="environment_<?= $integration['id']; ?>">
                                        <option value="sandbox" <?= $integration['environment'] == 'sandbox' ? 'selected' : ''; ?>>Sandbox (Teste)</option>
                                        <option value="production" <?= $integration['environment'] == 'production' ? 'selected' : ''; ?>>Production (Produção)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="timeout_<?= $integration['id']; ?>">Timeout (segundos)</label>
                                    <input type="number" name="timeout" id="timeout_<?= $integration['id']; ?>" 
                                           value="<?= $integration['timeout']; ?>" min="5" max="120">
                                </div>
                                <div class="form-group">
                                    <label for="priority_<?= $integration['id']; ?>">Prioridade</label>
                                    <input type="number" name="priority" id="priority_<?= $integration['id']; ?>" 
                                           value="<?= $integration['priority']; ?>" min="1" max="10">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="api_key_<?= $integration['id']; ?>">API Key</label>
                                    <div class="password-input-container">
                                        <input type="password" name="api_key" id="api_key_<?= $integration['id']; ?>" 
                                               value="<?= htmlspecialchars($integration['api_key']); ?>" 
                                               placeholder="Sua chave de API">
                                        <span class="password-toggle material-symbols-sharp" onclick="togglePassword('api_key_<?= $integration['id']; ?>')">
                                            visibility
                                        </span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="token_<?= $integration['id']; ?>">Token/Access Token</label>
                                    <div class="password-input-container">
                                        <input type="password" name="token" id="token_<?= $integration['id']; ?>" 
                                               value="<?= htmlspecialchars($integration['token']); ?>" 
                                               placeholder="Token de acesso">
                                        <span class="password-toggle material-symbols-sharp" onclick="togglePassword('token_<?= $integration['id']; ?>')">
                                            visibility
                                        </span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="secret_key_<?= $integration['id']; ?>">Secret Key</label>
                                    <div class="password-input-container">
                                        <input type="password" name="secret_key" id="secret_key_<?= $integration['id']; ?>" 
                                               value="<?= htmlspecialchars($integration['secret_key']); ?>" 
                                               placeholder="Chave secreta">
                                        <span class="password-toggle material-symbols-sharp" onclick="togglePassword('secret_key_<?= $integration['id']; ?>')">
                                            visibility
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="webhook_url_<?= $integration['id']; ?>">
                                        Webhook URL (opcional)
                                        <span class="tooltip">
                                            <span class="material-symbols-sharp" style="font-size: 1rem;">help</span>
                                            <span class="tooltiptext">URL para receber notificações da transportadora</span>
                                        </span>
                                    </label>
                                    <input type="url" name="webhook_url" id="webhook_url_<?= $integration['id']; ?>" 
                                           value="<?= htmlspecialchars($integration['webhook_url']); ?>" 
                                           placeholder="https://seusite.com/webhook/frete">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <span class="material-symbols-sharp">save</span>
                                Salvar Integração
                            </button>
                        </form>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>Nenhuma integração encontrada.</p>
            <?php endif; ?>
        </div>

        <!-- Seção 2: Serviços de Frete Disponíveis -->
        <div class="freight-section">
            <div class="section-header">
                <span class="material-symbols-sharp">local_shipping</span>
                <h2>Serviços de Frete Disponíveis</h2>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="update_services" value="1">
                
                <p style="margin-bottom: 1.5rem; color: var(--color-info-dark);">
                    Selecione quais serviços de frete você deseja utilizar no cálculo. Apenas os serviços marcados serão consultados.
                </p>

                <div class="form-row">
                    <?php if (!empty($services)): ?>
                        <?php 
                        $current_provider = '';
                        foreach ($services as $service): 
                            if ($current_provider !== $service['provider_name']):
                                if ($current_provider !== '') echo '</div>'; // Fechar div anterior
                                $current_provider = $service['provider_name'];
                        ?>
                                <div class="provider-section">
                                    <h3 class="provider-title">
                                        <?= htmlspecialchars($current_provider); ?>
                                    </h3>
                                </div>
                                <div class="services-grid">
                        <?php endif; ?>
                                
                                <div class="service-checkbox-container <?= $service['active'] ? 'active' : ''; ?>">
                                    <input type="checkbox" 
                                           name="services[]" 
                                           value="<?= $service['id']; ?>" 
                                           id="service_<?= $service['id']; ?>"
                                           <?= $service['active'] ? 'checked' : ''; ?>>
                                    <label for="service_<?= $service['id']; ?>" class="service-label">
                                        <div class="service-name">
                                            <?= htmlspecialchars($service['service_name']); ?>
                                        </div>
                                        <div class="service-details">
                                            <?= htmlspecialchars($service['company_name']); ?>
                                            <?php if ($service['service_code']): ?>
                                                • Código: <?= htmlspecialchars($service['service_code']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </label>
                                    <span class="status-badge <?= $service['active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?= $service['active'] ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </div>
                        
                        <?php endforeach; ?>
                        </div> <!-- Fechar última div de provider -->
                    <?php else: ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--color-info-dark);">
                            <span class="material-symbols-sharp" style="font-size: 3rem; opacity: 0.5;">local_shipping</span>
                            <p>Nenhum serviço de frete encontrado. Configure as integrações primeiro.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($services)): ?>
                    <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem;">
                        <span class="material-symbols-sharp">save</span>
                        Salvar Serviços Selecionados
                    </button>
                <?php endif; ?>
            </form>
        </div>

        <!-- Seção 3: Configurações Globais de Frete -->
        <div class="freight-section">
            <div class="section-header">
                <span class="material-symbols-sharp">tune</span>
                <h2>Configurações Globais de Frete</h2>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="update_settings" value="1">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="origin_zipcode">CEP de Origem</label>
                        <input type="text" name="origin_zipcode" id="origin_zipcode" 
                               value="<?= htmlspecialchars($settings['origin_zipcode'] ?? ''); ?>" 
                               placeholder="00000-000" maxlength="9" required>
                    </div>
                    <div class="form-group">
                        <label for="currency">Moeda</label>
                        <select name="currency" id="currency">
                            <option value="BRL" <?= ($settings['currency'] ?? '') == 'BRL' ? 'selected' : ''; ?>>Real (BRL)</option>
                            <option value="USD" <?= ($settings['currency'] ?? '') == 'USD' ? 'selected' : ''; ?>>Dólar (USD)</option>
                            <option value="EUR" <?= ($settings['currency'] ?? '') == 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="rounding_type">Tipo de Arredondamento</label>
                        <select name="rounding_type" id="rounding_type">
                            <option value="floor" <?= ($settings['rounding_type'] ?? '') == 'floor' ? 'selected' : ''; ?>>Para baixo</option>
                            <option value="ceil" <?= ($settings['rounding_type'] ?? '') == 'ceil' ? 'selected' : ''; ?>>Para cima</option>
                            <option value="round" <?= ($settings['rounding_type'] ?? '') == 'round' ? 'selected' : ''; ?>>Arredondamento normal</option>
                        </select>
                    </div>
                </div>

                <h3 style="margin: 2rem 0 1rem 0; color: var(--color-dark);">Dimensões Padrão do Produto</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="default_weight">Peso Padrão (kg)</label>
                        <input type="number" name="default_weight" id="default_weight" step="0.001" min="0.001"
                               value="<?= $settings['default_weight'] ?? '0.500'; ?>">
                    </div>
                    <div class="form-group">
                        <label for="default_height">Altura Padrão (cm)</label>
                        <input type="number" name="default_height" id="default_height" step="0.01" min="1"
                               value="<?= $settings['default_height'] ?? '20.00'; ?>">
                    </div>
                    <div class="form-group">
                        <label for="default_width">Largura Padrão (cm)</label>
                        <input type="number" name="default_width" id="default_width" step="0.01" min="1"
                               value="<?= $settings['default_width'] ?? '30.00'; ?>">
                    </div>
                    <div class="form-group">
                        <label for="default_length">Comprimento Padrão (cm)</label>
                        <input type="number" name="default_length" id="default_length" step="0.01" min="1"
                               value="<?= $settings['default_length'] ?? '40.00'; ?>">
                    </div>
                </div>

                <h3 style="margin: 2rem 0 1rem 0; color: var(--color-dark);">Margem e Taxas</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="margin_type">Tipo de Margem</label>
                        <select name="margin_type" id="margin_type">
                            <option value="percentage" <?= ($settings['margin_type'] ?? '') == 'percentage' ? 'selected' : ''; ?>>Porcentagem (%)</option>
                            <option value="fixed" <?= ($settings['margin_type'] ?? '') == 'fixed' ? 'selected' : ''; ?>>Valor Fixo</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="margin_value">Valor da Margem</label>
                        <input type="number" name="margin_value" id="margin_value" step="0.01" min="0"
                               value="<?= $settings['margin_value'] ?? '0.00'; ?>">
                    </div>
                </div>

                <h3 style="margin: 2rem 0 1rem 0; color: var(--color-dark);">Regras de Cálculo</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="calculation_mode">Modo de Cálculo</label>
                        <select name="calculation_mode" id="calculation_mode">
                            <option value="lowest_price" <?= ($settings['calculation_mode'] ?? '') == 'lowest_price' ? 'selected' : ''; ?>>Menor Valor</option>
                            <option value="lowest_time" <?= ($settings['calculation_mode'] ?? '') == 'lowest_time' ? 'selected' : ''; ?>>Menor Prazo</option>
                            <option value="priority" <?= ($settings['calculation_mode'] ?? '') == 'priority' ? 'selected' : ''; ?>>Por Prioridade</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="free_shipping_threshold">Frete Grátis Acima de</label>
                        <input type="number" name="free_shipping_threshold" id="free_shipping_threshold" step="0.01" min="0"
                               value="<?= $settings['free_shipping_threshold'] ?? '0.00'; ?>">
                    </div>
                    <div class="form-group">
                        <label for="minimum_order_value">Valor Mínimo do Pedido</label>
                        <input type="number" name="minimum_order_value" id="minimum_order_value" step="0.01" min="0"
                               value="<?= $settings['minimum_order_value'] ?? '0.00'; ?>">
                    </div>
                    <div class="form-group">
                        <label for="default_product_value">Valor Padrão do Produto</label>
                        <input type="number" name="default_product_value" id="default_product_value" step="0.01" min="0"
                               value="<?= $settings['default_product_value'] ?? '100.00'; ?>">
                        <small>Usado como valor declarado padrão nas simulações</small>
                    </div>
                    <div class="form-group">
                        <label for="maximum_freight_value">Limite Máximo de Frete</label>
                        <input type="number" name="maximum_freight_value" id="maximum_freight_value" step="0.01" min="0"
                               value="<?= $settings['maximum_freight_value'] ?? '999.99'; ?>">
                    </div>
                </div>

                <h3 style="margin: 2rem 0 1rem 0; color: var(--color-dark);">Fallback / Frete de Emergência</h3>
                <div style="margin-bottom: 1rem;">
                    <label class="toggle-switch">
                        <input type="checkbox" name="fallback_enabled" <?= ($settings['fallback_enabled'] ?? 1) ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                    <span style="margin-left: 1rem; font-weight: 500;">Ativar fallback automático</span>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="fallback_value">Valor Fixo do Frete de Emergência</label>
                        <input type="number" name="fallback_value" id="fallback_value" step="0.01" min="0"
                               value="<?= $settings['fallback_value'] ?? '15.00'; ?>">
                    </div>
                    <div class="form-group">
                        <label for="fallback_message">Mensagem Personalizada</label>
                        <textarea name="fallback_message" id="fallback_message" 
                                  placeholder="Mensagem exibida quando usar frete de emergência"><?= htmlspecialchars($settings['fallback_message'] ?? 'Prazo de entrega: 3 a 7 dias úteis'); ?></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-sharp">save</span>
                    Salvar Configurações Globais
                </button>
            </form>
        </div>

        <!-- Seção 4: Teste / Simulação de Frete -->
        <div class="freight-section">
            <div class="section-header">
                <span class="material-symbols-sharp">calculate</span>
                <h2>Simulação de Frete</h2>
            </div>

            <div class="simulation-container">
                <form id="simulation-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="sim_destination_zipcode">CEP de Destino</label>
                            <input type="text" id="sim_destination_zipcode" placeholder="00000-000" maxlength="9" required>
                        </div>
                        <div class="form-group">
                            <label for="sim_weight">Peso (kg)</label>
                            <input type="number" id="sim_weight" step="0.001" min="0.001" value="0.500">
                        </div>
                        <div class="form-group">
                            <label for="sim_height">Altura (cm)</label>
                            <input type="number" id="sim_height" step="0.01" min="1" value="20">
                        </div>
                        <div class="form-group">
                            <label for="sim_width">Largura (cm)</label>
                            <input type="number" id="sim_width" step="0.01" min="1" value="30">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="sim_length">Comprimento (cm)</label>
                            <input type="number" id="sim_length" step="0.01" min="1" value="40">
                        </div>
                        <div class="form-group">
                            <label for="sim_order_value">Valor do Pedido</label>
                            <input type="number" id="sim_order_value" step="0.01" min="0" 
                                   value="100.00">
                        </div>
                    </div>

                    <button type="button" class="btn btn-warning" onclick="simulateFreight()">
                        <span class="material-symbols-sharp">play_arrow</span>
                        Simular Frete
                    </button>
                </form>

                <div id="simulation-result" class="simulation-result">
                    <h3>Resultado da Simulação</h3>
                    <div id="simulation-content"></div>
                </div>
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

    <script src="../../js/dashboard.js"></script>
    <script>
        // Aplicar tema salvo
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('darkTheme');
            if (savedTheme === 'true') {
                document.body.classList.add('dark-theme-variables');
                console.log('Tema dark aplicado em frete.php');
            }
        });

        // Formatação automática do CEP
        function formatZipCode(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length >= 5) {
                value = value.substring(0, 5) + '-' + value.substring(5, 8);
            }
            input.value = value;
        }

        // Adicionar formatação aos campos de CEP
        document.querySelectorAll('input[id*="zipcode"]').forEach(function(input) {
            input.addEventListener('input', function() {
                formatZipCode(this);
            });
        });

        // Interação dos checkboxes de serviços
        document.querySelectorAll('.service-checkbox-container input[type="checkbox"]').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const container = this.closest('.service-checkbox-container');
                const statusBadge = container.querySelector('.status-badge');
                
                if (this.checked) {
                    container.classList.add('active');
                    statusBadge.className = 'status-badge status-active';
                    statusBadge.textContent = 'Ativo';
                } else {
                    container.classList.remove('active');
                    statusBadge.className = 'status-badge status-inactive';
                    statusBadge.textContent = 'Inativo';
                }
            });
            
            // Permitir clicar no container para marcar/desmarcar
            const container = checkbox.closest('.service-checkbox-container');
            container.addEventListener('click', function(e) {
                if (e.target.type !== 'checkbox' && e.target.tagName !== 'LABEL') {
                    checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change'));
                }
            });
        });

        // Simulação de frete REAL usando API
        function simulateFreight() {
            const form = document.getElementById('simulation-form');
            const resultDiv = document.getElementById('simulation-result');
            const contentDiv = document.getElementById('simulation-content');
            
            // Coletar dados do formulário
            const data = {
                destination_zipcode: document.getElementById('sim_destination_zipcode').value,
                weight: document.getElementById('sim_weight').value,
                height: document.getElementById('sim_height').value,
                width: document.getElementById('sim_width').value,
                length: document.getElementById('sim_length').value,
                order_value: document.getElementById('sim_order_value').value
            };

            // Validação simples
            if (!data.destination_zipcode) {
                alert('Por favor, preencha o CEP de destino.');
                return;
            }

            if (parseFloat(data.weight) <= 0) {
                alert('Por favor, informe um peso válido.');
                return;
            }

            // Exibir carregamento
            contentDiv.innerHTML = `
                <div style="text-align: center; padding: 2rem;">
                    <span class="material-symbols-sharp" style="font-size: 2rem; animation: spin 1s linear infinite; display: inline-block;">refresh</span>
                    <p style="margin-top: 1rem; color: var(--color-info-dark);">Consultando APIs de frete...</p>
                    <small>Isso pode levar alguns segundos</small>
                </div>
            `;
            resultDiv.style.display = 'block';
            resultDiv.className = 'simulation-result';

            // Fazer chamada REAL para a API
            fetch('api-calcular-frete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    let html = '';
                    
                    // Se temos múltiplas opções
                    if (result.opcoes && result.opcoes.length > 1) {
                        html = `
                            <div style="margin-bottom: 1.5rem;">
                                <strong>Encontradas ${result.total_opcoes} opções disponíveis para esta rota:</strong>
                                <div style="font-size: 0.9em; color: var(--color-info-dark); margin-top: 0.5rem;">
                                    <em>ℹ️ A API só retorna serviços realmente disponíveis para o CEP de destino informado</em>
                                </div>
                            </div>
                        `;
                        
                        result.opcoes.forEach((opcao, index) => {
                            const isMelhor = index === 0 ? '' : '';
                            html += `
                                <div style="border: 1px solid var(--color-light); border-radius: 8px; padding: 1rem; margin-bottom: 1rem; ${index === 0 ? 'border-color: var(--color-success); background: rgba(65, 241, 182, 0.05);' : ''}">
                                    <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                                        <strong style="margin-right: 1rem;">${opcao.service_name}${isMelhor}</strong>
                                        <span style="font-size: 1.2em; font-weight: 600; color: var(--color-success);">R$ ${parseFloat(opcao.price).toFixed(2)}</span>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.9em; color: var(--color-info-dark);">
                                        <div><strong>Transportadora:</strong> ${opcao.service_company}</div>
                                        <div><strong>Prazo:</strong> ${opcao.delivery_time} dias</div>
                                    </div>
                                    ${opcao.original_price && opcao.original_price !== opcao.price ? 
                                        `<div style="font-size: 0.8em; color: var(--color-info-dark); margin-top: 0.5rem;">Valor original da API: R$ ${parseFloat(opcao.original_price).toFixed(2)}</div>` 
                                        : ''}
                                </div>
                            `;
                        });
                        
                        html += `
                            <div style="margin-top: 1rem; padding: 1rem; background: var(--color-background); border-radius: 8px;">
                                <strong>Melhor opção:</strong> ${result.melhor_opcao.service_name} por <strong>R$ ${parseFloat(result.melhor_opcao.price).toFixed(2)}</strong>
                            </div>
                        `;
                        
                    } else {
                        // Opção única (resultado antigo compatível)
                        const opcao = result.melhor_opcao || result;
                        html = `
                            <div style="display: grid; gap: 1rem;">
                                <div>
                                    <strong>Integração Utilizada:</strong> ${opcao.provider}
                                </div>
                                <div>
                                    <strong> Serviço:</strong> ${opcao.service_name || 'N/A'}
                                </div>
                        `;
                        
                        if (opcao.service_company) {
                            html += `
                                <div>
                                    <strong> Transportadora:</strong> ${opcao.service_company}
                                </div>
                            `;
                        }
                        
                        html += `
                                <div>
                                    <strong> Valor Calculado:</strong> R$ ${parseFloat(opcao.price).toFixed(2)}
                                </div>
                        `;
                        
                        if (opcao.original_price && opcao.original_price !== opcao.price) {
                            html += `
                                <div style="font-size: 0.9em; color: var(--color-info-dark);">
                                    <strong>Valor Original API:</strong> R$ ${parseFloat(opcao.original_price).toFixed(2)}
                                </div>
                            `;
                        }
                        
                        html += `
                                <div>
                                    <strong>Prazo de Entrega:</strong> ${opcao.delivery_time} dias úteis
                                </div>
                        `;
                        
                        if (opcao.delivery_range) {
                            html += `
                                <div style="font-size: 0.9em; color: var(--color-info-dark);">
                                    <strong>Faixa de Prazo:</strong> ${opcao.delivery_range.min} - ${opcao.delivery_range.max} dias
                                </div>
                            `;
                        }
                        
                        if (result.free_shipping) {
                            html += `
                                <div style="color: var(--color-success); font-weight: 600;">
                                    <strong> Frete Grátis Aplicado!</strong>
                                </div>
                            `;
                        } else if (result.fallback_used) {
                            html += `
                                <div style="color: var(--color-warning); font-weight: 600;">
                                    <strong> Fallback Aplicado</strong><br>
                                    <small>${result.message || 'APIs indisponíveis'}</small>
                                </div>
                            `;
                        } else {
                            html += `
                                <div style="color: var(--color-success);">
                                    <strong>Status:</strong> ✓ Cálculo realizado com sucesso
                                </div>
                            `;
                        }
                        
                        html += '</div>';
                    }
                    
                    contentDiv.innerHTML = html;
                    resultDiv.className = 'simulation-result';
                    
                } else {
                    // Erro no cálculo
                    let errorHtml = `
                        <div style="color: var(--color-danger);">
                            <strong>❌ Erro na Simulação:</strong><br>
                            ${result.error || 'Não foi possível calcular o frete.'}
                        </div>
                    `;
                    
                    if (result.api_errors && result.api_errors.length > 0) {
                        errorHtml += `
                            <div style="margin-top: 1rem; font-size: 0.9em; color: var(--color-info-dark);">
                                <strong>Detalhes dos Erros:</strong><br>
                                ${result.api_errors.join('<br>')}
                            </div>
                        `;
                    }
                    
                    contentDiv.innerHTML = errorHtml;
                    resultDiv.className = 'simulation-result error';
                }
                
                resultDiv.style.display = 'block';
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                contentDiv.innerHTML = `
                    <div style="color: var(--color-danger);">
                        <strong>❌ Erro de Conexão:</strong><br>
                        Não foi possível conectar com o servidor.<br>
                        <em>Verifique sua conexão e tente novamente.</em>
                    </div>
                `;
                resultDiv.className = 'simulation-result error';
                resultDiv.style.display = 'block';
            });
        }

        // Função para mostrar/esconder senha
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.parentElement.querySelector('.password-toggle');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = 'visibility_off';
            } else {
                input.type = 'password';
                icon.textContent = 'visibility';
            }
        }

        // Animação de rotação para o loading
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);

        // Auto-submit dos formulários quando toggle é alterado
        document.querySelectorAll('.toggle-switch input[type="checkbox"]').forEach(function(toggle) {
            toggle.addEventListener('change', function() {
                // Se não for o toggle de fallback, submeter o formulário automaticamente
                if (this.name === 'active') {
                    this.closest('form').submit();
                }
            });
        });
    </script>
 </body>
</html>