<?php
// Configuração de Email para D&Z Admin - Integrada com Automação

// Função para buscar configurações da automação
function buscarConfiguracaoEmail() {
    try {
        require_once __DIR__ . '/../PHP/conexao.php';
        global $conexao;
        
        if (!$conexao) {
            return false;
        }
        
        // Buscar configurações SMTP da automação
        $query = "SELECT * FROM configuracoes_email WHERE ativo = 1 LIMIT 1";
        $result = mysqli_query($conexao, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }
        
        // Se não encontrar na tabela, buscar nas configurações gerais da automação
        $query_geral = "SELECT chave, valor FROM configuracoes WHERE chave IN ('smtp_host', 'smtp_porta', 'smtp_email', 'smtp_senha', 'email_remetente', 'nome_remetente')";
        $result_geral = mysqli_query($conexao, $query_geral);
        
        $config_automacao = [];
        if ($result_geral) {
            while ($row = mysqli_fetch_assoc($result_geral)) {
                $config_automacao[$row['chave']] = $row['valor'];
            }
        }
        
        // Se encontrou configurações da automação, usar elas
        if (!empty($config_automacao)) {
            return [
                'host_smtp' => !empty($config_automacao['smtp_host']) ? $config_automacao['smtp_host'] : 'smtp.gmail.com',
                'porta_smtp' => !empty($config_automacao['smtp_porta']) ? $config_automacao['smtp_porta'] : 465,
                'email_usuario' => !empty($config_automacao['smtp_email']) ? $config_automacao['smtp_email'] : 'dznailsofficial@gmail.com',
                'senha_smtp' => $config_automacao['smtp_senha'] ?? '',
                'email_remetente' => $config_automacao['email_remetente'] ?? 'dznailsofficial@gmail.com',
                'nome_remetente' => $config_automacao['nome_remetente'] ?? 'D&Z Nails',
                'ativo' => true
            ];
        }
        
        // Se não encontrar na tabela, usar configurações padrão da automação
        return [
            'host_smtp' => 'smtp.gmail.com',
            'porta_smtp' => 465,
            'email_usuario' => 'dznailsofficial@gmail.com',
            'senha_smtp' => '', // Será configurada na automação
            'email_remetente' => 'dznailsofficial@gmail.com',
            'nome_remetente' => 'D&Z Nails',
            'ativo' => true
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao buscar configuração de email: " . $e->getMessage());
        return false;
    }
}

// Buscar configurações da automação
$config_email = buscarConfiguracaoEmail();

// Definir constantes baseadas na automação
if ($config_email) {
    define('SMTP_HOST', $config_email['host_smtp'] ?? 'smtp.gmail.com');
    define('SMTP_PORT', $config_email['porta_smtp'] ?? 465);
    define('SMTP_USERNAME', $config_email['email_usuario'] ?? 'dznaileofficial@gmail.com');
    define('SMTP_PASSWORD', $config_email['senha_smtp'] ?? '');
    define('SMTP_SECURE', ($config_email['porta_smtp'] == 465) ? 'ssl' : 'tls');
    define('EMAIL_FROM', $config_email['email_remetente'] ?? 'dznaileofficial@gmail.com');
    define('EMAIL_FROM_NAME', $config_email['nome_remetente'] ?? 'D&Z Nails');
    define('EMAIL_ENABLED', (bool)($config_email['ativo'] ?? true));
} else {
    // Configurações de fallback
    define('SMTP_HOST', 'smtp.gmail.com');
    define('SMTP_PORT', 465);
    define('SMTP_USERNAME', 'dznaileofficial@gmail.com');
    define('SMTP_PASSWORD', '');
    define('SMTP_SECURE', 'ssl');
    define('EMAIL_FROM', 'dznaileofficial@gmail.com');
    define('EMAIL_FROM_NAME', 'D&Z Nails');
    define('EMAIL_ENABLED', true);
}

define('EMAIL_DEBUG', true);

/**
 * Configurações automáticas da D&Z
 * 
 * - Host SMTP: smtp.gmail.com
 * - Porta: 465 (SSL)
 * - Email: dznaileofficial@gmail.com
 * - Alertas de Estoque: ATIVO
 * - Horário de Envio: 09:00
 * 
 * As configurações são puxadas automaticamente da automação.
 * Para alterar, use o painel de configurações da automação.
 */
?>