<?php
// api/manuals.php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$action  = $_GET['action'] ?? $_POST['action'] ?? '';
$db      = getDB();

// ── Helpers ──────────────────────────────────────────────────────────────────

function requireAdmin()
{
    global $isAdmin;
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso restrito a administradores.']);
        exit;
    }
}

function ensureUploadDir(int $manualId): string
{
    $dir = __DIR__ . '/../assets/manual_imgs/' . $manualId;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

// ── Routes ───────────────────────────────────────────────────────────────────

switch ($action) {

    // ── LIST ─────────────────────────────────────────────────────────────────
    case 'list':
        $stmt = $db->query("
            SELECT m.id, m.title,
                   COUNT(ms.id) AS step_count,
                   m.created_at,
                   m.updated_at,
                   u.name AS author
            FROM manuals m
            LEFT JOIN manual_steps ms ON ms.manual_id = m.id
            LEFT JOIN users u ON u.id = m.created_by
            GROUP BY m.id
            ORDER BY m.updated_at DESC
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    // ── GET (single) ─────────────────────────────────────────────────────────
    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'ID inválido.']); exit; }

        $manual = $db->prepare("SELECT * FROM manuals WHERE id = ?");
        $manual->execute([$id]);
        $manual = $manual->fetch();
        if (!$manual) { echo json_encode(['success' => false, 'message' => 'Manual não encontrado.']); exit; }

        $steps = $db->prepare("SELECT * FROM manual_steps WHERE manual_id = ? ORDER BY position ASC");
        $steps->execute([$id]);
        $steps = $steps->fetchAll();

        foreach ($steps as &$step) {
            $imgs = $db->prepare("SELECT * FROM manual_images WHERE step_id = ?");
            $imgs->execute([$step['id']]);
            $step['images'] = $imgs->fetchAll();
        }

        $manual['steps'] = $steps;
        echo json_encode(['success' => true, 'data' => $manual]);
        break;

    // ── SEARCH ───────────────────────────────────────────────────────────────
    case 'search':
        $q = '%' . trim($_GET['q'] ?? '') . '%';
        $stmt = $db->prepare("
            SELECT DISTINCT m.id, m.title, m.updated_at,
                   COUNT(DISTINCT ms2.id) AS step_count
            FROM manuals m
            LEFT JOIN manual_steps ms ON ms.manual_id = m.id
            LEFT JOIN manual_steps ms2 ON ms2.manual_id = m.id
            WHERE m.title LIKE ?
               OR ms.content LIKE ?
            GROUP BY m.id
            ORDER BY m.updated_at DESC
        ");
        $stmt->execute([$q, $q]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    // ── SAVE (create / edit) ─────────────────────────────────────────────────
    case 'save':
        requireAdmin();
        $body  = json_decode(file_get_contents('php://input'), true);
        $id    = (int)($body['id'] ?? 0);
        $title = trim($body['title'] ?? '');
        $steps = $body['steps'] ?? [];

        if (!$title) { echo json_encode(['success' => false, 'message' => 'Título obrigatório.']); exit; }

        $db->beginTransaction();
        try {
            if ($id) {
                // Update manual title
                $db->prepare("UPDATE manuals SET title = ? WHERE id = ?")->execute([$title, $id]);

                // Remove steps that were deleted by the user
                $keepIds = array_filter(array_column($steps, 'id'));
                if ($keepIds) {
                    $in = implode(',', array_map('intval', $keepIds));
                    $db->exec("DELETE FROM manual_steps WHERE manual_id = $id AND id NOT IN ($in)");
                } else {
                    $db->prepare("DELETE FROM manual_steps WHERE manual_id = ?")->execute([$id]);
                }
            } else {
                // Create new manual
                $db->prepare("INSERT INTO manuals (title, created_by) VALUES (?, ?)")
                   ->execute([$title, $_SESSION['user_id'] ?? null]);
                $id = (int)$db->lastInsertId();
            }

            $stepIds = [];
            foreach ($steps as $pos => $step) {
                $content = $step['content'] ?? '';
                $stepId  = (int)($step['id'] ?? 0);

                if ($stepId) {
                    $db->prepare("UPDATE manual_steps SET content = ?, position = ? WHERE id = ? AND manual_id = ?")
                       ->execute([$content, $pos, $stepId, $id]);
                    $stepIds[] = $stepId;
                } else {
                    $db->prepare("INSERT INTO manual_steps (manual_id, position, content) VALUES (?, ?, ?)")
                       ->execute([$id, $pos, $content]);
                    $stepIds[] = (int)$db->lastInsertId();
                }
            }

            $db->prepare("UPDATE manuals SET updated_at = NOW() WHERE id = ?")->execute([$id]);
            $db->commit();

            echo json_encode(['success' => true, 'id' => $id, 'step_ids' => $stepIds]);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ── UPLOAD IMAGE ─────────────────────────────────────────────────────────
    case 'upload_image':
        requireAdmin();
        $stepId   = (int)($_POST['step_id'] ?? 0);
        $manualId = (int)($_POST['manual_id'] ?? 0);
        $caption  = trim($_POST['caption'] ?? '');

        if (!$stepId || !$manualId) { echo json_encode(['success' => false, 'message' => 'step_id e manual_id obrigatórios.']); exit; }
        if (empty($_FILES['image'])) { echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado.']); exit; }

        $file  = $_FILES['image'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed)) { echo json_encode(['success' => false, 'message' => 'Tipo de arquivo não permitido.']); exit; }
        if ($file['size'] > 5 * 1024 * 1024) { echo json_encode(['success' => false, 'message' => 'Arquivo muito grande (máx 5MB).']); exit; }

        $dir      = ensureUploadDir($manualId);
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('img_', true) . '.' . strtolower($ext);
        $dest     = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar imagem.']);
            exit;
        }

        $db->prepare("INSERT INTO manual_images (step_id, filename, caption) VALUES (?, ?, ?)")
           ->execute([$stepId, $manualId . '/' . $filename, $caption]);

        $imgId = (int)$db->lastInsertId();
        echo json_encode([
            'success' => true,
            'image'   => [
                'id'       => $imgId,
                'filename' => $manualId . '/' . $filename,
                'caption'  => $caption,
                'url'      => 'assets/manual_imgs/' . $manualId . '/' . $filename,
            ],
        ]);
        break;

    // ── DELETE IMAGE ─────────────────────────────────────────────────────────
    case 'delete_image':
        requireAdmin();
        $body = json_decode(file_get_contents('php://input'), true);
        $imgId = (int)($body['id'] ?? 0);
        if (!$imgId) { echo json_encode(['success' => false, 'message' => 'ID inválido.']); exit; }

        $img = $db->prepare("SELECT * FROM manual_images WHERE id = ?");
        $img->execute([$imgId]);
        $img = $img->fetch();
        if ($img) {
            $path = __DIR__ . '/../assets/manual_imgs/' . $img['filename'];
            if (file_exists($path)) unlink($path);
            $db->prepare("DELETE FROM manual_images WHERE id = ?")->execute([$imgId]);
        }
        echo json_encode(['success' => true]);
        break;

    // ── DELETE MANUAL ─────────────────────────────────────────────────────────
    case 'delete':
        requireAdmin();
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($body['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'ID inválido.']); exit; }

        // Remove image files from disk
        $imgs = $db->prepare("
            SELECT mi.filename FROM manual_images mi
            JOIN manual_steps ms ON ms.id = mi.step_id
            WHERE ms.manual_id = ?
        ");
        $imgs->execute([$id]);
        foreach ($imgs->fetchAll() as $img) {
            $path = __DIR__ . '/../assets/manual_imgs/' . $img['filename'];
            if (file_exists($path)) unlink($path);
        }

        $db->prepare("DELETE FROM manuals WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ação desconhecida.']);
}
