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

// Criar tabelas necessárias se não existirem (sem foreign keys para evitar problemas)
$tables_to_check = [
    'vendedoras' => "
    CREATE TABLE IF NOT EXISTS vendedoras (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nome VARCHAR(255) NOT NULL,
        whatsapp VARCHAR(20),
        email VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    'leads_revendedores' => "
    CREATE TABLE IF NOT EXISTS leads_revendedores (
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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    'controle_ciclo' => "
    CREATE TABLE IF NOT EXISTS controle_ciclo (
        id INT PRIMARY KEY AUTO_INCREMENT,
        vendedoras_usadas TEXT,
        ciclo_completo BOOLEAN DEFAULT FALSE,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )"
];

foreach ($tables_to_check as $table_name => $create_query) {
    $result = mysqli_query($conexao, $create_query);
    
    if (!$result) {
        error_log("Erro ao criar tabela $table_name: " . mysqli_error($conexao));
        continue;
    }
    
    // Inserir dados iniciais se necessário
    if ($table_name === 'vendedoras') {
        $check_vendedoras = "SELECT COUNT(*) as total FROM vendedoras";
        $check_result = mysqli_query($conexao, $check_vendedoras);
        
        if ($check_result) {
            $count = mysqli_fetch_assoc($check_result)['total'];
            if ($count == 0) {
                // Não criar vendedores automaticamente - usuário deve criar manualmente
                echo "<div class='alert alert-warning' style='background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 10px; margin: 20px 0;'>";
                echo "🚨 <strong>Atenção:</strong> Nenhum vendedor encontrado. <a href='gerenciar-vendedoras.php' style='color: #007bff;'>Clique aqui para adicionar vendedores</a>";
                echo "</div>";
            }
        }
    } elseif ($table_name === 'controle_ciclo') {
        $check_ciclo = "SELECT COUNT(*) as total FROM controle_ciclo";
        $check_result = mysqli_query($conexao, $check_ciclo);
        
        if ($check_result) {
            $count = mysqli_fetch_assoc($check_result)['total'];
            if ($count == 0) {
                $insert_cycle = "INSERT INTO controle_ciclo (vendedoras_usadas, ciclo_completo) VALUES ('', FALSE)";
                mysqli_query($conexao, $insert_cycle);
            }
        }
    }
}

// Processar exportação CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Buscar todos os leads
    $export_sql = "
        SELECT 
            l.id,
            l.nome_responsavel,
            l.whatsapp,
            l.nome_loja,
            l.cidade,
            l.estado,
            l.ramo_loja,
            CASE l.faturamento
                WHEN 'ate_5000' THEN 'Até R$ 5.000'
                WHEN '5001_15000' THEN 'R$ 5.001 - R$ 15.000'
                WHEN '15001_30000' THEN 'R$ 15.001 - R$ 30.000'
                WHEN 'acima_30000' THEN 'Acima de R$ 30.000'
            END as faturamento,
            l.interesse,
            v.nome as vendedora_responsavel,
            DATE_FORMAT(l.created_at, '%d/%m/%Y %H:%i') as data_cadastro,
            l.ip_address
        FROM leads_revendedores l 
        LEFT JOIN vendedoras v ON l.vendedora_id = v.id 
        ORDER BY l.created_at DESC
    ";
    
    $export_result = mysqli_query($conexao, $export_sql);
    
    if ($export_result) {
        // Definir headers para download CSV
        $filename = 'relatorio_revendedores_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Abrir output stream
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8 (garante acentos no Excel)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Cabeçalhos do CSV
        fputcsv($output, [
            'ID',
            'Nome Responsável',
            'WhatsApp', 
            'Nome da Loja',
            'Cidade',
            'Estado',
            'Ramo da Loja',
            'Faturamento',
            'Interesse',
            'Vendedora Responsável',
            'Data de Cadastro',
            'IP de Origem'
        ], ';');
        
        // Dados
        while ($row = mysqli_fetch_assoc($export_result)) {
            fputcsv($output, [
                $row['id'],
                $row['nome_responsavel'],
                $row['whatsapp'],
                $row['nome_loja'],
                $row['cidade'],
                $row['estado'],
                $row['ramo_loja'],
                $row['faturamento'],
                $row['interesse'],
                $row['vendedora_responsavel'] ?? 'Não atribuído',
                $row['data_cadastro'],
                $row['ip_address']
            ], ';');
        }
        
        fclose($output);
        exit();
    }
}

// Processar ações de leads
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'edit_lead') {
        $id = intval($_POST['id']);
        $nome_responsavel = trim($_POST['nome_responsavel']);
        $whatsapp = preg_replace('/[^0-9]/', '', $_POST['whatsapp']);
        $nome_loja = trim($_POST['nome_loja']);
        $cidade = trim($_POST['cidade']);
        $estado = trim($_POST['estado']);
        $ramo_loja = $_POST['ramo_loja'];
        $faturamento = $_POST['faturamento'];
        $interesse = isset($_POST['interesse']) ? implode(',', $_POST['interesse']) : '';
        $vendedora_id = intval($_POST['vendedora_id']);
        
        $sql = "UPDATE leads_revendedores SET nome_responsavel = ?, whatsapp = ?, nome_loja = ?, cidade = ?, estado = ?, ramo_loja = ?, faturamento = ?, interesse = ?, vendedora_id = ? WHERE id = ?";
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($stmt, "ssssssssii", $nome_responsavel, $whatsapp, $nome_loja, $cidade, $estado, $ramo_loja, $faturamento, $interesse, $vendedora_id, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Lead atualizado com sucesso!";
        } else {
            $error_msg = "Erro ao atualizar lead: " . mysqli_error($conexao);
        }
        mysqli_stmt_close($stmt);
    }
    elseif ($action === 'delete_lead') {
        $id = intval($_POST['id']);
        
        $sql = "DELETE FROM leads_revendedores WHERE id = ?";
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Lead excluído com sucesso!";
        } else {
            $error_msg = "Erro ao excluir lead: " . mysqli_error($conexao);
        }
        mysqli_stmt_close($stmt);
    }
}

