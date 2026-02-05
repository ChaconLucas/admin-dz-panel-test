<?php
// Script de verificação e correção rápida das tabelas
session_start();

// Verificar se está logado (opcional para debug)
if (!isset($_SESSION['usuario_logado'])) {
    echo "<h2>⚠️ Execute este script apenas uma vez para corrigir as tabelas</h2>";
}

require_once '../../../PHP/conexao.php';

if (!$conexao) {
    die("❌ Erro na conexão com o banco de dados");
}

echo "<h2>🔧 Verificando e Corrigindo Estrutura das Tabelas</h2>";

// Verificar se a tabela clientes existe
$tableExists = mysqli_query($conexao, "SHOW TABLES LIKE 'clientes'");
if (mysqli_num_rows($tableExists) == 0) {
    echo "<p>❌ Tabela 'clientes' não encontrada. Criando...</p>";
    
    $createClientes = "
    CREATE TABLE clientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        telefone VARCHAR(20),
        endereco TEXT,
        cidade VARCHAR(100),
        estado VARCHAR(2),
        cep VARCHAR(10),
        data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('ativo', 'inativo') DEFAULT 'ativo'
    )";
    
    if (mysqli_query($conexao, $createClientes)) {
        echo "<p>✅ Tabela 'clientes' criada com sucesso!</p>";
    } else {
        echo "<p>❌ Erro ao criar tabela clientes: " . mysqli_error($conexao) . "</p>";
    }
}

// Verificar se a tabela pedidos existe
$tableExists = mysqli_query($conexao, "SHOW TABLES LIKE 'pedidos'");
if (mysqli_num_rows($tableExists) == 0) {
    echo "<p>❌ Tabela 'pedidos' não encontrada. Criando...</p>";
    
    $createPedidos = "
    CREATE TABLE pedidos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cliente_id INT NOT NULL,
        valor_total DECIMAL(10,2) NOT NULL,
        status VARCHAR(100) DEFAULT 'Pedido Recebido',
        data_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        data_entrega DATE NULL,
        observacoes TEXT,
        forma_pagamento VARCHAR(50),
        status_pagamento VARCHAR(50) DEFAULT 'Pendente',
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
    )";
    
    if (mysqli_query($conexao, $createPedidos)) {
        echo "<p>✅ Tabela 'pedidos' criada com sucesso!</p>";
    } else {
        echo "<p>❌ Erro ao criar tabela pedidos: " . mysqli_error($conexao) . "</p>";
    }
}

// Inserir alguns dados de exemplo se as tabelas estão vazias
$checkClientes = mysqli_query($conexao, "SELECT COUNT(*) as total FROM clientes");
if ($checkClientes) {
    $row = mysqli_fetch_assoc($checkClientes);
    if ($row['total'] == 0) {
        echo "<p>📝 Inserindo dados de exemplo na tabela clientes...</p>";
        
        $insertClientes = [
            "INSERT INTO clientes (nome, email, telefone, cidade, estado, cep) VALUES ('Maria Silva', 'maria@email.com', '(11) 99999-9999', 'São Paulo', 'SP', '01000-000')",
            "INSERT INTO clientes (nome, email, telefone, cidade, estado, cep) VALUES ('João Santos', 'joao@email.com', '(21) 88888-8888', 'Rio de Janeiro', 'RJ', '20000-000')",
            "INSERT INTO clientes (nome, email, telefone, cidade, estado, cep) VALUES ('Ana Costa', 'ana@email.com', '(31) 77777-7777', 'Belo Horizonte', 'MG', '30000-000')"
        ];
        
        foreach ($insertClientes as $query) {
            if (mysqli_query($conexao, $query)) {
                echo "<p>✅ Cliente de exemplo inserido</p>";
            } else {
                echo "<p>⚠️ Aviso: " . mysqli_error($conexao) . "</p>";
            }
        }
    }
}

