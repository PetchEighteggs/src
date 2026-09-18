<?php
define('_VALID_ACCESS', 1);
session_start();
include_once('../../configuration.php');
include_once('../../config/ms_com.php');
include_once('../../config/ms.class.php');
include_once('../../config/func.class.php');
require_once('../includes/action_security.php');

$DB = mosConnectPDO();
bofSecurityRequireAuth($DB, ['login_url' => '../login.php']);
$MS = new MS($DB);

$action = $_REQUEST['action'] ?? '';
$StaffID = (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0);
$CreateIP = $_SERVER['REMOTE_ADDR'];

try {
    switch ($action) {
        case 'add':
            $GroupName_TH = $_POST['GroupName_TH'] ?? '';
            $GroupName_EN = $_POST['GroupName_EN'] ?? '';
            $OrderNo = $_POST['OrderNo'] ?? 1;
            $IsActive = $_POST['IsActive'] ?? 'N';

            $sql = "INSERT INTO cio_news_groups 
						(GroupName_TH, GroupName_EN, OrderNo, IsActive, CreateDate, CreateBy, CreateIP) 
						VALUES 
						(:GroupName_TH, :GroupName_EN, :OrderNo, :IsActive, NOW(), :CreateBy, :CreateIP)";
            
            $stmt = $DB->prepare($sql);
            $stmt->execute([
						':GroupName_TH' => $GroupName_TH,
						':GroupName_EN' => $GroupName_EN,
						':OrderNo' => $OrderNo,
						':IsActive' => $IsActive,
						':CreateBy' => $StaffID,
						':CreateIP' => $CreateIP
            ]);

            $_SESSION['alert'] = ['status' => 'success', 'title' => 'บันทึกข้อมูลสำเร็จ'];
            header("Location: group.php");
            exit();

        case 'edit':
            $id = $_POST['id'] ?? 0;
            if (!$id) {
                $_SESSION['alert'] = ['status' => 'error', 'title' => 'ไม่พบข้อมูล'];
                header("Location: group.php");
                exit();
            }

            $GroupName_TH = $_POST['GroupName_TH'] ?? '';
            $GroupName_EN = $_POST['GroupName_EN'] ?? '';
            $OrderNo = $_POST['OrderNo'] ?? 1;
            $IsActive = $_POST['IsActive'] ?? 'N';

            $sql = "UPDATE cio_news_groups SET 
                    GroupName_TH = :GroupName_TH, GroupName_EN = :GroupName_EN, 
                    OrderNo = :OrderNo, IsActive = :IsActive, 
                    UpdateDate = NOW(), UpdateBy = :UpdateBy, UpdateIP = :UpdateIP 
                    WHERE GroupID = :id";
            
            $stmt = $DB->prepare($sql);
            $stmt->execute([
                ':GroupName_TH' => $GroupName_TH,
                ':GroupName_EN' => $GroupName_EN,
                ':OrderNo' => $OrderNo,
                ':IsActive' => $IsActive,
                ':UpdateBy' => $StaffID,
                ':UpdateIP' => $CreateIP,
                ':id' => $id
            ]);

            $_SESSION['alert'] = ['status' => 'success', 'title' => 'แก้ไขข้อมูลสำเร็จ'];
            header("Location: group.php");
            exit();

        case 'delete':
            $id = $_GET['id'] ?? 0;
            if ($id) {
                // Check if category is used in news
                $stmtCheck = $DB->prepare("SELECT COUNT(*) FROM cio_news WHERE GroupID = :id AND DelData = 'N'");
                $stmtCheck->execute([':id' => $id]);
                if ($stmtCheck->fetchColumn() > 0) {
                    $_SESSION['alert'] = ['status' => 'error', 'title' => 'ไม่สามารถลบได้', 'text' => 'กลุ่มนี้ถูกใช้งานอยู่ในข่าวสาร'];
                } else {
                    $sql = "UPDATE cio_news_groups SET DelData = 'Y', DelDataDate = NOW(), DelDataBy = :StaffID, DelDataIP = :CreateIP WHERE GroupID = :id";
                    $stmt = $DB->prepare($sql);
                    $stmt->execute([':StaffID' => $StaffID, ':CreateIP' => $CreateIP, ':id' => $id]);
                    $_SESSION['alert'] = ['status' => 'success', 'title' => 'ลบข้อมูลสำเร็จ'];
                }
            }
            header("Location: group.php");
            exit();

        case 'toggle_status':
            $id = $_GET['id'] ?? 0;
            $status = $_GET['status'] ?? 'N';
            if ($id) {
                $sql = "UPDATE cio_news_groups SET IsActive = :status, UpdateDate = NOW(), UpdateBy = :StaffID, UpdateIP = :CreateIP WHERE GroupID = :id";
                $stmt = $DB->prepare($sql);
                $stmt->execute([':status' => $status, ':StaffID' => $StaffID, ':CreateIP' => $CreateIP, ':id' => $id]);
                $_SESSION['alert'] = ['status' => 'success', 'title' => 'อัพเดทสถานะสำเร็จ'];
            }
            header("Location: group.php");
            exit();

        case 'update_order':
            $id = $_GET['id'] ?? 0;
            $order = $_GET['order'] ?? 1;
            if ($id) {
                $sql = "UPDATE cio_news_groups SET OrderNo = :order, UpdateDate = NOW(), UpdateBy = :StaffID, UpdateIP = :CreateIP WHERE GroupID = :id";
                $stmt = $DB->prepare($sql);
                $stmt->execute([':order' => $order, ':StaffID' => $StaffID, ':CreateIP' => $CreateIP, ':id' => $id]);
                echo json_encode(['status' => 'success']);
                exit();
            }
            echo json_encode(['status' => 'error']);
            exit();

        case 'bulk_active':
            $ids = explode(',', $_GET['ids'] ?? '');
            if (!empty($ids)) {
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $sql = "UPDATE cio_news_groups SET IsActive = 'Y', UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE GroupID IN ($placeholders)";
                $params = array_merge([$StaffID, $CreateIP], $ids);
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
                $_SESSION['alert'] = ['status' => 'success', 'title' => 'เปิดใช้งานรายการที่เลือกสำเร็จ'];
            }
            header("Location: group.php");
            exit();

        case 'bulk_inactive':
            $ids = explode(',', $_GET['ids'] ?? '');
            if (!empty($ids)) {
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $sql = "UPDATE cio_news_groups SET IsActive = 'N', UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE GroupID IN ($placeholders)";
                $params = array_merge([$StaffID, $CreateIP], $ids);
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
                $_SESSION['alert'] = ['status' => 'success', 'title' => 'ปิดการใช้งานรายการที่เลือกสำเร็จ'];
            }
            header("Location: group.php");
            exit();

        case 'bulk_delete':
            $ids = explode(',', $_GET['ids'] ?? '');
            if (!empty($ids)) {
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $sql = "UPDATE cio_news_groups SET DelData = 'Y', DelDataDate = NOW(), DelDataBy = ?, DelDataIP = ? WHERE GroupID IN ($placeholders)";
                $params = array_merge([$StaffID, $CreateIP], $ids);
                $stmt = $DB->prepare($sql);
                $stmt->execute($params);
                $_SESSION['alert'] = ['status' => 'success', 'title' => 'ลบรายการที่เลือกสำเร็จ'];
            }
            header("Location: group.php");
            exit();

        default:
            header("Location: group.php");
            exit();
    }
} catch (PDOException $e) {
    $_SESSION['alert'] = ['status' => 'error', 'title' => 'เกิดข้อผิดพลาด', 'text' => $e->getMessage()];
    header("Location: group.php");
    exit();
}
?>
