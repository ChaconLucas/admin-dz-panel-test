<?php
require_once __DIR__ . '/../../../PHP/conexao.php';

// Verificar se é uma requisição POST para exportar Excel
if ($_POST['action'] === 'export_excel') {
    
    // Receber dados do POST
    $data_inicio = $_POST['data_inicio'] ?? date('Y-m-01');
    $data_fim = $_POST['data_fim'] ?? date('Y-m-d');
    $kpis = json_decode($_POST['kpis'], true);
    $lista_pedidos = json_decode($_POST['lista_pedidos'], true);
    $dados_evolucao = json_decode($_POST['dados_evolucao'], true);
    $dados_categorias = json_decode($_POST['dados_categorias'], true);
    
    // Gerar arquivo Excel XML
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="DZ_Relatorio_Premium_' . $data_inicio . '_' . $data_fim . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Início do XML
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
            xmlns:o="urn:schemas-microsoft-com:office:office"
            xmlns:x="urn:schemas-microsoft-com:office:excel"
            xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
            xmlns:html="http://www.w3.org/TR/REC-html40">
        
        <!-- Estilos CSS para Excel -->
        <Styles>
            <!-- Estilo do cabeçalho principal D&Z -->
            <Style ss:ID="HeaderDZ">
                <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
                <Borders>
                    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2"/>
                    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2"/>
                    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="2"/>
                    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="2"/>
                </Borders>
                <Font ss:FontName="Arial" ss:Color="#FFFFFF" ss:Bold="1" ss:Size="14"/>
                <Interior ss:Color="#FF00CC" ss:Pattern="Solid"/>
            </Style>
            
            <!-- Estilo dos cabeçalhos das seções -->
            <Style ss:ID="SectionHeader">
                <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
                <Font ss:FontName="Arial" ss:Color="#333333" ss:Bold="1" ss:Size="12"/>
                <Interior ss:Color="#F8F9FA" ss:Pattern="Solid"/>
                <Borders>
                    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                </Borders>
            </Style>
            
            <!-- Estilo dos cabeçalhos das tabelas -->
            <Style ss:ID="TableHeader">
                <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
                <Font ss:FontName="Arial" ss:Color="#FFFFFF" ss:Bold="1" ss:Size="10"/>
                <Interior ss:Color="#6C757D" ss:Pattern="Solid"/>
                <Borders>
                    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                </Borders>
            </Style>
            
            <!-- Estilo KPI destacado -->
            <Style ss:ID="KPI">
                <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
                <Font ss:FontName="Arial" ss:Color="#0F5132" ss:Bold="1" ss:Size="10"/>
                <NumberFormat ss:Format="[$R$-416] #,##0.00"/>
                <Borders>
                    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                </Borders>
            </Style>
            
            <!-- Status Pago - Verde -->
            <Style ss:ID="StatusPago">
                <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
                <Font ss:FontName="Arial" ss:Color="#FFFFFF" ss:Bold="1" ss:Size="9"/>
                <Interior ss:Color="#198754" ss:Pattern="Solid"/>
                <Borders>
                    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                </Borders>
            </Style>
            
            <!-- Status Pendente - Amarelo -->
            <Style ss:ID="StatusPendente">
                <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
                <Font ss:FontName="Arial" ss:Color="#664D03" ss:Bold="1" ss:Size="9"/>
                <Interior ss:Color="#FFC107" ss:Pattern="Solid"/>
                <Borders>
                    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                </Borders>
            </Style>
            
            <!-- Status Em Preparação - Azul Claro -->
            <Style ss:ID="StatusPreparacao">
                <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
                <Font ss:FontName="Arial" ss:Color="#495057" ss:Bold="1" ss:Size="9"/>
                <Interior ss:Color="#F8F9FA" ss:Pattern="Solid"/>
                <Borders>
                    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                </Borders>
            </Style>
            
            <!-- Status Estornado - Vermelho -->
            <Style ss:ID="StatusEstornado">
                <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
                <Font ss:FontName="Arial" ss:Color="#FFFFFF" ss:Bold="1" ss:Size="9"/>
                <Interior ss:Color="#DC3545" ss:Pattern="Solid"/>
                <Borders>
                    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                </Borders>
            </Style>
            
            <!-- Estilo da célula padrão -->
            <Style ss:ID="Default">
                <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
                <Font ss:FontName="Arial" ss:Size="9"/>
                <Borders>
                    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                </Borders>
            </Style>
            
            <!-- Valores monetários -->
            <Style ss:ID="Currency">
                <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
                <Font ss:FontName="Arial" ss:Size="9"/>
                <NumberFormat ss:Format="[$R$-416] #,##0.00"/>
                <Borders>
                    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                </Borders>
            </Style>
        </Styles>
        
        <Worksheet ss:Name="Relatório D&amp;Z">
            <Table>
                <!-- Definir larguras das colunas -->
                <Column ss:AutoFitWidth="1" ss:Width="120"/>
                <Column ss:AutoFitWidth="1" ss:Width="80"/>
                <Column ss:AutoFitWidth="1" ss:Width="150"/>
                <Column ss:AutoFitWidth="1" ss:Width="80"/>
                <Column ss:AutoFitWidth="1" ss:Width="100"/>
                <Column ss:AutoFitWidth="1" ss:Width="100"/>
                <Column ss:AutoFitWidth="1" ss:Width="100"/>
                <Column ss:AutoFitWidth="1" ss:Width="100"/>
                <Column ss:AutoFitWidth="1" ss:Width="120"/>
                
                <!-- CABEÇALHO PRINCIPAL D&Z -->
                <Row ss:Height="35">
                    <Cell ss:MergeAcross="8" ss:StyleID="HeaderDZ">
                        <Data ss:Type="String">🏢 D&amp;Z - RELATÓRIO DE VENDAS</Data>
                    </Cell>
                </Row>
                
                <Row ss:Height="20">
                    <Cell ss:MergeAcross="8" ss:StyleID="Default">
                        <Data ss:Type="String">📅 Período: ' . $data_inicio . ' até ' . $data_fim . ' | 📊 Gerado em: ' . date('d/m/Y H:i:s') . '</Data>
                    </Cell>
                </Row>
                
                <!-- ESPAÇAMENTO -->
                <Row ss:Height="15">
                    <Cell><Data ss:Type="String"></Data></Cell>
                </Row>
                
                <!-- BLOCO 1: RESUMO EXECUTIVO (KPIs) -->
                <Row ss:Height="25">
                    <Cell ss:MergeAcross="8" ss:StyleID="SectionHeader">
                        <Data ss:Type="String">🎯 RESUMO EXECUTIVO - INDICADORES PRINCIPAIS</Data>
                    </Cell>
                </Row>
                
                <!-- Cabeçalhos KPIs -->
                <Row ss:Height="20">
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">💰 Indicador</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">🏆 Valor</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">📊 Status</Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                </Row>';
    
    // KPIs dinâmicos
    $kpi_items = [
        ['label' => '💰 Faturamento Total', 'value' => 'R$ ' . number_format($kpis['faturamento'], 2, ',', '.'), 'status' => '✅ Sucesso'],
        ['label' => '🛒 Total de Vendas', 'value' => $kpis['total_vendas'] . ' pedidos', 'status' => '📈 Ativo'],
        ['label' => '💳 Ticket Médio', 'value' => 'R$ ' . number_format($kpis['ticket_medio'], 2, ',', '.'), 'status' => '📊 Normal'],
        ['label' => '📦 Itens Vendidos', 'value' => $kpis['itens_vendidos'] . ' unidades', 'status' => '🔥 Forte']
    ];
    
    foreach ($kpi_items as $kpi) {
        echo '<Row ss:Height="18">
                <Cell ss:StyleID="Default"><Data ss:Type="String">' . $kpi['label'] . '</Data></Cell>
                <Cell ss:StyleID="KPI"><Data ss:Type="String">' . $kpi['value'] . '</Data></Cell>
                <Cell ss:StyleID="Default"><Data ss:Type="String">' . $kpi['status'] . '</Data></Cell>
                <Cell><Data ss:Type="String"></Data></Cell>
                <Cell><Data ss:Type="String"></Data></Cell>
                <Cell><Data ss:Type="String"></Data></Cell>
                <Cell><Data ss:Type="String"></Data></Cell>
                <Cell><Data ss:Type="String"></Data></Cell>
                <Cell><Data ss:Type="String"></Data></Cell>
            </Row>';
    }
    
    // Espaçamento e cabeçalho dos pedidos
    echo '
                <!-- ESPAÇAMENTO -->
                <Row ss:Height="15">
                    <Cell><Data ss:Type="String"></Data></Cell>
                </Row>
                
                <!-- BLOCO 2: RELATÓRIO DETALHADO DE PEDIDOS -->
                <Row ss:Height="25">
                    <Cell ss:MergeAcross="8" ss:StyleID="SectionHeader">
                        <Data ss:Type="String">📋 RELATÓRIO DETALHADO DOS PEDIDOS</Data>
                    </Cell>
                </Row>
                
                <!-- Cabeçalhos da tabela -->
                <Row ss:Height="20">
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">📅 Data</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">🆔 Pedido</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">👤 Cliente</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">📦 Itens</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">💰 Subtotal</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">🚚 Desc.Frete</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">🎫 Desc.Cupom</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">💳 Valor Final</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">📊 Status</Data></Cell>
                </Row>';
    
    // Dados dos pedidos
    if (!empty($lista_pedidos)) {
        foreach ($lista_pedidos as $pedido) {
            // Formatar valores
            $subtotal = $pedido['subtotal'] ?? 0;
            $descFrete = $pedido['desconto_frete'] ?? 0;
            $descCupom = $pedido['desconto_cupom'] ?? 0;
            $valorFinal = $pedido['valor_total'] ?? 0;
            
            // Determinar estilo do status
            $statusStyle = 'Default';
            $status = strtolower(str_replace(' ', '', $pedido['status'] ?? ''));
            
            switch ($status) {
                case 'pago':
                case 'entregue':
                case 'pedidoconfirmado':
                    $statusStyle = 'StatusPago';
                    break;
                case 'pagamentopendente':
                case 'pendente':
                    $statusStyle = 'StatusPendente';
                    break;
                case 'empreparacao':
                case 'empreparação':
                case 'pedidorecebido':
                    $statusStyle = 'StatusPreparacao';
                    break;
                case 'estornado':
                case 'cancelado':
                    $statusStyle = 'StatusEstornado';
                    break;
            }
            
            echo '<Row ss:Height="16">
                    <Cell ss:StyleID="Default"><Data ss:Type="String">' . date('d/m/Y', strtotime($pedido['data_pedido'])) . '</Data></Cell>
                    <Cell ss:StyleID="Default"><Data ss:Type="String">#' . $pedido['id'] . '</Data></Cell>
                    <Cell ss:StyleID="Default"><Data ss:Type="String">' . htmlspecialchars($pedido['cliente_nome'] ?? 'N/A') . '</Data></Cell>
                    <Cell ss:StyleID="Default"><Data ss:Type="Number">' . ($pedido['total_itens'] ?? 0) . '</Data></Cell>
                    <Cell ss:StyleID="Currency"><Data ss:Type="Number">' . $subtotal . '</Data></Cell>
                    <Cell ss:StyleID="Currency"><Data ss:Type="Number">' . $descFrete . '</Data></Cell>
                    <Cell ss:StyleID="Currency"><Data ss:Type="Number">' . $descCupom . '</Data></Cell>
                    <Cell ss:StyleID="Currency"><Data ss:Type="Number">' . $valorFinal . '</Data></Cell>
                    <Cell ss:StyleID="' . $statusStyle . '"><Data ss:Type="String">' . htmlspecialchars($pedido['status']) . '</Data></Cell>
                </Row>';
        }
    }
    
    // Rodapé e fechamento
    echo '
                <!-- ESPAÇAMENTO -->
                <Row ss:Height="15">
                    <Cell><Data ss:Type="String"></Data></Cell>
                </Row>
                
                <!-- RODAPÉ -->
                <Row ss:Height="20">
                    <Cell ss:MergeAcross="8" ss:StyleID="Default">
                        <Data ss:Type="String">🏢 Relatório gerado automaticamente pelo Sistema D&amp;Z Dashboard © ' . date('Y') . '</Data>
                    </Cell>
                </Row>
                
            </Table>
        </Worksheet>
    </Workbook>';
    
    exit;
}
?>