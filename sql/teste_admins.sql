-- Testar administradores na tabela teste_dz
-- Execute este SQL para verificar os dados existentes

SELECT 
    id,
    nome,
    email,
    foto_perfil,
    'Online' as status_simulado
FROM teste_dz 
ORDER BY nome ASC;

-- Para adicionar mais administradores de teste (opcional):
/*
INSERT INTO teste_dz (nome, email, senha) VALUES
('Ana Helena Santos', 'ana@dz.com', '$2y$10$example_hash_here'),
('Carlos Silva', 'carlos@dz.com', '$2y$10$example_hash_here'),
('Maria Fernanda', 'maria@dz.com', '$2y$10$example_hash_here');
*/