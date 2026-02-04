<?php
session_start();
// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../../../PHP/login.php');
    exit();
}

require_once '../../../PHP/conexao.php';

// Funcionalidade de exportar CSV das vendedoras
if (isset($_GET['exportar_vendedoras']) && $_GET['exportar_vendedoras'] === 'csv') {
    // Query para buscar todas as vendedoras com estatísticas
    $query = "
        SELECT 
            v.id,
            v.nome,
            v.whatsapp,
            v.email,
            v.created_at as data_cadastro,
            COUNT(lr.id) as total_leads,
            SUM(CASE WHEN lr.interesse = 'alta' THEN 1 ELSE 0 END) as leads_alta_prioridade,
            SUM(CASE WHEN lr.interesse = 'media' THEN 1 ELSE 0 END) as leads_media_prioridade,
            SUM(CASE WHEN lr.interesse = 'baixa' THEN 1 ELSE 0 END) as leads_baixa_prioridade
        FROM vendedoras v 
        LEFT JOIN leads_revendedores lr ON v.id = lr.vendedora_id 
        GROUP BY v.id, v.nome, v.whatsapp, v.email, v.created_at
        ORDER BY v.nome ASC
    ";
    
    $result = mysqli_query($conexao, $query);
    
    if ($result) {
        // Definir headers para download do arquivo CSV
        $filename = 'vendedoras_relatorio_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        
        // Abrir output stream
        $output = fopen('php://output', 'w');
        
        // Adicionar BOM para UTF-8 (para Excel abrir corretamente)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers do CSV
        fputcsv($output, [
            'ID',
            'Nome da Vendedora',
            'WhatsApp',
            'Email',
            'Data de Cadastro',
            'Total de Leads',
            'Leads Alta Prioridade',
            'Leads Média Prioridade',
            'Leads Baixa Prioridade'
        ]);
        
        // Dados das vendedoras
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['id'],
                $row['nome'],
                $row['whatsapp'],
                $row['email'],
                date('d/m/Y H:i:s', strtotime($row['data_cadastro'])),
                $row['total_leads'],
                $row['leads_alta_prioridade'],
                $row['leads_media_prioridade'],
                $row['leads_baixa_prioridade']
            ]);
        }
        
        fclose($output);
        exit();
    }
}

// Processar ações de vendedores
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_vendedor') {
        $nome = trim($_POST['nome']);
        $whatsapp = preg_replace('/[^0-9]/', '', $_POST['whatsapp']);
        $email = trim($_POST['email']);
        
        if ($nome && $whatsapp) {
            $sql = "INSERT INTO vendedoras (nome, whatsapp, email) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, "sss", $nome, $whatsapp, $email);
            
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "Vendedor adicionado com sucesso!";
            } else {
                $error_msg = "Erro ao adicionar vendedor: " . mysqli_error($conexao);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_msg = "Nome e WhatsApp são obrigatórios!";
        }
    }
    elseif ($action === 'edit_vendedor') {
        $id = intval($_POST['id']);
        $nome = trim($_POST['nome']);
        $whatsapp = preg_replace('/[^0-9]/', '', $_POST['whatsapp']);
        $email = trim($_POST['email']);
        
        $sql = "UPDATE vendedoras SET nome = ?, whatsapp = ?, email = ? WHERE id = ?";
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($stmt, "sssi", $nome, $whatsapp, $email, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Vendedor atualizado com sucesso!";
        } else {
            $error_msg = "Erro ao atualizar vendedor: " . mysqli_error($conexao);
        }
        mysqli_stmt_close($stmt);
    }
    elseif ($action === 'delete_vendedor') {
        $id = intval($_POST['id']);
        
        // Primeiro deletar todos os leads associados
        $delete_leads = "DELETE FROM leads_revendedores WHERE vendedora_id = ?";
        $stmt_leads = mysqli_prepare($conexao, $delete_leads);
        mysqli_stmt_bind_param($stmt_leads, "i", $id);
        mysqli_stmt_execute($stmt_leads);
        
        // Depois deletar o vendedor
        $sql = "DELETE FROM vendedoras WHERE id = ?";
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Vendedor excluído com sucesso!";
        } else {
            $error_msg = "Erro ao excluir vendedor: " . mysqli_error($conexao);
        }
        
        mysqli_stmt_close($stmt_leads);
        mysqli_stmt_close($stmt);
    }
}

