<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit();
}

// Incluir conexão com banco
require_once '../../../PHP/conexao.php';

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit();
}

// Coletar dados do formulário
$cep_destino = trim($_POST['destination_zipcode'] ?? '');
$peso = floatval($_POST['weight'] ?? 0);
$altura = floatval($_POST['height'] ?? 0);
$largura = floatval($_POST['width'] ?? 0);
$comprimento = floatval($_POST['length'] ?? 0);
$valor_pedido = 100.00; // Valor fixo de R$ 100

// Validar dados obrigatórios
if (empty($cep_destino) || $peso <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'CEP de destino e peso são obrigatórios'
    ]);
    exit();
}

// Buscar configurações de frete do banco
function buscarConfiguracoesFrete() {
    global $conexao;
    
    // Buscar configurações globais
    $settings = [];
    $result = $conexao->query("SELECT * FROM freight_settings WHERE id = 1");
    if ($result && $result->num_rows > 0) {
        $settings = $result->fetch_assoc();
    }
    
    // Buscar integrações ativas
    $integrations = [];
    $result = $conexao->query("SELECT * FROM freight_integrations WHERE active = 1 ORDER BY priority ASC");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $integrations[] = $row;
        }
    }
    
    // Buscar serviços ativos
    $active_services = [];
    $active_services_details = [];
    $result = $conexao->query("SELECT service_code, service_name FROM freight_services WHERE active = 1");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $active_services[] = $row['service_code'];
            $active_services_details[] = $row;
        }
    }
    
    return ['settings' => $settings, 'integrations' => $integrations, 'active_services' => $active_services, 'active_services_details' => $active_services_details];
}

// Função para calcular via Melhor Envio
function calcularMelhorEnvio($token, $cep_origem, $cep_destino, $peso, $altura, $largura, $comprimento, $valor_declarado = 100.00) {
    $url = "https://www.melhorenvio.com.br/api/v2/me/shipment/calculate";
    
    // Forçar valor fixo de R$ 100 para todas as simulações
    $valor_fixo = 100.00;
    
    $payload = [
        "from" => [
            "postal_code" => preg_replace('/\D/', '', $cep_origem)
        ],
        "to" => [
            "postal_code" => preg_replace('/\D/', '', $cep_destino)
        ],
        "package" => [
            "height" => $altura,
            "width" => $largura,
            "length" => $comprimento,
            "weight" => $peso,
            "value" => $valor_fixo
        ]
    ];
    
    $headers = [
        "Authorization: Bearer " . $token,
        "Content-Type: application/json",
        "Accept: application/json",
        "User-Agent: Sistema-Frete-DZ/1.0"
    ];
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        return ['error' => 'Erro de conexão: ' . $error];
    }
    
    if ($httpCode !== 200) {
        return ['error' => 'API retornou código ' . $httpCode];
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Erro ao decodificar resposta da API'];
    }
    
    return $data;
}

// Função para calcular via Jadlog
function calcularJadlog($token, $cep_origem, $cep_destino, $peso, $altura, $largura, $comprimento, $valor_nf) {
    $url = "https://www.jadlog.com.br/embarcador/api/frete/valor";
    
    // Remover formatação dos CEPs
    $cep_origem = preg_replace('/\D/', '', $cep_origem);
    $cep_destino = preg_replace('/\D/', '', $cep_destino);
    
    $payload = [
        "cepori" => $cep_origem,
        "cepdes" => $cep_destino,
        "peso" => $peso,
        "altura" => $altura,
        "largura" => $largura,
        "comprimento" => $comprimento,
        "vlrdeclarado" => $valor_nf,
        "modalidade" => "0" // 0 = todas as modalidades
    ];
    
    $headers = [
        "Authorization: Bearer " . $token,
        "Content-Type: application/json",
        "Accept: application/json"
    ];
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        return ['error' => 'Erro de conexão: ' . $error];
    }
    
    if ($httpCode !== 200) {
        return ['error' => 'API Jadlog retornou código ' . $httpCode];
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Erro ao decodificar resposta da API Jadlog'];
    }
    
    return $data;
}

// Função para aplicar configurações (margem, etc)
function aplicarConfiguracoes($valor, $settings) {
    if (!$settings || !isset($settings['margin_type'])) {
        return $valor;
    }
    
    $margem_tipo = $settings['margin_type'];
    $margem_valor = floatval($settings['margin_value'] ?? 0);
    
    if ($margem_tipo === 'percentage' && $margem_valor > 0) {
        $valor += ($valor * ($margem_valor / 100));
    } elseif ($margem_tipo === 'fixed' && $margem_valor > 0) {
        $valor += $margem_valor;
    }
    
    // Aplicar arredondamento
    $rounding_type = $settings['rounding_type'] ?? 'round';
    switch ($rounding_type) {
        case 'floor':
            $valor = floor($valor * 100) / 100;
            break;
        case 'ceil':
            $valor = ceil($valor * 100) / 100;
            break;
        default:
            $valor = round($valor, 2);
    }
    
    return $valor;
}

// Função de fallback
function aplicarFallback($settings) {
    $fallback_valor = floatval($settings['fallback_value'] ?? 15.00);
    $fallback_message = $settings['fallback_message'] ?? 'Prazo de entrega: 3 a 7 dias úteis';
    
    return [
        'success' => true,
        'provider' => 'Fallback',
        'service_name' => 'Frete de Emergência',
        'price' => $fallback_valor,
        'delivery_time' => 7,
        'message' => $fallback_message,
        'fallback_used' => true
    ];
}

