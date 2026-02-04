<?php
// DEBUG SUPER ROBUSTO - Cria tabela se necessário
echo "🔧 <strong>DEBUG ROBUSTO DO SISTEMA DE EMAIL</strong><br><br>";

// 1. Conexão manual com banco
echo "1️⃣ <strong>Testando conexão com banco...</strong><br>";

$host = '127.0.0.1';
$usuario = 'root'; 
$senha = '';
$banco = 'teste_dz';

$conexao = mysqli_connect($host, $usuario, $senha, $banco);

if ($conexao) {
    echo "✅ Conexão com banco: <strong style='color: green;'>SUCESSO</strong><br>";
    echo "📍 Banco: $banco | Host: $host<br><br>";
} else {
    echo "❌ Conexão com banco: <strong style='color: red;'>FALHOU</strong><br>";
    echo "🔧 <strong>ERRO:</strong> " . mysqli_connect_error() . "<br><br>";
    exit;
}

// 2. Verificar e criar tabela de configurações se necessário
echo "2️⃣ <strong>Verificando tabela de configurações...</strong><br>";

$query = "SHOW TABLES LIKE 'configuracoes_gerais'";
$result = mysqli_query($conexao, $query);

if (mysqli_num_rows($result) == 0) {
    echo "⚠️ Tabela 'configuracoes_gerais' não existe. Criando...<br>";
    
    $create_table = "CREATE TABLE configuracoes_gerais (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campo VARCHAR(100) NOT NULL,
        valor TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (mysqli_query($conexao, $create_table)) {
        echo "✅ Tabela 'configuracoes_gerais' criada com sucesso!<br>";
        
        // Inserir configurações padrão
        $configs_padrao = [
            "INSERT INTO configuracoes_gerais (campo, valor) VALUES ('smtp_host', 'smtp.gmail.com')",
            "INSERT INTO configuracoes_gerais (campo, valor) VALUES ('smtp_porta', '465')",
            "INSERT INTO configuracoes_gerais (campo, valor) VALUES ('smtp_email', '')",
            "INSERT INTO configuracoes_gerais (campo, valor) VALUES ('smtp_senha', '')"
        ];
        
        foreach ($configs_padrao as $sql) {
            mysqli_query($conexao, $sql);
        }
        
        echo "✅ Configurações padrão inseridas<br><br>";
    } else {
        echo "❌ Erro ao criar tabela: " . mysqli_error($conexao) . "<br><br>";
    }
} else {
    echo "✅ Tabela 'configuracoes_gerais' encontrada<br>";
}

// 3. Verificar estrutura da tabela primeiro
echo "3️⃣ <strong>Verificando estrutura da tabela...</strong><br>";

$describe = mysqli_query($conexao, "DESCRIBE configuracoes_gerais");
$colunas = [];
if ($describe) {
    while ($row = mysqli_fetch_assoc($describe)) {
        $colunas[] = $row['Field'];
    }
    echo "🔍 Colunas encontradas: " . implode(', ', $colunas) . "<br>";
}

// Detectar estrutura e adaptar query
$configs = [];
if (in_array('campo', $colunas) && in_array('valor', $colunas)) {
    echo "📋 Usando estrutura: campo/valor<br>";
    $query = "SELECT campo, valor FROM configuracoes_gerais WHERE campo IN ('smtp_host', 'smtp_porta', 'smtp_email', 'smtp_senha')";
    $result = mysqli_query($conexao, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $configs[$row['campo']] = $row['valor'];
        }
    }
} elseif (in_array('config_key', $colunas) && in_array('config_value', $colunas)) {
    echo "📋 Usando estrutura: config_key/config_value<br>";
    $query = "SELECT config_key as campo, config_value as valor FROM configuracoes_gerais WHERE config_key IN ('smtp_host', 'smtp_porta', 'smtp_email', 'smtp_senha')";
    $result = mysqli_query($conexao, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $configs[$row['campo']] = $row['valor'];
        }
    }
} else {
    echo "⚠️ Estrutura desconhecida. Vou recriar a tabela...<br>";
    
    // Backup e recriação
    mysqli_query($conexao, "DROP TABLE IF EXISTS configuracoes_gerais_backup");
    mysqli_query($conexao, "CREATE TABLE configuracoes_gerais_backup AS SELECT * FROM configuracoes_gerais");
    mysqli_query($conexao, "DROP TABLE configuracoes_gerais");
    
    $create_table = "CREATE TABLE configuracoes_gerais (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campo VARCHAR(100) NOT NULL,
        valor TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (mysqli_query($conexao, $create_table)) {
        echo "✅ Tabela recriada com estrutura correta<br>";
        
        // Inserir configurações padrão
        $configs_padrao = [
            "INSERT INTO configuracoes_gerais (campo, valor) VALUES ('smtp_host', 'smtp.gmail.com')",
            "INSERT INTO configuracoes_gerais (campo, valor) VALUES ('smtp_porta', '465')",
            "INSERT INTO configuracoes_gerais (campo, valor) VALUES ('smtp_email', '')",
            "INSERT INTO configuracoes_gerais (campo, valor) VALUES ('smtp_senha', '')"
        ];
        
        foreach ($configs_padrao as $sql) {
            mysqli_query($conexao, $sql);
        }
        
        echo "✅ Configurações padrão inseridas<br>";
    }
}

