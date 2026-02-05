<?php
// Script para atualizar schema das tabelas relacionadas a pedidos
// Execute este arquivo uma vez para garantir que todas as colunas necessárias existam

require_once '../../../PHP/conexao.php';

if (!$conexao) {
    die("Erro na conexão com o banco de dados");
}

echo "<h2>Atualizando Schema das Tabelas de Pedidos</h2>";

// Atualizar tabela clientes - adicionar campos faltantes se necessário
$alterClientesQueries = [
    "ALTER TABLE clientes ADD COLUMN IF NOT EXISTS cpf VARCHAR(14) UNIQUE AFTER email",
    "ALTER TABLE clientes ADD COLUMN IF NOT EXISTS complemento TEXT AFTER endereco",
    "ALTER TABLE clientes ADD COLUMN IF NOT EXISTS bairro VARCHAR(100) AFTER endereco"
];

echo "<h3>Atualizando tabela clientes...</h3>";
foreach ($alterClientesQueries as $query) {
    if (mysqli_query($conexao, $query)) {
        echo "✓ Executado: " . htmlspecialchars($query) . "<br>";
    } else {
        echo "❌ Erro: " . mysqli_error($conexao) . "<br>";
    }
}

// Atualizar tabela pedidos - adicionar campos para gestão completa
$alterPedidosQueries = [
    "ALTER TABLE pedidos MODIFY COLUMN status VARCHAR(100) DEFAULT 'Pedido Recebido'",
    "ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS forma_pagamento VARCHAR(50) AFTER status",
    "ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS status_pagamento VARCHAR(50) DEFAULT 'Pendente' AFTER forma_pagamento",
    "ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS codigo_rastreio VARCHAR(100) AFTER data_entrega",
    "ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS link_rastreio TEXT AFTER codigo_rastreio",
    "ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS comprovante_pagamento VARCHAR(255) AFTER link_rastreio",
    "ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS data_status_mudanca TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER observacoes"
];

echo "<h3>Atualizando tabela pedidos...</h3>";
foreach ($alterPedidosQueries as $query) {
    if (mysqli_query($conexao, $query)) {
        echo "✓ Executado: " . htmlspecialchars($query) . "<br>";
    } else {
        echo "❌ Erro: " . mysqli_error($conexao) . "<br>";
    }
}

// Criar tabela de histórico de status se não existir
$createHistoricoQuery = "
CREATE TABLE IF NOT EXISTS pedidos_historico_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    status_anterior VARCHAR(100),
    status_novo VARCHAR(100) NOT NULL,
    data_mudanca TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    usuario_alteracao VARCHAR(100),
    observacoes TEXT,
    email_enviado TINYINT(1) DEFAULT 0,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    INDEX idx_pedido_data (pedido_id, data_mudanca)
)";

echo "<h3>Criando tabela de histórico de status...</h3>";
if (mysqli_query($conexao, $createHistoricoQuery)) {
    echo "✓ Tabela pedidos_historico_status criada/verificada<br>";
} else {
    echo "❌ Erro ao criar tabela de histórico: " . mysqli_error($conexao) . "<br>";
}

// Atualizar dados existentes se necessário
echo "<h3>Atualizando dados existentes...</h3>";

// Verificar se existem pedidos sem status adequado e atualizar
$updateStatusQuery = "
UPDATE pedidos 
SET status = CASE 
    WHEN status = 'pendente' THEN 'Aguardando Pagamento'
    WHEN status = 'processando' THEN 'Em Preparação'
    WHEN status = 'enviado' THEN 'Enviado'
    WHEN status = 'entregue' THEN 'Entregue'
    WHEN status = 'cancelado' THEN 'Cancelado'
    ELSE status
END
WHERE status IN ('pendente', 'processando', 'enviado', 'entregue', 'cancelado')
";

if (mysqli_query($conexao, $updateStatusQuery)) {
    $affected = mysqli_affected_rows($conexao);
    echo "✓ Status de $affected pedidos atualizados para o novo formato<br>";
} else {
    echo "❌ Erro ao atualizar status: " . mysqli_error($conexao) . "<br>";
}

// Inserir alguns dados de exemplo se a tabela estiver vazia
$checkPedidos = mysqli_query($conexao, "SELECT COUNT(*) as total FROM pedidos");
if ($checkPedidos) {
    $row = mysqli_fetch_assoc($checkPedidos);
    if ($row['total'] == 0) {
        echo "<h3>Inserindo dados de exemplo...</h3>";
        
        // Inserir alguns pedidos de exemplo
        $exemploQueries = [
            "INSERT INTO pedidos (cliente_id, valor_total, status, forma_pagamento, status_pagamento) VALUES (1, 129.90, 'Pedido Recebido', 'Pix', 'Pendente')",
            "INSERT INTO pedidos (cliente_id, valor_total, status, forma_pagamento, status_pagamento) VALUES (2, 89.90, 'Pagamento Confirmado', 'Cartão', 'Aprovado')",
            "INSERT INTO pedidos (cliente_id, valor_total, status, forma_pagamento, status_pagamento) VALUES (3, 199.90, 'Enviado', 'Boleto', 'Pago')",
            "INSERT INTO pedidos (cliente_id, valor_total, status, forma_pagamento, status_pagamento) VALUES (1, 59.90, 'Solicitado Reembolso', 'Pix', 'Estornado')"
        ];
        
        foreach ($exemploQueries as $query) {
            if (mysqli_query($conexao, $query)) {
                echo "✓ Pedido de exemplo inserido<br>";
            } else {
                echo "❌ Erro ao inserir exemplo: " . mysqli_error($conexao) . "<br>";
            }
        }
    }
}

echo "<h3>✅ Atualização concluída!</h3>";
echo "<p><a href='orders.php'>← Voltar para Gestão de Pedidos</a></p>";
?>