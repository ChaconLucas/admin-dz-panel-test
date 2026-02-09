<?php
session_start();
// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../../../PHP/login.php');
    exit();
}

require_once '../../../PHP/conexao.php';
require_once 'helper-contador.php';
require_once '../auto_log.php';

// Criar tabela status_fluxo se não existir
if ($conexao) {
    $createTableQuery = "CREATE TABLE IF NOT EXISTS status_fluxo (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        cor_hex VARCHAR(7) NOT NULL DEFAULT '#ff00d4',
        baixa_estoque TINYINT(1) DEFAULT 0,
        bloquear_edicao TINYINT(1) DEFAULT 0,
        gerar_logistica TINYINT(1) DEFAULT 0,
        notificar TINYINT(1) DEFAULT 0,
        estornar_estoque TINYINT(1) DEFAULT 0,
        gerar_link_cobranca TINYINT(1) DEFAULT 0,
        sla_horas INT DEFAULT 0,
        mensagem_template TEXT,
        mensagem_email TEXT,
        ordem INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    mysqli_query($conexao, $createTableQuery);
    
    // Adicionar novas colunas se não existirem (para bancos existentes)
    $alterQueries = [
        "ALTER TABLE status_fluxo ADD COLUMN IF NOT EXISTS estornar_estoque TINYINT(1) DEFAULT 0",
        "ALTER TABLE status_fluxo ADD COLUMN IF NOT EXISTS gerar_link_cobranca TINYINT(1) DEFAULT 0",
        "ALTER TABLE status_fluxo ADD COLUMN IF NOT EXISTS sla_horas INT DEFAULT 0",
        "ALTER TABLE status_fluxo ADD COLUMN IF NOT EXISTS mensagem_email TEXT"
    ];
    
    foreach ($alterQueries as $query) {
        mysqli_query($conexao, $query);
    }
    
    // Verificar se há registros, se não, inserir alguns padrão
    $checkRecords = mysqli_query($conexao, "SELECT COUNT(*) as total FROM status_fluxo");
    if ($checkRecords) {
        $row = mysqli_fetch_assoc($checkRecords);
        if ($row['total'] == 0) {
            $defaultStatuses = [
                ['nome' => 'Pedido Recebido', 'cor_hex' => '#ff00d4', 'ordem' => 1, 'notificar' => 1, 'mensagem_template' => 'Olá {cliente}! Recebemos seu pedido #{id_pedido}. Em breve você receberá mais atualizações. Obrigado por escolher nossa loja!'],
                ['nome' => 'Pagamento Confirmado', 'cor_hex' => '#41f1b6', 'ordem' => 2, 'notificar' => 1, 'mensagem_template' => 'Ótima notícia, {cliente}! Seu pagamento do pedido #{id_pedido} foi confirmado. Agora vamos preparar seus produtos para envio.'],
                ['nome' => 'Em Preparação', 'cor_hex' => '#ffbb55', 'ordem' => 3, 'baixa_estoque' => 1, 'bloquear_edicao' => 1, 'mensagem_template' => 'Seu pedido #{id_pedido} está sendo preparado com muito carinho. Em breve será enviado!'],
                ['nome' => 'Enviado', 'cor_hex' => '#007bff', 'ordem' => 4, 'gerar_logistica' => 1, 'notificar' => 1, 'mensagem_template' => 'Pedido #{id_pedido} enviado! Código de rastreamento: {codigo_rastreio}. Acompanhe a entrega pelo link: {link_rastreio}'],
                ['nome' => 'Entregue', 'cor_hex' => '#28a745', 'ordem' => 5, 'notificar' => 1, 'mensagem_template' => 'Parabéns {cliente}! Seu pedido #{id_pedido} foi entregue com sucesso. Esperamos que goste de seus produtos!']
            ];
            
            foreach ($defaultStatuses as $status) {
                $insertQuery = "INSERT INTO status_fluxo (nome, cor_hex, baixa_estoque, bloquear_edicao, gerar_logistica, notificar, mensagem_template, ordem) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conexao, $insertQuery);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ssiiiisi", $status['nome'], $status['cor_hex'], $status['baixa_estoque'], $status['bloquear_edicao'], $status['gerar_logistica'], $status['notificar'], $status['mensagem_template'], $status['ordem']);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

$success_msg = '';
$error_msg = '';

// Recuperar mensagens da sessão (padrão PRG)
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
        case 'add_status':
            $nome = trim($_POST['nome'] ?? '');
            $cor_hex = $_POST['cor_hex'] ?? '#ff00d4';
            $baixa_estoque = isset($_POST['baixa_estoque']) ? 1 : 0;
            $bloquear_edicao = isset($_POST['bloquear_edicao']) ? 1 : 0;
            $gerar_logistica = isset($_POST['gerar_logistica']) ? 1 : 0;
            $notificar = isset($_POST['notificar']) ? 1 : 0;
            $estornar_estoque = isset($_POST['estornar_estoque']) ? 1 : 0;
            $gerar_link_cobranca = isset($_POST['gerar_link_cobranca']) ? 1 : 0;
            $sla_horas = intval($_POST['sla_horas'] ?? 0);
            $mensagem_template = trim($_POST['mensagem_template'] ?? '');
            $mensagem_email = trim($_POST['mensagem_email'] ?? '');
            
            if ($nome) {
                // Obter próxima ordem
                $orderResult = mysqli_query($conexao, "SELECT MAX(ordem) as max_ordem FROM status_fluxo");
                $nextOrder = 1;
                if ($orderResult) {
                    $row = mysqli_fetch_assoc($orderResult);
                    $nextOrder = ($row['max_ordem'] ?? 0) + 1;
                }
                
                $insertQuery = "INSERT INTO status_fluxo (nome, cor_hex, baixa_estoque, bloquear_edicao, gerar_logistica, notificar, estornar_estoque, gerar_link_cobranca, sla_horas, mensagem_template, mensagem_email, ordem) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conexao, $insertQuery);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ssiiiiiiiissi", $nome, $cor_hex, $baixa_estoque, $bloquear_edicao, $gerar_logistica, $notificar, $estornar_estoque, $gerar_link_cobranca, $sla_horas, $mensagem_template, $mensagem_email, $nextOrder);
                    if (mysqli_stmt_execute($stmt)) {
                        registrar_log($conexao, "Adicionou novo status de fluxo: $nome");
                        // Implementar PRG (Post-Redirect-Get) para evitar resubmissão
                        $_SESSION['success_msg'] = "✅ Status '$nome' adicionado com sucesso!";
                        header('Location: gestao-fluxo.php');
                        exit();
                    } else {
                        $_SESSION['error_msg'] = "❌ Erro ao adicionar status: " . mysqli_error($conexao);
                        header('Location: gestao-fluxo.php');
                        exit();
                    }
                    mysqli_stmt_close($stmt);
                }
            } else {
                $_SESSION['error_msg'] = "❌ Nome do status é obrigatório!";
                header('Location: gestao-fluxo.php');
                exit();
            }
            break;
            
        case 'update_status':
            $id = intval($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $cor_hex = $_POST['cor_hex'] ?? '#ff00d4';
            $baixa_estoque = isset($_POST['baixa_estoque']) ? 1 : 0;
            $bloquear_edicao = isset($_POST['bloquear_edicao']) ? 1 : 0;
            $gerar_logistica = isset($_POST['gerar_logistica']) ? 1 : 0;
            $notificar = isset($_POST['notificar']) ? 1 : 0;
            $estornar_estoque = isset($_POST['estornar_estoque']) ? 1 : 0;
            $gerar_link_cobranca = isset($_POST['gerar_link_cobranca']) ? 1 : 0;
            $sla_horas = intval($_POST['sla_horas'] ?? 0);
            $mensagem_template = trim($_POST['mensagem_template'] ?? '');
            $mensagem_email = trim($_POST['mensagem_email'] ?? '');
            
            if ($id && $nome) {
                $updateQuery = "UPDATE status_fluxo SET nome = ?, cor_hex = ?, baixa_estoque = ?, bloquear_edicao = ?, gerar_logistica = ?, notificar = ?, estornar_estoque = ?, gerar_link_cobranca = ?, sla_horas = ?, mensagem_template = ?, mensagem_email = ? WHERE id = ?";
                $stmt = mysqli_prepare($conexao, $updateQuery);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ssiiiiiiissi", $nome, $cor_hex, $baixa_estoque, $bloquear_edicao, $gerar_logistica, $notificar, $estornar_estoque, $gerar_link_cobranca, $sla_horas, $mensagem_template, $mensagem_email, $id);
                    if (mysqli_stmt_execute($stmt)) {
                        registrar_log($conexao, "Atualizou status de fluxo: $nome (ID: $id)");
                        // Implementar PRG (Post-Redirect-Get) para evitar resubmissão
                        $_SESSION['success_msg'] = "✅ Status '$nome' atualizado com sucesso!";
                        header('Location: gestao-fluxo.php');
                        exit();
                    } else {
                        $_SESSION['error_msg'] = "❌ Erro ao atualizar status: " . mysqli_error($conexao);
                        header('Location: gestao-fluxo.php');
                        exit();
                    }
                    mysqli_stmt_close($stmt);
                }
            } else {
                $_SESSION['error_msg'] = "❌ Dados inválidos para atualização!";
                header('Location: gestao-fluxo.php');
                exit();
            }
            break;
            
        case 'delete_status':
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                // Buscar nome antes de deletar para o log
                $nameQuery = "SELECT nome FROM status_fluxo WHERE id = ?";
                $nameStmt = mysqli_prepare($conexao, $nameQuery);
                $statusName = '';
                if ($nameStmt) {
                    mysqli_stmt_bind_param($nameStmt, "i", $id);
                    mysqli_stmt_execute($nameStmt);
                    $nameResult = mysqli_stmt_get_result($nameStmt);
                    if ($nameRow = mysqli_fetch_assoc($nameResult)) {
                        $statusName = $nameRow['nome'];
                    }
                    mysqli_stmt_close($nameStmt);
                }
                
                $deleteQuery = "DELETE FROM status_fluxo WHERE id = ?";
                $stmt = mysqli_prepare($conexao, $deleteQuery);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "i", $id);
                    if (mysqli_stmt_execute($stmt)) {
                        registrar_log($conexao, "Removeu status de fluxo: $statusName (ID: $id)");
                        // Implementar PRG (Post-Redirect-Get) para evitar resubmissão
                        $_SESSION['success_msg'] = "✅ Status removido com sucesso!";
                        header('Location: gestao-fluxo.php');
                        exit();
                    } else {
                        $_SESSION['error_msg'] = "❌ Erro ao remover status: " . mysqli_error($conexao);
                        header('Location: gestao-fluxo.php');
                        exit();
                    }
                    mysqli_stmt_close($stmt);
                }
            }
            break;
    }
}