// Buscar estatísticas completas
$stats = [];

// Inicializar com valores padrão
$stats['total_leads'] = 0;
$stats['leads_hoje'] = 0;
$stats['por_vendedora'] = [];
$stats['por_ramo'] = [];
$stats['interesse'] = ['unha' => 0, 'cilios' => 0];
$stats['por_faturamento'] = [];
$stats['por_dia'] = [];

// Total de leads
$total_query = "SELECT COUNT(*) as total FROM leads_revendedores";
$total_result = mysqli_query($conexao, $total_query);
if ($total_result) {
    $row = mysqli_fetch_assoc($total_result);
    $stats['total_leads'] = $row['total'] ?? 0;
}

// Leads hoje
$hoje_query = "SELECT COUNT(*) as hoje FROM leads_revendedores WHERE DATE(created_at) = CURDATE()";
$hoje_result = mysqli_query($conexao, $hoje_query);
if ($hoje_result) {
    $row = mysqli_fetch_assoc($hoje_result);
    $stats['leads_hoje'] = $row['hoje'] ?? 0;
}

// Leads por vendedora
$vendedora_query = "
SELECT v.nome, COUNT(l.id) as total_leads
FROM vendedoras v
LEFT JOIN leads_revendedores l ON v.id = l.vendedora_id
GROUP BY v.id, v.nome
ORDER BY total_leads DESC";
$vendedora_result = mysqli_query($conexao, $vendedora_query);
if ($vendedora_result) {
    while ($row = mysqli_fetch_assoc($vendedora_result)) {
        $stats['por_vendedora'][] = $row;
    }
}

// Leads por ramo (só se existir dados)
if ($stats['total_leads'] > 0) {
    $ramo_query = "
    SELECT ramo_loja, COUNT(*) as total
    FROM leads_revendedores
    GROUP BY ramo_loja
    ORDER BY total DESC";
    $ramo_result = mysqli_query($conexao, $ramo_query);
    if ($ramo_result) {
        while ($row = mysqli_fetch_assoc($ramo_result)) {
            $stats['por_ramo'][] = $row;
        }
    }
}

// Leads por interesse (só se existir dados)
if ($stats['total_leads'] > 0) {
    $interesse_query = "
    SELECT 
        SUM(FIND_IN_SET('unha', interesse) > 0) as unha,
        SUM(FIND_IN_SET('cilios', interesse) > 0) as cilios
    FROM leads_revendedores";
    $interesse_result = mysqli_query($conexao, $interesse_query);
    if ($interesse_result) {
        $row = mysqli_fetch_assoc($interesse_result);
        $stats['interesse'] = $row ?? ['unha' => 0, 'cilios' => 0];
    }
}

// Leads por faturamento (só se existir dados)
if ($stats['total_leads'] > 0) {
    $faturamento_query = "
    SELECT 
        faturamento,
        COUNT(*) as total,
        CASE 
            WHEN faturamento = 'ate_5000' THEN 'Até R$ 5.000'
            WHEN faturamento = '5001_15000' THEN 'R$ 5.001 - 15.000'
            WHEN faturamento = '15001_30000' THEN 'R$ 15.001 - 30.000'
            WHEN faturamento = 'acima_30000' THEN 'Acima de R$ 30.000'
        END as label
    FROM leads_revendedores
    GROUP BY faturamento
    ORDER BY total DESC";
    $faturamento_result = mysqli_query($conexao, $faturamento_query);
    if ($faturamento_result) {
        while ($row = mysqli_fetch_assoc($faturamento_result)) {
            $stats['por_faturamento'][] = $row;
        }
    }
}

