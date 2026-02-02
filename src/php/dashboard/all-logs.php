<?php
session_start();
// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../../../PHP/login.php');
    exit();
}

require_once '../../../PHP/conexao.php';
require_once '../auto_log.php';

// Processar exclusões de logs
if (isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    if ($_POST['action'] === 'delete_log') {
        $log_id = (int)$_POST['log_id'];
        
        try {
            $sql = "DELETE FROM admin_logs WHERE id = ?";
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, "i", $log_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $response['success'] = true;
                $response['message'] = 'Log excluído com sucesso!';
                
                // Registrar que alguém excluiu um log
                registrar_log($conexao, "excluiu log ID: {$log_id} do sistema");
            } else {
                $response['message'] = 'Erro ao excluir log.';
            }
        } catch (Exception $e) {
            $response['message'] = 'Erro: ' . $e->getMessage();
        }
    }
    
    if ($_POST['action'] === 'delete_all_logs') {
        try {
            // Contar quantos logs serão excluídos
            $count_result = mysqli_query($conexao, "SELECT COUNT(*) as total FROM admin_logs");
            $total_logs = $count_result->fetch_assoc()['total'];
            
            $sql = "DELETE FROM admin_logs";
            if (mysqli_query($conexao, $sql)) {
                $response['success'] = true;
                $response['message'] = "Todos os {$total_logs} logs foram excluídos!";
                
                // Registrar a limpeza total (este será o primeiro log depois da limpeza)
                registrar_log($conexao, "limpou todos os logs do sistema ({$total_logs} registros excluídos)");
            } else {
                $response['message'] = 'Erro ao limpar logs.';
            }
        } catch (Exception $e) {
            $response['message'] = 'Erro: ' . $e->getMessage();
        }
    }
    
    // Retornar resposta JSON para AJAX
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Redirecionamento normal
    $_SESSION['message'] = $response['message'];
    $_SESSION['message_type'] = $response['success'] ? 'success' : 'error';
    header('Location: all-logs.php');
    exit;
}

// Buscar todos os logs com paginação e pesquisa
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$admin_logs = [];
$total_logs = 0;

