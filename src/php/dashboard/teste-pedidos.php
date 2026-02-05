<?php
session_start();
// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../../../PHP/login.php');
    exit();
}

require_once '../../../PHP/conexao.php';

// Função para criar pedido de teste
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $debug_info = [];
    $redirecionar = false;
    
    if (isset($_POST['criar_e_ir'])) {
        $redirecionar = true;
        $debug_info[] = "Modo: Criar e ir para principal";
    }
    
    if (isset($_POST['criar_pedido_teste']) || isset($_POST['criar_e_ir'])) {
        $debug_info[] = "Iniciando criação de pedido...";
        
        // Verificar se existe cliente disponível
        $cliente_result = mysqli_query($conexao, "SELECT id FROM clientes LIMIT 1");
        if (!$cliente_result || mysqli_num_rows($cliente_result) == 0) {
            $mensagem = "❌ Erro: Nenhum cliente encontrado. Execute fix-database.php primeiro!";
        } else {
            $cliente = mysqli_fetch_assoc($cliente_result);
            $cliente_id = $cliente['id'];
            $debug_info[] = "Cliente ID encontrado: $cliente_id";
            
            $valor_total = rand(50, 300) + 0.90; // Valor aleatório
            $status_opcoes = ['Pedido Recebido', 'Pagamento Confirmado', 'Em Preparação', 'Enviado', 'Entregue'];
            $status = $status_opcoes[array_rand($status_opcoes)];
            $debug_info[] = "Dados: Valor=$valor_total, Status=$status";
            
            // Usar apenas colunas que existem na tabela básica
            $sql = "INSERT INTO pedidos (cliente_id, valor_total, status) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conexao, $sql);
            
            if (!$stmt) {
                $mensagem = "❌ Erro ao preparar SQL: " . mysqli_error($conexao);
                $debug_info[] = "Erro prepare: " . mysqli_error($conexao);
            } else {
                mysqli_stmt_bind_param($stmt, 'ids', $cliente_id, $valor_total, $status);
                
                if (mysqli_stmt_execute($stmt)) {
                    $novo_pedido_id = mysqli_insert_id($conexao);
                    $debug_info[] = "Pedido criado com ID: $novo_pedido_id";
                    
                    // Adicionar alguns itens ao pedido
                    $produtos_result = mysqli_query($conexao, "SELECT id FROM produtos LIMIT 3");
                    $produtos_ids = [];
                    while ($produto = mysqli_fetch_assoc($produtos_result)) {
                        $produtos_ids[] = $produto['id'];
                    }
                    $debug_info[] = "Produtos disponíveis: " . implode(', ', $produtos_ids);
                    
                    if (!empty($produtos_ids)) {
                        $sql_item = "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)";
                        $stmt_item = mysqli_prepare($conexao, $sql_item);
                        
                        // Adicionar 1-3 produtos aleatórios
                        $num_produtos = min(rand(1, 3), count($produtos_ids));
                        $itens_adicionados = 0;
                        for ($i = 0; $i < $num_produtos; $i++) {
                            $produto_id = $produtos_ids[array_rand($produtos_ids)]; // Usar ID real
                            $quantidade = rand(1, 3);
                            $preco = rand(20, 50) + 0.90;
                            
                            mysqli_stmt_bind_param($stmt_item, 'iiid', $novo_pedido_id, $produto_id, $quantidade, $preco);
                            if (mysqli_stmt_execute($stmt_item)) {
                                $itens_adicionados++;
                            }
                        }
                        $debug_info[] = "Itens adicionados: $itens_adicionados";
                    }
                    
                    $mensagem = "✅ Pedido #$novo_pedido_id criado com sucesso! Status: $status";
                    
                    // Se solicitado, redirecionar para página principal
                    if ($redirecionar) {
                        $debug_info[] = "Redirecionando...";
                        header('Location: orders.php');
                        exit();
                    }
                } else {
                    $mensagem = "❌ Erro ao executar SQL: " . mysqli_stmt_error($stmt);
                    $debug_info[] = "Erro execute: " . mysqli_stmt_error($stmt);
                }
            }
        }
    }
}

// Buscar todos os pedidos para teste
$sql_pedidos = "
    SELECT 
        p.id,
        p.data_pedido,
        p.valor_total,
        p.status,
        c.nome as cliente_nome,
        COUNT(ip.id) as total_itens
    FROM pedidos p
    LEFT JOIN clientes c ON p.cliente_id = c.id
    LEFT JOIN itens_pedido ip ON p.id = ip.pedido_id
    GROUP BY p.id
    ORDER BY p.data_pedido DESC
