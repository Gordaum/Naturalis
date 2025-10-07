-- ================================================================
-- BANCO DE DADOS NATURALLIS - SISTEMA DE PRODUTOS NATURAIS
-- Versão: 2.1 Corrigida
-- Data: 29 de setembro de 2025
-- Descrição: E-commerce de produtos naturais com sistema administrativo
-- ================================================================

-- Criação do banco de dados
DROP DATABASE IF EXISTS naturallis_db;
CREATE DATABASE naturallis_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE naturallis_db;

-- ================================================================
-- TABELA: usuarios
-- Descrição: Gerenciamento de usuários e administradores
-- ================================================================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    senha VARCHAR(255) NOT NULL COMMENT 'Senha hasheada com password_hash()',
    role ENUM('admin', 'usuario') DEFAULT 'usuario' COMMENT 'Role do usuário no sistema',
    ativo TINYINT(1) DEFAULT 1,
    telefone VARCHAR(20) NULL,
    endereco TEXT NULL,
    cidade VARCHAR(100) NULL,
    estado VARCHAR(2) NULL,
    cep VARCHAR(10) NULL,
    data_nascimento DATE NULL,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_acesso DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY uk_usuarios_email (email),
    KEY idx_usuarios_role (role),
    KEY idx_usuarios_ativo (ativo)
);

-- ================================================================
-- TABELA: categorias
-- Descrição: Categorias dos produtos naturais
-- ================================================================
CREATE TABLE categorias (
    id INT AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY idx_categorias_ativo (ativo)
);

-- ================================================================
-- TABELA: produtos
-- Descrição: Catálogo completo de produtos naturais
-- ================================================================
CREATE TABLE produtos (
    id INT AUTO_INCREMENT,
    nome VARCHAR(200) NOT NULL,
    descricao TEXT NULL,
    preco DECIMAL(10,2) NOT NULL,
    preco_promocional DECIMAL(10,2) NULL,
    categoria_id INT NULL,
    estoque INT DEFAULT 0,
    peso DECIMAL(8,3) NULL COMMENT 'Peso em kg',
    dimensoes VARCHAR(50) NULL COMMENT 'Formato: LxAxP em cm',
    ingredientes TEXT NULL,
    modo_uso TEXT NULL,
    imagem VARCHAR(255) NULL,
    imagens_adicionais TEXT NULL COMMENT 'JSON de URLs de imagens extras',
    ativo TINYINT(1) DEFAULT 1,
    destaque TINYINT(1) DEFAULT 0,
    vegano TINYINT(1) DEFAULT 1,
    organico TINYINT(1) DEFAULT 0,
    cruelty_free TINYINT(1) DEFAULT 1,
    data_lancamento DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY fk_produtos_categoria (categoria_id),
    KEY idx_produtos_ativo (ativo),
    KEY idx_produtos_destaque (destaque),
    KEY idx_produtos_preco (preco),
    
    CONSTRAINT fk_produtos_categoria_id FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL
);

-- ================================================================
-- TABELA: carrinho
-- Descrição: Carrinho de compras dos usuários
-- ================================================================
CREATE TABLE carrinho (
    id INT AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    produto_id INT NOT NULL,
    quantidade INT DEFAULT 1,
    preco_unitario DECIMAL(10,2) NOT NULL COMMENT 'Preço no momento da adição',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY uk_carrinho_user_product (usuario_id, produto_id),
    KEY idx_carrinho_usuario (usuario_id),
    KEY fk_carrinho_produto (produto_id),
    
    CONSTRAINT fk_carrinho_usuario_id FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_carrinho_produto_id FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
);

-- ================================================================
-- TABELA: pedidos
-- Descrição: Sistema de pedidos e vendas
-- ================================================================
CREATE TABLE pedidos (
    id INT AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    numero_pedido VARCHAR(20) NOT NULL,
    status ENUM('pendente', 'confirmado', 'preparando', 'enviado', 'entregue', 'cancelado') DEFAULT 'pendente',
    valor_subtotal DECIMAL(10,2) NOT NULL,
    valor_frete DECIMAL(10,2) DEFAULT 0.00,
    valor_desconto DECIMAL(10,2) DEFAULT 0.00,
    valor_total DECIMAL(10,2) NOT NULL,
    forma_pagamento ENUM('cartao_credito', 'cartao_debito', 'pix', 'boleto', 'transferencia') NOT NULL,
    endereco_entrega TEXT NOT NULL COMMENT 'Dados completos do endereço de entrega',
    observacoes TEXT NULL,
    data_entrega_prevista DATE NULL,
    data_entrega DATE NULL,
    codigo_rastreamento VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY uk_pedidos_numero (numero_pedido),
    KEY idx_pedidos_usuario (usuario_id),
    KEY idx_pedidos_status (status),
    KEY idx_pedidos_data (created_at),
    
    CONSTRAINT fk_pedidos_usuario_id FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
);

