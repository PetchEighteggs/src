<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

define('_VALID_ACCESS', 1);
session_start();
require_once('../../configuration.php');
require_once('../../config/ms_com.php');
require_once('../../config/ms.class.php');
require_once('../includes/action_security.php');
$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['login_url' => '../login.php']);
bofSecurityRequireMethod(['POST']);
bofSecurityRequireCsrf();
$MS = new MS($DB);

$deptID = $_POST['DeptID'] ?? 0;

// Verify Access
$userGroupID = $_SESSION['GroupID'] ?? '';
$hasAccess = false;
if ($userGroupID === 'A') {
    $hasAccess = true;
} else if ($userGroupID === 'S' && $deptID > 0) {
    $staffID = $_SESSION['StaffID'] ?? 0;
    require_once('../includes/microsite_permission.php');
    if (checkMicrositeMenuPermission($DB, $staffID, $deptID, 'settings', 'CanEdit')) {
        $hasAccess = true;
    }
}

if (!$hasAccess || ($deptID == 0 && $userGroupID !== 'A')) {
    echo "<script>alert('คุณไม่มีสิทธิ์ทำรายการนี้'); window.history.back();</script>";
    exit;
}

$audit = $MS->micrositeAuditContext();
$staffID = $audit['staff_id'];
$requestIP = $audit['ip'];

// Prepare Data
$nameTH = $_POST['NameTH'] ?? '';
$nameEN = $_POST['NameEN'] ?? '';
$layout = $_POST['DefaultLayout'] ?? '1';
$deptGroup = $_POST['DeptGroup'] ?? 'central';
$isActive = $_POST['IsActive'] ?? 'Y';
$address = $_POST['Address'] ?? '';
$addressEN = $_POST['AddressEN'] ?? '';
$tel = $_POST['Tel'] ?? '';
$fax = $_POST['Fax'] ?? '';
$email = $_POST['Email'] ?? '';
$facebook = $_POST['Facebook'] ?? '';
$youtube = $_POST['YouTube'] ?? '';
$latitude = $_POST['Latitude'] ?? '';
$longitude = $_POST['Longitude'] ?? '';
$seoTitle = $_POST['SEOTitle'] ?? '';
$seoDescription = $_POST['SEODescription'] ?? '';
$seoKeywords = $_POST['SEOKeywords'] ?? '';

// Convert Display/Create Date
$createDateRaw = $_POST['CreateDate'] ?? '';
$createDate = date('Y-m-d H:i:s');
if (!empty($createDateRaw)) {
    $dateParts = explode('/', $createDateRaw);
    if (count($dateParts) === 3) {
        $createDate = $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0] . ' ' . date('H:i:s');
    }
}

// Get Slug and Image
if ($deptID > 0) {
    $stmt = $DB->prepare("SELECT Slug, Image, MapImage FROM microsite_departments WHERE DeptID = ?");
    $stmt->execute([$deptID]);
    $dept = $stmt->fetch(PDO::FETCH_ASSOC);
    $slug = $dept['Slug'];
    $currentImage = $dept['Image'];
    $currentMapImage = $dept['MapImage'] ?? '';
} else {
    $slug = strtolower(trim($_POST['Slug'] ?? ''));
    if (!preg_match('/^[a-z0-9_]+$/', $slug)) {
        echo "<script>alert('Slug ต้องเป็นภาษาอังกฤษพิมพ์เล็ก ตัวเลข หรือ underscore เท่านั้น'); window.history.back();</script>";
        exit;
    }
    
    // Check duplicate slug
    $stmt = $DB->prepare("SELECT 1 FROM microsite_departments WHERE Slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        echo "<script>alert('Slug นี้มีใช้งานแล้ว กรุณาระบุใหม่'); window.history.back();</script>";
        exit;
    }
    $currentImage = '';
    $currentMapImage = '';
}

