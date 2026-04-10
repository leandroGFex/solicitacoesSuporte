-- =============================================================
-- GRUPO FLEX - KANBAN OPERACIONAL
-- Banco de Dados: ezyro_41229897_banco_soli
-- Gerado em: 2026-02-23
-- Importar via phpMyAdmin no InfinityFree
-- =============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "-03:00";

-- -------------------------------------------------------------
-- Tabela: users
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100)                  NOT NULL,
  `email`      VARCHAR(150)                  NOT NULL UNIQUE,
  `password`   VARCHAR(255)                  NOT NULL,
  `role`       ENUM('admin','user')          DEFAULT 'user',
  `active`     TINYINT(1)                    DEFAULT 1,
  `created_at` TIMESTAMP                     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: columns_kanban
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `columns_kanban` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100)  NOT NULL,
  `color`      VARCHAR(20)   DEFAULT '#00897B',
  `icon`       VARCHAR(50)   DEFAULT 'label',
  `position`   INT           DEFAULT 0,
  `created_at` TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: cards
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cards` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `column_id`           INT           NULL,
  `title`               VARCHAR(255)  NOT NULL,
  `description`         TEXT,
  `category`            ENUM('cartao','tag','pos','rastreador') DEFAULT 'cartao',
  `pos_request_type`    VARCHAR(50)   DEFAULT NULL COMMENT 'Envio, Retirada ou Reverso',
  `pos_reason`          TEXT          DEFAULT NULL COMMENT 'Motivo para retirada ou reverso',
  `company_name`        VARCHAR(150),
  `remessa`             VARCHAR(100),
  `placa`               VARCHAR(30)   COMMENT 'Primeira placa (compat. legado)',
  `card_number`         VARCHAR(100)  COMMENT 'Número do cartão principal (compat. legado)',
  `extra_data`          TEXT          COMMENT 'JSON com múltiplas placas/seriais/ICCIDs por categoria',
  `client_name`         VARCHAR(150),
  `client_email`        VARCHAR(150),
  `cnpj`                VARCHAR(25),
  `address`             TEXT,
  `tracking_code`       VARCHAR(100),
  `reverse_tracking_code` VARCHAR(100) DEFAULT NULL,
  `tracking_status`     TEXT,
  `tracking_updated_at` TIMESTAMP     NULL,
  `deadline`            DATE          NULL,
  `deadline_met`        TINYINT(1)    DEFAULT NULL COMMENT '1=cumprido, 0=atrasado, NULL=aberto',
  `priority`            ENUM('baixa','media','alta') DEFAULT 'media',
  `position`            INT           DEFAULT 0,
  `created_by`          INT           NULL,
  `created_from_email`  TINYINT(1)    DEFAULT 0,
  `is_archived`         TINYINT(1)    DEFAULT 0,
  `withdrawal_declaration` VARCHAR(255) DEFAULT NULL,
  `created_at`          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`column_id`)   REFERENCES `columns_kanban`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: comments
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comments` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `card_id`    INT   NOT NULL,
  `user_id`    INT   NULL,
  `user_name`  VARCHAR(100),
  `content`    TEXT  NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`card_id`) REFERENCES `cards`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: tracking_history
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tracking_history` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `card_id`      INT          NOT NULL,
  `tracking_code`VARCHAR(100),
  `status_json`  TEXT,
  `checked_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`card_id`) REFERENCES `cards`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: email_log
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_log` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `message_id`   VARCHAR(255)  UNIQUE,
  `subject`      VARCHAR(255),
  `sender`       VARCHAR(150),
  `card_id`      INT           NULL,
  `processed_at` TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- DADOS INICIAIS
-- =============================================================

-- Colunas padrão do Kanban (igual aos prints)
INSERT INTO `columns_kanban` (`name`, `color`, `icon`, `position`) VALUES
  ('Recebido',         '#00897B', 'inbox',        1),
  ('Em Andamento',     '#00897B', 'settings',     2),
  ('Aguardando Envio', '#00897B', 'schedule',     3),
  ('Enviado',          '#00897B', 'send',         4),
  ('Retornos',         '#E64A19', 'replay',       5),
  ('Entregue',         '#2E7D32', 'check_circle', 6);

-- Usuário administrador padrão
-- E-mail: admin@grupoflex.com.br
-- Senha:  FlexAdmin2026
-- ⚠️ TROQUE A SENHA APÓS O PRIMEIRO LOGIN!
INSERT INTO `users` (`name`, `email`, `password`, `role`) VALUES
  ('Administrador', 'admin@grupoflex.com.br',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
   'admin');
-- Nota: o hash acima corresponde à senha "password".
-- Use o install.php para gerar um admin com senha própria (recomendado).

-- =============================================================
-- ATUALIZAÇÃO — rodar apenas se o banco já existia antes
-- (ignorar se está importando do zero)
-- =============================================================
ALTER TABLE `cards`
  ADD COLUMN IF NOT EXISTS `category`    ENUM('cartao','tag','pos','rastreador') DEFAULT 'cartao' AFTER `description`,
  ADD COLUMN IF NOT EXISTS `card_number` VARCHAR(100) COMMENT 'Número do cartão principal' AFTER `placa`,
  ADD COLUMN IF NOT EXISTS `extra_data`  TEXT         COMMENT 'JSON com múltiplas placas/seriais por categoria' AFTER `card_number`;

-- -------------------------------------------------------------
-- Ferramentas Globais (POS e Rastreadores)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pos_equipments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `model` VARCHAR(100) NOT NULL,
  `serial_number` VARCHAR(100) NOT NULL UNIQUE,
  `chip_iccid` VARCHAR(50),
  `status` ENUM('Estoque', 'Enviado', 'Recebido', 'Defeito') DEFAULT 'Estoque',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pos_id` INT NOT NULL,
  `card_id` INT NULL,
  `action` ENUM('Cadastro', 'Edicao', 'Envio', 'Recebimento', 'Manutencao') NOT NULL,
  `problem_description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`pos_id`) REFERENCES `pos_equipments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`card_id`) REFERENCES `cards`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `trackers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `model` VARCHAR(100),
  `serial_number` VARCHAR(100) NOT NULL UNIQUE,
  `chip_iccid` VARCHAR(50),
  `status` ENUM('Estoque', 'Enviado', 'Recebido', 'Defeito') DEFAULT 'Estoque',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracker_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tracker_id` INT NOT NULL,
  `card_id` INT NULL,
  `action` ENUM('Cadastro', 'Edicao', 'Envio', 'Recebimento', 'Manutencao') NOT NULL,
  `problem_description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`tracker_id`) REFERENCES `trackers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`card_id`) REFERENCES `cards`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Ferramenta Global (Chips / SIM Cards)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sim_cards` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `phone_number` VARCHAR(50) NOT NULL UNIQUE,
  `iccid` VARCHAR(100) NOT NULL UNIQUE,
  `carrier` VARCHAR(50),
  `status` ENUM('Estoque', 'Em Uso', 'Cancelado', 'Defeito') DEFAULT 'Estoque',
  `motivo` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sim_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sim_id` INT NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `problem_description` TEXT,
  `modified_by` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sim_id`) REFERENCES `sim_cards`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: inventory_items (Estoque Geral)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `category` VARCHAR(100) NULL,
  `description` TEXT NULL,
  `quantity` INT DEFAULT 0,
  `min_quantity` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: inventory_exits (Saídas de Estoque)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_exits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_id` INT NOT NULL,
  `quantity_used` INT NOT NULL,
  `user_name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: inventory_history (Log de Histórico do Estoque)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_id` INT NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `quantity_change` INT NOT NULL DEFAULT 0,
  `modified_by` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Módulo de Manuais (base de conhecimento)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `manuals` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `title`      VARCHAR(255) NOT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `manual_steps` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `manual_id`  INT NOT NULL,
  `position`   INT DEFAULT 0,
  `content`    LONGTEXT NOT NULL COMMENT 'HTML gerado pelo Quill.js',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`manual_id`) REFERENCES `manuals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `manual_images` (
  `id`       INT AUTO_INCREMENT PRIMARY KEY,
  `step_id`  INT NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `caption`  VARCHAR(255) NULL,
  FOREIGN KEY (`step_id`) REFERENCES `manual_steps`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Módulo de Procedimentos Empresas
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `company_procedures` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(255) NOT NULL,
  `observation` TEXT NULL,
  `created_by`  INT NULL,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `procedure_contacts` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `procedure_id` INT NOT NULL,
  `name`         VARCHAR(150) NOT NULL,
  `phone`        VARCHAR(50) NULL,
  `email`        VARCHAR(150) NULL,
  FOREIGN KEY (`procedure_id`) REFERENCES `company_procedures`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `procedure_managers` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `procedure_id` INT NOT NULL,
  `name`         VARCHAR(150) NOT NULL,
  `phone`        VARCHAR(50) NULL,
  `observation`  TEXT NULL,
  FOREIGN KEY (`procedure_id`) REFERENCES `company_procedures`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `procedure_items` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `procedure_id` INT NOT NULL,
  `type`         ENUM('trava', 'motorista') NOT NULL,
  `label`        VARCHAR(150) NOT NULL,
  `enabled`      TINYINT(1) DEFAULT 0,
  `description`  TEXT NULL,
  FOREIGN KEY (`procedure_id`) REFERENCES `company_procedures`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
