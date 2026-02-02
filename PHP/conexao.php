<?php
// Configurar fuso horário do Brasil
date_default_timezone_set('America/Sao_Paulo');

define('HOST', '127.0.0.1');
define('USUARIO', 'root');
define('SENHA', '');
define('DB', 'teste_dz');

$conexao = mysqli_connect(HOST, USUARIO, SENHA, DB) or die('Não foi possível conectar');

// Configurar timezone do MySQL para o Brasil
mysqli_query($conexao, "SET time_zone = '-03:00'");
?>
