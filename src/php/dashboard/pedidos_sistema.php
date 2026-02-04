<?php
session_start();
// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../../../PHP/login.php');
    exit();
}

// Incluir arquivos necessários
require_once 'helper-contador.php';
require_once '../auto_log.php';
require_once '../../../config/config.php';
require_once 'email_automatico.php';

// Criar tabela de pedidos se não existir
$create_pedidos_table = "CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    cliente_nome VARCHAR(255) NOT NULL,
    cliente_email VARCHAR(255) NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    status VARCHAR(100) DEFAULT 'Pedido Recebido',
    data_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    observacoes TEXT,
    INDEX idx_cliente_id (cliente_id),
    INDEX idx_status (status)
)";
mysqli_query($conexao, $create_pedidos_table);

$success_msg = '';
$error_msg = '';

// Recuperar mensagens da sessão
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
        case 'create_pedido':
            $cliente_id = intval($_POST['cliente_id'] ?? 0);
            $valor_total = floatval($_POST['valor_total'] ?? 0);
            $observacoes = trim($_POST['observacoes'] ?? '');
            
            if ($cliente_id && $valor_total > 0) {
                // Buscar dados do cliente
                $query_cliente = "SELECT nome, email FROM clientes WHERE id = ?";
                $stmt_cliente = mysqli_prepare($conexao, $query_cliente);
                mysqli_stmt_bind_param($stmt_cliente, "i", $cliente_id);
                mysqli_stmt_execute($stmt_cliente);
                $result_cliente = mysqli_stmt_get_result($stmt_cliente);
                $cliente = mysqli_fetch_assoc($result_cliente);
                
                if ($cliente) {
                    // Inserir pedido
                    $query = "INSERT INTO pedidos (cliente_id, cliente_nome, cliente_email, valor_total, observacoes) VALUES (?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conexao, $query);
                    mysqli_stmt_bind_param($stmt, "issds", $cliente_id, $cliente['nome'], $cliente['email'], $valor_total, $observacoes);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $pedido_id = mysqli_insert_id($conexao);
                        
                        // 🚀 DISPARAR EMAIL DE CONFIRMAÇÃO DE PEDIDO AUTOMATICAMENTE
                        enviarEmailAutomatico('novo_pedido', [
                            'pedido_id' => $pedido_id,
                            'nome' => $cliente['nome'],
                            'email' => $cliente['email'],
                            'valor_total' => $valor_total
                        ]);
                        
                        // 🚀 DISPARAR EMAIL DO PRIMEIRO STATUS (Pedido Recebido)
                        enviarEmailAutomatico('status_pedido', [
                            'pedido_id' => $pedido_id,
                            'nome' => $cliente['nome'],
                            'email' => $cliente['email'],
                            'novo_status' => 'Pedido Recebido'
                        ]);
                        
                        registrar_log($conexao, "Criou pedido #{$pedido_id} para {$cliente['nome']} - R$ " . number_format($valor_total, 2, ',', '.'));
                        $_SESSION['success_msg'] = "✅ Pedido #{$pedido_id} criado com sucesso! Emails automáticos enviados.";
                    } else {
                        $_SESSION['error_msg'] = "❌ Erro ao criar pedido: " . mysqli_error($conexao);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $_SESSION['error_msg'] = "❌ Cliente não encontrado!";
                }
                mysqli_stmt_close($stmt_cliente);
            } else {
                $_SESSION['error_msg'] = "❌ Dados do pedido inválidos!";
            }
            
            header('Location: pedidos_sistema.php');
            exit();
            break;
            
        case 'update_status':
            $pedido_id = intval($_POST['pedido_id'] ?? 0);
            $novo_status = trim($_POST['novo_status'] ?? '');
            
            if ($pedido_id && $novo_status) {
                // Buscar dados do pedido
                $query_pedido = "SELECT cliente_nome, cliente_email, status FROM pedidos WHERE id = ?";
                $stmt_pedido = mysqli_prepare($conexao, $query_pedido);
                mysqli_stmt_bind_param($stmt_pedido, "i", $pedido_id);
                mysqli_stmt_execute($stmt_pedido);
                $result_pedido = mysqli_stmt_get_result($stmt_pedido);
                $pedido = mysqli_fetch_assoc($result_pedido);
                
                if ($pedido) {
                    $status_antigo = $pedido['status'];
                    
                    // Atualizar status
                    $query = "UPDATE pedidos SET status = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conexao, $query);
                    mysqli_stmt_bind_param($stmt, "si", $novo_status, $pedido_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        // 🚀 DISPARAR EMAIL DE MUDANÇA DE STATUS AUTOMATICAMENTE
                        enviarEmailAutomatico('status_pedido', [
                            'pedido_id' => $pedido_id,
                            'nome' => $pedido['cliente_nome'],
                            'email' => $pedido['cliente_email'],
                            'novo_status' => $novo_status
                        ]);
                        
                        registrar_log($conexao, "Alterou status do pedido #{$pedido_id} de '{$status_antigo}' para '{$novo_status}'");
                        $_SESSION['success_msg'] = "✅ Status atualizado! Email automático enviado para {$pedido['cliente_nome']}.";
                    } else {
                        $_SESSION['error_msg'] = "❌ Erro ao atualizar status: " . mysqli_error($conexao);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $_SESSION['error_msg'] = "❌ Pedido não encontrado!";
                }
                mysqli_stmt_close($stmt_pedido);
            }
            
            header('Location: pedidos_sistema.php');
            exit();
            break;
    }
}

