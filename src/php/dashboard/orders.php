<?php
session_start();
// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../../../PHP/login.php');
    exit();
}

// Incluir contador de mensagens
require_once 'helper-contador.php';

// Incluir sistema de logs automático
require_once '../auto_log.php';

// Incluir conexão com banco
require_once '../../../PHP/conexao.php';

// Função para buscar status de fluxo
function buscarStatusFluxo($conexao) {
    $status = [];
    
    // Verificar se tabela existe
    $tableExists = mysqli_query($conexao, "SHOW TABLES LIKE 'status_fluxo'");
    if (mysqli_num_rows($tableExists) == 0) {
        // Retornar status padrão se tabela não existe
        return [
            ['id' => 1, 'nome' => 'Pedido Recebido', 'cor_hex' => '#ff00d4'],
            ['id' => 2, 'nome' => 'Pagamento Confirmado', 'cor_hex' => '#41f1b6'],
            ['id' => 3, 'nome' => 'Em Preparação', 'cor_hex' => '#ffbb55'],
            ['id' => 4, 'nome' => 'Enviado', 'cor_hex' => '#007bff'],
            ['id' => 5, 'nome' => 'Entregue', 'cor_hex' => '#28a745']
        ];
    }
    
    $query = "SELECT id, nome, cor_hex FROM status_fluxo ORDER BY ordem";
    $result = mysqli_query($conexao, $query);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $status[] = $row;
        }
    }
    
    // Se não há status cadastrados, retornar padrões
    if (empty($status)) {
        return [
            ['id' => 1, 'nome' => 'Pedido Recebido', 'cor_hex' => '#ff00d4'],
            ['id' => 2, 'nome' => 'Pagamento Confirmado', 'cor_hex' => '#41f1b6'],
            ['id' => 3, 'nome' => 'Em Preparação', 'cor_hex' => '#ffbb55'],
            ['id' => 4, 'nome' => 'Enviado', 'cor_hex' => '#007bff'],
            ['id' => 5, 'nome' => 'Entregue', 'cor_hex' => '#28a745']
        ];
    }
    
    return $status;
}

// Função para buscar pedidos
function buscarPedidos($conexao, $filtros = []) {
    $where = ["1=1"];
    $params = [];
    $types = '';
    
    // Verificar se tabelas existem
    $tabelasNecessarias = ['pedidos', 'clientes'];
    foreach ($tabelasNecessarias as $tabela) {
        $tableExists = mysqli_query($conexao, "SHOW TABLES LIKE '$tabela'");
        if (mysqli_num_rows($tableExists) == 0) {
            error_log("Tabela $tabela não encontrada");
            return []; // Retorna array vazio se tabelas não existem
        }
    }
    
    // Filtro de status
    if (!empty($filtros['status'])) {
        if ($filtros['status'] == 'reembolso') {
            $where[] = "(p.status LIKE '%reembolso%' OR p.status LIKE '%estornado%')";
        } else {
            $where[] = "p.status = ?";
            $params[] = $filtros['status'];
            $types .= 's';
        }
    }
    
    // Filtro de data
    if (!empty($filtros['data_inicio'])) {
        $where[] = "DATE(p.data_pedido) >= ?";
        $params[] = $filtros['data_inicio'];
        $types .= 's';
    }
    
    if (!empty($filtros['data_fim'])) {
        $where[] = "DATE(p.data_pedido) <= ?";
        $params[] = $filtros['data_fim'];
        $types .= 's';
    }
    
    // Filtro de busca
    if (!empty($filtros['busca'])) {
        $where[] = "(c.nome LIKE ? OR c.email LIKE ? OR p.id LIKE ?)";
        $busca = '%' . $filtros['busca'] . '%';
        $params[] = $busca;
        $params[] = $busca;
        $params[] = $busca;
        $types .= 'sss';
    }
    
    // Verificar quais colunas existem na tabela clientes
    $colunas_clientes = ['nome', 'email'];
    $colunas_opcionais = ['telefone', 'endereco', 'cidade', 'estado', 'cep'];
    
    // Query básica funcional - usando apenas colunas que existem
    // Primeiro tentar versão simplificada sem JOIN
    $sql_simples = "SELECT COUNT(*) as total FROM pedidos";
    $count_result = mysqli_query($conexao, $sql_simples);
    if ($count_result) {
        $count_row = mysqli_fetch_assoc($count_result);
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
            error_log("Total pedidos direto: " . $count_row['total']);
        }
    }
    
    $sql = "
        SELECT 
            p.id, 
            p.data_pedido, 
            p.valor_total, 
            p.status,
            COALESCE(c.nome, 'Cliente não encontrado') as cliente_nome,
            COALESCE(c.email, '') as cliente_email,
            '' as cliente_telefone,
            '' as cliente_endereco,
            '' as cliente_cidade,
            '' as cliente_estado,
            '' as cliente_cep,
            GROUP_CONCAT(
                CONCAT(pr.nome, ' (', ip.quantidade, 'x)')
                ORDER BY ip.id
                SEPARATOR ', '
            ) as produtos_resumo
        FROM pedidos p 
        LEFT JOIN clientes c ON p.cliente_id = c.id 
        LEFT JOIN itens_pedido ip ON p.id = ip.pedido_id
        LEFT JOIN produtos pr ON ip.produto_id = pr.id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY p.id
        ORDER BY p.data_pedido DESC
    ";
    
    try {
        $stmt = mysqli_prepare($conexao, $sql);
        if ($stmt && !empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        
        $result = $stmt ? mysqli_stmt_get_result($stmt) : mysqli_query($conexao, $sql);
        $pedidos = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $pedidos[] = $row;
            }
        } else {
            error_log("Erro na consulta SQL: " . mysqli_error($conexao));
            error_log("SQL executado: " . $sql);
        }
        
        // Debug para AJAX
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
            error_log("Debug buscarPedidos: SQL = " . $sql);
            error_log("Debug buscarPedidos: Erro = " . mysqli_error($conexao));
            error_log("Debug buscarPedidos: Resultados = " . count($pedidos));
        }
        
        return $pedidos;
    } catch (Exception $e) {
        error_log("Erro ao buscar pedidos: " . $e->getMessage());
        return [];
    }
}