// Criar tabela de vendedores se não existir
$create_table = "
    CREATE TABLE IF NOT EXISTS vendedoras (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nome VARCHAR(255) NOT NULL,
        whatsapp VARCHAR(20),
        email VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
";
mysqli_query($conexao, $create_table);

// Garantir que existe pelo menos um vendedor
$check_vendedores = mysqli_query($conexao, "SELECT COUNT(*) as total FROM vendedoras");
$total_vendedores = mysqli_fetch_assoc($check_vendedores)['total'];

if ($total_vendedores == 0) {
    mysqli_query($conexao, "INSERT INTO vendedoras (nome, whatsapp, email) VALUES ('Lucas Chacon', '21985136806', 'lucaschacon79@gmail.com')");
}

// Buscar vendedores
$vendedores_sql = "
    SELECT *, 
           (SELECT COUNT(*) FROM leads_revendedores WHERE vendedora_id = vendedoras.id) as total_leads
    FROM vendedoras 
    ORDER BY nome
";
$vendedores_result = mysqli_query($conexao, $vendedores_sql);
$vendedores = [];
if ($vendedores_result) {
    while ($row = mysqli_fetch_assoc($vendedores_result)) {
        $vendedores[] = $row;
    }
}

$total_vendedores_ativas = count($vendedores);

// Calcular mensagens não lidas
require_once '../sistema.php';
global $conexao;
$nao_lidas = 0;
try {
    $result = $conexao->query("SELECT COUNT(*) as total FROM mensagens WHERE lida = FALSE AND remetente != 'admin'");
    $nao_lidas = $result ? $result->fetch_assoc()['total'] : 0;
} catch (Exception $e) {
    error_log("Erro ao contar mensagens: " . $e->getMessage());
}

// Buscar todos os administradores do banco (apenas os 3 que existem)
$admins = [];
try {
    $result = $conexao->query("SELECT id, nome, email, created_at FROM teste_dz ORDER BY created_at DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Corrigir problemas de codificação no nome
            $nome = $row['nome'];
            if (strpos($nome, '?') !== false) {
                $nome = str_replace('?', 'ã', $nome); // Corrige Jo?o Silva para João Silva
            }
            $row['nome'] = $nome;
            $admins[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Erro ao buscar admins: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../css/dashboard.css" />
    <link rel="stylesheet" href="../../css/dashboard-sections.css" />
    <link
      href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp"
      rel="stylesheet"
    />
    <title>Vendedores - Dashboard D&Z</title>
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
                    <span class="material-symbols-sharp">mail</span>
                    <h3>Mensagens</h3>
                    <span class="message-count"><?php echo $nao_lidas; ?></span>
                </a>

                <a href="products.php">
                    <span class="material-symbols-sharp">inventory</span>
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
                      <span class="material-symbols-sharp">settings</span>
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

                <a href="gerenciar-vendedoras.php" class="active">
                    <span class="material-symbols-sharp">support_agent</span>
                    <h3>Vendedores</h3>
                </a>

                <a href="../../../PHP/logout.php">
                    <span class="material-symbols-sharp">logout</span>
                    <h3>Sair</h3>
                </a>
            </div>
        </aside>

        <!----------FINAL ASIDE------------>
        <main>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h1 style="margin: 0;">Vendedores</h1>
                <a href="?exportar_vendedoras=csv" style="
                    background: linear-gradient(135deg, #28a745, #20c997);
                    color: white;
                    padding: 0.75rem 1.5rem;
                    border-radius: var(--card-border-radius);
                    text-decoration: none;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    font-weight: 500;
                    transition: all 0.3s ease;
                    box-shadow: 0 4px 6px -1px rgba(40, 167, 69, 0.1);
                    border: none;
                    font-size: 0.9rem;
                " onmouseover="
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 8px 15px -3px rgba(40, 167, 69, 0.3)';
                " onmouseout="
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '0 4px 6px -1px rgba(40, 167, 69, 0.1)';
                ">
                    <span class="material-symbols-sharp" style="font-size: 1rem;">download</span>
                    Exportar Vendedoras
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

            <!-- Tabela de Vendedores -->
            <div class="recent-orders">
                <!-- Modal de Edição -->
                <div id="edit-vendedor-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
                    <div style="background: var(--color-white); border-radius: var(--card-border-radius); padding: 2rem; width: 90%; max-width: 500px; position: relative; box-shadow: 0 20px 25px -5px rgba(255, 20, 147, 0.15), 0 10px 10px -5px rgba(255, 20, 147, 0.1); border: 2px solid rgba(255, 20, 147, 0.1);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                            <h3 style="margin: 0; color: #ff1493; font-size: 1.25rem; font-weight: 600;">Editar Vendedor</h3>
                            <button onclick="cancelEditVendedor()" style="background: transparent; border: none; color: var(--color-info-dark); cursor: pointer; padding: 0.25rem; border-radius: var(--border-radius-1); transition: all 0.2s;" onmouseover="this.style.background='rgba(255, 20, 147, 0.1)'; this.style.color='#ff1493'" onmouseout="this.style.background='transparent'; this.style.color='var(--color-info-dark)'">
                                <span class="material-symbols-sharp" style="font-size: 1.25rem;">close</span>
                            </button>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="edit_vendedor">
                            <input type="hidden" name="id" id="edit_vendedor_id">
                            
                            <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem;">
                                <div>
                                    <label style="display: block; font-weight: 500; color: var(--color-dark); margin-bottom: 0.5rem; font-size: 0.875rem;">Nome Completo</label>
                                    <input type="text" name="nome" id="edit_vendedor_nome" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); font-size: 0.875rem; transition: all 0.2s; background: var(--color-white);" required onfocus="this.style.borderColor='#ff1493'; this.style.boxShadow='0 0 0 3px rgba(255, 20, 147, 0.1)'" onblur="this.style.borderColor='var(--color-info-light)'; this.style.boxShadow='none'">
                                </div>
                                
                                <div>
                                    <label style="display: block; font-weight: 500; color: var(--color-dark); margin-bottom: 0.5rem; font-size: 0.875rem;">WhatsApp</label>
                                    <input type="text" name="whatsapp" id="edit_vendedor_whatsapp" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); font-size: 0.875rem; transition: all 0.2s; background: var(--color-white);" required onfocus="this.style.borderColor='#ff1493'; this.style.boxShadow='0 0 0 3px rgba(255, 20, 147, 0.1)'" onblur="this.style.borderColor='var(--color-info-light)'; this.style.boxShadow='none'">
                                </div>
                                
                                <div>
                                    <label style="display: block; font-weight: 500; color: var(--color-dark); margin-bottom: 0.5rem; font-size: 0.875rem;">Email</label>
                                    <input type="email" name="email" id="edit_vendedor_email" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); font-size: 0.875rem; transition: all 0.2s; background: var(--color-white);" onfocus="this.style.borderColor='#ff1493'; this.style.boxShadow='0 0 0 3px rgba(255, 20, 147, 0.1)'" onblur="this.style.borderColor='var(--color-info-light)'; this.style.boxShadow='none'">
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                                <button type="button" onclick="cancelEditVendedor()" style="background: transparent; color: var(--color-dark-variant); border: 1px solid var(--color-info-light); padding: 0.625rem 1rem; border-radius: var(--border-radius-1); font-weight: 500; cursor: pointer; font-size: 0.875rem; transition: all 0.2s;" onmouseover="this.style.background='var(--color-light)'" onmouseout="this.style.background='transparent'">Cancelar</button>
                                <button type="submit" style="background: #ff1493; color: white; padding: 0.625rem 1rem; border: none; border-radius: var(--border-radius-1); font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.375rem; font-size: 0.875rem; transition: all 0.2s;" onmouseover="this.style.background='#e91e63'" onmouseout="this.style.background='#ff1493'">
                                    <span class="material-symbols-sharp" style="font-size: 1rem;">save</span>
                                    Salvar Alterações
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 1rem;">Nome do Vendedor</th>
                            <th style="text-align: center; padding: 1rem;">WhatsApp</th>
                            <th style="text-align: center; padding: 1rem;">Email</th>
                            <th style="text-align: center; padding: 1rem;">Leads</th>
                            <th style="text-align: center; padding: 1rem;">Status</th>
                            <th style="text-align: center; padding: 1rem;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vendedores)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 3rem 2rem; color: var(--color-info-dark);">
                                <span class="material-symbols-sharp" style="font-size: 4rem; display: block; margin-bottom: 1rem; color: var(--color-info-light);">group</span>
                                <h3 style="margin-bottom: 0.5rem; color: var(--color-dark);">Nenhum vendedor cadastrado</h3>
                                <p style="color: var(--color-info-dark);">Adicione seu primeiro vendedor usando o formulário ao lado</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($vendedores as $vendedor): ?>
                            <tr>
                                <td style="padding: 1rem; font-weight: 500;"><?= htmlspecialchars($vendedor['nome']) ?></td>
                                <td style="padding: 1rem; text-align: center;"><?= $vendedor['whatsapp'] ?></td>
                                <td style="padding: 1rem; text-align: center;"><?= htmlspecialchars($vendedor['email']) ?></td>
                                <td style="padding: 1rem; text-align: center;">
                                    <span style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; padding: 0.125rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 500;">
                                        <?= $vendedor['total_leads'] ?? 0 ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; text-align: center;">
                                    <span style="display: flex; align-items: center; justify-content: center; gap: 0.25rem; background: rgba(34, 197, 94, 0.1); color: #22c55e; padding: 0.125rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 500;">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #22c55e;"></span>
                                        Ativo
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                        <button onclick="editVendedorInline(<?= htmlspecialchars(json_encode($vendedor)) ?>)" 
                                                style="background: transparent; color: #6b7280; border: none; padding: 0.25rem; cursor: pointer; display: flex; align-items: center; transition: all 0.2s; border-radius: 0.25rem;" 
                                                title="Editar" onmouseover="this.style.background='#f3f4f6'; this.style.color='#f59e0b'" onmouseout="this.style.background='transparent'; this.style.color='#6b7280'">
                                            <span class="material-symbols-sharp" style="font-size: 1.125rem;">edit</span>
                                        </button>
                                        <button onclick="deleteVendedorInline(<?= $vendedor['id'] ?>, '<?= htmlspecialchars($vendedor['nome']) ?>')" 
                                                style="background: transparent; color: #6b7280; border: none; padding: 0.25rem; cursor: pointer; display: flex; align-items: center; transition: all 0.2s; border-radius: 0.25rem;" 
                                                title="Excluir" onmouseover="this.style.background='#fef2f2'; this.style.color='#ef4444'" onmouseout="this.style.background='transparent'; this.style.color='#6b7280'">
                                            <span class="material-symbols-sharp" style="font-size: 1.125rem;">delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
        <!--------------------------------------------FINAL MAIN-------------------------------------->

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
            <div class="recent-updates">
                <h2>Últimas Atualizações</h2>
                <div class="updates">
                    <?php if (!empty($admins)): ?>
                        <?php foreach ($admins as $admin): ?>
                            <div class="update">
                                <div class="profile-photo">
                                    <img src="../../../assets/images/logo.png" alt="" />
                                </div>
                                <div class="message">
                                    <p>
                                        <b><?php echo htmlspecialchars($admin['nome']); ?></b> acessou o sistema administrativo
                                    </p>
                                    <small class="text-muted">
                                        <?php 
                                            $data = new DateTime($admin['created_at']);
                                            $agora = new DateTime();
                                            $diferenca = $agora->diff($data);
                                            
                                            if ($diferenca->days > 0) {
                                                echo $diferenca->days . " dias atrás";
                                            } elseif ($diferenca->h > 0) {
                                                echo $diferenca->h . " horas atrás";
                                            } else {
                                                echo "Agora mesmo";
                                            }
                                        ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!------------------------FINAL UPDATES----------------------->
            <div class="sales-analytics">
                <h2>Novo Vendedor</h2>
                
                <form method="POST" style="background: var(--color-white); padding: var(--card-padding); border-radius: var(--card-border-radius); border: 1px solid var(--color-info-light);">
                    <input type="hidden" name="action" value="add_vendedor">
                    
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-weight: 600; color: var(--color-dark); margin-bottom: 0.5rem;">Nome Completo</label>
                        <input type="text" name="nome" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); background: var(--color-white);" placeholder="Ex: João Silva" required>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-weight: 600; color: var(--color-dark); margin-bottom: 0.5rem;">WhatsApp</label>
                        <input type="text" name="whatsapp" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); background: var(--color-white);" placeholder="11999999999" required>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-weight: 600; color: var(--color-dark); margin-bottom: 0.5rem;">Email</label>
                        <input type="email" name="email" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); background: var(--color-white);" placeholder="joao@exemplo.com">
                    </div>
                    
                    <button type="submit" style="width: 100%; background: #ff1493; color: white; padding: 0.75rem; border: none; border-radius: 0.5rem; font-weight: 500; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.375rem; font-size: 0.875rem; transition: all 0.2s;" onmouseover="this.style.background='#e91e63'" onmouseout="this.style.background='#ff1493'">
                        <span class="material-symbols-sharp" style="font-size: 1rem;">add</span>
                        Adicionar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="../../js/dashboard.js"></script>

    <script>
        // Funções para gerenciar vendedores
        function editVendedorInline(vendedor) {
            const modal = document.getElementById('edit-vendedor-modal');
            modal.style.display = 'flex';
            
            document.getElementById('edit_vendedor_id').value = vendedor.id;
            document.getElementById('edit_vendedor_nome').value = vendedor.nome;
            document.getElementById('edit_vendedor_whatsapp').value = vendedor.whatsapp;
            document.getElementById('edit_vendedor_email').value = vendedor.email || '';
            
            // Foco no primeiro campo
            setTimeout(() => {
                document.getElementById('edit_vendedor_nome').focus();
            }, 100);
        }
        
        function cancelEditVendedor() {
            const modal = document.getElementById('edit-vendedor-modal');
            modal.style.display = 'none';
        }
        
        // Fechar modal clicando fora dele
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('edit-vendedor-modal');
            if (event.target === modal) {
                cancelEditVendedor();
            }
        });
        
        // Fechar modal com ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('edit-vendedor-modal');
                if (modal.style.display === 'flex') {
                    cancelEditVendedor();
                }
            }
        });
        
        function deleteVendedorInline(id, nome) {
            if (confirm(`Tem certeza que deseja excluir o vendedor "${nome}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_vendedor">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Garantir que o tema dark funcione
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('darkTheme');
            if (savedTheme === 'true') {
                document.body.classList.add('dark-theme-variables');
                console.log('Tema dark aplicado em gerenciar-vendedoras.php');
            }
        });
    </script>
</body>
</html>
