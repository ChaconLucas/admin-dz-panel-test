<?php
session_start();
require 'conexao.php';

if (isset($_POST['btn_login'])) {
    $email = mysqli_real_escape_string($conexao, trim($_POST['email']));
    $senha = trim($_POST['senha']);
    
    // Validações básicas
    if (empty($email) || empty($senha)) {
        $_SESSION['mensagem'] = 'Por favor, preencha todos os campos.';
        header('Location: login.php');
        exit();
    }
    
    // Buscar usuário no banco
    $sql = "SELECT * FROM teste_dz WHERE email = ?";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($resultado) > 0) {
        $usuario = mysqli_fetch_assoc($resultado);
        
        // Verificar senha (assumindo que está armazenada como hash)
        // Se a senha não estiver hasheada, use: if ($senha === $usuario['senha'])
        if (password_verify($senha, $usuario['senha']) || $senha === $usuario['senha']) {
            // Login bem-sucedido
            $_SESSION['usuario_logado'] = true;
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['nome_usuario'] = $usuario['nome']; // Para os logs
            
            // Registrar log de login
            require_once '../src/php/auto_log.php';
            registrar_log($conexao, "fez login no sistema");
            
            // Se marcou "lembrar-me", criar cookie
            if (isset($_POST['lembrar'])) {
                setcookie('email_lembrado', $email, time() + (30 * 24 * 60 * 60), '/'); // 30 dias
            }
            
            $_SESSION['mensagem'] = 'Login realizado com sucesso! Bem-vindo, ' . $usuario['nome'];
            header('Location: ../src/php/dashboard/index.php');
            exit();
        } else {
            $_SESSION['mensagem'] = 'Senha incorreta.';
            header('Location: login.php');
            exit();
        }
    } else {
        $_SESSION['mensagem'] = 'E-mail não encontrado.';
        header('Location: login.php');
        exit();
    }
} else {
    // Acesso direto ao arquivo
    header('Location: login.php');
    exit();
}
?>