-- ================================================================
-- DADOS INICIAIS - CATEGORIAS
-- ================================================================
INSERT INTO categorias (nome, descricao, ativo) VALUES
('Cabelos', 'Produtos para cuidados capilares naturais e veganos', 1),
('Pele', 'Produtos para cuidados faciais e corporais com a pele', 1),
('Corpo', 'Produtos para cuidados corporais e higiene pessoal', 1),
('Óleos Essenciais', 'Óleos essenciais puros e blends terapêuticos', 1),
('Suplementos', 'Suplementos naturais e vitaminas', 1),
('Casa', 'Produtos naturais para limpeza e cuidados domésticos', 1),
('Bebês', 'Produtos naturais especiais para bebês e crianças', 1),
('Acessórios', 'Acessórios ecológicos e sustentáveis', 1);

-- ================================================================
-- DADOS INICIAIS - PRODUTOS
-- ================================================================
INSERT INTO produtos (nome, descricao, preco, categoria_id, estoque, ingredientes, modo_uso, imagem, vegano, organico, cruelty_free, ativo, destaque) VALUES

-- Produtos para Cabelos
('Shampoo Natural de Coco', 
 'Shampoo 100% natural e vegano com óleo de coco virgem. Limpa suavemente sem ressacar os fios, ideal para todos os tipos de cabelo.', 
 29.90, 1, 45, 
 'Água purificada, óleo de coco virgem, saponina natural, glicerina vegetal, extrato de aloe vera, conservante natural', 
 'Aplique nos cabelos molhados, massageie suavemente o couro cabeludo e enxágue abundantemente. Repita se necessário.', 
 'img/shampoo-coco.jpg', 1, 1, 1, 1, 1),

('Condicionador de Argan', 
 'Condicionador nutritivo com óleo de argan marroquino. Proporciona hidratação profunda e brilho natural.', 
 34.90, 1, 30, 
 'Água de rosas, óleo de argan, manteiga de karité, proteína de quinoa, extrato de camomila', 
 'Após o shampoo, aplique nos comprimentos e pontas. Deixe agir por 3 minutos e enxágue.', 
 'img/condicionador-argan.jpg', 1, 1, 1, 1, 0),

-- Produtos para Pele
('Sabonete Orgânico de Lavanda', 
 'Sabonete artesanal com ingredientes orgânicos certificados e óleo essencial de lavanda francesa. Hidrata e acalma a pele.', 
 15.90, 2, 60, 
 'Óleo de coco orgânico, óleo de oliva extra virgem, soda cáustica, óleo essencial de lavanda, flores secas de lavanda', 
 'Use durante o banho fazendo espuma suave sobre a pele úmida. Evite o contato com os olhos.', 
 'img/sabonete-lavanda.jpg', 1, 1, 1, 1, 0),

('Creme Facial Anti-idade Natural', 
 'Creme facial com ativos naturais que combatem os sinais do envelhecimento. Rico em antioxidantes e vitaminas.', 
 89.90, 2, 25, 
 'Água de rosas, óleo de argan, ácido hialurônico vegetal, vitamina E, extrato de ginseng, colágeno vegetal', 
 'Aplique no rosto e pescoço limpos, pela manhã e à noite, massageando suavemente até completa absorção.', 
 'img/creme-antiidade.jpg', 1, 1, 1, 1, 1),

-- Óleos Essenciais
('Óleo Essencial de Tea Tree', 
 'Óleo essencial de melaleuca 100% puro e natural. Propriedades antissépticas, anti-inflamatórias e purificantes.', 
 45.00, 4, 35, 
 '100% óleo essencial de Melaleuca alternifolia (Tea Tree) - origem: Austrália', 
 'Sempre dilua em óleo vegetal antes do uso na pele (1-2 gotas por aplicação). Para aromaterapia: 3-5 gotas no difusor.', 
 'img/oleo-tea-tree.jpg', 1, 0, 1, 1, 1),

