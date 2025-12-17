<?php
session_start();

// Se já estiver logado, redireciona para o dashboard
if (isset($_SESSION['usuario_logado'])) {
    header('Location: ../src/php/dashboard/index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Login D&Z</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" type="image/png" href="../assets/images/logodz.png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        .login-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 30px;
            display: block;
            border-radius: 50%;
        }
        .login-title {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
            font-weight: 600;
        }
        .form-control {
            border: none;
            border-bottom: 2px solid #e9ecef;
            border-radius: 0;
            padding: 15px 0;
            background: transparent;
        }
        .form-control:focus {
            border-bottom-color: #667eea;
            box-shadow: none;
            background: transparent;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 12px;
            color: white;
            font-weight: 600;
            width: 100%;
            margin-top: 20px;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .input-group {
            position: relative;
            margin-bottom: 25px;
        }
        .input-group i {
            position: absolute;
            left: 0;
            top: 15px;
            color: #999;
        }
        .input-group .form-control {
            padding-left: 30px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <img src="../assets/images/logodz.png" alt="D&Z Logo" class="login-logo" />
        
        <h2 class="login-title">Login</h2>
        
        <!-- Exibir mensagens de erro/sucesso -->
        <?php if (isset($_SESSION['mensagem'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['mensagem']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['mensagem']); ?>
        <?php endif; ?>

        <form action="validar-login.php" method="POST">
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="email" name="email" class="form-control" placeholder="E-mail" required>
            </div>

            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="senha" class="form-control" placeholder="Senha" required>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="lembrar" id="lembrar">
                <label class="form-check-label" for="lembrar">
                    Lembrar-me
                </label>
            </div>

            <button type="submit" name="btn_login" class="btn btn-login">
                <i class="fas fa-sign-in-alt me-2"></i>Entrar
            </button>
        </form>

        <div class="text-center mt-4">
            <a href="#" class="text-muted">Esqueceu sua senha?</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
