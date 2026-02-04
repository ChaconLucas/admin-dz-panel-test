<?php
// Suprimir warnings e notices para evitar HTML na resposta JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

session_start();

// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit();
}

// Incluir conexão com banco
require_once '../../../PHP/conexao.php';

header('Content-Type: application/json');

// Debug para verificar se chegou até aqui
error_log("Processamento iniciado para automação");

try {
    // Verificar se a conexão existe
    if (!isset($conexao) || $conexao->connect_error) {
        throw new Exception("Erro de conexão com o banco de dados");
    }
    
    // Verificar se existe o parâmetro action
    if (!isset($_POST['action'])) {
        echo json_encode(['success' => false, 'message' => 'Parâmetro action não encontrado']);
        exit();
    }
    
    // Debug dos dados recebidos
    error_log("Action recebida: " . $_POST['action']);
    error_log("POST data: " . print_r($_POST, true));
    
    if ($_POST['action'] === 'save_automation_config') {
        
        // Primeiro, verificar se a tabela existe e criar uma versão simples
        $table_check = $conexao->query("SHOW TABLES LIKE 'configuracoes_gerais'");
        if ($table_check->num_rows == 0) {
            // Criar tabela simples se não existir
            $create_table = $conexao->query("
                CREATE TABLE configuracoes_gerais (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    campo VARCHAR(100) UNIQUE NOT NULL,
                    valor TEXT
                )
            ");
            
            if (!$create_table) {
                throw new Exception("Erro ao criar tabela: " . $conexao->error);
            }
            error_log("Tabela configuracoes_gerais criada com sucesso (estrutura simples)");
            $field_name = 'campo';
            $has_timestamps = false;
        } else {
            // Verificar estrutura existente
            $columns_result = $conexao->query("SHOW COLUMNS FROM configuracoes_gerais");
            $columns = [];
            while ($col = $columns_result->fetch_assoc()) {
                $columns[] = $col['Field'];
            }
            
            error_log("Colunas existentes: " . implode(', ', $columns));
            
            // Determinar nome da coluna principal
            if (in_array('campo', $columns)) {
                $field_name = 'campo';
            } elseif (in_array('chave', $columns)) {
                $field_name = 'chave';
            } else {
                // Recriar tabela simples
                $conexao->query("DROP TABLE configuracoes_gerais");
                $create_table = $conexao->query("
                    CREATE TABLE configuracoes_gerais (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        campo VARCHAR(100) UNIQUE NOT NULL,
                        valor TEXT
                    )
                ");
                if (!$create_table) {
                    throw new Exception("Erro ao recriar tabela: " . $conexao->error);
                }
                $field_name = 'campo';
                $has_timestamps = false;
                error_log("Tabela recriada com estrutura simples");
            }
            
            // Verificar se tem colunas de timestamp
            $has_timestamps = in_array('created_at', $columns) && in_array('updated_at', $columns);
        }
        
        // Configurações para salvar - apenas as que estão sendo enviadas
        $configs = [];
        
        // Só adicionar configurações que realmente estão no POST
        $possible_configs = [
            'whatsapp_token', 'whatsapp_instancia', 'whatsapp_enabled',
            'smtp_host', 'smtp_porta', 'smtp_email', 'smtp_senha', 
            'trigger_stock', 'relatorio_diario_enabled', 'relatorio_diario_hora',
            'stock_critico_limite', 'stock_message'
        ];
        
        foreach ($possible_configs as $config_name) {
            if (isset($_POST[$config_name])) {
                $configs[$config_name] = $_POST[$config_name];
            }
        }
        
        // Garantir que valores dos checkboxes sejam tratados corretamente
        // Se um checkbox não está no POST, significa que está desmarcado (valor = 0)
        $checkbox_fields = ['whatsapp_enabled', 'trigger_stock', 'relatorio_diario_enabled'];
        foreach ($checkbox_fields as $field) {
            if (array_key_exists($field, $_POST)) {
                // Se a chave existe no POST, usar o valor (pode ser 0 via hidden ou 1 via checkbox)
                $configs[$field] = $_POST[$field];
            }
            // Se não existe no POST, não modificar na base de dados
        }
        
        // Debug: Log dos valores recebidos
        error_log("=== PROCESSAMENTO AUTOMAÇÃO ===");
        error_log("POST completo: " . print_r($_POST, true));
        error_log("Configurações que serão processadas: " . print_r($configs, true));
        error_log("Usando campo: $field_name, tem timestamps: " . ($has_timestamps ? 'sim' : 'não'));
        
        // Preparar statement baseado na estrutura da tabela
        if ($has_timestamps) {
            $sql = "
                INSERT INTO configuracoes_gerais ($field_name, valor, created_at, updated_at) 
                VALUES (?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                valor = VALUES(valor), updated_at = NOW()
            ";
        } else {
            $sql = "
                INSERT INTO configuracoes_gerais ($field_name, valor) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE 
                valor = VALUES(valor)
            ";
        }
        
        $stmt = $conexao->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Erro ao preparar statement: " . $conexao->error);
        }
        
        $saved_count = 0;
        $errors = [];
        $saved_configs = [];
        
        // Inserir/atualizar cada configuração
        foreach ($configs as $campo => $valor) {
            if ($stmt->bind_param("ss", $campo, $valor) && $stmt->execute()) {
                $saved_count++;
                $saved_configs[] = $campo;
                error_log("✅ Configuração '$campo' salva com valor '$valor' usando campo '$field_name'");
            } else {
                $errors[] = "Erro ao salvar '$campo': " . $stmt->error;
                error_log("❌ Erro ao salvar configuração '$campo': " . $stmt->error);
            }
        }
        
        $stmt->close();
        
        // Log da atividade (opcional - pode falhar sem afetar o resultado)
        try {
            $admin_nome = $_SESSION['usuario_nome'] ?? 'Admin';
            $log_stmt = $conexao->prepare("INSERT INTO admin_logs (admin_nome, acao, data_hora) VALUES (?, 'atualizou configurações de automação', NOW())");
            if ($log_stmt) {
                $log_stmt->bind_param("s", $admin_nome);
                $log_stmt->execute();
                $log_stmt->close();
            }
        } catch (Exception $log_error) {
            error_log("Erro ao gravar log (não crítico): " . $log_error->getMessage());
        }
        
        if ($saved_count > 0) {
            echo json_encode([
                'success' => true, 
                'message' => "Salvo com sucesso! (" . implode(', ', $saved_configs) . ")",
                'saved_count' => $saved_count,
                'saved_configs' => $saved_configs,
                'errors' => $errors,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Nenhuma configuração foi enviada para salvar.',
                'post_data' => $_POST,
                'errors' => $errors
            ]);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Ação não reconhecida: ' . ($_POST['action'] ?? 'nenhuma')]);
    }
    
} catch (Exception $e) {
    error_log("Erro em processa_automacao.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno: ' . $e->getMessage(),
        'debug' => [
            'file' => __FILE__,
            'line' => $e->getLine(),
            'post_data' => $_POST
        ]
    ]);
} finally {
    if (isset($conexao) && $conexao instanceof mysqli) {
        $conexao->close();
    }
}
?>