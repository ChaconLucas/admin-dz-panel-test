<?php
require_once 'PHP/conexao.php';

$errors = [];
$sucesso = false;

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = mysqli_real_escape_string($conexao, trim($_POST['nome']));
    $email = mysqli_real_escape_string($conexao, trim($_POST['email']));
    $telefone = mysqli_real_escape_string($conexao, trim($_POST['telefone']));
    $empresa = mysqli_real_escape_string($conexao, trim($_POST['empresa']));
    $cnpj = mysqli_real_escape_string($conexao, trim($_POST['cnpj']));
    $cidade = mysqli_real_escape_string($conexao, trim($_POST['cidade']));
    $estado = mysqli_real_escape_string($conexao, trim($_POST['estado']));
    $experiencia = mysqli_real_escape_string($conexao, trim($_POST['experiencia']));
    $mensagem = mysqli_real_escape_string($conexao, trim($_POST['mensagem']));
    
    // Validação básica
    if (empty($nome)) {
        $errors[] = "Nome é obrigatório";
    }
    if (empty($email)) {
        $errors[] = "Email é obrigatório";
    }
    
    if (empty($errors)) {
        // Criar tabela se não existir
        $check_table = "SHOW TABLES LIKE 'revendedores'";
        $table_exists = mysqli_query($conexao, $check_table);
        
        if (mysqli_num_rows($table_exists) == 0) {
            $create_table = "
            CREATE TABLE revendedores (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nome VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                telefone VARCHAR(20),
                empresa VARCHAR(255),
                cnpj VARCHAR(20),
                cidade VARCHAR(100),
                estado VARCHAR(50),
                experiencia TEXT,
                mensagem TEXT,
                status ENUM('pendente', 'aprovado', 'rejeitado') DEFAULT 'pendente',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            mysqli_query($conexao, $create_table);
        }
        
        // Inserir dados
        $sql = "INSERT INTO revendedores (nome, email, telefone, empresa, cnpj, cidade, estado, experiencia, mensagem) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($stmt, "sssssssss", $nome, $email, $telefone, $empresa, $cnpj, $cidade, $estado, $experiencia, $mensagem);
        
        if (mysqli_stmt_execute($stmt)) {
            $sucesso = true;
        } else {
            $errors[] = "Erro ao enviar solicitação. Tente novamente.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quero ser Revendedor - D&Z</title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp" rel="stylesheet">
    
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }
      
      :root {
        --color-primary: #ff00d4;
        --color-danger: #ff7782;
        --color-success: #41f1b6;
        --color-warning: #ffbb55;
        --color-white: #fff;
        --color-info-dark: #7d8da1;
        --color-info-light: #dce1eb;
        --color-dark: #363949;
        --color-light: rgba(132, 139, 200, 0.18);
        --color-primary-variant: #111e88;
        --color-dark-variant: #5d6679;
        --color-background: #f6f6f9;
        --card-border-radius: 2rem;
        --border-radius-1: 0.4rem;
        --border-radius-2: 0.8rem;
        --border-radius-3: 1.2rem;
        --card-padding: 1.8rem;
        --padding-1: 1.2rem;
        --box-shadow: 0 2rem 3rem var(--color-light);
      }
      
      body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-variant) 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
      }
      
      .form-container {
        background: var(--color-white);
        border-radius: var(--card-border-radius);
        padding: var(--card-padding);
        box-shadow: var(--box-shadow);
        max-width: 600px;
        width: 100%;
      }
      
      .header {
        text-align: center;
        margin-bottom: 2rem;
      }
      
      .header h1 {
        color: var(--color-dark);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
      }
      
      .header p {
        color: var(--color-info-dark);
      }
      
      .form-group {
        margin-bottom: 1.5rem;
      }
      
      .form-label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--color-dark);
        font-weight: 500;
      }
      
      .form-input,
      .form-textarea,
      .form-select {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--color-info-light);
        border-radius: var(--border-radius-1);
        font-family: inherit;
        font-size: 1rem;
        transition: border-color 0.3s ease;
      }
      
      .form-input:focus,
      .form-textarea:focus,
      .form-select:focus {
        outline: none;
        border-color: var(--color-primary);
      }
      
      .form-textarea {
        min-height: 100px;
        resize: vertical;
      }
      
      .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
      }
      
      .btn {
        width: 100%;
        padding: 1rem;
        background: var(--color-primary);
        color: var(--color-white);
        border: none;
        border-radius: var(--border-radius-1);
        font-size: 1rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
      }
      
      .btn:hover {
        background: var(--color-primary-variant);
        transform: translateY(-2px);
      }
      
      .alert {
        padding: 1rem;
        border-radius: var(--border-radius-1);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }
      
      .alert-success {
        background: rgba(65, 241, 182, 0.1);
        color: #0c5460;
        border: 1px solid var(--color-success);
      }
      
      .alert-error {
        background: rgba(255, 119, 130, 0.1);
        color: #721c24;
        border: 1px solid var(--color-danger);
      }
      
      .back-link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--color-primary);
        text-decoration: none;
        margin-top: 1rem;
        font-weight: 500;
      }
      
      .back-link:hover {
        text-decoration: underline;
      }
      
      @media (max-width: 768px) {
        .form-row {
          grid-template-columns: 1fr;
        }
        
        body {
          padding: 1rem;
        }
      }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="header">
            <h1>
                <span class="material-symbols-sharp">handshake</span>
                Quero ser Revendedor
            </h1>
            <p>Preencha os dados abaixo e entraremos em contato em breve</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <span class="material-symbols-sharp">error</span>
                <div>
                    <strong>Erro:</strong>
                    <ul style="margin: 0; padding-left: 1rem;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="alert alert-success">
                <span class="material-symbols-sharp">check_circle</span>
                Solicitação enviada com sucesso! Entraremos em contato em breve.
            </div>
        <?php endif; ?>

        <?php if (!$sucesso): ?>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Nome Completo *</label>
                    <input type="text" name="nome" class="form-input" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Telefone</label>
                        <input type="tel" name="telefone" class="form-input" placeholder="(11) 99999-9999">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nome da Empresa</label>
                        <input type="text" name="empresa" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">CNPJ</label>
                        <input type="text" name="cnpj" class="form-input" placeholder="00.000.000/0000-00">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Cidade</label>
                        <input type="text" name="cidade" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="">Selecione</option>
                            <option value="AC">Acre</option>
                            <option value="AL">Alagoas</option>
                            <option value="AP">Amapá</option>
                            <option value="AM">Amazonas</option>
                            <option value="BA">Bahia</option>
                            <option value="CE">Ceará</option>
                            <option value="DF">Distrito Federal</option>
                            <option value="ES">Espírito Santo</option>
                            <option value="GO">Goiás</option>
                            <option value="MA">Maranhão</option>
                            <option value="MT">Mato Grosso</option>
                            <option value="MS">Mato Grosso do Sul</option>
                            <option value="MG">Minas Gerais</option>
                            <option value="PA">Pará</option>
                            <option value="PB">Paraíba</option>
                            <option value="PR">Paraná</option>
                            <option value="PE">Pernambuco</option>
                            <option value="PI">Piauí</option>
                            <option value="RJ">Rio de Janeiro</option>
                            <option value="RN">Rio Grande do Norte</option>
                            <option value="RS">Rio Grande do Sul</option>
                            <option value="RO">Rondônia</option>
                            <option value="RR">Roraima</option>
                            <option value="SC">Santa Catarina</option>
                            <option value="SP">São Paulo</option>
                            <option value="SE">Sergipe</option>
                            <option value="TO">Tocantins</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Experiência com Vendas</label>
                    <textarea name="experiencia" class="form-textarea" placeholder="Conte-nos sobre sua experiência com vendas..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Mensagem</label>
                    <textarea name="mensagem" class="form-textarea" placeholder="Deixe uma mensagem adicional (opcional)..."></textarea>
                </div>

                <button type="submit" class="btn">
                    <span class="material-symbols-sharp">send</span>
                    Enviar Solicitação
                </button>
            </form>
        <?php endif; ?>

        <a href="index.php" class="back-link">
            <span class="material-symbols-sharp">arrow_back</span>
            Voltar ao início
        </a>
    </div>
</body>
</html>