// Verificar pedidos
$checkPedidos = mysqli_query($conexao, "SELECT COUNT(*) as total FROM pedidos");
if ($checkPedidos) {
    $row = mysqli_fetch_assoc($checkPedidos);
    if ($row['total'] == 0) {
        echo "<p>📝 Inserindo dados de exemplo na tabela pedidos...</p>";
        
        $insertPedidos = [
            "INSERT INTO pedidos (cliente_id, valor_total, status, forma_pagamento, status_pagamento) VALUES (1, 129.90, 'Pedido Recebido', 'Pix', 'Pendente')",
            "INSERT INTO pedidos (cliente_id, valor_total, status, forma_pagamento, status_pagamento) VALUES (2, 89.90, 'Pagamento Confirmado', 'Cartão', 'Aprovado')",
            "INSERT INTO pedidos (cliente_id, valor_total, status, forma_pagamento, status_pagamento) VALUES (3, 199.90, 'Enviado', 'Boleto', 'Pago')",
            "INSERT INTO pedidos (cliente_id, valor_total, status, forma_pagamento, status_pagamento) VALUES (1, 59.90, 'Solicitado Reembolso', 'Pix', 'Pendente')"
        ];
        
        foreach ($insertPedidos as $query) {
            if (mysqli_query($conexao, $query)) {
                echo "<p>✅ Pedido de exemplo inserido</p>";
            } else {
                echo "<p>❌ Erro: " . mysqli_error($conexao) . "</p>";
            }
        }
    }
}

// Verificar produtos (necessário para itens_pedido)
$tableExists = mysqli_query($conexao, "SHOW TABLES LIKE 'produtos'");
if (mysqli_num_rows($tableExists) == 0) {
    echo "<p>❌ Tabela 'produtos' não encontrada. Criando...</p>";
    
    $createProdutos = "
    CREATE TABLE produtos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        descricao TEXT,
        preco DECIMAL(10,2) NOT NULL,
        estoque INT DEFAULT 0,
        categoria VARCHAR(100),
        imagem VARCHAR(255),
        status ENUM('ativo', 'inativo') DEFAULT 'ativo',
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (mysqli_query($conexao, $createProdutos)) {
        echo "<p>✅ Tabela 'produtos' criada com sucesso!</p>";
        
        // Inserir produtos exemplo
        $insertProdutos = [
            "INSERT INTO produtos (nome, preco, estoque, categoria) VALUES ('Produto A', 29.90, 50, 'Categoria 1')",
            "INSERT INTO produtos (nome, preco, estoque, categoria) VALUES ('Produto B', 39.90, 30, 'Categoria 2')",
            "INSERT INTO produtos (nome, preco, estoque, categoria) VALUES ('Produto C', 19.90, 20, 'Categoria 1')"
        ];
        
        foreach ($insertProdutos as $query) {
            mysqli_query($conexao, $query);
        }
        echo "<p>✅ Produtos de exemplo inseridos</p>";
    } else {
        echo "<p>❌ Erro ao criar tabela produtos: " . mysqli_error($conexao) . "</p>";
    }
}

// Criar tabela itens_pedido
$tableExists = mysqli_query($conexao, "SHOW TABLES LIKE 'itens_pedido'");
if (mysqli_num_rows($tableExists) == 0) {
    echo "<p>❌ Tabela 'itens_pedido' não encontrada. Criando...</p>";
    
    $createItens = "
    CREATE TABLE itens_pedido (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pedido_id INT NOT NULL,
        produto_id INT NOT NULL,
        quantidade INT NOT NULL,
        preco_unitario DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
        FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
    )";
    
    if (mysqli_query($conexao, $createItens)) {
        echo "<p>✅ Tabela 'itens_pedido' criada com sucesso!</p>";
        
        // Inserir alguns itens exemplo
        $insertItens = [
            "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES (1, 1, 2, 29.90)",
            "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES (1, 2, 1, 39.90)",
            "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES (2, 3, 1, 19.90)",
            "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES (3, 1, 1, 29.90)"
        ];
        
        foreach ($insertItens as $query) {
            if (mysqli_query($conexao, $query)) {
                echo "<p>✅ Item de pedido inserido</p>";
            } else {
                echo "<p>❌ Erro: " . mysqli_error($conexao) . "</p>";
            }
        }
        echo "<p>✅ Itens iniciais inseridos</p>";
    } else {
        echo "<p>❌ Erro ao criar tabela itens_pedido: " . mysqli_error($conexao) . "</p>";
    }
} else {
    echo "<p>✅ Tabela 'itens_pedido' já existe</p>";
}

