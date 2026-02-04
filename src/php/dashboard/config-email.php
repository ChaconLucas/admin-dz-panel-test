<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📧 Configurar Email - D&Z Sistema</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .header h1 {
            color: #ff00d4;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 16px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        input:focus {
            outline: none;
            border-color: #ff00d4;
            box-shadow: 0 0 0 3px rgba(255, 0, 212, 0.1);
        }
        
        .btn-save {
            background: linear-gradient(135deg, #ff1493, #ff00d4);
            color: white;
            border: none;
            padding: 18px 40px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 20, 147, 0.3);
        }
        
        .help-box {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 20px;
            margin: 30px 0;
            border-radius: 8px;
        }
        
        .help-box h3 {
            color: #007bff;
            margin-bottom: 15px;
        }
        
        .help-box ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .help-box li {
            margin-bottom: 8px;
            color: #666;
        }
        
        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            text-align: center;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .btn-test {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 15px;
            transition: all 0.3s ease;
        }
        
        .btn-test:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Configurar Email Automático</h1>
            <p>Configure seu Gmail para enviar emails automáticos do sistema D&Z</p>
        </div>
        
        <?php
        // Conectar ao banco
        $host = '127.0.0.1';
        $usuario = 'root'; 
        $senha = '';
        $banco = 'teste_dz';
        
        $conexao = mysqli_connect($host, $usuario, $senha, $banco);
        
        $message = '';
        $message_type = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['save_smtp'])) {
                $smtp_host = trim($_POST['smtp_host']);
                $smtp_porta = trim($_POST['smtp_porta']);
                $smtp_email = trim($_POST['smtp_email']);
                $smtp_senha = trim($_POST['smtp_senha']);
                
                // Salvar cada configuração
                $configs = [
                    'smtp_host' => $smtp_host,
                    'smtp_porta' => $smtp_porta,
                    'smtp_email' => $smtp_email,
                    'smtp_senha' => $smtp_senha
                ];
                
                $success = true;
                foreach ($configs as $campo => $valor) {
                    $query = "INSERT INTO configuracoes_gerais (campo, valor) VALUES ('$campo', '$valor') ON DUPLICATE KEY UPDATE valor = '$valor'";
                    if (!mysqli_query($conexao, $query)) {
                        $success = false;
                        break;
                    }
                }
                
                if ($success) {
                    $message = '✅ Configurações SMTP salvas com sucesso! Agora você pode testar o email.';
                    $message_type = 'success';
                } else {
                    $message = '❌ Erro ao salvar configurações: ' . mysqli_error($conexao);
                    $message_type = 'error';
                }
            }
        }
        
        // Carregar configurações atuais
        $configs = [];
        $result = mysqli_query($conexao, "SELECT campo, valor FROM configuracoes_gerais WHERE campo IN ('smtp_host', 'smtp_porta', 'smtp_email', 'smtp_senha')");
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $configs[$row['campo']] = $row['valor'];
            }
        }
        ?>
        
        <?php if ($message): ?>
            <div class="message <?= $message_type ?>">
                <?= $message ?>
                <?php if ($message_type === 'success'): ?>
                    <br><br>
                    <a href="debug_final.php" style="background: #ff00d4; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                        🚀 Testar Agora
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="smtp_host">🌐 Host SMTP</label>
                <input type="text" id="smtp_host" name="smtp_host" 
                       value="<?= htmlspecialchars($configs['smtp_host'] ?? 'smtp.gmail.com') ?>"
                       placeholder="smtp.gmail.com" required>
            </div>
            
            <div class="form-group">
                <label for="smtp_porta">🔌 Porta SMTP</label>
                <input type="number" id="smtp_porta" name="smtp_porta" 
                       value="<?= htmlspecialchars($configs['smtp_porta'] ?? '465') ?>"
                       placeholder="465" required>
            </div>
            
            <div class="form-group">
                <label for="smtp_email">📧 Seu Email Gmail</label>
                <input type="email" id="smtp_email" name="smtp_email" 
                       value="<?= htmlspecialchars($configs['smtp_email'] ?? '') ?>"
                       placeholder="seu@gmail.com" required>
            </div>
            
            <div class="form-group">
                <label for="smtp_senha">🔐 Senha de App Gmail</label>
                <input type="password" id="smtp_senha" name="smtp_senha" 
                       value="<?= htmlspecialchars($configs['smtp_senha'] ?? '') ?>"
                       placeholder="Senha de app do Gmail (não a senha normal!)" required>
            </div>
            
            <button type="submit" name="save_smtp" class="btn-save">
                💾 Salvar Configurações SMTP
            </button>
        </form>
        
        <div class="help-box">
            <h3>🤔 Como criar senha de app no Gmail:</h3>
            <ul>
                <li>Entre na sua <strong>Conta Google</strong></li>
                <li>Vá em <strong>Segurança</strong></li>
                <li>Ative <strong>Verificação em duas etapas</strong> (se não ativou ainda)</li>
                <li>Em <strong>Senhas de app</strong>, clique em <strong>Gerar</strong></li>
                <li>Escolha <strong>Email</strong> como tipo</li>
                <li>Copie a senha gerada e cole aqui</li>
            </ul>
        </div>
        
        <?php if (!empty($configs['smtp_email']) && !empty($configs['smtp_senha'])): ?>
            <div style="text-align: center; margin-top: 30px;">
                <p style="color: #28a745; font-weight: bold; margin-bottom: 15px;">
                    ✅ SMTP Configurado! Pronto para testes.
                </p>
                <a href="debug_final.php" class="btn-test" style="text-decoration: none; display: inline-block;">
                    🚀 Ir para Testes Completos
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>