<?php
session_start();
// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../../../PHP/login.php');
    exit();
}

require_once '../../../PHP/conexao.php';

// Verificar se tabela de imagens existe, se não, criar
$check_table = "SHOW TABLES LIKE 'produto_imagens'";
$table_exists = mysqli_query($conexao, $check_table);

if (mysqli_num_rows($table_exists) == 0) {
    $create_table = "
    CREATE TABLE produto_imagens (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nome VARCHAR(255) NOT NULL,
        nome_arquivo VARCHAR(255) NOT NULL,
        tamanho INT NOT NULL,
        tipo VARCHAR(100) NOT NULL,
        caminho VARCHAR(500) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    mysqli_query($conexao, $create_table);
}

// Verificar colunas existentes na tabela
$columns_query = "SHOW COLUMNS FROM produto_imagens";
$columns_result = mysqli_query($conexao, $columns_query);
$existing_columns = [];
while ($col = mysqli_fetch_assoc($columns_result)) {
    $existing_columns[] = $col['Field'];
}

// Adicionar colunas que faltam
$required_columns = [
    'nome' => 'VARCHAR(255) NOT NULL',
    'nome_arquivo' => 'VARCHAR(255) NOT NULL', 
    'tamanho' => 'INT NOT NULL',
    'tipo' => 'VARCHAR(100) NOT NULL',
    'caminho' => 'VARCHAR(500) NOT NULL'
];

foreach ($required_columns as $column => $definition) {
    if (!in_array($column, $existing_columns)) {
        $add_column = "ALTER TABLE produto_imagens ADD COLUMN $column $definition";
        mysqli_query($conexao, $add_column);
    }
}

// Processar upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['imagens'])) {
    $upload_dir = '../../../assets/images/produtos/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $sucessos = [];
    $erros = [];
    $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    foreach ($_FILES['imagens']['name'] as $key => $filename) {
        if ($_FILES['imagens']['error'][$key] == 0) {
            $extensao = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($extensao, $extensoes_permitidas)) {
                if ($_FILES['imagens']['size'][$key] <= 5000000) { // 5MB
                    $nome_arquivo = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
                    $caminho_completo = $upload_dir . $nome_arquivo;
                    
                    if (move_uploaded_file($_FILES['imagens']['tmp_name'][$key], $caminho_completo)) {
                        // Verificar colunas novamente para INSERT dinâmico
                        $columns_query = "SHOW COLUMNS FROM produto_imagens";
                        $columns_result = mysqli_query($conexao, $columns_query);
                        $available_columns = [];
                        while ($col = mysqli_fetch_assoc($columns_result)) {
                            $available_columns[] = $col['Field'];
                        }
                        
                        // Dados para inserir
                        $data_to_insert = [
                            'nome' => $filename,
                            'nome_arquivo' => $nome_arquivo,
                            'tamanho' => $_FILES['imagens']['size'][$key],
                            'tipo' => $_FILES['imagens']['type'][$key],
                            'caminho' => 'assets/images/produtos/' . $nome_arquivo
                        ];
                        
                        // Construir SQL dinamicamente
                        $insert_columns = [];
                        $insert_values = [];
                        $bind_types = '';
                        $bind_params = [];
                        
                        foreach ($data_to_insert as $column => $value) {
                            if (in_array($column, $available_columns)) {
                                $insert_columns[] = $column;
                                $insert_values[] = '?';
                                $bind_types .= is_int($value) ? 'i' : 's';
                                $bind_params[] = $value;
                            }
                        }
                        
                        if (!empty($insert_columns)) {
                            $sql = "INSERT INTO produto_imagens (" . implode(', ', $insert_columns) . ") VALUES (" . implode(', ', $insert_values) . ")";
                            $stmt = mysqli_prepare($conexao, $sql);
                            if (!empty($bind_params)) {
                                mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_params);
                            }
                        }
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $sucessos[] = "✅ $filename enviada com sucesso";
                        } else {
                            $erros[] = "❌ Erro ao salvar $filename no banco";
                        }
                    } else {
                        $erros[] = "❌ Erro ao mover $filename";
                    }
                } else {
                    $erros[] = "❌ $filename muito grande (máx 5MB)";
                }
            } else {
                $erros[] = "❌ $filename - formato não permitido";
            }
        }
    }
}

// Buscar imagens existentes
$sql = "SELECT * FROM produto_imagens ORDER BY created_at DESC";
$imagens_result = mysqli_query($conexao, $sql);

