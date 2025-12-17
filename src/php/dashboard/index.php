<?php
session_start();
// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../../../PHP/login.php');
    exit();
}

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
        <div class="date">
          <input type="date" />
        </div>
        <div class="insights">
          <div class="sales">
            <span class="material-symbols-sharp"> bar_chart_4_bars </span>
            <div class="middle">
              <div class="left">
                <h3>Total Vendas</h3>
                <h1>R$578.000,00</h1>
              </div>
              <div class="progress">
                <svg>
                  <circle cx="38" cy="38" r="36"></circle>
                </svg>
                <div class="number">
                  <p>81%</p>
                </div>
              </div>
            </div>
            <small class="text-muted">Últimas 24 horas</small>
          </div>
          <!------------------------FINAL VENDAS---------------------------->
          <div class="expenses">
            <span class="material-symbols-sharp"> Receipt_long </span>
            <div class="middle">
              <div class="left">
                <h3>Total Custos</h3>
                <h1>R$115.000,00</h1>
              </div>
              <div class="progress">
                <svg>
                  <circle cx="38" cy="38" r="36"></circle>
                </svg>
                <div class="number">
                  <p>19,9%</p>
                </div>
              </div>
            </div>
            <small class="text-muted">Últimas 24 horas</small>
          </div>
          <!------------------------FINAL CUSTOS---------------------------->
          <div class="income">
            <span class="material-symbols-sharp"> Savings </span>
            <div class="middle">
              <div class="left">
                <h3>Total Lucro</h3>
                <h1>R$463.000,00</h1>
              </div>
              <div class="progress">
                <svg>
                  <circle cx="38" cy="38" r="36"></circle>
                </svg>
                <div class="number">
                  <p>80,1%</p>
                </div>
              </div>
            </div>
            <small class="text-muted">Últimas 24 horas</small>
          </div>
          <!------------------------FINAL RENDA---------------------------->
        </div>
        <!---------------------------FINAL INSIGHTS---------------------------->
        <div class="recent-orders">
          <h2>Últimas Vendas</h2>
          <table>
            <thead>
              <tr>
                <th>Nome do Produto</th>
                <th>Número do Produto</th>
                <th>Pagaentos</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Top coat D&Z Branquinho</td>
                <td>3558</td>
                <td>Boleto</td>
                <td class="warning">Pendente</td>
                <td class="primary">Detalhes</td>
              </tr>
              <tr>
                <td>Sun 5 Original</td>
                <td>2038</td>
                <td>Pix</td>
                <td class="success">Aprovado</td>
                <td class="primary">Detalhes</td>
              </tr>
              <tr>
                <td>Coleto Oval Sioux (Off White)</td>
                <td>2100</td>
                <td>Cartão de Crédito</td>
                <td class="warning">Pendente</td>
                <td class="primary">Detalhes</td>
              </tr>
              <tr>
                <td>Top coat D&Z Branquinho</td>
                <td>3558</td>
                <td>Pix</td>
                <td class="success">Aprovado</td>
                <td class="primary">Detalhes</td>
              </tr>
              <tr>
                <td>Esmalte D&Z Coleção Luxo Cacau</td>
                <td>0820</td>
                <td>Cartão de Crédito</td>
                <td class="danger">Recusado</td>
                <td class="primary">Detalhes</td>
              </tr>
              <tr>
                <td>Motor Porquinho D&Z</td>
                <td>3888</td>
                <td>Boleto</td>
                <td class="success">Aprovado</td>
                <td class="primary">Detalhes</td>
              </tr>
            </tbody>
          </table>
          <a href="#">Mostrar Todos</a>
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
                          echo $diferenca->i . " minutos atrás";
                        }
                      ?>
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
                  <p><b>Sistema</b> Nenhum administrador encontrado</p>
                  <small class="text-muted">Agora mesmo</small>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <!--------------------------------FINAL ULTIMAS ATT--------------------------->
        <div class="sales-analytics">
          <h2>Análises de Vendas</h2>
          <div class="item online">
            <div class="icon">
              <span class="material-symbols-sharp"> shopping_cart </span>
            </div>
            <div class="right">
              <div class="info">
                <h3>PEDIDOS ONLINE</h3>
                <small class="text-muted">Últimas 24 horas</small>
              </div>
              <h5 class="success">+58%</h5>
              <h3>4872</h3>
            </div>
          </div>
          <div class="item offline">
            <div class="icon">
              <span class="material-symbols-sharp"> local_mall </span>
            </div>
            <div class="right">
              <div class="info">
                <h3>PEDIDOS OFFLINE</h3>
                <small class="text-muted">Últimas 24 horas</small>
              </div>
              <h5 class="danger">-14%</h5>
              <h3>987</h3>
            </div>
          </div>
          <div class="item customers">
            <div class="icon">
              <span class="material-symbols-sharp"> person </span>
            </div>
            <div class="right">
              <div class="info">
                <h3>NOVOS CLIENTES</h3>
                <small class="text-muted">Últimas 24 horas</small>
              </div>
              <h5 class="success">+72%</h5>
              <h3>80452</h3>
            </div>
          </div>
          <div class="item add-product">
          <div>
            <span class="material-symbols-sharp"> add </span>
            <h3>Adicionar Produto</h3>
          </div>
        </div>
      </div>
    </div>

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
});
</script>
  </body>
</html>