// Buscar pedidos
$pedidos = [];
$query = "SELECT * FROM pedidos ORDER BY id DESC LIMIT 50";
$result = mysqli_query($conexao, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $pedidos[] = $row;
    }
}

// Buscar clientes para dropdown
$clientes = [];
$query_clientes = "SELECT id, nome, email FROM clientes ORDER BY nome";
$result_clientes = mysqli_query($conexao, $query_clientes);
if ($result_clientes) {
    while ($row = mysqli_fetch_assoc($result_clientes)) {
        $clientes[] = $row;
    }
}

// Buscar status do fluxo
$status_fluxo = [];
$query_status = "SELECT nome FROM status_fluxo ORDER BY ordem";
$result_status = mysqli_query($conexao, $query_status);
if ($result_status) {
    while ($row = mysqli_fetch_assoc($result_status)) {
        $status_fluxo[] = $row['nome'];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../css/dashboard.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp" rel="stylesheet">
    <title>Pedidos - Sistema de Emails Automáticos</title>
    <style>
        .pedidos-container {
            background: var(--color-white);
            padding: 30px;
            border-radius: var(--card-border-radius);
            box-shadow: var(--box-shadow);
            margin: 20px 0;
        }
        
        .pedidos-grid {
            display: grid;
            grid-template-columns: 80px 1fr 1fr 150px 150px 200px 120px;
            gap: 1rem;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--color-light);
        }
        
        .status-badge {
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            color: white;
        }
        
        .status-pedido-recebido { background: #ff00d4; }
        .status-pagamento-confirmado { background: #41f1b6; }
        .status-em-preparacao { background: #ffbb55; }
        .status-enviado { background: #007bff; }
        .status-entregue { background: #28a745; }
        
        .form-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background: var(--color-white);
            margin: 5% auto;
            padding: 30px;
            border-radius: var(--card-border-radius);
            max-width: 600px;
            position: relative;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--color-dark);
        }
        
        .form-input, .form-select {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--color-light);
            border-radius: var(--border-radius-2);
            font-size: 14px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #ff1493, #ff00d4);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: var(--border-radius-2);
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-secondary {
            background: var(--color-light);
            color: var(--color-dark);
            border: none;
            padding: 8px 16px;
            border-radius: var(--border-radius-2);
            cursor: pointer;
            font-size: 12px;
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
                <a href="pedidos_sistema.php" class="active">
                    <span class="material-symbols-sharp">shopping_cart</span>
                    <h3>Pedidos</h3>
                </a>
                <a href="products.php">
                    <span class="material-symbols-sharp">inventory</span>
                    <h3>Produtos</h3>
                </a>
                <a href="gestao-fluxo.php">
                    <span class="material-symbols-sharp">account_tree</span>
                    <h3>Gestão de Fluxo</h3>
                </a>
                <a href="automacao.php">
                    <span class="material-symbols-sharp">automation</span>
                    <h3>Automação</h3>
                </a>
                <a href="../../../PHP/logout.php">
                    <span class="material-symbols-sharp">logout</span>
                    <h3>Sair</h3>
                </a>
            </div>
        </aside>

        <main>
            <h1>🛍️ Sistema de Pedidos com Emails Automáticos</h1>
            
            <!-- Mensagens de Feedback -->
            <?php if ($success_msg): ?>
                <div style="background: var(--color-white); border: 2px solid var(--color-success); color: var(--color-success); padding: 1rem; border-radius: var(--card-border-radius); margin: 1rem 0;">
                    <span class="material-symbols-sharp" style="margin-right: 0.5rem;">check_circle</span>
                    <?= htmlspecialchars($success_msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div style="background: var(--color-white); border: 2px solid var(--color-danger); color: var(--color-danger); padding: 1rem; border-radius: var(--card-border-radius); margin: 1rem 0;">
                    <span class="material-symbols-sharp" style="margin-right: 0.5rem;">error</span>
                    <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <!-- Botões de Ação -->
            <div style="display: flex; gap: 1rem; margin: 20px 0;">
                <button onclick="openModal('criar')" class="btn-primary">
                    <span class="material-symbols-sharp" style="margin-right: 0.5rem;">add</span>
                    Criar Novo Pedido
                </button>
                <button onclick="location.href='email_automatico.php'" class="btn-secondary">
                    <span class="material-symbols-sharp" style="margin-right: 0.5rem;">email</span>
                    Testar Emails
                </button>
            </div>

            <!-- Lista de Pedidos -->
            <div class="pedidos-container">
                <div class="pedidos-grid" style="font-weight: 600; background: var(--color-background);">
                    <div>ID</div>
                    <div>Cliente</div>
                    <div>Email</div>
                    <div>Valor</div>
                    <div>Status</div>
                    <div>Data</div>
                    <div>Ações</div>
                </div>

                <?php if (empty($pedidos)): ?>
                    <div style="text-align: center; padding: 50px; color: var(--color-info-dark);">
                        <span class="material-symbols-sharp" style="font-size: 4rem; opacity: 0.5; display: block; margin-bottom: 1rem;">shopping_cart_off</span>
                        <h3>Nenhum pedido encontrado</h3>
                        <p>Crie o primeiro pedido para testar o sistema de emails automáticos.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pedidos as $pedido): ?>
                        <div class="pedidos-grid">
                            <div style="font-weight: 600; color: var(--color-primary);">#<?= $pedido['id'] ?></div>
                            <div style="font-weight: 600;"><?= htmlspecialchars($pedido['cliente_nome']) ?></div>
                            <div style="color: var(--color-info-dark); font-size: 12px;"><?= htmlspecialchars($pedido['cliente_email']) ?></div>
                            <div style="font-weight: 600; color: var(--color-success);">R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></div>
                            <div>
                                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $pedido['status'])) ?>">
                                    <?= htmlspecialchars($pedido['status']) ?>
                                </span>
                            </div>
                            <div style="font-size: 12px; color: var(--color-info-dark);">
                                <?= date('d/m/Y H:i', strtotime($pedido['data_pedido'])) ?>
                            </div>
                            <div>
                                <button onclick="openStatusModal(<?= $pedido['id'] ?>, '<?= htmlspecialchars($pedido['status']) ?>', '<?= htmlspecialchars($pedido['cliente_nome']) ?>')" class="btn-secondary">
                                    <span class="material-symbols-sharp">edit</span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal Criar Pedido -->
    <div id="modalCriar" class="form-modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px; color: var(--color-primary);">🛍️ Criar Novo Pedido</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="create_pedido">
                
                <div class="form-group">
                    <label class="form-label">Cliente</label>
                    <select name="cliente_id" class="form-select" required>
                        <option value="">Selecione um cliente</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?= $cliente['id'] ?>"><?= htmlspecialchars($cliente['nome']) ?> (<?= htmlspecialchars($cliente['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Valor Total</label>
                    <input type="number" name="valor_total" class="form-input" step="0.01" min="0.01" required placeholder="0,00">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Observações</label>
                    <textarea name="observacoes" class="form-input" rows="3" placeholder="Observações do pedido..."></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 30px;">
                    <button type="button" onclick="closeModal('modalCriar')" style="background: var(--color-light); color: var(--color-dark); border: none; padding: 12px 24px; border-radius: var(--border-radius-2); cursor: pointer;">Cancelar</button>
                    <button type="submit" class="btn-primary">
                        <span class="material-symbols-sharp" style="margin-right: 0.5rem;">add</span>
                        Criar Pedido
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Alterar Status -->
    <div id="modalStatus" class="form-modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px; color: var(--color-primary);">📦 Alterar Status do Pedido</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="pedido_id" id="statusPedidoId">
                
                <p style="margin-bottom: 20px; color: var(--color-info-dark);">
                    <strong>Pedido:</strong> #<span id="statusPedidoNumero"></span><br>
                    <strong>Cliente:</strong> <span id="statusClienteNome"></span>
                </p>
                
                <div class="form-group">
                    <label class="form-label">Novo Status</label>
                    <select name="novo_status" class="form-select" required>
                        <option value="">Selecione o novo status</option>
                        <?php foreach ($status_fluxo as $status): ?>
                            <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="background: #e7f3ff; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #007bff;">
                    <strong>📧 Email Automático</strong><br>
                    <small style="color: #666;">Um email será enviado automaticamente para o cliente informando sobre a mudança de status.</small>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 30px;">
                    <button type="button" onclick="closeModal('modalStatus')" style="background: var(--color-light); color: var(--color-dark); border: none; padding: 12px 24px; border-radius: var(--border-radius-2); cursor: pointer;">Cancelar</button>
                    <button type="submit" class="btn-primary">
                        <span class="material-symbols-sharp" style="margin-right: 0.5rem;">email</span>
                        Atualizar e Enviar Email
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../js/dashboard.js"></script>
    <script>
        // Aplicar tema salvo
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('darkTheme');
            if (savedTheme === 'true') {
                document.body.classList.add('dark-theme-variables');
            }
        });

        function openModal(tipo) {
            document.getElementById('modalCriar').style.display = 'block';
        }

        function openStatusModal(pedidoId, statusAtual, clienteNome) {
            document.getElementById('statusPedidoId').value = pedidoId;
            document.getElementById('statusPedidoNumero').textContent = pedidoId;
            document.getElementById('statusClienteNome').textContent = clienteNome;
            document.getElementById('modalStatus').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Fechar modal clicando fora dele
        window.onclick = function(event) {
            if (event.target.classList.contains('form-modal')) {
                event.target.style.display = 'none';
            }
        }

        console.log('🚀 Sistema de Pedidos com Emails Automáticos carregado!');
    </script>
</body>
</html>