// Processar ações AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'buscar_pedidos':
            // Consulta melhorada com produtos
            $sql_com_produtos = "
                SELECT 
                    p.id,
                    p.data_pedido, 
                    p.valor_total,
                    p.status,
                    COALESCE(c.nome, 'Cliente não encontrado') as cliente_nome,
                    '' as cliente_email,
                    GROUP_CONCAT(
                        CONCAT(COALESCE(pr.nome, 'Produto'), ' (', COALESCE(ip.quantidade, 1), 'x)')
                        ORDER BY ip.id
                        SEPARATOR ', '
                    ) as produtos_resumo
                FROM pedidos p 
                LEFT JOIN clientes c ON p.cliente_id = c.id 
                LEFT JOIN itens_pedido ip ON p.id = ip.pedido_id
                LEFT JOIN produtos pr ON ip.produto_id = pr.id
                GROUP BY p.id
                ORDER BY p.data_pedido DESC 
                LIMIT 20
            ";
            
            $result_com_produtos = mysqli_query($conexao, $sql_com_produtos);
            
            // Buscar cores dos status da gestão de fluxo com fallback
            $cores_status = [
                // Cores padrão baseadas na imagem
                'EM PREPARAÇÃO' => '#7dd87d',
                'Em Preparação' => '#7dd87d',
                'PAGAMENTO CONFIRMADO' => '#41f1b6', 
                'Pagamento Confirmado' => '#41f1b6',
                'ENTREGUE' => '#28a745',
                'Entregue' => '#28a745', 
                'PEDIDO RECEBIDO' => '#ff00cc',
                'Pedido Recebido' => '#ff00cc',
                'ESTORNADO' => '#fd7e14',
                'Estornado' => '#fd7e14'
            ];
            
            // Sobrescrever com dados da base (se existir)
            $status_result = mysqli_query($conexao, "SELECT nome, cor_hex FROM status_fluxo ORDER BY ordem, id");
            if ($status_result) {
                while ($status_row = mysqli_fetch_assoc($status_result)) {
                    $cores_status[$status_row['nome']] = $status_row['cor_hex'];
                }
            }
            
            // Cores específicas baseadas na imagem
            $cores_especificas = [
                'PAGAMENTO CONFIRMADO' => '#41f1b6',
                'Pagamento Confirmado' => '#41f1b6', 
                'EM PREPARAÇÃO' => '#7dd87d',
                'Em Preparação' => '#7dd87d',
                'ENTREGUE' => '#28a745', 
                'Entregue' => '#28a745',
                'PEDIDO RECEBIDO' => '#ff00cc',
                'Pedido Recebido' => '#ff00cc',
                'ESTORNADO' => '#fd7e14',
                'Estornado' => '#fd7e14'
            ];
            
            $pedidos_completos = [];
            if ($result_com_produtos) {
                while ($row = mysqli_fetch_assoc($result_com_produtos)) {
                    // Se não tem produtos, mostrar placeholder
                    if (empty($row['produtos_resumo'])) {
                        $row['produtos_resumo'] = 'Produtos não informados';
                    }
                    
                    // Cor do status
                    $status = $row['status'];
                    if (isset($cores_especificas[$status])) {
                        $cor_status = $cores_especificas[$status];
                    } else {
                        $cor_status = $cores_status[$status] ?? '#6c757d';
                    }
                    
                    $pedidos_completos[] = [
                        'id' => $row['id'],
                        'data_pedido' => $row['data_pedido'],
                        'valor_total' => $row['valor_total'],
                        'status' => $row['status'],
                        'cliente_nome' => $row['cliente_nome'],
                        'cliente_email' => $row['cliente_email'],
                        'produtos_resumo' => $row['produtos_resumo'],
                        'cor_status' => $cor_status
                    ];
                }
            }
            
            error_log("Query com produtos encontrou: " . count($pedidos_completos) . " pedidos");
            echo json_encode(['success' => true, 'pedidos' => $pedidos_completos]);
            exit;
            
        case 'atualizar_status':
            // Limpar qualquer output anterior
            if (ob_get_level()) {
                ob_clean();
            }
            
            // Definir header JSON
            header('Content-Type: application/json; charset=utf-8');
            
            $pedido_id = intval($_POST['pedido_id'] ?? 0);
            $novo_status = $_POST['novo_status'] ?? '';
            
            if ($pedido_id && $novo_status) {
                try {
                    // Atualizar status no banco (simples)
                    $update_sql = "UPDATE pedidos SET status = ? WHERE id = ?";
                    $update_stmt = mysqli_prepare($conexao, $update_sql);
                    
                    if (!$update_stmt) {
                        throw new Exception('Erro ao preparar query: ' . mysqli_error($conexao));
                    }
                    
                    mysqli_stmt_bind_param($update_stmt, 'si', $novo_status, $pedido_id);
                    
                    if (mysqli_stmt_execute($update_stmt)) {
                        // Buscar dados do cliente para envio de email
                        $cliente_query = "SELECT c.nome, c.email FROM clientes c JOIN pedidos p ON c.id = p.cliente_id WHERE p.id = ?";
                        $cliente_stmt = mysqli_prepare($conexao, $cliente_query);
                        if ($cliente_stmt) {
                            mysqli_stmt_bind_param($cliente_stmt, 'i', $pedido_id);
                            mysqli_stmt_execute($cliente_stmt);
                            $cliente_result = mysqli_stmt_get_result($cliente_stmt);
                            $cliente = mysqli_fetch_assoc($cliente_result);
                            
                            // Debug: verificar dados do cliente
                            error_log("🔍 DEBUG Cliente encontrado: " . ($cliente ? json_encode($cliente) : 'null'));
                            error_log("🔍 DEBUG Email do cliente: " . ($cliente['email'] ?? 'vazio'));
                            
                            // Enviar email automático se cliente tem email
                            if ($cliente && !empty($cliente['email'])) {
                                // Buscar mensagem personalizada da gestão de fluxo
                                $mensagem_query = "SELECT mensagem_email FROM status_fluxo WHERE nome = ?";
                                $mensagem_stmt = mysqli_prepare($conexao, $mensagem_query);
                                if ($mensagem_stmt) {
                                    mysqli_stmt_bind_param($mensagem_stmt, 's', $novo_status);
                                    mysqli_stmt_execute($mensagem_stmt);
                                    $mensagem_result = mysqli_stmt_get_result($mensagem_stmt);
                                    $mensagem_row = mysqli_fetch_assoc($mensagem_result);
                                    
                                    // Usar mensagem personalizada ou padrão
                                    if (!empty($mensagem_row['mensagem_email'])) {
                                        $mensagem = $mensagem_row['mensagem_email'];
                                        
                                        // Substituir variáveis na mensagem
                                        $mensagem = str_replace('{nome_cliente}', $cliente['nome'], $mensagem);
                                        $mensagem = str_replace('{numero_pedido}', $pedido_id, $mensagem);
                                        
                                        // Buscar valor total e data do pedido
                                        $detalhes_query = "SELECT valor_total, data_pedido FROM pedidos WHERE id = ?";
                                        $detalhes_stmt = mysqli_prepare($conexao, $detalhes_query);
                                        if ($detalhes_stmt) {
                                            mysqli_stmt_bind_param($detalhes_stmt, 'i', $pedido_id);
                                            mysqli_stmt_execute($detalhes_stmt);
                                            $detalhes_result = mysqli_stmt_get_result($detalhes_stmt);
                                            $detalhes_row = mysqli_fetch_assoc($detalhes_result);
                                            
                                            if ($detalhes_row) {
                                                $valor_total = $detalhes_row['valor_total'] ?? '0.00';
                                                $data_pedido = date('d/m/Y', strtotime($detalhes_row['data_pedido']));
                                                
                                                $mensagem = str_replace('{valor_total}', 'R$ ' . number_format($valor_total, 2, ',', '.'), $mensagem);
                                                $mensagem = str_replace('{data_pedido}', $data_pedido, $mensagem);
                                                $mensagem = str_replace('{status_atual}', $novo_status, $mensagem);
                                            }
                                            mysqli_stmt_close($detalhes_stmt);
                                        }
                                    } else {
                                        // Mensagem padrão caso não tenha personalizada
                                        $mensagem = "Olá {$cliente['nome']},\n\n";
                                        $mensagem .= "Seu pedido #$pedido_id teve o status atualizado para: $novo_status\n\n";
                                        $mensagem .= "Você pode acompanhar seu pedido através do nosso sistema.\n\n";
                                        $mensagem .= "Atenciosamente,\nEquipe D&Z";
                                    }
                                } else {
                                    // Mensagem padrão em caso de erro
                                    $mensagem = "Olá {$cliente['nome']},\n\n";
                                    $mensagem .= "Seu pedido #$pedido_id teve o status atualizado para: $novo_status\n\n";
                                    $mensagem .= "Atenciosamente,\nEquipe D&Z";
                                }
                                
                                $assunto = "📦 Atualização do Pedido #$pedido_id - D&Z";
                                
                                error_log("📧 DEBUG Tentando enviar email para: " . $cliente['email']);
                                $email_enviado = enviarEmailAutomatico($cliente['email'], $cliente['nome'], $assunto, $mensagem);
                                error_log("📧 DEBUG Email enviado: " . ($email_enviado ? 'SIM' : 'NÃO'));
                                
                            } else {
                                error_log("❌ DEBUG Cliente sem email ou não encontrado");
                                if ($cliente) {
                                    error_log("🔍 DEBUG Cliente existe mas email vazio: " . json_encode($cliente));
                                } else {
                                    error_log("🔍 DEBUG Cliente não encontrado para pedido: " . $pedido_id);
                                }
                            }
                        }
                        
                        echo json_encode(['success' => true, 'message' => 'Status atualizado e email enviado!'], JSON_UNESCAPED_UNICODE);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar status: ' . mysqli_stmt_error($update_stmt)], JSON_UNESCAPED_UNICODE);
                    }
                    
                } catch (Exception $e) {
                    error_log("Erro ao atualizar status: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Dados inválidos'], JSON_UNESCAPED_UNICODE);
            }
            exit;
            
        case 'buscar_detalhes_pedido':
            // Limpar qualquer output anterior
            if (ob_get_level()) {
                ob_clean();
            }
            
            // Definir header JSON
            header('Content-Type: application/json; charset=utf-8');
            
            $pedido_id = intval($_POST['pedido_id'] ?? 0);
            
            if ($pedido_id) {
                try {
                    // Buscar dados do pedido e cliente
                    $sql = "
                        SELECT 
                            p.*,
                            c.nome as cliente_nome,
                            c.email as cliente_email
                        FROM pedidos p 
                        LEFT JOIN clientes c ON p.cliente_id = c.id 
                        WHERE p.id = ?
                    ";
                    
                    $stmt = mysqli_prepare($conexao, $sql);
                    if (!$stmt) {
                        throw new Exception('Erro ao preparar query: ' . mysqli_error($conexao));
                    }
                    
                    mysqli_stmt_bind_param($stmt, 'i', $pedido_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if ($pedido = mysqli_fetch_assoc($result)) {
                        // Buscar itens do pedido
                        $sql_itens = "
                            SELECT 
                                ip.*,
                                COALESCE(pr.nome, 'Produto não encontrado') as produto_nome
                            FROM itens_pedido ip
                            LEFT JOIN produtos pr ON ip.produto_id = pr.id
                            WHERE ip.pedido_id = ?
                        ";
                        
                        $stmt_itens = mysqli_prepare($conexao, $sql_itens);
                        if ($stmt_itens) {
                            mysqli_stmt_bind_param($stmt_itens, 'i', $pedido_id);
                            mysqli_stmt_execute($stmt_itens);
                            $result_itens = mysqli_stmt_get_result($stmt_itens);
                            
                            $itens = [];
                            while ($item = mysqli_fetch_assoc($result_itens)) {
                                $itens[] = $item;
                            }
                            $pedido['itens'] = $itens;
                        } else {
                            $pedido['itens'] = [];
                        }
                        
                        echo json_encode(['success' => true, 'pedido' => $pedido], JSON_UNESCAPED_UNICODE);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Pedido não encontrado'], JSON_UNESCAPED_UNICODE);
                    }
                    
                } catch (Exception $e) {
                    error_log("Erro ao buscar detalhes do pedido: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'ID do pedido inválido'], JSON_UNESCAPED_UNICODE);
            }
            exit;
            
        case 'processar_reembolso':
            $pedido_id = intval($_POST['pedido_id'] ?? 0);
            
            if ($pedido_id) {
                $sql = "UPDATE pedidos SET status = 'Estornado' WHERE id = ?";
                $stmt = mysqli_prepare($conexao, $sql);
                mysqli_stmt_bind_param($stmt, 'i', $pedido_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    registrar_log($conexao, "Reembolso processado para pedido #$pedido_id");
                    echo json_encode(['success' => true, 'message' => 'Reembolso processado com sucesso!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erro ao processar reembolso']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'ID do pedido inválido']);
            }
            exit;
    }
}

// Endpoint para listar status disponíveis
if (isset($_GET['action']) && $_GET['action'] === 'listar_status') {
    try {
        $query = "SELECT nome, cor_hex as cor FROM status_fluxo ORDER BY ordem, id";
        $result = mysqli_query($conexao, $query);
        $status = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $status[] = $row;
            }
        }
        
        echo json_encode([
            'success' => true, 
            'status' => $status
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Função para enviar email automático
function enviarEmailAutomatico($email, $nome, $assunto, $mensagem) {
    try {
        // Carregar configurações de email da automação
        $email_config = '../../../config/email-config.php';
        if (file_exists($email_config)) {
            require_once $email_config;
        }
        
        // Verificar se emails estão habilitados na automação
        if (!defined('EMAIL_ENABLED') || !EMAIL_ENABLED) {
            error_log("📧 Emails desabilitados na automação - simulando envio para: $email");
            return true;
        }
        
        // Verificar se há senha SMTP configurada
        if (!defined('SMTP_PASSWORD') || empty(SMTP_PASSWORD)) {
            error_log("📧 Senha SMTP não configurada na automação - simulando envio para: $email");
            return true;
        }
        
        // Verificar se PHPMailer existe
        if (file_exists('../../../phpmailer/src/PHPMailer.php')) {
            require_once '../../../phpmailer/src/PHPMailer.php';
            require_once '../../../phpmailer/src/SMTP.php';
            require_once '../../../phpmailer/src/Exception.php';
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Configurações do servidor da automação
            $mail->isSMTP();
            $mail->Host = SMTP_HOST; // smtp.gmail.com
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME; // dznaileofficial@gmail.com
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_SECURE; // ssl para porta 465
            $mail->Port = SMTP_PORT; // 465
            $mail->CharSet = 'UTF-8';
            
            // Remetente da automação D&Z
            $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME); // dznaileofficial@gmail.com, D&Z Nails
            $mail->addAddress($email, $nome);
            
            // Conteúdo personalizado D&Z
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            
            // Template HTML para emails D&Z
            $html_body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: linear-gradient(135deg, #ff00cc, #e91e63); padding: 20px; text-align: center;'>
                    <h1 style='color: white; margin: 0;'>D&Z Nails</h1>
                </div>
                <div style='padding: 30px; background: #f9f9f9;'>
                    <h2 style='color: #333;'>Olá, {$nome}!</h2>
                    <div style='background: white; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                        " . nl2br(htmlspecialchars($mensagem)) . "
                    </div>
                    <p style='color: #666; font-size: 14px;'>
                        Este é um email automático. Para mais informações, entre em contato conosco.
                    </p>
                </div>
                <div style='background: #333; color: white; padding: 15px; text-align: center; font-size: 12px;'>
                    © 2026 D&Z Nails - Todos os direitos reservados
                </div>
            </div>
            ";
            
            $mail->Body = $html_body;
            $mail->AltBody = strip_tags($mensagem); // Versão texto
            
            $mail->send();
            error_log("✅ Email D&Z enviado com sucesso para: $email via " . SMTP_USERNAME);
            return true;
            
        } else {
            // Simulação caso PHPMailer não esteja disponível
            $log_message = "📧 EMAIL D&Z AUTOMÁTICO (PHPMailer não encontrado):\n";
            $log_message .= "De: " . (defined('EMAIL_FROM') ? EMAIL_FROM : 'dznaileofficial@gmail.com') . "\n";
            $log_message .= "Para: $email ($nome)\n";
            $log_message .= "Assunto: $assunto\n";
            $log_message .= "Mensagem: $mensagem\n";
            $log_message .= "Data: " . date('Y-m-d H:i:s') . "\n";
            $log_message .= "Configuração: Automação D&Z\n";
            $log_message .= "---\n";
            
            error_log($log_message);
            return true;
        }
        
    } catch (Exception $e) {
        error_log("❌ Erro ao enviar email D&Z: " . $e->getMessage());
        return false;
    }
}

// Buscar dados iniciais com tratamento de erro
$statusFluxo = [];
$pedidos = [];
$debug_orders = [];

try {
    if ($conexao) {
        $debug_orders[] = "Conexão OK";
        
        $statusFluxo = buscarStatusFluxo($conexao);
        $debug_orders[] = "Status de fluxo: " . count($statusFluxo) . " encontrados";
        
        $pedidos = buscarPedidos($conexao);
        $debug_orders[] = "Pedidos: " . count($pedidos) . " encontrados";
        
        // Debug: verificar se há pedidos no banco
        $count_result = mysqli_query($conexao, "SELECT COUNT(*) as total FROM pedidos");
        if ($count_result) {
            $count_row = mysqli_fetch_assoc($count_result);
            $debug_orders[] = "Total pedidos no banco: " . $count_row['total'];
        }
        
    } else {
        $debug_orders[] = "Sem conexão";
    }
} catch (Exception $e) {
    error_log("Erro ao inicializar dados da página: " . $e->getMessage());
    $debug_orders[] = "Erro: " . $e->getMessage();
    // Dados ficam vazios, página mostrará mensagem apropriada
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../css/dashboard.css">

     <link
      href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp"
      rel="stylesheet"
    />

    <title>Pedidos - D&Z Admin</title>
    <style>
        /* Estilos específicos para Orders */
        .filters-section {
            background: var(--color-white);
            border-radius: var(--card-border-radius);
            padding: var(--card-padding);
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
        }
        
        .filters-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .filter-group label {
            font-size: 0.8rem;
            color: var(--color-dark-variant);
            font-weight: 500;
        }
        
        .filter-group input, .filter-group select {
            padding: 0.8rem;
            border: 1px solid var(--color-light);
            border-radius: var(--border-radius-1);
            background: var(--color-white);
            color: var(--color-dark);
        }
        
        .tabs-container {
            margin: 1rem 0;
        }
        
        .tabs {
            display: flex;
            gap: 0.5rem;
            border-bottom: 2px solid var(--color-light);
            margin-bottom: 1rem;
        }
        
        .tab {
            padding: 1rem 1.5rem;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: 500;
            color: var(--color-dark-variant);
            transition: all 0.3s ease;
        }
        
        .tab:hover {
            color: var(--color-primary);
            background: var(--color-light);
        }
        
        .tab.active {
            color: var(--color-primary);
            border-bottom-color: var(--color-primary);
            background: var(--color-light);
        }
        
        .orders-table {
            background: var(--color-white);
            border-radius: var(--card-border-radius);
            padding: var(--card-padding);
            box-shadow: var(--box-shadow);
            overflow-x: auto;
        }
        
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }
        
        .orders-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .orders-table th,
        .orders-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--color-light);
        }
        
        .orders-table th {
            font-weight: 600;
            color: var(--color-dark);
            background: var(--color-background);
        }
        
        .orders-table td {
            color: var(--color-dark-variant);
        }
        
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            color: white;
            text-align: center;
            display: inline-block;
            min-width: 80px;
        }
        
        .btn-details {
            background: #ff00cc !important;
            color: white !important;
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: var(--border-radius-1);
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-details:hover {
            background: #e600b8 !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 0, 204, 0.3);
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: var(--color-white);
            margin: 2% auto;
            padding: 2rem;
            border-radius: var(--card-border-radius);
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            right: 1rem;
            top: 1rem;
            cursor: pointer;
        }
        
        .close:hover {
            color: var(--color-primary);
        }
        
        .order-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .detail-card {
            background: var(--color-background);
            padding: 1.5rem;
            border-radius: var(--border-radius-2);
            border-left: 4px solid var(--color-primary);
        }
        
        .detail-card h4 {
            color: var(--color-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .detail-item {
            margin-bottom: 0.8rem;
        }
        
        .detail-item label {
            font-weight: 600;
            color: var(--color-dark);
            display: block;
            margin-bottom: 0.2rem;
        }
        
        .detail-item span {
            color: var(--color-dark-variant);
        }
        
        .items-list {
            background: var(--color-white);
            border-radius: var(--border-radius-1);
            overflow: hidden;
        }
        
        .items-list table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .items-list th,
        .items-list td {
            padding: 0.8rem;
            border-bottom: 1px solid var(--color-light);
            text-align: left;
        }
        
        .items-list th {
            background: var(--color-light);
            font-weight: 600;
        }
        
        .btn-process-refund {
            background: var(--color-danger);
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: var(--border-radius-1);
            cursor: pointer;
            font-weight: 500;
            margin-top: 1rem;
        }
        
        .btn-process-refund:hover {
            background: var(--color-primary-variant);
            transform: translateY(-2px);
        }
        
        .loading {
            text-align: center;
            padding: 2rem;
            color: var(--color-dark-variant);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--color-dark-variant);
        }
        
        .empty-state .material-symbols-sharp {
            font-size: 4rem;
            color: var(--color-light);
            margin-bottom: 1rem;
        }
        
        /* Notificações */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            background: var(--color-white);
            border-left: 4px solid #ff00cc;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            z-index: 10000;
            animation: slideIn 0.3s ease;
        }
        
        .notification.success {
            border-left-color: #28a745;
            background: #d4edda;
            color: #155724;
        }
        
        .notification.error {
            border-left-color: #dc3545;
            background: #f8d7da;
            color: #721c24;
        }
        
        .notification span.material-symbols-sharp {
            font-size: 1.2rem;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        /* Select personalizado no modal */
        .form-select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--color-info-light);
            border-radius: 4px;
            background: var(--color-white);
            color: var(--color-dark);
            font-size: 0.9rem;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #ff00cc;
            box-shadow: 0 0 0 2px rgba(255,0,204,0.2);
        }
        
        
        /* =================================
           DESIGN MODERNO - ORDERS 2.0
           ================================= */
        
        /* Filtros Modernos */
        .filters-container {
            margin-bottom: 2.5rem;
        }
        
        .filters-card {
            background: var(--color-white);
            border-radius: 16px;
            padding: 1.5rem 3rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,0,204,0.1);
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: 200px 200px 1fr;
            gap: 2rem;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .filter-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: var(--color-dark);
            font-size: 0.9rem;
        }
        
        .filter-group label .material-symbols-sharp {
            font-size: 1.1rem;
            color: #ff00cc;
        }
        
        .modern-input {
            padding: 0.6rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--color-white);
            color: var(--color-dark);
            height: 42px;
            box-sizing: border-box;
        }
        
        .modern-input:focus {
            outline: none;
            border-color: #ff00cc;
            box-shadow: 0 0 0 3px rgba(255,0,204,0.1);
            transform: translateY(-1px);
        }
        
        .search-container {
            display: flex;
            gap: 0.5rem;
        }
        
        .search-input {
            flex: 1;
        }
        
        .btn-search {
            background: linear-gradient(135deg, #ff00cc, #ff3d71);
            color: white;
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(255,0,204,0.3);
            height: 42px;
            box-sizing: border-box;
        }
        
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255,0,204,0.4);
        }
        
        /* Tabs Modernos */
        .tabs-wrapper {
            background: var(--color-white);
            border-radius: 16px;
            padding: 0.5rem;
            display: flex;
            gap: 0.5rem;
            position: relative;
            overflow-x: auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,0,204,0.1);
        }
        
        .tab {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            background: transparent;
            border: none;
            font-weight: 500;
            white-space: nowrap;
            min-width: fit-content;
            height: 60px;
            box-sizing: border-box;
        }
        
        .tab:hover {
            background: rgba(255,0,204,0.05);
            transform: translateY(-1px);
        }
        
        .tab.active {
            background: linear-gradient(135deg, #ff00cc, #ff3d71);
            color: white;
            box-shadow: 0 4px 12px rgba(255,0,204,0.3);
        }
        
        .tab-icon .material-symbols-sharp {
            font-size: 1.2rem;
        }
        
        .tab-text {
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .tab-count {
            background: rgba(255,255,255,0.2);
            color: var(--color-dark);
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            min-width: 20px;
            text-align: center;
        }
        
        .tab.active .tab-count {
            background: rgba(255,255,255,0.3);
            color: white;
        }
        
        /* Tabela Moderna */
        .table-container {
            background: var(--color-white);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,0,204,0.1);
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        
        .orders-table thead {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }
        
        .orders-table thead th {
            padding: 1.5rem 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--color-dark);
            border: none;
            position: relative;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .orders-table tbody tr {
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .orders-table tbody tr:hover {
            background: rgba(255,0,204,0.02);
            transform: scale(1.001);
        }
        
        .orders-table tbody td {
            padding: 1.5rem 1rem;
            border: none;
            vertical-align: middle;
            font-size: 0.9rem;
        }
        
        .order-id {
            font-weight: 700;
            color: #ff00cc;
            font-size: 1rem;
        }
        
        .order-date {
            color: var(--color-info-dark);
            font-size: 0.85rem;
        }
        
        .order-time {
            color: var(--color-dark-variant);
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .order-summary {
            color: var(--color-dark);
            font-size: 0.85rem;
            font-style: italic;
            max-width: 200px;
            display: block;
        }
        
        .client-name {
            font-weight: 600;
            color: var(--color-dark);
        }
        
        .order-value {
            font-weight: 700;
            color: var(--color-success);
            font-size: 1rem;
        }
        
        /* Status Badge Melhorado */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: none;
            color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .status-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        /* Botão Ver Detalhes Melhorado */
        .btn-details {
            background: linear-gradient(135deg, #ff00cc, #ff3d71);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(255,0,204,0.2);
        }
        
        .btn-details:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255,0,204,0.3);
        }
        
        .btn-details .material-symbols-sharp {
            font-size: 1.1rem;
        }
        
        /* Loading State */
        .loading {
            text-align: center;
            padding: 3rem;
            color: var(--color-info-dark);
            font-style: italic;
        }
        
        .loading .material-symbols-sharp {
            font-size: 2rem;
            animation: spin 1s linear infinite;
            color: #ff00cc;
            margin-right: 0.5rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Modal Melhorado */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background-color: var(--color-white);
            margin: 2% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 1200px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease;
        }
        
        .modal-large {
            width: 95%;
            max-width: 1400px;
        }
        
        @keyframes modalSlideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-header {
            background: linear-gradient(135deg, #ff00cc, #ff3d71);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
        }
        
        .close {
            color: white;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            background: rgba(255,255,255,0.2);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .close:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.1);
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .pedido-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .detail-card {
            background: var(--color-white);
            border: 1px solid rgba(255,0,204,0.1);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .detail-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .detail-card.full-width {
            grid-column: 1 / -1;
        }
        
        .detail-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .detail-header span {
            color: #ff00cc;
            font-size: 1.2rem;
        }
        
        .detail-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--color-dark);
        }
        
        .detail-content {
            padding: 1.5rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-row strong {
            color: var(--color-dark);
            font-weight: 600;
            min-width: 140px;
        }
        
        .info-row span {
            color: var(--color-dark-variant);
            text-align: right;
            flex: 1;
        }
        
        .status-select {
            background: var(--color-white);
            border: 2px solid #ff00cc;
            border-radius: 8px;
            padding: 0.5rem;
            color: var(--color-dark);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 200px;
        }
        
        .status-select:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(255,0,204,0.2);
        }
        
        .payment-method {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            text-align: center;
            min-width: 100px;
        }
        
        .payment-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: flex-end;
        }
        
        .payment-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            color: white;
            text-align: center;
            min-width: 120px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            box-shadow: 0 2px 8px rgba(0,123,255,0.3);
        }
        
        .payment-badge.credit {
            background: linear-gradient(135deg, #28a745, #20c997);
            box-shadow: 0 2px 8px rgba(40,167,69,0.3);
        }
        
        .payment-badge.debit {
            background: linear-gradient(135deg, #17a2b8, #138496);
            box-shadow: 0 2px 8px rgba(23,162,184,0.3);
        }
        
        .payment-badge.pix {
            background: linear-gradient(135deg, #6f42c1, #5a2d91);
            box-shadow: 0 2px 8px rgba(111,66,193,0.3);
        }
        
        .payment-badge.boleto {
            background: linear-gradient(135deg, #fd7e14, #e55a00);
            box-shadow: 0 2px 8px rgba(253,126,20,0.3);
        }
        
        .payment-text {
            font-weight: 500;
            color: #333;
            font-size: 1rem;
        }
        
        .status-badge-compact {
            transition: all 0.2s ease;
        }
        
        .status-badge-compact:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.25) !important;
        }
        
        .status-badge-enhanced {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: default;
        }
        
        .status-badge-enhanced::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s ease;
        }
        
        .status-badge-enhanced:hover::before {
            left: 100%;
        }
        
        .status-badge-enhanced:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3) !important;
        }
        
        .parcelas-info {
            background: rgba(255,0,204,0.1);
            color: #ff00cc;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            border: 1px solid rgba(255,0,204,0.2);
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .items-table th,
        .items-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .items-table th {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            font-weight: 600;
            color: var(--color-dark);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .items-table td {
            color: var(--color-dark-variant);
        }
        
        .price {
            font-weight: 600;
            color: #28a745;
        }
        
        .discount {
            color: #dc3545;
        }
        
        .order-totals {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 1rem;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
        }
        
        .total-row.final-total {
            border-top: 2px solid #ff00cc;
            padding-top: 1rem;
            margin-top: 1rem;
            font-size: 1.1rem;
        }
        
        .status-timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline-item {
            position: relative;
            padding: 1rem 0;
            border-left: 3px solid #ff00cc;
            padding-left: 2rem;
        }
        
        .timeline-item:before {
            content: '';
            position: absolute;
            left: -8px;
            top: 1.5rem;
            width: 14px;
            height: 14px;
            background: #ff00cc;
            border-radius: 50%;
        }
        
        .timeline-date {
            font-size: 0.85rem;
            color: var(--color-dark-variant);
            margin-bottom: 0.5rem;
        }
        
        .timeline-status {
            font-weight: 600;
            color: var(--color-dark);
        }
        
        .timeline-user {
            font-size: 0.85rem;
            color: var(--color-dark-variant);
            font-style: italic;
        }
        
        .modal-footer {
            background: #f8f9fa;
            padding: 1.5rem 2rem;
            border-top: 1px solid rgba(0,0,0,0.1);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            border-radius: 0 0 20px 20px;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        @media (max-width: 768px) {
            .filters-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .tabs {
                flex-wrap: wrap;
            }
            
            .orders-table {
                font-size: 0.8rem;
            }
            
            .orders-table th,
            .orders-table td {
                padding: 0.5rem;
            }
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
          <a href="index.php" class="panel">
            <span class="material-symbols-sharp">grid_view</span>
            <h3>Painel</h3>
          </a>

          <a href="customers.php">
            <span class="material-symbols-sharp">group</span>
            <h3>Clientes</h3>
          </a>

          <a href="orders.php" class="active">
            <span class="material-symbols-sharp">Orders</span>
            <h3>Pedidos</h3>
          </a>



          <a href="analytics.php">
            <span class="material-symbols-sharp">Insights</span>
            <h3>Gráficos</h3>
          </a>

          <a href="menssage.php">
            <span class="material-symbols-sharp">Mail</span>
            <h3>Mensagens</h3>
            <span class="message-count"><?= $nao_lidas; ?></span>
          </a>

          <a href="products.php">
            <span class="material-symbols-sharp">Inventory</span>
            <h3>Produtos</h3>
          </a>

          <a href="cupons.php">
            <span class="material-symbols-sharp">sell</span>
            <h3>Cupons</h3>
          </a>

          <a href="gestao-fluxo.php">
            <span class="material-symbols-sharp">account_tree</span>
            <h3>Gestão de Fluxo</h3>
          </a>

          <div class="menu-item-container">
            <a href="geral.php" class="menu-item-with-submenu">
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
        <h1 style="margin-bottom: 2rem;">Gestão de Pedidos</h1>
        
        <?php if (!$conexao): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #dc3545;">
            <h4>❌ Erro de Conexão</h4>
            <p>Não foi possível conectar ao banco de dados. Verifique as configurações de conexão.</p>
        </div>
        <?php elseif (empty($pedidos) && empty($statusFluxo)): ?>
        <div style="background: #fff3cd; color: #856404; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #ffc107;">
            <h4>⚠️ Configuração Necessária</h4>
            <p>As tabelas do banco de dados precisam ser criadas. Execute o script de configuração:</p>
            <p><a href="fix-database.php" style="background: #ff00cc; color: white; padding: 0.5rem 1rem; border-radius: 4px; text-decoration: none; font-weight: bold;">🔧 Configurar Banco de Dados</a></p>
        </div>
        <?php endif; ?>
        
        <!-- Seção de Filtros Modernos -->
        <div class="filters-container">
            <div class="filters-card">
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="data_inicio">
                            <span class="material-symbols-sharp">event</span>
                            Data Início
                        </label>
                        <input type="date" id="data_inicio" class="modern-input" />
                    </div>
                    <div class="filter-group">
                        <label for="data_fim">
                            <span class="material-symbols-sharp">event</span>
                            Data Fim
                        </label>
                        <input type="date" id="data_fim" class="modern-input" />
                    </div>
                    <div class="filter-group search-group">
                        <label for="busca">
                            <span class="material-symbols-sharp">search</span>
                            Pesquisar
                        </label>
                        <div class="search-container">
                            <input type="text" id="busca" class="modern-input search-input" placeholder="Nome, CPF ou Nº do Pedido..." />
                            <button class="btn-search" onclick="filtrarPedidos()">
                                <span class="material-symbols-sharp">search</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Modernos -->
        <div class="tabs-container">
            <div class="tabs-wrapper">
                <div class="tab active" onclick="trocarAba('todos')">
                    <div class="tab-icon">
                        <span class="material-symbols-sharp">list_alt</span>
                    </div>
                    <span class="tab-text">Todos</span>
                    <div class="tab-count" id="count-todos">0</div>
                </div>
                <div class="tab" onclick="trocarAba('pendente')">
                    <div class="tab-icon">
                        <span class="material-symbols-sharp">schedule</span>
                    </div>
                    <span class="tab-text">Aguardando</span>
                    <div class="tab-count" id="count-pendente">0</div>
                </div>
                <div class="tab" onclick="trocarAba('Pagamento Confirmado')">
                    <div class="tab-icon">
                        <span class="material-symbols-sharp">check_circle</span>
                    </div>
                    <span class="tab-text">Confirmados</span>
                    <div class="tab-count" id="count-confirmado">0</div>
                </div>
                <div class="tab" onclick="trocarAba('Enviado')">
                    <div class="tab-icon">
                        <span class="material-symbols-sharp">local_shipping</span>
                    </div>
                    <span class="tab-text">Enviados</span>
                    <div class="tab-count" id="count-enviado">0</div>
                </div>
                <div class="tab" onclick="trocarAba('reembolso')">
                    <div class="tab-icon">
                        <span class="material-symbols-sharp">currency_exchange</span>
                    </div>
                    <span class="tab-text">Reembolsos</span>
                    <div class="tab-count" id="count-reembolso">0</div>
                </div>
            </div>
        </div>
        
        <!-- Tabela de Pedidos Moderna -->
        <div class="table-container">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data/Hora</th>
                        <th>Cliente</th>
                        <th>Resumo do Pedido</th>
                        <th>Valor Total</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="pedidos-tbody">
                    <tr>
                        <td colspan="7" class="loading">
                            <span class="material-symbols-sharp">refresh</span>
                            Carregando pedidos...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
      </main>

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
              <img src="../../../assets/images/logo.png" alt="" />
            </div>
          </div>
        </div>
        <!------------------------FINAL TOP----------------------->
      </div>
    </div>
    
    <!-- Modal de Detalhes do Pedido Melhorado -->
    <div id="orderModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2 id="modal-title">Detalhes do Pedido #</h2>
                <span class="close" onclick="fecharModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="pedido-details-grid">
                    <!-- Informações do Cliente -->
                    <div class="detail-card">
                        <div class="detail-header">
                            <span class="material-symbols-sharp">person</span>
                            <h3>Dados do Cliente</h3>
                        </div>
                        <div class="detail-content">
                            <div class="info-row">
                                <strong>Nome Completo:</strong>
                                <span id="cliente-nome">-</span>
                            </div>
                            <div class="info-row">
                                <strong>E-mail:</strong>
                                <span id="cliente-email">-</span>
                            </div>
                            <div class="info-row">
                                <strong>Telefone (WhatsApp):</strong>
                                <span id="cliente-telefone">-</span>
                            </div>
                            <div class="info-row">
                                <strong>CPF/CNPJ:</strong>
                                <span id="cliente-documento">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Endereço de Entrega -->
                    <div class="detail-card">
                        <div class="detail-header">
                            <span class="material-symbols-sharp">location_on</span>
                            <h3>Endereço de Entrega</h3>
                        </div>
                        <div class="detail-content">
                            <div class="info-row">
                                <strong>Endereço:</strong>
                                <span id="endereco-completo">-</span>
                            </div>
                            <div class="info-row">
                                <strong>Cidade/Estado:</strong>
                                <span id="cidade-estado">-</span>
                            </div>
                            <div class="info-row">
                                <strong>CEP:</strong>
                                <span id="endereco-cep">-</span>
                            </div>
                            <div class="info-row">
                                <strong>Complemento:</strong>
                                <span id="endereco-complemento">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Informações do Pedido -->
                    <div class="detail-card">
                        <div class="detail-header">
                            <span class="material-symbols-sharp">receipt_long</span>
                            <h3>Informações do Pedido</h3>
                        </div>
                        <div class="detail-content">
                            <div class="info-row">
                                <strong>Data do Pedido:</strong>
                                <span id="pedido-data">-</span>
                            </div>
                            <div class="info-row">
                                <strong>Hora do Pedido:</strong>
                                <span id="pedido-hora">-</span>
                            </div>
                            <div class="info-row">
                                <strong>Forma de Pagamento:</strong>
                                <div class="payment-info">
                                    <span id="forma-pagamento" class="payment-text">-</span>
                                    <span id="parcelas-info" class="parcelas-info">-</span>
                                </div>
                            </div>
                            <div class="info-row">
                                <strong>Status Atual:</strong>
                                <div id="status-atual">-</div>
                            </div>
                            <div class="info-row">
                                <strong>Alterar Status:</strong>
                                <select id="novo-status" class="status-select" onchange="alterarStatusAutomatico()">
                                    <option value="">Selecione novo status...</option>
                                </select>
                            </div>
                            <div class="info-row">
                                <strong>Observações:</strong>
                                <span id="pedido-observacoes">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Informações de Entrega -->
                    <div class="detail-card">
                        <div class="detail-header">
                            <span class="material-symbols-sharp">local_shipping</span>
                            <h3>Informações de Entrega</h3>
                        </div>
                        <div class="detail-content">
                            <div class="info-row">
                                <strong>Transportadora:</strong>
                                <span id="transportadora">-</span>
                            </div>
                            <div class="info-row">
                                <strong>Código de Rastreio:</strong>
                                <span id="codigo-rastreio">-</span>
                            </div>
                            <div class="info-row">
                                <strong>Previsão de Entrega:</strong>
                                <span id="previsao-entrega">-</span>
                            </div>
                            <div class="info-row">
                                <strong>Frete:</strong>
                                <span id="valor-frete" class="price">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Itens do Pedido -->
                <div class="detail-card full-width">
                    <div class="detail-header">
                        <span class="material-symbols-sharp">shopping_cart</span>
                        <h3>Itens do Pedido</h3>
                    </div>
                    <div class="detail-content">
                        <div class="table-responsive">
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th>SKU</th>
                                        <th>Quantidade</th>
                                        <th>Preço Unit.</th>
                                        <th>Desconto</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody id="pedido-itens">
                                    <!-- Itens serão carregados aqui -->
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Totais -->
                        <div class="order-totals">
                            <div class="total-row">
                                <strong>Subtotal:</strong>
                                <span id="subtotal-pedido" class="price">-</span>
                            </div>
                            <div class="total-row">
                                <strong>Desconto:</strong>
                                <span id="desconto-pedido" class="price discount">-</span>
                            </div>
                            <div class="total-row">
                                <strong>Frete:</strong>
                                <span id="frete-pedido" class="price">-</span>
                            </div>
                            <div class="total-row final-total">
                                <strong>Total Final:</strong>
                                <span id="total-final" class="price">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Histórico de Status -->
                <div class="detail-card full-width">
                    <div class="detail-header">
                        <span class="material-symbols-sharp">history</span>
                        <h3>Histórico de Status</h3>
                    </div>
                    <div class="detail-content">
                        <div id="historico-status" class="status-timeline">
                            <!-- Histórico será carregado aqui -->
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="fecharModal()">Fechar</button>
                <button class="btn btn-primary" onclick="imprimirPedido()">
                    <span class="material-symbols-sharp">print</span>
                    Imprimir
                </button>
                <button class="btn btn-success" onclick="enviarEmail()">
                    <span class="material-symbols-sharp">email</span>
                    Enviar E-mail
                </button>
            </div>
        </div>
    </div>

    
<script src="../../js/dashboard.js"></script>
<script>
// Carregar status e cores no início
window.statusOptions = '';
window.coresStatus = {
    // Cores padrão para garantir funcionamento
    'EM PREPARAÇÃO': '#7dd87d',
    'Em Preparação': '#7dd87d',
    'PAGAMENTO CONFIRMADO': '#41f1b6',
    'Pagamento Confirmado': '#41f1b6',
    'ENTREGUE': '#28a745',
    'Entregue': '#28a745',
    'PEDIDO RECEBIDO': '#ff00cc',
    'Pedido Recebido': '#ff00cc',
    'ESTORNADO': '#fd7e14',
    'Estornado': '#fd7e14'
}; // Será atualizado via AJAX mas tem fallback

// Função para obter cor do status com fallback robusto
function getStatusColor(status) {
    // Log para debug
    console.log('🔍 Buscando cor para status:', status);
    
    // Primeiro: verificar se existe na base carregada
    if (window.coresStatus && window.coresStatus[status]) {
        console.log('✅ Cor encontrada:', window.coresStatus[status]);
        return window.coresStatus[status];
    }
    
    // Segundo: cores padrão baseadas no que vemos na imagem
    const coresPadrao = {
        'EM PREPARAÇÃO': '#7dd87d',
        'Em Preparação': '#7dd87d', 
        'PAGAMENTO CONFIRMADO': '#41f1b6',
        'Pagamento Confirmado': '#41f1b6',
        'ENTREGUE': '#28a745',
        'Entregue': '#28a745',
        'PEDIDO RECEBIDO': '#ff00cc',
        'Pedido Recebido': '#ff00cc',
        'ESTORNADO': '#fd7e14',
        'Estornado': '#fd7e14'
    };
    
    if (coresPadrao[status]) {
        console.log('✅ Cor padrão:', coresPadrao[status]);
        return coresPadrao[status];
    }
    
    console.warn('⚠️ Status não encontrado, usando cinza:', status);
    return '#6c757d';
}

// Função para carregar status da gestão de fluxo
async function carregarStatus() {
    try {
        console.log('🔄 Carregando status da gestão de fluxo...');
        const response = await fetch('orders.php?action=listar_status');
        const data = await response.json();
        
        if (data.success && data.status) {
            window.statusOptions = data.status.map(status => 
                `<option value="${status.nome}">${status.nome}</option>`
            ).join('');
            
            // Atualizar cores (mantém fallback e adiciona da base)
            data.status.forEach(status => {
                window.coresStatus[status.nome] = status.cor;
            });
            
            console.log('✅ Status sincronizados:', window.coresStatus);
            
            // Forçar re-renderização das cores na tabela
            setTimeout(() => {
                console.log('🎨 Aplicando cores na tabela...');
                document.querySelectorAll('.status-badge').forEach(badge => {
                    const status = badge.textContent.trim();
                    const cor = getStatusColor(status);
                    badge.style.backgroundColor = cor;
                    console.log(`🟡 ${status} → ${cor}`);
                });
                aplicarFiltros();
            }, 200);
        } else {
            console.error('❌ Erro ao carregar status:', data);
        }
    } catch (error) {
        console.error('❌ Erro de conexão:', error);
    }
}

// Função para aplicar filtros (alias para filtrarPedidos)
function aplicarFiltros() {
    filtrarPedidos();
}

// Garantir que o tema dark funcione em todas as páginas
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('darkTheme');
    if (savedTheme === 'true') {
        document.body.classList.add('dark-theme-variables');
        console.log('Tema dark aplicado em orders.php');
    }
    
    // Carregar status disponíveis
    carregarStatus();
    
    // Carregar pedidos inicial
    filtrarPedidos();
});

// Variável global para controlar a aba ativa
let abaAtiva = 'todos';

// Função para trocar abas com animação
function trocarAba(status) {
    abaAtiva = status;
    
    // Atualizar visual das abas com animação suave
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Adicionar classe active na tab clicada
    const tabAtiva = event.target.closest('.tab');
    tabAtiva.classList.add('active');
    
    // Adicionar efeito de loading na tabela
    const tbody = document.getElementById('pedidos-tbody');
    tbody.innerHTML = `
        <tr>
            <td colspan="6" class="loading">
                <span class="material-symbols-sharp">refresh</span>
                Carregando pedidos...
            </td>
        </tr>
    `;
    
    // Filtrar pedidos após pequeno delay para animação
    setTimeout(() => {
        filtrarPedidos();
    }, 200);
}

// Função para filtrar pedidos
function filtrarPedidos() {
    console.log('🔍 Iniciando filtro de pedidos...');
    
    const dataInicio = document.getElementById('data_inicio').value;
    const dataFim = document.getElementById('data_fim').value;
    const busca = document.getElementById('busca').value;
    
    console.log('Filtros:', { dataInicio, dataFim, busca, abaAtiva });
    
    // Mostrar loading
    document.getElementById('pedidos-tbody').innerHTML = `
        <tr>
            <td colspan="6" class="loading">
                <span class="material-symbols-sharp">refresh</span>
                Carregando pedidos...
            </td>
        </tr>
    `;
    
    const formData = new FormData();
    formData.append('action', 'buscar_pedidos');
    formData.append('status', abaAtiva === 'todos' ? '' : abaAtiva);
    formData.append('data_inicio', dataInicio);
    formData.append('data_fim', dataFim);
    formData.append('busca', busca);
    
    console.log('📡 Enviando requisição AJAX...');
    
    fetch('orders.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('📥 Resposta recebida:', response.status);
        return response.text(); // Usar text() primeiro para debug
    })
    .then(text => {
        console.log('📄 Resposta completa:', text);
        
        try {
            const data = JSON.parse(text);
            console.log('✅ JSON parseado:', data);
            
            if (data.success) {
                console.log('🎉 Pedidos encontrados:', data.pedidos.length);
                renderizarPedidos(data.pedidos);
            } else {
                console.error('❌ Erro no servidor:', data.message);
                document.getElementById('pedidos-tbody').innerHTML = `
                    <tr>
                        <td colspan="6" class="empty-state">
                            <span class="material-symbols-sharp">error</span>
                            Erro: ${data.message || 'Erro desconhecido'}
                        </td>
                    </tr>
                `;
            }
        } catch (e) {
            console.error('❌ Erro ao parsear JSON:', e);
            console.log('📄 Resposta não é JSON válido:', text.substring(0, 500));
            document.getElementById('pedidos-tbody').innerHTML = `
                <tr>
                    <td colspan="6" class="empty-state">
                        <span class="material-symbols-sharp">error</span>
                        Erro de comunicação (verifique console)
                    </td>
                </tr>
            `;
        }
    })
    .catch(error => {
        console.error('❌ Erro na requisição:', error);
        document.getElementById('pedidos-tbody').innerHTML = `
            <tr>
                <td colspan="6" class="empty-state">
                    <span class="material-symbols-sharp">error</span>
                    Erro de conexão
                </td>
            </tr>
        `;
    });
}

// Função para renderizar pedidos na tabela
function renderizarPedidos(pedidos) {
    const tbody = document.getElementById('pedidos-tbody');
    
    if (pedidos.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="empty-state">
                    <span class="material-symbols-sharp">inbox</span>
                    <p>Nenhum pedido encontrado</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = pedidos.map(pedido => {
        // Data e hora DA COMPRA (data_pedido)
        const dataCompra = new Date(pedido.data_pedido);
        const data = dataCompra.toLocaleDateString('pt-BR');
        const hora = dataCompra.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        
        const valor = parseFloat(pedido.valor_total).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        });
        
        // Resumo com produtos comprados
        let resumo = 'Carregando produtos...';
        if (pedido.produtos_resumo) {
            resumo = pedido.produtos_resumo;
        }
        
        return `
            <tr>
                <td><span class="order-id">#${pedido.id}</span></td>
                <td>
                    <span class="order-date">${data}</span><br>
                    <span class="order-time">${hora}</span>
                </td>
                <td><span class="client-name">${pedido.cliente_nome}</span></td>
                <td><span class="order-summary">${resumo}</span></td>
                <td><span class="order-value">${valor}</span></td>
                <td>
                    <span class="status-badge" style="background-color: ${getStatusColor(pedido.status)}">
                        ${pedido.status}
                    </span>
                </td>
                <td>
                    <button class="btn-details" onclick="verDetalhes(${pedido.id})">
                        <span class="material-symbols-sharp">visibility</span>
                        Ver Detalhes
                    </button>
                </td>
            </tr>
        `;
    }).join('');
    
    // Atualizar contadores das tabs
    atualizarContadores(pedidos);
}

