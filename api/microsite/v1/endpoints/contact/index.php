<?php
// src/api/microsite/v1/endpoints/contact.php
require_once '../../config.php';
require_once '../../helper.php';

$slug = $_GET['slug'] ?? '';
$dept_id = (int)($_GET['dept_id'] ?? 0);
$group_id = (int)($_GET['group_id'] ?? 0);

// For POST requests, we might get slug from the body
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($slug)) $slug = $input['slug'] ?? '';
    if ($dept_id === 0) $dept_id = (int)($input['dept_id'] ?? 0);
}

try {
    // Resolve dept_id from slug if provided
    if (!empty($slug) && $dept_id === 0) {
        $stmtDept = $DB->prepare("SELECT DeptID FROM microsite_departments WHERE Slug = ? LIMIT 1");
        $stmtDept->execute([$slug]);
        $dept_id = $stmtDept->fetchColumn();
    }

    if ($dept_id === 0 || $dept_id === false) {
        sendError('Valid Department ID or Slug is required', 400);
    }

    // === Handle POST Request (Submit Contact Message) ===
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
        
        // Use a transaction just in case
        $DB->beginTransaction();

        // Get max ID to manually insert (Because ContactID is not AUTO_INCREMENT in the schema!)
        $stmtMax = $DB->query("SELECT MAX(ContactID) FROM microsite_contact");
        $maxId = (int)$stmtMax->fetchColumn();
        $newContactId = $maxId + 1;
        
        $stmtInsert = $DB->prepare("
            INSERT INTO microsite_contact 
            (ContactID, GroupID, DeptID, Title, Name, Detail, Email, Tel, IsActive, CreateDate, CreateIP) 
            VALUES 
            (:id, :group_id, :dept_id, :title, :name, :detail, :email, :tel, 'Y', :createdate, :createip)
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
            ':createdate' => $now,
            ':createip' => $client_ip
        ]);

        $DB->commit();

        sendResponse(['id' => $newContactId], 201, "Contact message submitted successfully");
        exit;
    }

    // === Handle GET Request (Fetch Contacts) ===
    // Build query conditions
    $conditions = ["c.DeptID = :dept_id", "c.IsActive = 'Y'", "c.DelData = 'N'", "(g.IsActive = 'Y' OR c.GroupID = 0 OR c.GroupID IS NULL)"];
    $params = [':dept_id' => $dept_id];

    if ($group_id > 0) {
        $conditions[] = "c.GroupID = :group_id";
        $params[':group_id'] = $group_id;
    }

    $whereClause = implode(' AND ', $conditions);

    // Fetch data
    $sql = "
        SELECT 
            c.ContactID, c.GroupID, g.GroupNameTH, c.Title, c.Name, c.Detail,
            c.File, c.Email, c.Tel, c.Fax, c.Address, d.Slug as DeptSlug
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
        $imageUrl = getImageUrl($row['File'], $deptSlug);

        $result[] = [
            'id' => $row['ContactID'],
            'title' => $row['Title'],
            'name' => $row['Name'],
            'detail' => $row['Detail'],
            'image' => $imageUrl,
            'email' => $row['Email'],
            'tel' => $row['Tel'],
            'fax' => $row['Fax'],
            'address' => $row['Address'],
            'group' => $row['GroupNameTH'],
            'group_id' => $row['GroupID']
        ];
    }

    sendResponse($result, 200, "Contacts retrieved successfully");

} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
