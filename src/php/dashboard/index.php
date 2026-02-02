<?php
session_start();
// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../../../PHP/login.php');
    exit();
}

/*
===============================================
SQL PARA CRIAÇÃO DAS TABELAS DO DASHBOARD
Execute este script no MySQL/phpMyAdmin na segunda-feira
===============================================

-- Tabela de Logs de Administradores
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    admin_nome VARCHAR(255) NOT NULL,
    acao TEXT NOT NULL,
    data_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45)
);

-- Tabela de Clientes
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    telefone VARCHAR(20),
    endereco TEXT,
    cidade VARCHAR(100),
    estado VARCHAR(2),
    cep VARCHAR(10),
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo'
);

-- Tabela de Produtos 
CREATE TABLE IF NOT EXISTS produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT,
    preco DECIMAL(10,2) NOT NULL,
    estoque INT DEFAULT 0,
    categoria VARCHAR(100),
    imagem VARCHAR(255),
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Pedidos
CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    status ENUM('pendente', 'processando', 'enviado', 'entregue', 'cancelado') DEFAULT 'pendente',
    data_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_entrega DATE NULL,
    observacoes TEXT,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
);

-- Tabela de Itens do Pedido
CREATE TABLE IF NOT EXISTS itens_pedido (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    produto_id INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
);
===============================================
*/

// Calcular mensagens não lidas (TABELA QUE JÁ EXISTE)
require_once '../sistema.php';
global $conexao;

// ===============================================
// FUNÇÕES AUXILIARES
// ===============================================

/**
 * Registrar log de atividade do administrador
 */
