<?php
ob_start(); // Capture any unexpected output (warnings, notices, etc.)
error_reporting(0); // Suppress PHP errors from polluting JSON output

require_once 'database.php';

header('Content-Type: application/json');

// Helper: send clean JSON and exit
function sendJson($data) {
    ob_clean();
    echo json_encode($data);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

if (empty($action) && $data && isset($data['action'])) {
    $action = $data['action'];
}

if ($action === 'upload') {
    if (isset($_FILES['bg_image']) && $_FILES['bg_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/';
        $fileName = time() . '_' . basename($_FILES['bg_image']['name']);
        $fileName = preg_replace('/[^A-Za-z0-9.\-_]/', '', $fileName);
        
        if (move_uploaded_file($_FILES['bg_image']['tmp_name'], $uploadDir . $fileName)) {
            sendJson(['success' => true, 'url' => 'uploads/' . $fileName]);
        }
    }
    sendJson(['success' => false, 'message' => 'Upload failed']);
}

if ($action === 'upload_font') {
    if (isset($_FILES['font_file']) && $_FILES['font_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/fonts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $originalName = basename($_FILES['font_file']['name']);
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $allowed = ['ttf', 'otf', 'woff', 'woff2'];
        
        if (!in_array(strtolower($ext), $allowed)) {
            sendJson(['success' => false, 'message' => 'Invalid font format']);
        }

        $fontName = preg_replace('/[^A-Za-z0-9 ]/', '', pathinfo($originalName, PATHINFO_FILENAME));
        if (empty(trim($fontName))) {
            $fontName = 'CustomFont_' . substr(md5($originalName), 0, 5);
        }
        
        $fileName = time() . '_' . preg_replace('/[^A-Za-z0-9.\-_]/', '', $originalName);
        if (empty(pathinfo($fileName, PATHINFO_FILENAME))) {
            $fileName = time() . '_font.' . $ext;
        }
        
        if (move_uploaded_file($_FILES['font_file']['tmp_name'], $uploadDir . $fileName)) {
            $filePath = 'uploads/fonts/' . $fileName;
            $stmt = $pdo->prepare("INSERT INTO fonts (name, file_path) VALUES (?, ?)");
            $stmt->execute([$fontName, $filePath]);
            sendJson(['success' => true, 'font' => ['name' => $fontName, 'file_path' => $filePath]]);
        }
    }
    sendJson(['success' => false, 'message' => 'Font upload failed']);
}

if ($action === 'list_fonts') {
    $stmt = $pdo->query("SELECT * FROM fonts ORDER BY name ASC");
    $fonts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendJson(['success' => true, 'data' => $fonts]);
}

if ($action === 'save_template') {
    $body = json_decode(file_get_contents('php://input'), true);
    
    if (!$body || !isset($body['name'], $body['bg_image'], $body['fields_json'])) {
        sendJson(['success' => false, 'message' => 'Invalid data']);
    }

    $id = $body['id'] ?? null;
    if ($id) {
        $stmt = $pdo->prepare("UPDATE templates SET name = ?, bg_image = ?, fields_json = ? WHERE id = ?");
        if ($stmt->execute([$body['name'], $body['bg_image'], json_encode($body['fields_json']), $id])) {
            sendJson(['success' => true, 'id' => $id]);
        } else {
            sendJson(['success' => false, 'message' => 'DB Update Failed']);
        }
    } else {
        $id = substr(md5(uniqid('', true)), 0, 8);
        $stmt = $pdo->prepare("INSERT INTO templates (id, name, bg_image, fields_json) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$id, $body['name'], $body['bg_image'], json_encode($body['fields_json'])])) {
            sendJson(['success' => true, 'id' => $id]);
        } else {
            sendJson(['success' => false, 'message' => 'DB Insert Failed']);
        }
    }
}

if ($action === 'list_templates') {
    $stmt = $pdo->query("SELECT id, name, bg_image, created_at FROM templates ORDER BY created_at DESC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendJson(['success' => true, 'data' => $templates]);
}

if ($action === 'delete_template') {
    $id = $data['id'] ?? $_GET['id'] ?? '';
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM templates WHERE id = ?");
        if ($stmt->execute([$id])) {
            sendJson(['success' => true]);
        } else {
            sendJson(['success' => false, 'message' => 'DB Delete Failed']);
        }
    } else {
        sendJson(['success' => false, 'message' => 'ID missing']);
    }
}

if ($action === 'reset_db') {
    $pdo->exec("DELETE FROM templates");
    $pdo->exec("DELETE FROM fonts");
    
    $uploadDir = __DIR__ . '/uploads/';
    $fontDir   = $uploadDir . 'fonts/';
    
    $files = array_merge(
        glob($uploadDir . "*.*") ?: [],
        glob($fontDir . "*.*") ?: []
    );
    foreach ($files as $file) {
        if (is_file($file)) unlink($file);
    }
    sendJson(['success' => true]);
}

if ($action === 'get_template') {
    $id = $_GET['id'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ?");
    $stmt->execute([$id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($template) {
        $template['fields'] = json_decode($template['fields_json'], true);
        sendJson(['success' => true, 'data' => $template]);
    } else {
        sendJson(['success' => false, 'message' => 'Not found']);
    }
}

sendJson(['success' => false, 'message' => 'Invalid action']);
