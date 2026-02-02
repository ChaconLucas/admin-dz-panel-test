<?php
/* 
 * PROCESSAMENTO DO PRÉ-CADASTRO DE REVENDEDORES
 * Este arquivo processa os dados do formulário e distribui para vendedoras
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Configuração do banco de dados
require_once 'PHP/conexao.php'; // Usar a conexão já existente

try {
    // Converter MySQLi para PDO usando as mesmas credenciais
    $pdo = new PDO("mysql:host=" . HOST . ";dbname=" . DB . ";charset=utf8mb4", USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Se não existir nenhum vendedor, criar um
    $check_exists = $pdo->query("SELECT COUNT(*) FROM vendedoras")->fetchColumn();
    if ($check_exists == 0) {
        $pdo->exec("INSERT INTO vendedoras (nome, whatsapp, email) VALUES ('Lucas Chacon', '21985136806', 'lucaschacon79@gmail.com')");
        error_log("Vendedor padrão criado automaticamente");
    }
    
    // Criar tabelas se não existirem
    $tables_sql = [
        "CREATE TABLE IF NOT EXISTS vendedoras (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nome VARCHAR(255) NOT NULL,
            whatsapp VARCHAR(20),
            email VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS leads_revendedores (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nome_responsavel VARCHAR(255) NOT NULL,
            whatsapp VARCHAR(20) NOT NULL,
            nome_loja VARCHAR(255) NOT NULL,
            cidade VARCHAR(100) NOT NULL,
            estado CHAR(2) NOT NULL,
            ramo_loja VARCHAR(100) NOT NULL,
            faturamento ENUM('ate_5000', '5001_15000', '15001_30000', 'acima_30000') NOT NULL,
            interesse SET('unha', 'cilios') NOT NULL,
            vendedora_id INT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_vendedora (vendedora_id)
        )",
        
        "CREATE TABLE IF NOT EXISTS controle_ciclo (
            id INT PRIMARY KEY AUTO_INCREMENT,
            vendedoras_usadas TEXT,
            ciclo_completo BOOLEAN DEFAULT FALSE,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    ];
    
    // Executar criação das tabelas
    foreach ($tables_sql as $sql) {
        $pdo->exec($sql);
    }
    
    // Verificar se existem vendedoras
    $check_vendedoras = $pdo->query("SELECT COUNT(*) as total FROM vendedoras");
    $total_vendedoras = $check_vendedoras->fetchColumn();
    
    error_log("DEBUG: Total de vendedoras encontradas: " . $total_vendedoras);
    
    if ($total_vendedoras == 0) {
        // Não existem vendedoras - criar uma
        $pdo->exec("INSERT INTO vendedoras (nome, whatsapp, email) VALUES ('Lucas Chacon', '21985136806', 'lucaschacon79@gmail.com')");
        error_log("DEBUG: Vendedor padrão criado");
        
        // Verificar novamente
        $recheck = $pdo->query("SELECT COUNT(*) FROM vendedoras");
        $final_count = $recheck->fetchColumn();
        
        if ($final_count == 0) {
            throw new Exception('Erro crítico: Não foi possível criar vendedores. Contate o administrador.');
        }
    }
    
    // Verificar se existe controle de ciclo, senão criar
    $check_ciclo = $pdo->query("SELECT COUNT(*) FROM controle_ciclo");
    if ($check_ciclo->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO controle_ciclo (vendedoras_usadas, ciclo_completo) VALUES ('', FALSE)");
    }
    
    // Validar dados recebidos
    $nome = trim($_POST['nome'] ?? '');
    $whatsapp = preg_replace('/[^0-9]/', '', $_POST['whatsapp'] ?? '');
    $loja = trim($_POST['loja'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = trim($_POST['estado'] ?? '');
    $ramo = trim($_POST['ramo'] ?? '');
    $faturamento = trim($_POST['faturamento'] ?? '');
    $interesse = $_POST['interesse'] ?? [];
    
    // Validações básicas
    if (empty($nome) || empty($whatsapp) || empty($loja) || 
        empty($cidade) || empty($estado) || empty($ramo) || 
        empty($faturamento) || empty($interesse)) {
        throw new Exception('Todos os campos são obrigatórios');
    }
    
    // Validar WhatsApp
    if (!preg_match('/^\d{10,11}$/', $whatsapp)) {
        throw new Exception('WhatsApp inválido');
    }
    
    // Converter interesse array para string
    $interesseString = implode(',', $interesse);
    
    // Verificar se já existe cadastro com mesmo WhatsApp (últimas 24h)
    $stmt = $pdo->prepare("
        SELECT id FROM leads_revendedores 
        WHERE whatsapp = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute([$whatsapp]);
    
    if ($stmt->rowCount() > 0) {
        throw new Exception('Já existe um cadastro recente com este WhatsApp');
    }
    
    // Função para distribuir vendedora
    function distribuirVendedora($pdo) {
        // Buscar todas as vendedoras (sem filtro de ativa)
        $stmt = $pdo->prepare("SELECT id FROM vendedoras ORDER BY id");
        $stmt->execute();
        $vendedoras = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($vendedoras)) {
            throw new Exception('Nenhuma vendedora encontrada');
        }
        
        // Buscar controle do ciclo
        $stmt = $pdo->prepare("SELECT * FROM controle_ciclo ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $ciclo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ciclo) {
            // Criar primeiro ciclo
            $stmt = $pdo->prepare("INSERT INTO controle_ciclo (vendedoras_usadas, ciclo_completo) VALUES ('', FALSE)");
            $stmt->execute();
            $vendedorasUsadas = [];
        } else {
            $vendedorasUsadas = $ciclo['vendedoras_usadas'] ? explode(',', $ciclo['vendedoras_usadas']) : [];
            // Converter para inteiros
            $vendedorasUsadas = array_map('intval', array_filter($vendedorasUsadas));
        }
        
        // Encontrar próxima vendedora
        $vendedorasDisponiveis = array_diff($vendedoras, $vendedorasUsadas);
        
        if (empty($vendedorasDisponiveis)) {
            // Reiniciar ciclo
            $vendedoraEscolhida = $vendedoras[0];
            $novasUsadas = [$vendedoraEscolhida];
            $cicloBool = false;
        } else {
            // Escolher primeira disponível  
            $vendedoraEscolhida = reset($vendedorasDisponiveis);
            $novasUsadas = array_merge($vendedorasUsadas, [$vendedoraEscolhida]);
            $cicloBool = (count($novasUsadas) >= count($vendedoras));
        }
        
        // Atualizar controle do ciclo
        $stmt = $pdo->prepare("
            UPDATE controle_ciclo 
            SET vendedoras_usadas = ?, ciclo_completo = ?, updated_at = NOW() 
            WHERE id = (SELECT id FROM (SELECT id FROM controle_ciclo ORDER BY id DESC LIMIT 1) as temp)
        ");
        $stmt->execute([implode(',', $novasUsadas), $cicloBool ? 1 : 0]);
        
        return $vendedoraEscolhida;
    }
    
    // Distribuir vendedora
    $vendedoraId = distribuirVendedora($pdo);
    
    // Capturar IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown');
    
    // Inserir lead
    $stmt = $pdo->prepare("
        INSERT INTO leads_revendedores 
        (nome_responsavel, whatsapp, nome_loja, cidade, estado, ramo_loja, 
         faturamento, interesse, vendedora_id, ip_address) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $nome, $whatsapp, $loja, $cidade, $estado, 
        $ramo, $faturamento, $interesseString, $vendedoraId, $ip
    ]);
    
    // Buscar informações completas da vendedora
    $stmt = $pdo->prepare("SELECT nome, whatsapp FROM vendedoras WHERE id = ?");
    $stmt->execute([$vendedoraId]);
    $vendedoraInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Log interno (opcional - para debug)
    error_log("Lead cadastrado: {$nome} ({$whatsapp}) -> Vendedora: {$vendedoraInfo['nome']}");
    
    // Resposta de sucesso com informações da vendedora
    echo json_encode([
        'success' => true,
        'message' => 'Cadastro realizado com sucesso!',
        'lead_id' => $pdo->lastInsertId(),
        'vendedora' => [
            'nome' => $vendedoraInfo['nome'],
            'whatsapp' => $vendedoraInfo['whatsapp'],
            'whatsapp_link' => 'https://wa.me/55' . $vendedoraInfo['whatsapp'] . '?text=Olá%20' . urlencode($vendedoraInfo['nome']) . '%2C%20acabei%20de%20me%20cadastrar%20como%20revendedor%20D%26Z%21'
        ]
    ]);
    
} catch (PDOException $e) {
    // Log do erro de banco
    error_log("Erro de banco no cadastro de lead: " . $e->getMessage());
    
    // Resposta de erro
    echo json_encode([
        'success' => false,
        'message' => 'Erro de conexão com banco de dados: ' . $e->getMessage(),
        'debug' => [
            'host' => HOST,
            'database' => DB,
            'user' => USUARIO
        ]
    ]);
    
} catch (Exception $e) {
    // Log do erro
    error_log("Erro no cadastro de lead: " . $e->getMessage());
    
    // Resposta de erro
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>