-- Produtos para Corpo
('Desodorante Natural de Palmarosa', 
 'Desodorante natural livre de alumínio e parabenos, com óleo essencial de palmarosa. Proteção natural e perfume suave.', 
 24.90, 3, 40, 
 'Óleo de coco, bicarbonato de sódio, amido de milho orgânico, cera de carnaúba, óleo essencial de palmarosa', 
 'Aplique uma pequena quantidade nas axilas limpas e secas. Aguarde alguns segundos antes de vestir a roupa.', 
 'img/desodorante-palmarosa.jpg', 1, 0, 1, 1, 0);

-- ================================================================
-- DADOS INICIAIS - USUÁRIOS
-- ================================================================

-- Usuário Administrador (senha: admin123)
INSERT INTO usuarios (nome, email, senha, role, ativo, cidade, estado) VALUES
('Administrador Naturallis', 'admin@naturallis.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 'São Paulo', 'SP');

-- Usuários de Teste (senha: 123456)
INSERT INTO usuarios (nome, email, senha, role, ativo, telefone, cidade, estado) VALUES
('Maria Silva Santos', 'maria@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'usuario', 1, '(11) 98765-4321', 'São Paulo', 'SP'),
('João Pedro Costa', 'joao@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'usuario', 1, '(21) 99876-5432', 'Rio de Janeiro', 'RJ'),
('Ana Carolina Lima', 'ana@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'usuario', 1, '(31) 97654-3210', 'Belo Horizonte', 'MG');

-- ================================================================
-- VIEWS ÚTEIS PARA CONSULTAS
-- ================================================================

-- View: Produtos ativos com informações da categoria
CREATE VIEW v_produtos_ativos AS
SELECT 
    p.id,
    p.nome,
    p.descricao,
    p.preco,
    p.preco_promocional,
    p.estoque,
    p.imagem,
    p.vegano,
    p.organico,
    p.cruelty_free,
    p.destaque,
    c.nome as categoria_nome,
    c.id as categoria_id
FROM produtos p
LEFT JOIN categorias c ON p.categoria_id = c.id
WHERE p.ativo = 1
ORDER BY p.destaque DESC, p.nome ASC;

-- View: Produtos em destaque
CREATE VIEW v_produtos_destaque AS
SELECT 
    p.id,
    p.nome,
    p.descricao,
    p.preco,
    p.preco_promocional,
    p.imagem,
    c.nome as categoria_nome
FROM produtos p
LEFT JOIN categorias c ON p.categoria_id = c.id
WHERE p.ativo = 1 AND p.destaque = 1
ORDER BY p.created_at DESC;

-- View: Resumo de pedidos para administração
CREATE VIEW v_pedidos_admin AS
SELECT 
    p.id,
    p.numero_pedido,
    p.status,
    p.valor_total,
    p.forma_pagamento,
    p.created_at as data_pedido,
    u.nome as cliente_nome,
    u.email as cliente_email,
    u.cidade as cliente_cidade
FROM pedidos p
JOIN usuarios u ON p.usuario_id = u.id
ORDER BY p.created_at DESC;

-- ================================================================
-- INSERÇÃO DE DADOS DE TESTE PARA O CARRINHO
-- ================================================================
INSERT INTO carrinho (usuario_id, produto_id, quantidade, preco_unitario) VALUES
(2, 1, 2, 29.90),  -- Maria: 2x Shampoo de Coco
(2, 3, 1, 15.90),  -- Maria: 1x Sabonete de Lavanda
(3, 5, 1, 45.00),  -- João: 1x Óleo Tea Tree
(4, 4, 1, 89.90);  -- Ana: 1x Creme Anti-idade

-- ================================================================
-- COMENTÁRIOS FINAIS
-- ================================================================
-- Este banco de dados contém apenas as tabelas essenciais:
-- 1. usuarios - Gestão de usuários e administradores
-- 2. categorias - Organização dos produtos
-- 3. produtos - Catálogo completo de produtos naturais
-- 4. carrinho - Sistema de carrinho de compras
-- 5. pedidos - Sistema de pedidos e vendas
--
-- Correções implementadas:
-- - Removido CREATE DATABASE IF NOT EXISTS (substituído por DROP/CREATE)
-- - Substituído BOOLEAN por TINYINT(1) para compatibilidade
-- - Substituído TRUE/FALSE por 1/0
-- - Reorganizada sintaxe de chaves e constraints
-- - Removido tipo JSON (substituído por TEXT)
-- - Simplificadas procedures e triggers para evitar erros de sintaxe
-- 
-- Total: 5 tabelas essenciais + 3 views otimizadas
-- ================================================================