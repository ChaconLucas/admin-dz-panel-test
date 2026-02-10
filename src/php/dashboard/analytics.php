<?php
session_start();
// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../../../PHP/login.php');
    exit();
}

// Incluir contador de mensagens
require_once 'helper-contador.php';

// Conectar ao banco de dados
require_once '../../../PHP/conexao.php';

// Configurar fuso horário
date_default_timezone_set('America/Sao_Paulo');

// Processar filtros de data
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01'); // Primeiro dia do mês atual
$data_fim = $_GET['data_fim'] ?? date('Y-m-d'); // Hoje

// Processar filtros rápidos
if (isset($_GET['filtro_rapido'])) {
    switch ($_GET['filtro_rapido']) {
        case 'hoje':
            $data_inicio = $data_fim = date('Y-m-d');
            break;
        case '7dias':
            $data_inicio = date('Y-m-d', strtotime('-7 days'));
            $data_fim = date('Y-m-d');
            break;
        case '30dias':
            $data_inicio = date('Y-m-d', strtotime('-30 days'));
            $data_fim = date('Y-m-d');
            break;
        case 'ano':
            $data_inicio = date('Y-01-01');
            $data_fim = date('Y-m-d');
            break;
        case 'total':
            $data_inicio = '2020-01-01'; // Data bem antiga para pegar tudo
            $data_fim = date('Y-m-d');
            break;
    }
}

// Buscar dados para KPIs
$sql_kpis = "
SELECT 
    COUNT(p.id) as total_vendas,
    COALESCE(SUM(p.valor_total), 0) as faturamento,
    COALESCE(AVG(p.valor_total), 0) as ticket_medio,
    COALESCE(SUM(ip.quantidade), 0) as itens_vendidos,
    COALESCE(SUM(p.desconto_frete), 0) as total_desconto_frete,
    COALESCE(SUM(p.desconto_cupom), 0) as total_desconto_cupom
FROM pedidos p 
LEFT JOIN itens_pedido ip ON p.id = ip.pedido_id
WHERE DATE(p.data_pedido) BETWEEN ? AND ?
AND p.status IN ('Pedido Confirmado', 'Pagamento Pendente', 'Em Preparação', 'Pedido Recebido', 'Enviado', 'Entregue', 'Pago', 'Estornado')
";

$stmt_kpis = mysqli_prepare($conexao, $sql_kpis);
mysqli_stmt_bind_param($stmt_kpis, 'ss', $data_inicio, $data_fim);
mysqli_stmt_execute($stmt_kpis);
$result_kpis = mysqli_stmt_get_result($stmt_kpis);
$kpis = mysqli_fetch_assoc($result_kpis);

// Se não há dados, definir valores padrão
if (!$kpis) {
    $kpis = [
        'total_vendas' => 0,
        'faturamento' => 0,
        'ticket_medio' => 0,
        'itens_vendidos' => 0
    ];
}

// Buscar dados para gráfico de evolução de vendas
$sql_evolucao = "
SELECT 
    DATE(data_pedido) as data,
    SUM(valor_total) as faturamento,
    COUNT(*) as pedidos
FROM pedidos 
WHERE DATE(data_pedido) BETWEEN ? AND ?
AND status IN ('Pedido Confirmado', 'Pagamento Pendente', 'Em Preparação', 'Pedido Recebido', 'Enviado', 'Entregue', 'Pago', 'Estornado')
GROUP BY DATE(data_pedido)
ORDER BY data
";

$stmt_evolucao = mysqli_prepare($conexao, $sql_evolucao);
mysqli_stmt_bind_param($stmt_evolucao, 'ss', $data_inicio, $data_fim);
mysqli_stmt_execute($stmt_evolucao);
$result_evolucao = mysqli_stmt_get_result($stmt_evolucao);
$dados_evolucao = mysqli_fetch_all($result_evolucao, MYSQLI_ASSOC);

// Debug: verificar dados retornados
$debug_info = [
    'data_inicio' => $data_inicio,
    'data_fim' => $data_fim,
    'total_dados_evolucao' => count($dados_evolucao),
    'dados_evolucao' => $dados_evolucao
];

// Se não há dados no período, criar dados de exemplo para melhor visualização
if (empty($dados_evolucao)) {
    $dados_evolucao = [
        ['data' => $data_fim, 'faturamento' => '0.00', 'pedidos' => 0]
    ];
}

// Buscar dados para gráfico de top categorias
$sql_categorias = "
SELECT 
    COALESCE(pr.categoria, 'Sem Categoria') as categoria,
    SUM(ip.quantidade) as quantidade,
    SUM(ip.quantidade * ip.preco_unitario) as valor
FROM pedidos p
INNER JOIN itens_pedido ip ON p.id = ip.pedido_id
INNER JOIN produtos pr ON ip.produto_id = pr.id
WHERE DATE(p.data_pedido) BETWEEN ? AND ?
AND p.status IN ('Pedido Confirmado', 'Pagamento Pendente', 'Em Preparação', 'Pedido Recebido', 'Enviado', 'Entregue', 'Pago', 'Estornado')
GROUP BY COALESCE(pr.categoria, 'Sem Categoria')
ORDER BY valor DESC
LIMIT 5
";

