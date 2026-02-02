<?php
session_start();

// Registrar log de logout antes de destruir a sessão
if (isset($_SESSION['usuario_logado'])) {
    require_once '../src/php/auto_log.php';
    registrar_log($conexao, "saiu do sistema");
}

// Destruir todas as variáveis de sessão
$_SESSION = array();

// Se for desejado matar a sessão, também destrua o cookie de sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir a sessão
session_destroy();

// Remover cookie de "lembrar-me" se existir
if (isset($_COOKIE['email_lembrado'])) {
    setcookie('email_lembrado', '', time() - 3600, '/');
}

$_SESSION['mensagem'] = 'Logout realizado com sucesso!';
header('Location: login.php');
exit();
?>
