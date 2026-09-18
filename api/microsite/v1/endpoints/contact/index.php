<?php
require_once '../../config.php';
require_once '../../helper.php';

$slug = $_GET['slug'] ?? '';
$dept_id = (int)($_GET['dept_id'] ?? 0);
$group_id = (int)($_GET['group_id'] ?? 0);
$uploadedPath = null;

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($slug)) $slug = $input['slug'] ?? '';
    if ($dept_id === 0) $dept_id = (int)($input['dept_id'] ?? 0);
}

try {
    // Resolve the canonical slug from the database. A client-supplied slug is
    // never used directly as part of an upload path.
    if (!empty($slug)) {
        $stmtDept = $DB->prepare("SELECT DeptID, Slug FROM microsite_departments WHERE Slug = ? LIMIT 1");
        $stmtDept->execute([$slug]);
    } elseif ($dept_id > 0) {
        $stmtDept = $DB->prepare("SELECT DeptID, Slug FROM microsite_departments WHERE DeptID = ? LIMIT 1");
        $stmtDept->execute([$dept_id]);
    } else {
        sendError('Valid Department ID or Slug is required', 400);
    }

    $department = $stmtDept->fetch(PDO::FETCH_ASSOC);
    if (!$department || ($dept_id > 0 && (int)$department['DeptID'] !== $dept_id)) {
        sendError('Valid Department ID or Slug is required', 400);
    }

    $dept_id = (int)$department['DeptID'];
    $slug = (string)$department['Slug'];

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
        if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['file'];
            $maxSize = 5 * 1024 * 1024; // 5 MB

            if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
                sendError('Invalid file upload', 400);
            }

            if ($file['size'] <= 0 || $file['size'] > $maxSize) {
                sendError('File size exceeds the 5MB limit', 400);
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedTypes = [
                'jpg' => ['image/jpeg'],
                'jpeg' => ['image/jpeg'],
                'png' => ['image/png'],
                'pdf' => ['application/pdf'],
                'doc' => ['application/msword', 'application/CDFV2'],
                'docx' => [
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/zip'
                ]
            ];

            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $fileInfo->file($file['tmp_name']);
            if (!isset($allowedTypes[$ext]) || !in_array($mimeType, $allowedTypes[$ext], true)) {
                sendError('Invalid file type', 400);
            }

            $fileName = date('ymdHis') . strtolower(FU::makeRandom()) . '.' . $ext;

            if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                $uploadDir = $basePath . 'uploads/images/microsite/' . $slug . '/contact/';
            } else {
                $uploadDir = $basePath . 'uploads/files/microsite/' . $slug . '/contact/';
            }

            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                sendError('Failed to prepare upload directory', 500);
            }
            
            $destination = $uploadDir . $fileName;
            
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                sendError('Failed to save uploaded file', 500);
            }
            $uploadedPath = $destination;
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

} catch (Throwable $e) {
    if (isset($DB) && $DB instanceof PDO && $DB->inTransaction()) {
        $DB->rollBack();
    }
    if ($uploadedPath !== null && is_file($uploadedPath)) {
        @unlink($uploadedPath);
    }
    error_log('Microsite contact endpoint failed: ' . $e->getMessage());
    sendError('Database error', 500);
}
