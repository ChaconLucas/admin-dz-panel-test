<?php
/**
 * SISTEMA DE LOGS AUTOMÁTICO PARA ADMINS
 * Incluir este arquivo em todas as páginas administrativas
 */

// Configurar fuso horário do Brasil
date_default_timezone_set('America/Sao_Paulo');

// Incluir conexão se ainda não foi incluída
if (!isset($conexao)) {
    require_once __DIR__ . '/../../PHP/conexao.php';
}

/**
 * Registrar log de atividade do administrador
 */
function registrar_log($conexao, $mensagem) {
    try {
        // Capturar dados da sessão
        $admin_id = $_SESSION['usuario_logado'] ?? 0;
        $admin_nome = $_SESSION['nome_usuario'] ?? 'Admin';
        
        // Capturar IP do usuário
        $ip_address = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip_address = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        
        // Inserir no banco
        $stmt = $conexao->prepare("INSERT INTO admin_logs (admin_id, admin_nome, acao, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $admin_id, $admin_nome, $mensagem, $ip_address);
        $stmt->execute();
        $stmt->close();
        
    } catch (Exception $e) {
        // Silencioso se tabela não existir ainda
        error_log("Erro ao registrar log: " . $e->getMessage());
    }
}

/**
 * Capturar automaticamente dados de POST/GET para logs detalhados
 */
function capturar_dados_acao() {
    $dados = [];
    
    // Capturar ação principal - prioridade para ações específicas
    if (isset($_POST['action'])) {
        $dados['acao'] = $_POST['action'];
    } elseif (isset($_GET['action'])) {
        $dados['acao'] = $_GET['action'];
    } elseif (isset($_GET['delete'])) {
        $dados['acao'] = 'delete';
        $dados['id'] = $_GET['delete'];
    } elseif (isset($_POST['btn_login'])) {
        $dados['acao'] = 'login';
    } elseif (isset($_POST['create_usuario'])) {
        $dados['acao'] = 'create_usuario';
    } elseif (isset($_POST['update_usuario'])) {
        $dados['acao'] = 'update_usuario';
    } elseif (isset($_POST['delete_usuario'])) {
        $dados['acao'] = 'delete_usuario';
    }
    
    // Auto-detectar ação baseada no contexto
    if (empty($dados['acao'])) {
        // Se há POST com ID, é uma atualização
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['id']) || isset($_POST['produto_id']) || isset($_POST['usuario_id']))) {
            // Detectar tipo específico baseado na URL
            $url = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($url, 'product') !== false || strpos($url, 'addproduct') !== false) {
                $dados['acao'] = 'update_product';
            } elseif (strpos($url, 'customer') !== false || strpos($url, 'cliente') !== false) {
                $dados['acao'] = 'update_cliente';
            } elseif (strpos($url, 'usuario') !== false) {
                $dados['acao'] = 'update_usuario';
            } else {
                $dados['acao'] = 'update';
            }
        }
        // Se há POST sem ID, é criação
        elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $url = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($url, 'product') !== false || strpos($url, 'addproduct') !== false) {
                $dados['acao'] = 'add_product';
            } elseif (strpos($url, 'customer') !== false || strpos($url, 'cliente') !== false) {
                $dados['acao'] = 'add_cliente';
            } elseif (strpos($url, 'usuario') !== false) {
                $dados['acao'] = 'create_usuario';
            } else {
                $dados['acao'] = 'create';
            }
        }
    }
    
    // Capturar dados específicos com múltiplas variações
    $dados['nome'] = $_POST['nome'] ?? $_POST['nome_produto'] ?? $_POST['produto_nome'] ?? $_POST['name'] ?? '';
    $dados['email'] = $_POST['email'] ?? $_POST['email_cliente'] ?? '';
    $dados['id'] = $_POST['id'] ?? $_POST['produto_id'] ?? $_POST['usuario_id'] ?? $_POST['cliente_id'] ?? $_GET['id'] ?? '';
    $dados['status'] = $_POST['status'] ?? $_POST['produto_status'] ?? '';
    $dados['preco'] = $_POST['preco'] ?? $_POST['valor'] ?? $_POST['price'] ?? '';
    $dados['estoque'] = $_POST['estoque'] ?? $_POST['quantidade'] ?? $_POST['qty'] ?? '';
    $dados['categoria'] = $_POST['categoria'] ?? $_POST['category'] ?? '';
    $dados['descricao'] = $_POST['descricao'] ?? $_POST['description'] ?? '';
    
    // Dados de cliente específicos
    $dados['telefone'] = $_POST['telefone'] ?? $_POST['phone'] ?? '';
    $dados['endereco'] = $_POST['endereco'] ?? $_POST['address'] ?? '';
    $dados['cidade'] = $_POST['cidade'] ?? $_POST['city'] ?? '';
    $dados['cep'] = $_POST['cep'] ?? $_POST['zipcode'] ?? '';
    
    // Dados de pedidos
    $dados['valor_total'] = $_POST['valor_total'] ?? $_POST['total'] ?? '';
    $dados['forma_pagamento'] = $_POST['forma_pagamento'] ?? $_POST['payment_method'] ?? '';
    
    // IDs específicos para diferentes módulos
    if (isset($_POST['produto_id'])) $dados['produto_id'] = $_POST['produto_id'];
    if (isset($_POST['cliente_id'])) $dados['cliente_id'] = $_POST['cliente_id'];
    if (isset($_POST['pedido_id'])) $dados['pedido_id'] = $_POST['pedido_id'];
    
    // Buscar nome do produto/cliente pelo ID se não temos o nome
    if (empty($dados['nome']) && !empty($dados['id'])) {
        $dados['nome'] = buscar_nome_por_id($dados['id'], $dados['acao']);
    }
    
    return $dados;
}

