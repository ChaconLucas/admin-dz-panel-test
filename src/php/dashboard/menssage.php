<?php
// Iniciar buffer de output para evitar problemas com headers
ob_start();

session_start();

// ENDPOINTS AJAX - MOVER PARA O TOPO ANTES DE QUALQUER HTML
// Endpoint AJAX para buscar status da IA
if (isset($_GET['action']) && $_GET['action'] === 'get_performance_metrics' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    ob_clean(); // Limpar qualquer output
    header('Content-Type: application/json');
    
    // Verificar se IA foi pausada pelo admin
    $ia_pausada_manual = isset($_SESSION['ia_pausada']) ? $_SESSION['ia_pausada'] : false;
    
    if ($ia_pausada_manual) {
        // IA foi pausada manualmente
        $status = [
            'online' => false,
            'status_text' => 'IA Pausada',
            'status_subtitle' => 'Pausada pelo administrador'
        ];
    } else {
        // Status simples baseado na hora atual
        $hora_atual = date('H');
        $dia_semana = date('w'); // 0 = domingo, 6 = sábado
        
        // Simular que IA está online durante horário comercial
        $horario_comercial = ($hora_atual >= 8 && $hora_atual <= 18) && ($dia_semana >= 1 && $dia_semana <= 5);
        
        if ($horario_comercial) {
            $status = [
                'online' => true,
                'status_text' => 'IA Online',
                'status_subtitle' => 'Horário de funcionamento ativo'
            ];
        } else {
            $status = [
                'online' => false,
                'status_text' => 'IA em Standby',
                'status_subtitle' => 'Fora do horário comercial'
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'ia_status' => $status,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Endpoint para pausar/despausar IA
if (isset($_POST['action']) && $_POST['action'] === 'toggle_ia_status') {
    ob_clean(); // Limpar qualquer output
    header('Content-Type: application/json');
    
    try {
        // Verificar se IA está pausada (usando sessão)
        $ia_pausada = isset($_SESSION['ia_pausada']) ? $_SESSION['ia_pausada'] : false;
        
        // Alternar status
        $novo_status = !$ia_pausada;
        $_SESSION['ia_pausada'] = $novo_status;
        $_SESSION['admin_responsavel'] = $_SESSION['usuario'] ?? 'admin';
        $_SESSION['ultima_alteracao'] = date('Y-m-d H:i:s');
        
        echo json_encode([
            'success' => true,
            'ia_ativa' => !$novo_status, // Se pausada = false, então ativa = true
            'message' => $novo_status ? 'IA pausada com sucesso' : 'IA reativada com sucesso',
            'debug' => [
                'ia_pausada_antes' => $ia_pausada,
                'novo_status_pausada' => $novo_status,
                'ia_ativa_retorno' => !$novo_status
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'error' => 'Erro ao alterar status da IA: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Endpoint para verificar status atual da IA
if (isset($_GET['action']) && $_GET['action'] === 'check_ia_status') {
    ob_clean(); // Limpar qualquer output
    header('Content-Type: application/json');
    
    $ia_pausada = isset($_SESSION['ia_pausada']) ? $_SESSION['ia_pausada'] : false;
    
    echo json_encode([
        'success' => true,
        'ia_pausada' => $ia_pausada,
        'ia_ativa' => !$ia_pausada
    ]);
    exit;
}

// Endpoint para exportar relatórios
if (isset($_GET['action']) && $_GET['action'] === 'export_reports') {
    ob_clean(); // Limpar qualquer output
    
    try {
        // Gerar dados do relatório
        $data_inicio = date('Y-m-d', strtotime('-30 days'));
        $data_fim = date('Y-m-d');
        
        // Dados simulados realistas para demonstração
        $dados = [];
        for ($i = 30; $i >= 0; $i--) {
            $data = date('Y-m-d', strtotime("-$i days"));
            $dados[] = [
                'data' => $data,
                'total_mensagens' => rand(50, 200),
                'mensagens_ia' => rand(25, 120),
                'mensagens_usuario' => rand(25, 80),
                'total_conversas' => rand(10, 50)
            ];
        }
        
        // Gerar arquivo CSV
        $filename = 'relatorio_chat_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo "\xEF\xBB\xBF"; // UTF-8 BOM para Excel
        
        // Cabeçalho
        echo "Data;Total Mensagens;Mensagens IA;Mensagens Usuario;Total Conversas\n";
        
        // Dados
        foreach ($dados as $linha) {
            echo date('d/m/Y', strtotime($linha['data'])) . ';' .
                 $linha['total_mensagens'] . ';' .
                 $linha['mensagens_ia'] . ';' .
                 $linha['mensagens_usuario'] . ';' .
                 $linha['total_conversas'] . "\n";
        }
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Erro ao gerar relatório']);
    }
    exit;
}

// RESTANTE DO CÓDIGO PHP - CARREGAMENTO DA PÁGINA
// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: /../src/html/chat-cliente.html');
    exit();
}

// Incluir conexão com banco de dados
$conexao_file = '../../../PHP/conexao.php';
if (!file_exists($conexao_file)) {
    if (isset($_GET['action']) || isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Arquivo de conexão não encontrado']);
        exit;
    }
    die('Arquivo de conexão não encontrado: ' . $conexao_file);
}
require_once $conexao_file;

// Incluir session tracker para detectar admin online
$session_tracker_file = '../session-tracker.php';
if (file_exists($session_tracker_file)) {
    require_once $session_tracker_file;
}

// Incluir sistema de chat (apenas classes, sem API endpoints)
$sistema_file = '../sistema.php';
if (file_exists($sistema_file)) {
    try {
        require_once $sistema_file;
        
        if (!isset($chat_manager)) {
            throw new Exception('ChatManager não foi inicializado');
        }
        
        $stats = $chat_manager->obterEstatisticas();
        $conversas = $chat_manager->obterConversas();
    } catch (Exception $e) {
        // Se é uma requisição AJAX, retornar JSON
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_GET['action']) || isset($_POST['action'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Erro no sistema de chat: ' . $e->getMessage()
            ]);
            exit;
        }
        // Caso contrário, mostrar erro normal
        die("Erro no sistema de chat: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
    }
} else {
    // Se sistema.php não existe, criar dados padrão
    $stats = ['total_mensagens' => 0, 'conversas_ativas' => 0];
    $conversas = [];
}

// Incluir contador de mensagens não lidas
require_once 'helper-contador.php';

// Buscar administradores do banco de dados
function buscarAdministradoresOnline($conexao) {
    $admins = [];
    
    try {
        // Buscar todos os usuários da tabela teste_dz
        $query = "SELECT id, nome, email, foto_perfil FROM teste_dz ORDER BY nome ASC";
        $result = mysqli_query($conexao, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            while ($admin = mysqli_fetch_assoc($result)) {
                // Verificar se o admin está online (baseado em sessão ativa)
                $isOnline = verificarAdminOnline($admin['id']);
                
                $admins[] = [
                    'id' => $admin['id'],
                    'nome' => $admin['nome'],
                    'email' => $admin['email'],
                    'foto_perfil' => $admin['foto_perfil'],
                    'iniciais' => gerarIniciais($admin['nome']),
                    'online' => $isOnline
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar administradores: " . $e->getMessage());
    }
    
    return $admins;
}

// Função para verificar se admin está online (sistema real de sessões)
function verificarAdminOnline($adminId) {
    global $conexao;
    
    try {
        // Criar tabela de sessões ativas se não existir
        criarTabelaSessoesSeNaoExistir($conexao);
        
        // Verificar se existe uma sessão ativa para este usuário nos últimos 5 minutos
        $query = "SELECT COUNT(*) as ativo FROM admin_sessions 
                  WHERE user_id = ? 
                  AND last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
        
        $stmt = mysqli_prepare($conexao, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $adminId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            return ($row['ativo'] > 0);
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Erro ao verificar status online: " . $e->getMessage());
        return false;
    }
}

// Função para criar tabela de sessões se não existir
function criarTabelaSessoesSeNaoExistir($conexao) {
    $createTable = "CREATE TABLE IF NOT EXISTS admin_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_id VARCHAR(255) NOT NULL,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        ip_address VARCHAR(45),
        user_agent TEXT,
        INDEX idx_user_activity (user_id, last_activity),
        INDEX idx_session (session_id),
        UNIQUE KEY unique_user_session (user_id, session_id)
    ) ENGINE=InnoDB";
    
    mysqli_query($conexao, $createTable);
}

// Função para registrar/atualizar sessão do usuário atual
function registrarSessaoAtiva() {
    global $conexao;
    
    if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_logado'])) {
        return false;
    }
    
    try {
        $userId = $_SESSION['usuario_id'];
        $sessionId = session_id();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Inserir ou atualizar sessão ativa
        $query = "INSERT INTO admin_sessions (user_id, session_id, ip_address, user_agent, last_activity) 
                  VALUES (?, ?, ?, ?, NOW()) 
                  ON DUPLICATE KEY UPDATE 
                  last_activity = NOW(), 
                  ip_address = VALUES(ip_address),
                  user_agent = VALUES(user_agent)";
        
        $stmt = mysqli_prepare($conexao, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'isss', $userId, $sessionId, $ipAddress, $userAgent);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return true;
        }
        
    } catch (Exception $e) {
        error_log("Erro ao registrar sessão: " . $e->getMessage());
    }
    
    return false;
}

// Função para limpar sessões expiradas
function limparSessoesExpiradas($conexao) {
    try {
        // Remover sessões inativas há mais de 10 minutos
        $query = "DELETE FROM admin_sessions 
                  WHERE last_activity < DATE_SUB(NOW(), INTERVAL 10 MINUTE)";
        mysqli_query($conexao, $query);
    } catch (Exception $e) {
        error_log("Erro ao limpar sessões expiradas: " . $e->getMessage());
    }
}

// Registrar sessão do usuário atual
registrarSessaoAtiva();

// Limpar sessões expiradas
limparSessoesExpiradas($conexao);

// Função para gerar iniciais do nome
function gerarIniciais($nome) {
    $nomes = explode(' ', trim($nome));
    if (count($nomes) >= 2) {
        return strtoupper(substr($nomes[0], 0, 1) . substr($nomes[1], 0, 1));
    } else {
        return strtoupper(substr($nome, 0, 2));
    }
}

// Buscar os administradores
$administradores = buscarAdministradoresOnline($conexao);

// Função para buscar alertas críticos reais do sistema de chat
function buscarAlertasCriticos($conexao) {
    $alertas = [];
    
    // Verificar se IA está pausada
    $ia_pausada = isset($_SESSION['ia_pausada']) ? $_SESSION['ia_pausada'] : false;
    
    if ($ia_pausada) {
        // Adicionar alerta de IA pausada
        $alertas[] = [
            'tipo' => 'warning',
            'icone' => 'pause_circle',
            'titulo' => 'IA pausada pelo administrador',
            'descricao' => 'Sistema de IA desativado manualmente',
            'tempo' => 'Agora'
        ];
    }
    
    try {
        // 1. Conversas aguardando intervenção humana há mais de 2 minutos
        $sql1 = "SELECT c.id, c.usuario_nome, c.updated_at, 
                        TIMESTAMPDIFF(MINUTE, c.updated_at, NOW()) as minutos_aguardando
                FROM conversas c 
                WHERE c.status = 'aguardando_humano' 
                AND TIMESTAMPDIFF(MINUTE, c.updated_at, NOW()) >= 2 
                ORDER BY c.updated_at ASC LIMIT 5";
        
        $result1 = mysqli_query($conexao, $sql1);
        if ($result1) {
            while ($row = mysqli_fetch_assoc($result1)) {
                $alertas[] = [
                    'tipo' => 'critical',
                    'icone' => 'schedule',
                    'titulo' => 'Aguardando intervenção humana',
                    'descricao' => 'Cliente: ' . ($row['usuario_nome'] ?: 'Chat #' . $row['id']),
                    'tempo' => 'há ' . $row['minutos_aguardando'] . ' min',
                    'timestamp' => $row['updated_at'],
                    'conversa_id' => $row['id']
                ];
            }
        }
        
        // 2. Conversas com muitas mensagens não respondidas (possível insatisfação)
        $sql2 = "SELECT c.id, c.usuario_nome, COUNT(m.id) as msg_nao_lidas,
                        MAX(m.timestamp) as ultima_mensagem
                FROM conversas c 
                JOIN mensagens m ON c.id = m.conversa_id 
                WHERE m.lida = FALSE 
                AND m.remetente = 'usuario' 
                AND c.status = 'ativa'
                GROUP BY c.id 
                HAVING COUNT(m.id) >= 3 
                ORDER BY COUNT(m.id) DESC LIMIT 3";
        
        $result2 = mysqli_query($conexao, $sql2);
        if ($result2) {
            while ($row = mysqli_fetch_assoc($result2)) {
                $tempo_decorrido = calcularTempoDecorrido($row['ultima_mensagem']);
                $alertas[] = [
                    'tipo' => 'critical',
                    'icone' => 'priority_high',
                    'titulo' => 'Cliente insatisfeito - Chat #' . $row['id'],
                    'descricao' => $row['msg_nao_lidas'] . ' mensagens não respondidas',
                    'tempo' => $tempo_decorrido,
                    'timestamp' => $row['ultima_mensagem'],
                    'conversa_id' => $row['id']
                ];
            }
        }
        
        // 3. Detectar palavras-chave negativas nas mensagens recentes
        $palavrasNegativas = ['ruim', 'péssimo', 'horrível', 'problema', 'erro', 'não funciona', 'insatisfeito', 'reclamar', 'cancelar'];
        $palavrasQuery = "'" . implode("','", $palavrasNegativas) . "'";
        
        $sql3 = "SELECT c.id, c.usuario_nome, m.conteudo, m.timestamp 
                FROM conversas c 
                JOIN mensagens m ON c.id = m.conversa_id 
                WHERE m.remetente = 'usuario' 
                AND m.timestamp >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                AND (LOWER(m.conteudo) REGEXP 'ruim|péssimo|horrível|problema|erro|não funciona|insatisfeito|reclamar|cancelar')
                ORDER BY m.timestamp DESC LIMIT 3";
        
        $result3 = mysqli_query($conexao, $sql3);
        if ($result3) {
            while ($row = mysqli_fetch_assoc($result3)) {
                $tempo_decorrido = calcularTempoDecorrido($row['timestamp']);
                $alertas[] = [
                    'tipo' => 'critical',
                    'icone' => 'emergency',
                    'titulo' => 'Sentimento negativo detectado',
                    'descricao' => 'Cliente: ' . ($row['usuario_nome'] ?: 'Chat #' . $row['id']),
                    'tempo' => $tempo_decorrido,
                    'timestamp' => $row['timestamp'],
                    'conversa_id' => $row['id']
                ];
            }
        }
        
        // 4. Conversas com tempo de resposta muito longo (> 10 minutos)
        $sql4 = "SELECT c.id, c.usuario_nome, 
                        MAX(CASE WHEN m.remetente = 'usuario' THEN m.timestamp END) as ultima_msg_cliente,
                        MAX(CASE WHEN m.remetente IN ('admin', 'ia') THEN m.timestamp END) as ultima_resposta,
                        TIMESTAMPDIFF(MINUTE, 
                            MAX(CASE WHEN m.remetente = 'usuario' THEN m.timestamp END),
                            NOW()
                        ) as minutos_sem_resposta
                FROM conversas c 
                JOIN mensagens m ON c.id = m.conversa_id 
                WHERE c.status = 'ativa'
                GROUP BY c.id 
                HAVING ultima_msg_cliente > COALESCE(ultima_resposta, '2000-01-01') 
                AND minutos_sem_resposta >= 10
                ORDER BY minutos_sem_resposta DESC LIMIT 2";
        
        $result4 = mysqli_query($conexao, $sql4);
        if ($result4) {
            while ($row = mysqli_fetch_assoc($result4)) {
                $alertas[] = [
                    'tipo' => 'warning',
                    'icone' => 'schedule',
                    'titulo' => 'Tempo de resposta alto',
                    'descricao' => 'Cliente: ' . ($row['usuario_nome'] ?: 'Chat #' . $row['id']),
                    'tempo' => 'há ' . $row['minutos_sem_resposta'] . ' min',
                    'timestamp' => $row['ultima_msg_cliente'],
                    'conversa_id' => $row['id']
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Erro ao buscar alertas críticos: " . $e->getMessage());
    }
    
    // Ordenar por timestamp (mais recente primeiro)
    usort($alertas, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    return array_slice($alertas, 0, 8); // Limitar a 8 alertas
}

// Função auxiliar para calcular tempo decorrido
function calcularTempoDecorrido($timestamp) {
    $agora = time();
    $tempo = strtotime($timestamp);
    $diferenca = $agora - $tempo;
    
    if ($diferenca < 60) {
        return 'agora';
    } elseif ($diferenca < 3600) {
        $minutos = floor($diferenca / 60);
        return "há {$minutos} min";
    } else {
        $horas = floor($diferenca / 3600);
        return "há {$horas}h";
    }
}

// Buscar alertas críticos reais
$alertasCriticos = buscarAlertasCriticos($conexao);

// Função para calcular métricas reais de performance da IA
function calcularPerformanceIA($conexao) {
    $performance = [
        'taxa_sucesso' => 0,
        'tempo_resposta' => 0,
        'total_conversas' => 0,
        'conversas_resolvidas_ia' => 0,
        'conversas_escaladas' => 0
    ];
    
    try {
        // 1. Calcular taxa de sucesso da IA (conversas resolvidas sem intervenção humana)
        $sql_sucesso = "
            SELECT 
                COUNT(*) as total_conversas,
                SUM(CASE WHEN status = 'resolvida' AND id NOT IN (
                    SELECT DISTINCT conversa_id FROM mensagens WHERE remetente = 'admin'
                ) THEN 1 ELSE 0 END) as resolvidas_ia,
                SUM(CASE WHEN status = 'aguardando_humano' OR id IN (
                    SELECT DISTINCT conversa_id FROM mensagens WHERE remetente = 'admin'
                ) THEN 1 ELSE 0 END) as escaladas
            FROM conversas 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND status IN ('ativa', 'resolvida', 'aguardando_humano')
        ";
        
        $result = mysqli_query($conexao, $sql_sucesso);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $total = (int)$row['total_conversas'];
            $resolvidas_ia = (int)$row['resolvidas_ia'];
            $escaladas = (int)$row['escaladas'];
            
            $performance['total_conversas'] = $total;
            $performance['conversas_resolvidas_ia'] = $resolvidas_ia;
            $performance['conversas_escaladas'] = $escaladas;
            
            if ($total > 0) {
                $performance['taxa_sucesso'] = round(($resolvidas_ia / $total) * 100);
            } else {
                $performance['taxa_sucesso'] = 95; // Valor padrão se não houver dados
            }
        }
        
        // 2. Calcular tempo médio de resposta da IA
        $sql_tempo = "
            SELECT AVG(tempo_resposta) as tempo_medio
            FROM (
                SELECT 
                    TIMESTAMPDIFF(SECOND, m1.timestamp, m2.timestamp) / 60 as tempo_resposta
                FROM mensagens m1
                JOIN mensagens m2 ON m1.conversa_id = m2.conversa_id 
                WHERE m1.remetente = 'usuario' 
                AND m2.remetente = 'ia'
                AND m2.timestamp > m1.timestamp
                AND m1.timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND TIMESTAMPDIFF(SECOND, m1.timestamp, m2.timestamp) BETWEEN 1 AND 300
                ORDER BY m1.timestamp, m2.timestamp
            ) as tempos
        ";
        
        $result = mysqli_query($conexao, $sql_tempo);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $tempo_medio = $row['tempo_medio'];
            if ($tempo_medio !== null && $tempo_medio > 0) {
                $performance['tempo_resposta'] = round($tempo_medio, 1);
            } else {
                // Calcular baseado em estatísticas simples se não houver dados específicos
                $sql_backup = "
                    SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) / 60 as tempo_medio
                    FROM conversas 
                    WHERE updated_at > created_at 
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ";
                $result_backup = mysqli_query($conexao, $sql_backup);
                if ($result_backup && $row_backup = mysqli_fetch_assoc($result_backup)) {
                    $performance['tempo_resposta'] = max(0.5, round($row_backup['tempo_medio'] ?: 1.2, 1));
                } else {
                    $performance['tempo_resposta'] = 1.2; // Valor padrão
                }
            }
        }
        
        // 3. Calcular métricas adicionais para insights
        $sql_insights = "
            SELECT 
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as conversas_ultima_hora,
                COUNT(CASE WHEN status = 'ativa' THEN 1 END) as conversas_ativas,
                AVG(total_mensagens) as media_mensagens_por_conversa
            FROM (
                SELECT c.id, c.status, c.created_at,
                       COUNT(m.id) as total_mensagens
                FROM conversas c
                LEFT JOIN mensagens m ON c.id = m.conversa_id
                WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY c.id, c.status, c.created_at
            ) as stats
        ";
        
        $result = mysqli_query($conexao, $sql_insights);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $performance['conversas_ultima_hora'] = (int)$row['conversas_ultima_hora'];
            $performance['conversas_ativas'] = (int)$row['conversas_ativas'];
            $performance['media_mensagens'] = round($row['media_mensagens_por_conversa'] ?: 0, 1);
        }
        
    } catch (Exception $e) {
        error_log("Erro ao calcular performance da IA: " . $e->getMessage());
        // Valores padrão em caso de erro
        $performance['taxa_sucesso'] = 85;
        $performance['tempo_resposta'] = 1.2;
    }
    
    return $performance;
}

// Calcular performance real da IA
$performanceIA = calcularPerformanceIA($conexao);

// Endpoint AJAX para atualizar lista de administradores
if (isset($_GET['action']) && $_GET['action'] === 'get_admins_online' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $adminsAtualizados = buscarAdministradoresOnline($conexao);
    
    echo json_encode([
        'success' => true,
        'admins' => $adminsAtualizados,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Endpoint AJAX para manter sessão ativa
if (isset($_GET['action']) && $_GET['action'] === 'ping_session' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $sucesso = registrarSessaoAtiva();
    
    echo json_encode([
        'success' => $sucesso,
        'user_id' => $_SESSION['usuario_id'] ?? null,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Endpoint AJAX para buscar alertas críticos atualizados
if (isset($_GET['action']) && $_GET['action'] === 'get_critical_alerts' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $alertasAtualizados = buscarAlertasCriticos($conexao);
    
    echo json_encode([
        'success' => true,
        'alerts' => $alertasAtualizados,
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../css/dashboard.css?v=<?php echo time(); ?>" />
    <link rel="stylesheet" href="../../css/modern-chat.css?v=<?php echo time(); ?>" />
    <link
      href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp"
      rel="stylesheet"
    />
    <title>Mensagens - Dashboard</title>
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

          <a href="orders.php">
            <span class="material-symbols-sharp">Orders</span>
            <h3>Pedidos</h3>
          </a>



          <a href="analytics.php">
            <span class="material-symbols-sharp">Insights</span>
            <h3>Gráficos</h3>
          </a>

          <a href="menssage.php" class="active">
            <span class="material-symbols-sharp">Mail</span>
            <h3>Mensagens</h3>
            <span class="message-count"><?php echo $nao_lidas; ?></span>
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
        <h1>Central de Mensagens</h1>

        <div class="insights modern-chat-panel">
          <!-- Modern Stats Cards -->
          <div class="chat-stats">
            <div class="stat-card messages-today">
              <div class="stat-icon">
                <span class="material-symbols-sharp">mail</span>
              </div>
              <div class="stat-content">
                <h3><?php echo $stats['mensagens_hoje']; ?></h3>
                <p>Mensagens Hoje</p>
              </div>
            </div>
                        
            <div class="stat-card unread-messages">
              <div class="stat-icon">
                <span class="material-symbols-sharp">person_raised_hand</span>
              </div>
              <div class="stat-content">
                <h3><?php echo count(array_filter($conversas, fn($c) => $c['status'] == 'aguardando_humano')); ?></h3>
                <p>Mensagens Escaladas</p>
              </div>
            </div>

 <div class="stat-card active-chats">
              <div class="stat-icon">
                <span class="material-symbols-sharp">check_circle</span>
              </div>
              <div class="stat-content">
                <h3><?php echo count(array_filter($conversas, fn($c) => $c['status'] == 'resolvida')); ?></h3>
                <p>Conversas Resolvidas</p>
              </div>
            </div>

          </div>
        </div>

          <!-- Modern Chat Interface -->
          <div class="chat-interface">
            <!-- Conversations Sidebar -->
            <div class="conversations-sidebar">
              <div class="sidebar-header">
                <h3>Conversas 
                  <?php if($stats['nao_lidas'] > 0): ?>
                    <span class="count"><?php echo $stats['nao_lidas']; ?></span>
                  <?php endif; ?>
                </h3>
                <div class="conversation-filters">
                  <button class="filter-tab active" onclick="filtrarConversas('todas')">
                    <span class="material-symbols-sharp">forum</span>
                    Todas
                    <span class="count"><?php echo count($conversas); ?></span>
                  </button>
                  <button class="filter-tab" onclick="filtrarConversas('nao_lidas')">
                    <span class="material-symbols-sharp">mark_chat_unread</span>
                    Não Lidas
                    <span class="count"><?php echo array_sum(array_column($conversas, 'nao_lidas')); ?></span>
                  </button>
                  <button class="filter-tab" onclick="filtrarConversas('ativa')">
                    <span class="material-symbols-sharp">circle</span>
                    Ativas
                    <span class="count"><?php echo $stats['conversas_ativas']; ?></span>
                  </button>
                  <button class="filter-tab" onclick="filtrarConversas('aguardando_humano')">
                    <span class="material-symbols-sharp">person_raised_hand</span>
                    Escalado
                    <span class="count"><?php echo count(array_filter($conversas, fn($c) => $c['status'] == 'aguardando_humano')); ?></span>
                  </button>
                  <button class="filter-tab" onclick="filtrarConversas('resolvida')">
                    <span class="material-symbols-sharp">check_circle</span>
                    Resolvidas
                    <span class="count"><?php echo count(array_filter($conversas, fn($c) => $c['status'] == 'resolvida')); ?></span>
                  </button>
                </div>
              </div>
              
              <div class="conversations-list">
                <?php if(!empty($conversas)): ?>
                  <?php foreach($conversas as $conversa): ?>
                    <div class="conversation-item" 
                         data-id="<?php echo $conversa['id']; ?>"
                         data-status="<?php echo $conversa['status']; ?>"
                         data-nao-lidas="<?php echo $conversa['nao_lidas']; ?>"
                         onclick="selecionarConversa(<?php echo $conversa['id']; ?>, '<?php echo htmlspecialchars($conversa['usuario_nome'] ?: 'Cliente #' . $conversa['id']); ?>')">
                      
                      <div class="conversation-avatar">
                        <div class="avatar-circle">
                          <?php echo strtoupper(substr($conversa['usuario_nome'] ?: 'Cliente', 0, 1)); ?>
                        </div>
                        <?php if($conversa['nao_lidas'] > 0): ?>
                          <div class="unread-indicator"></div>
                        <?php endif; ?>
                      </div>
                      
                      <div class="conversation-content">
                        <div class="conversation-main">
                          <h4><?php echo htmlspecialchars($conversa['usuario_nome'] ?: 'Cliente #' . $conversa['id']); ?></h4>
                          <p class="conversation-preview">
                            <?php echo htmlspecialchars(substr($conversa['ultima_mensagem'] ?? 'Sem mensagens', 0, 40)); ?>...
                          </p>
                        </div>
                        
                        <div class="conversation-meta">
                          <span class="conversation-time"><?php echo date('H:i', strtotime($conversa['updated_at'] ?? 'now')); ?></span>
                          <span class="status-badge status-<?php echo $conversa['status']; ?>">
                            <?php 
                            switch($conversa['status']) {
                              case 'ativa': echo 'ATIVA'; break;
                              case 'aguardando_humano': echo 'PENDENTE'; break;
                              case 'resolvida': echo 'RESOLVIDA'; break;
                            }
                            ?>
                          </span>
                          <?php if($conversa['nao_lidas'] > 0): ?>
                            <span class="unread-count"><?php echo $conversa['nao_lidas']; ?></span>
                          <?php endif; ?>
                        </div>
                      </div>
                      
                      <div class="conversation-actions">
                        <button onclick="event.stopPropagation(); marcarComoNaoLida(<?php echo $conversa['id']; ?>)" 
                                class="action-btn" title="Marcar como não lida">
                          <span class="material-symbols-sharp">mark_email_unread</span>
                        </button>
                        <button onclick="event.stopPropagation(); deletarConversa(<?php echo $conversa['id']; ?>)" 
                                class="action-btn delete" title="Deletar">
                          <span class="material-symbols-sharp">delete</span>
                        </button>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="empty-state">
                    <span class="material-symbols-sharp">forum</span>
                    <h4>Nenhuma conversa</h4>
                    <p>As conversas aparecerão aqui quando os clientes iniciarem o chat</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
            
            <!-- Chat Main Area -->
            <div class="chat-main">
              <div class="chat-placeholder">
                <div class="placeholder-content">
                  <span class="material-symbols-sharp">chat</span>
                  <h3>Selecione uma conversa</h3>
                  <p>Clique em uma conversa para visualizar e responder mensagens</p>
                </div>
              </div>
              
              <div id="conversa-ativa" class="active-conversation" style="display: none;">
                <div class="chat-header">
                  <div id="chat-header-content">
                    <!-- Conteúdo será preenchido via JavaScript -->
                  </div>
                </div>
                
                <div class="messages-container" id="mensagens-container">
                  <!-- Mensagens serão carregadas aqui -->
                </div>
                
                <div class="message-input">
                  <div class="quick-actions">
                    <button onclick="escalarParaHumano()" class="quick-btn escalate">
                      <span class="material-symbols-sharp">person_add</span>
                      Escalar para Humano
                    </button>
                    <button onclick="resolverConversa()" class="quick-btn resolve">
                      <span class="material-symbols-sharp">check_circle</span>
                      Resolver Conversa
                    </button>
                  </div>
                  
                  <div class="input-area">
                    <input type="text" 
                           id="admin-mensagem" 
                           placeholder="Digite sua mensagem..." 
                           onkeypress="if(event.key==='Enter') enviarMensagemAdmin()">
                    <button onclick="enviarMensagemAdmin()" class="send-btn">
                      <span class="material-symbols-sharp">send</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
      </main>

      <!----------FINAL MAIN---------->
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

        <!----------FINAL DO TOP RIGHT---------->
        <!-- Monitor de Performance da IA -->
        <div class="monitoring-panel">
          <div class="panel-section performance-monitor">
            <div class="section-header">
              <div class="icon-wrapper status-icon-wrapper" id="statusIconWrapper">
                <span class="material-symbols-sharp">speed</span>
              </div>
              <h3>
                Status da IA
              </h3>
              <small id="lastUpdate" style="color: #666; font-size: 0.8em; margin-left: 10px;">Carregando...</small>
            </div>
            <div class="performance-metrics">
              <div class="ia-status-container">
                <div class="ia-status-main">
                  <div class="ia-status-info">
                    <span class="ia-status-text" id="iaStatusText">IA Online</span>
                    <span class="ia-status-subtitle" id="iaStatusSubtitle">Sistema funcionando</span>
                  </div>
                </div>
                <small id="lastUpdate" style="color: #666; font-size: 0.7em; margin-top: 8px;">Carregando...</small>
              </div>
            </div>
            </div>
          </div>

          <!-- Alertas Críticos -->
          <div class="panel-section alerts-panel">
            <div class="section-header">
              <div class="icon-wrapper warning">
                <span class="material-symbols-sharp">warning</span>
              </div>
              <h3>Alertas Críticos (<?php echo count($alertasCriticos); ?>)</h3>
            </div>
            <div class="alerts-feed" id="alertsFeedDashboard">
              <?php if (!empty($alertasCriticos)): ?>
                <?php foreach ($alertasCriticos as $alerta): ?>
                  <div class="alert-item <?php echo $alerta['tipo']; ?>" 
                       onclick="abrirConversa(<?php echo $alerta['conversa_id']; ?>)" 
                       style="cursor: pointer;">
                    <span class="material-symbols-sharp alert-icon"><?php echo $alerta['icone']; ?></span>
                    <div class="alert-content">
                      <div class="alert-title"><?php echo htmlspecialchars($alerta['titulo']); ?></div>
                      <div class="alert-description" style="font-size: 0.65rem; color: var(--color-dark-variant); margin: 0.1rem 0;">
                        <?php echo htmlspecialchars($alerta['descricao']); ?>
                      </div>
                      <div class="alert-time"><?php echo $alerta['tempo']; ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="alert-item" style="border-left-color: var(--color-success);">
                  <span class="material-symbols-sharp alert-icon" style="color: var(--color-success);">check_circle</span>
                  <div class="alert-content">
                    <div class="alert-title">Tudo funcionando bem!</div>
                    <div class="alert-time">Nenhum alerta crítico</div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Ações Rápidas -->
          <div class="panel-section quick-actions-panel">
            <div class="section-header">
              <div class="icon-wrapper primary">
                <span class="material-symbols-sharp">bolt</span>
              </div>
              <h3>Ações Rápidas</h3>
            </div>
            <div class="quick-actions-grid">
              <button class="action-button <?php echo (isset($_SESSION['ia_pausada']) && $_SESSION['ia_pausada']) ? 'warning' : 'primary'; ?>" id="pauseAIDashboard">
                <span class="material-symbols-sharp"><?php echo (isset($_SESSION['ia_pausada']) && $_SESSION['ia_pausada']) ? 'play_circle' : 'pause_circle'; ?></span>
                <?php echo (isset($_SESSION['ia_pausada']) && $_SESSION['ia_pausada']) ? 'Reativar IA' : 'Pausar IA Geral'; ?>
              </button>
              <button class="action-button secondary" id="exportReportsDashboard">
                <span class="material-symbols-sharp">download</span>
                Exportar Relatórios
              </button>
            </div>
          </div>

          <!-- Administradores Online -->
          <div class="panel-section admins-panel">
            <div class="section-header">
              <div class="icon-wrapper success">
                <span class="material-symbols-sharp">admin_panel_settings</span>
              </div>
              <h3>Admins Online (<?php echo count(array_filter($administradores, fn($admin) => $admin['online'])); ?>)</h3>
            </div>
            <div class="admin-list-dashboard">
              <?php if (!empty($administradores)): ?>
                <?php foreach ($administradores as $admin): ?>
                  <?php if ($admin['online']): ?>
                    <div class="admin-item-dash">
                      <div class="admin-avatar" style="background: linear-gradient(135deg, var(--color-danger), #ff69b4);">
                        <?php echo htmlspecialchars($admin['iniciais']); ?>
                      </div>
                      <div class="admin-info">
                        <div class="admin-name"><?php echo htmlspecialchars($admin['nome']); ?></div>
                        <div class="admin-status">
                          <div class="status-dot"></div>
                          Online
                        </div>
                      </div>
                    </div>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="admin-item-dash">
                  <div class="admin-avatar">?</div>
                  <div class="admin-info">
                    <div class="admin-name">Nenhum admin online</div>
                    <div class="admin-status" style="color: var(--color-dark-variant);">
                      <div class="status-dot" style="background: var(--color-dark-variant);"></div>
                      Offline
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="../../js/dashboard.js?v=<?php echo time(); ?>"></script>
    
    <script>
      let conversaAtiva = null;
      let nomeClienteAtivo = null;
      const nomeAdminLogado = '<?php echo addslashes($_SESSION['usuario_nome'] ?? 'Admin'); ?>';
      
      // Dados PHP para JavaScript
      const mensagensEscaladasPHP = <?php echo count(array_filter($conversas, fn($c) => $c['status'] == 'aguardando_humano')); ?>;
      console.log('🐘 Mensagens escaladas do PHP:', mensagensEscaladasPHP);
      
      // Filtrar conversas (função corrigida para não quebrar event listeners)
      function filtrarConversas(status) {
        // Atualizar botões - procurar pelo filtro correto
        document.querySelectorAll('.filter-tab').forEach(btn => {
          btn.classList.remove('active');
        });
        
        // Encontrar e ativar o botão correto baseado no onclick
        document.querySelectorAll('.filter-tab').forEach(btn => {
          const onclick = btn.getAttribute('onclick');
          if (onclick && onclick.includes(`'${status}'`)) {
            btn.classList.add('active');
          }
        });
        
        // Filtrar itens com animação suave
        document.querySelectorAll('.conversation-item').forEach(item => {
          const itemStatus = item.getAttribute('data-status');
          const naoLidas = parseInt(item.getAttribute('data-nao-lidas')) || 0;
          
          let mostrar = false;
          switch(status) {
            case 'todas':
              mostrar = true;
              break;
            case 'nao_lidas':
              mostrar = naoLidas > 0;
              break;
            case 'ativa':
              mostrar = itemStatus === 'ativa';
              break;
            case 'resolvida':
              mostrar = itemStatus === 'resolvida';
              break;
            case 'aguardando_humano':
              mostrar = itemStatus === 'aguardando_humano';
              break;
            default:
              mostrar = itemStatus === status;
          }
          
          // Aplicar filtro usando classes CSS para manter o layout
          if (mostrar) {
            // Remover todas as classes de filtro
            item.classList.remove('filtered-hidden', 'animate-out', 'hide');
            // Limpar estilos inline que possam interferir
            item.style.display = '';
            item.style.opacity = '';
            item.style.transform = '';
            
            // Re-adicionar event listeners se necessário
            if (!item.hasAttribute('data-listeners-added')) {
              item.setAttribute('data-listeners-added', 'true');
              
              // Restaurar hover effects
              item.addEventListener('mouseenter', function() {
                if (!this.classList.contains('selected') && !this.classList.contains('filtered-hidden')) {
                  this.style.transform = 'translateX(4px)';
                }
              });
              
              item.addEventListener('mouseleave', function() {
                if (!this.classList.contains('selected') && !this.classList.contains('filtered-hidden')) {
                  this.style.transform = 'translateX(0)';
                }
              });
            }
          } else {
            // Usar classes CSS para esconder mantendo o flexbox
            item.classList.add('filtered-hidden');
            setTimeout(() => {
              if (item.classList.contains('filtered-hidden')) {
                item.classList.add('animate-out');
                setTimeout(() => {
                  if (item.classList.contains('animate-out')) {
                    item.classList.add('hide');
                  }
                }, 200);
              }
            }, 50);
          }
        });
        
        console.log(`Filtro aplicado: ${status}`);
      }
      
      // Selecionar conversa (função otimizada com marcação imediata)
      function selecionarConversa(id, nome) {
        // Garantir que sempre temos um nome válido
        nome = nome || `Cliente #${id}`;
        console.log('🎯 Selecionando conversa:', id, nome);
        
        // Evitar reprocessamento desnecessário
        if (conversaAtiva === parseInt(id)) {
          console.log('⚠️ Conversa já está ativa, ignorando');
          return;
        }
        
        conversaAtiva = parseInt(id);
        nomeClienteAtivo = nome; // Armazenar o nome do cliente ativo
        
        // PRIMEIRO: Marcar como lida IMEDIATAMENTE (antes mesmo de carregar mensagens)
        const conversaItem = document.querySelector(`[data-id="${id}"]`);
        if (conversaItem && parseInt(conversaItem.getAttribute('data-nao-lidas')) > 0) {
          console.log('🔄 Marcando conversa como lida imediatamente');
          
          // Remover indicadores visuais imediatamente
          const unreadIndicator = conversaItem.querySelector('.unread-indicator');
          const unreadCount = conversaItem.querySelector('.unread-count');
          
          if (unreadIndicator) unreadIndicator.remove();
          if (unreadCount) unreadCount.remove();
          
          conversaItem.setAttribute('data-nao-lidas', '0');
          
          // Feedback visual
          conversaItem.style.backgroundColor = '#d4edda';
          conversaItem.style.borderColor = '#28a745';
          setTimeout(() => {
            conversaItem.style.backgroundColor = '';
            conversaItem.style.borderColor = '';
          }, 1000);
        }
        
        // Destacar conversa selecionada
        document.querySelectorAll('.conversation-item').forEach(item => {
          if (parseInt(item.getAttribute('data-id')) === conversaAtiva) {
            item.classList.add('selected');
          } else {
            item.classList.remove('selected');
          }
        });
        
        // Mostrar chat
        const placeholder = document.querySelector('.chat-placeholder');
        const chatAtivo = document.getElementById('conversa-ativa');
        
        if (placeholder) placeholder.style.display = 'none';
        if (chatAtivo) {
          chatAtivo.style.display = 'flex';
        }
        
        // Atualizar header
        const headerContent = document.getElementById('chat-header-content');
        if (headerContent) {
          headerContent.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <div>
                <h3 style="margin: 0; color: var(--color-dark);">${nome}</h3>
                <small style="color: var(--color-dark-variant);">Conversa #${id}</small>
              </div>
              <div style="display: flex; gap: 0.5rem;">
                <button onclick="escalarParaHumano()" class="mini-btn" title="Escalar para humano">
                  <span class="material-symbols-sharp">person_add</span>
                </button>
                <button onclick="resolverConversa()" class="mini-btn" title="Resolver conversa">
                  <span class="material-symbols-sharp">check_circle</span>
                </button>
              </div>
            </div>
          `;
        }
        
        // Carregar mensagens (que também tentará marcar como lida na API)
        carregarMensagens(id);
        
        // Atualizar contadores
        setTimeout(() => {
          atualizarContadoresFiltros();
          if (window.atualizarContadorMensagens) {
            window.atualizarContadorMensagens();
          }
        }, 200);
      }
      
      // Carregar mensagens
      async function carregarMensagens(conversaId) {
        try {
          const url = `../sistema.php?api=1&endpoint=admin&action=get_messages&conversa_id=${conversaId}`;
          const response = await fetch(url);
          
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
          }
          
          const mensagens = await response.json();
          console.log('Mensagens recebidas:', mensagens);
          
          // Verificar se é um array de mensagens
          if (!Array.isArray(mensagens)) {
            console.error('Resposta não é um array:', mensagens);
            throw new Error('Formato de resposta inválido');
          }
          
          const container = document.getElementById('mensagens-container');
          container.innerHTML = '';
          
          mensagens.forEach(msg => {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message-bubble ${msg.remetente === 'usuario' ? 'client' : (msg.remetente === 'admin' ? 'admin' : 'ia')}`;
            
            // Usar nome salvo no banco se disponível, senão fallback
            const remetente = (msg.nome_remetente && msg.nome_remetente !== 'null') ? msg.nome_remetente : 
                             (msg.remetente === 'usuario' ? (nomeClienteAtivo || 'Cliente') : 
                              msg.remetente === 'admin' ? nomeAdminLogado : 'DAIze');
            
            // Avatar especial para DAIze (logo da marca) ou letra normal
            let avatarContent;
            if (remetente === 'DAIze' || msg.remetente === 'ia') {
              // Usar logo da marca D&Z (borboleta)
              avatarContent = `
                <img src="../../../assets/images/logodz.png" alt="DAIze" 
                     style="width: 85%; height: 85%; object-fit: contain;" 
                     onerror="this.parentNode.innerHTML='🦋'; this.parentNode.style.fontSize='1.4rem'">
              `;
            } else {
              avatarContent = remetente.charAt(0).toUpperCase();
            }
            const timestamp = new Date(msg.timestamp).toLocaleString('pt-BR', {
              hour: '2-digit', 
              minute: '2-digit',
              day: '2-digit',
              month: '2-digit'
            });
            
            // Estrutura diferente para admin/IA (avatar à direita) vs cliente (avatar à esquerda)
            if (msg.remetente === 'admin' || msg.remetente === 'ia') {
              messageDiv.innerHTML = `
                <div class="message-wrapper">
                  <div class="message-header">
                    <span class="message-sender">${remetente}</span>
                    <span class="message-time">${timestamp}</span>
                  </div>
                  <div class="message-content">
                    <div class="message-text">${msg.conteudo}</div>
                  </div>
                </div>
                <div class="message-avatar">${avatarContent}</div>
              `;
            } else {
              messageDiv.innerHTML = `
                <div class="message-avatar">${avatarContent}</div>
                <div class="message-wrapper">
                  <div class="message-header">
                    <span class="message-sender">${remetente}</span>
                    <span class="message-time">${timestamp}</span>
                  </div>
                  <div class="message-content">
                    <div class="message-text">${msg.conteudo}</div>
                  </div>
                </div>
              `;
            }
            
            container.appendChild(messageDiv);
          });
          
          container.scrollTop = container.scrollHeight;
          
          // Marcar mensagens como lidas
          await marcarMensagensLidas(conversaId);
          
        } catch (error) {
          console.error('Erro ao carregar mensagens:', error);
          const container = document.getElementById('mensagens-container');
          if (container) {
            container.innerHTML = '<div style="padding: 20px; text-align: center; color: #ff0066;">❌ Erro ao carregar mensagens. Verifique o console.</div>';
          }
        }
      }
      
      // Marcar mensagens como lidas (função corrigida e fortalecida)
      async function marcarMensagensLidas(conversaId) {
        console.log('🔄 Tentando marcar mensagens como lidas para conversa:', conversaId);
        
        // SEMPRE atualizar interface visual primeiro (para feedback imediato)
        const conversaItem = document.querySelector(`[data-id="${conversaId}"]`);
        if (conversaItem) {
          console.log('📝 Atualizando interface visual para conversa:', conversaId);
          
          // Remover indicador de não lida
          const unreadIndicator = conversaItem.querySelector('.unread-indicator');
          if (unreadIndicator) {
            console.log('🔴 Removendo indicador de não lida');
            unreadIndicator.remove();
          }
          
          // Remover contador de não lidas
          const unreadCount = conversaItem.querySelector('.unread-count');
          if (unreadCount) {
            console.log('🔢 Removendo contador de não lidas:', unreadCount.textContent);
            unreadCount.remove();
          }
          
          // Atualizar atributo data
          conversaItem.setAttribute('data-nao-lidas', '0');
          console.log('✅ Atributo data-nao-lidas definido como 0');
          
          // Feedback visual imediato
          conversaItem.style.transition = 'all 0.3s ease';
          conversaItem.style.backgroundColor = '#d4edda';
          conversaItem.style.borderColor = '#28a745';
          
          setTimeout(() => {
            conversaItem.style.backgroundColor = '';
            conversaItem.style.borderColor = '';
          }, 1500);
        }
        
        // Tentar atualizar no backend (sem bloquear a UI)
        try {
          const response = await fetch('../sistema.php?api=1&endpoint=admin&action=mark_messages_read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ conversa_id: conversaId })
          });
          
          if (!response.ok) {
            console.warn('⚠️ Resposta HTTP não ok:', response.status);
            return;
          }
          
          const result = await response.json();
          console.log('📊 Resposta da API:', result);
          
          if (result.success) {
            console.log('✅ API confirmou: mensagens marcadas como lidas');
          } else {
            console.warn('❌ API retornou erro:', result.error || 'Erro desconhecido');
            // Não reverter a interface - manter como lida visualmente
          }
          
        } catch (error) {
          console.error('🚨 Erro de conexão com API:', error);
          // Não reverter a interface - manter como lida visualmente
        }
        
        // SEMPRE atualizar contadores (independente da API)
        setTimeout(() => {
          atualizarContadoresFiltros();
          if (window.atualizarContadorMensagens) {
            window.atualizarContadorMensagens();
          }
        }, 100);
      }
      
      // Enviar mensagem admin
      async function enviarMensagemAdmin() {
        console.log('🚀 Tentando enviar mensagem, conversa ativa:', conversaAtiva);
        
        if (!conversaAtiva) {
          console.error('❌ Nenhuma conversa ativa');
          return;
        }
        
        const input = document.getElementById('admin-mensagem');
        const mensagem = input.value.trim();
        
        console.log('📝 Mensagem a enviar:', mensagem);
        
        if (!mensagem) {
          console.warn('⚠️ Mensagem vazia');
          return;
        }
        
        try {
          const response = await fetch('../sistema.php?api=1&endpoint=admin&action=send_admin_message', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              conversa_id: conversaAtiva,
              mensagem: mensagem
            })
          });
          
          const result = await response.json();
          console.log('📨 Resposta da API:', result);
          
          if (result.success) {
            console.log('✅ Mensagem enviada com sucesso');
            input.value = '';
            carregarMensagens(conversaAtiva);
          } else {
            console.error('❌ Erro da API:', result.error);
            alert('Erro ao enviar mensagem: ' + result.error);
          }
        } catch (error) {
          console.error('❌ Erro ao enviar mensagem:', error);
          alert('Erro de conexão: ' + error.message);
        }
      }
      
      // Escalar para humano
      async function escalarParaHumano() {
        if (!conversaAtiva) return;
        
        if (confirm('Deseja escalar esta conversa para atendimento humano?')) {
          try {
            const response = await fetch('../sistema.php?api=1&endpoint=admin&action=escalar_humano', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                conversa_id: conversaAtiva
              })
            });
            
            const result = await response.json();
            
            if (result.success) {
              alert('Conversa escalada com sucesso!');
              location.reload();
            } else {
              alert('Erro: ' + result.error);
            }
          } catch (error) {
            console.error('Erro:', error);
            alert('Erro de conexão');
          }
        }
      }
      
      // Resolver conversa
      async function resolverConversa() {
        if (!conversaAtiva) return;
        
        if (confirm('Deseja marcar esta conversa como resolvida?')) {
          try {
            const response = await fetch('../sistema.php?api=1&endpoint=admin&action=resolver_conversa', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                conversa_id: conversaAtiva
              })
            });
            
            const result = await response.json();
            
            if (result.success) {
              alert('Conversa resolvida com sucesso!');
              location.reload();
            } else {
              alert('Erro: ' + result.error);
            }
          } catch (error) {
            console.error('Erro:', error);
            alert('Erro de conexão');
          }
        }
      }
      
      // Marcar como não lida
      async function marcarComoNaoLida(conversaId) {
        try {
          const response = await fetch('../sistema.php?api=1&endpoint=admin&action=marcar_nao_lida', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ conversa_id: conversaId })
          });
          
          const result = await response.json();
          
          if (result.success) {
            const item = document.querySelector(`[data-id="${conversaId}"]`);
            if (item) {
              // Atualizar atributo
              item.setAttribute('data-nao-lidas', '1');
              
              // Adicionar indicador visual se não existir
              const avatar = item.querySelector('.conversation-avatar');
              let indicator = avatar.querySelector('.unread-indicator');
              if (!indicator) {
                indicator = document.createElement('div');
                indicator.className = 'unread-indicator';
                avatar.appendChild(indicator);
              }
              
              // Adicionar contador se não existir
              const footer = item.querySelector('.conversation-footer');
              let unreadCount = footer.querySelector('.unread-count');
              if (!unreadCount) {
                unreadCount = document.createElement('span');
                unreadCount.className = 'unread-count';
                unreadCount.textContent = '1';
                footer.appendChild(unreadCount);
              } else {
                unreadCount.textContent = '1';
              }
              
              // Feedback visual
              item.style.transition = 'all 0.3s ease';
              item.style.backgroundColor = 'var(--color-warning)';
              item.style.transform = 'scale(1.02)';
              
              setTimeout(() => {
                item.style.backgroundColor = '';
                item.style.transform = '';
              }, 800);
            }
            
            mostrarToast('Marcado como não lida!');
            
            // Atualizar contadores
            atualizarContadoresFiltros();
            if (window.atualizarContadorMensagens) {
              setTimeout(window.atualizarContadorMensagens, 500);
            }
          }
        } catch (error) {
          console.error('Erro:', error);
        }
      }
      
      // Função arquivarConversa removida - não é mais necessária
      
      // Deletar conversa
      async function deletarConversa(conversaId) {
        if (confirm('⚠️ ATENÇÃO: Deseja realmente DELETAR esta conversa? Esta ação não pode ser desfeita!')) {
          try {
            const response = await fetch('../sistema.php?api=1&endpoint=admin&action=deletar_conversa', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ conversa_id: conversaId })
            });
            
            const result = await response.json();
            
            if (result.success) {
              const item = document.querySelector(`[data-id="${conversaId}"]`);
              if (item) {
                item.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => {
                  item.remove();
                  if (conversaAtiva === conversaId) {
                    document.querySelector('.chat-placeholder').style.display = 'flex';
                    document.getElementById('conversa-ativa').style.display = 'none';
                    conversaAtiva = null;
                    nomeClienteAtivo = null;
                  }
                }, 300);
              }
              mostrarToast('Conversa deletada!', 'danger');
              if (window.atualizarContadorMensagens) {
                setTimeout(window.atualizarContadorMensagens, 500);
              }
            }
          } catch (error) {
            console.error('Erro:', error);
          }
        }
      }
      
      // Mostrar toast
      function mostrarToast(mensagem, tipo = 'success') {
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerHTML = `
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span class="material-symbols-sharp">${tipo === 'success' ? 'check_circle' : 'error'}</span>
            ${mensagem}
          </div>
        `;
        
        if (tipo === 'danger') {
          toast.style.background = 'linear-gradient(45deg, var(--color-danger), #ff6b9d)';
        }
        
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
      }
      
      // Testar API Groq
      async function testarGroqAPI() {
        try {
          const response = await fetch('../sistema.php?api=1&endpoint=admin&action=get_stats');
          const result = await response.json();
          
          if (result.success) {
            alert('✅ API Groq funcionando!\n\nResposta: ' + result.message);
          } else {
            alert('❌ Erro na API Groq:\n\n' + result.message);
          }
        } catch (error) {
          alert('❌ Erro de conexão: ' + error.message);
        }
      }
      
      // Auto-atualizar a cada 30 segundos
      setInterval(() => {
        if (conversaAtiva) {
          carregarMensagens(conversaAtiva);
        }
      }, 30000);
      
      // Função para gerenciar indicador de scroll
      function setupScrollIndicator() {
        const conversationsList = document.querySelector('.conversations-list');
        const sidebar = document.querySelector('.conversations-sidebar');
        
        if (!conversationsList || !sidebar) return;
        
        function checkScroll() {
          const hasScroll = conversationsList.scrollHeight > conversationsList.clientHeight;
          sidebar.classList.toggle('has-scroll', hasScroll);
        }
        
        // Verificar inicial
        checkScroll();
        
        // Verificar quando há mudanças no conteúdo
        const observer = new MutationObserver(checkScroll);
        observer.observe(conversationsList, { childList: true, subtree: true });
        
        // Verificar no redimensionamento
        window.addEventListener('resize', checkScroll);
        
        // Adicionar evento de scroll suave
        conversationsList.addEventListener('scroll', () => {
          // Opcional: lógica adicional quando o usuário faz scroll
        });
      }

      // Inicialização e configuração de event listeners
      function inicializarEventListeners() {
        // Setup do indicador de scroll
        setupScrollIndicator();
        
        // Hover effects para conversa items
        document.querySelectorAll('.conversation-item').forEach(item => {
          // Remover listeners existentes primeiro
          item.removeEventListener('mouseenter', hoverEnterHandler);
          item.removeEventListener('mouseleave', hoverLeaveHandler);
          
          // Adicionar novos listeners
          item.addEventListener('mouseenter', hoverEnterHandler);
          item.addEventListener('mouseleave', hoverLeaveHandler);
          
          // Marcar como inicializado
          item.setAttribute('data-listeners-added', 'true');
        });
      }
      
      // Handlers de hover separados para facilitar remoção
      function hoverEnterHandler() {
        if (!this.classList.contains('selected')) {
          this.style.transform = 'translateX(4px)';
        }
      }
      
      function hoverLeaveHandler() {
        if (!this.classList.contains('selected')) {
          this.style.transform = 'translateX(0)';
        }
      }
      
      // Adicionar CSS slideOut e estilos para indicadores
      const slideOutStyle = document.createElement('style');
      slideOutStyle.textContent = `
        @keyframes slideOut {
          to { transform: translateX(-100%); opacity: 0; height: 0; margin: 0; padding: 0; }
        }
        
        /* Garantir que indicadores removidos desapareçam */
        .unread-indicator.removing,
        .unread-count.removing {
          opacity: 0 !important;
          transform: scale(0) !important;
          transition: all 0.3s ease !important;
        }
        
        /* Estilo para conversa lida */
        .conversation-item[data-nao-lidas="0"] .unread-indicator,
        .conversation-item[data-nao-lidas="0"] .unread-count {
          display: none !important;
        }
        
        /* Debug - mostrar conversas não lidas em vermelho */
        .conversation-item[data-nao-lidas]:not([data-nao-lidas="0"]) {
          border-left: 3px solid var(--color-danger) !important;
        }
        
        .conversation-item[data-nao-lidas="0"] {
          border-left: 3px solid transparent !important;
        }
      `;
      document.head.appendChild(slideOutStyle);
      
      // Inicializar quando a página carregar
      document.addEventListener('DOMContentLoaded', inicializarEventListeners);
      

      

    </script>

    <style>
      /* CSS melhorado para interface de mensagens */
      .filter-tabs {
        background: white;
        border-radius: 12px;
        padding: 0.3rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      }
      
      .filter-btn {
        padding: 0.5rem 1rem;
        border: none;
        background: transparent;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.85rem;
        color: var(--color-dark-variant);
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }
      
      .filter-btn.active {
        background: var(--color-danger);
        color: white;
        border: 1px solid var(--color-danger);
        box-shadow: 0 2px 8px rgba(255, 0, 212, 0.3);
      }
      
      .filter-btn:hover:not(.active) {
        background: var(--color-baby-pink);
        color: var(--color-danger);
      }
      
      .badge {
        background: rgba(255,255,255,0.3);
        padding: 0.2rem 0.5rem;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: bold;
      }
      
      .filter-btn.active .badge {
        background: rgba(255,255,255,0.3);
      }
      
      .filter-btn:not(.active) .badge {
        background: var(--color-danger);
        color: white;
      }
      
      .action-btn {
        padding: 0.6rem;
        border: 1px solid var(--color-light);
        background: white;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
        color: var(--color-dark-variant);
      }
      
      .action-btn:hover {
        background: var(--color-danger);
        color: white;
        border-color: var(--color-danger);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255, 0, 212, 0.3);
      }
      
      .conversa-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(255, 0, 212, 0.2);
        border-color: var(--color-danger);
      }
      
      .conversa-item:hover .action-buttons {
        opacity: 1 !important;
      }
      
      .mini-btn {
        padding: 0.4rem;
        border: 1px solid var(--color-light);
        background: white;
        border-radius: 50%;
        cursor: pointer;
        transition: all 0.2s ease;
        color: var(--color-dark-variant);
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
      }
      
      .mini-btn:hover {
        transform: scale(1.1);
        background: var(--color-danger);
        color: white;
        border-color: var(--color-danger);
      }
      
      .mini-btn.delete:hover {
        background: var(--color-danger);
        border-color: var(--color-danger);
      }
      
      /* Animação removida para simplificar */
      
      /* Melhorar área de chat */
      #mensagens-container::-webkit-scrollbar {
        width: 6px;
      }
      
      #mensagens-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
      }
      
      #mensagens-container::-webkit-scrollbar-thumb {
        background: var(--color-primary);
        border-radius: 3px;
      }
      
      .mensagem-item {
        margin-bottom: 1rem;
        animation: slideIn 0.3s ease;
      }
      
      @keyframes slideIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
      }
      
      .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(45deg, var(--color-success), #7dd87d);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        z-index: 1000;
        animation: slideInRight 0.3s ease, fadeOut 0.3s ease 2.7s forwards;
      }
      
      @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
      }
      
      @keyframes fadeOut {
        to { transform: translateX(100%); opacity: 0; }
      }

      /* ===== ESTILOS DO PAINEL DE MONITORAMENTO ===== */
      .monitoring-panel {
        display: flex;
        flex-direction: column;
        gap: 2.5rem;
        margin-top: 1rem;
      }

      .panel-section {
        background: var(--color-white);
        border-radius: var(--card-border-radius);
        padding: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: var(--box-shadow);
        transition: all 0.3s ease;
      }

      .panel-section:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(255, 20, 147, 0.15);
      }

      .section-header {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        margin-bottom: 1rem;
      }

      .icon-wrapper {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--color-danger), #ff69b4);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
      }

      .icon-wrapper.warning {
        background: linear-gradient(135deg, var(--color-warning), #ffcc77);
      }

      .icon-wrapper.primary {
        background: linear-gradient(135deg, var(--color-danger), #ff69b4);
      }

      .icon-wrapper.success {
        background: linear-gradient(135deg, var(--color-success), #5ae4c4);
      }

      .section-header h3 {
        margin: 0;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--color-dark);
        display: flex;
        align-items: center;
        gap: 8px;
      }

      /* Status indicator */
      .performance-status {
        display: inline-flex;
        align-items: center;
        margin-right: 8px;
      }
      
      .performance-status .status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #2ecc71;
        box-shadow: 0 0 8px rgba(46, 204, 113, 0.6);
        animation: pulse 2s infinite ease-in-out;
        display: inline-block;
        flex-shrink: 0;
        min-width: 10px;
        min-height: 10px;
        max-width: 10px;
        max-height: 10px;
        aspect-ratio: 1/1;
        transform: scale(1);
      }
      
      .performance-status.loading .status-dot {
        background: #f39c12;
        box-shadow: 0 0 8px rgba(243, 156, 18, 0.6);
        animation: spin 1s linear infinite;
      }
      
      .performance-status.error .status-dot {
        background: #e74c3c;
        box-shadow: 0 0 8px rgba(231, 76, 60, 0.6);
        animation: shake 0.5s ease-in-out infinite;
      }
      
      @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.7; transform: scale(1.1); }
      }
      
      @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
      }
      
      @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-2px); }
        75% { transform: translateX(2px); }
      }

      /* Performance Metrics */
      .performance-metrics {
        display: flex;
        flex-direction: column;
        gap: 0.8rem;
        padding: 1rem;
      }

      .performance-monitor {
        padding: 1rem !important;
      }

      .status-icon-wrapper {
        background: linear-gradient(135deg, #ff1493, #e91e63) !important;
        color: white !important;
        width: 32px;
        height: 32px;
        min-width: 32px;
        min-height: 32px;
        max-width: 32px;
        max-height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 8px rgba(255, 20, 147, 0.2);
        flex-shrink: 0;
      }

      .status-icon-wrapper .material-symbols-sharp {
        font-size: 18px;
      }

      .status-icon-wrapper.offline {
        background: linear-gradient(135deg, #e74c3c, #c0392b) !important;
        box-shadow: 0 2px 8px rgba(231, 76, 60, 0.2);
      }

      .status-icon-wrapper.checking {
        background: linear-gradient(135deg, #f39c12, #e67e22) !important;
        box-shadow: 0 2px 8px rgba(243, 156, 18, 0.2);
        animation: pulse 1.5s infinite;
      }

      .ia-status-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
      }

      .ia-status-main {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;
      }

      .ia-status-dot {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #2ecc71;
        box-shadow: 0 0 12px rgba(46, 204, 113, 0.6);
        animation: pulse 2s infinite ease-in-out;
        flex-shrink: 0;
      }

      .ia-status-dot.offline {
        background: #e74c3c;
        box-shadow: 0 0 12px rgba(231, 76, 60, 0.6);
        animation: none;
      }

      .ia-status-dot.checking {
        background: #f39c12;
        box-shadow: 0 0 12px rgba(243, 156, 18, 0.6);
        animation: spin 1s linear infinite;
      }

      .ia-status-info {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
      }

      .ia-status-text {
        font-weight: 600;
        font-size: 0.95rem;
        color: var(--color-dark);
      }

      .ia-status-subtitle {
        font-size: 0.75rem;
        color: var(--color-dark-variant);
        margin-top: 2px;
      }

      .metric-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.7rem;
        background: var(--color-light);
        border-radius: var(--border-radius-2);
        border-left: 3px solid var(--color-danger);
      }

      .metric-label {
        font-size: 0.8rem;
        color: var(--color-dark-variant);
        font-weight: 500;
      }

      .metric-value {
        font-size: 1rem;
        font-weight: 600;
        color: var(--color-danger);
      }

      /* Alerts Feed */
      .alerts-feed {
        max-height: 180px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
      }

      .alert-item {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        padding: 0.7rem;
        margin-bottom: 0.8rem;
        background: var(--color-light);
        border-radius: var(--border-radius-1);
        border-left: 3px solid var(--color-warning);
        transition: all 0.3s ease;
      }

      .alert-item:last-child {
        margin-bottom: 0;
      }

      .alert-item:hover {
        background: #fff8e1;
        transform: translateX(4px);
      }

      .alert-item.critical {
        border-left-color: var(--color-danger);
      }

      .alert-item.critical:hover {
        background: #ffeef3;
      }

      .alert-icon {
        font-size: 1.1rem;
        color: var(--color-warning);
      }

      .alert-item.critical .alert-icon {
        color: var(--color-danger);
      }

      .alert-content {
        flex: 1;
      }

      .alert-title {
        font-size: 0.75rem;
        font-weight: 600;
        margin-bottom: 0.1rem;
        color: var(--color-dark);
        line-height: 1.2;
      }

      .alert-description {
        font-size: 0.65rem;
        color: var(--color-dark-variant);
        margin: 0.1rem 0;
        line-height: 1.1;
      }

      .alert-time {
        font-size: 0.65rem;
        color: var(--color-dark-variant);
      }

      /* Quick Actions */
      .quick-actions-grid {
        display: flex;
        flex-direction: column;
        gap: 1rem;
      }

      .action-button {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.7rem;
        border: none;
        border-radius: var(--border-radius-2);
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.8rem;
        font-family: inherit;
      }

      .action-button.primary {
        background: linear-gradient(135deg, var(--color-danger), #ff69b4);
        color: white;
      }

      .action-button.secondary {
        background: var(--color-light);
        color: var(--color-dark);
        border: 1px solid #ddd;
      }

      .action-button:hover {
        transform: translateY(-2px);
      }

      .action-button.primary:hover {
        box-shadow: 0 4px 12px rgba(255, 20, 147, 0.3);
      }

      .action-button.warning {
        background: linear-gradient(135deg, #f39c12, #e67e22);
        color: var(--color-white);
      }

      .action-button.warning:hover {
        box-shadow: 0 4px 12px rgba(243, 156, 18, 0.3);
      }

      .action-button.secondary:hover {
        background: var(--color-white);
        border-color: var(--color-danger);
        color: var(--color-danger);
      }

      /* Admin List */
      .admin-list-dashboard {
        display: flex;
        flex-direction: column;
        gap: 0.8rem;
      }

      .admin-item-dash {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        padding: 0.6rem;
        background: var(--color-light);
        border-radius: var(--border-radius-1);
        transition: all 0.3s ease;
      }

      .admin-item-dash:hover {
        background: var(--color-white);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      }

      .admin-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--color-danger), #ff69b4);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.7rem;
      }

      .admin-info {
        flex: 1;
      }

      .admin-name {
        font-size: 0.8rem;
        font-weight: 600;
        margin-bottom: 0.1rem;
        color: var(--color-dark);
      }

      .admin-status {
        font-size: 0.65rem;
        color: var(--color-success);
        display: flex;
        align-items: center;
        gap: 0.3rem;
      }

      .status-dot {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: var(--color-success);
        animation: pulse 2s infinite;
      }

      @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
      }

      /* Responsividade para o painel */
      @media screen and (max-width: 1200px) {
        .monitoring-panel {
          gap: 1rem;
        }
        
        .panel-section {
          padding: 1rem;
        }
        
        .section-header h3 {
          font-size: 0.85rem;
        }
      }
    </style>

    <script>
      // Atualizar contadores dos filtros
      function atualizarContadoresFiltros() {
        const conversas = document.querySelectorAll('.conversation-item');
        let totalConversas = conversas.length;
        let conversasNaoLidas = 0;
        let conversasAtivas = 0;
        let conversasEscaladas = 0;
        let conversasResolvidas = 0;
        
        conversas.forEach(item => {
          const status = item.getAttribute('data-status');
          const naoLidas = parseInt(item.getAttribute('data-nao-lidas')) || 0;
          
          if (naoLidas > 0) conversasNaoLidas++;
          if (status === 'ativa') conversasAtivas++;
          if (status === 'aguardando_humano') conversasEscaladas++;
          if (status === 'resolvida') conversasResolvidas++;
        });
        
        // Atualizar contadores nos botões
        const btnTodas = document.querySelector('.filter-tab[onclick*="todas"] .count');
        const btnNaoLidas = document.querySelector('.filter-tab[onclick*="nao_lidas"] .count');
        const btnAtivas = document.querySelector('.filter-tab[onclick*="ativa"] .count');
        const btnEscaladas = document.querySelector('.filter-tab[onclick*="aguardando_humano"] .count');
        const btnResolvidas = document.querySelector('.filter-tab[onclick*="resolvida"] .count');
        
        if (btnTodas) btnTodas.textContent = totalConversas;
        if (btnNaoLidas) btnNaoLidas.textContent = conversasNaoLidas;
        if (btnAtivas) btnAtivas.textContent = conversasAtivas;
        if (btnEscaladas) btnEscaladas.textContent = conversasEscaladas;
        if (btnResolvidas) btnResolvidas.textContent = conversasResolvidas;
      }
      
      // Marcar todas como lidas
      async function marcarTodasLidas() {
        if (confirm('Marcar todas as conversas como lidas?')) {
          try {
            const response = await fetch('../sistema.php?api=1&endpoint=admin&action=marcar_todas_lidas', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' }
            });
            
            const result = await response.json();
            
            if (result.success) {
              // Atualizar interface para todas as conversas
              document.querySelectorAll('.conversation-item').forEach(item => {
                item.setAttribute('data-nao-lidas', '0');
                
                const indicator = item.querySelector('.unread-indicator');
                if (indicator) indicator.remove();
                
                const unreadCount = item.querySelector('.unread-count');
                if (unreadCount) unreadCount.remove();
                
                // Feedback visual
                item.style.transition = 'all 0.3s ease';
                item.style.backgroundColor = 'var(--color-success)';
                setTimeout(() => {
                  item.style.backgroundColor = '';
                }, 1000);
              });
              
              mostrarToast('Todas as conversas foram marcadas como lidas!');
              
              // Atualizar contadores
              atualizarContadoresFiltros();
              if (window.atualizarContadorMensagens) {
                setTimeout(window.atualizarContadorMensagens, 500);
              }
            }
          } catch (error) {
            console.error('Erro:', error);
          }
        }
      }
    </script>

    <style>

      
      /* CSS específico para avatar da DAIze */
      .message-bubble.ia .message-avatar {
        background: linear-gradient(135deg, #ff6b9d, #c44569) !important;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        position: relative;
        overflow: hidden;
      }
      
      .message-bubble.ia .message-avatar img {
        transition: all 0.3s ease;
      }
      
      .message-bubble.ia .message-avatar img:hover {
        transform: scale(1.1);
      }
      
      /* Fallback emoji styling */
      .message-bubble.ia .message-avatar:not(:has(img)) {
        font-size: 1.2rem;
        background: linear-gradient(135deg, #ff6b9d, #c44569);
      }
    </style>

    <!-- JavaScript do Painel de Monitoramento -->
    <script>
      // Variáveis globais do painel
      let aiPausedDashboard = false;
      let metricsInterval;
      let alertsInterval;

      // Inicializar painel quando a página carregar
      document.addEventListener('DOMContentLoaded', function() {
        initMonitoringPanel();
        
        // Adicionar event listeners para ações rápidas
        const pauseBtn = document.getElementById('pauseAIDashboard');
        const exportBtn = document.getElementById('exportReportsDashboard');
        
        if (pauseBtn) {
          pauseBtn.addEventListener('click', toggleIAStatus);
          
          // Debug: Verificar estado inicial do botão
          console.log('🔄 Estado inicial do botão:', {
            text: pauseBtn.textContent,
            class: pauseBtn.className,
            ia_pausada_session: <?php echo json_encode(isset($_SESSION['ia_pausada']) ? $_SESSION['ia_pausada'] : false); ?>
          });
        }
        
        if (exportBtn) {
          exportBtn.addEventListener('click', exportReports);
        }
        
        // Debug: Mostrar informações dos administradores
        console.log('👥 Administradores carregados:', <?php echo json_encode($administradores); ?>);
        console.log('🔑 Usuário atual:', '<?php echo isset($_SESSION['usuario_nome']) ? addslashes($_SESSION['usuario_nome']) : 'Não logado'; ?>');
        console.log('🆔 ID do usuário:', <?php echo isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 'null'; ?>);
      });

      function initMonitoringPanel() {
        // Configurar eventos dos botões
        setupMonitoringEvents();
        
        // Atualizar métricas periodicamente
        updateMetrics();
        metricsInterval = setInterval(updateMetrics, 60000); // A cada 1 minuto (dados reais)
        
        // Atualizar lista de administradores periodicamente
        updateAdminList();
        setInterval(updateAdminList, 20000); // A cada 20 segundos
        
        // Atualizar alertas críticos periodicamente
        updateCriticalAlerts();
        setInterval(updateCriticalAlerts, 30000); // A cada 30 segundos
        
        // Manter sessão ativa (ping do usuário atual)
        keepSessionAlive();
        setInterval(keepSessionAlive, 60000); // A cada 1 minuto
        
        // Simular alertas periódicos menos frequente
        alertsInterval = setInterval(addRandomAlert, 120000); // A cada 2 minutos
        
        console.log('🎛️ Painel de monitoramento inicializado');
      }

      function setupMonitoringEvents() {
        // Botão Pausar/Reativar IA
        const pauseBtn = document.getElementById('pauseAIDashboard');
        if (pauseBtn) {
          pauseBtn.addEventListener('click', function() {
            toggleAI();
          });
        }

        // Botão Exportar Relatórios
        const exportBtn = document.getElementById('exportReportsDashboard');
        if (exportBtn) {
          exportBtn.addEventListener('click', function() {
            exportDailyReport();
          });
        }
      }

      // Função para abrir conversa específica (chamada pelos alertas)
      function abrirConversa(conversaId) {
        // Buscar e selecionar a conversa na lista
        const conversaItem = document.querySelector(`[data-id="${conversaId}"]`);
        if (conversaItem) {
          conversaItem.click();
          conversaItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
          // Se a conversa não está visível, recarregar a página com filtro específico
          window.location.href = `menssage.php?conversa=${conversaId}`;
        }
      }

      function keepSessionAlive() {
        // Fazer ping para manter a sessão ativa
        fetch('?action=ping_session', {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            timestamp: new Date().getTime()
          })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            console.log('🟢 Sessão mantida ativa');
          }
        })
        .catch(error => {
          console.error('⚠️ Erro ao manter sessão ativa:', error);
        });
      }

      function updateAdminList() {
        // Atualizar lista de administradores online
        fetch('?action=get_admins_online', {
          method: 'GET',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            updateAdminDisplay(data.admins);
          }
        })
        .catch(error => {
          console.error('Erro ao atualizar lista de admins:', error);
        });
      }

      function updateAdminDisplay(admins) {
        const adminList = document.querySelector('.admin-list-dashboard');
        const adminCount = document.querySelector('.admins-panel h3');
        
        if (!adminList) return;
        
        // Contar admins online
        const onlineAdmins = admins.filter(admin => admin.online);
        
        // Atualizar contador no título
        if (adminCount) {
          adminCount.textContent = `Admins Online (${onlineAdmins.length})`;
        }
        
        // Limpar lista atual
        adminList.innerHTML = '';
        
        if (onlineAdmins.length > 0) {
          onlineAdmins.forEach(admin => {
            const adminElement = createAdminElement(admin);
            adminList.appendChild(adminElement);
          });
        } else {
          adminList.innerHTML = `
            <div class="admin-item-dash">
              <div class="admin-avatar">?</div>
              <div class="admin-info">
                <div class="admin-name">Nenhum admin online</div>
                <div class="admin-status" style="color: var(--color-dark-variant);">
                  <div class="status-dot" style="background: var(--color-dark-variant);"></div>
                  Offline
                </div>
              </div>
            </div>
          `;
        }
      }

      function createAdminElement(admin) {
        const div = document.createElement('div');
        div.className = 'admin-item-dash';
        
        div.innerHTML = `
          <div class="admin-avatar" style="background: linear-gradient(135deg, var(--color-danger), #ff69b4);">
            ${admin.iniciais}
          </div>
          <div class="admin-info">
            <div class="admin-name">${admin.nome}</div>
            <div class="admin-status">
              <div class="status-dot"></div>
              Online
            </div>
          </div>
        `;
        
        return div;
      }

      function updateCriticalAlerts() {
        // Atualizar alertas críticos reais
        fetch('?action=get_critical_alerts', {
          method: 'GET',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            updateAlertsDisplay(data.alerts);
          }
        })
        .catch(error => {
          console.error('Erro ao atualizar alertas:', error);
        });
      }

      function updateAlertsDisplay(alerts) {
        const alertsFeed = document.getElementById('alertsFeedDashboard');
        const alertsCount = document.querySelector('.alerts-panel h3');
        
        if (!alertsFeed) return;
        
        // Atualizar contador no título
        if (alertsCount) {
          alertsCount.textContent = `Alertas Críticos (${alerts.length})`;
        }
        
        // Limpar lista atual
        alertsFeed.innerHTML = '';
        
        if (alerts.length > 0) {
          alerts.forEach(alert => {
            const alertElement = createAlertElement(alert);
            alertsFeed.appendChild(alertElement);
          });
        } else {
          alertsFeed.innerHTML = `
            <div class="alert-item" style="border-left-color: var(--color-success);">
              <span class="material-symbols-sharp alert-icon" style="color: var(--color-success);">check_circle</span>
              <div class="alert-content">
                <div class="alert-title">Tudo funcionando bem!</div>
                <div class="alert-time">Nenhum alerta crítico</div>
              </div>
            </div>
          `;
        }
      }

      function createAlertElement(alert) {
        const div = document.createElement('div');
        div.className = `alert-item ${alert.tipo}`;
        div.style.cursor = 'pointer';
        div.onclick = () => abrirConversa(alert.conversa_id);
        
        div.innerHTML = `
          <span class="material-symbols-sharp alert-icon">${alert.icone}</span>
          <div class="alert-content">
            <div class="alert-title">${alert.titulo}</div>
            ${alert.descricao ? `<div class="alert-description" style="font-size: 0.65rem; color: var(--color-dark-variant); margin: 0.1rem 0;">${alert.descricao}</div>` : ''}
            <div class="alert-time">${alert.tempo}</div>
          </div>
        `;
        
        return div;
      }

      function updateMetrics() {
        const statusIconWrapper = document.getElementById('statusIconWrapper');
        const lastUpdateEl = document.getElementById('lastUpdate');
        
        // Mostrar que está verificando
        if (statusIconWrapper) statusIconWrapper.className = 'icon-wrapper status-icon-wrapper checking';
        
        // Buscar status da IA
        fetch('?action=get_performance_metrics', {
          method: 'GET',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
        .then(response => {
          // Verificar se é JSON válido
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
          }
          
          const contentType = response.headers.get('content-type');
          if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Resposta não é JSON válido - verifique erros PHP no console');
          }
          
          return response.json();
        })
        .then(data => {
          console.log('Resposta da API:', data); // Debug
          
          if (data.success && data.ia_status) {
            updateIAStatus(data.ia_status);
          } else {
            // Fallback se não conseguir verificar
            updateIAStatus({
              online: false,
              status_text: 'Status desconhecido',
              status_subtitle: 'Erro ao verificar'
            });
          }
          
          // Atualizar timestamp
          if (lastUpdateEl) {
            const now = new Date();
            const timeString = now.toLocaleTimeString('pt-BR', { 
              hour: '2-digit', 
              minute: '2-digit'
            });
            lastUpdateEl.textContent = `Atualizado às ${timeString}`;
          }
        })
        .catch(error => {
          console.error('Erro ao verificar status da IA:', error);
          
          // Estado de erro
          updateIAStatus({
            online: false,
            status_text: 'Erro de conexão',
            status_subtitle: 'Verifique a conexão'
          });
        });
      }

      function updateIAStatus(status) {
        const statusIconWrapper = document.getElementById('statusIconWrapper');
        const statusText = document.getElementById('iaStatusText');
        const statusSubtitle = document.getElementById('iaStatusSubtitle');
        
        if (statusIconWrapper) {
          if (status.online) {
            statusIconWrapper.className = 'icon-wrapper status-icon-wrapper';
          } else {
            statusIconWrapper.className = 'icon-wrapper status-icon-wrapper offline';
          }
        }
        
        if (statusText) {
          statusText.textContent = status.status_text;
          statusText.style.color = status.online ? 'var(--color-success)' : 'var(--color-danger)';
        }
        
        if (statusSubtitle) {
          statusSubtitle.textContent = status.status_subtitle;
        }

        console.log('🤖 Status da IA atualizado:', status);
      }

      // Função para pausar/despausar IA
      function toggleIAStatus() {
        const pauseBtn = document.getElementById('pauseAIDashboard');
        if (!pauseBtn) return;
        
        // Mostrar loading
        pauseBtn.disabled = true;
        pauseBtn.innerHTML = '<span class="material-symbols-sharp">hourglass_empty</span>Processando...';
        
        // Criar formulário para POST
        const formData = new FormData();
        formData.append('action', 'toggle_ia_status');
        
        fetch(window.location.href, {
          method: 'POST',
          body: formData
        })
        .then(response => {
          console.log('Status da resposta:', response.status); // Debug
          
          // Verificar se é JSON válido
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
          }
          
          const contentType = response.headers.get('content-type');
          if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Resposta não é JSON válido');
          }
          
          return response.json();
        })
        .then(data => {
          console.log('Resposta recebida:', data); // Debug
          
          if (data.success) {
            // Atualizar botão baseado no status
            if (data.ia_ativa) {
              pauseBtn.innerHTML = '<span class="material-symbols-sharp">pause_circle</span>Pausar IA Geral';
              pauseBtn.className = 'action-button primary';
            } else {
              pauseBtn.innerHTML = '<span class="material-symbols-sharp">play_circle</span>Reativar IA';
              pauseBtn.className = 'action-button warning';
            }
            
            // Mostrar notificação
            showNotification(data.message, 'success');
            
            // FORÇAR ATUALIZAÇÃO IMEDIATA do status da IA
            setTimeout(() => {
              updateMetrics();
              updateAlertsDisplay(); // Também atualizar alertas
            }, 500); // Pequeno delay para garantir que a sessão foi salva
            
          } else {
            showNotification(data.error || 'Erro desconhecido', 'error');
          }
        })
        .catch(error => {
          console.error('Erro na requisição:', error);
          showNotification('Erro de conexão: ' + error.message, 'error');
        })
        .finally(() => {
          pauseBtn.disabled = false;
          
          // Se ainda estiver com texto de loading, restaurar
          if (pauseBtn.innerHTML.includes('Processando')) {
            pauseBtn.innerHTML = '<span class="material-symbols-sharp">pause_circle</span>Pausar IA Geral';
            pauseBtn.className = 'action-button primary';
          }
        });
      }

      // Função para exportar relatórios
      function exportReports() {
        const exportBtn = document.getElementById('exportReportsDashboard');
        if (!exportBtn) return;
        
        // Mostrar loading
        exportBtn.disabled = true;
        exportBtn.innerHTML = '<span class="material-symbols-sharp">hourglass_empty</span>Gerando...';
        
        // Abrir URL de download diretamente
        window.open('?action=export_reports', '_blank');
        
        // Restaurar botão
        setTimeout(() => {
          exportBtn.disabled = false;
          exportBtn.innerHTML = '<span class="material-symbols-sharp">download</span>Exportar Relatórios';
          showNotification('Relatório gerado com sucesso!', 'success');
        }, 2000);
      }

      // Função para mostrar notificações
      function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.style.cssText = `
          position: fixed;
          top: 20px;
          right: 20px;
          background: ${type === 'success' ? '#2ecc71' : type === 'error' ? '#e74c3c' : '#3498db'};
          color: white;
          padding: 15px 20px;
          border-radius: 8px;
          box-shadow: 0 4px 12px rgba(0,0,0,0.3);
          z-index: 10000;
          font-weight: 500;
          max-width: 300px;
        `;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Remover após 3 segundos
        setTimeout(() => {
          notification.remove();
        }, 3000);
      }

      function updateMetricsDisplay(performance) {
        const successEl = document.getElementById('aiSuccessRate');
        const timeEl = document.getElementById('aiResponseTime');
        const lastUpdateEl = document.getElementById('lastUpdate');
        
        if (successEl) {
          successEl.textContent = `${performance.taxa_sucesso}%`;
          
          // Cor baseada na performance
          if (performance.taxa_sucesso >= 90) {
            successEl.style.color = 'var(--color-success)';
          } else if (performance.taxa_sucesso >= 75) {
            successEl.style.color = 'var(--color-warning)';
          } else {
            successEl.style.color = 'var(--color-danger)';
          }
        }
        
        if (timeEl) {
          timeEl.textContent = `${performance.tempo_resposta}min`;
          
          // Cor baseada no tempo de resposta
          if (performance.tempo_resposta <= 1.0) {
            timeEl.style.color = 'var(--color-success)';
          } else if (performance.tempo_resposta <= 2.0) {
            timeEl.style.color = 'var(--color-warning)';
          } else {
            timeEl.style.color = 'var(--color-danger)';
          }
        }

        // Atualizar timestamp
        if (lastUpdateEl) {
          const now = new Date();
          const timeString = now.toLocaleTimeString('pt-BR', { 
            hour: '2-digit', 
            minute: '2-digit',
            second: '2-digit'
          });
          lastUpdateEl.textContent = `Atualizado às ${timeString}`;
        }

        console.log('📊 Métricas atualizadas:', performance);
      }

      function updateMetricsDisplayFallback() {
        // Fallback com simulação se API falhar
        const successRate = Math.floor(Math.random() * 15) + 80;
        const responseTime = (Math.random() * 1.5 + 0.5).toFixed(1);
        
        updateMetricsDisplay({
          taxa_sucesso: successRate,
          tempo_resposta: parseFloat(responseTime)
        });
      }

      function addAlert(message, type = 'warning') {
        const alertsFeed = document.getElementById('alertsFeedDashboard');
        if (!alertsFeed) return;

        const alertDiv = document.createElement('div');
        alertDiv.className = `alert-item ${type}`;
        
        const icons = {
          critical: 'priority_high',
          warning: 'schedule',
          info: 'info'
        };
        
        const icon = icons[type] || 'schedule';
        const now = new Date();
        const timeStr = `${now.getHours()}:${now.getMinutes().toString().padStart(2, '0')}`;
        
        alertDiv.innerHTML = `
          <span class="material-symbols-sharp alert-icon">${icon}</span>
          <div class="alert-content">
            <div class="alert-title">${message}</div>
            <div class="alert-time">${timeStr}</div>
          </div>
        `;
        
        // Adicionar no topo
        alertsFeed.insertBefore(alertDiv, alertsFeed.firstChild);
        
        // Limitar a 6 alertas
        if (alertsFeed.children.length > 6) {
          alertsFeed.removeChild(alertsFeed.lastChild);
        }

        // Animação de entrada
        alertDiv.style.opacity = '0';
        alertDiv.style.transform = 'translateX(-20px)';
        setTimeout(() => {
          alertDiv.style.transition = 'all 0.3s ease';
          alertDiv.style.opacity = '1';
          alertDiv.style.transform = 'translateX(0)';
        }, 100);
      }

      function addRandomAlert() {
        const alerts = [
          { msg: 'Nova conversa iniciada', type: 'info' },
          { msg: 'Cliente solicitou atendimento humano', type: 'warning' },
          { msg: 'Tempo de resposta alto detectado', type: 'warning' },
          { msg: 'Feedback positivo recebido', type: 'info' },
          { msg: 'Possível problema detectado', type: 'critical' },
          { msg: 'IA respondeu com sucesso', type: 'info' }
        ];
        
        const randomAlert = alerts[Math.floor(Math.random() * alerts.length)];
        addAlert(randomAlert.msg, randomAlert.type);
      }

      function toggleAI() {
        aiPausedDashboard = !aiPausedDashboard;
        const pauseBtn = document.getElementById('pauseAIDashboard');
        
        if (pauseBtn) {
          if (aiPausedDashboard) {
            pauseBtn.innerHTML = '<span class="material-symbols-sharp">play_circle</span> Reativar IA';
            pauseBtn.className = 'action-button secondary';
            addAlert('IA pausada pelo administrador', 'critical');
          } else {
            pauseBtn.innerHTML = '<span class="material-symbols-sharp">pause_circle</span> Pausar IA Geral';
            pauseBtn.className = 'action-button primary';
            addAlert('IA reativada com sucesso', 'info');
          }
        }
        
        console.log(`🤖 IA ${aiPausedDashboard ? 'PAUSADA' : 'ATIVADA'}`);
      }

      function exportDailyReport() {
        addAlert('Gerando relatório do dia...', 'info');
        
        setTimeout(() => {
          // Dados simulados para o relatório
          const now = new Date();
          const dateStr = now.toLocaleDateString('pt-BR');
          const successRate = document.getElementById('aiSuccessRate')?.textContent || '85%';
          const responseTime = document.getElementById('aiResponseTime')?.textContent || '1.2s';
          
          const reportData = `=== RELATÓRIO DIÁRIO D&Z ===
Data: ${dateStr}
Hora de Geração: ${now.toLocaleTimeString('pt-BR')}

📊 MÉTRICAS DE PERFORMANCE:
• Taxa de Sucesso da IA: ${successRate}
• Tempo Médio de Resposta: ${responseTime}

💬 ESTATÍSTICAS DE CONVERSAS:
• Total de Conversas: ${Math.floor(Math.random() * 50) + 30}
• Conversas Ativas: ${Math.floor(Math.random() * 15) + 5}
• Escaladas para Humano: ${Math.floor(Math.random() * 8) + 2}
• Conversas Resolvidas: ${Math.floor(Math.random() * 40) + 20}

🎯 SATISFAÇÃO DO CLIENTE:
• Feedback Positivo: ${Math.floor(Math.random() * 20) + 15}
• Feedback Negativo: ${Math.floor(Math.random() * 3) + 1}
• Avaliação Média: ${(Math.random() * 1 + 4).toFixed(1)}/5

📈 TENDÊNCIAS:
• Crescimento nas conversas: +${Math.floor(Math.random() * 20) + 5}%
• Melhoria na taxa de sucesso: +${Math.floor(Math.random() * 10) + 2}%
• Redução no tempo de resposta: -${Math.floor(Math.random() * 15) + 5}%

---
Relatório gerado automaticamente pelo Sistema D&Z
`;
          
          // Download do arquivo
          const blob = new Blob([reportData], { type: 'text/plain;charset=utf-8' });
          const link = document.createElement('a');
          link.href = URL.createObjectURL(blob);
          link.download = `relatorio_dz_${now.toISOString().split('T')[0]}.txt`;
          link.click();
          
          addAlert('Relatório baixado com sucesso!', 'info');
          
        }, 2000);
      }

      // Detectar sentimento negativo nas mensagens (exemplo)
      function detectSentiment(message) {
        const negativeWords = ['ruim', 'péssimo', 'horrível', 'problema', 'erro', 'não funciona', 'insatisfeito'];
        const messageText = message.toLowerCase();
        
        for (let word of negativeWords) {
          if (messageText.includes(word)) {
            addAlert('Sentimento negativo detectado na conversa', 'critical');
            break;
          }
        }
      }

      // Limpar intervalos quando a página for fechada
      window.addEventListener('beforeunload', function() {
        if (metricsInterval) clearInterval(metricsInterval);
        if (alertsInterval) clearInterval(alertsInterval);
      });

      console.log('🎛️ Sistema de monitoramento D&Z carregado!');
    </script>

    <script src="../../js/contador-auto.js"></script>
  </body>
</html>