function registrar_log($conexao, $mensagem) {
    try {
        // Capturar dados da sessão
        $admin_id = $_SESSION['usuario_logado'] ?? 0;
        $admin_nome = $_SESSION['nome_usuario'] ?? 'Admin';
        
        // Capturar IP do usuário
        $ip_address = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip_address = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        
        // Inserir no banco
        $stmt = $conexao->prepare("INSERT INTO admin_logs (admin_id, admin_nome, acao, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $admin_id, $admin_nome, $mensagem, $ip_address);
        $stmt->execute();
        $stmt->close();
        
    } catch (Exception $e) {
        // Silencioso se tabela não existir ainda
        error_log("Erro ao registrar log: " . $e->getMessage());
    }
}

/**
 * Converter timestamp em formato amigável
 */
function tempo_amigavel($timestamp) {
    $agora = new DateTime();
    $data_log = new DateTime($timestamp);
    $diferenca = $agora->diff($data_log);
    
    if ($diferenca->d > 0) {
        if ($diferenca->d == 1) {
            return "Ontem às " . $data_log->format('H:i');
        } else {
            return $data_log->format('d/m') . " às " . $data_log->format('H:i');
        }
    } elseif ($diferenca->h > 0) {
        return "há " . $diferenca->h . " hora" . ($diferenca->h > 1 ? "s" : "");
    } elseif ($diferenca->i > 0) {
        return "há " . $diferenca->i . " minuto" . ($diferenca->i > 1 ? "s" : "");
    } else {
        return "Agora mesmo";
    }
}

$nao_lidas = 0;
try {
    $result = $conexao->query("SELECT COUNT(*) as total FROM mensagens WHERE lida = FALSE AND remetente != 'admin'");
    $nao_lidas = $result ? $result->fetch_assoc()['total'] : 0;
} catch (Exception $e) {
    error_log("Erro ao contar mensagens: " . $e->getMessage());
}

// ===============================================
// VARIÁVEIS GLOBAIS DO DASHBOARD
// (Valores padrão até criar as tabelas na segunda)
// ===============================================

// Vendas
$vendas_hoje = 0;
$vendas_total = 0;

// Pedidos
$pedidos_hoje = 0;
$pedidos_total = 0;

// Clientes
$clientes_ativos = 0;
$clientes_total = 0;

// Produtos sem estoque (para sidebar)
$produtos_sem_estoque = 1; // Valor de exemplo para mostrar "ATENÇÃO"

// Pedidos pendentes (para sidebar)
$pedidos_pendentes = 0;

// Dados para gráfico de performance (últimos 7 dias - todos zerados)
$labels_7_dias = [];
$vendas_7_dias = [];
for ($i = 6; $i >= 0; $i--) {
    $labels_7_dias[] = date('d/m', strtotime("-$i days"));
    $vendas_7_dias[] = 0; // Zero até ter dados reais
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

// Buscar logs de atividade dos administradores (últimos 3)
$admin_logs = [];
try {
    $result = $conexao->query("SELECT admin_nome, acao, data_hora FROM admin_logs ORDER BY data_hora DESC LIMIT 3");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $admin_logs[] = $row;
        }
    }
} catch (Exception $e) {
    // Tabela ainda não existe - silencioso
    error_log("Tabela admin_logs não encontrada: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../css/dashboard.css" />
    <link rel="stylesheet" href="../../css/dashboard-sections.css" />
    <link rel="stylesheet" href="../../css/dashboard-cards.css" />
    <link
      href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp"
      rel="stylesheet"
    />
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <title>Responsive Dashboard</title>
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
          <a href="index.php" class="active" id="dashboard-link">
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

          <a href="gestao-fluxo.php" id="gestao-fluxo-link">
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
        <h1>Dashboard</h1>
        <!-- Dashboard Cards -->
        <div class="insights">
          <div class="sales">
            <span class="material-symbols-sharp">trending_up</span>
            <div class="middle">
              <div class="left">
                <h3>Total Vendas</h3>
                <h1>R$ <?php echo number_format($vendas_hoje, 2, ',', '.'); ?></h1>
              </div>
              <div class="progress">
                <canvas id="salesChart" width="92" height="92"></canvas>
                <div class="number">
                  <p><?php echo $vendas_total > 0 ? round(($vendas_hoje / $vendas_total) * 100) : 0; ?>%</p>
                </div>
              </div>
            </div>
            <small class="text-muted">Últimas 24 horas</small>
          </div>
          
          <div class="expenses">
            <span class="material-symbols-sharp">shopping_cart</span>
            <div class="middle">
              <div class="left">
                <h3>Pedidos</h3>
                <h1><?php echo $pedidos_hoje; ?></h1>
              </div>
              <div class="progress">
                <canvas id="ordersChart" width="92" height="92"></canvas>
                <div class="number">
                  <p><?php echo $pedidos_total > 0 ? round(($pedidos_hoje / $pedidos_total) * 100) : 0; ?>%</p>
                </div>
              </div>
            </div>
            <small class="text-muted">Pedidos hoje</small>
          </div>
          
          <div class="income">
            <span class="material-symbols-sharp">group</span>
            <div class="middle">
              <div class="left">
                <h3>Clientes</h3>
                <h1><?php echo $clientes_ativos; ?></h1>
              </div>
              <div class="progress">
                <canvas id="clientsChart" width="92" height="92"></canvas>
                <div class="number">
                  <p><?php echo $clientes_total > 0 ? round(($clientes_ativos / $clientes_total) * 100) : 0; ?>%</p>
                </div>
              </div>
            </div>
            <small class="text-muted">Clientes ativos</small>
          </div>
        </div>
        
        <!-- Gráfico de Performance -->
        <div class="chart-container">
          <h2>Últimos 7 dias</h2>
          <canvas id="performanceChart" width="400" height="200"></canvas>
        </div>
        <!---------------------------FINAL INSIGHTS---------------------------->
        <div class="recent-orders">
          <h2>Pedidos Recentes</h2>
          <?php
          // Buscar pedidos do banco de dados
          $pedidos = [];
          try {
              $result = $conexao->query("SELECT * FROM pedidos ORDER BY created_at DESC LIMIT 6");
              if ($result) {
                  while ($row = $result->fetch_assoc()) {
                      $pedidos[] = $row;
                  }
              }
          } catch (Exception $e) {
              // Silencioso - se não houver tabela pedidos, $pedidos fica vazio
          }
          
          if (empty($pedidos)): ?>
            <div class="no-orders">
              <span class="material-symbols-sharp">inbox</span>
              <p>Nenhum pedido registrado no momento</p>
            </div>
          <?php else: ?>
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Cliente</th>
                  <th>Produto</th>
                  <th>Valor</th>
                  <th>Status</th>
                  <th>Ação</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pedidos as $pedido): ?>
                <tr>
                  <td>#<?php echo $pedido['id']; ?></td>
                  <td><?php echo htmlspecialchars($pedido['cliente_nome'] ?? 'N/A'); ?></td>
                  <td><?php echo htmlspecialchars($pedido['produto_nome'] ?? 'N/A'); ?></td>
                  <td>R$ <?php echo number_format($pedido['valor'] ?? 0, 2, ',', '.'); ?></td>
                  <td class="<?php echo $pedido['status'] == 'aprovado' ? 'success' : ($pedido['status'] == 'pendente' ? 'warning' : 'danger'); ?>">
                    <?php echo ucfirst($pedido['status'] ?? 'pendente'); ?>
                  </td>
                  <td class="primary">Detalhes</td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <a href="orders.php">Mostrar Todos</a>
          <?php endif; ?>
        </div>

        <!-- Seção de Vendedores -->
        <div id="vendedores-section" class="dashboard-section" style="display: none;">
          <h1>Vendedores</h1>
          <div class="date">
            <input type="date" />
          </div>

          
          <!-- Insights dos Vendedores -->
          <div class="insights">
            <div class="sales">
              <span class="material-symbols-sharp">group</span>
              <div class="middle">
                <div class="left">
                  <h3>Total Vendedores</h3>
                  <h1><?= $total_vendedores_ativas ?></h1>
                </div>
                <div class="progress">
                  <svg>
                    <circle cx="38" cy="38" r="36"></circle>
                  </svg>
                  <div class="number">
                    <p>100%</p>
                  </div>
                </div>
              </div>
              <small class="text-muted">Vendedores ativos</small>
            </div>
            
            <div class="expenses">
              <span class="material-symbols-sharp">handshake</span>
              <div class="middle">
                <div class="left">
                  <h3>Total Leads</h3>
                  <h1><?php 
                    $total_leads = 0;
                    foreach($vendedores as $v) {
                      $total_leads += $v['total_leads'] ?? 0;
                    }
                    echo $total_leads;
                  ?></h1>
                </div>
                <div class="progress">
                  <svg>
                    <circle cx="38" cy="38" r="36"></circle>
                  </svg>
                  <div class="number">
                    <p>90%</p>
                  </div>
                </div>
              </div>
              <small class="text-muted">Leads distribuídos</small>
            </div>
            
            <div class="income">
              <span class="material-symbols-sharp">trending_up</span>
              <div class="middle">
                <div class="left">
                  <h3>Performance</h3>
                  <h1>85%</h1>
                </div>
                <div class="progress">
                  <svg>
                    <circle cx="38" cy="38" r="36"></circle>
                  </svg>
                  <div class="number">
                    <p>85%</p>
                  </div>
                </div>
              </div>
              <small class="text-muted">Taxa de conversão</small>
            </div>
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
            <h2>Gerenciar Vendedores</h2>
            
            <!-- Formulário de Edição (oculto por padrão) -->
            <div id="edit-vendedor-form" style="display: none; background: var(--color-white); border: 2px solid var(--color-warning); border-radius: var(--card-border-radius); padding: var(--card-padding); margin-bottom: 1rem;">
              <h3 style="margin-bottom: 1rem; color: var(--color-warning);">Editar Vendedor</h3>
              <form method="POST">
                <input type="hidden" name="action" value="edit_vendedor">
                <input type="hidden" name="id" id="edit_vendedor_id">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                  <div>
                    <label style="display: block; font-weight: 600; color: var(--color-dark); margin-bottom: 0.5rem;">Nome Completo</label>
                    <input type="text" name="nome" id="edit_vendedor_nome" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); background: var(--color-white);" required>
                  </div>
                  
                  <div>
                    <label style="display: block; font-weight: 600; color: var(--color-dark); margin-bottom: 0.5rem;">WhatsApp</label>
                    <input type="text" name="whatsapp" id="edit_vendedor_whatsapp" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); background: var(--color-white);" required>
                  </div>
                  
                  <div>
                    <label style="display: block; font-weight: 600; color: var(--color-dark); margin-bottom: 0.5rem;">Email</label>
                    <input type="email" name="email" id="edit_vendedor_email" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); background: var(--color-white);">
                  </div>
                </div>
                
                <div style="display: flex; gap: 0.75rem;">
                  <button type="submit" style="background: var(--color-success); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: var(--border-radius-1); font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                    <span class="material-symbols-sharp">save</span>
                    Salvar
                  </button>
                  <button type="button" onclick="cancelEditVendedor()" style="background: var(--color-dark-variant); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: var(--border-radius-1); font-weight: 600; cursor: pointer;">Cancelar</button>
                </div>
              </form>
            </div>

            <table>
              <thead>
                <tr>
                  <th>Nome do Vendedor</th>
                  <th>WhatsApp</th>
                  <th>Email</th>
                  <th>Leads</th>
                  <th>Status</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($vendedores)): ?>
                <tr>
                  <td colspan="6" style="text-align: center; padding: 2rem; color: var(--color-info-dark);">
                    <span class="material-symbols-sharp" style="font-size: 3rem; display: block; margin-bottom: 1rem; color: var(--color-info-light);">group</span>
                    Nenhum vendedor cadastrado
                  </td>
                </tr>
                <?php else: ?>
                  <?php foreach ($vendedores as $vendedor): ?>
                  <tr>
                    <td style="font-weight: 600;"><?= htmlspecialchars($vendedor['nome']) ?></td>
                    <td><?= $vendedor['whatsapp'] ?></td>
                    <td><?= htmlspecialchars($vendedor['email']) ?: '-' ?></td>
                    <td>
                      <span style="background: var(--color-primary); color: white; padding: 0.25rem 0.5rem; border-radius: var(--border-radius-1); font-size: 0.8rem; font-weight: 600;">
                        <?= $vendedor['total_leads'] ?? 0 ?> leads
                      </span>
                    </td>
                    <td class="success">Ativo</td>
                    <td>
                      <div style="display: flex; gap: 0.5rem;">
                        <button onclick="editVendedorInline(<?= htmlspecialchars(json_encode($vendedor)) ?>)" 
                                style="background: var(--color-warning); color: white; border: none; padding: 0.5rem; border-radius: var(--border-radius-1); cursor: pointer; display: flex; align-items: center;" 
                                title="Editar">
                          <span class="material-symbols-sharp" style="font-size: 1rem;">edit</span>
                        </button>
                        <button onclick="deleteVendedorInline(<?= $vendedor['id'] ?>, '<?= htmlspecialchars($vendedor['nome']) ?>')" 
                                style="background: var(--color-danger); color: white; border: none; padding: 0.5rem; border-radius: var(--border-radius-1); cursor: pointer; display: flex; align-items: center;" 
                                title="Excluir">
                          <span class="material-symbols-sharp" style="font-size: 1rem;">delete</span>
                        </button>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
            
            <!-- Formulário de Adicionar Vendedor -->
            <div style="background: var(--color-white); border-radius: var(--card-border-radius); padding: var(--card-padding); margin-top: 2rem; box-shadow: var(--box-shadow);">
              <h3 style="margin-bottom: 1rem; color: var(--color-dark);">
                <span class="material-symbols-sharp" style="vertical-align: middle; margin-right: 0.5rem;">person_add</span>
                Adicionar Novo Vendedor
              </h3>
              
              <form method="POST" style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 1rem; align-items: end;">
                <input type="hidden" name="action" value="add_vendedor">
                
                <div>
                  <label style="display: block; font-weight: 600; color: var(--color-dark); margin-bottom: 0.5rem; font-size: 0.9rem;">Nome Completo</label>
                  <input type="text" name="nome" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); background: var(--color-white);" placeholder="Ex: João Silva" required>
                </div>
                
                <div>
                  <label style="display: block; font-weight: 600; color: var(--color-dark); margin-bottom: 0.5rem; font-size: 0.9rem;">WhatsApp</label>
                  <input type="text" name="whatsapp" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); background: var(--color-white);" placeholder="11999999999" required>
                </div>
                
                <div>
                  <label style="display: block; font-weight: 600; color: var(--color-dark); margin-bottom: 0.5rem; font-size: 0.9rem;">Email</label>
                  <input type="email" name="email" style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-info-light); border-radius: var(--border-radius-1); background: var(--color-white);" placeholder="joao@exemplo.com">
                </div>
                
                <button type="submit" style="background: var(--color-success); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: var(--border-radius-1); font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap;">
                  <span class="material-symbols-sharp">person_add</span>
                  Adicionar
                </button>
              </form>
            </div>
            
            <a href="index.php" style="display: inline-flex; align-items: center; gap: 0.5rem; margin-top: 1rem; color: var(--color-primary);">
              <span class="material-symbols-sharp">arrow_back</span>
              Voltar ao Dashboard
            </a>
        </div>

        <!-- Outras seções podem ser adicionadas aqui -->
        <div id="clientes-section" class="dashboard-section" style="display: none;">
          <h1>Clientes</h1>
          <p>Em desenvolvimento...</p>
        </div>

        <div id="pedidos-section" class="dashboard-section" style="display: none;">
          <h1>Pedidos</h1>
          <p>Em desenvolvimento...</p>
        </div>

        <div id="graficos-section" class="dashboard-section" style="display: none;">
          <h1>Gráficos</h1>
          <p>Em desenvolvimento...</p>
        </div>

        <div id="mensagens-section" class="dashboard-section" style="display: none;">
          <h1>Mensagens</h1>
          <p>Em desenvolvimento...</p>
        </div>

        <div id="produtos-section" class="dashboard-section" style="display: none;">
          <h1>Produtos</h1>
          <p>Em desenvolvimento...</p>
        </div>

        <div id="configuracoes-section" class="dashboard-section" style="display: none;">
          <h1>Configurações</h1>
          <p>Em desenvolvimento...</p>
        </div>

        <div id="revendedores-section" class="dashboard-section" style="display: none;">
          <h1>Revendedores</h1>
          <p>
            <a href="revendedores.php">Gerenciar Leads</a> | 
            <a href="gerenciar-vendedoras.php">Gerenciar Vendedoras</a> | 
            <a href="chat-cadastro-revendedor.php">Cadastro Chat</a>
          </p>
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
          <h2>Log de Atividades do Admin</h2>
          <div class="updates">
            <?php if (!empty($admin_logs)): ?>
              <?php foreach ($admin_logs as $log): ?>
                <div class="update">
                  <div class="profile-photo">
                    <img src="../../../assets/images/logo.png" alt="" />
                  </div>
                  <div class="message">
                    <p>
                      <b><?php echo htmlspecialchars($log['admin_nome']); ?></b> <?php echo htmlspecialchars($log['acao']); ?>
                    </p>
                    <small class="text-muted">
                      <?php echo tempo_amigavel($log['data_hora']); ?>
                    </small>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="update">
                <div class="profile-photo">
                  <img src="../../../assets/images/logo.png" alt="" />
                </div>
                <div class="message">
                  <p><b>Sistema</b> Nenhum log de administrador encontrado</p>
                  <small class="text-muted">Agora mesmo</small>
                </div>
              </div>
            <?php endif; ?>
          </div>
          <div class="view-all-logs">
            <a href="all-logs.php" class="btn-view-all">
              <span class="material-symbols-sharp">history</span>
              Ver Todos os Logs
            </a>
          </div>
        </div>
        <!--------------------------------FINAL ULTIMAS ATT--------------------------->                
        <div class="sales-analytics">
          <h2>Informações Operacionais</h2>
          <div class="item offline">
            <div class="icon">
              <span class="material-symbols-sharp">inventory_2</span>
            </div>
            <div class="right">
              <div class="info">
                <h3>PRODUTOS SEM ESTOQUE</h3>
                <small class="text-muted">Produtos em falta <span class="number"><?php echo $produtos_sem_estoque; ?></span></small>
              </div>
              <h5 class="danger"><?php echo $produtos_sem_estoque > 0 ? 'ATENÇÃO' : 'OK'; ?></h5>
            </div>
          </div>
          <div class="item customers">
            <div class="icon">
              <span class="material-symbols-sharp">mail</span>
            </div>
            <div class="right">
              <div class="info">
                <h3>MENSAGENS PENDENTES</h3>
                <small class="text-muted">Aguardando resposta <span class="number"><?php echo $nao_lidas; ?></span></small>
              </div>
              <h5 class="<?php echo $nao_lidas > 0 ? 'warning' : 'success'; ?>"><?php echo $nao_lidas > 0 ? 'PENDENTE' : 'OK'; ?></h5>
            </div>
          </div>
          <div class="item online">
            <div class="icon">
              <span class="material-symbols-sharp">pending_actions</span>
            </div>
            <div class="right">
              <div class="info">
                <h3>PEDIDOS PENDENTES</h3>
                <small class="text-muted">Aguardando processamento <span class="number"><?php echo $pedidos_pendentes; ?></span></small>
              </div>
              <h5 class="<?php echo $pedidos_pendentes > 0 ? 'warning' : 'success'; ?>"><?php echo $pedidos_pendentes > 0 ? 'AGUARDANDO' : 'OK'; ?></h5>
            </div>
          </div>
        </div>
      </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../../js/dashboard.js"></script>
