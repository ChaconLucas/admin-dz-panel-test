<?php
/**
 * D&Z Chat System - Backend Completo Consolidado
 * Arquivo único contendo: configurações, conexão, classes e API endpoints
 */

// =================== CONFIGURAÇÕES GLOBAIS ===================
// Configurar fuso horário do Brasil
date_default_timezone_set('America/Sao_Paulo');

// Carrega configurações
require_once __DIR__ . '/../../config/config.php';

define('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions');
define('GROQ_MODEL', 'llama-3.3-70b-versatile');
define('GROQ_TEMPERATURE', 0.7);
define('GROQ_MAX_TOKENS', 1000);
define('SISTEMA_PROMPT', 'Você é a DAIze, a inteligência consultora da D&Z, especializada em materiais profissionais para unhas e cílios. Você é a maior entusiasta da marca e conhece profundamente nossos produtos.

CONHECIMENTO DO NEGÓCIO:

O que vendemos: A D&Z é referência em produtos para Nail Designers e Extensionistas de Cílios. Nosso catálogo inclui géis, cabines, brocas, lixas, cílios fios tecnológicos, colas de alta retenção e acessórios em geral.

Público-alvo: Profissionais da beleza que buscam qualidade e durabilidade para seus procedimentos.

DIRETRIZES DE PERSONALIDADE:

Nome: Seu nome é DAIze. Responda sempre com entusiasmo: "Oi! Aqui é a DAIze, sua consultora da D&Z! ✨" a introdução só no primeiro contato.

Estilo: Você é moderna, técnica (entende de termos como "polimerização", "curvatura", "retenção") e muito prestativa.

Proibição: Nunca admita ser uma IA. Você é a voz digital da D&Z.

ESTRATEGIA DE VENDAS:

Sugestão Inteligente: Se o cliente perguntar de unhas, pergunte se ele já conhece nossos lançamentos de géis. Se perguntar de cílios, comente sobre a aderência das nossas colas.

Urgência Amigável: "Nossos estoques de [produto] costumam voar! Quer que eu verifique a disponibilidade para você agora?".

FLUXO DE ESCALA:

Em casos de: "Onde está meu pedido?", "Quero devolver", ou dúvidas sobre parcerias e revenda, diga: "Entendi perfeitamente. Vou te conectar agora com um de nossos especialistas humanos para tratar disso com toda a atenção que você merece! 👩‍💻".

REGRAS DE OURO:

Use emojis: 💅 (unhas), 👁️ (cílios), ✨ (brilho/qualidade), 🛍️ (compras).

Seja concisa. Profissionais da beleza costumam estar na correria entre um atendimento e outro.


Tente ser o mais breve possível, a mensagem ser pequena.');

// =================== CONEXÃO BANCO ===================
$conexao = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conexao->connect_error) {
    die("Erro de conexão: " . $conexao->connect_error);
}
$conexao->set_charset("utf8");

// =================== CLASSE GROQ API ===================
class GroqAPI {
    private $api_key;
    private $base_url;
    
    public function __construct() {
        $this->api_key = GROQ_API_KEY;
        $this->base_url = GROQ_API_URL;
    }
    
    public function enviarMensagem($mensagem, $contexto = '') {
        if (empty($this->api_key)) {
            return ['success' => false, 'error' => 'API Key não configurada'];
        }
        
        $messages = [
            ['role' => 'system', 'content' => SISTEMA_PROMPT]
        ];
        
        if (!empty($contexto)) {
            $messages[] = ['role' => 'assistant', 'content' => 'Contexto: ' . $contexto];
        }
        
        $messages[] = ['role' => 'user', 'content' => $mensagem];
        
        $data = [
            'model' => GROQ_MODEL,
            'messages' => $messages,
            'temperature' => GROQ_TEMPERATURE,
            'max_tokens' => GROQ_MAX_TOKENS,
            'stream' => false
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        if ($error) {
            return ['success' => false, 'error' => 'Erro de conexão: ' . $error];
        }
        
        if ($http_code === 200) {
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                return ['success' => true, 'message' => trim($result['choices'][0]['message']['content'])];
            }
        }
        
        $error_data = json_decode($response, true);
        return ['success' => false, 'error' => "HTTP $http_code: " . ($error_data['error']['message'] ?? 'Erro desconhecido')];
    }
}

// =================== CLASSE CHAT MANAGER ===================
class ChatManager {
    private $conexao;
    private $groq;
    
    public function __construct($conexao) {
        $this->conexao = $conexao;
        $this->groq = new GroqAPI();
    }
    
