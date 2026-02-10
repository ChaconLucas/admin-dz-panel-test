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
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    
    ?>
    <Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
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
                        <Data ss:Type="String">📅 Período: <?= $data_inicio ?> até <?= $data_fim ?> | 📊 Gerado em: <?= date('d/m/Y H:i:s') ?></Data>
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
                </Row>
                
                <!-- Dados KPIs -->
                <?php 
                $totalDescontos = floatval($kpis['total_desconto_frete'] ?? 0) + floatval($kpis['total_desconto_cupom'] ?? 0);
                $faturamentoLiquido = floatval($kpis['faturamento']) - $totalDescontos;
                ?>
                <Row>
                    <Cell ss:StyleID="Default"><Data ss:Type="String">💵 Faturamento Líquido</Data></Cell>
                    <Cell ss:StyleID="KPI"><Data ss:Type="Number"><?= $faturamentoLiquido ?></Data></Cell>
                    <Cell ss:StyleID="Default"><Data ss:Type="String">🥇 Principal</Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                </Row>
                <Row>
                    <Cell ss:StyleID="Default"><Data ss:Type="String">📦 Total de Vendas</Data></Cell>
                    <Cell ss:StyleID="KPI"><Data ss:Type="Number"><?= $kpis['total_vendas'] ?></Data></Cell>
                    <Cell ss:StyleID="Default"><Data ss:Type="String">✅ Ativo</Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                </Row>
                <Row>
                    <Cell ss:StyleID="Default"><Data ss:Type="String">🎯 Ticket Médio</Data></Cell>
                    <Cell ss:StyleID="KPI"><Data ss:Type="Number"><?= $kpis['ticket_medio'] ?></Data></Cell>
                    <Cell ss:StyleID="Default"><Data ss:Type="String">📊 Calculado</Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                </Row>
                
                <!-- ESPAÇAMENTO -->
                <Row ss:Height="15">
                    <Cell><Data ss:Type="String"></Data></Cell>
                </Row>
                
                <!-- BLOCO 2: EVOLUÇÃO DIÁRIA -->
                <Row ss:Height="25">
                    <Cell ss:MergeAcross="8" ss:StyleID="SectionHeader">
                        <Data ss:Type="String">📈 EVOLUÇÃO DIÁRIA DE VENDAS</Data>
                    </Cell>
                </Row>
                
                <Row ss:Height="20">
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">📅 Data</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">💰 Faturamento</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">📦 Pedidos</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">🎯 Ticket Médio</Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                </Row>
                
                <?php if (!empty($dados_evolucao)): ?>
                    <?php foreach ($dados_evolucao as $dia): ?>
                        <?php $ticketMedio = floatval($dia['faturamento']) / intval($dia['pedidos']); ?>
                        <Row>
                            <Cell ss:StyleID="Default"><Data ss:Type="String"><?= date('d/m/Y', strtotime($dia['data'])) ?></Data></Cell>
                            <Cell ss:StyleID="Currency"><Data ss:Type="Number"><?= $dia['faturamento'] ?></Data></Cell>
                            <Cell ss:StyleID="Default"><Data ss:Type="Number"><?= $dia['pedidos'] ?></Data></Cell>
                            <Cell ss:StyleID="Currency"><Data ss:Type="Number"><?= $ticketMedio ?></Data></Cell>
                            <Cell><Data ss:Type="String"></Data></Cell>
                            <Cell><Data ss:Type="String"></Data></Cell>
                            <Cell><Data ss:Type="String"></Data></Cell>
                            <Cell><Data ss:Type="String"></Data></Cell>
                            <Cell><Data ss:Type="String"></Data></Cell>
                        </Row>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- ESPAÇAMENTO -->
                <Row ss:Height="15">
                    <Cell><Data ss:Type="String"></Data></Cell>
                </Row>
                
                <!-- BLOCO 3: DETALHAMENTO COMPLETO -->
                <Row ss:Height="25">
                    <Cell ss:MergeAcross="8" ss:StyleID="SectionHeader">
                        <Data ss:Type="String">📋 DETALHAMENTO COMPLETO DOS PEDIDOS</Data>
                    </Cell>
                </Row>
                
                <!-- Cabeçalhos da tabela de pedidos -->
                <Row ss:Height="20">
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">📟 ID</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">📅 Data</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">👤 Cliente</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">📦 Itens</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">💰 Subtotal</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">🚚 Desc. Frete</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">🎫 Desc. Cupom</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">🏆 Valor Final</Data></Cell>
                    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">⚡ Status</Data></Cell>
                </Row>
                
                <!-- Dados dos pedidos -->
                <?php foreach ($lista_pedidos as $pedido): ?>
                    <?php 
                    $subtotal = floatval($pedido['valor_subtotal'] ?? $pedido['valor_total']);
                    $descFrete = floatval($pedido['desconto_frete'] ?? 0);
                    $descCupom = floatval($pedido['desconto_cupom'] ?? 0);
                    $valorFinal = $subtotal - $descFrete - $descCupom;
                    
                    // Determinar estilo do status
                    $statusStyle = "Default";
                    switch(strtolower(str_replace(' ', '', $pedido['status']))) {
                        case 'pago':
                            $statusStyle = "StatusPago";
                            break;
                        case 'pagamentopendente':
                            $statusStyle = "StatusPendente";
                            break;
                        case 'empreparacao':
                        case 'empreparação':
                            $statusStyle = "StatusPreparacao";
                            break;
                        case 'estornado':
                            $statusStyle = "StatusEstornado";
                            break;
                    }
                    ?>
                    <Row>
                        <Cell ss:StyleID="Default"><Data ss:Type="String">#<?= str_pad($pedido['id'], 4, '0', STR_PAD_LEFT) ?></Data></Cell>
                        <Cell ss:StyleID="Default"><Data ss:Type="String"><?= date('d/m/Y', strtotime($pedido['data_pedido'])) ?></Data></Cell>
                        <Cell ss:StyleID="Default"><Data ss:Type="String"><?= htmlspecialchars($pedido['cliente_nome']) ?></Data></Cell>
                        <Cell ss:StyleID="Default"><Data ss:Type="Number"><?= $pedido['total_itens'] ?></Data></Cell>
                        <Cell ss:StyleID="Currency"><Data ss:Type="Number"><?= $subtotal ?></Data></Cell>
                        <Cell ss:StyleID="Currency"><Data ss:Type="Number"><?= $descFrete ?></Data></Cell>
                        <Cell ss:StyleID="Currency"><Data ss:Type="Number"><?= $descCupom ?></Data></Cell>
                        <Cell ss:StyleID="Currency"><Data ss:Type="Number"><?= $valorFinal ?></Data></Cell>
                        <Cell ss:StyleID="<?= $statusStyle ?>"><Data ss:Type="String"><?= htmlspecialchars($pedido['status']) ?></Data></Cell>
                    </Row>
                <?php endforeach; ?>
                
                <!-- ESPAÇAMENTO -->
                <Row ss:Height="15">
                    <Cell><Data ss:Type="String"></Data></Cell>
                </Row>
                
                <!-- RODAPÉ -->
                <Row ss:Height="20">
                    <Cell ss:MergeAcross="8" ss:StyleID="Default">
                        <Data ss:Type="String">🏢 Relatório gerado automaticamente pelo Sistema D&amp;Z Dashboard © <?= date('Y') ?></Data>
                    </Cell>
                </Row>
                
            </Table>
        </Worksheet>
    </Workbook>
    <?php
    exit;
}
?>