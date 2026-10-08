CREATE DATABASE IF NOT EXISTS negociacoes
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE negociacoes;


-- =========================================================
-- PERFIS
-- =========================================================

CREATE TABLE IF NOT EXISTS perfis (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;


-- =========================================================
-- USUÁRIOS
-- =========================================================

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    perfil_id INT UNSIGNED NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,

    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (perfil_id)
        REFERENCES perfis(id)
) ENGINE=InnoDB;


-- =========================================================
-- CLIENTES
-- Mantida por compatibilidade com a estrutura antiga.
-- O novo fluxo de atendimento utiliza a inscrição
-- diretamente e não depende desta tabela.
-- =========================================================

CREATE TABLE IF NOT EXISTS clientes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    nome VARCHAR(150) NOT NULL,

    documento VARCHAR(30) NULL UNIQUE,

    telefone VARCHAR(30) NULL,

    email VARCHAR(150) NULL,

    ativo TINYINT(1) NOT NULL DEFAULT 1,

    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_cliente_nome(nome)
) ENGINE=InnoDB;


-- =========================================================
-- ATENDIMENTOS
-- Novo fluxo principal do sistema.
-- Cada registro representa um atendimento realizado.
-- =========================================================

CREATE TABLE IF NOT EXISTS atendimentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    usuario_id INT UNSIGNED NOT NULL,

    data_atendimento DATE NOT NULL,

    inscricao VARCHAR(50) NOT NULL,

    parcelas_em_atraso INT UNSIGNED NOT NULL,

    valor_negociado DECIMAL(12,2) NOT NULL,

    valor_recebido DECIMAL(12,2) NULL,

    data_pagamento DATE NULL,

    observacoes TEXT NULL,

    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

-- =========================================================
-- NEGOCIAÇÕES
-- ESTRUTURA LEGADA.
-- Mantida temporariamente porque o fluxo antigo de
-- pagamento ainda utiliza esta tabela.
-- =========================================================

CREATE TABLE IF NOT EXISTS negociacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    cliente_id INT UNSIGNED NOT NULL,

    usuario_id INT UNSIGNED NOT NULL,

    data_contato DATE NOT NULL,

    valor_original DECIMAL(12,2) NOT NULL,

    desconto DECIMAL(12,2) NOT NULL DEFAULT 0,

    valor_final DECIMAL(12,2) NOT NULL,

    data_combinada DATE NULL,

    data_pagamento DATE NULL,

    valor_pago DECIMAL(12,2) NULL,

    status ENUM(
        'PENDENTE',
        'PAGO',
        'VENCIDO',
        'CANCELADO'
    ) NOT NULL DEFAULT 'PENDENTE',

    descricao TEXT NULL,

    observacoes TEXT NULL,

    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (cliente_id)
        REFERENCES clientes(id),

    FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id),

    INDEX idx_neg_data(data_contato),

    INDEX idx_neg_status(status)
) ENGINE=InnoDB;


-- =========================================================
-- AUDITORIA
-- =========================================================

CREATE TABLE IF NOT EXISTS auditoria (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    usuario_id INT UNSIGNED NULL,

    acao VARCHAR(50) NOT NULL,

    tabela_afetada VARCHAR(80) NOT NULL,

    registro_id INT UNSIGNED NULL,

    dados_anteriores JSON NULL,

    dados_novos JSON NULL,

    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- =========================================================
-- PERFIS PADRÃO
-- =========================================================

INSERT INTO perfis(nome)
VALUES
    ('ADMIN'),
    ('SUPERVISOR'),
    ('OPERADOR')
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome);


-- =========================================================
-- USUÁRIO ADMINISTRADOR
-- Senha: password
-- =========================================================

INSERT INTO usuarios(
    nome,
    email,
    senha,
    perfil_id
)

SELECT
    'Administrador',
    'admin@local.test',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC9rQ4dH8yP2H8xj8O6',
    id

FROM perfis

WHERE nome = 'ADMIN'

AND NOT EXISTS (
    SELECT 1
    FROM usuarios
    WHERE email = 'admin@local.test'
);


-- =========================================================
-- USUÁRIO SUPERVISOR
-- Senha: password
-- =========================================================

INSERT INTO usuarios(
    nome,
    email,
    senha,
    perfil_id
)

SELECT
    'Supervisor',
    'supervisor@local.test',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC9rQ4dH8yP2H8xj8O6',
    id

FROM perfis

WHERE nome = 'SUPERVISOR'

AND NOT EXISTS (
    SELECT 1
    FROM usuarios
    WHERE email = 'supervisor@local.test'
);


-- =========================================================
-- USUÁRIO OPERADOR
-- Senha: password
-- =========================================================

INSERT INTO usuarios(
    nome,
    email,
    senha,
    perfil_id
)

SELECT
    'Operador',
    'operador@local.test',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC9rQ4dH8yP2H8xj8O6',
    id

FROM perfis

WHERE nome = 'OPERADOR'

AND NOT EXISTS (
    SELECT 1
    FROM usuarios
    WHERE email = 'operador@local.test'
);


-- =========================================================
-- CLIENTE DE EXEMPLO
-- Estrutura legada.
-- =========================================================

INSERT INTO clientes(
    nome,
    documento,
    telefone,
    email
)

SELECT
    'Cliente Exemplo',
    '00000000000',
    '(22) 99999-9999',
    'cliente@local.test'

WHERE NOT EXISTS (
    SELECT 1
    FROM clientes
    WHERE documento = '00000000000'
);