// Função para atualizar contadores das tabs
function atualizarContadores(pedidos) {
    const contadores = {
        todos: pedidos.length,
        pendente: 0,
        confirmado: 0,
        enviado: 0,
        reembolso: 0
    };
    
    pedidos.forEach(pedido => {
        const status = pedido.status.toLowerCase();
        if (status.includes('aguardando') || status.includes('pendente')) {
            contadores.pendente++;
        } else if (status.includes('confirmado') || status.includes('pago')) {
            contadores.confirmado++;
        } else if (status.includes('enviado') || status.includes('entregue')) {
            contadores.enviado++;
        } else if (status.includes('reembolso')) {
            contadores.reembolso++;
        }
    });
    
    // Atualizar elementos
    document.getElementById('count-todos').textContent = contadores.todos;
    document.getElementById('count-pendente').textContent = contadores.pendente;
    document.getElementById('count-confirmado').textContent = contadores.confirmado;
    document.getElementById('count-enviado').textContent = contadores.enviado;
    document.getElementById('count-reembolso').textContent = contadores.reembolso;
}

// Função para ver detalhes do pedido
function verDetalhes(pedidoId) {
    document.getElementById('orderModal').style.display = 'block';
    document.getElementById('modal-title').textContent = `Detalhes do Pedido #${pedidoId}`;
    document.getElementById('modal-content-area').innerHTML = `
        <div class="loading">
            <span class="material-symbols-sharp">refresh</span>
            Carregando detalhes...
        </div>
    `;
    
    const formData = new FormData();
    formData.append('action', 'buscar_detalhes_pedido');
    formData.append('pedido_id', pedidoId);
    
    fetch('orders.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderizarDetalhesPedido(data.pedido);
        } else {
            document.getElementById('modal-content-area').innerHTML = `
                <div class="empty-state">
                    <span class="material-symbols-sharp">error</span>
                    <p>Erro ao carregar detalhes: ${data.message}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        document.getElementById('modal-content-area').innerHTML = `
            <div class="empty-state">
                <span class="material-symbols-sharp">error</span>
                <p>Erro de conexão</p>
            </div>
        `;
    });
}

// Função para renderizar detalhes do pedido
function renderizarDetalhesPedido(pedido) {
    const data = new Date(pedido.data_pedido).toLocaleDateString('pt-BR');
    const valor = parseFloat(pedido.valor_total).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    });
    
    let itensHtml = '';
    if (pedido.itens && pedido.itens.length > 0) {
        itensHtml = `
            <table>
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Quantidade</th>
                        <th>Preço Unit.</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    ${pedido.itens.map(item => {
                        const precoUnit = parseFloat(item.preco_unitario).toLocaleString('pt-BR', {
                            style: 'currency',
                            currency: 'BRL'
                        });
                        const subtotal = (item.quantidade * item.preco_unitario).toLocaleString('pt-BR', {
                            style: 'currency',
                            currency: 'BRL'
                        });
                        
                        return `
                            <tr>
                                <td>${item.produto_nome}</td>
                                <td>${item.quantidade}</td>
                                <td>${precoUnit}</td>
                                <td>${subtotal}</td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        `;
    } else {
        itensHtml = '<p>Nenhum item encontrado</p>';
    }
    
    const reembolsoBtn = (pedido.status.includes('reembolso') || pedido.status.includes('Solicitado')) ? 
        `<button class="btn-process-refund" onclick="processarReembolso(${pedido.id})">
            <span class="material-symbols-sharp">currency_exchange</span>
            Processar Reembolso
        </button>` : '';
    
    document.getElementById('modal-content-area').innerHTML = `
        <div class="order-details-grid">
            <div class="detail-card">
                <h4>
                    <span class="material-symbols-sharp">person</span>
                    Dados do Cliente
                </h4>
                <div class="detail-item">
                    <label>Nome Completo:</label>
                    <span>${pedido.cliente_nome}</span>
                </div>
                <div class="detail-item">
                    <label>E-mail:</label>
                    <span>${pedido.cliente_email}</span>
                </div>
                <div class="detail-item">
                    <label>Telefone (WhatsApp):</label>
                    <span>${pedido.cliente_telefone || 'Não informado'}</span>
                </div>
            </div>
            
            <div class="detail-card">
                <h4>
                    <span class="material-symbols-sharp">location_on</span>
                    Endereço de Entrega
                </h4>
                <div class="detail-item">
                    <label>Endereço:</label>
                    <span>${pedido.cliente_endereco || 'Não informado'}</span>
                </div>
                <div class="detail-item">
                    <label>Cidade/Estado:</label>
                    <span>${pedido.cliente_cidade || 'Não informado'} - ${pedido.cliente_estado || ''}</span>
                </div>
                <div class="detail-item">
                    <label>CEP:</label>
                    <span>${pedido.cliente_cep || 'Não informado'}</span>
                </div>
            </div>
            
            <div class="detail-card">
                <h4>
                    <span class="material-symbols-sharp">payment</span>
                    Informações do Pedido
                </h4>
                <div class="detail-item">
                    <label>Data do Pedido:</label>
                    <span>${data}</span>
                </div>
                <div class="detail-item">
                    <label>Valor Total:</label>
                    <span>${valor}</span>
                </div>
                <div class="detail-item">
                    <label>Status Atual:</label>
                    <span class="status-badge" style="background-color: ${getStatusColor(pedido.status)}">
                        ${pedido.status}
                    </span>
                </div>
                <div class="detail-item">
                    <label>Alterar Status:</label>
                    <select class="form-select" id="novo-status-${pedido.id}" onchange="atualizarStatusPedido(${pedido.id})">
                        <option value="">Selecione novo status...</option>
                        ${window.statusOptions || ''}
                    </select>
                </div>
                <div class="detail-item">
                    <label>Observações:</label>
                    <span>${pedido.observacoes || 'Nenhuma observação'}</span>
                </div>
            </div>
        </div>
        
        <div class="detail-card" style="margin-top: 2rem;">
            <h4>
                <span class="material-symbols-sharp">shopping_cart</span>
                Itens do Pedido
            </h4>
            <div class="items-list">
                ${itensHtml}
            </div>
        </div>
        
        ${reembolsoBtn}
    `;
}

// Função para processar reembolso
function processarReembolso(pedidoId) {
    if (!confirm('Tem certeza que deseja processar o reembolso deste pedido?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'processar_reembolso');
    formData.append('pedido_id', pedidoId);
    
    fetch('orders.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Reembolso processado com sucesso!');
            fecharModal();
            filtrarPedidos(); // Recarregar lista
        } else {
            alert('Erro ao processar reembolso: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro de conexão');
    });
}

// Função para fechar modal
function fecharModal() {
    document.getElementById('orderModal').style.display = 'none';
}

// Função para atualizar status do pedido
function atualizarStatusPedido(pedidoId) {
    const selectElement = document.getElementById(`novo-status-${pedidoId}`);
    const novoStatus = selectElement.value;
    
    if (!novoStatus) {
        return;
    }
    
    if (!confirm(`Confirmar alteração do status para "${novoStatus}"?\nUm email automático será enviado ao cliente.`)) {
        selectElement.value = '';
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'atualizar_status');
    formData.append('pedido_id', pedidoId);
    formData.append('novo_status', novoStatus);
    
    // Mostrar loading no select
    selectElement.disabled = true;
    selectElement.style.opacity = '0.6';
    
    fetch('orders.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Sucesso - exibir mensagem
            showNotification('Status atualizado com sucesso! Email enviado ao cliente.', 'success');
            
            // Atualizar a tabela
            aplicarFiltros();
            
            // Fechar modal após 1 segundo
            setTimeout(() => {
                fecharModal();
            }, 1000);
        } else {
            // Erro - exibir mensagem
            showNotification(data.error || 'Erro ao atualizar status', 'error');
            selectElement.value = '';
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showNotification('Erro de conexão. Tente novamente.', 'error');
        selectElement.value = '';
    })
    .finally(() => {
        // Restaurar select
        selectElement.disabled = false;
        selectElement.style.opacity = '1';
    });
}

// Função para ver detalhes melhorada
function verDetalhes(pedidoId) {
    console.log('🔍 Abrindo detalhes do pedido:', pedidoId);
    window.pedidoAtualId = pedidoId;
    
    // Abrir modal
    const modal = document.getElementById('orderModal');
    modal.style.display = 'block';
    
    // Atualizar título
    document.getElementById('modal-title').textContent = `Detalhes do Pedido #${pedidoId}`;
    
    // Carregar dados
    carregarDetalhesPedido(pedidoId);
}

// Função para carregar detalhes completos do pedido
async function carregarDetalhesPedido(pedidoId) {
    try {
        console.log('📡 Carregando detalhes do pedido:', pedidoId);
        
        const response = await fetch('orders.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=buscar_detalhes_pedido&pedido_id=${pedidoId}`
        });
        
        console.log('📥 Resposta HTTP recebida:', response.status);
        
        // Verificar se a resposta é OK
        if (!response.ok) {
            throw new Error(`Erro HTTP: ${response.status}`);
        }
        
        // Tentar obter o texto da resposta primeiro
        const responseText = await response.text();
        console.log('📄 Resposta bruta:', responseText.substring(0, 200) + '...');
        
        // Tentar fazer parse do JSON
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('❌ Erro ao fazer parse JSON:', parseError);
            console.error('🔍 Resposta completa:', responseText);
            throw new Error('Resposta inválida do servidor (não é JSON válido)');
        }
        
        console.log('✅ JSON parseado:', data);
        
        if (data.success && data.pedido) {
            console.log('✅ Dados do pedido:', data.pedido);
            preencherDetalhesPedido(data.pedido);
        } else {
            console.error('❌ Erro ao carregar detalhes:', data.message);
            mostrarNotificacao(data.message || 'Erro ao carregar detalhes do pedido', 'error');
        }
    } catch (error) {
        console.error('❌ Erro de conexão/processamento:', error);
        mostrarNotificacao('Erro de conexão: ' + error.message, 'error');
    }
}

