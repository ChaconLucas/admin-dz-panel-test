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
          <a href="index.php" class="active">
            <span class="material-symbols-sharp">grid_view</span>
            <h3>Painel</h3>
          </a>

          <a href="customers.php">
            <span class="material-symbols-sharp">Groups</span>
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

          <a href="settings.php">
            <span class="material-symbols-sharp">Settings</span>
            <h3>Configurações</h3>
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
  </body>
</html>






