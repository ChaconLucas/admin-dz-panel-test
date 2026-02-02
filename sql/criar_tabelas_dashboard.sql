-- Script SQL para criação das tabelas do Dashboard D&Z Admin
-- Execute este script no MySQL/phpMyAdmin na segunda-feira

-- Tabela de Clientes
CREATE TABLE IF NOT EXISTS clientes (
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
);

-- Tabela de Produtos 
CREATE TABLE IF NOT EXISTS produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT,
    preco DECIMAL(10,2) NOT NULL,
    estoque INT DEFAULT 0,
    categoria VARCHAR(100),
    imagem VARCHAR(255),
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Pedidos
CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    status ENUM('pendente', 'processando', 'enviado', 'entregue', 'cancelado') DEFAULT 'pendente',
    data_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_entrega DATE NULL,
    observacoes TEXT,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
);

-- Tabela de Itens do Pedido
CREATE TABLE IF NOT EXISTS itens_pedido (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    produto_id INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
);

-- Tabela de Status de Fluxo (para Gestão de Fluxo)
CREATE TABLE IF NOT EXISTS status_fluxo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    cor_hex VARCHAR(7) NOT NULL DEFAULT '#ff00d4',
    baixa_estoque TINYINT(1) DEFAULT 0,
    bloquear_edicao TINYINT(1) DEFAULT 0,
    gerar_logistica TINYINT(1) DEFAULT 0,
    notificar TINYINT(1) DEFAULT 0,
    mensagem_template TEXT,
    ordem INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Inserir dados de exemplo (opcional - remova se não quiser dados de teste)
INSERT INTO clientes (nome, email, telefone, cidade, estado) VALUES
('Maria Silva', 'maria@email.com', '(11) 99999-9999', 'São Paulo', 'SP'),
('João Santos', 'joao@email.com', '(21) 88888-8888', 'Rio de Janeiro', 'RJ'),
('Ana Costa', 'ana@email.com', '(31) 77777-7777', 'Belo Horizonte', 'MG');

INSERT INTO produtos (nome, preco, estoque, categoria) VALUES
('Produto A', 29.90, 50, 'Categoria 1'),
('Produto B', 39.90, 30, 'Categoria 2'),
('Produto C', 19.90, 0, 'Categoria 1'),
('Produto D', 49.90, 25, 'Categoria 3');

INSERT INTO pedidos (cliente_id, valor_total, status) VALUES
(1, 59.80, 'entregue'),
(2, 39.90, 'pendente'),
(3, 89.70, 'processando'),
(1, 29.90, 'enviado');

INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES
(1, 1, 2, 29.90),
(2, 2, 1, 39.90),
(3, 1, 1, 29.90),
(3, 4, 1, 49.90),
(4, 1, 1, 29.90);