<?php
session_start();
require_once '../sistema.php';
// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../../../PHP/login.php');
    exit();
}

// Incluir contador de mensagens
require_once 'helper-contador.php';

// Declarar conexão global
global $conexao;

// Processar ações CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_usuario'])) {
        $nome = mysqli_real_escape_string($conexao, trim($_POST['nome']));
        $email = mysqli_real_escape_string($conexao, trim($_POST['email']));
        $senha = password_hash(trim($_POST['senha']), PASSWORD_DEFAULT);
        $data_nascimento = mysqli_real_escape_string($conexao, trim($_POST['data_nascimento']));
        
        $sql = "INSERT INTO teste_dz (nome, email, senha, data_nascimento) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($stmt, "ssss", $nome, $email, $senha, $data_nascimento);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['mensagem'] = 'Usuário criado com sucesso!';
        } else {
            $_SESSION['mensagem'] = 'Erro ao criar usuário.';
        }
    }
    
    if (isset($_POST['edit_usuario'])) {
        $id = (int)$_POST['id'];
        $nome = mysqli_real_escape_string($conexao, trim($_POST['nome']));
        $email = mysqli_real_escape_string($conexao, trim($_POST['email']));
        $data_nascimento = mysqli_real_escape_string($conexao, trim($_POST['data_nascimento']));
        
        if (!empty($_POST['senha'])) {
            $senha = password_hash(trim($_POST['senha']), PASSWORD_DEFAULT);
            $sql = "UPDATE teste_dz SET nome = ?, email = ?, senha = ?, data_nascimento = ? WHERE id = ?";
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, "ssssi", $nome, $email, $senha, $data_nascimento, $id);
        } else {
            $sql = "UPDATE teste_dz SET nome = ?, email = ?, data_nascimento = ? WHERE id = ?";
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, "sssi", $nome, $email, $data_nascimento, $id);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['mensagem'] = 'Usuário atualizado com sucesso!';
        } else {
            $_SESSION['mensagem'] = 'Erro ao atualizar usuário.';
        }
    }
    
    if (isset($_POST['delete_usuario'])) {
        $id = (int)$_POST['id'];
        
        // Não permitir que o usuário delete a si mesmo
        if ($id != $_SESSION['usuario_id']) {
            $sql = "DELETE FROM teste_dz WHERE id = ?";
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, "i", $id);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['mensagem'] = 'Usuário removido com sucesso!';
            } else {
                $_SESSION['mensagem'] = 'Erro ao remover usuário.';
            }
        } else {
            $_SESSION['mensagem'] = 'Você não pode excluir sua própria conta!';
        }
    }
}

// Buscar usuários
$sql = 'SELECT * FROM teste_dz ORDER BY created_at DESC';
$usuarios = mysqli_query($conexao, $sql);
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
        <h1>Configurações</h1>
        <div class="date">
          <input type="date" />
        </div>
        
        <div class="insights">
          <p>Aqui serão exibidas as configurações do sistema.</p>
          <!-- Adicione o conteúdo específico da página de configurações aqui -->
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








