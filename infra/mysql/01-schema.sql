SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS nexus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nexus;

CREATE TABLE empresas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo_demo VARCHAR(24) NOT NULL UNIQUE,
  razao_social VARCHAR(140) NOT NULL,
  setor VARCHAR(90) NOT NULL,
  cidade VARCHAR(90) NOT NULL,
  uf CHAR(2) NOT NULL,
  situacao VARCHAR(24) NOT NULL
);

CREATE TABLE pessoas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome_ficticio VARCHAR(120) NOT NULL
);

CREATE TABLE participacoes_societarias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id INT NOT NULL,
  pessoa_id INT NOT NULL,
  percentual DECIMAL(5,2) NOT NULL,
  CONSTRAINT fk_part_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  CONSTRAINT fk_part_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas(id),
  CONSTRAINT uq_part UNIQUE (empresa_id, pessoa_id),
  CONSTRAINT ck_percent CHECK (percentual >= 0 AND percentual <= 100)
);

CREATE TABLE vinculos_empresariais (
  id INT AUTO_INCREMENT PRIMARY KEY,
  empresa_origem_id INT NOT NULL,
  empresa_destino_id INT NOT NULL,
  tipo VARCHAR(50) NOT NULL,
  descricao VARCHAR(220) NOT NULL,
  CONSTRAINT fk_vinculo_origem FOREIGN KEY (empresa_origem_id) REFERENCES empresas(id),
  CONSTRAINT fk_vinculo_destino FOREIGN KEY (empresa_destino_id) REFERENCES empresas(id),
  CONSTRAINT ck_distinct_company CHECK (empresa_origem_id <> empresa_destino_id)
);

CREATE TABLE fontes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(90) NOT NULL UNIQUE,
  categoria VARCHAR(60) NOT NULL
);

CREATE TABLE indicadores_financeiros (
  id INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id INT NOT NULL,
  fonte_id INT NOT NULL,
  score SMALLINT NULL,
  pendencias INT NOT NULL DEFAULT 0,
  protestos INT NOT NULL DEFAULT 0,
  atualizado_em DATE NOT NULL,
  CONSTRAINT fk_ind_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  CONSTRAINT fk_ind_fonte FOREIGN KEY (fonte_id) REFERENCES fontes(id),
  CONSTRAINT uq_ind_empresa_fonte UNIQUE (empresa_id, fonte_id),
  CONSTRAINT ck_ind_score CHECK (score IS NULL OR score BETWEEN 0 AND 1000)
);

CREATE TABLE registros_juridicos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id INT NOT NULL,
  fonte_id INT NOT NULL,
  tipo VARCHAR(80) NOT NULL,
  resumo TEXT NOT NULL,
  situacao VARCHAR(60) NOT NULL,
  data_registro DATE NOT NULL,
  CONSTRAINT fk_legal_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  CONSTRAINT fk_legal_fonte FOREIGN KEY (fonte_id) REFERENCES fontes(id)
);

CREATE TABLE consultas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id INT NOT NULL,
  finalidade VARCHAR(140) NOT NULL,
  criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_consulta_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id)
);

INSERT INTO empresas (id, codigo_demo, razao_social, setor, cidade, uf, situacao) VALUES
  (1, 'DEMO-EMP-001', 'Aurora Tecnologia Ltda.', 'Tecnologia', 'Curitiba', 'PR', 'Ativa'),
  (2, 'DEMO-EMP-002', 'Horizonte Logística S.A.', 'Logística', 'Londrina', 'PR', 'Ativa'),
  (3, 'DEMO-EMP-003', 'Atlas Comercial Ltda.', 'Comércio', 'Joinville', 'SC', 'Ativa');

INSERT INTO pessoas (id, nome_ficticio) VALUES
  (1, 'Marina Vale'), (2, 'Lucas Prado'), (3, 'Rafaela Torres'), (4, 'Daniel Monteiro');

INSERT INTO participacoes_societarias (empresa_id, pessoa_id, percentual) VALUES
  (1, 1, 60.00), (1, 2, 40.00),
  (2, 2, 20.00), (2, 3, 80.00),
  (3, 4, 55.00), (3, 1, 45.00);

INSERT INTO vinculos_empresariais (empresa_origem_id, empresa_destino_id, tipo, descricao) VALUES
  (1, 2, 'Sócio em comum', 'Lucas Prado participa das duas sociedades.'),
  (1, 3, 'Sócia em comum', 'Marina Vale participa das duas sociedades.');

INSERT INTO fontes (id, nome, categoria) VALUES
  (1, 'Bureau A (simulado)', 'Crédito'),
  (2, 'Bureau B (simulado)', 'Crédito'),
  (3, 'Base jurídica fictícia', 'Jurídico');

INSERT INTO indicadores_financeiros (empresa_id, fonte_id, score, pendencias, protestos, atualizado_em) VALUES
  (1, 1, 740, 1, 0, '2026-09-21'),
  (1, 2, 698, 0, 0, '2026-09-20'),
  (2, 1, 510, 3, 1, '2026-09-22'),
  (2, 2, 575, 2, 0, '2026-09-19'),
  (3, 1, 865, 0, 0, '2026-09-23');

INSERT INTO registros_juridicos (empresa_id, fonte_id, tipo, resumo, situacao, data_registro) VALUES
  (1, 3, 'Ação cível', 'Registro de demonstração sobre divergência contratual.', 'Em andamento', '2026-07-12'),
  (1, 3, 'Ação trabalhista', 'Registro fictício de processo já concluído.', 'Encerrado', '2025-11-03'),
  (2, 3, 'Ação cível', 'Registro fictício sobre cobrança comercial.', 'Em andamento', '2026-06-18');
