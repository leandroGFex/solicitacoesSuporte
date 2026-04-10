<?php
/**
 * Migration Helper - Mantém o banco de dados atualizado automaticamente
 */
function runAutoMigration($db) {
    try {
        // Colunas para a tabela cards
        $cardCols = [
            'pos_request_type' => "VARCHAR(50) DEFAULT NULL",
            'pos_reason' => "TEXT DEFAULT NULL",
            'card_number' => "VARCHAR(100) DEFAULT NULL",
            'extra_data' => "TEXT DEFAULT NULL",
            'reverse_tracking_code' => "VARCHAR(100) DEFAULT NULL",
            'is_archived' => "TINYINT(1) DEFAULT 0",
            'withdrawal_declaration' => "VARCHAR(255) DEFAULT NULL",
            'correios_prepost_id' => "VARCHAR(100) DEFAULT NULL",
            'category' => "VARCHAR(50) DEFAULT 'cartao'"
        ];

        foreach ($cardCols as $col => $def) {
            try { $db->exec("ALTER TABLE `cards` ADD COLUMN `$col` $def"); } catch (Exception $e) {}
        }

        // Colunas para a tabela columns_kanban
        try { 
            $db->exec("ALTER TABLE `columns_kanban` ADD COLUMN `category` VARCHAR(50) DEFAULT 'cartao'");
            // Atribuir categoria para colunas existentes que não tem categoria
            $db->exec("UPDATE `columns_kanban` SET `category` = 'cartao' WHERE `category` IS NULL OR `category` = ''");
        } catch (Exception $e) {}

        // Tabela de Histórico
        $db->exec("CREATE TABLE IF NOT EXISTS `card_history` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `card_id` INT NOT NULL,
            `user_id` INT,
            `user_name` VARCHAR(100),
            `action` VARCHAR(255) NOT NULL,
            `old_col_name` VARCHAR(100),
            `new_col_name` VARCHAR(100),
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`card_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Tabelas de Automação
        $db->exec("CREATE TABLE IF NOT EXISTS `automation_rules` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `category` VARCHAR(50) NOT NULL,
            `trigger_event` VARCHAR(50) NOT NULL,
            `trigger_config` TEXT,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $db->exec("CREATE TABLE IF NOT EXISTS `automation_actions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `rule_id` INT NOT NULL,
            `action_type` VARCHAR(50) NOT NULL,
            `action_config` TEXT,
            `position` INT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Tentar adicionar FK separadamente para não quebrar a criação da tabela em ambientes restritos
        try {
            $db->exec("ALTER TABLE `automation_actions` ADD CONSTRAINT `fk_rule` FOREIGN KEY (`rule_id`) REFERENCES `automation_rules`(`id`) ON DELETE CASCADE");
        } catch (Exception $e) {}

    } catch (Exception $e) {
        error_log("Migration Error: " . $e->getMessage());
    }
}