// MAIN: Calcular frete
try {
    $config = buscarConfiguracoesFrete();
    $settings = $config['settings'];
    $integrations = $config['integrations'];
    
    // Verificar se há serviços ativos selecionados
    if (empty($config['active_services'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Nenhum serviço de frete foi selecionado. Configure os serviços na seção "Serviços de Frete Disponíveis".'
        ]);
        exit();
    }
    
    // CEP de origem padrão
    $cep_origem = $settings['origin_zipcode'] ?? '01310-100';
    
    // Verificar frete grátis
    $free_shipping_threshold = floatval($settings['free_shipping_threshold'] ?? 0);
    if ($free_shipping_threshold > 0 && $valor_pedido >= $free_shipping_threshold) {
        echo json_encode([
            'success' => true,
            'provider' => 'Sistema',
            'service_name' => 'Frete Grátis',
            'price' => 0.00,
            'delivery_time' => 5,
            'message' => 'Frete grátis aplicado!',
            'free_shipping' => true,
            'opcoes' => [[
                'success' => true,
                'provider' => 'Sistema',
                'service_name' => 'Frete Grátis',
                'price' => 0.00,
                'delivery_time' => 5,
                'message' => 'Frete grátis aplicado!',
                'free_shipping' => true
            ]]
        ]);
        exit();
    }
    
    $todas_opcoes = [];
    $erro_apis = [];
    
    // Tentar cada integração ativa
    foreach ($integrations as $integration) {
        $provider_slug = $integration['provider_slug'];
        
        if ($provider_slug === 'melhor_envio' && !empty($integration['token'])) {
            try {
                $resultado = calcularMelhorEnvio(
                    $integration['token'],
                    $cep_origem,
                    $cep_destino,
                    $peso,
                    $altura,
                    $largura,
                    $comprimento,
                    $valor_pedido  // Usar valor do pedido como valor declarado
                );
                
                if (isset($resultado['error'])) {
                    $erro_apis[] = "Melhor Envio: " . $resultado['error'];
                    continue;
                }
                
                // Processar resultados da API
                if (is_array($resultado) && count($resultado) > 0) {
                    foreach ($resultado as $opcao) {
                        // Verificar se este serviço está ativo
                        $service_id = strval($opcao['id'] ?? '');
                        
                        // Verificar se há erro no serviço específico
                        if (isset($opcao['error']) || !isset($opcao['price']) || $opcao['price'] <= 0) {
                            continue; // Pular serviços com erro
                        }
                        
                        if (!empty($config['active_services']) && !in_array($service_id, $config['active_services'])) {
                            continue; // Pular serviços não selecionados
                        }
                        
                        // Buscar nome personalizado da nossa configuração
                        $nome_personalizado = 'Serviço';
                        foreach ($config['active_services_details'] ?? [] as $service) {
                            if (strval($service['service_code']) === $service_id) {
                                $nome_personalizado = $service['service_name'];
                                break;
                            }
                        }
                        
                        $preco_final = aplicarConfiguracoes(floatval($opcao['price']), $settings);
                        
                        $opcao_formatada = [
                            'success' => true,
                            'provider' => 'Melhor Envio',
                            'service_name' => $nome_personalizado,
                            'service_company' => $opcao['company']['name'] ?? '',
                            'price' => $preco_final,
                            'original_price' => floatval($opcao['price']),
                            'delivery_time' => intval($opcao['delivery_time'] ?? 5),
                            'delivery_range' => $opcao['delivery_range'] ?? null,
                            'package_id' => $opcao['id'] ?? null,
                            'service_id' => $service_id
                        ];
                        
                        $todas_opcoes[] = $opcao_formatada;
                    }
                }
                
            } catch (Exception $e) {
                $erro_apis[] = "Melhor Envio: " . $e->getMessage();
            }
        }
        
        // Aqui você pode adicionar outras integrações (Correios direto, Loggi, etc)
        
        // Se encontrou resultado, quebrar o loop dependendo da configuração
        $calculation_mode = $settings['calculation_mode'] ?? 'lowest_price';
        if (!empty($todas_opcoes) && $calculation_mode === 'priority') {
            break; // Para no primeiro que funcionar (por prioridade)
        }
    }
    
    // Se encontrou resultados válidos
    if (!empty($todas_opcoes)) {
        // Ordenar por preço (menor primeiro)
        usort($todas_opcoes, function($a, $b) {
            return $a['price'] <=> $b['price'];
        });
        
        echo json_encode([
            'success' => true,
            'opcoes' => $todas_opcoes,
            'melhor_opcao' => $todas_opcoes[0], // Primeira opção (mais barata)
            'total_opcoes' => count($todas_opcoes)
        ]);
        exit();
    }
    
    // Se chegou aqui, todas as APIs falharam - usar fallback
    $fallback_enabled = intval($settings['fallback_enabled'] ?? 1);
    if ($fallback_enabled) {
        $resultado_fallback = aplicarFallback($settings);
        $resultado_fallback['api_errors'] = $erro_apis;
        echo json_encode($resultado_fallback);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Não foi possível calcular o frete',
            'api_errors' => $erro_apis
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>