/**
 * Buscar nome por ID no banco de dados para logs mais descritivos
 */
function buscar_nome_por_id($id, $tipo_acao = '') {
    global $conexao;
    
    if (empty($id) || !$conexao) return '';
    
    try {
        // Determinar tabela baseada no tipo de ação
        $tabela = '';
        $campo_nome = '';
        
        if (strpos($tipo_acao, 'product') !== false || strpos($tipo_acao, 'produto') !== false) {
            $tabela = 'produtos';
            $campo_nome = 'nome';
        } elseif (strpos($tipo_acao, 'cliente') !== false || strpos($tipo_acao, 'customer') !== false) {
            $tabela = 'clientes';
            $campo_nome = 'nome';
        } elseif (strpos($tipo_acao, 'usuario') !== false || strpos($tipo_acao, 'user') !== false) {
            $tabela = 'usuarios';
            $campo_nome = 'nome';
        } elseif (strpos($tipo_acao, 'pedido') !== false || strpos($tipo_acao, 'order') !== false) {
            $tabela = 'pedidos';
            $campo_nome = 'id'; // Para pedidos, usamos o ID como identificador
        }
        
        // Se não conseguimos determinar a tabela, tentar várias
        if (empty($tabela)) {
            $tabelas_possiveis = [
                ['produtos', 'nome'],
                ['clientes', 'nome'], 
                ['usuarios', 'nome'],
                ['pedidos', 'id']
            ];
            
            foreach ($tabelas_possiveis as $opcao) {
                $stmt = $conexao->prepare("SELECT {$opcao[1]} FROM {$opcao[0]} WHERE id = ? LIMIT 1");
                $stmt->execute([$id]);
                $resultado = $stmt->fetch(PDO::FETCH_COLUMN);
                
                if ($resultado) {
                    return $resultado;
                }
            }
            return '';
        }
        
        // Buscar nome na tabela específica
        $stmt = $conexao->prepare("SELECT $campo_nome FROM $tabela WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $nome = $stmt->fetch(PDO::FETCH_COLUMN);
        
        return $nome ?: '';
        
    } catch (Exception $e) {
        // Em caso de erro, retornar string vazia
        return '';
    }
}

/**
 * Gerar mensagem de log baseada na ação
 */
function gerar_mensagem_log($dados, $pagina_atual = '') {
    $acao = $dados['acao'] ?? 'ação desconhecida';
    $nome = $dados['nome'] ?? '';
    $email = $dados['email'] ?? '';
    $id = $dados['id'] ?? '';
    $status = $dados['status'] ?? '';
    $preco = $dados['preco'] ?? '';
    $estoque = $dados['estoque'] ?? '';
    $categoria = $dados['categoria'] ?? '';
    $produto_id = $dados['produto_id'] ?? '';
    $cliente_id = $dados['cliente_id'] ?? '';
    $valor_total = $dados['valor_total'] ?? '';
    
    // Mapear ações para mensagens específicas e detalhadas
    switch ($acao) {
        // Ações de usuário
        case 'create_usuario':
            return "cadastrou usuário '$nome' ($email)";
            
        case 'update_usuario':
            return "atualizou dados do usuário '$nome' (ID: $id)";
            
        case 'delete_usuario':
            return "excluiu usuário '$nome' (ID: $id)";
            
        // Ações de produtos
        case 'add_product':
        case 'create_product':
            $msg = "adicionou produto '$nome'";
            if (!empty($preco)) $msg .= " por R$ $preco";
            if (!empty($categoria)) $msg .= " na categoria '$categoria'";
            return $msg;
            
        case 'edit_product':
        case 'update_product':
            $msg = "alterou produto '$nome'";
            if (!empty($preco)) $msg .= " - novo preço: R$ $preco";
            if (!empty($estoque)) $msg .= " - estoque: $estoque unidades";
            return $msg;
            
        case 'delete_product':
            return "excluiu produto '$nome' (ID: $id)";
            
        case 'update_stock':
            return "alterou estoque do produto '$nome' para $estoque unidades";
            
        case 'update_price':
            return "alterou preço do produto '$nome' para R$ $preco";
            
        // Ações de clientes
        case 'add_cliente':
        case 'create_cliente':
            return "cadastrou cliente '$nome' ($email)";
            
        case 'edit_cliente':
        case 'update_cliente':
            return "atualizou dados do cliente '$nome' (ID: $id)";
            
        case 'delete_cliente':
            return "excluiu cliente '$nome' (ID: $id)";
            
        // Ações de pedidos
        case 'add_pedido':
        case 'create_order':
            $msg = "criou pedido";
            if (!empty($valor_total)) $msg .= " no valor de R$ $valor_total";
            if (!empty($cliente_id)) $msg .= " para cliente ID: $cliente_id";
            return $msg;
            
        case 'update_order':
        case 'edit_pedido':
            $msg = "atualizou pedido ID: $id";
            if (!empty($status)) $msg .= " - status: $status";
            return $msg;
            
        case 'cancel_order':
            return "cancelou pedido ID: $id";
            
        case 'approve_order':
            return "aprovou pedido ID: $id";
            
        case 'ship_order':
            return "marcou pedido ID: $id como enviado";
            
        // Ações de produtos
        case 'add_product':
        case 'create_product':
            $msg = "criou produto '$nome'";
            if (!empty($preco)) $msg .= " por R$ $preco";
            if (!empty($categoria)) $msg .= " na categoria '$categoria'";
            return $msg;
            
        case 'edit_product':
        case 'update_product':
            $msg = "alterou produto '$nome'";
            if (!empty($preco)) $msg .= " - novo preço: R$ $preco";
            if (!empty($estoque)) $msg .= " - estoque: $estoque unidades";
            return $msg;
            
        case 'delete_product':
            return "excluiu permanentemente o produto '$nome' (ID: $id)";
            
        case 'update_stock':
            return "alterou estoque do produto '$nome' para $estoque unidades";
            
        case 'update_price':
            return "alterou preço do produto '$nome' para R$ $preco";
            
        default:
            if (!empty($nome)) {
                return "$acao '$nome' em $pagina_atual";
            } elseif (!empty($id)) {
                return "$acao item ID: $id em $pagina_atual";
            }
            return "$acao em $pagina_atual";
    }
}

/**
 * Log automático baseado na página atual e dados do formulário
 */
function log_automatico($conexao) {
    // Detectar a página atual
    $pagina_atual = basename($_SERVER['PHP_SELF'], '.php');
    
    // Mapear páginas para nomes amigáveis
    $paginas_nome = [
        'addproducts' => 'produtos',
        'customers' => 'clientes',
        'orders' => 'pedidos',
        'products' => 'produtos',
        'settings' => 'configurações',
        'usuario-create' => 'usuários',
        'usuario-edit' => 'usuários',
        'gerenciar-vendedoras' => 'vendedoras',
        'revendedores' => 'revendedores'
    ];
    
    $nome_pagina = $paginas_nome[$pagina_atual] ?? $pagina_atual;
    
    // Capturar dados da ação
    $dados = capturar_dados_acao();
    
    // Se há dados de formulário, gerar log
    if (!empty($dados)) {
        $mensagem = gerar_mensagem_log($dados, $nome_pagina);
        registrar_log($conexao, $mensagem);
    }
    
    // Log de acesso à página (apenas para páginas importantes)
    $paginas_importantes = ['settings', 'analytics', 'metricas'];
    if (in_array($pagina_atual, $paginas_importantes) && empty($_POST) && empty($_GET['action'])) {
        registrar_log($conexao, "acessou $nome_pagina");
    }
}

// Sistema de logs automático DESABILITADO
// Agora usamos apenas logs manuais específicos para alterações de dados
// Remova as linhas abaixo se quiser reativar o log automático:
/*
$is_product_ajax = isset($_POST['action']) && in_array($_POST['action'], ['update_product', 'update_variation_field']);

if (($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) && !$is_product_ajax) {
    log_automatico($conexao);
}
*/
/**
 * Registrar log com valores "antes e depois" para alterações
 */
function registrar_log_alteracao($conexao, $acao, $item, $campo, $valor_antigo, $valor_novo, $detalhes = '') {
    if (!$conexao || !isset($_SESSION['usuario_logado'])) return;
    
    $admin_id = $_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? 1;
    $admin_nome = $_SESSION['usuario_nome'] ?? $_SESSION['nome_usuario'] ?? 'Admin';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'localhost';
    
    // Formatar mensagem baseada no tipo de alteração
    switch ($acao) {
        case 'estoque':
            $mensagem = "alterou estoque do produto '{$item}' de {$valor_antigo} para {$valor_novo} unidades";
            break;
        case 'preco':
            $valor_antigo_fmt = 'R$ ' . number_format((float)$valor_antigo, 2, ',', '.');
            $valor_novo_fmt = 'R$ ' . number_format((float)$valor_novo, 2, ',', '.');
            $mensagem = "alterou preço do produto '{$item}' de {$valor_antigo_fmt} para {$valor_novo_fmt}";
            break;
        case 'preco_promocional':
            if ($valor_novo === null || $valor_novo == 0) {
                $mensagem = "removeu promoção do produto '{$item}' (era R$ " . number_format((float)$valor_antigo, 2, ',', '.') . ")";
            } else {
                $valor_novo_fmt = 'R$ ' . number_format((float)$valor_novo, 2, ',', '.');
                if ($valor_antigo === null || $valor_antigo == 0) {
                    $mensagem = "definiu preço promocional de {$valor_novo_fmt} para o produto '{$item}'";
                } else {
                    $valor_antigo_fmt = 'R$ ' . number_format((float)$valor_antigo, 2, ',', '.');
                    $mensagem = "alterou preço promocional do produto '{$item}' de {$valor_antigo_fmt} para {$valor_novo_fmt}";
                }
            }
            break;
        case 'usuario_dados':
            $mensagem = "alterou dados do usuário '{$item}': {$campo} de '{$valor_antigo}' para '{$valor_novo}'";
            break;
        default:
            $mensagem = "alterou {$campo} do {$acao} '{$item}' de '{$valor_antigo}' para '{$valor_novo}'";
            if ($detalhes) $mensagem .= " - {$detalhes}";
    }
    
    registrar_log($conexao, $mensagem);
}

/**
 * Registrar log simples para criação/exclusão
 */
function registrar_log_acao($conexao, $acao, $item, $detalhes = '', $sku = '') {
    if (!$conexao || !isset($_SESSION['usuario_logado'])) return;
    
    switch ($acao) {
        case 'criar_produto':
            $mensagem = "criou produto '{$item}'";
            if ($sku) $mensagem .= " (SKU: {$sku})";
            if ($detalhes) $mensagem .= " - {$detalhes}";
            break;
        case 'excluir_produto':
            $mensagem = "excluiu permanentemente o produto '{$item}'";
            if ($sku) $mensagem .= " (SKU: {$sku})";
            break;
        case 'criar_usuario':
            $mensagem = "criou novo usuário administrador: '{$item}'";
            if ($detalhes) $mensagem .= " - {$detalhes}";
            break;
        case 'alterar_permissoes':
            $mensagem = "alterou permissões/dados do usuário: '{$item}'";
            if ($detalhes) $mensagem .= " - {$detalhes}";
            break;
        default:
            $mensagem = "{$acao}: {$item}";
            if ($detalhes) $mensagem .= " - {$detalhes}";
    }
    
    registrar_log($conexao, $mensagem);
}

/**
 * Buscar dados atuais antes de alterar (para comparação)
 */
function buscar_dados_atuais($conexao, $tabela, $id, $campos = []) {
    if (!$conexao || !$tabela || !$id) return [];
    
    try {
        $campos_sql = empty($campos) ? '*' : implode(', ', $campos);
        $sql = "SELECT {$campos_sql} FROM {$tabela} WHERE id = ?";
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_fetch_assoc($result) ?: [];
    } catch (Exception $e) {
        error_log("Erro ao buscar dados atuais: " . $e->getMessage());
        return [];
    }
}
?>