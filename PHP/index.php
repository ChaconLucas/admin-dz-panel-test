<?php
session_start();
require 'conexao.php';
require 'verificar-login.php';
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuário</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  </head>
  <body>
    <?php include('navbar.php'); ?>
    <div class="container mt-4">
      <?php include('mensagem.php'); ?>
      <div class="row">
        <div class="col-md-12">
          <div class="card">
            <div class="card-header">
              <h4>Lista de Usuários
              <a href="usuario-create.php" class="btn btn-primary float-end">Adicionar Usuário</a>
              </h4>
            </div>
            <div class="card-body">
              <table class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                    $sql = 'SELECT * FROM teste_dz';
                    $usuarios = mysqli_query($conexao, $sql);
                  if (mysqli_num_rows($usuarios) > 0){
                  foreach ($usuarios as $usuario) {
                  ?>
                 <tr>
                    <td><?= $usuario['id']; ?></td>
                    <td><?= $usuario['nome']; ?></td>
                    <td><?= $usuario['email']; ?></td>
                    <td><?= date('d/m/Y', strtotime($usuario['data_nascimento'])); ?></td>
                    <td>
                      <a href="usuario-view.php?id=<?= $usuario['id']; ?>" class="btn btn-secondary btn-sm"><span class="bi-eye-fill"></span>&nbsp; Visualizar</a>
                      <a href="usuario-edit.php?id=<?= $usuario['id']; ?>" class="btn btn-success btn-sm"><span class="bi-pencil-fill"></span>&nbsp; Editar</a>
                      <form action="acoes.php" method="POST" class="d-inline">
                        <button onClick="return confirm('Tem certeza que deseja excluir este usuário?');" type="submit" name="delete_usuario" value="<?= $usuario['id']; ?>" class="btn btn-danger btn-sm"><span class="bi-trash-fill"></span>&nbsp; Excluir</button>
                      </form>
                    </td>
                 </tr>
                 <?php
                      }
                    } else {
                      echo '<h5>Nenhum usuário encontrado.</h5>';
                    }
                  ?>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
  </body>
</html>