    public function obterConversas($status = null) {
        $sql = "SELECT c.*, 
                       COALESCE(NULLIF(c.usuario_nome, ''), CONCAT('Cliente #', c.id)) as usuario_nome,
                       (SELECT conteudo FROM mensagens WHERE conversa_id = c.id ORDER BY timestamp DESC LIMIT 1) as ultima_mensagem,
                       (SELECT COUNT(*) FROM mensagens WHERE conversa_id = c.id AND lida = FALSE AND remetente != 'admin') as nao_lidas,
                       (SELECT COUNT(*) FROM mensagens WHERE conversa_id = c.id) as total_mensagens
                FROM conversas c
                ORDER BY c.created_at DESC";
        
        if ($status) {
            $sql = str_replace("ORDER BY", "WHERE c.status = ? ORDER BY", $sql);
            $stmt = $this->conexao->prepare($sql);
            $stmt->bind_param("s", $status);
        } else {
            $stmt = $this->conexao->prepare($sql);
        }
        
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    public function obterMensagens($conversa_id) {
        // Tentar com remetente_nome primeiro, se falhar usar query simples
        try {
            $sql = "SELECT *, COALESCE(remetente_nome, 
                           CASE 
                               WHEN remetente = 'admin' THEN 'Admin'
                               WHEN remetente = 'ia' THEN 'DAIze'
                               ELSE 'Cliente'
                           END) as nome_remetente 
                    FROM mensagens 
                    WHERE conversa_id = ? 
                    ORDER BY timestamp ASC";
            $stmt = $this->conexao->prepare($sql);
            $stmt->bind_param("i", $conversa_id);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            // Fallback se a coluna remetente_nome não existe
            $sql = "SELECT * FROM mensagens WHERE conversa_id = ? ORDER BY timestamp ASC";
            $stmt = $this->conexao->prepare($sql);
            $stmt->bind_param("i", $conversa_id);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    public function processarMensagemUsuario($conversa_id, $mensagem) {
        $sql = "INSERT INTO mensagens (conversa_id, remetente, conteudo) VALUES (?, 'usuario', ?)";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param("is", $conversa_id, $mensagem);
        $stmt->execute();
        
        $contexto = $this->obterContextoConversa($conversa_id);
        $resposta_ia = $this->groq->enviarMensagem($mensagem, $contexto);
        
        if ($resposta_ia['success']) {
            $resposta_texto = $resposta_ia['message'];
        } else {
            $resposta_texto = "Obrigado por sua mensagem! Nosso sistema está com alta demanda. Um atendente entrará em contato em breve para ajudá-lo. 👨‍💼";
        }
        
        $sql = "INSERT INTO mensagens (conversa_id, remetente, conteudo) VALUES (?, 'ia', ?)";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param("is", $conversa_id, $resposta_texto);
        $stmt->execute();
        
        return $resposta_texto;
    }
    
    public function obterContextoConversa($conversa_id, $limite = 6) {
        $sql = "SELECT remetente, conteudo FROM mensagens WHERE conversa_id = ? ORDER BY timestamp DESC LIMIT ?";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param("ii", $conversa_id, $limite);
        $stmt->execute();
        $mensagens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $contexto = "";
        foreach (array_reverse($mensagens) as $msg) {
            $remetente = $msg['remetente'] === 'usuario' ? 'Cliente' : 'Atendente';
            $contexto .= "$remetente: " . $msg['conteudo'] . "\n";
        }
        
        return $contexto;
    }
    
    public function enviarMensagemAdmin($conversa_id, $mensagem, $admin_id, $admin_nome = null) {
        // Se não foi fornecido nome, tenta usar da sessão
        if (!$admin_nome && isset($_SESSION['usuario_nome'])) {
            $admin_nome = $_SESSION['usuario_nome'];
        }
        
        // Se ainda não tem nome, usa 'Admin' como fallback
        $admin_nome = $admin_nome ?: 'Admin';
        
        /* 
         * IMPORTANTE: Execute este SQL no seu banco de dados antes de usar:
         * ALTER TABLE mensagens ADD COLUMN remetente_nome VARCHAR(255) NULL;
         */
        
        // Tentar inserir com remetente_nome, se falhar usar método antigo
        try {
            $sql = "INSERT INTO mensagens (conversa_id, remetente, conteudo, remetente_nome) VALUES (?, 'admin', ?, ?)";
            $stmt = $this->conexao->prepare($sql);
            $stmt->bind_param("iss", $conversa_id, $mensagem, $admin_nome);
            return $stmt->execute();
        } catch (Exception $e) {
            // Fallback se a coluna ainda não existe
            $sql = "INSERT INTO mensagens (conversa_id, remetente, conteudo) VALUES (?, 'admin', ?)";
            $stmt = $this->conexao->prepare($sql);
            $stmt->bind_param("is", $conversa_id, $mensagem);
            return $stmt->execute();
        }
    }
    
    public function escalarParaHumano($conversa_id) {
        $sql = "UPDATE conversas SET status = 'aguardando_humano' WHERE id = ?";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param("i", $conversa_id);
        return $stmt->execute();
    }
    
    public function resolverConversa($conversa_id) {
        $sql = "UPDATE conversas SET status = 'resolvida' WHERE id = ?";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param("i", $conversa_id);
        return $stmt->execute();
    }
    
    public function obterEstatisticas() {
        $stats = [];
        
        $result = $this->conexao->query("SELECT COUNT(*) as total FROM conversas");
        $stats['total_conversas'] = $result->fetch_assoc()['total'];
        
        $result = $this->conexao->query("SELECT COUNT(*) as total FROM mensagens WHERE DATE(timestamp) = CURDATE()");
        $stats['mensagens_hoje'] = $result->fetch_assoc()['total'];
        
        $result = $this->conexao->query("SELECT COUNT(*) as total FROM conversas WHERE status = 'ativa'");
        $stats['conversas_ativas'] = $result->fetch_assoc()['total'];
        
        $result = $this->conexao->query("SELECT COUNT(*) as total FROM mensagens WHERE lida = FALSE AND remetente != 'admin'");
        $stats['nao_lidas'] = $result->fetch_assoc()['total'];
        
        return $stats;
    }
    
    public function marcarComoNaoLida($conversa_id) {
        // Marcar todas as mensagens da conversa como não lidas (exceto do admin)
        $sql = "UPDATE mensagens SET lida = FALSE WHERE conversa_id = ? AND remetente != 'admin'";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param("i", $conversa_id);
        return $stmt->execute();
    }
    
    public function arquivarConversa($conversa_id) {
        // Marcar conversa como arquivada (pode criar uma nova coluna 'arquivada' ou usar status)
        $sql = "UPDATE conversas SET status = 'arquivada' WHERE id = ?";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param("i", $conversa_id);
        return $stmt->execute();
    }
    
    public function deletarConversa($conversa_id) {
        // Deletar mensagens primeiro (por causa da chave estrangeira)
        $sql1 = "DELETE FROM mensagens WHERE conversa_id = ?";
        $stmt1 = $this->conexao->prepare($sql1);
        $stmt1->bind_param("i", $conversa_id);
        $resultado1 = $stmt1->execute();
        
        // Depois deletar a conversa
        if ($resultado1) {
            $sql2 = "DELETE FROM conversas WHERE id = ?";
            $stmt2 = $this->conexao->prepare($sql2);
            $stmt2->bind_param("i", $conversa_id);
            return $stmt2->execute();
        }
        
        return false;
    }
    
    public function marcarTodasLidas() {
        // Marcar todas as mensagens como lidas (exceto do admin)
        $sql = "UPDATE mensagens SET lida = TRUE WHERE remetente != 'admin'";
        $stmt = $this->conexao->prepare($sql);
        return $stmt->execute();
    }
}

// =================== CLASSE AUTH ===================
class AuthManager {
    private $conexao;
    
    public function __construct($conexao) {
        $this->conexao = $conexao;
    }
    
    public function validarLogin($email, $senha) {
        $sql = "SELECT id, nome, email FROM usuarios WHERE email = ? AND senha = MD5(?)";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param("ss", $email, $senha);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            return $result->fetch_assoc();
        }
        return false;
    }
    
    public function criarSessao($usuario) {
        session_start();
        $_SESSION['usuario_logado'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_email'] = $usuario['email'];
    }
    
    public function verificarSessao() {
        session_start();
        return isset($_SESSION['usuario_logado']);
    }
    
    public function destruirSessao() {
        session_start();
        session_destroy();
    }
}

// =================== INICIALIZAÇÃO ===================
$chat_manager = new ChatManager($conexao);
$auth_manager = new AuthManager($conexao);

// =================== API ENDPOINTS ===================
if (isset($_GET['api']) || isset($_POST['api'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    $endpoint = $_GET['endpoint'] ?? $_POST['endpoint'] ?? '';
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    switch ($endpoint) {
        case 'client':
            handleClientAPI($chat_manager, $action);
            break;
        case 'admin':
            handleAdminAPI($chat_manager, $action);
            break;
        case 'auth':
            handleAuthAPI($auth_manager, $action);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Endpoint não encontrado']);
            break;
    }
    exit;
}

// =================== HANDLERS API ===================
function handleClientAPI($chat_manager, $action) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        switch ($action) {
            case 'start_conversation':
                $nome = trim($input['nome'] ?? '');
                $email = trim($input['email'] ?? '');
                $mensagem = trim($input['mensagem'] ?? '');
                
                if (!$nome || !$email || !$mensagem) {
                    echo json_encode(['success' => false, 'error' => 'Por favor, preencha todos os campos']);
                    exit;
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'error' => 'Email inválido']);
                    exit;
                }
                
                try {
                    global $conexao;
                    $sql = "INSERT INTO conversas (usuario_nome, usuario_email, status) VALUES (?, ?, 'ativa')";
                    $stmt = $conexao->prepare($sql);
                    $stmt->bind_param("ss", $nome, $email);
                    $stmt->execute();
                    
                    $conversa_id = $conexao->insert_id;
                    
                    if ($conversa_id) {
                        $resposta_ia = $chat_manager->processarMensagemUsuario($conversa_id, $mensagem);
                        echo json_encode([
                            'success' => true,
                            'conversa_id' => $conversa_id,
                            'resposta' => $resposta_ia
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Erro ao criar conversa']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                break;
                
            case 'send_message':
                $conversa_id = intval($input['conversa_id'] ?? 0);
                $mensagem = trim($input['mensagem'] ?? '');
                
                if (!$conversa_id || !$mensagem) {
                    echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
                    exit;
                }
                
                try {
                    global $conexao;
                    $sql = "SELECT id, status FROM conversas WHERE id = ?";
                    $stmt = $conexao->prepare($sql);
                    $stmt->bind_param("i", $conversa_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $conversa = $result->fetch_assoc();
                    
                    if (!$conversa) {
                        echo json_encode(['success' => false, 'error' => 'Conversa não encontrada']);
                        exit;
                    }
                    
                    if ($conversa['status'] === 'resolvida') {
                        echo json_encode([
                            'success' => true,
                            'resposta' => 'Esta conversa foi finalizada. Se precisar de mais ajuda, inicie uma nova conversa.'
                        ]);
                        exit;
                    }
                    
                    if ($conversa['status'] === 'aguardando_humano') {
                        $sql = "INSERT INTO mensagens (conversa_id, remetente, conteudo) VALUES (?, 'usuario', ?)";
                        $stmt = $conexao->prepare($sql);
                        $stmt->bind_param("is", $conversa_id, $mensagem);
                        $stmt->execute();
                        
                        echo json_encode([
                            'success' => true,
                            'resposta' => 'Sua mensagem foi recebida. Um atendente humano responderá em breve. 👨‍💼'
                        ]);
                        exit;
                    }
                    
                    $resposta_ia = $chat_manager->processarMensagemUsuario($conversa_id, $mensagem);
                    echo json_encode(['success' => true, 'resposta' => $resposta_ia]);
                    
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                break;
        }
    }
}

function handleAdminAPI($chat_manager, $action) {
    session_start();
    
    if (!isset($_SESSION['usuario_logado'])) {
        echo json_encode(['success' => false, 'error' => 'Não autorizado']);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        switch ($action) {
            case 'send_admin_message':
                $conversa_id = intval($input['conversa_id'] ?? 0);
                $mensagem = trim($input['mensagem'] ?? '');
                $admin_id = $_SESSION['usuario_logado'];
                
                if (!$conversa_id || !$mensagem) {
                    echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
                    exit;
                }
                
                $admin_nome = $_SESSION['usuario_nome'] ?? 'Admin';
                $resultado = $chat_manager->enviarMensagemAdmin($conversa_id, $mensagem, $admin_id, $admin_nome);
                echo json_encode(['success' => $resultado]);
                break;
                
            case 'escalar_humano':
                $conversa_id = intval($input['conversa_id'] ?? 0);
                if (!$conversa_id) {
                    echo json_encode(['success' => false, 'error' => 'ID da conversa é obrigatório']);
                    exit;
                }
                
                $resultado = $chat_manager->escalarParaHumano($conversa_id);
                $mensagem_sistema = "Esta conversa foi escalada para atendimento humano. Um especialista entrará em contato em breve.";
                $chat_manager->enviarMensagemAdmin($conversa_id, $mensagem_sistema, 'sistema');
                echo json_encode(['success' => $resultado]);
                break;
                
            case 'resolver_conversa':
                $conversa_id = intval($input['conversa_id'] ?? 0);
                if (!$conversa_id) {
                    echo json_encode(['success' => false, 'error' => 'ID da conversa é obrigatório']);
                    exit;
                }
                
                $resultado = $chat_manager->resolverConversa($conversa_id);
                $mensagem_encerramento = "Esta conversa foi marcada como resolvida. Obrigado por entrar em contato conosco!";
                $chat_manager->enviarMensagemAdmin($conversa_id, $mensagem_encerramento, 'sistema');
                echo json_encode(['success' => $resultado]);
                break;
                
            case 'marcar_nao_lida':
                $conversa_id = intval($input['conversa_id'] ?? 0);
                if (!$conversa_id) {
                    echo json_encode(['success' => false, 'error' => 'ID da conversa é obrigatório']);
                    exit;
                }
                
                $resultado = $chat_manager->marcarComoNaoLida($conversa_id);
                echo json_encode(['success' => $resultado]);
                break;
                
            case 'arquivar_conversa':
                $conversa_id = intval($input['conversa_id'] ?? 0);
                if (!$conversa_id) {
                    echo json_encode(['success' => false, 'error' => 'ID da conversa é obrigatório']);
                    exit;
                }
                
                $resultado = $chat_manager->arquivarConversa($conversa_id);
                echo json_encode(['success' => $resultado]);
                break;
                
            case 'deletar_conversa':
                $conversa_id = intval($input['conversa_id'] ?? 0);
                if (!$conversa_id) {
                    echo json_encode(['success' => false, 'error' => 'ID da conversa é obrigatório']);
                    exit;
                }
                
                $resultado = $chat_manager->deletarConversa($conversa_id);
                echo json_encode(['success' => $resultado]);
                break;
                
            case 'marcar_todas_lidas':
                $resultado = $chat_manager->marcarTodasLidas();
                echo json_encode(['success' => $resultado]);
                break;
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        switch ($action) {
            case 'get_conversations':
                $status = $_GET['status'] ?? null;
                $conversas = $chat_manager->obterConversas($status);
                echo json_encode($conversas);
                break;
                
            case 'get_messages':
                $conversa_id = intval($_GET['conversa_id'] ?? 0);
                if (!$conversa_id) {
                    echo json_encode([]);
                    exit;
                }
                
                $mensagens = $chat_manager->obterMensagens($conversa_id);
                
                global $conexao;
                $sql = "UPDATE mensagens SET lida = TRUE WHERE conversa_id = ? AND remetente != 'admin'";
                $stmt = $conexao->prepare($sql);
                $stmt->bind_param("i", $conversa_id);
                $stmt->execute();
                
                echo json_encode($mensagens);
                break;
                
            case 'get_stats':
                $stats = $chat_manager->obterEstatisticas();
                echo json_encode($stats);
                break;
                
            case 'mark_messages_read':
                $conversa_id = intval($input['conversa_id'] ?? 0);
                if ($conversa_id > 0) {
                    $sql = "UPDATE mensagens SET lida = TRUE WHERE conversa_id = ? AND remetente != 'admin'";
                    $stmt = $chat_manager->conexao->prepare($sql);
                    $stmt->bind_param("i", $conversa_id);
                    
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Erro ao marcar mensagens como lidas']);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'ID da conversa inválido']);
                }
                break;
        }
    }
}

function handleAuthAPI($auth_manager, $action) {
    switch ($action) {
        case 'login':
            $input = json_decode(file_get_contents('php://input'), true);
            $email = trim($input['email'] ?? '');
            $senha = trim($input['senha'] ?? '');
            
            if (!$email || !$senha) {
                echo json_encode(['success' => false, 'error' => 'Email e senha são obrigatórios']);
                exit;
            }
            
            $usuario = $auth_manager->validarLogin($email, $senha);
            if ($usuario) {
                $auth_manager->criarSessao($usuario);
                echo json_encode(['success' => true, 'usuario' => $usuario]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Email ou senha incorretos']);
            }
            break;
            
        case 'logout':
            $auth_manager->destruirSessao();
            echo json_encode(['success' => true]);
            break;
            
        case 'verify':
            $logado = $auth_manager->verificarSessao();
            echo json_encode(['success' => true, 'logado' => $logado]);
            break;
    }
}
?>