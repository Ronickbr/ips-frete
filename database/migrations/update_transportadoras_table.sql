-- Migration para atualizar a tabela transportadoras com novos campos
-- Data: 2024-01-20

USE sistema_fretes;

-- Adicionar novas colunas necessárias
ALTER TABLE transportadoras 
ADD COLUMN peso_ate_50kg DECIMAL(10,2) NULL AFTER nome,
ADD COLUMN peso_ate_300kg DECIMAL(10,2) NULL AFTER peso_ate_200kg,
ADD COLUMN pedagio DECIMAL(10,2) NULL AFTER frete_minimo,
ADD COLUMN frete_valor_percentual DECIMAL(5,2) NULL AFTER pedagio,
ADD COLUMN fator_peso_cubico DECIMAL(10,2) NULL AFTER frete_valor_percentual;

-- Renomear colunas existentes para padronizar
ALTER TABLE transportadoras 
CHANGE COLUMN peso_ate_30kg peso_ate_50kg_old DECIMAL(10,2) NULL,
CHANGE COLUMN frete_valor frete_valor_old DECIMAL(5,2) NULL,
CHANGE COLUMN pedagio_peso_cubico fator_peso_cubico_old DECIMAL(10,2) NULL,
CHANGE COLUMN ativa ativo TINYINT(1) DEFAULT 1;

-- Migrar dados das colunas antigas para as novas (se necessário)
UPDATE transportadoras SET 
    peso_ate_50kg = COALESCE(peso_ate_50kg_old, 0),
    frete_valor_percentual = COALESCE(frete_valor_old, 0),
    fator_peso_cubico = COALESCE(fator_peso_cubico_old, 0)
WHERE peso_ate_50kg_old IS NOT NULL OR frete_valor_old IS NOT NULL OR fator_peso_cubico_old IS NOT NULL;

-- Remover colunas antigas após migração
ALTER TABLE transportadoras 
DROP COLUMN peso_ate_50kg_old,
DROP COLUMN frete_valor_old,
DROP COLUMN fator_peso_cubico_old;

-- Verificar estrutura final
DESCRIBE transportadoras;