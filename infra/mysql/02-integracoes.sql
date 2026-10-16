SET NAMES utf8mb4;

USE nexus;

CREATE TABLE IF NOT EXISTS integracao_execucoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id INT NOT NULL,
  criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_exec_empresa_data (empresa_id, criada_em),
  CONSTRAINT fk_exec_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id)
);

CREATE TABLE IF NOT EXISTS integracao_resultados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  execucao_id INT NOT NULL,
  provedor VARCHAR(32) NOT NULL,
  cenario VARCHAR(16) NOT NULL,
  situacao VARCHAR(32) NOT NULL,
  http_status SMALLINT NULL,
  duracao_ms INT NOT NULL,
  erro VARCHAR(200) NULL,
  score SMALLINT NULL,
  pendencias INT NULL,
  protestos INT NULL,
  atualizado_em DATE NULL,
  CONSTRAINT fk_result_exec FOREIGN KEY (execucao_id) REFERENCES integracao_execucoes(id) ON DELETE CASCADE,
  CONSTRAINT uq_result_exec_prov UNIQUE (execucao_id, provedor)
);