echo "<br>4️⃣ <strong>Verificando configurações SMTP...</strong><br>";
    
echo "📋 <strong>Configurações encontradas:</strong><br>";
echo "• Host: " . (isset($configs['smtp_host']) ? $configs['smtp_host'] : '❌ NÃO CONFIGURADO') . "<br>";
echo "• Porta: " . (isset($configs['smtp_porta']) ? $configs['smtp_porta'] : '❌ NÃO CONFIGURADO') . "<br>";
echo "• Email: " . (isset($configs['smtp_email']) && !empty($configs['smtp_email']) ? '✅ CONFIGURADO (' . $configs['smtp_email'] . ')' : '❌ NÃO CONFIGURADO') . "<br>";
echo "• Senha: " . (isset($configs['smtp_senha']) && !empty($configs['smtp_senha']) ? '✅ CONFIGURADA' : '❌ NÃO CONFIGURADA') . "<br><br>";

// 5. Verificar PHPMailer
echo "5️⃣ <strong>Verificando PHPMailer...</strong><br>";

$caminhos_phpmailer = [
    '../../../phpmailer/src/PHPMailer.php',
    '../../../PHPMailer-6.9.1/src/PHPMailer.php',
    'PHPMailer-6.9.1/src/PHPMailer.php'
];

$phpmailer_encontrado = false;
$caminho_correto = '';

foreach ($caminhos_phpmailer as $caminho) {
    if (file_exists($caminho)) {
        $phpmailer_encontrado = true;
        $caminho_correto = $caminho;
        break;
    }
}

if ($phpmailer_encontrado) {
    echo "✅ PHPMailer encontrado: <code>$caminho_correto</code><br>";
    
    try {
        require_once $caminho_correto;
        require_once dirname($caminho_correto) . '/SMTP.php';
        require_once dirname($caminho_correto) . '/Exception.php';
        echo "✅ Classes PHPMailer carregadas com sucesso!<br><br>";
    } catch (Exception $e) {
        echo "❌ Erro ao carregar PHPMailer: " . $e->getMessage() . "<br><br>";
        $phpmailer_encontrado = false;
    }
} else {
    echo "❌ PHPMailer NÃO encontrado!<br>";
    echo "📁 <strong>Caminhos verificados:</strong><br>";
    foreach ($caminhos_phpmailer as $caminho) {
        echo "• $caminho<br>";
    }
    echo "<br>💡 <strong>SOLUÇÃO:</strong><br>";
    echo "• Baixe PHPMailer de: <a href='https://github.com/PHPMailer/PHPMailer/releases' target='_blank'>GitHub</a><br>";
    echo "• Extraia na pasta: <code>c:\\XAMPP-install\\htdocs\\admin-teste\\phpmailer\\</code><br><br>";
}

// 5. Status final e teste de email
$smtp_configurado = isset($configs['smtp_email']) && !empty($configs['smtp_email']) && 
                   isset($configs['smtp_senha']) && !empty($configs['smtp_senha']);

echo "6️⃣ <strong>STATUS FINAL:</strong><br>";

