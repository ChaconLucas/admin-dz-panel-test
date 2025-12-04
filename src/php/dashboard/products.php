<?php
session_start();
// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../../../PHP/login.php');
    exit();}

// Incluir contador de mensagens
require_once 'helper-contador.php';
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

    <title>Customers Page</title>
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

          <a href="#">
            <span class="material-symbols-sharp">Report</span>
            <h3>Relatórios</h3>
          </a>

          <a href="settings.php">
            <span class="material-symbols-sharp">Settings</span>
            <h3>Configurações</h3>
          </a>

          <a href="addproducts.php">
            <span class="material-symbols-sharp">Add</span>
            <h3>Adicionar Produto</h3>
          </a>

          <a href="../../../PHP/logout.php">
            <span class="material-symbols-sharp">Logout</span>
            <h3>Sair</h3>
          </a>
        </div>
      </aside>

      <!----------FINAL ASIDE------------>
      <main>
        <h1>Produtos</h1>
        <div class="date">
          <input type="date" />
        </div>
        
        <div class="insights">
          <p>Aqui será exibida a lista de produtos e seu estoque.</p>
          <!-- Adicione o conteúdo específico da página de produtos aqui -->
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
 </body>
</html>









