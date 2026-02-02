<?php
/**
 * Arquivo de entrada principal do sistema
 * Redireciona para o login ou dashboard se já logado
 */
session_start();

if (isset($_SESSION['usuario_logado'])) {
    // Se já está logado, vai para o dashboard
    header('Location: src/php/dashboard/index.php');
} else {
    // Se não está logado, vai para o login
    header('Location: PHP/login.php');
}
exit();
?>