";
$result_pedidos = mysqli_query($conexao, $sql_pedidos);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Pedidos - D&Z Admin</title>
    <link rel="stylesheet" href="../../css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp" rel="stylesheet">
    <style>
        .teste-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
            background: var(--color-white);
            border-radius: var(--card-border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .teste-section {
            margin-bottom: 3rem;
            padding: 1.5rem;
            border-left: 4px solid var(--color-primary);
            background: var(--color-background);
            border-radius: var(--border-radius-2);
        }
        
        .btn-teste {
            background: var(--color-primary);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--border-radius-1);
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-teste:hover {
            background: var(--color-primary-variant);
            transform: translateY(-2px);
        }
        
        .pedidos-teste table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .pedidos-teste th,
        .pedidos-teste td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--color-light);
        }
        
        .pedidos-teste th {
            background: var(--color-light);
            font-weight: 600;
        }
        
        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            color: white;
            text-align: center;
        }
        
        .mensagem {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: var(--border-radius-1);
            background: var(--color-success);
            color: white;
            font-weight: 500;
        }
        
        .info-card {
            background: var(--color-light);
            padding: 1rem;
            border-radius: var(--border-radius-1);
            margin: 1rem 0;
        }
        
        .btn-ver-detalhes {
            background: #ff00cc;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--border-radius-1);
            cursor: pointer;
            font-size: 0.8rem;
        }
        
        .btn-ver-detalhes:hover {
            background: #e600b8;
        }
    </style>
