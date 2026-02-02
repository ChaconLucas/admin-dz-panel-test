<?php
// Função para carregar configurações
function carregarConfig() {
    $config = [];
    
    // Tenta carregar do .env primeiro
    if (file_exists(__DIR__ . '/../.env')) {
        $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) continue;
            if (strpos($line, '=') === false) continue;
            list($key, $value) = explode('=', $line, 2);
            $config[trim($key)] = trim($value);
        }
    }
    
    // Fallback para valores padrão (apenas se não existir no .env)
    if (!isset($config['GROQ_API_KEY']) || $config['GROQ_API_KEY'] === 'your_groq_api_key_here') {
        $config['GROQ_API_KEY'] = ''; // Vazio = API desabilitada
    }
    
    if (!isset($config['DB_HOST'])) {
        $config['DB_HOST'] = 'localhost';
        $config['DB_NAME'] = 'teste_dz';
        $config['DB_USER'] = 'root';
        $config['DB_PASS'] = '';
    }
    
    return $config;
}

$config = carregarConfig();
define('GROQ_API_KEY', $config['GROQ_API_KEY']);
define('DB_HOST', $config['DB_HOST']);
define('DB_NAME', $config['DB_NAME']);
define('DB_USER', $config['DB_USER']);
define('DB_PASS', $config['DB_PASS']);
?>