// Processar exclusão
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Buscar arquivo para deletar
    $sql = "SELECT caminho FROM produto_imagens WHERE id = ?";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $arquivo = '../../../' . $row['caminho'];
        if (file_exists($arquivo)) {
            unlink($arquivo);
        }
        
        // Deletar do banco
        $sql_delete = "DELETE FROM produto_imagens WHERE id = ?";
        $stmt_delete = mysqli_prepare($conexao, $sql_delete);
        mysqli_stmt_bind_param($stmt_delete, "i", $id);
        mysqli_stmt_execute($stmt_delete);
        
        $sucessos[] = "✅ Imagem deletada com sucesso";
    }
    
    // Recarregar imagens
    $imagens_result = mysqli_query($conexao, "SELECT * FROM produto_imagens ORDER BY created_at DESC");
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galeria de Imagens - Produtos</title>
    <link rel="stylesheet" href="../../css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp" rel="stylesheet">
    <style>
        .gallery-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .gallery-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .gallery-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--color-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .upload-section {
            background: var(--color-white);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
        }
        
        .upload-area {
            border: 2px dashed var(--color-primary);
            border-radius: 8px;
            padding: 3rem 2rem;
            text-align: center;
            background: rgba(255, 0, 212, 0.05);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .upload-area:hover {
            background: rgba(255, 0, 212, 0.1);
        }
        
        .upload-area.dragover {
            border-color: var(--color-success);
            background: rgba(65, 241, 182, 0.1);
        }
        
        .upload-icon {
            font-size: 4rem;
            color: var(--color-primary);
            margin-bottom: 1rem;
        }
        
        .upload-text {
            font-size: 1.2rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .upload-help {
            color: var(--color-info-dark);
        }
        
        .file-input {
            display: none;
        }
        
        .btn-upload {
            background: var(--color-primary);
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            margin-top: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .image-card {
            background: var(--color-white);
            border-radius: 12px;
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .image-card:hover {
            transform: translateY(-4px);
        }
        
        .image-preview {
            height: 200px;
            overflow: hidden;
            position: relative;
        }
        
        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .image-actions {
            position: absolute;
            top: 8px;
            right: 8px;
            display: flex;
            gap: 0.5rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .image-card:hover .image-actions {
            opacity: 1;
        }
        
        .btn-action {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-copy {
            background: var(--color-success);
            color: white;
        }
        
        .btn-delete {
            background: var(--color-danger);
            color: white;
        }
        
        .image-info {
            padding: 1rem;
        }
        
        .image-name {
            font-weight: 500;
            margin-bottom: 0.5rem;
            word-break: break-word;
        }
        
        .image-details {
            font-size: 0.85rem;
            color: var(--color-info-dark);
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-success {
            background: rgba(65, 241, 182, 0.1);
            color: var(--color-success);
            border-left: 4px solid var(--color-success);
        }
        
        .alert-error {
            background: rgba(255, 0, 212, 0.1);
            color: var(--color-danger);
            border-left: 4px solid var(--color-danger);
        }
        
        .back-btn {
            background: var(--color-light);
            color: var(--color-dark-variant);
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .back-btn:hover {
            background: var(--color-info-light);
        }
    </style>
</head>
<body>
    <div class="gallery-container">
        <a href="products.php" class="back-btn">
            <span class="material-symbols-sharp">arrow_back</span>
            Voltar aos Produtos
        </a>
        
        <?php if (!empty($sucessos)): ?>
            <div class="alert alert-success">
                <span class="material-symbols-sharp">check_circle</span>
                <div>
                    <?php foreach ($sucessos as $sucesso): ?>
                        <div><?php echo $sucesso; ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($erros)): ?>
            <div class="alert alert-error">
                <span class="material-symbols-sharp">error</span>
                <div>
                    <?php foreach ($erros as $erro): ?>
                        <div><?php echo $erro; ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="gallery-header">
            <h1 class="gallery-title">
                <span class="material-symbols-sharp">photo_library</span>
                Galeria de Imagens dos Produtos
            </h1>
        </div>
        
        <!-- Upload Section -->
        <div class="upload-section">
            <h2>Adicionar Novas Imagens</h2>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="upload-area" onclick="document.getElementById('imagens').click()">
                    <div class="upload-icon">
                        <span class="material-symbols-sharp">cloud_upload</span>
                    </div>
                    <div class="upload-text">Clique ou arraste imagens aqui</div>
                    <div class="upload-help">
                        Suporta JPG, PNG, GIF, WebP até 5MB cada<br>
                        Você pode selecionar várias imagens de uma vez
                    </div>
                    <input type="file" id="imagens" name="imagens[]" class="file-input" multiple accept="image/*" onchange="showSelectedFiles()">
                </div>
                
                <div id="selectedFiles" style="margin-top: 1rem; display: none;">
                    <h3>Arquivos selecionados:</h3>
                    <div id="filesList"></div>
                    <button type="submit" class="btn-upload">
                        <span class="material-symbols-sharp">upload</span>
                        Enviar Imagens
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Gallery Grid -->
        <div class="gallery-grid">
            <?php if (mysqli_num_rows($imagens_result) > 0): ?>
                <?php while ($imagem = mysqli_fetch_assoc($imagens_result)): ?>
                    <div class="image-card">
                        <div class="image-preview">
                            <img src="../../../<?php echo htmlspecialchars($imagem['caminho']); ?>" 
                                 alt="<?php echo htmlspecialchars($imagem['nome']); ?>">
                            
                            <div class="image-actions">
                                <button class="btn-action btn-copy" 
                                        title="Copiar caminho" 
                                        onclick="copyImagePath('<?php echo htmlspecialchars($imagem['nome_arquivo']); ?>')">
                                    <span class="material-symbols-sharp">content_copy</span>
                                </button>
                                
                                <a href="?delete=<?php echo $imagem['id']; ?>" 
                                   class="btn-action btn-delete" 
                                   title="Deletar imagem"
                                   onclick="return confirm('Tem certeza que deseja deletar esta imagem?')">
                                    <span class="material-symbols-sharp">delete</span>
                                </a>
                            </div>
                        </div>
                        
                        <div class="image-info">
                            <div class="image-name"><?php echo htmlspecialchars($imagem['nome']); ?></div>
                            <div class="image-details">
                                <div><strong>Arquivo:</strong> <?php echo htmlspecialchars($imagem['nome_arquivo']); ?></div>
                                <div><strong>Tamanho:</strong> <?php echo number_format($imagem['tamanho'] / 1024, 1); ?> KB</div>
                                <div><strong>Tipo:</strong> <?php echo htmlspecialchars($imagem['tipo']); ?></div>
                                <div><strong>Upload:</strong> <?php echo date('d/m/Y H:i', strtotime($imagem['created_at'])); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--color-info-dark);">
                    <span class="material-symbols-sharp" style="font-size: 4rem; opacity: 0.5;">image</span>
                    <h3>Nenhuma imagem encontrada</h3>
                    <p>Faça upload das primeiras imagens para seus produtos</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Mostrar arquivos selecionados
        function showSelectedFiles() {
            const input = document.getElementById('imagens');
            const selectedDiv = document.getElementById('selectedFiles');
            const filesList = document.getElementById('filesList');
            
            if (input.files.length > 0) {
                selectedDiv.style.display = 'block';
                filesList.innerHTML = '';
                
                Array.from(input.files).forEach(file => {
                    const fileItem = document.createElement('div');
                    fileItem.style.padding = '0.5rem';
                    fileItem.style.background = 'var(--color-background)';
                    fileItem.style.borderRadius = '4px';
                    fileItem.style.marginBottom = '0.5rem';
                    fileItem.innerHTML = `
                        📁 ${file.name} (${(file.size / 1024).toFixed(1)} KB)
                    `;
                    filesList.appendChild(fileItem);
                });
            } else {
                selectedDiv.style.display = 'none';
            }
        }
        
        // Copiar caminho da imagem
        function copyImagePath(filename) {
            const path = `assets/images/produtos/${filename}`;
            navigator.clipboard.writeText(path).then(() => {
                // Feedback visual
                const btn = event.target.closest('.btn-copy');
                const originalIcon = btn.querySelector('.material-symbols-sharp').textContent;
                btn.querySelector('.material-symbols-sharp').textContent = 'check';
                btn.style.background = 'var(--color-success)';
                
                setTimeout(() => {
                    btn.querySelector('.material-symbols-sharp').textContent = originalIcon;
                    btn.style.background = 'var(--color-success)';
                }, 2000);
            });
        }
        
        // Drag and drop
        const uploadArea = document.querySelector('.upload-area');
        const fileInput = document.getElementById('imagens');
        
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', function() {
            this.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            fileInput.files = e.dataTransfer.files;
            showSelectedFiles();
        });
    </script>
</body>
</html>