<script src="../../js/contador-auto.js"></script>

<script>
// Scripts essenciais mantidos
// Garantir que o tema dark funcione em todas as páginas
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('darkTheme');
    if (savedTheme === 'true') {
        document.body.classList.add('dark-theme-variables');
        console.log('Tema dark aplicado em index.php');
    }
    
    // Inicializar Chart.js
    initPerformanceChart();
    initCardCharts();
});

// Função para inicializar o gráfico de performance
function initPerformanceChart() {
    const ctx = document.getElementById('performanceChart').getContext('2d');
    
    // Dados reais do PHP (últimos 7 dias)
    const labels = <?php echo json_encode($labels_7_dias); ?>;
    const data = <?php echo json_encode($vendas_7_dias); ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Vendas (R$)',
                data: data,
                borderColor: '#ff00d4',
                backgroundColor: 'rgba(255, 0, 212, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#ff00d4',
                pointBorderColor: '#ff00d4',
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'R$ ' + context.parsed.y.toLocaleString('pt-BR', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#a3bdcc',
                        callback: function(value) {
                            return 'R$ ' + value.toLocaleString('pt-BR');
                        }
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#a3bdcc'
                    }
                }
            },
            elements: {
                point: {
                    hoverBackgroundColor: '#ff00d4'
                }
            }
        }
    });
}

