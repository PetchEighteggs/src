<?php
require_once '../../config.php';
require_once '../../helper.php';

$slug = $_GET['slug'] ?? '';
$dept_id = (int)($_GET['dept_id'] ?? 0);
$group_id = (int)($_GET['group_id'] ?? 0);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($slug)) $slug = $input['slug'] ?? '';
    if ($dept_id === 0) $dept_id = (int)($input['dept_id'] ?? 0);
}

try {
    if (!empty($slug) && $dept_id === 0) {
        $stmtDept = $DB->prepare("SELECT DeptID FROM microsite_departments WHERE Slug = ? LIMIT 1");
        $stmtDept->execute([$slug]);
        $dept_id = $stmtDept->fetchColumn();
    }

    if ($dept_id === 0 || $dept_id === false) {
        sendError('Valid Department ID or Slug is required', 400);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $title = trim($input['title'] ?? '');
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $detail = trim($input['detail'] ?? '');
        $tel = trim($input['tel'] ?? '');
        $post_group_id = (int)($input['group_id'] ?? 0);
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (empty($title) || empty($name) || empty($detail)) {
            sendError('Title, Name, and Detail are required fields', 400);
        }

        $now = date('Y-m-d H:i:s');
        
        $fileName = '';
        
        // Handle file upload
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file'];
            $maxSize = 5 * 1024 * 1024; // 5 MB
            
            if ($file['size'] > $maxSize) {
                sendError('File size exceeds the 5MB limit', 400);
            }
            
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedImageExts = ['jpg', 'jpeg', 'png'];
            $allowedFileExts = ['pdf', 'doc', 'docx'];
            $allowedExts = array_merge($allowedImageExts, $allowedFileExts);
            
            if (!in_array($ext, $allowedExts)) {
                sendError('Invalid file extension', 400);
            }
            
            $fileName = date('ymdHis') . strtolower(FU::makeRandom()) . '.' . $ext;
            
            if (in_array($ext, $allowedImageExts)) {
                $uploadDir = $basePath . 'uploads/images/microsite/' . $slug . '/contact/';
            } else {
                $uploadDir = $basePath . 'uploads/files/microsite/' . $slug . '/contact/';
            }
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $destination = $uploadDir . $fileName;
            
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                sendError('Failed to save uploaded file', 500);
            }
        }
        
        $DB->beginTransaction();

        $stmtMax = $DB->query("SELECT MAX(ContactID) FROM microsite_contact");
        $maxId = (int)$stmtMax->fetchColumn();
        $newContactId = $maxId + 1;
        
        $stmtInsert = $DB->prepare("
            INSERT INTO microsite_contact 
            (ContactID, GroupID, DeptID, Title, Name, Detail, Email, Tel, File, IsActive, CreateDate, CreateIP) 
            VALUES 
            (:id, :group_id, :dept_id, :title, :name, :detail, :email, :tel, :file, 'Y', :createdate, :createip)
        ");
        
        $stmtInsert->execute([
            ':id' => $newContactId,
            ':group_id' => $post_group_id,
            ':dept_id' => $dept_id,
            ':title' => $title,
            ':name' => $name,
            ':detail' => $detail,
            ':email' => $email,
            ':tel' => $tel,
            ':file' => $fileName,
            ':createdate' => $now,
            ':createip' => $client_ip
        ]);

        $DB->commit();

        sendResponse(['id' => $newContactId], 201, "Contact message submitted successfully");
        exit;
    }

    $conditions = ["c.DeptID = :dept_id", "c.IsActive = 'Y'", "c.DelData = 'N'", "(g.IsActive = 'Y' OR c.GroupID = 0 OR c.GroupID IS NULL)"];
    $params = [':dept_id' => $dept_id];

    if ($group_id > 0) {
        $conditions[] = "c.GroupID = :group_id";
        $params[':group_id'] = $group_id;
    }

    $whereClause = implode(' AND ', $conditions);

    $sql = "SELECT 
			c.ContactID, c.GroupID, g.GroupNameTH, c.Title, c.Name, c.Detail,
			c.File, c.Email, c.Tel, c.Fax, d.Slug as DeptSlug
			FROM microsite_contact c
			LEFT JOIN microsite_contact_groups g ON c.GroupID = g.GroupID
			LEFT JOIN microsite_departments d ON c.DeptID = d.DeptID
			WHERE $whereClause
			ORDER BY c.OrderNo ASC, c.ContactID ASC
    ";
    
    $stmt = $DB->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = [];

    // Format output
    foreach ($contacts as $row) {
        $deptSlug = $row['DeptSlug'] ?? 'unknown';
        
        $fileUrl = '';
        if (!empty($row['File'])) {
					$ext = strtolower(pathinfo($row['File'], PATHINFO_EXTENSION));
					if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
							$fileUrl = getImageUrl($row['File'], $deptSlug);
					} else {
							$fileUrl = getFileUrl($row['File'], $deptSlug);
					}
        }

        $result[] = [
            'id' => $row['ContactID'],
            'title' => $row['Title'],
            'name' => $row['Name'],
            'detail' => $row['Detail'],
            'file_url' => $fileUrl,
            'email' => $row['Email'],
            'tel' => $row['Tel'],
            'fax' => $row['Fax'],
            'group' => $row['GroupNameTH'],
            'group_id' => $row['GroupID']
        ];
    }

    sendResponse($result, 200, "Contacts retrieved successfully");

} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}