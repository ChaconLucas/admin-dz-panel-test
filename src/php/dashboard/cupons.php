<?php
session_start();
// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../../../PHP/login.php');
    exit();
}

// API para buscar dados de um cupom específico
if (isset($_GET['action']) && $_GET['action'] === 'get_cupom' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    require_once '../sistema.php';
    global $conexao;
    
    $cupom_id = intval($_GET['id']);
    $stmt = $conexao->prepare("SELECT * FROM cupons WHERE id = ?");
    $stmt->bind_param("i", $cupom_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($cupom = $result->fetch_assoc()) {
        echo json_encode($cupom);
    } else {
        echo json_encode(['error' => 'Cupom não encontrado']);
    }
    exit();
}

// Calcular mensagens não lidas
require_once '../sistema.php';
global $conexao;

$nao_lidas = 0;
try {
    $result = $conexao->query("SELECT COUNT(*) as total FROM mensagens WHERE lida = FALSE AND remetente != 'admin'");
    $nao_lidas = $result ? $result->fetch_assoc()['total'] : 0;
} catch (Exception $e) {
    error_log("Erro ao contar mensagens: " . $e->getMessage());
}

// Criar tabela de cupons se não existir
$table_check = mysqli_query($conexao, "SHOW TABLES LIKE 'cupons'");
if (mysqli_num_rows($table_check) == 0) {
    mysqli_query($conexao, "
        CREATE TABLE cupons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(50) UNIQUE NOT NULL,
            tipo_desconto ENUM('porcentagem', 'valor_fixo', 'frete_gratis', 'brinde', 'progressivo') NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            valor_minimo DECIMAL(10,2) DEFAULT 0,
            data_expiracao DATE NOT NULL,
            limite_uso_total INT DEFAULT 100,
            limite_uso_cpf INT DEFAULT 1,
            uso_diario INT DEFAULT 50,
            primeira_compra BOOLEAN DEFAULT 0,
            categoria_especifica VARCHAR(50) DEFAULT NULL,
            brinde_item VARCHAR(100) DEFAULT NULL,
            progressivo_config JSON DEFAULT NULL,
            usos_realizados INT DEFAULT 0,
            economia_gerada DECIMAL(12,2) DEFAULT 0,
            ativo BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} else {
    // Verificar se colunas existem e adicionar se necessário
    $columns_to_add = [
        'limite_uso_total' => 'INT DEFAULT 100',
        'limite_uso_cpf' => 'INT DEFAULT 1', 
        'usos_realizados' => 'INT DEFAULT 0',
        'uso_diario' => 'INT DEFAULT 50',
        'primeira_compra' => 'BOOLEAN DEFAULT 0',
        'categoria_especifica' => 'VARCHAR(50) DEFAULT NULL',
        'brinde_item' => 'VARCHAR(100) DEFAULT NULL',
        'progressivo_config' => 'JSON DEFAULT NULL',
        'economia_gerada' => 'DECIMAL(12,2) DEFAULT 0'
    ];
    
    // Atualizar ENUM do tipo_desconto se necessário
    $check_enum = mysqli_query($conexao, "SHOW COLUMNS FROM cupons WHERE Field = 'tipo_desconto'");
    if ($check_enum) {
        $enum_info = mysqli_fetch_assoc($check_enum);
        if (strpos($enum_info['Type'], 'frete_gratis') === false) {
            mysqli_query($conexao, "ALTER TABLE cupons MODIFY tipo_desconto ENUM('porcentagem', 'valor_fixo', 'frete_gratis', 'brinde', 'progressivo')");
        }
    }
    
    foreach ($columns_to_add as $column => $definition) {
        $check_column = mysqli_query($conexao, "SHOW COLUMNS FROM cupons LIKE '$column'");
        if (mysqli_num_rows($check_column) == 0) {
            mysqli_query($conexao, "ALTER TABLE cupons ADD COLUMN $column $definition");
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['salvar_cupom']) || isset($_POST['ajax'])) {
            $codigo = strtoupper(trim($_POST['codigo']));
            $tipo_desconto = $_POST['tipo_desconto'];
            $valor = floatval($_POST['valor']);
            $valor_minimo = floatval($_POST['valor_minimo']);
            $data_expiracao = $_POST['data_expiracao'];
            $limite_uso_total = intval($_POST['limite_uso_total']);
            $limite_uso_cpf = intval($_POST['limite_uso_cpf']);
            $uso_diario = intval($_POST['uso_diario'] ?? 50);
            $primeira_compra = isset($_POST['primeira_compra']) ? 1 : 0;
            $categoria_especifica = !empty($_POST['categoria_especifica']) ? $_POST['categoria_especifica'] : NULL;
            $brinde_item = !empty($_POST['brinde_item']) ? $_POST['brinde_item'] : NULL;
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $cupom_id = isset($_POST['cupom_id']) ? intval($_POST['cupom_id']) : 0;
            
            // Configuração progressiva (JSON)
            $progressivo_config = NULL;
            if ($tipo_desconto === 'progressivo') {
                $progressivo_config = json_encode([
                    '1_item' => floatval($_POST['prog_1'] ?? 0),
                    '2_itens' => floatval($_POST['prog_2'] ?? 0),
                    '3_itens' => floatval($_POST['prog_3'] ?? 0)
                ]);
            }
            
            // Validações
            if (empty($codigo)) {
                throw new Exception('Código do cupom é obrigatório');
            }
            
            // Verificar se já existe um cupom com este código (exceto o próprio cupom sendo editado)
            if ($cupom_id > 0) {
                $check_codigo = mysqli_prepare($conexao, "SELECT id FROM cupons WHERE codigo = ? AND id != ?");
                mysqli_stmt_bind_param($check_codigo, "si", $codigo, $cupom_id);
            } else {
                $check_codigo = mysqli_prepare($conexao, "SELECT id FROM cupons WHERE codigo = ?");
                mysqli_stmt_bind_param($check_codigo, "s", $codigo);
            }
            mysqli_stmt_execute($check_codigo);
            $result_check = mysqli_stmt_get_result($check_codigo);
            
            if (mysqli_num_rows($result_check) > 0) {
                throw new Exception("Já existe um cupom com o código '{$codigo}'. Escolha outro código.");
            }
            mysqli_stmt_close($check_codigo);
            
            // Validações específicas por tipo
            if (in_array($tipo_desconto, ['porcentagem', 'valor_fixo'])) {
                if ($valor <= 0) {
                    throw new Exception('Valor deve ser maior que zero');
                }
                if ($tipo_desconto === 'porcentagem' && $valor > 100) {
                    throw new Exception('Desconto em porcentagem não pode ser maior que 100%');
                }
            }
            
            if ($tipo_desconto === 'brinde' && empty($brinde_item)) {
                throw new Exception('Item do brinde é obrigatório para cupons de brinde');
            }
            
            if ($tipo_desconto === 'progressivo' && empty($progressivo_config)) {
                throw new Exception('Configure pelo menos um nível de desconto progressivo');
            }
            
            // Para frete grátis, zerar o valor
            if ($tipo_desconto === 'frete_gratis') {
                $valor = 0;
            }
            
            if ($limite_uso_total <= 0) {
                throw new Exception('Limite de uso total deve ser maior que zero');
            }
            if ($limite_uso_cpf <= 0) {
                throw new Exception('Limite de uso por CPF deve ser maior que zero');
            }
            
            // Verificar se é edição ou criação
            if ($cupom_id > 0) {
                // ATUALIZAR cupom existente
                $stmt = $conexao->prepare("
                    UPDATE cupons SET 
                        codigo = ?, tipo_desconto = ?, valor = ?, valor_minimo = ?,
                        data_expiracao = ?, limite_uso_total = ?, limite_uso_cpf = ?, 
                        uso_diario = ?, primeira_compra = ?, categoria_especifica = ?, 
                        brinde_item = ?, progressivo_config = ?, ativo = ?
                    WHERE id = ?
                ");
                
                $stmt->bind_param("ssddsiiiisssii", 
                    $codigo, $tipo_desconto, $valor, $valor_minimo,
                    $data_expiracao, $limite_uso_total, $limite_uso_cpf,
                    $uso_diario, $primeira_compra, $categoria_especifica,
                    $brinde_item, $progressivo_config, $ativo, $cupom_id
                );
                
                $stmt->execute();
                $message_text = "Cupom {$codigo} atualizado com sucesso!";
            } else {
                // CRIAR novo cupom
                $stmt = $conexao->prepare("
                    INSERT INTO cupons (
                        codigo, tipo_desconto, valor, valor_minimo, data_expiracao, 
                        limite_uso_total, limite_uso_cpf, uso_diario, primeira_compra, 
                        categoria_especifica, brinde_item, progressivo_config, ativo
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->bind_param("ssddsiiiisssi", 
                    $codigo, $tipo_desconto, $valor, $valor_minimo, $data_expiracao,
                    $limite_uso_total, $limite_uso_cpf, $uso_diario, $primeira_compra,
                    $categoria_especifica, $brinde_item, $progressivo_config, $ativo
                );
                
                $stmt->execute();
                $message_text = "Cupom {$codigo} criado com sucesso!";
            }
            
            if (isset($_POST['ajax'])) {
                echo json_encode([
                    'success' => true, 
                    'message' => $message_text,
                    'codigo' => $codigo
                ]);
                exit();
            }
        }
        
        if (isset($_POST['toggle_status'])) {
            $id = intval($_POST['cupom_id']);
            $status = intval($_POST['status']);
            
            $stmt = $conexao->prepare("UPDATE cupons SET ativo = ? WHERE id = ?");
            $stmt->bind_param("ii", $status, $id);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
            exit();
        }
        
        if (isset($_POST['deletar_cupom'])) {
            $id = intval($_POST['cupom_id']);
            
            $stmt = $conexao->prepare("DELETE FROM cupons WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
        }
        
    } catch (Exception $e) {
        if (isset($_POST['ajax'])) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
    }
}

// Carregar cupons existentes
$cupons = [];
$result = mysqli_query($conexao, "SELECT * FROM cupons ORDER BY created_at DESC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $cupons[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <!-- FONT AWESOME -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css" />
    <link rel="stylesheet" href="../../css/dashboard.css" />
    <link rel="stylesheet" href="../../css/dashboard-sections.css" />
    <link rel="stylesheet" href="../../css/dashboard-cards.css" />
    <link
      href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp"
      rel="stylesheet"
    />
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Aplicar tema imediatamente -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('darkTheme');
            if (savedTheme === 'true' || savedTheme === null) {
                document.body.classList.add('dark-theme-variables');
            } else {
                document.body.classList.remove('dark-theme-variables');
            }
        })();
    </script>
    
    <!-- Scripts globais para funções dos botões -->
    <script>
        // Funções globais que devem estar disponíveis antes dos elementos
        window.editarCupom = function(id) {
            // Buscar dados do cupom
            fetch('?action=get_cupom&id=' + id)
                .then(response => response.json())
                .then(cupom => {
                    if (cupom.error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: cupom.error,
                            confirmButtonColor: '#ff00cc'
                        });
                        return;
                    }
                    
                    // Preencher formulário do modal
                    document.getElementById('edit_cupom_id').value = cupom.id;
                    document.getElementById('edit_codigo').value = cupom.codigo;
                    document.getElementById('edit_tipo_desconto').value = cupom.tipo_desconto;
                    document.getElementById('edit_valor').value = cupom.valor;
                    document.getElementById('edit_valor_minimo').value = cupom.valor_minimo;
                    document.getElementById('edit_data_expiracao').value = cupom.data_expiracao;
                    document.getElementById('edit_limite_uso_total').value = cupom.limite_uso_total;
                    document.getElementById('edit_limite_uso_cpf').value = cupom.limite_uso_cpf;
                    document.getElementById('edit_uso_diario').value = cupom.uso_diario;
                    document.getElementById('edit_categoria_especifica').value = cupom.categoria_especifica || '';
                    document.getElementById('edit_brinde_item').value = cupom.brinde_item || '';
                    
                    // Checkboxes
                    document.getElementById('edit_primeira_compra').checked = cupom.primeira_compra == 1;
                    document.getElementById('edit_ativo').checked = cupom.ativo == 1;
                    
                    // Atualizar switches visualmente
                    const primeiraCompraContainer = document.getElementById('edit_primeira_compra').closest('.switch-container');
                    const ativoContainer = document.getElementById('edit_ativo').closest('.switch-container');
                    
                    if (cupom.primeira_compra == 1) {
                        primeiraCompraContainer.classList.add('active');
                    } else {
                        primeiraCompraContainer.classList.remove('active');
                    }
                    
                    if (cupom.ativo == 1) {
                        ativoContainer.classList.add('active');
                    } else {
                        ativoContainer.classList.remove('active');
                    }
                    
                    // Campos progressivos se aplicável
                    if (cupom.tipo_desconto === 'progressivo' && cupom.progressivo_config) {
                        try {
                            const config = JSON.parse(cupom.progressivo_config);
                            document.getElementById('edit_prog_1').value = config['1_item'] || '';
                            document.getElementById('edit_prog_2').value = config['2_itens'] || '';
                            document.getElementById('edit_prog_3').value = config['3_itens'] || '';
                        } catch (e) {
                            console.error('Erro ao parsear configuração progressiva:', e);
                        }
                    }
                    
                    // Mostrar campos especiais baseados no tipo
                    mostrarCamposEspeciaisModal();
                    
                    // Alterar título do modal
                    document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-edit" style="color: white;"></i> Editar Cupom: ' + cupom.codigo;
                    
                    // Abrir modal
                    document.getElementById('modalEdicao').style.display = 'block';
                })
                .catch(error => {
                    console.error('Erro:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Erro ao carregar dados do cupom.',
                        confirmButtonColor: '#ff00cc'
                    });
                });
        };

        // Funções do Modal
        window.fecharModal = function() {
            document.getElementById('modalEdicao').style.display = 'none';
        };
        
        window.mostrarCamposEspeciaisModal = function() {
            const tipo = document.getElementById('edit_tipo_desconto').value;
            const valorGroup = document.querySelector('#edit_valor').closest('.form-group');
            
            // Ocultar todos os campos especiais primeiro
            document.querySelectorAll('#modalEdicao .campos-especiais').forEach(campo => {
                campo.classList.remove('show');
            });
            
            // Mostrar campos específicos baseado no tipo
            switch(tipo) {
                case 'brinde':
                    document.getElementById('edit-campos-brinde').classList.add('show');
                    valorGroup.style.display = 'none';
                    break;
                case 'progressivo':
                    document.getElementById('edit-campos-progressivo').classList.add('show');
                    valorGroup.style.display = 'block';
                    break;
                case 'frete_gratis':
                    valorGroup.style.display = 'none';
                    break;
                default:
                    valorGroup.style.display = 'block';
            }
        };
        
        window.toggleSwitchModal = function(fieldName) {
            const checkbox = document.getElementById(fieldName);
            const container = checkbox.closest('.switch-container');
            
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                container.classList.add('active');
            } else {
                container.classList.remove('active');
            }
        };
        
        window.salvarEdicao = function() {
            const formData = new FormData(document.getElementById('formEdicao'));
            formData.append('ajax', '1');
            
            // Validação especial para frete grátis
            const tipoDesconto = document.getElementById('edit_tipo_desconto').value;
            if (tipoDesconto === 'frete_gratis') {
                formData.set('valor', '0');
            }
            
            // Coletar configuração progressiva se aplicável
            if (tipoDesconto === 'progressivo') {
                const prog1 = document.getElementById('edit_prog_1').value;
                const prog2 = document.getElementById('edit_prog_2').value;
                const prog3 = document.getElementById('edit_prog_3').value;
                
                const config = {
                    '1_item': parseFloat(prog1 || 0),
                    '2_itens': parseFloat(prog2 || 0),
                    '3_itens': parseFloat(prog3 || 0)
                };
                
                formData.append('progressivo_config', JSON.stringify(config));
            }
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: data.message,
                        confirmButtonColor: '#ff00cc',
                        showConfirmButton: true,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        fecharModal();
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: data.message,
                        confirmButtonColor: '#ff00cc'
                    });
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Erro ao salvar alterações.',
                    confirmButtonColor: '#ff00cc'
                });
            });
        };

        window.excluirCupom = function(id) {
            Swal.fire({
                title: 'Tem certeza?',
                text: "Você não poderá desfazer esta ação!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, excluir!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('deletar_cupom', '1');
                    formData.append('cupom_id', id);
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        Swal.fire({
                            icon: 'success',
                            title: 'Excluído!',
                            text: 'Cupom excluído com sucesso.',
                            confirmButtonColor: '#ff00cc',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: 'Erro ao excluir cupom.',
                            confirmButtonColor: '#ff00cc'
                        });
                    });
                }
            });
        };
        
        window.toggleSwitch = function(fieldName) {
            const checkbox = document.getElementById(fieldName);
            const container = checkbox.closest('.switch-container');
            
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                container.classList.add('active');
            } else {
                container.classList.remove('active');
            }
        };
        
        window.mostrarCamposEspeciais = function() {
            const tipo = document.getElementById('tipo_desconto').value;
            const valorGroup = document.querySelector('#valor').closest('.form-group');
            
            // Ocultar todos os campos especiais primeiro
            document.querySelectorAll('.campos-especiais').forEach(campo => {
                campo.classList.remove('show');
            });
            
            // Mostrar campos específicos baseado no tipo
            switch(tipo) {
                case 'brinde':
                    document.getElementById('campos-brinde').classList.add('show');
                    valorGroup.style.display = 'none';
                    break;
                case 'progressivo':
                    document.getElementById('campos-progressivo').classList.add('show');
                    valorGroup.style.display = 'block';
                    break;
                case 'frete_gratis':
                    valorGroup.style.display = 'none';
                    break;
                default:
                    valorGroup.style.display = 'block';
            }
        };
    </script>
    
    <title>Cupons de Desconto - D&Z</title>
    <style>
        /* Variáveis CSS para modo dark */
        :root {
            --cupom-bg: #ffffff;
            --cupom-text: #333333;
            --cupom-border: #ddd;
            --cupom-shadow: 0 2px 10px rgba(0,0,0,0.1);
            --cupom-input-bg: #ffffff;
            --cupom-modal-bg: #ffffff;
        }
        
        body.dark-theme-variables {
            --cupom-bg: #202528;
            --cupom-text: #edeffd;
            --cupom-border: rgba(255,255,255,0.1);
            --cupom-shadow: 0 2px 10px rgba(0,0,0,0.4);
            --cupom-input-bg: #2a2d31;
            --cupom-modal-bg: #202528;
        }
        
        /* Garantir que o layout principal funcione corretamente */
        .container {
            display: grid !important;
            width: 96% !important;
            margin: 0 auto !important;
            gap: 1.8rem !important;
            grid-template-columns: 14rem auto 18rem !important;
            min-height: 100vh !important;
        }
        
        main {
            background: transparent !important;
            padding: 1.5rem !important;
            overflow-x: auto !important;
        }
        
        .cupons-container {
            padding: 2rem 0;
        }
        
        body.dark-theme-variables .cupons-container {
            color: var(--cupom-text);
        }
        
        body.dark-theme-variables .cupons-container {
            color: var(--cupom-text);
        }
        
        .cupom-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        body.dark-theme-variables .cupom-card {
            background: var(--cupom-bg);
            box-shadow: var(--cupom-shadow);
        }
        
        .cupom-card h2 {
            color: #333;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        body.dark-theme-variables .cupom-card h2 {
            color: var(--cupom-text);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #555;
        }
        
        body.dark-theme-variables .form-group label {
            color: var(--cupom-text);
        }
        
        small {
            color: #666;
        }
        
        body.dark-theme-variables small {
            color: var(--cupom-text) !important;
            opacity: 0.7;
        }
        
        .text-muted {
            color: #666;
        }
        
        body.dark-theme-variables .text-muted {
            color: var(--cupom-text) !important;
            opacity: 0.7;
        }
        
        .codigo-info {
            color: #666;
            font-size: 0.8rem;
            margin-top: 0.5rem;
            display: block;
        }
        
        body.dark-theme-variables .codigo-info {
            color: var(--cupom-text) !important;
            opacity: 0.8;
        }
        
        .form-hint {
            color: #666;
            font-size: 0.8rem;
        }
        
        body.dark-theme-variables .form-hint {
            color: var(--cupom-text) !important;
            opacity: 0.7;
        }
        
        .table-date, .table-desc {
            color: #6c757d;
            font-size: 0.8rem;
        }
        
        body.dark-theme-variables .table-date,
        body.dark-theme-variables .table-desc {
            color: var(--cupom-text) !important;
            opacity: 0.7;
        }
        
        input[type="date"] {
            background: #ffffff;
            color: #333;
        }
        
        body.dark-theme-variables input[type="date"] {
            background: var(--cupom-input-bg) !important;
            color: var(--cupom-text) !important;
            border-color: var(--cupom-border) !important;
        }
        
        body.dark-theme-variables input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
        }
        
        /* Garantir que todos os elementos tenham cores corretas no modo dark */
        body.dark-theme-variables * {
            border-color: var(--cupom-border);
        }
        
        body.dark-theme-variables label {
            color: var(--cupom-text) !important;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
            background: #ffffff;
            color: #333;
        }
        
        body.dark-theme-variables .form-group input,
        body.dark-theme-variables .form-group select {
            background: var(--cupom-input-bg);
            color: var(--cupom-text);
            border-color: var(--cupom-border);
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #ff00cc;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .btn-save {
            background: #ff00cc;
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            font-size: 1rem;
        }
        
        body.dark-theme-variables .btn-save {
            background: #ff00cc;
            box-shadow: 0 2px 10px rgba(255, 0, 204, 0.3);
        }
        
        .btn-save:hover {
            background: #e600b5;
            transform: translateY(-2px);
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        
        body.dark-theme-variables .btn-cancel {
            background: #4a4a4a;
            color: var(--cupom-text);
        }
        
        .btn-cancel:hover {
            background: #545b62;
            transform: translateY(-2px);
        }
        
        .cupons-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        body.dark-theme-variables .cupons-table {
            background: var(--cupom-bg);
            box-shadow: var(--cupom-shadow);
        }
        
        .cupons-table th, .cupons-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        body.dark-theme-variables .cupons-table th,
        body.dark-theme-variables .cupons-table td {
            border-bottom-color: var(--cupom-border);
            color: var(--cupom-text);
        }
        
        .cupons-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
        }
        
        body.dark-theme-variables .cupons-table th {
            background: #2a2d31;
            color: var(--cupom-text);
        }
        
        .cupons-table td {
            font-size: 0.9rem;
            vertical-align: middle;
        }
        
        .cupons-table tbody tr:hover {
            background: rgba(255, 0, 204, 0.02);
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #ff00cc;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .btn-delete:hover {
            background: #c82333;
        }
        
        .status-ativo {
            background: #28a745;
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-inativo {
            background: #6c757d;
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-expirado {
            background: #fd7e14;
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .progress-uso {
            font-size: 0.85rem;
            color: #495057;
            background: #f8f9fa;
            padding: 0.3rem 0.6rem;
            border-radius: 10px;
            display: inline-block;
        }
        
        .btn-edit {
            background: #ff00cc;
            color: white;
            border: none;
            padding: 0.5rem 0.8rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            margin-right: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-edit:hover {
            background: #e600b5;
            transform: translateY(-1px);
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 0.8rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-1px);
        }
        
        .switch-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 1rem 0;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        body.dark-theme-variables .switch-container {
            background: var(--cupom-input-bg);
            color: var(--cupom-text);
        }
        
        .switch-container.active {
            background: rgba(255, 0, 204, 0.05);
            border-color: #ff00cc;
        }
        
        body.dark-theme-variables .switch-container.active {
            background: rgba(255, 0, 204, 0.1);
            border-color: #ff40d6;
        }
        
        .switch-label {
            font-weight: 600;
            color: #374151;
        }
        
        body.dark-theme-variables .switch-label {
            color: var(--cupom-text);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .campos-especiais {
            display: none;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 1rem 0;
            border-left: 4px solid #ff00cc;
        }
        
        body.dark-theme-variables .campos-especiais {
            background: var(--cupom-input-bg);
            color: var(--cupom-text);
        }
        
        .campos-especiais.show {
            display: block;
        }
        
        .progressivo-config {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }
        
        .icon-tipo {
            font-size: 1.2rem;
            margin-right: 0.5rem;
            vertical-align: middle;
        }
        
        .economia-valor {
            color: #28a745;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        /* Estilos do Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: modalShow 0.3s ease;
        }
        
        body.dark-theme-variables .modal-content {
            background-color: var(--cupom-modal-bg);
            color: var(--cupom-text);
        }
        
        @keyframes modalShow {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            background: linear-gradient(135deg, #ff00cc, #e600b5);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }
        
        .close {
            color: white;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .close:hover {
            transform: scale(1.1);
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .modal-footer {
            padding: 1.5rem 2rem;
            background: #f8f9fa;
            border-radius: 0 0 15px 15px;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            border-top: 1px solid #dee2e6;
        }
        
        body.dark-theme-variables .modal-footer {
            background: #2a2d31;
            border-top-color: var(--cupom-border);
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }
        
        body.dark-theme-variables .btn-cancel:hover {
            background: #3a3a3a;
        }
    </style>
  </head>

  <body>
    <div class="container">
      <aside>
        <div class="top">
          <div class="logo">
            <img src="../../../assets/images/Logodz.png" />
            <a href="index.php"><h2 class="danger">D&Z</h2></a>
          </div>

          <div class="close" id="close-btn">
            <span class="material-symbols-sharp">close</span>
          </div>
        </div>

        <div class="sidebar">
          <a href="index.php" id="dashboard-link">
            <span class="material-symbols-sharp">grid_view</span>
            <h3>Painel</h3>
          </a>

          <a href="customers.php" id="clientes-link">
            <span class="material-symbols-sharp">group</span>
            <h3>Clientes</h3>
          </a>

          <a href="orders.php" id="pedidos-link">
            <span class="material-symbols-sharp">Orders</span>
            <h3>Pedidos</h3>
          </a>

          <a href="analytics.php" id="graficos-link">
            <span class="material-symbols-sharp">Insights</span>
            <h3>Gráficos</h3>
          </a>

          <a href="menssage.php" id="mensagens-link">
            <span class="material-symbols-sharp">Mail</span>
            <h3>Mensagens</h3>
            <span class="message-count"><?php echo $nao_lidas; ?></span>
          </a>

          <a href="products.php" id="produtos-link">
            <span class="material-symbols-sharp">Inventory</span>
            <h3>Produtos</h3>
          </a>

          <a href="cupons.php" class="active" id="cupons-link">
            <span class="material-symbols-sharp">sell</span>
            <h3>Cupons</h3>
          </a>

          <a href="gestao-fluxo.php" id="gestao-fluxo-link">
            <span class="material-symbols-sharp">account_tree</span>
            <h3>Gestão de Fluxo</h3>
          </a>

          <div class="menu-item-container">
            <a href="geral.php" id="configuracoes-link" class="menu-item-with-submenu">
              <span class="material-symbols-sharp">Settings</span>
              <h3>Configurações</h3>
            </a>
            
            <div class="submenu">
              <a href="geral.php">
                <span class="material-symbols-sharp">tune</span>
                <h3>Geral</h3>
              </a>
              <a href="pagamentos.php">
                <span class="material-symbols-sharp">payments</span>
                <h3>Pagamentos</h3>
              </a>
              <a href="frete.php">
                <span class="material-symbols-sharp">local_shipping</span>
                <h3>Frete</h3>
              </a>
              <a href="automacao.php">
                <span class="material-symbols-sharp">automation</span>
                <h3>Automação</h3>
              </a>
              <a href="metricas.php">
                <span class="material-symbols-sharp">analytics</span>
                <h3>Métricas</h3>
              </a>
              <a href="settings.php">
                <span class="material-symbols-sharp">group</span>
                <h3>Usuários</h3>
              </a>
            </div>
          </div>

          <a href="revendedores.php">
            <span class="material-symbols-sharp">handshake</span>
            <h3>Revendedores</h3>
          </a>

          <a href="../../../PHP/logout.php">
            <span class="material-symbols-sharp">Logout</span>
            <h3>Sair</h3>
          </a>
        </div>
      </aside>

      <!----------FINAL ASIDE------------>
      <main>
        <h1>Cupons de Desconto</h1>

        <div class="cupons-container">
          <!-- Card de Cadastro -->
          <div class="cupom-card">
            <h2>
              <i class="fas fa-plus-circle" style="color: #ff00cc;"></i>
              Novo Cupom de Desconto
            </h2>

            <form method="POST" id="cupomForm">
              <div class="form-grid">
                <div class="form-group">
                  <label for="codigo">Código</label>
                  <input type="text" id="codigo" name="codigo" 
                         placeholder="Ex: BEMVINDA10" required>
                  <?php if (!empty($cupons)): ?>
                    <small class="codigo-info">
                      <strong>Códigos já utilizados:</strong> 
                      <?php 
                        $codigos_existentes = array_column($cupons, 'codigo');
                        echo implode(', ', array_slice($codigos_existentes, 0, 5));
                        if (count($codigos_existentes) > 5) echo '...';
                      ?>
                    </small>
                  <?php endif; ?>
                </div>
                
                <div class="form-group">
                  <label for="tipo_desconto">Tipo</label>
                  <select id="tipo_desconto" name="tipo_desconto" required onchange="mostrarCamposEspeciais()">
                    <option value="">Selecione</option>
                    <option value="porcentagem">Porcentagem (%)</option>
                    <option value="valor_fixo">Valor Fixo (R$)</option>
                    <option value="frete_gratis">Frete Grátis</option>
                    <option value="brinde">Brinde</option>
                    <option value="progressivo">Progressivo</option>
                  </select>
                </div>
              </div>

              <div class="form-grid">
                <div class="form-group">
                  <label for="valor">Valor</label>
                  <input type="number" step="0.01" min="0" id="valor" name="valor" 
                         placeholder="0.00" required>
                </div>

                <div class="form-group">
                  <label for="valor_minimo">Valor Mínimo</label>
                  <input type="number" step="0.01" min="0" id="valor_minimo" 
                         name="valor_minimo" placeholder="0.00">
                </div>
              </div>
              
              <div class="form-grid">
                <div class="form-group">
                  <label for="limite_uso_total">Limite Total</label>
                  <input type="number" min="1" id="limite_uso_total" 
                         name="limite_uso_total" value="100" required>
                  <small class="form-hint">
                    Quantas vezes o cupom pode ser usado
                  </small>
                </div>
                
                <div class="form-group">
                  <label for="limite_uso_cpf">Limite por CPF</label>
                  <input type="number" min="1" id="limite_uso_cpf" 
                         name="limite_uso_cpf" value="1" required>
                  <small class="form-hint">
                    Usos permitidos por CPF
                  </small>
                </div>
              </div>
              
              <div class="form-grid">
                <div class="form-group">
                  <label for="uso_diario">Uso Diário</label>
                  <input type="number" min="1" id="uso_diario" 
                         name="uso_diario" value="50" required>
                  <small class="form-hint">
                    Limite de usos por dia no site
                  </small>
                </div>
                
                <div class="form-group">
                  <label for="categoria_especifica">Categoria</label>
                  <select id="categoria_especifica" name="categoria_especifica">
                    <option value="">Todas as categorias</option>
                    <option value="esmaltes">Esmaltes</option>
                    <option value="acessorios">Acessórios</option>
                    <option value="kits">Kits</option>
                    <option value="cuidados">Cuidados</option>
                  </select>
                  <small class="form-hint">
                    Restringir por categoria
                  </small>
                </div>
              </div>
              
              <!-- Campos especiais baseados no tipo -->
              <div id="campos-brinde" class="campos-especiais">
                <div class="form-group">
                  <label for="brinde_item">Item do Brinde</label>
                  <input type="text" id="brinde_item" name="brinde_item" 
                         placeholder="Ex: Esmalte D&Z Rosa Chique">
                </div>
              </div>
              
              <div id="campos-progressivo" class="campos-especiais">
                <label>Configuração Progressiva</label>
                <div class="progressivo-config">
                  <div class="form-group">
                    <label for="prog_1">1 item (%)</label>
                    <input type="number" step="0.01" id="prog_1" name="prog_1" 
                           placeholder="5" min="0" max="100">
                  </div>
                  <div class="form-group">
                    <label for="prog_2">2+ itens (%)</label>
                    <input type="number" step="0.01" id="prog_2" name="prog_2" 
                           placeholder="10" min="0" max="100">
                  </div>
                  <div class="form-group">
                    <label for="prog_3">3+ itens (%)</label>
                    <input type="number" step="0.01" id="prog_3" name="prog_3" 
                           placeholder="15" min="0" max="100">
                  </div>
                </div>
              </div>
              
              <div class="switch-container" onclick="toggleSwitch('primeira_compra')">
                <span class="switch-label">Exclusivo Primeira Compra</span>
                <label class="toggle-switch">
                  <input type="checkbox" name="primeira_compra" id="primeira_compra" value="1">
                  <span class="slider"></span>
                </label>
              </div>

              <div class="form-group">
                <label for="data_expiracao">Data de Expiração</label>
                <input type="date" id="data_expiracao" name="data_expiracao" required>
              </div>
              
              <div class="switch-container" onclick="toggleSwitch('ativo')">
                <span class="switch-label">Cupom Ativo</span>
                <label class="toggle-switch">
                  <input type="checkbox" name="ativo" id="ativo" value="1" checked>
                  <span class="slider"></span>
                </label>
              </div>

              <button type="submit" name="salvar_cupom" class="btn-save">
                <i class="fas fa-save"></i>
                Salvar Cupom
              </button>
            </form>
          </div>

          <!-- Modal de Edição -->
          <div id="modalEdicao" class="modal">
            <div class="modal-content">
              <div class="modal-header">
                <h2 id="modalTitulo">
                  <i class="fas fa-edit" style="color: #ff00cc;"></i>
                  Editar Cupom
                </h2>
                <span class="close" onclick="fecharModal()">&times;</span>
              </div>
              
              <form id="formEdicao" class="modal-body">
                <input type="hidden" id="edit_cupom_id" name="cupom_id" value="">
                
                <div class="form-grid">
                  <div class="form-group">
                    <label for="edit_codigo">Código</label>
                    <input type="text" id="edit_codigo" name="codigo" required>
                  </div>
                  
                  <div class="form-group">
                    <label for="edit_tipo_desconto">Tipo</label>
                    <select id="edit_tipo_desconto" name="tipo_desconto" required onchange="mostrarCamposEspeciaisModal()">
                      <option value="">Selecione</option>
                      <option value="porcentagem">Porcentagem (%)</option>
                      <option value="valor_fixo">Valor Fixo (R$)</option>
                      <option value="frete_gratis">Frete Grátis</option>
                      <option value="brinde">Brinde</option>
                      <option value="progressivo">Progressivo</option>
                    </select>
                  </div>
                </div>
                
                <div class="form-grid">
                  <div class="form-group">
                    <label for="edit_valor">Valor</label>
                    <input type="number" step="0.01" id="edit_valor" name="valor" min="0" required>
                  </div>
                  
                  <div class="form-group">
                    <label for="edit_valor_minimo">Valor Mínimo</label>
                    <input type="number" step="0.01" id="edit_valor_minimo" name="valor_minimo" min="0" value="0">
                  </div>
                </div>
                
                <div class="form-grid">
                  <div class="form-group">
                    <label for="edit_limite_uso_total">Limite Total</label>
                    <input type="number" min="1" id="edit_limite_uso_total" name="limite_uso_total" value="100" required>
                  </div>
                  
                  <div class="form-group">
                    <label for="edit_limite_uso_cpf">Limite por CPF</label>
                    <input type="number" min="1" id="edit_limite_uso_cpf" name="limite_uso_cpf" value="1" required>
                  </div>
                </div>
                
                <div class="form-grid">
                  <div class="form-group">
                    <label for="edit_uso_diario">Uso Diário</label>
                    <input type="number" min="1" id="edit_uso_diario" name="uso_diario" value="50" required>
                  </div>
                  
                  <div class="form-group">
                    <label for="edit_categoria_especifica">Categoria</label>
                    <select id="edit_categoria_especifica" name="categoria_especifica">
                      <option value="">Todas as categorias</option>
                      <option value="esmaltes">Esmaltes</option>
                      <option value="acessorios">Acessórios</option>
                      <option value="kits">Kits</option>
                      <option value="cuidados">Cuidados</option>
                    </select>
                  </div>
                </div>
                
                <!-- Campos especiais modal -->
                <div id="edit-campos-brinde" class="campos-especiais">
                  <div class="form-group">
                    <label for="edit_brinde_item">Item do Brinde</label>
                    <input type="text" id="edit_brinde_item" name="brinde_item" 
                           placeholder="Ex: Esmalte D&Z Rosa Chique">
                  </div>
                </div>
                
                <div id="edit-campos-progressivo" class="campos-especiais">
                  <label>Configuração Progressiva</label>
                  <div class="progressivo-config">
                    <div class="form-group">
                      <label for="edit_prog_1">1 item (%)</label>
                      <input type="number" step="0.01" id="edit_prog_1" name="prog_1" 
                             placeholder="5" min="0" max="100">
                    </div>
                    <div class="form-group">
                      <label for="edit_prog_2">2+ itens (%)</label>
                      <input type="number" step="0.01" id="edit_prog_2" name="prog_2" 
                             placeholder="10" min="0" max="100">
                    </div>
                    <div class="form-group">
                      <label for="edit_prog_3">3+ itens (%)</label>
                      <input type="number" step="0.01" id="edit_prog_3" name="prog_3" 
                             placeholder="15" min="0" max="100">
                    </div>
                  </div>
                </div>
                
                <div class="switch-container" onclick="toggleSwitchModal('edit_primeira_compra')">
                  <span class="switch-label">Exclusivo Primeira Compra</span>
                  <label class="toggle-switch">
                    <input type="checkbox" name="primeira_compra" id="edit_primeira_compra" value="1">
                    <span class="slider"></span>
                  </label>
                </div>

                <div class="form-group">
                  <label for="edit_data_expiracao">Data de Expiração</label>
                  <input type="date" id="edit_data_expiracao" name="data_expiracao" required>
                </div>
                
                <div class="switch-container" onclick="toggleSwitchModal('edit_ativo')">
                  <span class="switch-label">Cupom Ativo</span>
                  <label class="toggle-switch">
                    <input type="checkbox" name="ativo" id="edit_ativo" value="1" checked>
                    <span class="slider"></span>
                  </label>
                </div>
              </form>
              
              <div class="modal-footer">
                <button type="button" onclick="fecharModal()" class="btn-cancel">
                  <i class="fas fa-times"></i>
                  Cancelar
                </button>
                <button type="button" onclick="salvarEdicao()" class="btn-save">
                  <i class="fas fa-save"></i>
                  Salvar Alterações
                </button>
              </div>
            </div>
          </div>

          <!-- Tabela de Listagem -->
          <div class="cupom-card">
            <h2>
              <i class="fas fa-list" style="color: #ff00cc;"></i>
              Cupons Cadastrados
            </h2>
            
            <?php if (empty($cupons)): ?>
                <p style="text-align: center; color: #666; padding: 2rem;">
                    Nenhum cupom cadastrado ainda. Crie o primeiro cupom acima!
                </p>
            <?php else: ?>
                <table class="cupons-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Tipo</th>
                            <th>Regras</th>
                            <th>Uso</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cupons as $cupom): ?>
                            <?php
                                $hoje = date('Y-m-d');
                                $expirado = $cupom['data_expiracao'] < $hoje;
                                $ativo = (bool)$cupom['ativo'];
                                
                                // Ícone e tipo do desconto
                                switch($cupom['tipo_desconto']) {
                                    case 'porcentagem':
                                        $tipo_icon = '📊';
                                        $tipo_text = 'Porcentagem';
                                        $desconto_text = $cupom['valor'] . '%';
                                        break;
                                    case 'valor_fixo':
                                        $tipo_icon = '💰';
                                        $tipo_text = 'Valor Fixo';
                                        $desconto_text = 'R$ ' . number_format($cupom['valor'], 2, ',', '.');
                                        break;
                                    case 'frete_gratis':
                                        $tipo_icon = '🚚';
                                        $tipo_text = 'Frete Grátis';
                                        $desconto_text = 'Frete R$ 0';
                                        break;
                                    case 'brinde':
                                        $tipo_icon = '🎁';
                                        $tipo_text = 'Brinde';
                                        $desconto_text = $cupom['brinde_item'] ?? 'Item grátis';
                                        break;
                                    case 'progressivo':
                                        $tipo_icon = '📈';
                                        $tipo_text = 'Progressivo';
                                        $desconto_text = 'Variável';
                                        break;
                                    default:
                                        $tipo_icon = '🏷️';
                                        $tipo_text = 'Desconto';
                                        $desconto_text = $cupom['valor'] . '%';
                                }
                                
                                // Regras do cupom
                                $regras = [];
                                if ($cupom['valor_minimo'] > 0) {
                                    $regras[] = 'Mín. R$ ' . number_format($cupom['valor_minimo'], 2, ',', '.');
                                }
                                if (!empty($cupom['categoria_especifica'])) {
                                    $regras[] = ucfirst($cupom['categoria_especifica']);
                                }
                                if (isset($cupom['primeira_compra']) && $cupom['primeira_compra']) {
                                    $regras[] = '1ª Compra';
                                }
                                $regras_text = !empty($regras) ? implode(' • ', $regras) : 'Sem restrições';
                                
                                // Lógica de status com validação de data
                                if ($expirado) {
                                    $status_class = 'status-expirado';
                                    $status_text = 'Expirado';
                                    $status_icon = '❌';
                                } else if ($ativo) {
                                    $status_class = 'status-ativo';
                                    $status_text = 'Ativo';
                                    $status_icon = '✅';
                                } else {
                                    $status_class = 'status-inativo';
                                    $status_text = 'Inativo';
                                    $status_icon = '⏸️';
                                }
                                
                                // Dados de uso
                                $usos = (int)($cupom['usos_realizados'] ?? 0);
                                $limite = (int)($cupom['limite_uso_total'] ?? 100);
                                
                                // Economia gerada
                                $economia = (float)($cupom['economia_gerada'] ?? 0);
                                $economia_text = $economia > 0 
                                    ? 'R$ ' . number_format($economia, 2, ',', '.') 
                                    : 'R$ 0,00';
                            ?>
                            <tr>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <strong style="color: #ff00cc; font-size: 1rem;">
                                            <?= htmlspecialchars($cupom['codigo']) ?>
                                        </strong>
                                        <small class="table-date">
                                            Expira: <?= date('d/m/Y', strtotime($cupom['data_expiracao'])) ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span style="font-size: 1.2rem;"><?= $tipo_icon ?></span>
                                        <div>
                                            <div style="font-weight: 600; color: #495057;"><?= $tipo_text ?></div>
                                            <small class="table-desc"><?= $desconto_text ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="color: #6c757d; font-size: 0.9rem;">
                                        <?= $regras_text ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; align-items: center;">
                                        <span class="progress-uso" style="font-weight: 600;">
                                            <?= $usos ?>/<?= $limite ?>
                                        </span>
                                        <div style="width: 100%; height: 4px; background: #e9ecef; border-radius: 2px; margin-top: 4px;">
                                            <div style="width: <?= $limite > 0 ? ($usos/$limite)*100 : 0 ?>%; height: 100%; background: #ff00cc; border-radius: 2px;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.3rem;">
                                        <span><?= $status_icon ?></span>
                                        <span class="<?= $status_class ?>">
                                            <?= $status_text ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.3rem;">
                                        <button class="btn-edit" onclick="editarCupom(<?= $cupom['id'] ?>)" title="Editar cupom">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-delete" onclick="excluirCupom(<?= $cupom['id'] ?>)" title="Excluir cupom">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
          </div>
        </div>
      </main>

      <!-- Parte direita mantida do dashboard original -->
      <div class="right">
        <div class="top">
          <button id="menu-btn">
            <span class="material-symbols-sharp"> menu </span>
          </button>
          <div class="theme-toggler">
            <span class="material-symbols-sharp active"> wb_sunny </span
            ><span class="material-symbols-sharp"> bedtime </span>
          </div>
          <div class="profile">
            <div class="info">
              <p>Olá, <b><?= isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : 'Usuário'; ?></b></p>
              <small class="text-muted">Admin</small>
            </div>
            <div class="profile-photo">
              <img src="../../../assets/images/profile-1.jpg" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNmZjAwY2MiLz4KPHBhdGggZD0iTTIwIDIwYzIuMjEgMCA0LTEuNzkgNC00cy0xLjc5LTQtNC00LTQgMS43OS00IDQgMS43OSA0IDQgNHptMCAyYy0yLjY3IDAtOCAxLjM0LTggNHYyaDE2di0yYzAtMi42Ni01LjMzLTQtOC00eiIgZmlsbD0iI2ZmZiIvPgo8L3N2Zz4='" alt="Profile" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="../../js/dashboard.js"></script>
    <script>
        function toggleStatus(cupomId, status) {
            const formData = new FormData();
            formData.append('toggle_status', '1');
            formData.append('cupom_id', cupomId);
            formData.append('status', status ? 1 : 0);
            
            fetch('cupons.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: 'Status atualizado com sucesso'
                    });
                } else {
                    console.error('Erro ao atualizar status');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
            })
            .then(data => {
                if (!data.success) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Erro ao alterar status do cupom'
                    });
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Erro ao alterar status do cupom'
                });
                location.reload();
            });
        }

        // Formatar código em maiúsculo
        document.getElementById('codigo').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });

        // Validar formulário
        document.querySelector('form').addEventListener('submit', function(e) {
            const tipo = document.getElementById('tipo_desconto').value;
            const valor = parseFloat(document.getElementById('valor').value);
            
            if (tipo === 'porcentagem' && valor > 100) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Atenção!',
                    text: 'Desconto em porcentagem não pode ser maior que 100%'
                });
                return false;
            }

        // AJAX para envio do formulário
        document.getElementById('cupomForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // Se o checkbox não estiver marcado, adicionar valor 0
            if (!document.getElementById('ativo').checked) {
                formData.set('ativo', '0');
            }
            
            // Validação especial para frete grátis
            const tipoDesconto = document.getElementById('tipo_desconto').value;
            if (tipoDesconto === 'frete_gratis') {
                // Para frete grátis, zerar o valor
                formData.set('valor', '0');
            }
            
            // Coletar configuração progressiva se aplicável
            if (tipoDesconto === 'progressivo') {
                const niveis = [];
                const niveisElements = document.querySelectorAll('.nivel-progressivo');
                
                niveisElements.forEach(nivel => {
                    const valor_minimo = nivel.querySelector('input[name="nivel_valor_minimo[]"]').value;
                    const desconto = nivel.querySelector('input[name="nivel_desconto[]"]').value;
                    
                    if (valor_minimo && desconto) {
                        niveis.push({
                            valor_minimo: parseFloat(valor_minimo),
                            desconto: parseFloat(desconto)
                        });
                    }
                });
                
                if (niveis.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Atenção!',
                        text: 'Configure pelo menos um nível de desconto progressivo.',
                        confirmButtonColor: '#ff00cc'
                    });
                    return;
                }
                
                formData.append('progressivo_config', JSON.stringify(niveis));
            }
            
            // Adicionar flag para indicar requisição AJAX
            formData.append('ajax', '1');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: data.message,
                        confirmButtonColor: '#ff00cc',
                        showConfirmButton: true,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: data.message,
                        confirmButtonColor: '#ff00cc'
                    });
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Erro ao processar a solicitação.',
                    confirmButtonColor: '#ff00cc'
                });
            });
        });

        // Inicializar switches e eventos
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar switches
            const ativoCheckbox = document.getElementById('ativo');
            const primeiraCompraCheckbox = document.getElementById('primeira_compra');
            
            [ativoCheckbox, primeiraCompraCheckbox].forEach(checkbox => {
                if (checkbox) {
                    const container = checkbox.closest('.switch-container');
                    if (checkbox.checked) {
                        container.classList.add('active');
                    }
                }
            });
            
            // Converter código para maiúsculo e verificar duplicatas
            const codigoInput = document.getElementById('codigo');
            if (codigoInput) {
                const codigosExistentes = [<?php echo '"' . implode('","', array_column($cupons, 'codigo')) . '"'; ?>];
                
                codigoInput.addEventListener('input', function() {
                    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                    
                    // Verificar se código já existe
                    const codigo = this.value;
                    if (codigo && codigosExistentes.includes(codigo)) {
                        this.setCustomValidity('Este código já está sendo usado. Escolha outro.');
                        this.style.borderColor = '#dc3545';
                    } else {
                        this.setCustomValidity('');
                        this.style.borderColor = '';
                    }
                });
            }
            
            // Validar tipo de desconto vs valor
            const tipoSelect = document.getElementById('tipo_desconto');
            const valorInput = document.getElementById('valor');
            
            if (tipoSelect && valorInput) {
                function validarValor() {
                    const tipo = tipoSelect.value;
                    const valor = parseFloat(valorInput.value);
                    
                    if (tipo === 'porcentagem' && valor > 100) {
                        valorInput.setCustomValidity('Desconto em porcentagem não pode ser maior que 100%');
                    } else if (valor <= 0 && ['porcentagem', 'valor_fixo'].includes(tipo)) {
                        valorInput.setCustomValidity('Valor deve ser maior que zero');
                    } else {
                        valorInput.setCustomValidity('');
                    }
                }
                
                tipoSelect.addEventListener('change', function() {
                    validarValor();
                    window.mostrarCamposEspeciais();
                });
                valorInput.addEventListener('input', validarValor);
            }
            
            // Validar campos progressivos
            const progInputs = document.querySelectorAll('#campos-progressivo input');
            progInputs.forEach(input => {
                input.addEventListener('input', function() {
                    const valor = parseFloat(this.value);
                    if (valor > 100) {
                        this.setCustomValidity('Porcentagem não pode ser maior que 100%');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            });
        });
        });
        
        // Fechar modal ao clicar fora dele
        window.onclick = function(event) {
            const modal = document.getElementById('modalEdicao');
            if (event.target === modal) {
                fecharModal();
            }
        };
    </script>
  </body>
</html>