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
bofSecurityRequireMethod(['POST']);
bofSecurityRequireCsrf();
$MS = new MS($DB);
$StaffID = (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0);

$action = $_POST['action'] ?? '';

    function uploadMultipleFiles($filesArray, $uploadDir, $dbUploadDir, $newsId, $db, $table, $pathCol, $isAttachment = false) {
        global $_Config, $SetMaxSize, $SetFileSize, $StaffID;
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $count = is_array($filesArray['name']) ? count($filesArray['name']) : 0;
        for ($i = 0; $i < $count; $i++) {
            if ($filesArray['error'][$i] === 0) {

                $singleFile = [
									'name' => $filesArray['name'][$i],
									'type' => $filesArray['type'][$i],
									'tmp_name' => $filesArray['tmp_name'][$i],
									'error' => $filesArray['error'][$i],
									'size' => $filesArray['size'][$i]
                ];
                
                $fileName = $singleFile['name'];
                
                if ($isAttachment) {
									$res = FU::uploadNewFile($singleFile, $uploadDir, $_Config['AllowedExtensions']['File'], $_Config['DeniedExtensions']['File'], $SetFileSize, 1);
									if ($res['Flag'] == 0 && !empty($res['sFileName'])) {
											$dbPath = $dbUploadDir . $res['sFileName'];
											if ($table === 'news_attachments') {
													$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
													$stmt = $db->prepare("INSERT INTO $table (NewsID, FileName_TH, $pathCol, FileType, FileSize, OrderNo, CreateDate, CreateBy, CreateIP, DelData) VALUES (?, ?, ?, ?, ?, 9999, NOW(), ?, ?, 'N')");
											$stmt->execute([$newsId, $fileName, $dbPath, $ext, $singleFile['size'], $StaffID, $_SERVER['REMOTE_ADDR']]);
											} else {
													$stmt = $db->prepare("INSERT INTO $table (NewsID, FileName_TH, $pathCol, OrderNo) VALUES (?, ?, ?, 9999)");
													$stmt->execute([$newsId, $fileName, $dbPath]);
											}
									}
                } else {
									$res = FU::uploadNewImageAndResize($singleFile, $uploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
									if ($res['Flag'] == 0 && !empty($res['sFileName'])) {
											$dbPath = $dbUploadDir . $res['sFileName'];
											$stmt = $db->prepare("INSERT INTO $table (NewsID, $pathCol, OrderNo) VALUES (?, ?, 9999)");
											$stmt->execute([$newsId, $dbPath]);
									}
                }
            }
        }
    }

    $uploadDir = $sServerImgDir;
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    $slug = $_POST['Slug'] ?? '';
    if (empty($slug)) {
        $slugSource = !empty($_POST['Title_EN']) ? $_POST['Title_EN'] : $_POST['Title_TH'];
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $slugSource)));
    }
    $categoryId = $_POST['CategoryID'] ?? '';
    $isActive = $_POST['IsActive'] ?? 'Y';

    if ($action === 'add') {
        $coverPath = '';
        if (isset($_FILES['CoverImage']) && $_FILES['CoverImage']['error'] === 0) {
            $newsUploadDir = $sServerImgDir . 'news/';
            if (!is_dir($newsUploadDir)) mkdir($newsUploadDir, 0777, true);
            $res = FU::uploadNewImageAndResize($_FILES['CoverImage'], $newsUploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
            if ($res['Flag'] == 0 && !empty($res['sFileName'])) $coverPath = $res['sFileName'];
        }

        try {
            $publisherName = $_SESSION['BofAdmin_User'] ?? 'Admin';

            $stmtMax = $DB->prepare("SELECT MAX(OrderNo) FROM news");
            $stmtMax->execute();
            $maxOrder = $stmtMax->fetchColumn();
            $orderNo = $maxOrder ? $maxOrder + 1 : 1;

            $stmt = $DB->prepare("INSERT INTO news (Title_TH, Title_EN, Slug, Excerpt_TH, Excerpt_EN, Content_TH, Content_EN, CategoryID, DateStr, CoverImage, IsActive, OrderNo, CreateDate, CreateBy, CreateIP, Publisher) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)");
            $stmt->execute([
                $_POST['Title_TH'], $_POST['Title_EN'], $slug, $_POST['Excerpt_TH'], $_POST['Excerpt_EN'], 
                $_POST['Content_TH'], $_POST['Content_EN'], $categoryId, $_POST['DateStr'],
                $coverPath, $isActive, $orderNo, $StaffID, $_SERVER['REMOTE_ADDR'], $publisherName
            ]);
            $newId = $DB->lastInsertId();
            
            if (isset($_FILES['Gallery'])) uploadMultipleFiles($_FILES['Gallery'], $sServerImgDir . 'news/gallery/', '', $newId, $DB, 'news_gallery', 'ImagePath', false);
            if (isset($_FILES['Attachments'])) uploadMultipleFiles($_FILES['Attachments'], $sServerDirFile . 'news/', '', $newId, $DB, 'news_attachments', 'FilePath', true);

            $MS->StaffLog('เพิ่มข่าวสาร', $_SERVER['REQUEST_URI'], $StaffID, 3, 7, $newId);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) { die("Error: " . $e->getMessage()); }
    } 
    elseif ($action === 'edit') {
        $id = $_POST['id'];
        $coverPath = $_POST['old_cover'] ?? '';
        
        if (isset($_POST['deleteCoverImageFlag']) && $_POST['deleteCoverImageFlag'] == '1') {
            if (!empty($coverPath) && file_exists($sServerImgDir . 'news/' . $coverPath)) {
                unlink($sServerImgDir . 'news/' . $coverPath);
                $webpPath = preg_replace('/\.[^.]+$/', '.webp', $coverPath);
                if (file_exists($sServerImgDir . 'news/' . $webpPath)) unlink($sServerImgDir . 'news/' . $webpPath);
            }
            $coverPath = '';
            if (isset($_FILES['CoverImage']) && $_FILES['CoverImage']['error'] === 0) {
                $newsUploadDir = $sServerImgDir . 'news/';
                if (!is_dir($newsUploadDir)) mkdir($newsUploadDir, 0777, true);
                $res = FU::uploadNewImageAndResize($_FILES['CoverImage'], $newsUploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, 1);
                if ($res['Flag'] == 0 && !empty($res['sFileName'])) {
                    $coverPath = $res['sFileName'];
                }
            }
        } elseif (isset($_FILES['CoverImage']) && $_FILES['CoverImage']['error'] === 0) {
            $oldFilename = basename($coverPath);
            $newsUploadDir = $sServerImgDir . 'news/';
            if (!is_dir($newsUploadDir)) mkdir($newsUploadDir, 0777, true);
            $res = FU::uploadEditImageAndResize($_FILES['CoverImage'], $newsUploadDir, $_Config['AllowedExtensions']['Image'], $_Config['DeniedExtensions']['Image'], $SetMaxSize, CONF_IMG_MAX_DIM, CONF_IMG_REC_W, CONF_IMG_REC_H, $oldFilename, 1);
            if ($res['Flag'] == 0 && !empty($res['sFileName'])) $coverPath = $res['sFileName'];
        }

        $deleteGalleries = $_POST['delete_galleries'] ?? '';
        if (!empty($deleteGalleries)) {
            foreach (explode(',', $deleteGalleries) as $gid) {
							if(empty($gid)) continue;
							$stmt = $DB->prepare("SELECT ImagePath FROM news_gallery WHERE GalleryID = ?");
							$stmt->execute([$gid]);
							if ($row = $stmt->fetch()) {
								if (file_exists($sServerImgDir . 'news/gallery/' . $row['ImagePath'])) {
									unlink($sServerImgDir . 'news/gallery/' . $row['ImagePath']);
									$webpPath = preg_replace('/\.[^.]+$/', '.webp', $row['ImagePath']);
									if (file_exists($sServerImgDir . 'news/gallery/' . $webpPath)) unlink($sServerImgDir . 'news/gallery/' . $webpPath);
								}
								$DB->prepare("DELETE FROM news_gallery WHERE GalleryID = ?")->execute([$gid]);
							}
            }
        }
        
        $deleteAttachments = $_POST['delete_attachments'] ?? '';
        if (!empty($deleteAttachments)) {
            foreach (explode(',', $deleteAttachments) as $aid) {
                if(empty($aid)) continue;
                $stmt = $DB->prepare("SELECT FilePath FROM news_attachments WHERE AttachmentID = ?");
                $stmt->execute([$aid]);
                if ($row = $stmt->fetch()) {
                  if (file_exists($sServerDirFile . 'news/' . $row['FilePath'])) unlink($sServerDirFile . 'news/' . $row['FilePath']);
                  $DB->prepare("UPDATE news_attachments SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE AttachmentID = ?")->execute([$StaffID, $_SERVER['REMOTE_ADDR'], $aid]);
                }
            }
        }

        try {
            $orderNo = isset($_POST['OrderNo']) ? $_POST['OrderNo'] : null;
            if ($orderNo === null) {
                $stmtOrder = $DB->prepare("SELECT OrderNo FROM news WHERE NewsID = ?");
                $stmtOrder->execute([$id]);
                $orderNo = (int)$stmtOrder->fetchColumn();
            }

            $stmt = $DB->prepare("UPDATE news SET 
                Title_TH=?, Title_EN=?, Slug=?, Excerpt_TH=?, Excerpt_EN=?, Content_TH=?, Content_EN=?, 
                CategoryID=?, DateStr=?, CoverImage=?, IsActive=?, OrderNo=?, UpdateDate=NOW(), UpdateBy=?, UpdateIP=? 
                WHERE NewsID=?");
            $stmt->execute([
                $_POST['Title_TH'], $_POST['Title_EN'], $slug, $_POST['Excerpt_TH'], $_POST['Excerpt_EN'], 
                $_POST['Content_TH'], $_POST['Content_EN'], $categoryId, $_POST['DateStr'],
                $coverPath, $isActive, $orderNo, $StaffID, $_SERVER['REMOTE_ADDR'], $id
            ]);
            
            if (isset($_FILES['Gallery'])) uploadMultipleFiles($_FILES['Gallery'], $sServerImgDir . 'news/gallery/', '', $id, $DB, 'news_gallery', 'ImagePath', false);
            if (isset($_FILES['Attachments'])) uploadMultipleFiles($_FILES['Attachments'], $sServerDirFile . 'news/', '', $id, $DB, 'news_attachments', 'FilePath', true);

            // Update order for galleries
            $galleryOrder = $_POST['gallery_order'] ?? '';
            if (!empty($galleryOrder)) {
                $gids = explode(',', $galleryOrder);
                foreach ($gids as $idx => $gid) {
                    if (!empty($gid)) {
                        $DB->prepare("UPDATE news_gallery SET OrderNo = ? WHERE GalleryID = ?")->execute([$idx + 1, $gid]);
                    }
                }
            }

            // Update order for attachments
            $attachmentsOrder = $_POST['attachments_order'] ?? '';
            if (!empty($attachmentsOrder)) {
                $aids = explode(',', $attachmentsOrder);
                foreach ($aids as $idx => $aid) {
                    if (!empty($aid)) {
                        $DB->prepare("UPDATE news_attachments SET OrderNo = ? WHERE AttachmentID = ?")->execute([$idx + 1, $aid]);
                    }
                }
            }

            $MS->StaffLog('แก้ไขข่าวสาร', $_SERVER['REQUEST_URI'], $StaffID, 3, 7, $id);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) { die("Error: " . $e->getMessage()); }
    }
elseif ($action === 'delete') {
    $id = $_POST['id'] ?? 0;
    try {
        $stmt = $DB->prepare("UPDATE news SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE NewsID=?");
        $stmt->execute([$StaffID, $_SERVER['REMOTE_ADDR'], $id]);
        
        // Update attachments and gallery
        try { 
            $stmtAtt = $DB->prepare("SELECT FilePath FROM news_attachments WHERE NewsID=?");
            $stmtAtt->execute([$id]);
            $DB->prepare("UPDATE news_attachments SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE NewsID=?")->execute([$StaffID, $_SERVER['REMOTE_ADDR'], $id]);
        } catch(PDOException $e) {}
        try { $DB->prepare("UPDATE news_gallery SET DelData='Y' WHERE NewsID=?")->execute([$id]); } catch(PDOException $e) {}
        
        // Log function helper
        $MS->StaffLog('ลบข่าวสาร', $_SERVER['REQUEST_URI'], $StaffID, 3, 7, $id);
        
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
} elseif ($action === 'bulk_delete') {
    $ids = $_POST['ids'] ?? '';
    if (!empty($ids)) {
        try {
            $idArray = array_filter(explode(',', $ids), 'is_numeric');
            if (!empty($idArray)) {
                $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                $params = array_merge([$StaffID, $_SERVER['REMOTE_ADDR']], $idArray);
                
                $stmt = $DB->prepare("UPDATE news SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE NewsID IN ($placeholders)");
                $stmt->execute($params);
                
                // Update attachments and gallery
                try { 
                    $stmtAtt = $DB->prepare("SELECT FilePath FROM news_attachments WHERE NewsID IN ($placeholders)");
                    $stmtAtt->execute($idArray);
                    $DB->prepare("UPDATE news_attachments SET DelData='Y', DelDataDate=NOW(), DelDataBy=?, DelDataIP=? WHERE NewsID IN ($placeholders)")->execute($params); 
                } catch(PDOException $e) {}
                try { $DB->prepare("UPDATE news_gallery SET DelData='Y' WHERE NewsID IN ($placeholders)")->execute($idArray); } catch(PDOException $e) {}
                
                // Log function helper
                $MS->StaffLog('ลบข่าวสาร', $_SERVER['REQUEST_URI'], $StaffID, 3, 7);
            }
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
} elseif (in_array($action, ['bulk_active', 'bulk_inactive'])) {
    $ids = $_POST['ids'] ?? '';
    if (!empty($ids)) {
        try {
            $idArray = array_filter(explode(',', $ids), 'is_numeric');
            if (!empty($idArray)) {
                $status = ($action === 'bulk_active') ? 'Y' : 'N';
                $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                $params = array_merge([$status], $idArray);
                
                $stmt = $DB->prepare("UPDATE news SET IsActive=? WHERE NewsID IN ($placeholders)");
                $stmt->execute($params);
                
                // Log function helper
                $MS->StaffLog('เปลี่ยนสถานะข่าวสาร', $_SERVER['REQUEST_URI'], $StaffID, 3, 7);
            }
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
}
?>