// Função para preencher os detalhes no modal
function preencherDetalhesPedido(pedido) {
    console.log('🔧 Preenchendo detalhes do pedido:', pedido);
    
    // Dados do Cliente
    document.getElementById('cliente-nome').textContent = pedido.cliente_nome || 'Cliente não informado';
    document.getElementById('cliente-email').textContent = pedido.cliente_email || 'Email não informado';
    document.getElementById('cliente-telefone').textContent = pedido.cliente_telefone || pedido.telefone || 'Telefone não informado';
    document.getElementById('cliente-documento').textContent = pedido.cliente_documento || pedido.documento || pedido.cpf || 'CPF/CNPJ não informado';
    
    // Endereço
    document.getElementById('endereco-completo').textContent = pedido.cliente_endereco || pedido.endereco || 'Endereço não informado';
    document.getElementById('cidade-estado').textContent = 
        (pedido.cliente_cidade || pedido.cidade) && (pedido.cliente_estado || pedido.estado)
        ? `${pedido.cliente_cidade || pedido.cidade}/${pedido.cliente_estado || pedido.estado}` 
        : (pedido.cliente_cidade || pedido.cidade || 'Cidade não informada');
    document.getElementById('endereco-cep').textContent = pedido.cliente_cep || pedido.cep || 'CEP não informado';
    document.getElementById('endereco-complemento').textContent = pedido.endereco_complemento || pedido.complemento || 'Sem complemento';
    
    // Informações do Pedido
    let dataPedido;
    if (pedido.data_pedido) {
        dataPedido = new Date(pedido.data_pedido);
    } else {
        dataPedido = new Date(); // Fallback para agora
    }
    
    document.getElementById('pedido-data').textContent = dataPedido.toLocaleDateString('pt-BR');
    document.getElementById('pedido-hora').textContent = dataPedido.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    
    // Forma de Pagamento e Parcelamento
    const formaPagamento = pedido.forma_pagamento || 'Cartão de Crédito';
    const parcelas = pedido.parcelas || 1;
    const valorTotal = parseFloat(pedido.valor_total || 0);
    const valorParcela = pedido.valor_parcela || (valorTotal / parcelas);
    
    // Aplicar apenas como texto simples
    document.getElementById('forma-pagamento').className = 'payment-text';
    document.getElementById('forma-pagamento').textContent = formaPagamento;
    
    // Informações de parcelas
    const parcelasInfo = document.getElementById('parcelas-info');
    if (parcelas > 1) {
        parcelasInfo.textContent = `${parcelas}x de R$ ${valorParcela.toFixed(2).replace('.', ',')}`;
        parcelasInfo.style.display = 'inline-block';
    } else {
        parcelasInfo.textContent = 'À vista';
        parcelasInfo.style.display = 'inline-block';
    }
    
    // Status atual com design limpo sem fundo
    const statusAtual = document.getElementById('status-atual');
    const corStatus = getStatusColor(pedido.status);
    statusAtual.innerHTML = `
        <span style="
            background: ${corStatus};
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            display: inline-block;
            border: none;
        ">${pedido.status}</span>
    `;
    
    // Preencher select de status
    const selectStatus = document.getElementById('novo-status');
    selectStatus.innerHTML = '<option value="">Selecione novo status...</option>' + window.statusOptions;
    
    document.getElementById('pedido-observacoes').textContent = pedido.observacoes || 'Nenhuma observação específica';
    
    // Informações de Entrega
    document.getElementById('transportadora').textContent = pedido.transportadora || 'Correios';
    document.getElementById('codigo-rastreio').textContent = pedido.codigo_rastreio || 'Aguardando envio';
    document.getElementById('previsao-entrega').textContent = pedido.previsao_entrega || 'A calcular';
    document.getElementById('valor-frete').textContent = `R$ ${parseFloat(pedido.valor_frete || 0).toFixed(2).replace('.', ',')}`;
    
    // Carregar itens
    carregarItensPedido(pedido.itens || []);
    
    // Totais
    const subtotal = parseFloat(pedido.subtotal || pedido.valor_total || 0);
    const desconto = parseFloat(pedido.desconto || 0);
    const frete = parseFloat(pedido.valor_frete || 0);
    const total = parseFloat(pedido.valor_total || 0);
    
    document.getElementById('subtotal-pedido').textContent = `R$ ${subtotal.toFixed(2).replace('.', ',')}`;
    document.getElementById('desconto-pedido').textContent = desconto > 0 ? `-R$ ${desconto.toFixed(2).replace('.', ',')}` : 'R$ 0,00';
    document.getElementById('frete-pedido').textContent = `R$ ${frete.toFixed(2).replace('.', ',')}`;
    document.getElementById('total-final').textContent = `R$ ${total.toFixed(2).replace('.', ',')}`;
    
    // Histórico de status (simulado)
    carregarHistoricoStatus(pedido);
}