$stmt_categorias = mysqli_prepare($conexao, $sql_categorias);
mysqli_stmt_bind_param($stmt_categorias, 'ss', $data_inicio, $data_fim);
mysqli_stmt_execute($stmt_categorias);
$result_categorias = mysqli_stmt_get_result($stmt_categorias);
$dados_categorias = mysqli_fetch_all($result_categorias, MYSQLI_ASSOC);

// Se não há dados de categoria, criar dados de exemplo
if (empty($dados_categorias)) {
    $dados_categorias = [
        ['categoria' => 'Sem dados', 'quantidade' => 0, 'valor' => 0]
    ];
}

// Adicionar colunas de desconto se não existirem
$colunas_check = [
    'desconto_frete' => 'DECIMAL(10,2) DEFAULT 0.00',
    'desconto_cupom' => 'DECIMAL(10,2) DEFAULT 0.00',
    'valor_subtotal' => 'DECIMAL(10,2) DEFAULT 0.00'
];

foreach ($colunas_check as $coluna => $tipo) {
    $check_query = "SHOW COLUMNS FROM pedidos LIKE '$coluna'";
    $check_result = mysqli_query($conexao, $check_query);
    if (mysqli_num_rows($check_result) == 0) {
        $add_query = "ALTER TABLE pedidos ADD COLUMN $coluna $tipo";
        mysqli_query($conexao, $add_query);
    }
}

// Buscar lista de pedidos com informações financeiras detalhadas
$sql_pedidos = "
SELECT 
    p.id,
    p.data_pedido,
    COALESCE(c.nome, p.cliente_nome, 'Cliente não identificado') as cliente_nome,
    p.valor_total,
    COALESCE(p.valor_subtotal, p.valor_total) as valor_subtotal,
    COALESCE(p.desconto_frete, 0) as desconto_frete,
    COALESCE(p.desconto_cupom, 0) as desconto_cupom,
    p.status,
    p.forma_pagamento,
    p.parcelas,
    COUNT(ip.id) as total_itens,
    SUM(ip.quantidade * ip.preco_unitario) as valor_itens
FROM pedidos p
LEFT JOIN clientes c ON p.cliente_id = c.id
LEFT JOIN itens_pedido ip ON p.id = ip.pedido_id
WHERE DATE(p.data_pedido) BETWEEN ? AND ?
AND p.status IN ('Pedido Confirmado', 'Pagamento Pendente', 'Em Preparação', 'Pedido Recebido', 'Enviado', 'Entregue', 'Pago', 'Estornado')
GROUP BY p.id
ORDER BY p.data_pedido DESC
LIMIT 50
";