try {
    $uploadDir = $MS->micrositeUploadDirectory($sServerImgDir, $slug);
    $deleteLogoImage = ($_POST['delete_logo_image'] ?? '0') === '1';
    $deleteMapImage = ($_POST['delete_map_image'] ?? '0') === '1';
    $imageName = $deleteLogoImage ? '' : $currentImage;
    $mapImageName = $deleteMapImage ? '' : $currentMapImage;

    $newLogoName = $MS->micrositeStoreUpload($_FILES['Image'] ?? [], $uploadDir, ['jpg', 'jpeg', 'png', 'gif', 'webp'], $SetMaxSize, true);
    if ($newLogoName !== null) {
        $imageName = $newLogoName;
    }

    $newMapName = $MS->micrositeStoreUpload($_FILES['MapImage'] ?? [], $uploadDir, ['jpg', 'jpeg', 'png', 'gif', 'webp'], $SetMaxSize, true);
    if ($newMapName !== null) {
        $mapImageName = $newMapName;
    }
} catch (RuntimeException $e) {
    if (isset($newLogoName) && $newLogoName !== null && is_file($uploadDir . $newLogoName)) {
        @unlink($uploadDir . $newLogoName);
    }
    $MS->micrositeUploadFail($e->getMessage());
}

// Update or Insert DB
try {
    if ($deptID > 0) {
        $upd = $DB->prepare("
            UPDATE microsite_departments SET 
            NameTH=?, NameEN=?, DeptGroup=?, DefaultLayout=?, IsActive=?, 
            Address=?, AddressEN=?, Tel=?, Fax=?, Email=?, Facebook=?, YouTube=?, 
            Image=?, MapImage=?, Latitude=?, Longitude=?, SEOTitle=?, SEODescription=?, SEOKeywords=?, CreateDate=?,
            UpdateDate=NOW(), UpdateBy=?, UpdateIP=?
            WHERE DeptID=? AND COALESCE(DelData, 'N') <> 'Y'
        ");
        $upd->execute([
            $nameTH, $nameEN, $deptGroup, $layout, $isActive,
            $address, $addressEN, $tel, $fax, $email, $facebook, $youtube,
            $imageName, $mapImageName, $latitude, $longitude, $seoTitle, $seoDescription, $seoKeywords, $createDate,
            $staffID, $requestIP, $deptID
        ]);
        
        // Update Session if it's the active one
        if (isset($_SESSION['ActiveDeptID']) && $_SESSION['ActiveDeptID'] == $deptID) {
            $_SESSION['ActiveDeptName'] = $nameTH;
        }
    } else {
        $ins = $DB->prepare("
            INSERT INTO microsite_departments 
            (Slug, NameTH, NameEN, DeptGroup, DefaultLayout, IsActive, Address, AddressEN, Tel, Fax, Email, Facebook, YouTube, Image, MapImage, Latitude, Longitude, SEOTitle, SEODescription, SEOKeywords, CreateDate, CreateBy, CreateIP, DelData)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'N')
        ");
        $ins->execute([
            $slug, $nameTH, $nameEN, $deptGroup, $layout, $isActive,
            $address, $addressEN, $tel, $fax, $email, $facebook, $youtube, $imageName, $mapImageName, $latitude, $longitude, $seoTitle, $seoDescription, $seoKeywords, $createDate,
            $staffID, $requestIP
        ]);
        
        // Ensure upload directory is created even without logo
        $uploadDir = $MS->micrositeUploadDirectory($sServerImgDir, $slug);
    }

    if (($newLogoName !== null || $deleteLogoImage) && $currentImage !== '' && $currentImage !== $imageName && is_file($uploadDir . $currentImage)) {
        @unlink($uploadDir . $currentImage);
    }
    if (($newMapName !== null || $deleteMapImage) && $currentMapImage !== '' && $currentMapImage !== $mapImageName && is_file($uploadDir . $currentMapImage)) {
        @unlink($uploadDir . $currentMapImage);
    }
    
    $logAction = ($deptID > 0 ? 'แก้ไขตั้งค่าหน่วยงาน ID: ' : 'เพิ่มหน่วยงาน ID: ') . ($deptID > 0 ? $deptID : $DB->lastInsertId());
    $MS->StaffLog($logAction, $_SERVER['REQUEST_URI'], $staffID, '', 1, ($deptID > 0 ? $deptID : $DB->lastInsertId()));
    
    echo "<script>window.location.href='view.php';</script>";
} catch (PDOException $e) {
    if ($newLogoName !== null && is_file($uploadDir . $newLogoName)) {
        @unlink($uploadDir . $newLogoName);
    }
    if ($newMapName !== null && is_file($uploadDir . $newMapName)) {
        @unlink($uploadDir . $newMapName);
    }
    echo "<script>alert('เกิดข้อผิดพลาด: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
}
