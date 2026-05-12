<?php
require_once 'database.php';

header('Content-Type: application/json');

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
        $fileName = preg_replace('/[^A-Za-z0-9.\-_]/', '', $fileName); // Sanitize
        
        if (move_uploaded_file($_FILES['bg_image']['tmp_name'], $uploadDir . $fileName)) {
            echo json_encode(['success' => true, 'url' => 'uploads/' . $fileName]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'message' => 'Upload failed']);
    exit;
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
            echo json_encode(['success' => false, 'message' => 'Invalid font format']);
            exit;
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
            
            echo json_encode(['success' => true, 'font' => ['name' => $fontName, 'file_path' => $filePath]]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'message' => 'Font upload failed']);
    exit;
}

if ($action === 'list_fonts') {
    $stmt = $pdo->query("SELECT * FROM fonts ORDER BY name ASC");
    $fonts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $fonts]);
    exit;
}

if ($action === 'save_template') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['name'], $data['bg_image'], $data['fields_json'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }

    $id = $data['id'] ?? null;
    if ($id) {
        // Update existing template
        $stmt = $pdo->prepare("UPDATE templates SET name = ?, bg_image = ?, fields_json = ? WHERE id = ?");
        if ($stmt->execute([$data['name'], $data['bg_image'], json_encode($data['fields_json']), $id])) {
            echo json_encode(['success' => true, 'id' => $id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB Update Failed']);
        }
    } else {
        // Insert new template
        $id = substr(md5(uniqid('', true)), 0, 8); // e.g. a1b2c3d4
        
        $stmt = $pdo->prepare("INSERT INTO templates (id, name, bg_image, fields_json) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$id, $data['name'], $data['bg_image'], json_encode($data['fields_json'])])) {
            echo json_encode(['success' => true, 'id' => $id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB Insert Failed']);
        }
    }
    exit;
}

if ($action === 'list_templates') {
    $stmt = $pdo->query("SELECT id, name, bg_image, created_at FROM templates ORDER BY created_at DESC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $templates]);
    exit;
}

if ($action === 'delete_template') {
    $id = $data['id'] ?? $_GET['id'] ?? '';
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM templates WHERE id = ?");
        if ($stmt->execute([$id])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB Delete Failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'ID missing']);
    }
    exit;
}

if ($action === 'get_template') {
    $id = $_GET['id'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ?");
    $stmt->execute([$id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($template) {
        $template['fields'] = json_decode($template['fields_json'], true);
        echo json_encode(['success' => true, 'data' => $template]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Not found']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