// Função para carregar itens do pedido
function carregarItensPedido(itens) {
    const tbody = document.getElementById('pedido-itens');
    tbody.innerHTML = '';
    
    if (!itens || itens.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; color: #6c757d; font-style: italic;">
                    Nenhum item encontrado
                </td>
            </tr>
        `;
        return;
    }
    
    itens.forEach(item => {
        const row = tbody.insertRow();
        row.innerHTML = `
            <td>${item.produto_nome || 'Produto'}</td>
            <td>${item.sku || 'N/A'}</td>
            <td>${item.quantidade || 1}</td>
            <td>R$ ${parseFloat(item.preco_unitario || 0).toFixed(2).replace('.', ',')}</td>
            <td>R$ ${parseFloat(item.desconto || 0).toFixed(2).replace('.', ',')}</td>
            <td class="price">R$ ${(parseFloat(item.preco_unitario || 0) * parseInt(item.quantidade || 1)).toFixed(2).replace('.', ',')}</td>
        `;
    });
}

// Função para carregar histórico de status
function carregarHistoricoStatus(pedido) {
    const historico = document.getElementById('historico-status');
    
    // Histórico simulado baseado na data do pedido
    const dataBase = new Date(pedido.data_pedido);
    
    const timeline = [
        { 
            data: dataBase, 
            status: 'Pedido Recebido', 
            usuario: 'Sistema' 
        },
        { 
            data: new Date(dataBase.getTime() + 30*60000), 
            status: pedido.status, 
            usuario: 'Sistema' 
        }
    ];
    
    historico.innerHTML = '';
    
    timeline.forEach(item => {
        const div = document.createElement('div');
        div.className = 'timeline-item';
        div.innerHTML = `
            <div class="timeline-date">${item.data.toLocaleDateString('pt-BR')} ${item.data.toLocaleTimeString('pt-BR')}</div>
            <div class="timeline-status">${item.status}</div>
            <div class="timeline-user">Por: ${item.usuario}</div>
        `;
        historico.appendChild(div);
    });
}

// Função para alterar status automaticamente quando selecionado
function alterarStatusAutomatico() {
    const select = document.getElementById('novo-status');
    const novoStatus = select.value;
    
    if (!novoStatus) return;
    
    const pedidoId = window.pedidoAtualId;
    if (!pedidoId) {
        alert('Erro: ID do pedido não encontrado');
        return;
    }
    
    // Confirmar alteração
    if (!confirm(`Confirma a alteração do status para "${novoStatus}"?`)) {
        // Resetar select se cancelou
        select.selectedIndex = 0;
        return;
    }
    
    console.log('🔄 Alterando status automaticamente:', novoStatus);
    
    // Mostrar loading
    const statusAtual = document.getElementById('status-atual');
    const statusOriginal = statusAtual.innerHTML;
    statusAtual.innerHTML = '<span style="color: #ff00cc;">Atualizando...</span>';
    
    // Desabilitar select durante a atualização
    select.disabled = true;
    
    fetch('orders.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=atualizar_status&pedido_id=${pedidoId}&novo_status=${encodeURIComponent(novoStatus)}`
    })
    .then(response => {
        console.log('📥 Resposta recebida:', response.status);
        
        if (!response.ok) {
            throw new Error(`Erro HTTP: ${response.status}`);
        }
        
        return response.text(); // Primeiro pegar como texto
    })
    .then(responseText => {
        console.log('📄 Resposta bruta:', responseText.substring(0, 200) + '...');
        
        // Tentar fazer parse do JSON
        try {
            const data = JSON.parse(responseText);
            
            if (data.success) {
                // Atualizar badge de status com design limpo
                const cor = getStatusColor(novoStatus);
                statusAtual.innerHTML = `
                    <span style="
                        background: ${cor};
                        color: white;
                        padding: 0.4rem 0.8rem;
                        border-radius: 15px;
                        font-weight: 600;
                        font-size: 0.85rem;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
                        display: inline-block;
                        border: none;
                    ">${novoStatus}</span>
                `;
                
                // Mostrar notificação de sucesso
                mostrarNotificacao('Status atualizado com sucesso!', 'success');
                
                // Atualizar a linha específica na tabela principal
                atualizarLinhaTabela(pedidoId, novoStatus, cor);
                
                // Recarregar a tabela completa após um tempo (backup)
                setTimeout(() => {
                    filtrarPedidos(); // Recarregar a lista de pedidos
                    console.log('✅ Tabela principal atualizada');
                }, 1000);
            } else {
                console.error('❌ Erro na resposta:', data.message);
                statusAtual.innerHTML = statusOriginal;
                mostrarNotificacao(data.message || 'Erro ao atualizar status', 'error');
            }
            
        } catch (parseError) {
            console.error('❌ Erro ao fazer parse JSON:', parseError);
            console.error('🔍 Resposta completa:', responseText);
            statusAtual.innerHTML = statusOriginal;
            mostrarNotificacao('Erro de resposta do servidor', 'error');
        }
    })
    .catch(error => {
        console.error('❌ Erro:', error);
        statusAtual.innerHTML = statusOriginal;
        mostrarNotificacao('Erro de conexão: ' + error.message, 'error');
    })
    .finally(() => {
        select.disabled = false;
    });
}

