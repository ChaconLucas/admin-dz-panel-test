<?php
/**
 * Sistema de Email Automático - D&Z
 * Dispara emails baseados em eventos do sistema
 */

// Tentar incluir conexão (compatibilidade)
if (!isset($conexao)) {
    if (file_exists('../../../PHP/conexao.php')) {
        require_once '../../../PHP/conexao.php';
    } else {
        require_once '../../../config/config.php';
    }
}

class EmailAutomatico {
    private $conexao;
    private $phpmailerPath;
    
    public function __construct($conexao) {
        $this->conexao = $conexao;
        $this->phpmailerPath = dirname(__FILE__) . '/../../../phpmailer/src/';
    }
    
    /**
     * Buscar configurações SMTP do banco
     */
    private function getSmtpConfig() {
        $config = [
            'smtp_host' => 'smtp.gmail.com',
            'smtp_porta' => '465',
            'smtp_email' => '',
            'smtp_senha' => ''
        ];
        
        $query = "SELECT campo, valor FROM configuracoes_gerais WHERE campo IN ('smtp_host', 'smtp_porta', 'smtp_email', 'smtp_senha')";
        $result = mysqli_query($this->conexao, $query);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $config[$row['campo']] = $row['valor'];
        }
        
        return $config;
    }
    
    /**
     * Enviar email usando PHPMailer
     */
    private function enviarEmail($para, $nome_destinatario, $assunto, $corpo) {
        try {
            // Verificar se PHPMailer existe
            if (!file_exists($this->phpmailerPath . 'PHPMailer.php')) {
                error_log("PHPMailer não encontrado em: " . $this->phpmailerPath);
                return false;
            }
            
            require_once $this->phpmailerPath . 'PHPMailer.php';
            require_once $this->phpmailerPath . 'SMTP.php';
            require_once $this->phpmailerPath . 'Exception.php';
            
            $config = $this->getSmtpConfig();
            
            if (empty($config['smtp_email']) || empty($config['smtp_senha'])) {
                error_log("Configurações SMTP não encontradas");
                return false;
            }
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Configurações SMTP
            $mail->isSMTP();
            $mail->Host = $config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['smtp_email'];
            $mail->Password = $config['smtp_senha'];
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = $config['smtp_porta'];
            $mail->CharSet = 'UTF-8';
            
            // Remetente
            $mail->setFrom($config['smtp_email'], 'D&Z Nails');
            
            // Destinatário
            $mail->addAddress($para, $nome_destinatario);
            
            // Conteúdo
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            $mail->Body = $corpo;
            
            $mail->send();
            
            // Log de sucesso
            $this->logEmail($para, $assunto, 'enviado', '');
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erro ao enviar email: " . $e->getMessage());
            $this->logEmail($para, $assunto, 'erro', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registrar log de email
     */
    private function logEmail($destinatario, $assunto, $status, $erro = '') {
        $query = "INSERT INTO logs_email (destinatario, assunto, status, erro, data_envio) VALUES (?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($this->conexao, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssss", $destinatario, $assunto, $status, $erro);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    
    /**
     * Substituir variáveis no template
     */
    private function processarTemplate($template, $variaveis) {
        foreach ($variaveis as $chave => $valor) {
            $template = str_replace('{' . $chave . '}', $valor, $template);
        }
        return $template;
    }
    
    /**
     * EMAIL 1: Boas-vindas para novo cliente
     */
    public function emailBoasVindas($cliente_id, $nome_cliente, $email_cliente) {
        $template = "
        <html>
        <body style='font-family: Arial, sans-serif; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(255, 0, 212, 0.1);'>
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #ff1493, #ff00d4); padding: 30px; text-align: center;'>
                    <h1 style='color: white; margin: 0; font-size: 28px;'>🎉 Bem-vindo(a) à D&Z!</h1>
                </div>
                
                <!-- Conteúdo -->
                <div style='padding: 30px;'>
                    <h2 style='color: #ff00d4; margin-bottom: 20px;'>Olá, {nome_cliente}!</h2>
                    
                    <p style='line-height: 1.6; margin-bottom: 20px;'>
                        É com muito prazer que recebemos você em nossa família! 💖
                    </p>
                    
                    <p style='line-height: 1.6; margin-bottom: 20px;'>
                        Sua conta foi criada com sucesso e agora você tem acesso a:
                    </p>
                    
                    <ul style='line-height: 1.8; margin-bottom: 25px; color: #555;'>
                        <li>✨ Produtos exclusivos de beleza para unhas</li>
                        <li>🎯 Ofertas especiais para nossos clientes</li>
                        <li>📦 Acompanhamento de pedidos em tempo real</li>
                        <li>💬 Atendimento personalizado via WhatsApp</li>
                    </ul>
                    
                    <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #ff00d4; margin: 20px 0;'>
                        <h3 style='margin: 0 0 10px 0; color: #ff00d4;'>🎁 Oferta Especial!</h3>
                        <p style='margin: 0; color: #666;'>Como novo cliente, você terá <strong>5% de desconto</strong> na sua primeira compra! Use o código: <strong>BEMVINDO5</strong></p>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://localhost/admin-teste/' style='background: linear-gradient(135deg, #ff1493, #ff00d4); color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; display: inline-block;'>
                            🛍️ Fazer Primeira Compra
                        </a>
                    </div>
                    
                    <p style='line-height: 1.6; color: #666; text-align: center; margin-top: 30px;'>
                        Dúvidas? Entre em contato conosco pelo WhatsApp: <strong>(21) 98513-6806</strong>
                    </p>
                </div>
                
                <!-- Footer -->
                <div style='background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px;'>
                    <p style='margin: 0;'>D&Z Nails - Beleza que transforma ✨</p>
                    <p style='margin: 5px 0 0 0;'>Este e-mail foi enviado automaticamente. Por favor, não responda.</p>
                </div>
            </div>
        </body>
        </html>";
        
        $variaveis = [
            'nome_cliente' => $nome_cliente,
            'cliente_id' => $cliente_id
        ];
        
        $corpo = $this->processarTemplate($template, $variaveis);
        $assunto = "🎉 Bem-vindo(a) à D&Z - Sua conta foi criada!";
        
        return $this->enviarEmail($email_cliente, $nome_cliente, $assunto, $corpo);
    }
    
    /**
     * EMAIL 2: Confirmação de pedido criado
     */
    public function emailConfirmacaoPedido($pedido_id, $cliente_email, $nome_cliente, $valor_total, $itens = []) {
        $template = "
        <html>
        <body style='font-family: Arial, sans-serif; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(255, 0, 212, 0.1);'>
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #ff1493, #ff00d4); padding: 30px; text-align: center;'>
                    <h1 style='color: white; margin: 0; font-size: 24px;'>🛍️ Pedido Confirmado!</h1>
                </div>
                
                <!-- Conteúdo -->
                <div style='padding: 30px;'>
                    <h2 style='color: #ff00d4; margin-bottom: 20px;'>Olá, {nome_cliente}!</h2>
                    
                    <p style='line-height: 1.6; margin-bottom: 25px;'>
                        Seu pedido foi recebido com sucesso! 🎉
                    </p>
                    
                    <!-- Detalhes do Pedido -->
                    <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                        <h3 style='margin: 0 0 15px 0; color: #ff00d4;'>📋 Detalhes do Pedido</h3>
                        <p style='margin: 5px 0; color: #666;'><strong>Número:</strong> #{pedido_id}</p>
                        <p style='margin: 5px 0; color: #666;'><strong>Valor Total:</strong> R$ {valor_total}</p>
                        <p style='margin: 5px 0; color: #666;'><strong>Data:</strong> {data_pedido}</p>
                    </div>
                    
                    <div style='background: #e7f3ff; padding: 20px; border-radius: 8px; border-left: 4px solid #007bff; margin: 20px 0;'>
                        <h3 style='margin: 0 0 10px 0; color: #007bff;'>⏳ Próximos Passos</h3>
                        <ol style='margin: 0; padding-left: 20px; color: #666;'>
                            <li>Aguarde a confirmação do pagamento</li>
                            <li>Seus produtos serão separados</li>
                            <li>Você receberá o código de rastreamento</li>
                        </ol>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://localhost/admin-teste/' style='background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; display: inline-block;'>
                            📦 Acompanhar Pedido
                        </a>
                    </div>
                    
                    <p style='line-height: 1.6; color: #666; text-align: center; margin-top: 30px;'>
                        Dúvidas? Fale conosco: <strong>(21) 98513-6806</strong>
                    </p>
                </div>
                
                <!-- Footer -->
                <div style='background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px;'>
                    <p style='margin: 0;'>D&Z Nails - Beleza que transforma ✨</p>
                    <p style='margin: 5px 0 0 0;'>Este e-mail foi enviado automaticamente.</p>
                </div>
            </div>
        </body>
        </html>";
        
        $variaveis = [
            'nome_cliente' => $nome_cliente,
            'pedido_id' => $pedido_id,
            'valor_total' => number_format($valor_total, 2, ',', '.'),
            'data_pedido' => date('d/m/Y H:i')
        ];
        
        $corpo = $this->processarTemplate($template, $variaveis);
        $assunto = "🛍️ Pedido #{$pedido_id} Confirmado - D&Z";
        
        return $this->enviarEmail($cliente_email, $nome_cliente, $assunto, $corpo);
    }
    
    /**
     * EMAIL 3: Status do pedido alterado (usando templates do gestao-fluxo)
     */
    public function emailStatusPedido($pedido_id, $cliente_email, $nome_cliente, $novo_status) {
        // Buscar template do status no banco
        $query = "SELECT nome, cor_hex, mensagem_template FROM status_fluxo WHERE nome = ?";
        $stmt = mysqli_prepare($this->conexao, $query);
        mysqli_stmt_bind_param($stmt, "s", $novo_status);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $status_info = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$status_info) {
            return false;
        }
        
        // Cores baseadas no status
        $cores = [
            'Pedido Recebido' => ['#ff00d4', '#ff1493'],
            'Pagamento Confirmado' => ['#41f1b6', '#28a745'],
            'Em Preparação' => ['#ffbb55', '#f39c12'],
            'Enviado' => ['#007bff', '#0056b3'],
            'Entregue' => ['#28a745', '#20c997']
        ];
        
        $cor_gradiente = $cores[$novo_status] ?? ['#ff00d4', '#ff1493'];
        
        $template = "
        <html>
        <body style='font-family: Arial, sans-serif; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1);'>
                <!-- Header -->
                <div style='background: linear-gradient(135deg, {cor1}, {cor2}); padding: 30px; text-align: center;'>
                    <h1 style='color: white; margin: 0; font-size: 24px;'>📦 Atualização do Pedido</h1>
                </div>
                
                <!-- Conteúdo -->
                <div style='padding: 30px;'>
                    <h2 style='color: {cor1}; margin-bottom: 20px;'>Status Atualizado!</h2>
                    
                    <!-- Status Badge -->
                    <div style='background: {cor1}; color: white; padding: 10px 20px; border-radius: 20px; display: inline-block; margin-bottom: 20px; font-weight: bold;'>
                        {novo_status}
                    </div>
                    
                    <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid {cor1}; margin: 20px 0;'>
                        {mensagem_personalizada}
                    </div>
                    
                    <p style='color: #666; margin-bottom: 5px;'><strong>Pedido:</strong> #{pedido_id}</p>
                    <p style='color: #666; margin-bottom: 20px;'><strong>Data da atualização:</strong> {data_atualizacao}</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://localhost/admin-teste/' style='background: linear-gradient(135deg, {cor1}, {cor2}); color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; display: inline-block;'>
                            📦 Ver Detalhes
                        </a>
                    </div>
                </div>
                
                <!-- Footer -->
                <div style='background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px;'>
                    <p style='margin: 0;'>D&Z Nails - Acompanhe seu pedido! ✨</p>
                </div>
            </div>
        </body>
        </html>";
        
        // Processar mensagem personalizada
        $mensagem_personalizada = str_replace(
            ['{cliente}', '{id_pedido}'],
            [$nome_cliente, $pedido_id],
            $status_info['mensagem_template']
        );
        
        $variaveis = [
            'nome_cliente' => $nome_cliente,
            'pedido_id' => $pedido_id,
            'novo_status' => $novo_status,
            'cor1' => $cor_gradiente[0],
            'cor2' => $cor_gradiente[1],
            'mensagem_personalizada' => $mensagem_personalizada,
            'data_atualizacao' => date('d/m/Y H:i')
        ];
        
        $corpo = $this->processarTemplate($template, $variaveis);
        $assunto = "📦 Pedido #{$pedido_id} - {$novo_status}";
        
        return $this->enviarEmail($cliente_email, $nome_cliente, $assunto, $corpo);
    }
    
    /**
     * Criar tabela de logs de email se não existir
     */
    public function criarTabelaLogs() {
        $query = "CREATE TABLE IF NOT EXISTS logs_email (
            id INT AUTO_INCREMENT PRIMARY KEY,
            destinatario VARCHAR(255) NOT NULL,
            assunto VARCHAR(500) NOT NULL,
            status ENUM('enviado', 'erro') NOT NULL,
            erro TEXT,
            data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        return mysqli_query($this->conexao, $query);
    }
}

// Função global para usar nos hooks
function enviarEmailAutomatico($tipo, $dados) {
    global $conexao;
    
    $emailSystem = new EmailAutomatico($conexao);
    $emailSystem->criarTabelaLogs();
    
    switch($tipo) {
        case 'novo_cliente':
            return $emailSystem->emailBoasVindas(
                $dados['cliente_id'],
                $dados['nome'],
                $dados['email']
            );
            break;
            
        case 'novo_pedido':
            return $emailSystem->emailConfirmacaoPedido(
                $dados['pedido_id'],
                $dados['email'],
                $dados['nome'],
                $dados['valor_total'],
                $dados['itens'] ?? []
            );
            break;
            
        case 'status_pedido':
            return $emailSystem->emailStatusPedido(
                $dados['pedido_id'],
                $dados['email'],
                $dados['nome'],
                $dados['novo_status']
            );
            break;
    }
    
    return false;
}
?>