if ($conexao && $smtp_configurado && $phpmailer_encontrado) {
    echo "🎉 <strong style='color: green; font-size: 18px;'>SISTEMA 100% PRONTO!</strong><br>";
    echo "✅ Banco de dados conectado<br>";
    echo "✅ Configurações SMTP completas<br>";
    echo "✅ PHPMailer carregado<br><br>";
    
    // Formulário de teste
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email_teste'])) {
        $email_destino = $_POST['email_teste'];
        
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Configurações SMTP
            $mail->isSMTP();
            $mail->Host = $configs['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $configs['smtp_email'];
            $mail->Password = $configs['smtp_senha'];
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = $configs['smtp_porta'];
            $mail->CharSet = 'UTF-8';
            
            // Remetente e destinatário
            $mail->setFrom($configs['smtp_email'], 'D&Z Sistema Email');
            $mail->addAddress($email_destino);
            
            // Conteúdo
            $mail->isHTML(true);
            $mail->Subject = '🚀 Sistema D&Z - Email Automático Funcionando!';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);'>
                    <div style='text-align: center; background: linear-gradient(135deg, #ff1493, #ff00d4); padding: 25px; border-radius: 8px; margin-bottom: 25px;'>
                        <h1 style='color: white; margin: 0; font-size: 24px;'>🎉 Sistema Funcionando!</h1>
                    </div>
                    
                    <h2 style='color: #ff00d4;'>Parabéns!</h2>
                    <p>O sistema de email automático da D&Z está funcionando perfeitamente!</p>
                    
                    <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745;'>
                        <h3 style='color: #28a745; margin-top: 0;'>✅ Componentes Testados:</h3>
                        <ul style='margin: 0; color: #666;'>
                            <li>Conexão com banco de dados</li>
                            <li>Configurações SMTP</li>
                            <li>PHPMailer</li>
                            <li>Envio de email</li>
                        </ul>
                    </div>
                    
                    <p><strong>Teste realizado em:</strong> " . date('d/m/Y H:i:s') . "</p>
                    
                    <div style='text-align: center; margin: 25px 0;'>
                        <p style='color: #666;'>Agora você pode testar os emails automáticos:</p>
                        <p style='color: #666;'>• Cadastre um cliente → Email de boas-vindas</p>
                        <p style='color: #666;'>• Crie um pedido → Email de confirmação</p>
                        <p style='color: #666;'>• Mude status → Email de atualização</p>
                    </div>
                    
                    <div style='background: #ff00d4; color: white; padding: 15px; text-align: center; border-radius: 8px;'>
                        <strong>D&Z - Sistema de Email Automático</strong>
                    </div>
                </div>
            ";
            
            $mail->send();
            echo "🎉 <strong style='color: green; font-size: 20px;'>EMAIL ENVIADO COM SUCESSO!</strong><br>";
            echo "📧 Verifique a caixa de entrada (e spam) de: <strong>$email_destino</strong><br>";
            echo "✅ <strong>SISTEMA 100% FUNCIONANDO!</strong><br><br>";
            
            echo "🔗 <strong>AGORA TESTE OS SISTEMAS AUTOMÁTICOS:</strong><br>";
            echo "• <a href='pedidos_sistema.php' target='_blank' style='color: #ff00d4; font-weight: bold;'>🛍️ Sistema de Pedidos</a><br>";
            echo "• <a href='customers.php' target='_blank' style='color: #ff00d4; font-weight: bold;'>👥 Cadastrar Cliente</a><br><br>";
            
        } catch (Exception $e) {
            echo "❌ <strong style='color: red;'>ERRO NO ENVIO:</strong><br>";
            echo "🔧 Detalhes: " . $e->getMessage() . "<br><br>";
            
            echo "💡 <strong>POSSÍVEIS SOLUÇÕES:</strong><br>";
            echo "• Verifique se é uma 'Senha de App' do Gmail<br>";
            echo "• Confirme se o email está correto<br>";
            echo "• Teste com outro provedor<br><br>";
        }
    } else {
        echo "
        <div style='background: #e7f3ff; padding: 25px; border-radius: 10px; margin: 20px 0; border-left: 4px solid #007bff;'>
            <h3 style='color: #007bff; margin-top: 0;'>🚀 Pronto para teste!</h3>
            <p>Digite seu email para receber um teste completo:</p>
            
            <form method='POST' style='margin: 20px 0;'>
                <input type='email' name='email_teste' placeholder='seu@email.com' required 
                       style='padding: 15px; width: 350px; border: 2px solid #ddd; border-radius: 8px; margin-right: 15px; font-size: 16px;'>
                <button type='submit' style='background: linear-gradient(135deg, #ff1493, #ff00d4); color: white; border: none; padding: 15px 30px; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 16px;'>
                    🚀 TESTAR AGORA
                </button>
            </form>
        </div>
        ";
    }
} else {
    echo "⚠️ <strong style='color: orange;'>SISTEMA INCOMPLETO</strong><br>";
    echo "🔧 <strong>O QUE FALTA:</strong><br>";
    
    if (!$conexao) {
        echo "• ❌ Conexão com banco<br>";
    }
    if (!$smtp_configurado) {
        echo "• ❌ Configurações SMTP: <a href='automacao.php' target='_blank' style='color: #ff00d4;'>Configure aqui</a><br>";
    }
    if (!$phpmailer_encontrado) {
        echo "• ❌ PHPMailer: Baixe e extraia na pasta phpmailer/<br>";
    }
    echo "<br>";
}

mysqli_close($conexao);
?>

<style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
        background: #f5f5f5;
        line-height: 1.6;
    }
    
    code {
        background: #f1f1f1;
        padding: 3px 8px;
        border-radius: 4px;
        font-family: monospace;
        font-size: 14px;
    }
    
    a {
        color: #ff00d4;
        text-decoration: none;
        font-weight: bold;
    }
    
    a:hover {
        text-decoration: underline;
    }
</style>