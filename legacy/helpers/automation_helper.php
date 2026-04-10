<?php
// =============================================================
// HELPER - AUTOMATIONS ENGINE
// =============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/card_helper.php';

class AutomationEngine {
    
    /**
     * Processar gatilhos para um card específico
     * 
     * @param int $cardId ID do card
     * @param string $event Evento ocorrido (move_to_column, card_created, field_updated, date_reached)
     * @param array $context Dados extras do evento (ex: column_id, field_name)
     */
    public static function process($cardId, $event, $context = []) {
        $db = getDB();
        
        // 1. Buscar card atual
        $stmtCard = $db->prepare("SELECT * FROM cards WHERE id = ?");
        $stmtCard->execute([$cardId]);
        $card = $stmtCard->fetch();
        
        if (!$card) return;

        // 2. Buscar regras ativas para a categoria do card ou todas
        $stmtRules = $db->prepare("SELECT * FROM automation_rules WHERE is_active = 1 AND (category = ? OR category = 'all') AND trigger_event = ?");
        $stmtRules->execute([$card['category'], $event]);
        $rules = $stmtRules->fetchAll();

        error_log("[AutomationEngine] Processando evento '$event' para card #$cardId");
        foreach ($rules as $rule) {
            error_log("[AutomationEngine] Verificando regra: " . $rule['name']);
            if (self::checkCondition($card, $rule, $context)) {
                error_log("[AutomationEngine] Condição atendida! Executando ações.");
                self::executeActions($card, $rule);
            } else {
                error_log("[AutomationEngine] Condição NÃO atendida.");
            }
        }
    }

    /**
     * Verificar se as condições do gatilho são atendidas
     */
    private static function checkCondition($card, $rule, $context) {
        $config = json_decode($rule['trigger_config'], true);
        if (!$config) return false;

        switch ($rule['trigger_event']) {
            case 'move_to_column':
                // Se a regra é "Mover para coluna X"
                return isset($config['column_id']) && $config['column_id'] == ($context['column_id'] ?? $card['column_id']);
            
            case 'card_created':
                return true; // Gatilho geral de criação

            case 'field_updated':
                $field = $config['field'] ?? '';
                $contains = $config['contains'] ?? '';
                if ($field && $contains) {
                    $val = strtolower($card[$field] ?? '');
                    return strpos($val, strtolower($contains)) !== false;
                }
                return true;

            case 'date_reached':
                // Implementação para Web Cron (verificar se a data/hora configurada foi atingida)
                // Isso será chamado pelo cron_tracking ou similar
                return true;

            default:
                return false;
        }
    }

    /**
     * Executar todas as ações de uma regra
     */
    private static function executeActions($card, $rule) {
        $db = getDB();
        $stmtActions = $db->prepare("SELECT * FROM automation_actions WHERE rule_id = ? ORDER BY position ASC");
        $stmtActions->execute([$rule['id']]);
        $actions = $stmtActions->fetchAll();

        foreach ($actions as $action) {
            self::runAction($card, $action, $rule);
        }
    }

    /**
     * Executar uma ação individual
     */
    private static function runAction($card, $action, $rule) {
        $db = getDB();
        $config = json_decode($action['action_config'], true);
        if (!$config) return;

        error_log("[AutomationEngine] Executando ação '" . $action['action_type'] . "' da regra '" . $rule['name'] . "'");
        switch ($action['action_type']) {
            case 'move_to_column':
                if (isset($config['column_id'])) {
                    $oldColId = $card['column_id'];
                    $newColId = $config['column_id'];
                    
                    if ($oldColId != $newColId) {
                        $stmt = $db->prepare("UPDATE cards SET column_id = ? WHERE id = ?");
                        $stmt->execute([$newColId, $card['id']]);
                        
                        // Registrar histórico
                        $oldName = $oldColId ? $db->query("SELECT name FROM columns_kanban WHERE id=$oldColId")->fetchColumn() : 'Inicial';
                        $newName = $db->query("SELECT name FROM columns_kanban WHERE id=$newColId")->fetchColumn();
                        logCardHistory($card['id'], "Automação (" . $rule['name'] . "): Mover card", $oldName ?: '?', $newName ?: '?');
                    }
                }
                break;

            case 'set_deadline_days':
                if (isset($config['days'])) {
                    $days = (int) $config['days'];
                    $newDeadline = self::calculateBusinessDays(date('Y-m-d'), $days);
                    $stmt = $db->prepare("UPDATE cards SET deadline = ?, deadline_met = NULL WHERE id = ?");
                    $stmt->execute([$newDeadline, $card['id']]);
                    logCardHistory($card['id'], "Automação (" . $rule['name'] . "): Definir prazo para +$days dias úteis ($newDeadline)");
                }
                break;

            case 'set_priority':
                if (isset($config['priority'])) {
                    $prio = $config['priority'];
                    $db->prepare("UPDATE cards SET priority = ? WHERE id = ?")->execute([$prio, $card['id']]);
                    logCardHistory($card['id'], "Automação (" . $rule['name'] . "): Definir prioridade para " . ucfirst($prio));
                }
                break;

            case 'add_comment':
                if (isset($config['text'])) {
                    $text = $config['text'];
                    $db->prepare("INSERT INTO comments (card_id, user_name, content) VALUES (?, ?, ?)")
                       ->execute([$card['id'], '🤖 ' . $rule['name'], $text]);
                }
                break;

            case 'archive':
                $db->prepare("UPDATE cards SET is_archived = 1, updated_at = NOW() WHERE id = ?")->execute([$card['id']]);
                logCardHistory($card['id'], "Automação (" . $rule['name'] . "): Arquivar card");
                break;
            default:
                error_log("[AutomationEngine] Tipo de ação desconhecido: " . $action['action_type']);
                break;
        }
        error_log("[AutomationEngine] Ação '" . $action['action_type'] . "' concluída.");
    }

    /**
     * Calcular data após N dias úteis
     */
    private static function calculateBusinessDays($startDate, $days) {
        $feriados = ['01-01', '04-21', '05-01', '09-07', '10-12', '11-02', '11-15', '11-20', '12-25'];
        $cur = new DateTime($startDate);
        $uteis = 0;
        while ($uteis < $days) {
            $cur->modify('+1 day');
            $dow = $cur->format('N'); // 1=Seg, 7=Dom
            $mmdd = $cur->format('m-d');
            if ($dow < 6 && !in_array($mmdd, $feriados)) $uteis++;
        }
        return $cur->format('Y-m-d');
    }
}