// Buscar todos os status
$statusList = [];
try {
    $result = mysqli_query($conexao, "SELECT * FROM status_fluxo ORDER BY ordem ASC, id ASC");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $statusList[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Erro ao buscar status: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../css/dashboard.css" />
    <link rel="stylesheet" href="../../css/dashboard-sections.css" />
    <link rel="stylesheet" href="../../css/dashboard-cards.css" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp" rel="stylesheet" />
    <title>Gestão de Fluxo - Dashboard</title>
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
                <a href="index.php" id="dashboard-link">
                    <span class="material-symbols-sharp">grid_view</span>
                    <h3>Painel</h3>
                </a>

                <a href="customers.php" id="clientes-link">
                    <span class="material-symbols-sharp">group</span>
                    <h3>Clientes</h3>
                </a>

                <a href="orders.php" id="pedidos-link">
                    <span class="material-symbols-sharp">Orders</span>
                    <h3>Pedidos</h3>
                </a>

                <a href="analytics.php" id="graficos-link">
                    <span class="material-symbols-sharp">Insights</span>
                    <h3>Gráficos</h3>
                </a>

                <a href="menssage.php" id="mensagens-link">
                    <span class="material-symbols-sharp">Mail</span>
                    <h3>Mensagens</h3>
                    <span class="message-count"><?php echo $nao_lidas; ?></span>
                </a>

                <a href="products.php" id="produtos-link">
                    <span class="material-symbols-sharp">Inventory</span>
                    <h3>Produtos</h3>
                </a>

                <a href="cupons.php" id="cupons-link">
                    <span class="material-symbols-sharp">sell</span>
                    <h3>Cupons</h3>
                </a>

                <a href="gestao-fluxo.php" id="gestao-fluxo-link" class="active">
                    <span class="material-symbols-sharp">account_tree</span>
                    <h3>Gestão de Fluxo</h3>
                </a>

                <div class="menu-item-container">
                    <a href="geral.php" id="configuracoes-link" class="menu-item-with-submenu">
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
            <h1>Gestão de Fluxo</h1>

            <!-- Header com botão adicionar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin: 1rem 0;">
                <div class="date">
                    <span style="display: flex; align-items: center; gap: 0.5rem;">
                        <span class="material-symbols-sharp">account_tree</span>
                        Status de Pedidos
                    </span>
                </div>
                <button onclick="openAddModal()" style="background: var(--color-primary); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: var(--border-radius-2); cursor: pointer; display: flex; align-items: center; gap: 0.5rem; font-weight: 600;">
                    <span class="material-symbols-sharp">add</span>
                    Adicionar Novo Status
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

            <!-- Lista de Status em Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
                <?php foreach ($statusList as $status): ?>
                    <div class="status-card" style="background: var(--color-white); border-radius: var(--card-border-radius); padding: var(--card-padding); box-shadow: var(--box-shadow); position: relative;">
                        <!-- Header do Card -->
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div style="width: 16px; height: 16px; border-radius: 50%; background: <?= $status['cor_hex'] ?>;"></div>
                                <h3 style="margin: 0; color: var(--color-dark); font-size: 1.2rem;"><?= htmlspecialchars($status['nome']) ?></h3>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <button 
                                    class="edit-status-btn"
                                    data-id="<?= $status['id'] ?>"
                                    data-nome="<?= htmlspecialchars($status['nome']) ?>"
                                    data-cor="<?= $status['cor_hex'] ?>"
                                    data-baixa-estoque="<?= $status['baixa_estoque'] ?>"
                                    data-bloquear-edicao="<?= $status['bloquear_edicao'] ?>"
                                    data-gerar-logistica="<?= $status['gerar_logistica'] ?>"
                                    data-notificar="<?= $status['notificar'] ?>"
                                    data-estornar-estoque="<?= $status['estornar_estoque'] ?? 0 ?>"
                                    data-gerar-link-cobranca="<?= $status['gerar_link_cobranca'] ?? 0 ?>"
                                    data-sla-horas="<?= $status['sla_horas'] ?? 0 ?>"
                                    data-template="<?= htmlspecialchars($status['mensagem_template']) ?>"
                                    data-mensagem-email="<?= htmlspecialchars($status['mensagem_email'] ?? '') ?>"
                                    style="background: var(--color-primary); color: white; border: none; padding: 0.5rem; border-radius: var(--border-radius-1); cursor: pointer; display: flex; align-items: center;">
                                    <span class="material-symbols-sharp" style="font-size: 1rem;">edit</span>
                                </button>
                                <button onclick="deleteStatus(<?= $status['id'] ?>, '<?= addslashes($status['nome']) ?>')" style="background: var(--color-danger); color: white; border: none; padding: 0.5rem; border-radius: var(--border-radius-1); cursor: pointer; display: flex; align-items: center;">
                                    <span class="material-symbols-sharp" style="font-size: 1rem;">delete</span>
                                </button>
                            </div>
                        </div>

                        <!-- Badge Preview -->
                        <div style="margin-bottom: 1.5rem;">
                            <span style="padding: 0.5rem 1rem; background: <?= $status['cor_hex'] ?>; color: white; border-radius: var(--border-radius-2); font-size: 0.85rem; font-weight: 600;">
                                <?= htmlspecialchars($status['nome']) ?>
                            </span>
                        </div>

                        <!-- Regras de Negócio -->
                        <div style="margin-bottom: 1.5rem;">
                            <h4 style="margin-bottom: 0.75rem; color: var(--color-dark); font-size: 0.9rem; font-weight: 600;">REGRAS DE NEGÓCIO</h4>
                            <div style="display: grid; gap: 0.5rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span class="material-symbols-sharp" style="font-size: 1.2rem; color: <?= $status['baixa_estoque'] ? 'var(--color-success)' : 'var(--color-info-dark)' ?>;">
                                        <?= $status['baixa_estoque'] ? 'check_circle' : 'radio_button_unchecked' ?>
                                    </span>
                                    <span style="font-size: 0.85rem; color: var(--color-dark);">Baixar Estoque</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span class="material-symbols-sharp" style="font-size: 1.2rem; color: <?= ($status['estornar_estoque'] ?? 0) ? 'var(--color-success)' : 'var(--color-info-dark)' ?>;">
                                        <?= ($status['estornar_estoque'] ?? 0) ? 'check_circle' : 'radio_button_unchecked' ?>
                                    </span>
                                    <span style="font-size: 0.85rem; color: var(--color-dark);">Estornar Estoque</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span class="material-symbols-sharp" style="font-size: 1.2rem; color: <?= $status['bloquear_edicao'] ? 'var(--color-success)' : 'var(--color-info-dark)' ?>;">
                                        <?= $status['bloquear_edicao'] ? 'check_circle' : 'radio_button_unchecked' ?>
                                    </span>
                                    <span style="font-size: 0.85rem; color: var(--color-dark);">Bloquear Edição do Pedido</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span class="material-symbols-sharp" style="font-size: 1.2rem; color: <?= $status['gerar_logistica'] ? 'var(--color-success)' : 'var(--color-info-dark)' ?>;">
                                        <?= $status['gerar_logistica'] ? 'check_circle' : 'radio_button_unchecked' ?>
                                    </span>
                                    <span style="font-size: 0.85rem; color: var(--color-dark);">Gerar Logística (Melhor Envio)</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span class="material-symbols-sharp" style="font-size: 1.2rem; color: <?= ($status['gerar_link_cobranca'] ?? 0) ? 'var(--color-success)' : 'var(--color-info-dark)' ?>;">
                                        <?= ($status['gerar_link_cobranca'] ?? 0) ? 'check_circle' : 'radio_button_unchecked' ?>
                                    </span>
                                    <span style="font-size: 0.85rem; color: var(--color-dark);">Gerar Link de Cobrança</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span class="material-symbols-sharp" style="font-size: 1.2rem; color: <?= $status['notificar'] ? 'var(--color-success)' : 'var(--color-info-dark)' ?>;">
                                        <?= $status['notificar'] ? 'check_circle' : 'radio_button_unchecked' ?>
                                    </span>
                                    <span style="font-size: 0.85rem; color: var(--color-dark);">Notificação Automática</span>
                                </div>
                                <?php if (($status['sla_horas'] ?? 0) > 0): ?>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem; padding: 0.5rem; background: rgba(255, 193, 7, 0.1); border-radius: var(--border-radius-1); border-left: 3px solid var(--color-warning);">
                                        <span class="material-symbols-sharp" style="font-size: 1.2rem; color: var(--color-warning);">
                                            schedule
                                        </span>
                                        <span style="font-size: 0.85rem; color: var(--color-dark); font-weight: 600;">SLA: <?= $status['sla_horas'] ?>h</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Mensagens Configuradas -->
                        <?php if ($status['notificar'] && ($status['mensagem_template'] || $status['mensagem_email'])): ?>
                            <div style="margin-bottom: 1rem; border-top: 1px solid rgba(0,0,0,0.1); padding-top: 1rem;">
                                
                                <?php if ($status['mensagem_email']): ?>
                                    <div style="margin-bottom: 1rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                            <span class="material-symbols-sharp" style="font-size: 1rem; color: var(--color-primary);">email</span>
                                            <h4 style="margin: 0; color: var(--color-dark); font-size: 0.85rem; font-weight: 600;">MENSAGEM DE E-MAIL</h4>
                                        </div>
                                        <div style="background: rgba(255, 0, 204, 0.05); padding: 0.75rem; border-radius: var(--border-radius-1); border-left: 4px solid var(--color-primary); font-size: 0.8rem; color: var(--color-dark); line-height: 1.4;">
                                            <?= nl2br(htmlspecialchars(substr($status['mensagem_email'], 0, 150) . (strlen($status['mensagem_email']) > 150 ? '...' : ''))) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($status['mensagem_template']): ?>
                                    <div style="margin-bottom: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                            <span class="material-symbols-sharp" style="font-size: 1rem; color: var(--color-info);">message</span>
                                            <h4 style="margin: 0; color: var(--color-dark); font-size: 0.85rem; font-weight: 600;">TEMPLATE GENÉRICO</h4>
                                        </div>
                                        <div style="background: var(--color-background); padding: 0.75rem; border-radius: var(--border-radius-1); border-left: 4px solid <?= $status['cor_hex'] ?>; font-size: 0.8rem; color: var(--color-dark); line-height: 1.4;">
                                            <?= nl2br(htmlspecialchars(substr($status['mensagem_template'], 0, 150) . (strlen($status['mensagem_template']) > 150 ? '...' : ''))) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <small style="color: var(--color-info-dark); font-size: 0.75rem; display: block; background: rgba(255, 255, 255, 0.7); padding: 0.5rem; border-radius: 4px;">
                                    <strong>💡 Variáveis:</strong> {nome_cliente}, {numero_pedido}, {valor_total}, {data_pedido}, {status_atual}
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($statusList)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; background: var(--color-white); border-radius: var(--card-border-radius); box-shadow: var(--box-shadow);">
                        <span class="material-symbols-sharp" style="font-size: 4rem; color: var(--color-info-dark); margin-bottom: 1rem; display: block;">account_tree</span>
                        <h3 style="color: var(--color-dark); margin-bottom: 0.5rem;">Nenhum Status Configurado</h3>
                        <p style="color: var(--color-info-dark); margin-bottom: 1.5rem;">Adicione seu primeiro status para começar a gerenciar o fluxo de pedidos.</p>
                        <button onclick="openAddModal()" style="background: var(--color-primary); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: var(--border-radius-2); cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 600;">
                            <span class="material-symbols-sharp">add</span>
                            Adicionar Primeiro Status
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <!----------FINAL MAIN------------>
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

    <style>
        /* Animação para checkboxes */
        .regra-negocio.active .custom-checkbox {
            background: var(--color-primary);
            border-color: var(--color-primary);
        }
        
        .regra-negocio.active .custom-checkbox span {
            color: white !important;
        }
        
        .regra-negocio.active {
            border-color: var(--color-primary);
            background: rgba(255, 0, 204, 0.05);
        }
        
        /* Responsivo para mobile */
        @media (max-width: 768px) {
            #statusModal > div {
                width: 95% !important;
                margin: 1rem !important;
                max-height: calc(100vh - 2rem) !important;
            }
            
            #statusModal > div > div:last-child {
                padding: 1rem !important;
                max-height: calc(100vh - 6rem) !important;
            }
        }
        
        /* Efeito hover para todo o modal */
        #statusModal > div {
            animation: modalSlideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
    </style>

    <!-- Modal para Adicionar/Editar Status -->
    <div id="statusModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: flex-start; padding: 2rem 1rem; box-sizing: border-box; overflow-y: auto;">
        <div style="background: var(--color-white); border-radius: var(--card-border-radius); padding: 0; width: 100%; max-width: 650px; min-height: fit-content; max-height: calc(100vh - 4rem); position: relative; box-shadow: 0 20px 60px rgba(0,0,0,0.2);">
            <!-- Header Fixo -->
            <div style="padding: 2rem 2rem 1rem 2rem; border-bottom: 1px solid var(--color-light); position: sticky; top: 0; background: var(--color-white); z-index: 10; border-radius: var(--card-border-radius) var(--card-border-radius) 0 0;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2 id="modalTitle" style="color: var(--color-dark); margin: 0; font-size: 1.5rem;">Adicionar Novo Status</h2>
                    <button onclick="closeModal()" style="background: var(--color-light); border: none; cursor: pointer; color: var(--color-dark); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease;" onmouseover="this.style.background='var(--color-danger)'; this.style.color='white';" onmouseout="this.style.background='var(--color-light)'; this.style.color='var(--color-dark)';">
                        <span class="material-symbols-sharp" style="font-size: 20px;">close</span>
                    </button>
                </div>
            </div>
            
            <!-- Conteúdo Scrollável -->
            <div style="padding: 1.5rem 2rem 2rem 2rem; overflow-y: auto; max-height: calc(100vh - 8rem);">
                <form id="statusForm" method="POST">
                    <input type="hidden" name="action" value="add_status" id="formAction">
                    <input type="hidden" name="id" value="" id="statusId">

                    <!-- Nome do Status -->
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-weight: 600; color: var(--color-dark); margin-bottom: 0.5rem;">Nome do Status *</label>
                        <input type="text" name="nome" id="statusNome" required style="width: 100%; padding: 0.75rem; border: 2px solid var(--color-light); border-radius: var(--border-radius-1); background: var(--color-white); font-size: 1rem; transition: all 0.3s ease;" placeholder="Ex: Pago, Enviado, Entregue..." onfocus="this.style.borderColor='var(--color-primary)'" onblur="this.style.borderColor='var(--color-light)'">
                    </div>

                    <!-- Cor do Status -->
                    <div style="margin-bottom: 2rem;">
                        <label style="display: block; font-weight: 600; color: var(--color-dark); margin-bottom: 0.5rem;">Cor do Status</label>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <input type="color" name="cor_hex" id="statusCor" value="#ff00d4" style="width: 60px; height: 40px; border: none; border-radius: var(--border-radius-1); cursor: pointer; border: 2px solid var(--color-light);">
                            <span id="corPreview" style="padding: 0.5rem 1rem; background: #ff00d4; color: white; border-radius: var(--border-radius-2); font-size: 0.85rem; font-weight: 600; transition: all 0.3s ease;">Preview</span>
                        </div>
                    </div>

                    <!-- Regras de Negócio -->
                    <div style="margin-bottom: 2rem;">
                        <label style="display: block; font-weight: 600; color: var(--color-dark); margin-bottom: 1rem; font-size: 1.1rem; border-bottom: 2px solid var(--color-primary); padding-bottom: 0.5rem;">
                            <span class="material-symbols-sharp" style="font-size: 18px; vertical-align: middle; margin-right: 0.5rem; color: var(--color-primary);">settings</span>
                            Regras de Negócio
                        </label>
                        <div class="regras-container" style="display: flex; flex-direction: column; gap: 0.75rem; padding: 1rem; background: rgba(255, 0, 204, 0.02); border-radius: var(--border-radius-2); border: 1px solid rgba(255, 0, 204, 0.1);">
                            <!-- Baixar Estoque -->
                            <div class="regra-negocio" data-checkbox="baixaEstoque" style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; padding: 1rem; border-radius: var(--border-radius-1); border: 2px solid var(--color-light); transition: all 0.3s ease; background: var(--color-white); position: relative; min-height: 60px;" onmouseover="this.style.borderColor='var(--color-primary)'; this.style.boxShadow='0 2px 10px rgba(255, 0, 204, 0.1)';" onmouseout="this.style.borderColor='var(--color-light)'; this.style.boxShadow='none';">
                                <div class="custom-checkbox" style="display: flex; align-items: center; justify-content: center; width: 22px; height: 22px; border: 2px solid var(--color-light); border-radius: 4px; transition: all 0.3s ease; background: var(--color-white); flex-shrink: 0; margin-top: 2px;">
                                    <span class="material-symbols-sharp" style="font-size: 16px; color: transparent; transition: all 0.3s ease;">check</span>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 600; color: var(--color-dark); font-size: 0.95rem; margin-bottom: 0.25rem;">Baixar Estoque automaticamente</div>
                                    <small style="color: var(--color-info-dark); font-size: 0.8rem; line-height: 1.3;">Subtrai automaticamente do inventário quando o pedido atingir este status</small>
                                </div>
                                <input type="checkbox" name="baixa_estoque" id="baixaEstoque" style="display: none;">
                            </div>

                            <!-- Bloquear Edição -->
                            <div class="regra-negocio" data-checkbox="bloquearEdicao" style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; padding: 1rem; border-radius: var(--border-radius-1); border: 2px solid var(--color-light); transition: all 0.3s ease; background: var(--color-white); position: relative; min-height: 60px;" onmouseover="this.style.borderColor='var(--color-primary)'; this.style.boxShadow='0 2px 10px rgba(255, 0, 204, 0.1)';" onmouseout="this.style.borderColor='var(--color-light)'; this.style.boxShadow='none';">
                                <div class="custom-checkbox" style="display: flex; align-items: center; justify-content: center; width: 22px; height: 22px; border: 2px solid var(--color-light); border-radius: 4px; transition: all 0.3s ease; background: var(--color-white); flex-shrink: 0; margin-top: 2px;">
                                    <span class="material-symbols-sharp" style="font-size: 16px; color: transparent; transition: all 0.3s ease;">check</span>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 600; color: var(--color-dark); font-size: 0.95rem; margin-bottom: 0.25rem;">Bloquear edição do pedido</div>
                                    <small style="color: var(--color-info-dark); font-size: 0.8rem; line-height: 1.3;">Impede qualquer modificação no pedido após atingir este status</small>
                                </div>
                                <input type="checkbox" name="bloquear_edicao" id="bloquearEdicao" style="display: none;">
                            </div>

                            <!-- Gerar Logística -->
                            <div class="regra-negocio" data-checkbox="gerarLogistica" style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; padding: 1rem; border-radius: var(--border-radius-1); border: 2px solid var(--color-light); transition: all 0.3s ease; background: var(--color-white); position: relative; min-height: 60px;" onmouseover="this.style.borderColor='var(--color-primary)'; this.style.boxShadow='0 2px 10px rgba(255, 0, 204, 0.1)';" onmouseout="this.style.borderColor='var(--color-light)'; this.style.boxShadow='none';">
                                <div class="custom-checkbox" style="display: flex; align-items: center; justify-content: center; width: 22px; height: 22px; border: 2px solid var(--color-light); border-radius: 4px; transition: all 0.3s ease; background: var(--color-white); flex-shrink: 0; margin-top: 2px;">
                                    <span class="material-symbols-sharp" style="font-size: 16px; color: transparent; transition: all 0.3s ease;">check</span>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 600; color: var(--color-dark); font-size: 0.95rem; margin-bottom: 0.25rem;">Gerar logística (Melhor Envio)</div>
                                    <small style="color: var(--color-info-dark); font-size: 0.8rem; line-height: 1.3;">Habilita botões de rastreio e integração com transportadoras</small>
                                </div>
                                <input type="checkbox" name="gerar_logistica" id="gerarLogistica" style="display: none;">
                            </div>

                            <!-- Ativar Notificação -->
                            <div class="regra-negocio" data-checkbox="notificar" style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; padding: 1rem; border-radius: var(--border-radius-1); border: 2px solid var(--color-light); transition: all 0.3s ease; background: var(--color-white); position: relative; min-height: 60px;" onmouseover="this.style.borderColor='var(--color-primary)'; this.style.boxShadow='0 2px 10px rgba(255, 0, 204, 0.1)';" onmouseout="this.style.borderColor='var(--color-light)'; this.style.boxShadow='none';">
                                <div class="custom-checkbox" style="display: flex; align-items: center; justify-content: center; width: 22px; height: 22px; border: 2px solid var(--color-light); border-radius: 4px; transition: all 0.3s ease; background: var(--color-white); flex-shrink: 0; margin-top: 2px;">
                                    <span class="material-symbols-sharp" style="font-size: 16px; color: transparent; transition: all 0.3s ease;">check</span>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 600; color: var(--color-dark); font-size: 0.95rem; margin-bottom: 0.25rem;">Ativar notificação automática</div>
                                    <small style="color: var(--color-info-dark); font-size: 0.8rem; line-height: 1.3;">Envia mensagem automaticamente via WhatsApp/E-mail</small>
                                </div>
                                <input type="checkbox" name="notificar" id="notificar" style="display: none;">
                            </div>

                            <!-- Estornar Estoque -->
                            <div class="regra-negocio" data-checkbox="estornarEstoque" style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; padding: 1rem; border-radius: var(--border-radius-1); border: 2px solid var(--color-light); transition: all 0.3s ease; background: var(--color-white); position: relative; min-height: 60px;" onmouseover="this.style.borderColor='var(--color-primary)'; this.style.boxShadow='0 2px 10px rgba(255, 0, 204, 0.1)';" onmouseout="this.style.borderColor='var(--color-light)'; this.style.boxShadow='none';">
                                <div class="custom-checkbox" style="display: flex; align-items: center; justify-content: center; width: 22px; height: 22px; border: 2px solid var(--color-light); border-radius: 4px; transition: all 0.3s ease; background: var(--color-white); flex-shrink: 0; margin-top: 2px;">
                                    <span class="material-symbols-sharp" style="font-size: 16px; color: transparent; transition: all 0.3s ease;">check</span>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 600; color: var(--color-dark); font-size: 0.95rem; margin-bottom: 0.25rem;">Estornar Estoque</div>
                                    <small style="color: var(--color-info-dark); font-size: 0.8rem; line-height: 1.3;">Se ativado, produtos deste status voltam ao inventário (ex: Devoluções)</small>
                                </div>
                                <input type="checkbox" name="estornar_estoque" id="estornarEstoque" style="display: none;">
                            </div>

                            <!-- Gerar Link de Cobrança -->
                            <div class="regra-negocio" data-checkbox="gerarLinkCobranca" style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; padding: 1rem; border-radius: var(--border-radius-1); border: 2px solid var(--color-light); transition: all 0.3s ease; background: var(--color-white); position: relative; min-height: 60px;" onmouseover="this.style.borderColor='var(--color-primary)'; this.style.boxShadow='0 2px 10px rgba(255, 0, 204, 0.1)';" onmouseout="this.style.borderColor='var(--color-light)'; this.style.boxShadow='none';">
                                <div class="custom-checkbox" style="display: flex; align-items: center; justify-content: center; width: 22px; height: 22px; border: 2px solid var(--color-light); border-radius: 4px; transition: all 0.3s ease; background: var(--color-white); flex-shrink: 0; margin-top: 2px;">
                                    <span class="material-symbols-sharp" style="font-size: 16px; color: transparent; transition: all 0.3s ease;">credit_card</span>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 600; color: var(--color-dark); font-size: 0.95rem; margin-bottom: 0.25rem;">
                                        <span class="material-symbols-sharp" style="font-size: 16px; vertical-align: middle; margin-right: 0.5rem; color: var(--color-success);">credit_card</span>
                                        Gerar Link de Cobrança
                                    </div>
                                    <small style="color: var(--color-info-dark); font-size: 0.8rem; line-height: 1.3;">Se ativado, habilita o shortcode {link_pagamento} no template</small>
                                </div>
                                <input type="checkbox" name="gerar_link_cobranca" id="gerarLinkCobranca" style="display: none;">
                            </div>
                        </div>
                    </div>

                <!-- Prazo de SLA -->
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-weight: 600; color: var(--color-dark); margin-bottom: 0.75rem;">
                        <span class="material-symbols-sharp" style="font-size: 16px; vertical-align: middle; margin-right: 0.5rem; color: var(--color-warning);">schedule</span>
                        Prazo de SLA (Alerta de Atraso)
                    </label>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <input type="number" name="sla_horas" id="slaHoras" min="0" max="720" value="0" style="width: 120px; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); background: var(--color-white); font-size: 1rem; text-align: center;" placeholder="0">
                        <span style="color: var(--color-dark); font-weight: 600;">horas</span>
                        <small style="color: var(--color-info-dark); font-size: 0.8rem; flex: 1;">Definir alerta quando pedido permanecer neste status por mais tempo que o especificado. (0 = desabilitado)</small>
                    </div>
                </div>

                <!-- Gestão de Mensagens -->
                <div id="mensagemTemplateDiv" style="margin-bottom: 1.5rem; display: none;">
                    <div style="background: linear-gradient(135deg, rgba(255, 0, 204, 0.1), rgba(255, 0, 204, 0.05)); border: 2px solid rgba(255, 0, 204, 0.2); border-radius: var(--border-radius-2); padding: 1.5rem; margin-bottom: 1.5rem;">
                        <h3 style="margin: 0 0 1rem 0; color: var(--color-primary); display: flex; align-items: center; gap: 0.5rem;">
                            <span class="material-symbols-sharp" style="font-size: 20px;">message</span>
                            Gestão de Mensagens para este Status
                        </h3>
                        
                        <!-- Mensagem de E-mail -->
                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; font-weight: 600; color: var(--color-dark); margin-bottom: 0.5rem;">
                                <span class="material-symbols-sharp" style="font-size: 16px; vertical-align: middle; margin-right: 0.5rem; color: var(--color-primary);">email</span>
                                Mensagem de E-mail Personalizada
                            </label>
                            <textarea name="mensagem_email" id="mensagemEmail" rows="5" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); background: var(--color-white); font-size: 0.9rem; resize: vertical;" placeholder="Olá {nome_cliente}! Seu pedido #{numero_pedido} no valor de R$ {valor_total} foi atualizado..."></textarea>
                            <div style="margin-top: 0.5rem;">
                                <button type="button" onclick="inserirVariavelEmail('nome_cliente')" style="background: rgba(255, 0, 204, 0.1); border: 1px solid var(--color-primary); color: var(--color-primary); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; margin: 0.25rem 0.25rem 0.25rem 0; cursor: pointer;">{nome_cliente}</button>
                                <button type="button" onclick="inserirVariavelEmail('numero_pedido')" style="background: rgba(255, 0, 204, 0.1); border: 1px solid var(--color-primary); color: var(--color-primary); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; margin: 0.25rem 0.25rem 0.25rem 0; cursor: pointer;">{numero_pedido}</button>
                                <button type="button" onclick="inserirVariavelEmail('valor_total')" style="background: rgba(255, 0, 204, 0.1); border: 1px solid var(--color-primary); color: var(--color-primary); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; margin: 0.25rem 0.25rem 0.25rem 0; cursor: pointer;">{valor_total}</button>
                                <button type="button" onclick="inserirVariavelEmail('data_pedido')" style="background: rgba(255, 0, 204, 0.1); border: 1px solid var(--color-primary); color: var(--color-primary); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; margin: 0.25rem 0.25rem 0.25rem 0; cursor: pointer;">{data_pedido}</button>
                                <button type="button" onclick="inserirVariavelEmail('status_atual')" style="background: rgba(255, 0, 204, 0.1); border: 1px solid var(--color-primary); color: var(--color-primary); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; margin: 0.25rem 0.25rem 0.25rem 0; cursor: pointer;">{status_atual}</button>
                            </div>
                        </div>

                        <!-- Botões de Templates Prontos -->
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; font-weight: 600; color: var(--color-dark); margin-bottom: 0.5rem;">
                                <span class="material-symbols-sharp" style="font-size: 16px; vertical-align: middle; margin-right: 0.5rem; color: var(--color-success);">auto_fix_high</span>
                                Templates Prontos D&Z
                            </label>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <button type="button" onclick="aplicarTemplatePedidoConfirmado()" style="background: var(--color-success); color: white; border: none; padding: 0.5rem 0.75rem; border-radius: var(--border-radius-1); font-size: 0.8rem; cursor: pointer;">Pedido Confirmado</button>
                                <button type="button" onclick="aplicarTemplatePreparando()" style="background: var(--color-warning); color: white; border: none; padding: 0.5rem 0.75rem; border-radius: var(--border-radius-1); font-size: 0.8rem; cursor: pointer;">Preparando Pedido</button>
                                <button type="button" onclick="aplicarTemplateEnviado()" style="background: var(--color-info); color: white; border: none; padding: 0.5rem 0.75rem; border-radius: var(--border-radius-1); font-size: 0.8rem; cursor: pointer;">Pedido Enviado</button>
                                <button type="button" onclick="aplicarTemplateEntregue()" style="background: var(--color-primary); color: white; border: none; padding: 0.5rem 0.75rem; border-radius: var(--border-radius-1); font-size: 0.8rem; cursor: pointer;">Pedido Entregue</button>
                            </div>
                        </div>

                        <small style="color: var(--color-info-dark); font-size: 0.75rem; margin-top: 0.25rem; display: block; background: rgba(255, 255, 255, 0.7); padding: 0.5rem; border-radius: 4px;">
                            <strong>🚀 Variáveis disponíveis:</strong> {nome_cliente}, {numero_pedido}, {valor_total}, {data_pedido}, {status_atual}<br>
                            <strong>💡 Dica:</strong> Clique nos botões das variáveis para inseri-las automaticamente no texto!
                        </small>
                    </div>
                </div>

                    <!-- Botões -->
                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; padding-top: 1.5rem; border-top: 2px solid var(--color-light);">
                        <button type="button" onclick="closeModal()" style="background: var(--color-light); color: var(--color-dark); border: none; padding: 0.75rem 1.5rem; border-radius: var(--border-radius-2); cursor: pointer; font-weight: 600; transition: all 0.3s ease; display: flex; align-items: center; gap: 0.5rem;" onmouseover="this.style.background='var(--color-danger)'; this.style.color='white';" onmouseout="this.style.background='var(--color-light)'; this.style.color='var(--color-dark)';">
                            <span class="material-symbols-sharp" style="font-size: 18px;">cancel</span>
                            Cancelar
                        </button>
                        <button type="submit" style="background: var(--color-primary); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: var(--border-radius-2); cursor: pointer; font-weight: 600; transition: all 0.3s ease; display: flex; align-items: center; gap: 0.5rem;" onmouseover="this.style.transform='scale(1.05)';" onmouseout="this.style.transform='scale(1)';">
                            <span class="material-symbols-sharp" style="font-size: 18px;">check</span>
                            <span id="submitText">Adicionar Status</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../js/dashboard.js"></script>
    <script>
        // Aplicar tema salvo e configurar event listeners
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 DOM carregado, inicializando Gestão de Fluxo...');
            
            const savedTheme = localStorage.getItem('darkTheme');
            if (savedTheme === 'true') {
                document.body.classList.add('dark-theme-variables');
            }
            
            // Configurar event listeners para botões de editar
            const editButtons = document.querySelectorAll('.edit-status-btn');
            console.log(`📝 Encontrados ${editButtons.length} botões de editar`);
            
            editButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('🎯 Botão de editar clicado!', this);
                    editStatus(this);
                });
            });

            // Configurar event listeners para checkboxes customizados
            setupCustomCheckboxes();
            
            // Inicializar toggle na página load
            toggleMensagemTemplate();
        });

        // Função para configurar os checkboxes customizados
        function setupCustomCheckboxes() {
            const regraNegocio = document.querySelectorAll('.regra-negocio');
            
            regraNegocio.forEach(regra => {
                regra.addEventListener('click', function(e) {
                    e.preventDefault();
                    const checkboxId = this.dataset.checkbox;
                    const checkbox = document.getElementById(checkboxId);
                    const customCheckbox = this.querySelector('.custom-checkbox');
                    const checkIcon = customCheckbox.querySelector('span');
                    
                    if (checkbox) {
                        // Toggle do checkbox
                        checkbox.checked = !checkbox.checked;
                        
                        console.log(`🔄 ${checkboxId} alterado para: ${checkbox.checked}`);
                        
                        // Atualizar visual
                        updateCustomCheckboxVisual(this, checkbox.checked);
                        
                        // Disparar evento change para o toggle de mensagem
                        const changeEvent = new Event('change', { bubbles: true });
                        checkbox.dispatchEvent(changeEvent);
                        
                        // Se for o checkbox de notificação, atualizar template
                        if (checkboxId === 'notificar') {
                            toggleMensagemTemplate();
                        }
                    }
                });
            });
        }

        // Função para atualizar o visual dos checkboxes customizados
        function updateCustomCheckboxVisual(regraElement, isChecked) {
            console.log(`🎨 updateCustomCheckboxVisual chamado para:`, regraElement.dataset.checkbox, isChecked);
            
            const customCheckbox = regraElement.querySelector('.custom-checkbox');
            const checkIcon = customCheckbox.querySelector('span');
            
            console.log(`🔍 Elementos encontrados - customCheckbox:`, customCheckbox, `checkIcon:`, checkIcon);
            
            if (isChecked) {
                // Estado marcado - rosa
                regraElement.style.borderColor = 'var(--color-primary)';
                regraElement.style.backgroundColor = 'rgba(255, 0, 212, 0.05)';
                customCheckbox.style.backgroundColor = 'var(--color-primary)';
                customCheckbox.style.borderColor = 'var(--color-primary)';
                checkIcon.style.color = 'white';
                checkIcon.style.fontWeight = 'bold';
                console.log(`✅ Visual ativado (rosa) para ${regraElement.dataset.checkbox}`);
            } else {
                // Estado desmarcado - padrão
                regraElement.style.borderColor = 'var(--color-info-light)';
                regraElement.style.backgroundColor = 'var(--color-white)';
                customCheckbox.style.backgroundColor = 'var(--color-white)';
                customCheckbox.style.borderColor = 'var(--color-info-light)';
                checkIcon.style.color = 'transparent';
                checkIcon.style.fontWeight = 'normal';
                console.log(`⚪ Visual desativado (padrão) para ${regraElement.dataset.checkbox}`);
            }
        }

        // Funções do Modal
        function openAddModal() {
            console.log('➕ Abrindo modal para adicionar');
            resetModalForm();
            document.getElementById('modalTitle').textContent = 'Adicionar Novo Status';
            document.getElementById('formAction').value = 'add_status';
            document.getElementById('statusId').value = '';
            document.getElementById('submitText').textContent = 'Adicionar Status';
            document.getElementById('statusModal').style.display = 'flex';
        }

        function editStatus(button) {
            console.log('🔧 editStatus iniciado', button);
            
            try {
                // Reset do formulário primeiro e aguardar
                resetModalForm();
                
                // Aguardar um pouco para garantir que o reset foi aplicado
                setTimeout(() => {
                    // Pegar dados dos data attributes
                    const statusData = {
                        id: button.getAttribute('data-id'),
                        nome: button.getAttribute('data-nome'),
                        cor: button.getAttribute('data-cor') || '#ff007f', // Validação de cor padrão
                        baixaEstoque: button.getAttribute('data-baixa-estoque') === '1',
                        bloquearEdicao: button.getAttribute('data-bloquear-edicao') === '1',
                        gerarLogistica: button.getAttribute('data-gerar-logistica') === '1',
                        notificar: button.getAttribute('data-notificar') === '1',
                        estornarEstoque: button.getAttribute('data-estornar-estoque') === '1',
                        gerarLinkCobranca: button.getAttribute('data-gerar-link-cobranca') === '1',
                        slaHoras: parseInt(button.getAttribute('data-sla-horas')) || 0,
                        template: button.getAttribute('data-template') || '',
                        mensagemEmail: button.getAttribute('data-mensagem-email') || ''
                    };
                    
                    console.log('📊 Dados do status carregados:', statusData);
                    
                    // Configurar modal para edição
                    document.getElementById('modalTitle').textContent = 'Editar Status';
                    document.getElementById('formAction').value = 'update_status';
                    document.getElementById('statusId').value = statusData.id;
                    document.getElementById('submitText').textContent = 'Atualizar Status';
                    
                    // Preencher campos básicos com validação de cor
                    document.getElementById('statusNome').value = statusData.nome;
                    const corInput = document.getElementById('statusCor');
                    const corValida = statusData.cor && statusData.cor.match(/^#[0-9A-Fa-f]{6}$/) ? statusData.cor : '#ff007f';
                    corInput.value = corValida;
                    document.getElementById('slaHoras').value = statusData.slaHoras;
                    
                    // Aguardar mais um pouco antes de configurar checkboxes
                    setTimeout(() => {
                        console.log('🔄 Aplicando configurações dos checkboxes...');
                        
                        // Configurar checkboxes com verificação robusta - sincronia de toggles garantida
                        setCheckboxValue('baixaEstoque', statusData.baixaEstoque);
                        setCheckboxValue('bloquearEdicao', statusData.bloquearEdicao);
                        setCheckboxValue('gerarLogistica', statusData.gerarLogistica);
                        setCheckboxValue('notificar', statusData.notificar);
                        setCheckboxValue('estornarEstoque', statusData.estornarEstoque);
                        setCheckboxValue('gerarLinkCobranca', statusData.gerarLinkCobranca);
                        
                        // Preencher campos de template
                        const templateField = document.getElementById('mensagemTemplate');
                        if (templateField) {
                            templateField.value = statusData.template;
                        }
                        
                        const emailField = document.getElementById('mensagemEmail');
                        if (emailField) {
                            emailField.value = statusData.mensagemEmail;
                        }
                        
                        // Atualizar preview e toggle
                        updateColorPreview(statusData.cor, statusData.nome);
                        
                        // Aguardar antes de fazer toggle
                        setTimeout(() => {
                            toggleMensagemTemplate();
                        }, 100);
                        
                        console.log('✅ Todos os campos configurados');
                        
                    }, 100);
                    
                    // Mostrar modal
                    document.getElementById('statusModal').style.display = 'flex';
                    
                }, 100);
                
            } catch (error) {
                console.error('❌ Erro ao editar status:', error);
                alert('Erro ao carregar dados do status. Tente novamente.');
            }
        }

        // Função auxiliar para configurar checkboxes de forma robusta
        function setCheckboxValue(checkboxId, value) {
            const checkbox = document.getElementById(checkboxId);
            if (checkbox) {
                // Forçar o valor antes de marcar
                checkbox.checked = false;
                
                // Aguardar um pouco e definir o valor correto
                setTimeout(() => {
                    checkbox.checked = value;
                    console.log(`✓ ${checkboxId}: ${value} (${checkbox.checked})`);
                    
                    // Encontrar o elemento de regra correspondente
                    const regraElement = document.querySelector(`[data-checkbox="${checkboxId}"]`);
                    if (regraElement) {
                        updateCustomCheckboxVisual(regraElement, value);
                        console.log(`🎨 Visual atualizado para ${checkboxId}: ${value}`);
                    }
                    
                    // Disparar evento para garantir que outras funcionalidades sejam ativadas
                    const changeEvent = new Event('change', { bubbles: true });
                    checkbox.dispatchEvent(changeEvent);
                    
                }, 50);
                
            } else {
                console.warn(`⚠️ Checkbox ${checkboxId} não encontrado`);
            }
        }

        // Função para limpar/resetar o formulário do modal
        function resetModalForm() {
            console.log('🧹 Limpando formulário do modal');
            
            // Limpar campos básicos
            document.getElementById('statusNome').value = '';
            document.getElementById('statusCor').value = '#ff00d4';
            document.getElementById('statusId').value = '';
            
            // Desmarcar todos os checkboxes com força
            const checkboxes = ['baixaEstoque', 'bloquearEdicao', 'gerarLogistica', 'notificar', 'estornarEstoque', 'gerarLinkCobranca'];
            checkboxes.forEach(id => {
                const checkbox = document.getElementById(id);
                if (checkbox) {
                    // Forçar desmarcação
                    checkbox.checked = false;
                    checkbox.removeAttribute('checked');
                    
                    // Resetar visual do checkbox customizado
                    const regraElement = document.querySelector(`[data-checkbox="${id}"]`);
                    if (regraElement) {
                        updateCustomCheckboxVisual(regraElement, false);
                    }
                    
                    console.log(`🧹 ${id} limpo`);
                }
            });
            
            // Limpar template, email e SLA com verificações de segurança
            const templateField = document.getElementById('mensagemTemplate');
            if (templateField) {
                templateField.value = '';
            }
            
            const emailField = document.getElementById('mensagemEmail');
            if (emailField) {
                emailField.value = '';
            }
            
            const slaField = document.getElementById('slaHoras');
            if (slaField) {
                slaField.value = '0';
            }
            
            // Reset do preview
            updateColorPreview('#ff00d4', 'Preview');
            
            // Esconder div de template
            const templateDiv = document.getElementById('mensagemTemplateDiv');
            if (templateDiv) {
                templateDiv.style.display = 'none';
            }
            
            console.log('✨ Reset do modal concluído');
        }

        function deleteStatus(id, nome) {
            if (confirm(`Tem certeza que deseja excluir o status "${nome}"?\n\nEsta ação não pode ser desfeita.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.name = 'action';
                actionInput.value = 'delete_status';
                
                const idInput = document.createElement('input');
                idInput.name = 'id';
                idInput.value = id;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function closeModal() {
            console.log('🚪 Fechando modal');
            resetModalForm();
            document.getElementById('statusModal').style.display = 'none';
        }

        // Fechar modal clicando no fundo
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('statusModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal();
                    }
                });
            }
        });

        // Atualizar preview da cor em tempo real
        document.addEventListener('DOMContentLoaded', function() {
            const statusCor = document.getElementById('statusCor');
            const statusNome = document.getElementById('statusNome');
            
            if (statusCor) {
                statusCor.addEventListener('input', function() {
                    const cor = this.value;
                    const nome = statusNome ? statusNome.value || 'Preview' : 'Preview';
                    updateColorPreview(cor, nome);
                });
            }
            
            if (statusNome) {
                statusNome.addEventListener('input', function() {
                    const nome = this.value || 'Preview';
                    const cor = statusCor ? statusCor.value : '#ff00d4';
                    updateColorPreview(cor, nome);
                });
            }
        });

        function updateColorPreview(cor, nome) {
            const preview = document.getElementById('corPreview');
            if (preview) {
                // Validação de cor para evitar erros de formato hexadecimal
                const corValida = cor && cor.match(/^#[0-9A-Fa-f]{6}$/) ? cor : '#ff007f';
                preview.style.background = corValida;
                preview.textContent = nome || 'Preview';
            }
        }

        // Toggle do template de mensagem com melhor controle
        function toggleMensagemTemplate() {
            const checkbox = document.getElementById('notificar');
            const div = document.getElementById('mensagemTemplateDiv');
            
            if (checkbox && div) {
                const shouldShow = checkbox.checked;
                div.style.display = shouldShow ? 'block' : 'none';
                console.log(`📧 Template de mensagem: ${shouldShow ? 'visível' : 'oculto'}`);
            }
        }

        // Funções para inserir variáveis no campo de email
        function inserirVariavelEmail(variavel) {
            const emailField = document.getElementById('mensagemEmail');
            if (emailField) {
                const cursorPos = emailField.selectionStart;
                const textBefore = emailField.value.substring(0, cursorPos);
                const textAfter = emailField.value.substring(emailField.selectionEnd);
                const novoTexto = textBefore + '{' + variavel + '}' + textAfter;
                
                emailField.value = novoTexto;
                emailField.focus();
                emailField.setSelectionRange(cursorPos + variavel.length + 2, cursorPos + variavel.length + 2);
            }
        }

        // Templates prontos da D&Z
        function aplicarTemplatePedidoConfirmado() {
            const emailField = document.getElementById('mensagemEmail');
            if (emailField) {
                emailField.value = "🎉 Ótimas notícias, {nome_cliente}!\n\nSeu pedido #{numero_pedido} no valor de R$ {valor_total} foi confirmado e já está sendo processado!\n\n✨ Em breve você receberá mais atualizações sobre o andamento.\n\n💎 Obrigado por escolher a D&Z - sua beleza é nossa inspiração!\n\n#DZNails #UnhasPerfeitas";
            }
        }

        function aplicarTemplatePreparando() {
            const emailField = document.getElementById('mensagemEmail');
            if (emailField) {
                emailField.value = "💅 {nome_cliente}, estamos preparando sua encomenda!\n\nSeu pedido #{numero_pedido} está sendo cuidadosamente separado e preparado para envio.\n\n🎨 Cada item está sendo verificado para garantir a qualidade D&Z que você merece!\n\n⏰ Logo logo você receberá o código de rastreamento.\n\n✨ D&Z - Transformando sonhos em realidade!";
            }
        }

        function aplicarTemplateEnviado() {
            const emailField = document.getElementById('mensagemEmail');
            if (emailField) {
                emailField.value = "🚚 {nome_cliente}, seu pedido saiu para entrega!\n\nPedido #{numero_pedido} foi postado e está a caminho! 📦\n\n🔍 Acompanhe sua encomenda pelos Correios.\n\n⭐ Em breve você estará arrasando com os produtos D&Z!\n\n💖 Ansiosas para ver o resultado final!";
            }
        }

        function aplicarTemplateEntregue() {
            const emailField = document.getElementById('mensagemEmail');
            if (emailField) {
                emailField.value = "🎊 Parabéns {nome_cliente}!\n\nSeu pedido #{numero_pedido} foi entregue com sucesso! ✅\n\n💅 Agora é só arrasar com seus novos produtos D&Z!\n\n📸 Não esqueça de nos marcar nas suas fotos - adoramos ver vocês arrasando!\n\n⭐ Se possível, deixe sua avaliação. Isso nos ajuda muito!\n\n💎 D&Z - Sempre com você!";
            }
        }

        // Event listener para o checkbox de notificação
        document.addEventListener('DOMContentLoaded', function() {
            const notificarCheckbox = document.getElementById('notificar');
            if (notificarCheckbox) {
                notificarCheckbox.addEventListener('change', toggleMensagemTemplate);
            }
            
            // Adicionar event listeners para todas as regras de negócio
            initRegrasNegocio();
        });
        
        // Função para inicializar regras de negócio
        function initRegrasNegocio() {
            const regras = document.querySelectorAll('.regra-negocio');
            
            regras.forEach(regra => {
                // Adicionar event listener para clique na regra
                regra.addEventListener('click', function() {
                    const checkboxId = this.getAttribute('data-checkbox');
                    if (checkboxId) {
                        toggleCheckbox(checkboxId, this);
                    }
                });
                
                // Verificar estado inicial
                const checkboxId = regra.getAttribute('data-checkbox');
                if (checkboxId) {
                    const checkbox = document.getElementById(checkboxId);
                    if (checkbox && checkbox.checked) {
                        regra.classList.add('active');
                    }
                }
            });
        }
        
        // Função para toggle de checkbox
        function toggleCheckbox(checkboxId, regraElement) {
            const checkbox = document.getElementById(checkboxId);
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                updateCustomCheckboxVisual(regraElement, checkbox.checked);
                
                // Se for notificação, toggle do template
                if (checkboxId === 'notificar') {
                    toggleMensagemTemplate();
                }
            }
        }
        
        // Função para atualizar visual do checkbox customizado
        function updateCustomCheckboxVisual(regraElement, checked) {
            if (checked) {
                regraElement.classList.add('active');
            } else {
                regraElement.classList.remove('active');
            }
        }
    </script>
</body>
</html>