// Leads por dia (últimos 7 dias) - só se existir dados
if ($stats['total_leads'] > 0) {
    $dias_query = "
    SELECT 
        DATE(created_at) as data,
        COUNT(*) as total
    FROM leads_revendedores
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY data ASC";
    $dias_result = mysqli_query($conexao, $dias_query);
    if ($dias_result) {
        while ($row = mysqli_fetch_assoc($dias_result)) {
            $stats['por_dia'][] = $row;
        }
    }
}

// Buscar todos os leads com informações da vendedora
$leads = [];
if ($stats['total_leads'] > 0) {
    $leads_query = "
    SELECT 
        l.*,
        v.nome as vendedora_nome
    FROM leads_revendedores l
    LEFT JOIN vendedoras v ON l.vendedora_id = v.id
    ORDER BY l.created_at DESC";
    $leads_result = mysqli_query($conexao, $leads_query);
    if ($leads_result) {
        $leads = mysqli_fetch_all($leads_result, MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revendedores - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp" rel="stylesheet">
    <link rel="stylesheet" href="../../css/dashboard.css">
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
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--color-white);
            padding: 1.8rem;
            border-radius: var(--card-border-radius);
            box-shadow: var(--box-shadow);
            text-align: center;
            border-left: 4px solid var(--color-primary);
        }
        
        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--color-primary);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--color-info-dark);
            font-weight: 500;
        }
        
        .revendedores-list {
            display: grid;
            gap: 1.5rem;
        }
        
        .revendedor-card {
            background: var(--color-white);
            border-radius: var(--card-border-radius);
            padding: 2rem;
            box-shadow: var(--box-shadow);
            border-left: 4px solid;
            transition: transform 0.3s ease;
        }
        
        .revendedor-card:hover {
            transform: translateY(-2px);
        }
        
        .card-pendente {
            border-left-color: var(--color-warning);
        }
        
        .card-aprovado {
            border-left-color: var(--color-success);
        }
        
        .card-rejeitado {
            border-left-color: var(--color-danger);
        }
        
        .revendedor-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        
        .revendedor-info h3 {
            margin: 0 0 0.5rem 0;
            color: var(--color-dark);
            font-size: 1.3rem;
        }
        
        .revendedor-info .email {
            color: var(--color-primary);
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .revendedor-info .date {
            color: var(--color-info-dark);
            font-size: 0.9rem;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-pendente {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
            border: 1px solid var(--color-warning);
        }
        
        .status-aprovado {
            background: rgba(65, 241, 182, 0.1);
            color: #0c5460;
            border: 1px solid var(--color-success);
        }
        
        .status-rejeitado {
            background: rgba(255, 119, 130, 0.1);
            color: #721c24;
            border: 1px solid var(--color-danger);
        }
        
        .revendedor-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--color-dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .detail-value {
            color: var(--color-info-dark);
            line-height: 1.5;
        }
        
        .actions {
            display: flex;
            gap: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--color-info-light);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-aprovar {
            background: var(--color-success);
            color: white;
        }
        
        .btn-rejeitar {
            background: var(--color-danger);
            color: white;
        }
        
        .btn-excluir {
            background: var(--color-dark-variant);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--color-info-dark);
        }
        
        .empty-state .icon {
            font-size: 4rem;
            color: var(--color-info-light);
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .revendedor-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .revendedor-details {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
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
                    <span class="message-count"><?php echo $nao_lidas; ?></span>
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

                <a href="revendedores.php" class="active">
                    <span class="material-symbols-sharp">handshake</span>
                    <h3>Revendedores</h3>
                </a>

                <a href="gerenciar-vendedoras.php">
                    <span class="material-symbols-sharp">support_agent</span>
                    <h3>Vendedores</h3>
                </a>

                <a href="../../../PHP/logout.php">
                    <span class="material-symbols-sharp">Logout</span>
                    <h3>Sair</h3>
                </a>
            </div>
        </aside>

        <main>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h1 style="margin: 0;">
                    <span class="material-symbols-sharp">handshake</span>
                    Leads de Revendedores
                </h1>
                
                <a href="?export=csv" style="background: #28a745; color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; font-weight: 500; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);" 
                   onmouseover="this.style.background='#218838'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(40, 167, 69, 0.4)'" 
                   onmouseout="this.style.background='#28a745'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(40, 167, 69, 0.3)'">
                    <span class="material-symbols-sharp" style="font-size: 18px;">download</span>
                    Baixar Relatório CSV
                </a>
            </div>
            
            <!-- Mensagens de feedback -->
            <?php if (isset($success_msg)): ?>
                <div style="background: var(--color-white); border: 2px solid var(--color-success); color: var(--color-success); padding: 1rem; border-radius: var(--card-border-radius); margin: 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
                    <span class="material-symbols-sharp">check_circle</span>
                    <?= $success_msg ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_msg)): ?>
                <div style="background: var(--color-white); border: 2px solid var(--color-danger); color: var(--color-danger); padding: 1rem; border-radius: var(--card-border-radius); margin: 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
                    <span class="material-symbols-sharp">error</span>
                    <?= $error_msg ?>
                </div>
            <?php endif; ?>
            
            <!-- Estatísticas Principais -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_leads']; ?></div>
                    <div class="stat-label">Total de Leads</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['leads_hoje']; ?></div>
                    <div class="stat-label">Leads Hoje</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($stats['por_vendedora']); ?></div>
                    <div class="stat-label">Vendedores</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($stats['por_ramo']); ?></div>
                    <div class="stat-label">Tipos de Negócio</div>
                </div>
            </div>

            <!-- Gráficos -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin: 2rem 0;">
                
                <!-- Gráfico: Leads por Vendedora -->
                <div style="background: var(--color-white); padding: 2rem; border-radius: var(--card-border-radius); box-shadow: var(--box-shadow); height: 350px;">
                    <h3 style="margin-bottom: 1rem; color: var(--color-dark);">
                        <span class="material-symbols-sharp">person</span>
                        Leads por Vendedora
                    </h3>
                    <div style="height: 250px; position: relative;">
                        <canvas id="graficoVendedoras"></canvas>
                    </div>
                </div>

                <!-- Gráfico: Leads por Ramo -->
                <div style="background: var(--color-white); padding: 2rem; border-radius: var(--card-border-radius); box-shadow: var(--box-shadow); height: 350px;">
                    <h3 style="margin-bottom: 1rem; color: var(--color-dark);">
                        <span class="material-symbols-sharp">business</span>
                        Leads por Ramo
                    </h3>
                    <div style="height: 250px; position: relative;">
                        <canvas id="graficoRamos"></canvas>
                    </div>
                </div>

            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                
                <!-- Gráfico: Interesse -->
                <div style="background: var(--color-white); padding: 2rem; border-radius: var(--card-border-radius); box-shadow: var(--box-shadow); height: 350px;">
                    <h3 style="margin-bottom: 1rem; color: var(--color-dark);">
                        <span class="material-symbols-sharp">favorite</span>
                        Interesse dos Leads
                    </h3>
                    <div style="height: 250px; position: relative;">
                        <canvas id="graficoInteresse"></canvas>
                    </div>
                </div>

                <!-- Gráfico: Leads por Dia -->
                <div style="background: var(--color-white); padding: 2rem; border-radius: var(--card-border-radius); box-shadow: var(--box-shadow); height: 350px;">
                    <h3 style="margin-bottom: 1rem; color: var(--color-dark);">
                        <span class="material-symbols-sharp">trending_up</span>
                        Leads nos Últimos 7 Dias
                    </h3>
                    <div style="height: 250px; position: relative;">
                        <canvas id="graficoDias"></canvas>
                    </div>
                </div>

            </div>

            <!-- Tabela de Leads -->
            <div style="background: var(--color-white); border-radius: var(--card-border-radius); box-shadow: var(--box-shadow); overflow: hidden;">
                <div style="padding: 2rem; border-bottom: 1px solid var(--color-info-light);">
                    <h3 style="margin: 0; color: var(--color-dark); display: flex; align-items: center; gap: 0.5rem;">
                        <span class="material-symbols-sharp">table_view</span>
                        Lista de Leads Cadastrados
                    </h3>
                </div>
                
                <?php if (empty($leads)): ?>
                    <div style="padding: 3rem; text-align: center; color: var(--color-info-dark);">
                        <span class="material-symbols-sharp" style="font-size: 4rem; color: var(--color-info-light); margin-bottom: 1rem; display: block;">people_outline</span>
                        <h3>Nenhum lead ainda</h3>
                        <p>Quando alguém se cadastrar como revendedor, os leads aparecerão aqui.</p>
                        <a href="../../../cadastro-revendedor.php" target="_blank" style="display: inline-block; margin-top: 1rem; padding: 0.75rem 1.5rem; background: var(--color-primary); color: white; text-decoration: none; border-radius: 8px;">
                            <span class="material-symbols-sharp" style="vertical-align: middle;">open_in_new</span>
                            Testar Cadastro
                        </a>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--color-light); border-bottom: 2px solid var(--color-info-light);">
                                    <th style="padding: 1rem; text-align: left; color: var(--color-dark); font-weight: 600;">Responsável</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--color-dark); font-weight: 600;">Loja</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--color-dark); font-weight: 600;">WhatsApp</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--color-dark); font-weight: 600;">Localização</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--color-dark); font-weight: 600;">Ramo</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--color-dark); font-weight: 600;">Interesse</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--color-dark); font-weight: 600;">Faturamento</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--color-dark); font-weight: 600;">Vendedora</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--color-dark); font-weight: 600;">Data</th>
                                    <th style="padding: 1rem; text-align: center; color: var(--color-dark); font-weight: 600;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leads as $lead): ?>
                                    <tr style="border-bottom: 1px solid var(--color-info-light);">
                                        <td style="padding: 1rem;">
                                            <strong style="color: var(--color-dark);"><?php echo htmlspecialchars($lead['nome_responsavel']); ?></strong>
                                        </td>
                                        <td style="padding: 1rem; color: var(--color-info-dark);">
                                            <?php echo htmlspecialchars($lead['nome_loja']); ?>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <a href="https://wa.me/55<?php echo $lead['whatsapp']; ?>" 
                                               target="_blank" 
                                               style="color: var(--color-success); text-decoration: none; display: flex; align-items: center; gap: 0.25rem;">
                                                <span class="material-symbols-sharp" style="font-size: 1rem;">call</span>
                                                <?php echo htmlspecialchars($lead['whatsapp']); ?>
                                            </a>
                                        </td>
                                        <td style="padding: 1rem; color: var(--color-info-dark);">
                                            <?php echo htmlspecialchars($lead['cidade'] . ', ' . $lead['estado']); ?>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <span style="padding: 0.25rem 0.5rem; background: var(--color-light); border-radius: 4px; font-size: 0.85rem; color: var(--color-dark);">
                                                <?php echo ucfirst(str_replace('_', ' ', $lead['ramo_loja'])); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <?php 
                                            $interesses = explode(',', $lead['interesse']);
                                            foreach ($interesses as $int) {
                                                $cor = $int === 'unha' ? 'var(--color-primary)' : 'var(--color-success)';
                                                echo "<span style='padding: 0.25rem 0.5rem; background: {$cor}; color: white; border-radius: 4px; font-size: 0.75rem; margin-right: 0.25rem; display: inline-block;'>" . ucfirst($int) . "</span>";
                                            }
                                            ?>
                                        </td>
                                        <td style="padding: 1rem; color: var(--color-info-dark); font-size: 0.9rem;">
                                            <?php 
                                            $faturamento_labels = [
                                                'ate_5000' => 'Até R$ 5k',
                                                '5001_15000' => 'R$ 5-15k',
                                                '15001_30000' => 'R$ 15-30k',
                                                'acima_30000' => 'R$ 30k+'
                                            ];
                                            echo $faturamento_labels[$lead['faturamento']] ?? $lead['faturamento'];
                                            ?>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <span style="color: var(--color-primary); font-weight: 500;">
                                                <?php echo htmlspecialchars($lead['vendedora_nome'] ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1rem; color: var(--color-info-dark); font-size: 0.9rem;">
                                            <?php echo date('d/m/Y H:i', strtotime($lead['created_at'])); ?>
                                        </td>
                                        <td style="padding: 1rem; text-align: center;">
                                            <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                                <button onclick="editLeadInline(<?= htmlspecialchars(json_encode($lead)) ?>)" 
                                                        style="background: transparent; color: #6b7280; border: none; padding: 0.25rem; cursor: pointer; display: flex; align-items: center; transition: all 0.2s; border-radius: 0.25rem;" 
                                                        title="Editar" onmouseover="this.style.background='#f3f4f6'; this.style.color='#f59e0b'" onmouseout="this.style.background='transparent'; this.style.color='#6b7280'">
                                                    <span class="material-symbols-sharp" style="font-size: 1.125rem;">edit</span>
                                                </button>
                                                <button onclick="deleteLeadInline(<?= $lead['id'] ?>, '<?= htmlspecialchars($lead['nome_responsavel']) ?>')" 
                                                        style="background: transparent; color: #6b7280; border: none; padding: 0.25rem; cursor: pointer; display: flex; align-items: center; transition: all 0.2s; border-radius: 0.25rem;" 
                                                        title="Excluir" onmouseover="this.style.background='#fef2f2'; this.style.color='#ef4444'" onmouseout="this.style.background='transparent'; this.style.color='#6b7280'">
                                                    <span class="material-symbols-sharp" style="font-size: 1.125rem;">delete</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Modal de Edição de Lead -->
            <div id="edit-lead-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
                <div style="background: var(--color-white); border-radius: var(--card-border-radius); padding: 2rem; width: 90%; max-width: 600px; max-height: 80vh; overflow-y: auto; position: relative; box-shadow: 0 20px 25px -5px rgba(255, 20, 147, 0.15), 0 10px 10px -5px rgba(255, 20, 147, 0.1); border: 2px solid rgba(255, 20, 147, 0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 style="margin: 0; color: #ff1493; font-size: 1.25rem; font-weight: 600;">Editar Lead</h3>
                        <button onclick="cancelEditLead()" style="background: transparent; border: none; color: var(--color-info-dark); cursor: pointer; padding: 0.25rem; border-radius: var(--border-radius-1); transition: all 0.2s;" onmouseover="this.style.background='rgba(255, 20, 147, 0.1)'; this.style.color='#ff1493'" onmouseout="this.style.background='transparent'; this.style.color='var(--color-info-dark)'">
                            <span class="material-symbols-sharp" style="font-size: 1.25rem;">close</span>
                        </button>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_lead">
                        <input type="hidden" name="id" id="edit_lead_id">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div>
                                <label style="display: block; font-weight: 500; color: var(--color-dark); margin-bottom: 0.5rem; font-size: 0.875rem;">Nome Responsável</label>
                                <input type="text" name="nome_responsavel" id="edit_lead_nome_responsavel" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); font-size: 0.875rem; transition: all 0.2s; background: var(--color-white);" required onfocus="this.style.borderColor='#ff1493'; this.style.boxShadow='0 0 0 3px rgba(255, 20, 147, 0.1)'" onblur="this.style.borderColor='var(--color-info-light)'; this.style.boxShadow='none'">
                            </div>
                            
                            <div>
                                <label style="display: block; font-weight: 500; color: var(--color-dark); margin-bottom: 0.5rem; font-size: 0.875rem;">WhatsApp</label>
                                <input type="text" name="whatsapp" id="edit_lead_whatsapp" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); font-size: 0.875rem; transition: all 0.2s; background: var(--color-white);" required onfocus="this.style.borderColor='#ff1493'; this.style.boxShadow='0 0 0 3px rgba(255, 20, 147, 0.1)'" onblur="this.style.borderColor='var(--color-info-light)'; this.style.boxShadow='none'">
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; font-weight: 500; color: var(--color-dark); margin-bottom: 0.5rem; font-size: 0.875rem;">Nome da Loja</label>
                            <input type="text" name="nome_loja" id="edit_lead_nome_loja" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); font-size: 0.875rem; transition: all 0.2s; background: var(--color-white);" required onfocus="this.style.borderColor='#ff1493'; this.style.boxShadow='0 0 0 3px rgba(255, 20, 147, 0.1)'" onblur="this.style.borderColor='var(--color-info-light)'; this.style.boxShadow='none'">
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div>
                                <label style="display: block; font-weight: 500; color: var(--color-dark); margin-bottom: 0.5rem; font-size: 0.875rem;">Cidade</label>
                                <input type="text" name="cidade" id="edit_lead_cidade" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); font-size: 0.875rem; transition: all 0.2s; background: var(--color-white);" required onfocus="this.style.borderColor='#ff1493'; this.style.boxShadow='0 0 0 3px rgba(255, 20, 147, 0.1)'" onblur="this.style.borderColor='var(--color-info-light)'; this.style.boxShadow='none'">
                            </div>
                            
                            <div>
                                <label style="display: block; font-weight: 500; color: var(--color-dark); margin-bottom: 0.5rem; font-size: 0.875rem;">Estado</label>
                                <input type="text" name="estado" id="edit_lead_estado" maxlength="2" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); font-size: 0.875rem; transition: all 0.2s; background: var(--color-white);" required onfocus="this.style.borderColor='#ff1493'; this.style.boxShadow='0 0 0 3px rgba(255, 20, 147, 0.1)'" onblur="this.style.borderColor='var(--color-info-light)'; this.style.boxShadow='none'">
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div>
                                <label style="display: block; font-weight: 500; color: var(--color-dark); margin-bottom: 0.5rem; font-size: 0.875rem;">Ramo da Loja</label>
                                <select name="ramo_loja" id="edit_lead_ramo_loja" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); font-size: 0.875rem; background: var(--color-white);" required>
                                    <option value="salao_beleza">Salão de Beleza</option>
                                    <option value="clinica_estetica">Clínica Estética</option>
                                    <option value="loja_cosmeticos">Loja de Cosméticos</option>
                                    <option value="studio_unhas">Studio de Unhas</option>
                                    <option value="outro">Outro</option>
                                </select>
                            </div>
                            
                            <div>
                                <label style="display: block; font-weight: 500; color: var(--color-dark); margin-bottom: 0.5rem; font-size: 0.875rem;">Faturamento</label>
                                <select name="faturamento" id="edit_lead_faturamento" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); font-size: 0.875rem; background: var(--color-white);" required>
                                    <option value="ate_5000">Até R$ 5k</option>
                                    <option value="5001_15000">R$ 5-15k</option>
                                    <option value="15001_30000">R$ 15-30k</option>
                                    <option value="acima_30000">R$ 30k+</option>
                                </select>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; font-weight: 500; color: var(--color-dark); margin-bottom: 0.5rem; font-size: 0.875rem;">Interesse</label>
                            <select name="interesse" id="edit_lead_interesse" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); font-size: 0.875rem; background: var(--color-white);" required>
                                <option value="">Selecione o interesse</option>
                                <option value="unha">Unha</option>
                                <option value="cilios">Cílios</option>
                                <option value="unha,cilios">Unha e Cílios</option>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; font-weight: 500; color: var(--color-dark); margin-bottom: 0.5rem; font-size: 0.875rem;">Vendedora</label>
                            <select name="vendedora_id" id="edit_lead_vendedora_id" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); font-size: 0.875rem; background: var(--color-white);" required>
                                <?php 
                                $vendedoras_query = "SELECT id, nome FROM vendedoras ORDER BY nome";
                                $vendedoras_result = mysqli_query($conexao, $vendedoras_query);
                                if ($vendedoras_result) {
                                    while ($vendedora = mysqli_fetch_assoc($vendedoras_result)) {
                                        echo "<option value='{$vendedora['id']}'>{$vendedora['nome']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                            <button type="button" onclick="cancelEditLead()" style="background: transparent; color: var(--color-dark-variant); border: 1px solid var(--color-info-light); padding: 0.625rem 1rem; border-radius: var(--border-radius-1); font-weight: 500; cursor: pointer; font-size: 0.875rem; transition: all 0.2s;" onmouseover="this.style.background='var(--color-light)'" onmouseout="this.style.background='transparent'">Cancelar</button>
                            <button type="submit" style="background: #ff1493; color: white; padding: 0.625rem 1rem; border: none; border-radius: var(--border-radius-1); font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.375rem; font-size: 0.875rem; transition: all 0.2s;" onmouseover="this.style.background='#e91e63'" onmouseout="this.style.background='#ff1493'">
                                <span class="material-symbols-sharp" style="font-size: 1rem;">save</span>
                                Salvar Alterações
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>

        <div class="right">
            <div class="top">
                <button id="menu-btn">
                    <span class="material-symbols-sharp">menu</span>
                </button>
                <div class="theme-toggler">
                    <span class="material-symbols-sharp active">light_mode</span>
                    <span class="material-symbols-sharp">dark_mode</span>
                </div>
                <div class="profile">
                    <div class="info">
                        <p>Olá, <b><?php echo isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : 'Usuário'; ?></b></p>
                        <small class="text-muted">Administrador</small>
                    </div>
                    <div class="profile-photo">
                        <img src="../../../assets/images/logo_redondo.png" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // Dados do PHP para JavaScript
        const dadosVendedoras = <?php echo json_encode($stats['por_vendedora']); ?>;
        const dadosRamos = <?php echo json_encode($stats['por_ramo']); ?>;
        const dadosInteresse = <?php echo json_encode($stats['interesse']); ?>;
        const dadosPorDia = <?php echo json_encode($stats['por_dia']); ?>;

        // Configuração padrão dos gráficos
        Chart.defaults.font.family = 'Arial, sans-serif';
        Chart.defaults.color = '#666';
        
        // Aguardar carregamento completo da página
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
        });
        
        function initializeCharts() {

            // Gráfico: Leads por Vendedora
            const ctxVendedoras = document.getElementById('graficoVendedoras');
            if (ctxVendedoras) {
                new Chart(ctxVendedoras.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: dadosVendedoras.length > 0 ? dadosVendedoras.map(v => v.nome) : ['Nenhuma vendedora'],
                        datasets: [{
                            label: 'Leads',
                            data: dadosVendedoras.length > 0 ? dadosVendedoras.map(v => parseInt(v.total_leads)) : [0],
                            backgroundColor: 'rgba(255, 0, 212, 0.6)',
                            borderColor: '#ff00d4',
                            borderWidth: 2,
                            borderRadius: 8,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { stepSize: 1 }
                            }
                        }
                    }
                });
            }

            // Gráfico: Leads por Ramo
            const ctxRamos = document.getElementById('graficoRamos');
            if (ctxRamos) {
                new Chart(ctxRamos.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: dadosRamos.length > 0 ? dadosRamos.map(r => r.ramo_loja.replace('_', ' ')) : ['Nenhum dado'],
                        datasets: [{
                            data: dadosRamos.length > 0 ? dadosRamos.map(r => parseInt(r.total)) : [1],
                            backgroundColor: [
                                '#ff00d4', '#111e88', '#41f1b6', '#ffcd07', 
                                '#ff7782', '#17c0eb', '#7c3aed', '#f59e0b'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { padding: 15 }
                            }
                        }
                    }
                });
            }

            // Gráfico: Interesse dos Leads
            const ctxInteresse = document.getElementById('graficoInteresse');
            if (ctxInteresse) {
                const unhaCount = parseInt(dadosInteresse.unha) || 0;
                const ciliosCount = parseInt(dadosInteresse.cilios) || 0;
                const hasData = unhaCount > 0 || ciliosCount > 0;
                
                new Chart(ctxInteresse.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: hasData ? ['Material de Unha', 'Material de Cílios'] : ['Nenhum dado'],
                        datasets: [{
                            data: hasData ? [unhaCount, ciliosCount] : [1],
                            backgroundColor: hasData ? ['#ff00d4', '#111e88'] : ['#ddd'],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { padding: 15 }
                            }
                        }
                    }
                });
            }

            // Gráfico: Leads por Dia (Linha)
            const ctxDias = document.getElementById('graficoDias');
            if (ctxDias) {
                // Garantir que temos dados dos últimos 7 dias
                const ultimosSete = [];
                for (let i = 6; i >= 0; i--) {
                    const data = new Date();
                    data.setDate(data.getDate() - i);
                    const dataString = data.toISOString().split('T')[0];
                    
                    const encontrado = dadosPorDia.find(d => d.data === dataString);
                    ultimosSete.push({
                        data: dataString,
                        total: encontrado ? parseInt(encontrado.total) : 0
                    });
                }

                new Chart(ctxDias.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: ultimosSete.map(d => {
                            const [ano, mes, dia] = d.data.split('-');
                            return `${dia}/${mes}`;
                        }),
                        datasets: [{
                            label: 'Leads por Dia',
                            data: ultimosSete.map(d => d.total),
                            borderColor: '#ff00d4',
                            backgroundColor: 'rgba(255, 0, 212, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#ff00d4',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { stepSize: 1 }
                            }
                        }
                    }
                });
            }
        }
        
        // Funções para gerenciar leads
        function editLeadInline(lead) {
            const modal = document.getElementById('edit-lead-modal');
            modal.style.display = 'flex';
            
            // Limpar e preencher campos
            document.getElementById('edit_lead_id').value = lead.id;
            document.getElementById('edit_lead_nome_responsavel').value = lead.nome_responsavel || '';
            document.getElementById('edit_lead_whatsapp').value = lead.whatsapp || '';
            document.getElementById('edit_lead_nome_loja').value = lead.nome_loja || '';
            document.getElementById('edit_lead_cidade').value = lead.cidade || '';
            document.getElementById('edit_lead_estado').value = lead.estado || '';
            document.getElementById('edit_lead_ramo_loja').value = lead.ramo_loja || '';
            document.getElementById('edit_lead_faturamento').value = lead.faturamento || '';
            document.getElementById('edit_lead_vendedora_id').value = lead.vendedora_id || '';
            
            // Definir interesse no seletor
            const interesseSelect = document.getElementById('edit_lead_interesse');
            if (lead.interesse) {
                interesseSelect.value = lead.interesse.toString();
                console.log('Interesse selecionado:', lead.interesse);
            } else {
                interesseSelect.value = '';
            }
            
            // Foco no primeiro campo
            setTimeout(() => {
                document.getElementById('edit_lead_nome_responsavel').focus();
            }, 100);
        }
        
        function cancelEditLead() {
            const modal = document.getElementById('edit-lead-modal');
            modal.style.display = 'none';
        }
        
        function deleteLeadInline(id, nome) {
            if (confirm(`Tem certeza que deseja excluir o lead "${nome}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_lead">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Fechar modal clicando fora dele
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('edit-lead-modal');
            if (event.target === modal) {
                cancelEditLead();
            }
        });
        
        // Fechar modal com ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('edit-lead-modal');
                if (modal.style.display === 'flex') {
                    cancelEditLead();
                }
            }
        });
    </script>
    
    <script src="../../js/dashboard.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const savedTheme = localStorage.getItem('darkTheme');
        if (savedTheme === 'true') {
            document.body.classList.add('dark-theme-variables');
            console.log('Tema dark aplicado em revendedores.php');
        }
    });
    </script>
</body>
</html>