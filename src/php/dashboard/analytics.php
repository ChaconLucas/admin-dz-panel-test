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
        'itens_vendidos' => 0,
        'total_desconto_frete' => 0,
        'total_desconto_cupom' => 0
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
      .top-controls {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1.5rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
      }
      
      .filters-card {
        background: var(--color-white);
        border-radius: 12px;
        padding: 1rem 1.25rem;
        box-shadow: var(--box-shadow);
        border: 1px solid var(--color-info-light);
        flex: 1;
        min-width: 100%;
        width: 100%;
      }
      
      main h1 {
        margin: 0 0 1.5rem 0;
        color: var(--color-dark);
        font-size: 1.8rem;
      }
      

      
      
      .export-btn {
        background: #ff00cc;
        color: white;
        border: none;
        padding: 0.625rem 1.125rem;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
        font-weight: 500;
        font-size: 0.875rem;
        box-shadow: 0 2px 8px rgba(255, 0, 204, 0.25);
        white-space: nowrap;
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
        gap: 0.75rem;
        align-items: center;
      }
      
      /* Modal de Sucesso Elegante */
      .success-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        display: none;
        z-index: 99999;
        animation: fadeIn 0.3s ease;
      }
      
      .success-modal-content {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: linear-gradient(135deg, #ff00cc 0%, #e600b3 100%);
        border-radius: 20px;
        padding: 0;
        min-width: 400px;
        max-width: 500px;
        box-shadow: 0 20px 60px rgba(255, 0, 204, 0.4);
        animation: slideIn 0.4s ease-out;
        overflow: hidden;
      }
      
      .modal-header {
        background: rgba(255, 255, 255, 0.15);
        padding: 20px;
        text-align: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
      }
      
      .modal-icon {
        font-size: 3rem;
        margin-bottom: 10px;
        animation: bounce 0.6s ease-in-out;
      }
      
      .modal-title {
        color: white;
        font-size: 1.4rem;
        font-weight: 700;
        margin: 0;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
      }
      
      .modal-body {
        padding: 25px;
        background: var(--color-white);
      }
      
      .modal-filename {
        background: #f8f9fa;
        border: 2px dashed #ff00cc;
        border-radius: 12px;
        padding: 15px;
        margin: 15px 0;
        text-align: center;
        font-weight: 600;
        color: #ff00cc;
        font-size: 0.95rem;
      }
      
      .modal-features {
        margin: 20px 0;
      }
      
      .modal-features h4 {
        color: #333;
        margin-bottom: 12px;
        font-size: 1rem;
        display: flex;
        align-items: center;
        gap: 8px;
      }
      
      .feature-list {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-left: 0;
        padding-left: 0;
        list-style: none;
      }
      
      .feature-list li {
        background: #f8f9fa;
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 0.85rem;
        color: #495057;
        border-left: 3px solid #ff00cc;
      }
      
      .modal-footer {
        text-align: center;
        padding: 20px 25px;
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
      }
      
      .modal-close-btn {
        background: linear-gradient(135deg, #ff00cc 0%, #e600b3 100%);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 25px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(255, 0, 204, 0.3);
      }
      
      .modal-close-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(255, 0, 204, 0.4);
      }
      
      @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
      }
      
      @keyframes progress {
        0% { 
          transform: translateX(-100%); 
          opacity: 0.8;
        }
        50% {
          opacity: 1;
        }
        100% { 
          transform: translateX(100%);
          opacity: 0.8;
        }
      }
      
      @keyframes fillProgress {
        0% { width: 0%; }
        100% { width: 100%; }
      }
      
      @keyframes shimmer {
        0% { 
          background-position: -200px 0; 
        }
        100% { 
          background-position: 200px 0; 
        }
      }
      
      @keyframes slideIn {
        from { 
          opacity: 0;
          transform: translate(-50%, -60%);
        }
        to { 
          opacity: 1;
          transform: translate(-50%, -50%);
        }
      }
      
      @keyframes bounce {
        0%, 20%, 53%, 80%, 100% {
          animation-timing-function: cubic-bezier(0.215, 0.610, 0.355, 1.000);
          transform: translate3d(0,0,0);
        }
        40%, 43% {
          animation-timing-function: cubic-bezier(0.755, 0.050, 0.855, 0.060);
          transform: translate3d(0,-15px,0);
        }
        70% {
          animation-timing-function: cubic-bezier(0.755, 0.050, 0.855, 0.060);
          transform: translate3d(0,-7px,0);
        }
        90% {
          transform: translate3d(0,-2px,0);
        }
      }
      
      .filters-card {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.5rem;
        flex-wrap: wrap;
      }
      
      .filters-card .date-form {
        flex: 0 0 auto;
      }
      
      .filters-card .quick-filters {
        flex: 1;
        justify-content: center;
        max-width: 600px;
      }
      
      .filters-card .export-buttons {
        flex: 0 0 auto;
      }
      
      .date-form {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: var(--color-background);
        padding: 0.625rem 0.875rem;
        border-radius: 8px;
        border: 1px solid var(--color-light);
        transition: all 0.2s ease;
        width: fit-content;
      }
      
      .date-form:focus-within {
        border-color: #ff00cc;
        background: var(--color-white);
      }
      
      .date-form input[type="date"] {
        border: none;
        background: transparent;
        font-size: 0.875rem;
        color: var(--color-dark);
        outline: none;
        min-width: 120px;
        cursor: pointer;
      }
      
      .date-separator {
        color: var(--color-info-dark);
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
        gap: 0.375rem;
        background: var(--color-background);
        padding: 0.375rem;
        border-radius: 8px;
        border: 1px solid var(--color-light);
        flex-wrap: wrap;
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
        color: var(--color-dark);
      }
      
      .quick-filter-btn:hover {
        background: var(--color-white);
        color: var(--color-dark);
        box-shadow: var(--box-shadow);
      }
      
      .quick-filter-btn.active {
        background: #ff00cc;
        color: white;
        box-shadow: 0 2px 6px rgba(255, 0, 204, 0.3);
      }
      
      /* Responsivo */
      @media (max-width: 768px) {
        .top-controls {
          flex-direction: column;
          gap: 1rem;
        }
        
        .filters-card {
          max-width: 100%;
          min-width: unset;
          padding: 1rem;
          gap: 1rem;
          flex-direction: column;
        }
        
        .filters-card .date-form,
        .filters-card .quick-filters,
        .filters-card .export-buttons {
          width: 100%;
        }
        
        .export-buttons {
          justify-content: center;
        }
        
        .date-form {
          flex-wrap: wrap;
          justify-content: center;
          width: 100%;
        }
        
        .quick-filters {
          justify-content: center;
          flex-wrap: wrap;
          width: 100%;
        }
      }
      
      .kpis-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
      }
      
      .kpi-card {
        background: linear-gradient(145deg, var(--color-white) 0%, rgba(255, 255, 255, 0.95) 100%);
        padding: 2rem 1.5rem;
        border-radius: 20px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        text-align: center;
        border: 1px solid rgba(255, 255, 255, 0.18);
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        position: relative;
        overflow: hidden;
        backdrop-filter: blur(10px);
      }
      
      .kpi-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #ff00cc 0%, #e600b3 35%, #ff6b9d 100%);
        border-radius: 20px 20px 0 0;
      }
      
      .kpi-card::after {
        content: "";
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255, 0, 204, 0.05) 0%, transparent 70%);
        opacity: 0;
        transition: opacity 0.4s ease;
      }
      
      .kpi-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 20px 60px rgba(255, 0, 204, 0.15), 
                    0 8px 32px rgba(0, 0, 0, 0.12);
      }
      
      .kpi-card:hover::after {
        opacity: 1;
      }
      
      .kpi-icon {
        width: 48px;
        height: 48px;
        margin: 0 auto 1rem;
        background: linear-gradient(135deg, #ff00cc 0%, #e600b3 100%);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        box-shadow: 0 4px 16px rgba(255, 0, 204, 0.3);
      }
      
      .kpi-title {
        color: var(--color-info-dark);
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.75rem;
        opacity: 0.8;
      }
      
      .kpi-value {
        font-size: 2.25rem;
        font-weight: 800;
        background: linear-gradient(135deg, #ff00cc 0%, #e600b3 50%, #ff6b9d 100%);
        background-clip: text;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin: 0.75rem 0;
        line-height: 1.1;
      }
      
      .kpi-subtitle {
        color: var(--color-info-dark);
        font-size: 0.8rem;
        font-weight: 500;
        opacity: 0.7;
        margin-top: 0.5rem;
      }
      
      .kpi-details {
        color: var(--color-info-dark);
        font-size: 0.75rem;
        font-weight: 400;
        margin-top: 0.5rem;
        opacity: 0.6;
      }
      
      .charts-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.5rem;
        margin-bottom: 2rem;
      }
      
      .chart-container {
        background: var(--color-white);
        padding: 1.25rem;
        border-radius: 10px;
        box-shadow: var(--box-shadow);
        border: 1px solid var(--color-info-light);
        transition: all 0.2s ease;
      }
      
      .chart-container:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.1);
      }
      
      .chart-container h3 {
        margin-top: 0;
        color: var(--color-dark);
        font-weight: 600;
        font-size: 1.1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--color-info-light);
        margin-bottom: 1rem;
      }
      
      .orders-table {
        background: var(--color-white);
        border-radius: 10px;
        overflow: hidden;
        box-shadow: var(--box-shadow);
        border: 1px solid var(--color-info-light);
      }
      
      .orders-table h3 {
        margin: 0;
        padding: 1.25rem;
        background: var(--color-background);
        border-bottom: 1px solid var(--color-info-light);
        color: var(--color-dark);
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
      
      /* Estilos do Modal Excel */
      .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        z-index: 99999;
        animation: fadeIn 0.3s ease;
      }
      
      .modal-content {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: var(--color-white);
        border-radius: 20px;
        padding: 0;
        min-width: 450px;
        max-width: 550px;
        box-shadow: var(--box-shadow);
        animation: slideIn 0.4s ease-out;
        overflow: hidden;
      }
      
      .modal-header {
        background: linear-gradient(135deg, #ff6b9d, #c44569);
        color: white;
        padding: 20px;
        position: relative;
        text-align: center;
      }
      
      .success-icon {
        font-size: 40px;
        margin-bottom: 10px;
        display: block;
      }
      
      .modal-header h2 {
        margin: 0;
        font-size: 24px;
        font-weight: bold;
      }
      
      .close-btn {
        position: absolute;
        top: 15px;
        right: 20px;
        background: none;
        border: none;
        font-size: 28px;
        color: white;
        cursor: pointer;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.3s ease;
      }
      
      .close-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.1);
      }
      
      .modal-body {
        padding: 25px;
      }
      
      .file-info {
        display: flex;
        align-items: center;
        background: #f8f9ff;
        padding: 15px;
        border-radius: 12px;
        margin-bottom: 20px;
        border-left: 4px solid #ff6b9d;
      }
      
      .file-icon {
        font-size: 30px;
        margin-right: 15px;
      }
      
      .file-details p {
        margin: 5px 0;
        font-size: 14px;
      }
      
      .status-success {
        color: #22c55e;
        font-weight: bold;
        padding: 2px 8px;
        background: #dcfce7;
        border-radius: 6px;
        font-size: 12px;
      }
      
      .features-list h3 {
        color: #333;
        margin-bottom: 15px;
        font-size: 16px;
      }
      
      .feature-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-bottom: 20px;
      }
      
      .feature-item {
        background: #f1f5f9;
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 13px;
        color: #475569;
        border-left: 3px solid #ff6b9d;
      }
      
      .download-status {
        background: #f0fff4;
        padding: 15px;
        border-radius: 10px;
        text-align: center;
        border: 1px solid #bbf7d0;
      }
      
      .progress-bar {
        width: 100%;
        height: 8px;
        background: #e5e7eb;
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 10px;
      }
      
      .progress-fill {
        width: 0%;
        height: 100%;
        background: linear-gradient(45deg, #ff6b9d, #c44569);
        border-radius: 4px;
        animation: fillProgress 2s ease-out forwards;
      }
      
      .modal-footer {
        background: #f8fafc;
        padding: 20px;
        text-align: center;
        border-top: 1px solid #e2e8f0;
      }
      
      .btn-elegant {
        background: linear-gradient(135deg, #ff6b9d, #c44569);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 25px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(255, 107, 157, 0.3);
      }
      
      .btn-elegant:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(255, 107, 157, 0.4);
      }
      
      @keyframes slideIn {
        from {
          opacity: 0;
          transform: translate(-50%, -60%);
        }
        to {
          opacity: 1;
          transform: translate(-50%, -50%);
        }
      }
      
      @keyframes slideOut {
        from {
          opacity: 1;
          transform: translate(-50%, -50%);
        }
        to {
          opacity: 0;
          transform: translate(-50%, -40%);
        }
      }

      /* Estilos do Modal para Modo Escuro */
      body.dark-theme-variables .modal-overlay {
        background: rgba(0, 0, 0, 0.8) !important;
      }
      
      body.dark-theme-variables .modal-content {
        background: var(--color-background) !important;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6) !important;
      }
      
      body.dark-theme-variables .modal-header {
        background: linear-gradient(135deg, #ff6b9d, #c44569) !important;
        color: white !important;
      }
      
      body.dark-theme-variables .modal-body {
        background: var(--color-background) !important;
        color: var(--color-dark) !important;
      }
      
      body.dark-theme-variables .file-info {
        background: rgba(255, 255, 255, 0.05) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: var(--color-dark) !important;
      }
      
      body.dark-theme-variables .file-details p {
        color: var(--color-dark) !important;
      }
      
      body.dark-theme-variables .file-details strong {
        color: var(--color-white) !important;
      }
      
      body.dark-theme-variables .status-success {
        color: var(--color-success) !important;
      }
      
      body.dark-theme-variables .features-list h3 {
        color: var(--color-white) !important;
      }
      
      body.dark-theme-variables .feature-item {
        background: rgba(255, 255, 255, 0.05) !important;
        color: var(--color-dark) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
      }
      
      body.dark-theme-variables .download-status p {
        color: var(--color-dark) !important;
      }
      
      body.dark-theme-variables .progress-bar {
        background: rgba(255, 255, 255, 0.1) !important;
      }
      
      body.dark-theme-variables .progress-fill {
        background: linear-gradient(45deg, #ff6b9d, #c44569) !important;
      }
      
      body.dark-theme-variables .download-status {
        background: rgba(255, 255, 255, 0.05) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
      }
      
      body.dark-theme-variables .modal-footer {
        background: rgba(255, 255, 255, 0.05) !important;
        border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
      }
      
      body.dark-theme-variables .btn-elegant {
        background: linear-gradient(135deg, #ff6b9d, #c44569) !important;
        color: white !important;
        box-shadow: 0 4px 15px rgba(255, 107, 157, 0.4) !important;
      }
      
      body.dark-theme-variables .btn-elegant:hover {
        box-shadow: 0 6px 20px rgba(255, 107, 157, 0.5) !important;
      }
      
      body.dark-theme-variables .close-btn {
        color: white !important;
      }
      
      body.dark-theme-variables .close-btn:hover {
        background: rgba(255, 255, 255, 0.2) !important;
      }

      /* Regras específicas para o modo escuro */
      body.dark-theme-variables main h1 {
        color: var(--color-dark) !important;
      }
      
      body.dark-theme-variables .filters-card {
        background: var(--color-background) !important;
        border-color: var(--color-dark) !important;
      }

      body.dark-theme-variables .date-form {
        background: var(--color-background) !important;
        border-color: var(--color-dark) !important;
      }
      
      body.dark-theme-variables .date-form input[type="date"] {
        color: var(--color-dark) !important;
      }
      
      body.dark-theme-variables .date-separator {
        color: var(--color-info-dark) !important;
      }
      
      body.dark-theme-variables .quick-filters {
        background: var(--color-background) !important;
        border-color: var(--color-dark) !important;
      }
      
      body.dark-theme-variables .quick-filter-btn {
        color: var(--color-dark) !important;
      }
      
      body.dark-theme-variables .quick-filter-btn:hover {
        background: var(--color-white) !important;
        color: var(--color-dark) !important;
      }
      
      body.dark-theme-variables .submit-btn {
        background: #007bff !important;
        color: white !important;
      }
      
      body.dark-theme-variables .submit-btn:hover {
        background: #0056b3 !important;
      }
      
      body.dark-theme-variables .analytics-info h3 {
        color: var(--color-dark) !important;
      }
      
      /* Regras específicas para os novos cards de KPI no modo escuro */
      body.dark-theme-variables .kpi-card {
        background: linear-gradient(145deg, var(--color-background) 0%, rgba(26, 33, 44, 0.95) 100%) !important;
        border-color: var(--color-dark) !important;
        backdrop-filter: blur(15px) !important;
      }
      
      body.dark-theme-variables .kpi-card::after {
        background: radial-gradient(circle, rgba(255, 0, 204, 0.1) 0%, transparent 70%) !important;
      }
      
      body.dark-theme-variables .kpi-title {
        color: var(--color-white) !important;
      }
      
      body.dark-theme-variables .kpi-subtitle {
        color: var(--color-info-light) !important;
      }
      
      body.dark-theme-variables .kpi-details {
        color: var(--color-info-light) !important;
      }

      /* Responsividade melhorada para os cards */
      @media (max-width: 768px) {
        .kpis-grid {
          grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
          gap: 1rem;
        }
        
        .kpi-card {
          padding: 1.5rem 1rem;
        }
        
        .kpi-icon {
          width: 40px;
          height: 40px;
          font-size: 1.25rem;
        }
        
        .kpi-value {
          font-size: 1.875rem;
        }
        
        .charts-grid {
          grid-template-columns: 1fr;
          gap: 1rem;
        }
        
        .modal-content {
          width: 95%;
          max-width: 95%;
          margin: 10% auto;
          min-width: unset;
        }
        
        .modal-header h2 {
          font-size: 1.2rem;
        }
        
        .btn-elegant {
          width: 100%;
          margin: 0.5rem 0;
        }
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
        <h1>Relatórios</h1>
        
        <!-- Header com layout reorganizado -->
        <div class="top-controls">
          <!-- Card completo com todos os controles em uma linha -->
          <div class="filters-card">
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
        </div>
        
        <!-- Cards de KPIs -->
        <div class="kpis-grid">
          <div class="kpi-card">
            <div class="kpi-icon">
              <i class="fas fa-coins"></i>
            </div>
            <div class="kpi-title">Faturamento Líquido</div>
            <div class="kpi-value">R$ <?= number_format($kpis['faturamento'], 2, ',', '.') ?></div>
            <div class="kpi-subtitle">Valor final recebido</div>
          </div>
          
          <div class="kpi-card">
            <div class="kpi-icon">
              <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="kpi-title">Vendas</div>
            <div class="kpi-value"><?= $kpis['total_vendas'] ?></div>
            <div class="kpi-subtitle">pedidos realizados</div>
          </div>
          
          <div class="kpi-card">
            <div class="kpi-icon">
              <i class="fas fa-chart-line"></i>
            </div>
            <div class="kpi-title">Ticket Médio</div>
            <div class="kpi-value">R$ <?= number_format($kpis['ticket_medio'], 2, ',', '.') ?></div>
            <div class="kpi-subtitle">valor médio por pedido</div>
          </div>
          
          <div class="kpi-card">
            <div class="kpi-icon">
              <i class="fas fa-percentage"></i>
            </div>
            <div class="kpi-title">Descontos Dados</div>
            <div class="kpi-value">R$ <?= number_format(($kpis['total_desconto_frete'] + $kpis['total_desconto_cupom']), 2, ',', '.') ?></div>
            <div class="kpi-details">
              Frete: R$ <?= number_format($kpis['total_desconto_frete'], 2, ',', '.') ?> • 
              Cupom: R$ <?= number_format($kpis['total_desconto_cupom'], 2, ',', '.') ?>
            </div>
          </div>
          
          <div class="kpi-card">
            <div class="kpi-icon">
              <i class="fas fa-box"></i>
            </div>
            <div class="kpi-title">Itens Vendidos</div>
            <div class="kpi-value"><?= $kpis['itens_vendidos'] ?></div>
            <div class="kpi-subtitle">produtos saíram do estoque</div>
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
    
    // Criar formulário para enviar dados via POST para o exportador Excel
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_excel.php';
    form.style.display = 'none';
    
    // Adicionar campos do formulário
    const fields = {
        action: 'export_excel',
        data_inicio: dataInicio,
        data_fim: dataFim,
        kpis: JSON.stringify(kpis),
        lista_pedidos: JSON.stringify(dadosList),
        dados_evolucao: JSON.stringify(dadosEvolucao),
        dados_categorias: JSON.stringify(dadosCategorias)
    };
    
    Object.keys(fields).forEach(key => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = fields[key];
        form.appendChild(input);
    });
    
    // Adicionar ao DOM e submeter
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    
    // Feedback visual elegante
    setTimeout(() => {
        console.log('🚀 Chamando showSuccessModal...');
        showSuccessModal();
    }, 1000);
}

// Função para mostrar modal elegante
function showSuccessModal() {
    console.log('🎯 Modal sendo aberto...');
    const modal = document.getElementById('excelModal');
    
    if (!modal) {
        console.log('❌ Modal não encontrado!');
        return;
    }
    
    const modalContent = modal.querySelector('.modal-content');
    
    if (!modalContent) {
        console.log('❌ Modal content não encontrado!');
        return;
    }
    
    console.log('✅ Modal encontrado, exibindo...');
    modal.style.display = 'block';
    modalContent.style.animation = 'slideIn 0.5s ease-out';
    
    // Fechar modal automaticamente após 4 segundos
    setTimeout(() => {
        closeModal();
    }, 4000);
}

function closeModal() {
    console.log('🔄 Fechando modal...');
    const modal = document.getElementById('excelModal');
    
    if (!modal) {
        console.log('❌ Modal não encontrado ao fechar!');
        return;
    }
    
    const modalContent = modal.querySelector('.modal-content');
    
    if (modalContent) {
        modalContent.style.animation = 'slideOut 0.3s ease-in';
    }
    
    setTimeout(() => {
        modal.style.display = 'none';
        console.log('✅ Modal fechado com sucesso!');
    }, 300);
}

// Fechar modal ao clicar fora
window.onclick = function(event) {
    const modal = document.getElementById('excelModal');
    if (event.target == modal) {
        closeModal();
    }
}

// Sistema de tema já implementado no dashboard.js
// Aplicar tema salvo na inicialização
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('darkTheme');
    if (savedTheme === 'true') {
        document.body.classList.add('dark-theme-variables');
        
        // Atualizar ícones do toggle
        const sunIcon = document.querySelector('.theme-toggler span:nth-child(1)');
        const moonIcon = document.querySelector('.theme-toggler span:nth-child(2)');
        
        if (sunIcon && moonIcon) {
            sunIcon.classList.remove('active');
            moonIcon.classList.add('active');
        }
        
        console.log('Tema dark aplicado em analytics.php');
    }
});
</script>

