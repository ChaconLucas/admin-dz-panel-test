<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuário</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  </head>
  <body>
    <?php include('navbar.php'); ?>
    
   <div class="container mt-5 ">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>Adicionar Usuário
                        <a href="index.php" class="btn btn-danger float-end">Voltar</a>
                    </h4>
                </div>
                <div class="card-body">
                    <form action="acoes.php" method="POST">
                        <div class="mb-3">
                            <label>Nome</label>
                            <input type="text" name="nome" class="form-control">
                        </div>    
                        <div class="mb-3">
                            <label>Email</label>
                            <input type="text" name="email" class="form-control">
                        </div>
                            <div class="mb-3">
                            <label>Data de Nascimento</label>
                            <input type="date" name="data_nascimento" class="form-control">
                        </div>
                            <div class="mb-3">
                            <label>Senha</label>
                            <input type="password" name="senha" class="form-control">
                        </div>
                        <div class="mb-3">
                            <button type="submit" name="create_usuario" class="btn btn-primary">Salvar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
   </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
  </body>
</html>