// Função para criar gráficos donut nos cards
function initCardCharts() {
    // Dados PHP para os gráficos
    const vendas_hoje = <?php echo $vendas_hoje; ?>;
    const vendas_total = <?php echo $vendas_total; ?>;
    const pedidos_hoje = <?php echo $pedidos_hoje; ?>;
    const pedidos_total = <?php echo $pedidos_total; ?>;
    const clientes_ativos = <?php echo $clientes_ativos; ?>;
    const clientes_total = <?php echo $clientes_total; ?>;
    
    // Calcular percentuais (garantir que não seja 0/0)
    const vendas_percent = vendas_total > 0 ? Math.min((vendas_hoje / vendas_total) * 100, 100) : 0;
    const pedidos_percent = pedidos_total > 0 ? Math.min((pedidos_hoje / pedidos_total) * 100, 100) : 0;
    const clientes_percent = clientes_total > 0 ? Math.min((clientes_ativos / clientes_total) * 100, 100) : 0;

    // Gráfico de Vendas
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    new Chart(salesCtx, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [vendas_percent, 100 - vendas_percent],
                backgroundColor: ['#ff00d4', 'rgba(255, 255, 255, 0.1)'],
                borderWidth: 0,
                cutout: '70%'
            }]
        },
        options: {
            responsive: false,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { enabled: false }
            }
        }
    });

    // Gráfico de Pedidos
    const ordersCtx = document.getElementById('ordersChart').getContext('2d');
    new Chart(ordersCtx, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [pedidos_percent, 100 - pedidos_percent],
                backgroundColor: ['#eb2a2a', 'rgba(255, 255, 255, 0.1)'],
                borderWidth: 0,
                cutout: '70%'
            }]
        },
        options: {
            responsive: false,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { enabled: false }
            }
        }
    });

    // Gráfico de Clientes
    const clientsCtx = document.getElementById('clientsChart').getContext('2d');
    new Chart(clientsCtx, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [clientes_percent, 100 - clientes_percent],
                backgroundColor: ['#41f1b6', 'rgba(255, 255, 255, 0.1)'],
                borderWidth: 0,
                cutout: '70%'
            }]
        },
        options: {
            responsive: false,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { enabled: false }
            }
        }
    });
}
</script>
  </body>
</html>