<!-- Modal Elegante para Excel -->
<div id="excelModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <div class="success-icon">✅</div>
            <h2>Excel Premium Gerado!</h2>
            <button onclick="closeModal()" class="close-btn">&times;</button>
        </div>
        
        <div class="modal-body">
            <div class="file-info">
                <div class="file-icon">📊</div>
                <div class="file-details">
                    <p><strong>Arquivo:</strong> DZ_Relatorio_Premium.xls</p>
                    <p><strong>Status:</strong> <span class="status-success">Concluído</span></p>
                </div>
            </div>
            
            <div class="features-list">
                <h3>🎨 Formatação Profissional:</h3>
                <div class="feature-grid">
                    <div class="feature-item">🏢 Cabeçalho D&Z elegante</div>
                    <div class="feature-item">📊 Colunas auto-ajustáveis</div>
                    <div class="feature-item">🎯 KPIs destacados</div>
                    <div class="feature-item">📈 Evolução organizada</div>
                    <div class="feature-item">🏆 Status coloridos</div>
                    <div class="feature-item">💎 Design executivo</div>
                </div>
            </div>
            
            <div class="download-status">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <p>🚀 Pronto para apresentações executivas!</p>
            </div>
        </div>
        
        <div class="modal-footer">
            <button onclick="closeModal()" class="btn-elegant">
                <span>Perfeito!</span>
            </button>
        </div>
    </div>
</div>

 </body>
</html>