</head>
<body>
    <div class="teste-container">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1>🧪 Página de Teste - Pedidos</h1>
            <p>Use esta página para testar todas as funcionalidades dos pedidos</p>
        </div>

        <?php if (isset($mensagem)): ?>
            <div class="mensagem">
                <?= $mensagem ?>
                <div style="margin-top: 1rem;">
                    <a href="orders.php" class="btn-teste" style="background: #28a745; text-decoration: none;">
                        <span class="material-symbols-sharp">visibility</span>
                        🎯 Ver na Página Principal Agora
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($debug_info) && !empty($debug_info)): ?>
            <div style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                <h4>🐛 Debug Info:</h4>
                <?php foreach ($debug_info as $info): ?>
                    <p style="margin: 0.2rem 0; font-family: monospace; font-size: 0.9rem;">• <?= htmlspecialchars($info) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Seção 1: Informações do Sistema -->
        <div class="teste-section">
            <h2>📊 Status do Sistema</h2>
            <div class="info-card">
                <?php
                // Verificar se todas as tabelas necessárias existem
                $tabelas_necessarias = ['clientes', 'pedidos', 'produtos', 'itens_pedido'];
                $tabelas_ok = true;
                
                foreach ($tabelas_necessarias as $tabela) {
                    $check = mysqli_query($conexao, "SHOW TABLES LIKE '$tabela'");
                    if (mysqli_num_rows($check) == 0) {
                        echo "<p>❌ <strong>Tabela '$tabela' não encontrada!</strong></p>";
                        $tabelas_ok = false;
                    }
                }
                
                if ($tabelas_ok) {
                    $stats = [
                        'clientes' => mysqli_fetch_assoc(mysqli_query($conexao, "SELECT COUNT(*) as total FROM clientes"))['total'],
                        'pedidos' => mysqli_fetch_assoc(mysqli_query($conexao, "SELECT COUNT(*) as total FROM pedidos"))['total'],
                        'produtos' => mysqli_fetch_assoc(mysqli_query($conexao, "SELECT COUNT(*) as total FROM produtos"))['total'],
                        'itens_pedido' => mysqli_fetch_assoc(mysqli_query($conexao, "SELECT COUNT(*) as total FROM itens_pedido"))['total']
                    ];
                    
                    echo "<p><strong>📋 Clientes:</strong> {$stats['clientes']} registros</p>";
                    echo "<p><strong>📋 Pedidos:</strong> {$stats['pedidos']} registros</p>";
                    echo "<p><strong>📋 Produtos:</strong> {$stats['produtos']} registros</p>";
                    echo "<p><strong>📋 Itens de Pedidos:</strong> {$stats['itens_pedido']} registros</p>";
                    
                    if ($stats['clientes'] == 0 || $stats['produtos'] == 0) {
                        echo "<p style='color: red;'>⚠️ <strong>Precisa executar o fix-database.php primeiro!</strong></p>";
                    }
                } else {
                    echo "<p style='color: red;'>⚠️ <strong>Execute o fix-database.php para criar as tabelas!</strong></p>";
                }
                ?>
            </div>
        </div>

        <!-- Seção 2: Criar Pedido de Teste -->
        <div class="teste-section">
            <h2>🆕 Criar Pedido de Teste</h2>
            <p>Crie um pedido de teste e vá direto para a página principal verificar:</p>
            
            <div style="display: flex; gap: 1rem; margin-top: 1rem; flex-wrap: wrap;">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="criar_pedido_teste" class="btn-teste">
                        <span class="material-symbols-sharp">add_shopping_cart</span>
                        Criar Pedido de Teste
                    </button>
                </form>
                
                <form method="POST" style="display: inline;">
                    <button type="submit" name="criar_e_ir" class="btn-teste" style="background: #28a745;">
                        <span class="material-symbols-sharp">rocket_launch</span>
                        Criar e Ir para Principal
                    </button>
                </form>
                
                <a href="orders.php" class="btn-teste" style="background: #007bff; text-decoration: none;">
                    <span class="material-symbols-sharp">open_in_new</span>
                    Apenas Ir para Principal
                </a>
            </div>
            
            <div style="background: #e3f2fd; padding: 1rem; border-radius: 8px; margin-top: 1rem;">
                <strong>💡 Como testar:</strong>
                <ol style="margin: 0.5rem 0 0 1.5rem;">
                    <li>Clique em "Criar Pedido de Teste"</li>
                    <li>Clique no botão verde "Ver na Página Principal Agora"</li>
                    <li>Teste as abas, filtros e o botão "Ver Detalhes"</li>
                </ol>
            </div>
        </div>

        <!-- Seção 3: Lista de Pedidos -->
        <div class="teste-section">
            <h2>📦 Pedidos Existentes</h2>
            
            <div class="pedidos-teste">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Data</th>
                            <th>Cliente</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th>Itens</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($pedido = mysqli_fetch_assoc($result_pedidos)): ?>
                        <tr>
                            <td>#<?= $pedido['id'] ?></td>
                            <td><?= date('d/m/Y', strtotime($pedido['data_pedido'])) ?></td>
                            <td><?= htmlspecialchars($pedido['cliente_nome']) ?></td>
                            <td>R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></td>
                            <td>
                                <span class="status-badge" style="background-color: #007bff;">
                                    <?= htmlspecialchars($pedido['status']) ?>
                                </span>
                            </td>
                            <td><?= $pedido['total_itens'] ?> itens</td>
                            <td>
                                <button class="btn-ver-detalhes" onclick="verDetalhes(<?= $pedido['id'] ?>)">
                                    Ver Detalhes
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Seção 4: Links de Teste -->
        <div class="teste-section">
            <h2>🔗 Links Úteis</h2>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="orders.php" class="btn-teste">
                    <span class="material-symbols-sharp">shopping_cart</span>
                    Página Principal de Pedidos
                </a>
                <a href="fix-database.php" class="btn-teste">
                    <span class="material-symbols-sharp">settings</span>
                    Configurar Banco
                </a>
                <a href="gestao-fluxo.php" class="btn-teste">
                    <span class="material-symbols-sharp">account_tree</span>
                    Gestão de Fluxo
                </a>
            </div>
        </div>

        <!-- Detalhes do Pedido (Modal Simples) -->
        <div id="detalhes" style="display: none; margin-top: 2rem; padding: 2rem; background: var(--color-background); border-radius: var(--border-radius-2);">
            <h3>📋 Detalhes do Pedido</h3>
            <div id="detalhes-conteudo">
                <p>Carregando...</p>
            </div>
            <button onclick="fecharDetalhes()" class="btn-teste" style="margin-top: 1rem;">
                Fechar
            </button>
        </div>
    </div>

    <script>
        function verDetalhes(pedidoId) {
            document.getElementById('detalhes').style.display = 'block';
            document.getElementById('detalhes-conteudo').innerHTML = '<p>Carregando detalhes do pedido #' + pedidoId + '...</p>';
            
            // Simular carregamento
            setTimeout(() => {
                document.getElementById('detalhes-conteudo').innerHTML = `
                    <p><strong>Pedido ID:</strong> #${pedidoId}</p>
                    <p><strong>Status:</strong> Em teste</p>
                    <p><strong>Observação:</strong> Esta é uma visualização de teste. Para ver todos os detalhes, use a página principal de pedidos.</p>
                    <p><strong>Próximo passo:</strong> Teste o botão "Ver Detalhes" na página principal para ver o modal completo!</p>
                `;
            }, 1000);
        }
        
        function fecharDetalhes() {
            document.getElementById('detalhes').style.display = 'none';
        }
        
        // Auto refresh da página após criar pedido
        <?php if (isset($mensagem) && strpos($mensagem, '✅') !== false): ?>
            setTimeout(() => {
                window.scrollTo(0, 0);
            }, 500);
        <?php endif; ?>
    </script>
</body>
</html>