// Verificar se itens_pedido está vazia e inserir dados se necessário
$checkItens = mysqli_query($conexao, "SELECT COUNT(*) as total FROM itens_pedido");
if ($checkItens) {
    $row = mysqli_fetch_assoc($checkItens);
    if ($row['total'] == 0) {
        echo "<p>📝 Inserindo itens de exemplo na tabela itens_pedido...</p>";
        
        // Primeiro verificar quais produtos existem
        $produtosExistentes = mysqli_query($conexao, "SELECT id FROM produtos ORDER BY id LIMIT 3");
        $produtos_ids = [];
        if ($produtosExistentes) {
            while ($produto = mysqli_fetch_assoc($produtosExistentes)) {
                $produtos_ids[] = $produto['id'];
            }
        }
        
        // Verificar quais pedidos existem
        $pedidosExistentes = mysqli_query($conexao, "SELECT id FROM pedidos ORDER BY id LIMIT 3");
        $pedidos_ids = [];
        if ($pedidosExistentes) {
            while ($pedido = mysqli_fetch_assoc($pedidosExistentes)) {
                $pedidos_ids[] = $pedido['id'];
            }
        }
        
        // Só inserir se temos produtos e pedidos
        if (!empty($produtos_ids) && !empty($pedidos_ids)) {
            $insertItens = [
                "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES ({$pedidos_ids[0]}, {$produtos_ids[0]}, 2, 29.90)",
                "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES ({$pedidos_ids[0]}, {$produtos_ids[1]}, 1, 39.90)",
            ];
            
            // Adicionar mais itens se temos mais produtos/pedidos
            if (isset($produtos_ids[2]) && isset($pedidos_ids[1])) {
                $insertItens[] = "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES ({$pedidos_ids[1]}, {$produtos_ids[2]}, 1, 19.90)";
                $insertItens[] = "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES ({$pedidos_ids[1]}, {$produtos_ids[0]}, 1, 29.90)";
            }
            
            if (isset($pedidos_ids[2])) {
                $insertItens[] = "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES ({$pedidos_ids[2]}, {$produtos_ids[1]}, 2, 39.90)";
                $insertItens[] = "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES ({$pedidos_ids[2]}, {$produtos_ids[0]}, 1, 29.90)";
            }
            
            foreach ($insertItens as $query) {
                if (mysqli_query($conexao, $query)) {
                    echo "<p>✅ Item de pedido inserido</p>";
                } else {
                    echo "<p>❌ Erro: " . mysqli_error($conexao) . "</p>";
                }
            }
            echo "<p>✅ Itens de exemplo inseridos</p>";
        } else {
            echo "<p>⚠️ Não foi possível inserir itens: produtos ou pedidos não encontrados</p>";
        }
    }
}

echo "<hr>";
echo "<h3>✅ Verificação Concluída!</h3>";
echo "<p>✅ Todas as tabelas foram verificadas e criadas se necessário.</p>";
echo "<p>✅ Dados de exemplo foram inseridos se as tabelas estavam vazias.</p>";
echo "<p>🎯 <strong><a href='orders.php'>← Voltar para Gestão de Pedidos</a></strong></p>";

// Mostrar resumo das tabelas
echo "<hr><h4>📊 Resumo das Tabelas:</h4>";
$tabelas = ['clientes', 'pedidos', 'produtos', 'itens_pedido'];
foreach ($tabelas as $tabela) {
    $count = mysqli_query($conexao, "SELECT COUNT(*) as total FROM $tabela");
    if ($count) {
        $row = mysqli_fetch_assoc($count);
        echo "<p>📋 <strong>$tabela</strong>: {$row['total']} registros</p>";
    }
}
?>