try {
    // Construir query com pesquisa
    $where_clause = "";
    $search_condition = "";
    
    if (!empty($search)) {
        $search_escaped = mysqli_real_escape_string($conexao, $search);
        $where_clause = "WHERE admin_nome LIKE '%$search_escaped%' OR acao LIKE '%$search_escaped%' OR admin_id LIKE '%$search_escaped%' OR ip_address LIKE '%$search_escaped%' OR DATE_FORMAT(data_hora, '%d/%m/%Y %H:%i:%s') LIKE '%$search_escaped%'";
    }
    
    // Contar total de logs
    $count_sql = "SELECT COUNT(*) as total FROM admin_logs $where_clause";
    $count_result = $conexao->query($count_sql);
    if ($count_result) {
        $total_logs = $count_result->fetch_assoc()['total'];
    }
    
    // Buscar logs da página atual
    $sql = "SELECT admin_id, admin_nome, acao, data_hora, ip_address FROM admin_logs $where_clause ORDER BY data_hora DESC LIMIT $per_page OFFSET $offset";
    $result = $conexao->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $admin_logs[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Erro ao buscar logs: " . $e->getMessage());
}

// Calcular paginação
$total_pages = ceil($total_logs / $per_page);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todos os Logs - D&Z Admin</title>
    <link rel="stylesheet" href="../../css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp" rel="stylesheet">
    <style>
        .logs-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .logs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--color-light);
        }
        
        .header-actions {
            display: flex;
            gap: 1rem;
        }
        
        .logs-header h1 {
            color: var(--color-dark);
            font-size: 1.8rem;
            margin: 0;
            font-weight: 600;
        }
        
        .btn-back, .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            color: var(--color-white);
            text-decoration: none;
            border-radius: var(--border-radius-2);
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .btn-back {
            background: var(--color-dark);
        }
        
        .btn-back:hover {
            background: var(--color-dark-variant);
            transform: translateY(-2px);
        }
        
        .btn-download {
            background: linear-gradient(135deg, #28a745, #20c997);
        }
        
        .btn-download:hover {
            background: linear-gradient(135deg, #20c997, #28a745);
            transform: translateY(-2px);
        }
        
        .logs-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .search-container {
            background: var(--color-white);
            padding: 1.5rem;
            border-radius: var(--border-radius-2);
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
        }
        
        .search-form {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .search-input {
            flex: 1;
            padding: 0.8rem 1rem;
            border: 2px solid var(--color-light);
            border-radius: var(--border-radius-1);
            font-size: 1rem;
            background: var(--color-background);
            color: var(--color-dark);
            transition: border-color 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--color-primary);
        }
        
        .search-btn {
            padding: 0.8rem 1.5rem;
            background: var(--color-primary);
            color: var(--color-white);
            border: none;
            border-radius: var(--border-radius-1);
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .search-btn:hover {
            background: var(--color-primary-variant);
            transform: translateY(-1px);
        }
        
        .search-clear {
            padding: 0.6rem;
            background: var(--color-dark-variant);
            color: var(--color-white);
            border: none;
            border-radius: var(--border-radius-1);
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .search-clear:hover {
            background: var(--color-dark);
        }
        
        .search-info {
            margin-top: 1rem;
            padding: 0.8rem;
            background: var(--color-background);
            border-radius: var(--border-radius-1);
            color: var(--color-dark-variant);
            font-size: 0.9rem;
        }
        
        .stat-card {
            background: var(--color-white);
            padding: 1.5rem;
            border-radius: var(--border-radius-2);
            box-shadow: var(--box-shadow);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--color-primary);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--color-dark-variant);
            font-size: 0.9rem;
        }
        
        .logs-table {
            background: var(--color-white);
            border-radius: var(--border-radius-2);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }
        
        .table-header {
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-variant));
            color: var(--color-white);
            padding: 1.5rem;
            font-weight: 600;
        }
        
        .logs-list {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .log-item {
            display: grid;
            grid-template-columns: 50px 120px 2fr 180px 120px 120px 80px;
            gap: 1rem;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--color-light);
            transition: background-color 0.2s ease;
            font-size: 0.9rem;
        }
        
        .log-item:hover {
            background-color: var(--color-background);
        }
        
        .log-item:last-child {
            border-bottom: none;
        }
        
        .log-id {
            font-weight: 600;
            color: var(--color-dark-variant);
            font-size: 0.9rem;
        }
        
        .log-admin {
            font-weight: 600;
            color: var(--color-primary);
        }
        
        .log-action {
            color: var(--color-dark);
        }
        
        .log-time {
            color: var(--color-dark-variant);
            font-size: 0.9rem;
        }
        
        .log-ip {
            font-family: monospace;
            font-size: 0.8rem;
            color: var(--color-dark-variant);
            background: var(--color-background);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
        }
        
        .log-admin-id {
            font-weight: 600;
            color: var(--color-dark-variant);
            font-size: 0.9rem;
            text-align: center;
        }
        
        .log-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-1px);
        }
        
        .btn-delete-all {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: var(--border-radius-1);
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: 1rem;
        }
        
        .btn-delete-all:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
            padding: 1rem;
        }
        
        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            text-decoration: none;
            border-radius: var(--border-radius-1);
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .pagination a {
            background: var(--color-white);
            color: var(--color-dark);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .pagination a:hover {
            background: var(--color-primary);
            color: var(--color-white);
            transform: translateY(-2px);
        }
        
        .pagination .current {
            background: var(--color-primary);
            color: var(--color-white);
        }
        
        .no-logs {
            text-align: center;
            padding: 3rem;
            color: var(--color-dark-variant);
        }
        
        @media (max-width: 768px) {
            .logs-container {
                padding: 0 1rem;
            }
            
            .logs-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .header-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .search-form {
                flex-direction: column;
                gap: 1rem;
            }
            
            .search-input {
                width: 100%;
            }
            
            .logs-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .log-item {
                grid-template-columns: 1fr;
                gap: 0.5rem;
                text-align: left;
                padding: 1rem;
            }
            
            .log-item > div {
                padding: 0.25rem 0;
                border-bottom: 1px solid var(--color-light);
            }
            
            .log-item > div:last-child {
                border-bottom: none;
            }
            
            .log-actions {
                justify-content: flex-start;
                margin-top: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="logs-container">
        <div class="logs-header">
            <h1>Todos os Logs de Atividade</h1>
            <div class="header-actions">
                <button onclick="clearAllLogs()" class="btn-delete-all">
                    <span class="material-symbols-sharp">delete_sweep</span>
                    Limpar Todos os Logs
                </button>
                <a href="download-logs.php" class="btn-download">
                    <span class="material-symbols-sharp">download</span>
                    Baixar Planilha
                </a>
                <a href="index.php" class="btn-back">
                    <span class="material-symbols-sharp">arrow_back</span>
                    Voltar ao Dashboard
                </a>
            </div>
        </div>
        
        <div class="search-container">
            <form class="search-form" method="GET" action="">
                <input type="hidden" name="page" value="1">
                <input 
                    type="text" 
                    name="search" 
                    class="search-input" 
                    placeholder="Pesquisar por nome, ação, ID do admin, IP ou data..."
                    value="<?php echo htmlspecialchars($search); ?>"
                    id="searchInput"
                >
                <button type="submit" class="search-btn">
                    <span class="material-symbols-sharp">search</span>
                    Buscar
                </button>
                <?php if (!empty($search)): ?>
                    <a href="all-logs.php" class="search-clear" title="Limpar pesquisa">
                        <span class="material-symbols-sharp">close</span>
                    </a>
                <?php endif; ?>
            </form>
            
            <?php if (!empty($search)): ?>
                <div class="search-info">
                    <strong>Resultado da pesquisa por:</strong> "<?php echo htmlspecialchars($search); ?>" 
                    - <?php echo $total_logs; ?> registro(s) encontrado(s)
                </div>
            <?php endif; ?>
        </div>
        
        <div class="logs-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_logs; ?></div>
                <div class="stat-label">Total de Registros</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $page; ?> / <?php echo $total_pages; ?></div>
                <div class="stat-label">Página Atual</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($admin_logs); ?></div>
                <div class="stat-label">Registros Visíveis</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo date('d/m/Y H:i'); ?></div>
                <div class="stat-label">Última Atualização</div>
            </div>
        </div>
        
        <div class="logs-table">
            <div class="table-header">
                <div class="log-item" style="font-weight: 600;">
                    <div>ID</div>
                    <div>Administrador</div>
                    <div>Ação Realizada</div>
                    <div>Data/Hora</div>
                    <div>IP Address</div>
                    <div>Admin ID</div>
                    <div>Ações</div>
                </div>
            </div>
            
            <div class="logs-list">
                <?php if (!empty($admin_logs)): ?>
                    <?php 
                    // Buscar IDs dos logs para exclusão
                    $ids_query = "SELECT id FROM admin_logs $where_clause ORDER BY data_hora DESC LIMIT $per_page OFFSET $offset";
                    $ids_result = mysqli_query($conexao, $ids_query);
                    $log_ids = [];
                    if ($ids_result) {
                        while ($id_row = $ids_result->fetch_assoc()) {
                            $log_ids[] = $id_row['id'];
                        }
                    }
                    ?>
                    
                    <?php foreach ($admin_logs as $index => $log): ?>
                        <div class="log-item" id="log-<?php echo $log_ids[$index] ?? $index; ?>">
                            <div class="log-id"><?php echo $offset + $index + 1; ?></div>
                            <div class="log-admin"><?php echo htmlspecialchars($log['admin_nome']); ?></div>
                            <div class="log-action"><?php echo htmlspecialchars($log['acao']); ?></div>
                            <div class="log-time">
                                <?php echo date('d/m/Y H:i:s', strtotime($log['data_hora'])); ?>
                            </div>
                            <div class="log-ip"><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></div>
                            <div class="log-admin-id"><?php echo htmlspecialchars($log['admin_id'] ?? 'N/A'); ?></div>
                            <div class="log-actions">
                                <?php if (isset($log_ids[$index])): ?>
                                    <button onclick="deleteLog(<?php echo $log_ids[$index]; ?>)" class="btn-delete" title="Excluir este log">
                                        <span class="material-symbols-sharp">delete</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-logs">
                        <span class="material-symbols-sharp" style="font-size: 3rem; color: var(--color-light);">history</span>
                        <h3>Nenhum log encontrado</h3>
                        <p>Ainda não há atividades registradas no sistema.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php 
                $search_param = !empty($search) ? '&search=' . urlencode($search) : '';
                ?>
                
                <?php if ($page > 1): ?>
                    <a href="?page=1<?php echo $search_param; ?>" title="Primeira página">
                        <span class="material-symbols-sharp">first_page</span>
                    </a>
                    <a href="?page=<?php echo $page - 1; ?><?php echo $search_param; ?>" title="Página anterior">
                        <span class="material-symbols-sharp">chevron_left</span>
                    </a>
                <?php endif; ?>
                
                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search_param; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo $search_param; ?>" title="Próxima página">
                        <span class="material-symbols-sharp">chevron_right</span>
                    </a>
                    <a href="?page=<?php echo $total_pages; ?><?php echo $search_param; ?>" title="Última página">
                        <span class="material-symbols-sharp">last_page</span>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh a cada 30 segundos se estiver na primeira página e sem pesquisa
        <?php if ($page == 1 && empty($search)): ?>
        setInterval(() => {
            if (!document.hidden) {
                window.location.reload();
            }
        }, 30000);
        <?php endif; ?>
        
        // Adicionar efeitos de hover suaves
        document.querySelectorAll('.log-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.01)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });
        
        // Melhorar experiência de pesquisa
        const searchInput = document.getElementById('searchInput');
        const searchForm = document.querySelector('.search-form');
        
        // Highlight nos resultados
        function highlightSearchTerms() {
            const searchTerm = searchInput.value.trim().toLowerCase();
            if (!searchTerm) return;
            
            document.querySelectorAll('.log-admin, .log-action, .log-admin-id, .log-ip, .log-time').forEach(element => {
                const text = element.textContent;
                if (text.toLowerCase().includes(searchTerm)) {
                    const highlightedText = text.replace(
                        new RegExp(`(${searchTerm})`, 'gi'),
                        '<mark style="background: var(--color-primary); color: var(--color-white); padding: 2px 4px; border-radius: 3px;">$1</mark>'
                    );
                    element.innerHTML = highlightedText;
                }
            });
        }
        
        // Aplicar highlight se há termo de pesquisa
        <?php if (!empty($search)): ?>
        setTimeout(highlightSearchTerms, 100);
        <?php endif; ?>
        
        // Validação do formulário
        searchForm.addEventListener('submit', function(e) {
            const searchValue = searchInput.value.trim();
            if (searchValue.length > 0 && searchValue.length < 2) {
                e.preventDefault();
                alert('Digite pelo menos 2 caracteres para pesquisar.');
                searchInput.focus();
                return false;
            }
        });
        
        // Atalho de teclado para pesquisa (Ctrl+F ou Ctrl+K)
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey && e.key === 'f') || (e.ctrlKey && e.key === 'k')) {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
            
            // ESC para limpar pesquisa
            if (e.key === 'Escape' && document.activeElement === searchInput) {
                if (searchInput.value) {
                    searchInput.value = '';
                } else {
                    searchInput.blur();
                }
            }
        });
        
        // Auto-complete/sugestões simples
        searchInput.addEventListener('input', function() {
            const value = this.value;
            
            // Remover caracteres especiais perigosos
            this.value = value.replace(/[<>\"']/g, '');
            
            // Feedback visual para pesquisas válidas
            if (this.value.length >= 2) {
                this.style.borderColor = 'var(--color-success)';
            } else if (this.value.length > 0) {
                this.style.borderColor = 'var(--color-warning)';
            } else {
                this.style.borderColor = 'var(--color-light)';
            }
        });
        
        // Dicas de pesquisa
        searchInput.addEventListener('focus', function() {
            if (!this.value) {
                this.placeholder = 'Ex: Lucas, login, ID:1, 192.168.1.1, 29/01/2026...';
            }
        });
        
        searchInput.addEventListener('blur', function() {
            this.placeholder = 'Pesquisar por nome, ação, ID do admin, IP ou data...';
        });
        
        // Mostrar mensagem de feedback se existir
        <?php if (isset($_SESSION['message'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                showNotification('<?php echo addslashes($_SESSION['message']); ?>', '<?php echo isset($_SESSION['message_type']) ? $_SESSION['message_type'] : 'info'; ?>');
            });
        <?php 
            unset($_SESSION['message']); 
            unset($_SESSION['message_type']);
        endif; 
        ?>
        
        // Função para excluir log individual
        function deleteLog(logId) {
            if (confirm('Tem certeza que deseja excluir este log?\n\nEsta ação não pode ser desfeita.')) {
                const formData = new FormData();
                formData.append('action', 'delete_log');
                formData.append('log_id', logId);
                formData.append('ajax', '1');
                
                fetch('all-logs.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remover o elemento da tela
                        const logElement = document.getElementById(`log-${logId}`);
                        if (logElement) {
                            logElement.style.transition = 'all 0.3s ease';
                            logElement.style.opacity = '0';
                            logElement.style.transform = 'translateX(-20px)';
                            
                            setTimeout(() => {
                                logElement.remove();
                                showNotification(data.message, 'success');
                                
                                // Recarregar se não há mais logs visíveis
                                if (document.querySelectorAll('.log-item:not(.table-header > .log-item)').length === 0) {
                                    location.reload();
                                }
                            }, 300);
                        }
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    showNotification('Erro na comunicação com o servidor', 'error');
                    console.error('Erro:', error);
                });
            }
        }
        
        // Função para limpar todos os logs
        function clearAllLogs() {
            if (confirm('⚠️ ATENÇÃO ⚠️\n\nDeseja EXCLUIR TODOS OS LOGS?\n\nTodos os registros de atividade serão perdidos permanentemente.\n\nEsta ação NÃO pode ser desfeita!')) {
                if (confirm('Última confirmação:\n\nTem ABSOLUTA CERTEZA que quer limpar TODOS os logs do sistema?')) {
                    const formData = new FormData();
                    formData.append('action', 'delete_all_logs');
                    formData.append('ajax', '1');
                    
                    // Mostrar loading
                    const deleteAllBtn = document.querySelector('.btn-delete-all');
                    const originalText = deleteAllBtn.innerHTML;
                    deleteAllBtn.innerHTML = '<span class="material-symbols-sharp">hourglass_top</span>Limpando...';
                    deleteAllBtn.disabled = true;
                    
                    fetch('all-logs.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        deleteAllBtn.innerHTML = originalText;
                        deleteAllBtn.disabled = false;
                        
                        if (data.success) {
                            showNotification(data.message, 'success');
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            showNotification(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        deleteAllBtn.innerHTML = originalText;
                        deleteAllBtn.disabled = false;
                        showNotification('Erro na comunicação com o servidor', 'error');
                        console.error('Erro:', error);
                    });
                }
            }
        }
        
        // Função para mostrar notificações
        function showNotification(message, type = 'info') {
            // Remover notificação anterior se existir
            const existingNotification = document.querySelector('.notification');
            if (existingNotification) {
                existingNotification.remove();
            }
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#007bff'};
                color: white;
                padding: 1rem 1.5rem;
                border-radius: var(--border-radius-1);
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 9999;
                font-weight: 500;
                max-width: 350px;
                animation: slideIn 0.3s ease;
            `;
            
            notification.innerHTML = `
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span class="material-symbols-sharp">
                        ${type === 'success' ? 'check_circle' : type === 'error' ? 'error' : 'info'}
                    </span>
                    ${message}
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 4000);
        }
        
        // Adicionar CSS para animações
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            
            .btn-delete-all:disabled {
                background: #6c757d !important;
                cursor: not-allowed !important;
                transform: none !important;
            }
        `;
        document.head.appendChild(style);
        
    </script>
</body>
</html>