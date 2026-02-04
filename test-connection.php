<?php
// Teste de conexão para debugging
require_once 'config/config.php';

echo "<h2>Teste de Configuração do Sistema</h2>";

echo "<h3>1. Configurações do Banco:</h3>";
echo "DB_HOST: " . DB_HOST . "<br>";
echo "DB_NAME: " . DB_NAME . "<br>";
echo "DB_USER: " . DB_USER . "<br>";

echo "<h3>2. Teste de Conexão:</h3>";
try {
    $conexao = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conexao->connect_error) {
        echo "❌ Erro de conexão: " . $conexao->connect_error;
    } else {
        echo "✅ Conexão com banco de dados estabelecida com sucesso!<br>";
        echo "Versão do MySQL: " . $conexao->server_info . "<br>";
        
        // Testar se as tabelas existem
        echo "<h3>3. Verificação de Tabelas:</h3>";
        
        $tables = ['configuracoes', 'message_templates'];
        foreach ($tables as $table) {
            $result = $conexao->query("SHOW TABLES LIKE '$table'");
            if ($result && $result->num_rows > 0) {
                echo "✅ Tabela '$table' existe<br>";
                
                // Contar registros
                $count_result = $conexao->query("SELECT COUNT(*) as total FROM $table");
                if ($count_result) {
                    $count = $count_result->fetch_assoc()['total'];
                    echo "&nbsp;&nbsp;&nbsp;→ $count registros<br>";
                }
            } else {
                echo "❌ Tabela '$table' não existe<br>";
            }
        }
    }
} catch (Exception $e) {
    echo "❌ Erro ao conectar: " . $e->getMessage();
}

echo "<h3>4. Informações do Sistema:</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Current Directory: " . __DIR__ . "<br>";
echo "File exists config.php: " . (file_exists('config/config.php') ? 'Sim' : 'Não') . "<br>";

?>