// Função para adicionar item ao histórico
function adicionarAoHistorico(novoStatus) {
    const historico = document.getElementById('historico-status');
    const agora = new Date();
    const dataFormatada = agora.toLocaleDateString('pt-BR') + ' ' + agora.toLocaleTimeString('pt-BR');
    
    const novoItem = document.createElement('div');
    novoItem.className = 'timeline-item';
    novoItem.innerHTML = `
        <div class="timeline-date">${dataFormatada}</div>
        <div class="timeline-status">${novoStatus}</div>
        <div class="timeline-user">Por: Sistema</div>
    `;
    
    historico.insertBefore(novoItem, historico.firstChild);
}

// Função para mostrar notificações
function mostrarNotificacao(mensagem, tipo = 'info') {
    // Criar elemento de notificação
    const notificacao = document.createElement('div');
    notificacao.className = `notificacao ${tipo}`;
    notificacao.textContent = mensagem;
    
    // Estilos
    notificacao.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        color: white;
        font-weight: 600;
        z-index: 10000;
        animation: slideIn 0.3s ease;
        max-width: 400px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    `;
    
    // Cores por tipo
    const cores = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    
    notificacao.style.backgroundColor = cores[tipo] || cores.info;
    
    // Adicionar ao DOM
    document.body.appendChild(notificacao);
    
    // Remover após 4 segundos
    setTimeout(() => {
        notificacao.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            if (notificacao.parentNode) {
                notificacao.parentNode.removeChild(notificacao);
            }
        }, 300);
    }, 4000);
}

// Funções para os botões do modal
function imprimirPedido() {
    window.print();
}

function enviarEmail() {
    mostrarNotificacao('🚀 Funcionalidade de envio de e-mail em desenvolvimento', 'info');
}

// Função para fechar modal
function fecharModal() {
    const modal = document.getElementById('orderModal');
    modal.style.display = 'none';
    window.pedidoAtualId = null;
}

// Fechar modal ao clicar fora
window.onclick = function(event) {
    const modal = document.getElementById('orderModal');
    if (event.target == modal) {
        fecharModal();
    }
}

// Função para atualizar linha específica da tabela
function atualizarLinhaTabela(pedidoId, novoStatus, corStatus) {
    const tbody = document.getElementById('pedidos-tbody');
    if (!tbody) return;
    
    const linhas = tbody.querySelectorAll('tr');
    
    linhas.forEach(linha => {
        // Procurar pelo botão de detalhes que contém o ID do pedido
        const botaoDetalhes = linha.querySelector('.btn-details');
        if (botaoDetalhes && botaoDetalhes.getAttribute('onclick') && 
            botaoDetalhes.getAttribute('onclick').includes(`verDetalhes(${pedidoId})`)) {
            
            // Encontrou a linha do pedido, atualizar o status
            const statusBadge = linha.querySelector('.status-badge');
            if (statusBadge) {
                statusBadge.style.backgroundColor = corStatus;
                statusBadge.textContent = novoStatus;
                console.log(`✅ Status atualizado na linha da tabela para pedido ${pedidoId}: ${novoStatus}`);
            }
        }
    });
}
</script>
 </body>
</html>