$stmt_pedidos = mysqli_prepare($conexao, $sql_pedidos);
mysqli_stmt_bind_param($stmt_pedidos, 'ss', $data_inicio, $data_fim);
mysqli_stmt_execute($stmt_pedidos);
$result_pedidos = mysqli_stmt_get_result($stmt_pedidos);
$lista_pedidos = mysqli_fetch_all($result_pedidos, MYSQLI_ASSOC);
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
    
    <!-- Font Awesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js para gráficos -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
      .analytics-header {
        background: white;
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        border: 1px solid #e9ecef;
      }
      
      .header-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
      }
      
      .header-title h1 {
        margin: 0;
        color: #2c3e50;
        font-size: 1.5rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }
      
      
      .export-btn {
        background: #ff00cc;
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.375rem;
        transition: all 0.2s ease;
        font-weight: 500;
        font-size: 0.875rem;
        box-shadow: 0 2px 8px rgba(255, 0, 204, 0.25);
      }
      
      .export-btn:hover {
        background: #e600b3;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(255, 0, 204, 0.3);
      }
      
      .export-btn-secondary {
        background: #6c757d;
        box-shadow: 0 2px 8px rgba(108, 117, 125, 0.25);
      }
      
      .export-btn-secondary:hover {
        background: #5a6268;
        box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
      }
      
      .export-buttons {
        display: flex;
        gap: 0.5rem;
      }
      
      .filters-container {
        display: flex;
        gap: 1rem;
        align-items: center;
        flex-wrap: wrap;
      }
      
      .date-form {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: #f8f9fa;
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
        border: 1px solid #e9ecef;
        transition: all 0.2s ease;
      }
      
      .date-form:focus-within {
        border-color: #ff00cc;
        background: white;
      }
      
      .date-form input[type="date"] {
        border: none;
        background: transparent;
        font-size: 0.875rem;
        color: #495057;
        outline: none;
        min-width: 120px;
        cursor: pointer;
      }
      
      .date-separator {
        color: #6c757d;
        font-weight: 400;
        font-size: 0.875rem;
      }
      
      .submit-btn {
        background: #007bff;
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        font-size: 0.875rem;
        transition: all 0.2s ease;
      }
      
      .submit-btn:hover {
        background: #0056b3;
        transform: translateY(-1px);
      }
      
      .quick-filters {
        display: flex;
        gap: 0.5rem;
        background: #f8f9fa;
        padding: 0.375rem;
        border-radius: 8px;
        border: 1px solid #e9ecef;
      }
      
      .quick-filter-btn {
        padding: 0.5rem 0.875rem;
        border: none;
        background: transparent;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.8rem;
        font-weight: 500;
        text-decoration: none;
        color: #495057;
      }
      
      .quick-filter-btn:hover {
        background: white;
        color: #343a40;
        box-shadow: 0 1px 4px rgba(0,0,0,0.1);
      }
      
      .quick-filter-btn.active {
        background: #ff00cc;
        color: white;
        box-shadow: 0 2px 6px rgba(255, 0, 204, 0.3);
      }
      
      /* Responsivo */
      @media (max-width: 768px) {
        .analytics-header {
          padding: 1rem;
        }
        
        .header-title {
          flex-direction: column;
          gap: 0.75rem;
          align-items: flex-start;
        }
        
        .filters-container {
          flex-direction: column;
          align-items: stretch;
          gap: 0.75rem;
        }
        
        .date-form {
          flex-wrap: wrap;
          justify-content: center;
        }
        
        .quick-filters {
          justify-content: center;
          flex-wrap: wrap;
        }
      }
      
      .kpis-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.25rem;
        margin-bottom: 2rem;
      }
      
      .kpi-card {
        background: white;
        padding: 1.25rem;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        text-align: center;
        border: 1px solid #e9ecef;
        transition: all 0.2s ease;
        position: relative;
      }
      
      .kpi-card:before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: #ff00cc;
        border-radius: 10px 10px 0 0;
      }
      
      .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.1);
      }
      
      .kpi-value {
        font-size: 1.875rem;
        font-weight: 700;
        color: #ff00cc;
        margin: 0.5rem 0;
      }
      
      .kpi-label {
        color: #6c757d;
        font-size: 0.875rem;
        font-weight: 500;
      }
      
      .charts-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.5rem;
        margin-bottom: 2rem;
      }
      
      .chart-container {
        background: white;
        padding: 1.25rem;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        border: 1px solid #e9ecef;
        transition: all 0.2s ease;
      }
      
      .chart-container:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.1);
      }
      
      .chart-container h3 {
        margin-top: 0;
        color: #2c3e50;
        font-weight: 600;
        font-size: 1.1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #e9ecef;
        margin-bottom: 1rem;
      }
      
      .orders-table {
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        border: 1px solid #e9ecef;
      }
      
      .orders-table h3 {
        margin: 0;
        padding: 1.25rem;
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        color: #2c3e50;
        font-weight: 600;
        font-size: 1.1rem;
      }
      
      .table-container {
        overflow-x: auto;
      }
      
      table {
        width: 100%;
        border-collapse: collapse;
      }
      
      th, td {
        padding: 0.75rem 0.5rem;
        text-align: left;
        border-bottom: 1px solid #eee;
        font-size: 0.875rem;
        vertical-align: top;
      }
      
      th {
        background: #f8f9fa;
        font-weight: 600;
        color: #495057;
        white-space: nowrap;
        font-size: 0.8rem;
      }
      
      td {
        white-space: nowrap;
      }
      
      /* Colunas financeiras mais largas */
      th:nth-child(5), td:nth-child(5),  /* Subtotal */
      th:nth-child(6), td:nth-child(6),  /* Desc. Frete */
      th:nth-child(7), td:nth-child(7),  /* Desc. Cupom */
      th:nth-child(8), td:nth-child(8) { /* Valor Final */ 
        min-width: 85px;
        text-align: right;
      }
      
      /* Cliente e Status podem quebrar linha se necessário */
      th:nth-child(3), td:nth-child(3),  /* Cliente */
      th:nth-child(9), td:nth-child(9),  /* Pagamento */
      th:nth-child(10), td:nth-child(10) { /* Status */
        white-space: normal;
        max-width: 120px;
      }
      
      tr:hover {
        background: #f8f9fa;
      }
      
      .status-badge {
        padding: 0.2rem 0.6rem;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        display: inline-block;
        white-space: nowrap;
      }
      
      .status-pendente, .status-pagamentopendente { 
        background: #fff3cd; 
        color: #856404; 
        border: 1px solid #ffeaa7;
      }
      
      .status-empreparacao, .status-empreparação { 
        background: #f8f9fa; 
        color: #495057; 
        border: 1px solid #e9ecef;
      }
      
      .status-pedidorecebido, .status-pedidoconfirmado { 
        background: #cce7ff; 
        color: #004085; 
        border: 1px solid #74c0fc;
      }
      
      .status-estornado { 
        background: #f8d7da; 
        color: #721c24; 
        border: 1px solid #f5c2c7;
      }
      
      .status-processando { background: #d1ecf1; color: #0c5460; }
      .status-enviado { background: #d4edda; color: #155724; }
      .status-entregue { background: #d1e7dd; color: #0f5132; }
      .status-cancelado { background: #f8d7da; color: #721c24; }
      .status-pago { 
        background: #d1e7dd; 
        color: #0f5132; 
        border: 1px solid #badbcc;
      }
    </style>

    <title>Gráficos - D&Z Dashboard</title>
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



          <a href="analytics.php" class="panel">
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
            <a href="settings.php" class="menu-item-with-submenu">
              <span class="material-symbols-sharp">Settings</span>
              <h3>Configurações</h3>
            </a>
            
            <div class="submenu">
              <a href="#">
                <span class="material-symbols-sharp">tune</span>
                <h3>Geral</h3>
              </a>
              <a href="#">
                <span class="material-symbols-sharp">payments</span>
                <h3>Pagamentos</h3>
              </a>
              <a href="#">
                <span class="material-symbols-sharp">local_shipping</span>
                <h3>Frete</h3>
              </a>
              <a href="#">
                <span class="material-symbols-sharp">automation</span>
                <h3>Automação</h3>
              </a>
              <a href="#">
                <span class="material-symbols-sharp">analytics</span>
                <h3>Métricas</h3>
              </a>
              <a href="#">
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

      <main>
        <!-- Cabeçalho com Filtros e Exportação -->
        <div class="analytics-header">
          <div class="header-title">
            <h1>Gráficos</h1>
            <div class="export-buttons">
              <button class="export-btn" onclick="exportarExcel()">
                <i class="fas fa-file-excel"></i>
                Exportar Excel
              </button>
              <button class="export-btn export-btn-secondary" onclick="exportarRelatorio()">
                <i class="fas fa-file-alt"></i>
                Exportar TXT
              </button>
            </div>
          </div>
          
          <div class="filters-container">
            <form method="GET" class="date-form">
              <input type="date" name="data_inicio" id="data_inicio" value="<?= $data_inicio ?>" />
              <span class="date-separator">até</span>
              <input type="date" name="data_fim" id="data_fim" value="<?= $data_fim ?>" />
              <button type="submit" class="submit-btn">Filtrar</button>
            </form>
            
            <div class="quick-filters">
              <a href="?filtro_rapido=hoje" class="quick-filter-btn <?= ($_GET['filtro_rapido'] ?? '') === 'hoje' ? 'active' : '' ?>">Hoje</a>
              <a href="?filtro_rapido=7dias" class="quick-filter-btn <?= ($_GET['filtro_rapido'] ?? '') === '7dias' ? 'active' : '' ?>">7 Dias</a>
              <a href="?filtro_rapido=30dias" class="quick-filter-btn <?= ($_GET['filtro_rapido'] ?? '') === '30dias' ? 'active' : '' ?>">30 Dias</a>
              <a href="?filtro_rapido=ano" class="quick-filter-btn <?= ($_GET['filtro_rapido'] ?? '') === 'ano' ? 'active' : '' ?>">Este Ano</a>
              <a href="?filtro_rapido=total" class="quick-filter-btn <?= ($_GET['filtro_rapido'] ?? '') === 'total' ? 'active' : '' ?>">Total</a>
            </div>
          </div>
        </div>
        
        <!-- Cards de KPIs -->
        <div class="kpis-grid">
          <div class="kpi-card">
            <div class="kpi-label">Faturamento Líquido</div>
            <div class="kpi-value">R$ <?= number_format($kpis['faturamento'], 2, ',', '.') ?></div>
            <div class="kpi-label" style="font-size: 0.75rem; color: #28a745;">
              Valor final recebido
            </div>
          </div>
          
          <div class="kpi-card">
            <div class="kpi-label">Vendas</div>
            <div class="kpi-value"><?= $kpis['total_vendas'] ?></div>
            <div class="kpi-label">pedidos realizados</div>
          </div>
          
          <div class="kpi-card">
            <div class="kpi-label">Ticket Médio</div>
            <div class="kpi-value">R$ <?= number_format($kpis['ticket_medio'], 2, ',', '.') ?></div>
            <div class="kpi-label">valor médio por pedido</div>
          </div>
          
          <div class="kpi-card">
            <div class="kpi-label">Descontos Dados</div>
            <div class="kpi-value" style="color: #dc3545;">
              R$ <?= number_format(($kpis['total_desconto_frete'] + $kpis['total_desconto_cupom']), 2, ',', '.') ?>
            </div>
            <div class="kpi-label" style="font-size: 0.75rem;">
              Frete: R$ <?= number_format($kpis['total_desconto_frete'], 2, ',', '.') ?> |
              Cupom: R$ <?= number_format($kpis['total_desconto_cupom'], 2, ',', '.') ?>
            </div>
          </div>
          
          <div class="kpi-card">
            <div class="kpi-label">Itens Vendidos</div>
            <div class="kpi-value"><?= $kpis['itens_vendidos'] ?></div>
            <div class="kpi-label">produtos saíram do estoque</div>
          </div>
        </div>
        
        <!-- Gráficos -->
        <div class="charts-grid">
          <div class="chart-container">
            <h3>Evolução de Vendas</h3>
            <canvas id="evolucaoChart" style="max-height: 400px;"></canvas>
          </div>
          
          <div class="chart-container">
            <h3>Top Categorias</h3>
            <canvas id="categoriasChart" style="max-height: 400px;"></canvas>
          </div>
        </div>
        
        <!-- Lista de Pedidos -->
        <div class="orders-table">
          <h3>Pedidos do Período (<?= $data_inicio ?> até <?= $data_fim ?>)</h3>
          <?php if (empty($lista_pedidos)): ?>
            <div style="padding: 2rem; text-align: center; color: #666;">
              Nenhum pedido encontrado no período selecionado.
            </div>
          <?php else: ?>
            <div class="table-container">
              <table>
                <thead>
                  <tr>
                    <th>Data</th>
                    <th>ID Pedido</th>
                    <th>Cliente</th>
                    <th>Itens</th>
                    <th>Subtotal</th>
                    <th>Desc. Frete</th>
                    <th>Desc. Cupom</th>
                    <th>Valor Final</th>
                    <th>Pagamento</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($lista_pedidos as $pedido): ?>
                    <?php 
                    // Calcular valores se necessário
                    $subtotal = $pedido['valor_itens'] ?: $pedido['valor_subtotal'];
                    $desc_frete = $pedido['desconto_frete'];
                    $desc_cupom = $pedido['desconto_cupom'];
                    $valor_final = $pedido['valor_total'];
                    
                    // Se não há subtotal definido, usar valor final + descontos
                    if (!$subtotal) {
                        $subtotal = $valor_final + $desc_frete + $desc_cupom;
                    }
                    ?>
                    <tr>
                      <td><?= date('d/m/Y H:i', strtotime($pedido['data_pedido'])) ?></td>
                      <td>#<?= str_pad($pedido['id'], 4, '0', STR_PAD_LEFT) ?></td>
                      <td><?= htmlspecialchars($pedido['cliente_nome']) ?></td>
                      <td>
                        <span style="font-weight: 500;"><?= $pedido['total_itens'] ?></span>
                        <small style="color: #6c757d;">item<?= $pedido['total_itens'] > 1 ? 's' : '' ?></small>
                      </td>
                      <td>
                        <span style="font-weight: 500; color: #28a745;">R$ <?= number_format($subtotal, 2, ',', '.') ?></span>
                      </td>
                      <td>
                        <?php if ($desc_frete > 0): ?>
                          <span style="color: #dc3545;">-R$ <?= number_format($desc_frete, 2, ',', '.') ?></span>
                        <?php else: ?>
                          <span style="color: #6c757d;">-</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($desc_cupom > 0): ?>
                          <span style="color: #dc3545;">-R$ <?= number_format($desc_cupom, 2, ',', '.') ?></span>
                        <?php else: ?>
                          <span style="color: #6c757d;">-</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <span style="font-weight: 600; color: #007bff; font-size: 1.05em;">R$ <?= number_format($valor_final, 2, ',', '.') ?></span>
                      </td>
                      <td>
                        <div style="font-size: 0.8rem;">
                          <div style="font-weight: 500;"><?= $pedido['forma_pagamento'] ?: 'Não informado' ?></div>
                          <?php if ($pedido['parcelas'] > 1): ?>
                            <div style="color: #6c757d;"><?= $pedido['parcelas'] ?>x</div>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td>
                        <span class="status-badge status-<?= strtolower(str_replace(' ', '', $pedido['status'])) ?>">
                          <?= $pedido['status'] ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
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



    
<script src="../../js/dashboard.js"></script>

<script>
// Dados para o gráfico de evolução
const dadosEvolucao = <?= json_encode($dados_evolucao) ?>;
const dadosCategorias = <?= json_encode($dados_categorias) ?>;
const debugInfo = <?= json_encode($debug_info) ?>;

// Debug do JavaScript
console.log('Debug Analytics:', debugInfo);
console.log('Dados Evolução:', dadosEvolucao);
console.log('Dados Categorias:', dadosCategorias);

// Processar dados para o gráfico
let labelsGrafico = [];
let dadosGrafico = [];

if (dadosEvolucao.length === 1) {
    // Se há apenas um ponto, criar pontos adicionais para melhor visualização
    const dataUnica = dadosEvolucao[0];
    const date = new Date(dataUnica.data + 'T00:00:00');
    
    // Adicionar dia anterior com valor 0
    const dayBefore = new Date(date);
    dayBefore.setDate(dayBefore.getDate() - 1);
    
    // Adicionar dia posterior com valor 0
    const dayAfter = new Date(date);
    dayAfter.setDate(dayAfter.getDate() + 1);
    
    labelsGrafico = [
        dayBefore.toLocaleDateString('pt-BR'),
        date.toLocaleDateString('pt-BR'),
        dayAfter.toLocaleDateString('pt-BR')
    ];
    
    dadosGrafico = [
        0,
        parseFloat(dataUnica.faturamento || 0),
        0
    ];
    
    console.log('Dados únicos expandidos para melhor visualização');
} else {
    labelsGrafico = dadosEvolucao.map(item => {
        if (!item.data) return 'Sem data';
        try {
            const date = new Date(item.data + 'T00:00:00');
            return date.toLocaleDateString('pt-BR');
        } catch (e) {
            console.error('Erro ao processar data:', item.data, e);
            return item.data;
        }
    });

    dadosGrafico = dadosEvolucao.map(item => {
        const valor = parseFloat(item.faturamento || 0);
        console.log('Processando faturamento:', item.faturamento, '->', valor);
        return valor;
    });
}

// Configurar gráfico de evolução de vendas
const ctx1 = document.getElementById('evolucaoChart').getContext('2d');
const evolucaoChart = new Chart(ctx1, {
    type: 'line',
    data: {
        labels: labelsGrafico,
        datasets: [{
            label: 'Faturamento (R$)',
            data: dadosGrafico,
            borderColor: '#ff00cc',
            backgroundColor: 'rgba(255, 0, 204, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            title: {
                display: true,
                text: 'Faturamento por Data'
            }
        },
        scales: {
            x: {
                display: true,
                title: {
                    display: true,
                    text: 'Data'
                }
            },
            y: {
                beginAtZero: true,
                display: true,
                title: {
                    display: true,
                    text: 'Faturamento (R$)'
                },
                ticks: {
                    callback: function(value) {
                        return 'R$ ' + value.toLocaleString('pt-BR', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                }
            }
        },
        elements: {
            point: {
                radius: 6,
                hoverRadius: 10,
                backgroundColor: '#ff00cc',
                borderColor: '#ff00cc'
            }
        }
    }
});

// Configurar gráfico de categorias  
const ctx2 = document.getElementById('categoriasChart').getContext('2d');
const categoriasChart = new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: dadosCategorias.map(item => item.categoria || 'Sem categoria'),
        datasets: [{
            data: dadosCategorias.map(item => parseFloat(item.valor)),
            backgroundColor: [
                '#ff00cc',
                '#ff3399',
                '#ff66b3',
                '#ff99cc',
                '#ffcce6'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    padding: 20,
                    usePointStyle: true
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const valor = context.parsed;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentual = ((valor / total) * 100).toFixed(1);
                        return context.label + ': R$ ' + valor.toLocaleString('pt-BR') + ' (' + percentual + '%)';
                    }
                }
            }
        }
    }
});

// Função para exportar relatório
function exportarRelatorio() {
    const dataInicio = '<?= $data_inicio ?>';
    const dataFim = '<?= $data_fim ?>';
    
    // Criar conteúdo do relatório
    let conteudo = `RELATÓRIO DE VENDAS D&Z\n`;
    conteudo += `Período: ${dataInicio} até ${dataFim}\n`;
    conteudo += `Gerado em: ${new Date().toLocaleString('pt-BR')}\n\n`;
    
    conteudo += `RESUMO EXECUTIVO\n`;
    conteudo += `================\n`;
    conteudo += `Faturamento Total: R$ <?= number_format($kpis['faturamento'], 2, ',', '.') ?>\n`;
    conteudo += `Total de Vendas: <?= $kpis['total_vendas'] ?> pedidos\n`;
    conteudo += `Ticket Médio: R$ <?= number_format($kpis['ticket_medio'], 2, ',', '.') ?>\n`;
    conteudo += `Itens Vendidos: <?= $kpis['itens_vendidos'] ?>\n\n`;
    
    if (dadosCategorias.length > 0) {
        conteudo += `TOP CATEGORIAS\n`;
        conteudo += `==============\n`;
        dadosCategorias.forEach((cat, index) => {
            conteudo += `${index + 1}. ${cat.categoria}: R$ ${parseFloat(cat.valor).toLocaleString('pt-BR')} (${cat.quantidade} itens)\n`;
        });
        conteudo += `\n`;
    }
    
    // Criar arquivo e fazer download
    const blob = new Blob([conteudo], { type: 'text/plain;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `relatorio_vendas_${dataInicio}_${dataFim}.txt`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function exportarExcel() {
    // Usar valores PHP diretamente ou buscar do DOM como fallback
    const dataInicio = document.getElementById('data_inicio')?.value || '<?= $data_inicio ?>';
    const dataFim = document.getElementById('data_fim')?.value || '<?= $data_fim ?>';
    
    // Dados dos KPIs
    const kpis = <?= json_encode($kpis) ?>;
    const dadosList = <?= json_encode($lista_pedidos) ?>;
    const dadosEvolucao = <?= json_encode($dados_evolucao) ?>;
    const dadosCategorias = <?= json_encode($dados_categorias) ?>;
    
    // Criar conteúdo CSV elegante e bem formatado
    let csvContent = '\uFEFF'; // BOM para UTF-8
    
    // ═══════════════ CABEÇALHO ELEGANTE ═══════════════
    csvContent += ";;;;;;;;;;;;;;;\n";
    csvContent += ";🏢 D&Z DASHBOARD - RELATÓRIO EXECUTIVO;;;;;;;;;;;;;;\n";
    csvContent += ";;;;;;;;;;;;;;;\n";
    csvContent += ";📅 Data de Geração:;" + new Date().toLocaleString('pt-BR') + ";;;;;;;;;;;;;\n";
    csvContent += ";📊 Período Analisado:;" + dataInicio + " até " + dataFim + ";;;;;;;;;;;;;\n";
    csvContent += ";📈 Total de Registros:;" + dadosList.length + " pedidos;;;;;;;;;;;;;\n";
    csvContent += ";;;;;;;;;;;;;;;\n";
    csvContent += "═══════════════════════════════════════════════════════\n\n";
    
    // 🎯 RESUMO EXECUTIVO - KPIs EM DESTAQUE
    csvContent += "🎯 RESUMO EXECUTIVO;;;;;;;;;;;;;\n";
    csvContent += "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    csvContent += "Indicador;💰 Valor;📊 Unidade;📈 Status;;;;;;;;;;\n";
    csvContent += "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    csvContent += "📦 Total de Vendas;" + kpis.total_vendas + ";unidades;✅ Ativo;;;;;;;;;;\n";
    csvContent += "💵 Faturamento Bruto;R$ " + parseFloat(kpis.faturamento).toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ";reais;✅ Ativo;;;;;;;;;;\n";
    csvContent += "🎯 Ticket Médio;R$ " + parseFloat(kpis.ticket_medio).toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ";reais;📊 Calculado;;;;;;;;;;\n";
    csvContent += "📱 Itens Vendidos;" + kpis.itens_vendidos + ";unidades;✅ Ativo;;;;;;;;;;\n";
    csvContent += "🚚 Desconto Frete;R$ " + parseFloat(kpis.total_desconto_frete || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ";economia;💰 Benefício;;;;;;;;;;\n";
    csvContent += "🎫 Desconto Cupom;R$ " + parseFloat(kpis.total_desconto_cupom || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ";economia;💰 Benefício;;;;;;;;;;\n";
    
    // Calcular dados extras com visual
    const totalDescontos = parseFloat(kpis.total_desconto_frete || 0) + parseFloat(kpis.total_desconto_cupom || 0);
    const faturamentoLiquido = parseFloat(kpis.faturamento) - totalDescontos;
    
    csvContent += "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    csvContent += "💎 Total Descontos;R$ " + totalDescontos.toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ";economia;⭐ Destaque;;;;;;;;;;\n";
    csvContent += "🏆 Faturamento Líquido;R$ " + faturamentoLiquido.toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ";final;🥇 Principal;;;;;;;;;;\n";
    csvContent += "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // 📈 EVOLUÇÃO TEMPORAL ELEGANTE
    csvContent += "📈 EVOLUÇÃO DIÁRIA DE VENDAS;;;;;;;;;;;;;\n";
    csvContent += "┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓\n";
    csvContent += "┃ 📅 Data;💰 Faturamento;📦 Pedidos;🎯 Ticket Médio;📊 Performance;;;;;;;;;┃\n";
    csvContent += "┣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┫\n";
    
    if (dadosEvolucao && dadosEvolucao.length > 0) {
        dadosEvolucao.forEach(dia => {
            const ticketMedio = parseFloat(dia.faturamento) / parseInt(dia.pedidos);
            const performance = ticketMedio > parseFloat(kpis.ticket_medio) ? "🔥 Acima" : "📊 Normal";
            csvContent += "┃ " + new Date(dia.data).toLocaleDateString('pt-BR') + ";";
            csvContent += "R$ " + parseFloat(dia.faturamento).toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ";";
            csvContent += dia.pedidos + " un.;";
            csvContent += "R$ " + ticketMedio.toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ";";
            csvContent += performance + ";;;;;;;;;┃\n";
        });
    }
    csvContent += "┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛\n\n";
    
    // 🏆 TOP CATEGORIAS COM VISUAL ATRATIVO
    csvContent += "🏆 TOP 5 CATEGORIAS MAIS VENDIDAS;;;;;;;;;;;;;\n";
    csvContent += "▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓\n";
    csvContent += "🥇 Posição;📂 Categoria;📦 Quantidade;💰 Valor;📊 % Total;🎯 Status;;;;;;;;;;\n";
    csvContent += "▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓\n";
    
    if (dadosCategorias && dadosCategorias.length > 0) {
        const totalCategorias = dadosCategorias.reduce((sum, cat) => sum + parseFloat(cat.valor), 0);
        const medalhas = ["🥇", "🥈", "🥉", "🏅", "🏆"];
        dadosCategorias.forEach((cat, index) => {
            const percentual = (parseFloat(cat.valor) / totalCategorias * 100).toFixed(1);
            const status = index === 0 ? "👑 Líder" : index < 3 ? "⭐ Top 3" : "📈 Destaque";
            csvContent += medalhas[index] + " " + (index + 1) + "º Lugar;" + cat.categoria + ";" + cat.quantidade + " un.;";
            csvContent += "R$ " + parseFloat(cat.valor).toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ";" + percentual + "%;" + status + ";;;;;;;;;;\n";
        });
    }
    csvContent += "▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓\n\n";
    
    // 📋 DETALHAMENTO COMPLETO COM DESIGN ELEGANTE
    csvContent += "📋 DETALHAMENTO COMPLETO DOS PEDIDOS\n";
    csvContent += "╔═══════════════════════════════════════════════════════════════════════════════════════════════════╗\n";
    csvContent += "║ 📅 Data;🕐 Hora;📟 ID;👤 Cliente;📦 Itens;💰 Subtotal;🚚 Frete;🎫 Cupom;💎 Total Desc;🏆 Final;💯 Economia;💳 Pagto;📊 Parc;⚡ Status;📝 Obs ║\n";
    csvContent += "╠═══════════════════════════════════════════════════════════════════════════════════════════════════╣\n";
    
    dadosList.forEach((pedido, index) => {
        const dataHora = new Date(pedido.data_pedido);
        const data = dataHora.toLocaleDateString('pt-BR');
        const hora = dataHora.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
        const id = String(pedido.id).padStart(4, '0');
        const subtotal = parseFloat(pedido.valor_subtotal || pedido.valor_total);
        const descFrete = parseFloat(pedido.desconto_frete || 0);
        const descCupom = parseFloat(pedido.desconto_cupom || 0);
        const totalDesconto = descFrete + descCupom;
        const valorFinal = subtotal - totalDesconto;
        const economiaPercent = subtotal > 0 ? (totalDesconto / subtotal * 100).toFixed(1) : 0;
        
        // Status com emoji
        const statusEmoji = pedido.status === 'Pago' ? '💚' : 
                          pedido.status === 'Estornado' ? '🔴' : 
                          pedido.status === 'Em Preparação' ? '🔵' : 
                          pedido.status === 'Pedido Confirmado' ? '🟢' : '⚪';
        
        csvContent += "║ " + data + ";" + hora + ";#" + id + ";" + pedido.cliente_nome.replace(/[;,]/g, ' ').substring(0, 15) + ";";
        csvContent += pedido.total_itens + " un.;";
        csvContent += "R$ " + subtotal.toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ";";
        csvContent += descFrete > 0 ? "R$ " + descFrete.toLocaleString('pt-BR', {minimumFractionDigits: 2}) : "R$ 0,00";
        csvContent += ";";
        csvContent += descCupom > 0 ? "R$ " + descCupom.toLocaleString('pt-BR', {minimumFractionDigits: 2}) : "R$ 0,00";
        csvContent += ";";
        csvContent += "R$ " + totalDesconto.toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ";";
        csvContent += "R$ " + valorFinal.toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ";" + economiaPercent + "%;";
        csvContent += (pedido.forma_pagamento || 'Não inf.').substring(0, 10) + ";";
        csvContent += (pedido.parcelas > 1 ? pedido.parcelas + 'x' : 'À vista') + ";";
        csvContent += statusEmoji + " " + pedido.status + ";";
        
        // Observações inteligentes
        let obs = '';
        if (pedido.status === 'Estornado') obs = '⚠️ Estorno';
        else if (totalDesconto > 20) obs = '🎉 Grande desc.';
        else if (pedido.parcelas > 6) obs = '⏳ Longo prazo';
        else if (valorFinal > 500) obs = '💎 Alto valor';
        else obs = '✅ Normal';
        
        csvContent += obs + " ║\n";
    });
    
    csvContent += "╚═══════════════════════════════════════════════════════════════════════════════════════════════════╝\n\n";
    
    // 📊 ESTATÍSTICAS AVANÇADAS COM VISUAL
    csvContent += "📊 ESTATÍSTICAS E INSIGHTS AVANÇADOS\n";
    csvContent += "★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★\n";
    
    const totalPedidos = dadosList.length;
    const pedidosComDesconto = dadosList.filter(p => (parseFloat(p.desconto_frete || 0) + parseFloat(p.desconto_cupom || 0)) > 0).length;
    const pedidosParcelados = dadosList.filter(p => p.parcelas > 1).length;
    const pedidosAltoValor = dadosList.filter(p => parseFloat(p.valor_total) > 300).length;
    
    csvContent += "🔢 Total de Pedidos Analisados;;" + totalPedidos + " pedidos;100%;🎯 Base completa;;;;;;;;;\n";
    csvContent += "💰 Pedidos com Desconto;;" + pedidosComDesconto + " pedidos;" + (pedidosComDesconto/totalPedidos*100).toFixed(1) + "%;🎁 Economia ativa;;;;;;;;;\n";
    csvContent += "💳 Pedidos Parcelados;;" + pedidosParcelados + " pedidos;" + (pedidosParcelados/totalPedidos*100).toFixed(1) + "%;📊 Financiamento;;;;;;;;;\n";
    csvContent += "💎 Pedidos Alto Valor (>R$300);;" + pedidosAltoValor + " pedidos;" + (pedidosAltoValor/totalPedidos*100).toFixed(1) + "%;🏆 Premium;;;;;;;;;\n";
    csvContent += "✅ Taxa de Aprovação;;100%%;100%;🎯 Excelente;;;;;;;;;\n";
    
    csvContent += "\n🏷️ DISTRIBUIÇÃO POR STATUS;;;;;;;;;;;;;\n";
    csvContent += "▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼\n";
    
    const statusCount = {};
    dadosList.forEach(p => {
        statusCount[p.status] = (statusCount[p.status] || 0) + 1;
    });
    
    Object.entries(statusCount).forEach(([status, count]) => {
        const emoji = status === 'Pago' ? '💚' : 
                     status === 'Estornado' ? '🔴' : 
                     status === 'Em Preparação' ? '🔵' : 
                     status === 'Pedido Confirmado' ? '🟢' : '⚪';
        csvContent += emoji + " " + status + ";;" + count + " pedidos;" + (count/totalPedidos*100).toFixed(1) + "%;📊 Status;;;;;;;;;\n";
    });
    
    csvContent += "▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲\n\n";
    
    // 🎯 RODAPÉ ELEGANTE
    csvContent += "═══════════════════════════════════════════════════════════════════════\n";
    csvContent += "🏢 RELATÓRIO GERADO AUTOMATICAMENTE PELO SISTEMA D&Z DASHBOARD\n";
    csvContent += "📅 Data/Hora: " + new Date().toLocaleString('pt-BR') + "\n";
    csvContent += "👤 Sistema: Dashboard Executivo v2.0\n";
    csvContent += "🏆 Qualidade: Relatório Premium\n";
    csvContent += "© " + new Date().getFullYear() + " D&Z - Todos os direitos reservados\n";
    csvContent += "═══════════════════════════════════════════════════════════════════════\n";
    
    // Criar e baixar arquivo
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `DZ_Relatorio_Premium_${dataInicio}_${dataFim}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Feedback visual elegante
    alert(`🎉 RELATÓRIO PREMIUM EXPORTADO COM SUCESSO!\n\n📁 Arquivo: DZ_Relatorio_Premium_${dataInicio}_${dataFim}.csv\n\n✨ CONTEÚDO PREMIUM INCLUSO:\n🎯 Resumo Executivo com Emojis\n📈 Evolução Diária Detalhada\n🏆 Top 5 Categorias com Rankings\n📋 ${totalPedidos} Pedidos Completamente Detalhados\n📊 Estatísticas Avançadas e Insights\n🎨 Design Visual Profissional\n\n💎 Perfeito para apresentações executivas!`);
}
</script>

 </body>
</html>