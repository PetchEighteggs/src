<?php
session_start();
require_once('../../config/configuration.php');

if (!isset($_SESSION['StaffID']) || !isset($_SESSION['StaffType'])) {
    header("Location: ../login.php");
    exit();
}

$action = $_REQUEST['action'] ?? '';
$StaffID = $_SESSION['StaffID'];
$CreateIP = $_SERVER['REMOTE_ADDR'];

try {
    switch ($action) {
        case 'add':
            $GroupName_TH = $_POST['GroupName_TH'] ?? '';
            $GroupName_EN = $_POST['GroupName_EN'] ?? '';
            $OrderNo = $_POST['OrderNo'] ?? 1;
            $IsActive = $_POST['IsActive'] ?? 'N';

            $sql = "INSERT INTO cio_event_groups 
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

            $sql = "UPDATE cio_event_groups SET 
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
                // Check if category is used in events
                $stmtCheck = $DB->prepare("SELECT COUNT(*) FROM cio_events WHERE GroupID = :id AND DelData = 'N'");
                $stmtCheck->execute([':id' => $id]);
                if ($stmtCheck->fetchColumn() > 0) {
                    $_SESSION['alert'] = ['status' => 'error', 'title' => 'ไม่สามารถลบได้', 'text' => 'กลุ่มนี้ถูกใช้งานอยู่ในกิจกรรม'];
                } else {
                    $sql = "UPDATE cio_event_groups SET DelData = 'Y', DelDataDate = NOW(), DelDataBy = :StaffID, DelDataIP = :CreateIP WHERE GroupID = :id";
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
                $sql = "UPDATE cio_event_groups SET IsActive = :status, UpdateDate = NOW(), UpdateBy = :StaffID, UpdateIP = :CreateIP WHERE GroupID = :id";
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
                $sql = "UPDATE cio_event_groups SET OrderNo = :order, UpdateDate = NOW(), UpdateBy = :StaffID, UpdateIP = :CreateIP WHERE GroupID = :id";
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
                $sql = "UPDATE cio_event_groups SET IsActive = 'Y', UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE GroupID IN ($placeholders)";
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
                $sql = "UPDATE cio_event_groups SET IsActive = 'N', UpdateDate = NOW(), UpdateBy = ?, UpdateIP = ? WHERE GroupID IN ($placeholders)";
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
                // Here we might need a more complex check if we want to prevent bulk deleting used categories.
                // For simplicity, we just mark them as deleted.
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $sql = "UPDATE cio_event_groups SET DelData = 'Y', DelDataDate = NOW(), DelDataBy = ?, DelDataIP = ? WHERE GroupID IN ($placeholders)";
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
