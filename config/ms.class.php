<?php
class MS
{

	var $_db = null;

	//Constructor
	public function __construct($db){
		$this->_db = $db;
	}

	public function stringVal($value){
		if (is_object($value) && in_array("__toString", get_class_methods($value)))
			return strval($value->__toString());
		else
			return strval($value);
	}

	##============= Get function ===================================
	//Get Name of related table of nn_books
	public function getName($id, $tbl, $fieldid, $fieldname){
		$qry = "SELECT $fieldname FROM $tbl WHERE $fieldid = :id";
		try {
			$stmt = $this->_db->prepare($qry);
			$stmt->bindValue(':id', $id, PDO::PARAM_STR);
			$stmt->execute();
			$result = $stmt->fetch(PDO::FETCH_NUM);
			if ($result && $result[0] != "") {
				return $result[0];
			} else {
				return "-";
			}
		} catch (PDOException $e) {
			return "-";
		}
	}

	public function qstr($string, $magic_quotes = false) {
		if (is_null($string)) {
			return 'NULL';
		}
		if (is_bool($string)) {
			return $string ? '1' : '0';
		}
		if (is_numeric($string)) {
			return $string;
		}
		global $DB;
		if (isset($DB) && method_exists($DB, 'quote')) {
			return $DB->quote($string);
		}
		return "'" . addslashes($string) . "'";
	}

	// Back Office -------------------------------------------------------------------------------------------------------------------------------------------------------------
	public function StaffName($StaffID){
		global $_Config_table;
		$ArrUser = 'ไม่ระบุ';
		if (!empty($StaffID)) {
			$qryUser = "SELECT Name FROM staff WHERE StaffID = :staffId";
			try {
				$stmt = $this->_db->prepare($qryUser);
				$stmt->bindValue(':staffId', $StaffID, PDO::PARAM_STR);
				$stmt->execute();
				if ($stmt->rowCount() > 0) {
					$rowUser = $stmt->fetch(PDO::FETCH_ASSOC);
					$ArrUser = $rowUser['Name'];
				}
			} catch (PDOException $e) {
				// Error handling
			}
		}
		return $ArrUser;
	}

	function ClearSession(){
		unset($_SESSION['_ID']);
		unset($_SESSION['_LOGIN']);
		unset($_SESSION['_LOGINNAME']);
		unset($_SESSION['_TOKEN']);
		unset($_SESSION['_SETGID']);
		unset($_SESSION['_GRPID']);
		unset($_SESSION['_GRPLEVEL']);
		unset($_SESSION['_HUB']);
		unset($_SESSION['_LASTLOGIN']);

	}

	public function PageName($SectionID){
		$qryChk = "SELECT SectionName
		FROM bof_section WHERE
		SectionID = :sectionId";
		try {
			$stmt = $this->_db->prepare($qryChk);
			$stmt->bindValue(':sectionId', $SectionID, PDO::PARAM_STR);
			$stmt->execute();
			$rowChk = $stmt->fetch(PDO::FETCH_ASSOC);
			return $rowChk ? $rowChk["SectionName"] : '';
		} catch (PDOException $e) {
			return '';
		}
	}

	public function UserLoginLog($StaffID, $Action){
		global $Rip;
		if (!empty($StaffID)) {
			$qryInsLog = "INSERT INTO staff_login_log(StaffID, Action, CreateDate, CreateIP) VALUES (:staffId, :action, NOW(), :createIp)";
			try {
				$stmt = $this->_db->prepare($qryInsLog);
				$stmt->bindValue(':staffId', $StaffID, PDO::PARAM_INT);
				$stmt->bindValue(':action', $Action, PDO::PARAM_STR);
				$stmt->bindValue(':createIp', $Rip, PDO::PARAM_STR);
				$stmt->execute();
			} catch (PDOException $e) {
				// Error handling
			}
		}
	}

	public function ChkBofUserLogin($StaffID, $token){
		global $Rip;
		$Return = "N";
		if (!empty($StaffID) && !empty($token)) {
			$qryChk = "SELECT StaffID
			FROM staff WHERE
			StaffID = :staffId AND
			StaffToken = :token AND
			DelData != 'Y' AND
			Status = 'Y'";
			$stmt = $this->_db->prepare($qryChk);
			$stmt->bindValue(':staffId', $StaffID, PDO::PARAM_STR);
			$stmt->bindValue(':token', $token, PDO::PARAM_STR);
			$stmt->execute();

			if ($stmt->rowCount() > 0) {
				$qryUpdate = "UPDATE staff SET
				LastAccess = NOW(),
				LastAccessIP = :lastAccessIp WHERE
				StaffID = :staffId";

				$stmtUpdate = $this->_db->prepare($qryUpdate);
				$stmtUpdate->bindValue(':lastAccessIp', $Rip, PDO::PARAM_STR);
				$stmtUpdate->bindValue(':staffId', $StaffID, PDO::PARAM_STR);
				$stmtUpdate->execute();
				$Return = "Y";
			}
		}
		return $Return;
	}

	public function permission_access($PageID, $GRPID, $ID){
		$Data = 0;
		if ($GRPID != "A") {
			if (!empty($PageID)) {
				$qrySetGroup = "SELECT SetGroupID
				FROM staff WHERE
				StaffID = :staffId AND
				Status = 'Y'";

				$stmt = $this->_db->prepare($qrySetGroup);
				$stmt->bindValue(':staffId', $ID, PDO::PARAM_STR);
				$stmt->execute();

				if ($stmt->rowCount() > 0) {
					$rowSetGroup = $stmt->fetch(PDO::FETCH_ASSOC);
					$qryAccess = "SELECT * FROM staff_permission WHERE
					SectionID = :pageId AND
					SetGroupID = :setGroupId";

					$stmtAccess = $this->_db->prepare($qryAccess);
					$stmtAccess->bindValue(':pageId', $PageID, PDO::PARAM_STR);
					$stmtAccess->bindValue(':setGroupId', $rowSetGroup["SetGroupID"], PDO::PARAM_STR);
					$stmtAccess->execute();

					if ($stmtAccess->rowCount() > 0) {
						$Data = "1";
					}
				}
			}
		} else {
			$Data = "1";
		}
		return $Data;
	}

	public function UserPermission($PageID, $GRPID, $ID){
		$Return["Approve"] = 'N';
		$Return["View"] = 'N';
		$Return["Create"] = 'N';
		$Return["Edit"] = 'N';
		$Return["Del"] = 'N';
		if ($GRPID != "A") {
			try {
				$qrySetGroup = "SELECT SetGroupID
				FROM staff WHERE
				StaffID = :staffId AND
				Status = 'Y'";
				$stmt = $this->_db->prepare($qrySetGroup);
				$stmt->bindValue(':staffId', $ID, PDO::PARAM_STR);
				$stmt->execute();

				if ($stmt->rowCount() > 0) {
					$rowSetGroup = $stmt->fetch(PDO::FETCH_ASSOC);
					if (!empty($PageID)) {
						$qryAccess = "SELECT *
						FROM staff_permission WHERE
						SectionID = :pageId AND
						SetGroupID = :setGroupId";
						$stmtAccess = $this->_db->prepare($qryAccess);
						$stmtAccess->bindValue(':pageId', $PageID, PDO::PARAM_STR);
						$stmtAccess->bindValue(':setGroupId', $rowSetGroup["SetGroupID"], PDO::PARAM_STR);
						$stmtAccess->execute();

						if ($stmtAccess->rowCount() > 0) {
							$rowAccess = $stmtAccess->fetch(PDO::FETCH_ASSOC);
							$Return["View"] = 'Y';
							if ($rowAccess["CanAdd"] == 'Y') {
								$Return["Create"] = 'Y';
							}
							if ($rowAccess["CanEdit"] == 'Y') {
								$Return["Edit"] = 'Y';
							}
							if ($rowAccess["CanDelete"] == 'Y') {
								$Return["Del"] = 'Y';
							}
							if ($rowAccess["CanApprove"] == 'Y') {
								$Return["Approve"] = 'Y';
							}
						}
					}
				}
			} catch (PDOException $e) {
				// Error handling
			}
		} else {
			$Return["View"] = 'Y';
			$Return["Create"] = 'Y';
			$Return["Edit"] = 'Y';
			$Return["Del"] = 'Y';
			$Return["Approve"] = 'Y';
		}
		return $Return;
	}

	public function StaffLog($action, $action_url, $StaffID, $SectionGroupID = '', $SectionID = '', $ID = '', $SUBID = ''){
    global $Rip;
		try {
			$qryLogCHK = "SELECT LogID FROM bof_logs WHERE
			SectionGroupID = :sectionGroupId AND
			SectionID = :sectionId AND
			LogAction = :logAction AND
			ID = :id AND
			StaffID = :staffId AND
			CreateDate > DATE_SUB(NOW(), INTERVAL 10 MINUTE)";

			$stmtCheck = $this->_db->prepare($qryLogCHK);
			$stmtCheck->bindValue(':sectionGroupId', $SectionGroupID, PDO::PARAM_STR);
			$stmtCheck->bindValue(':sectionId', $SectionID, PDO::PARAM_STR);
			$stmtCheck->bindValue(':logAction', $action, PDO::PARAM_STR);
			$stmtCheck->bindValue(':id', $ID, PDO::PARAM_STR);
			$stmtCheck->bindValue(':staffId', $StaffID, PDO::PARAM_STR);
			$stmtCheck->execute();

			if ($stmtCheck->rowCount() == 0) {
				$qryInsLog = "INSERT INTO bof_logs (
				StaffID, SectionGroupID, SectionID,
				LogAction, LogActionUrl, ID, SUBID,
				CreateDate, CreateIP) VALUES (
				:staffId, :sectionGroupId, :sectionId,
				:logAction, :logActionUrl, :id, :subId,
				NOW(), :createIp)";

				$stmtInsert = $this->_db->prepare($qryInsLog);
				$stmtInsert->bindValue(':staffId', $StaffID, PDO::PARAM_STR);
				$stmtInsert->bindValue(':sectionGroupId', $SectionGroupID, PDO::PARAM_STR);
				$stmtInsert->bindValue(':sectionId', $SectionID, PDO::PARAM_STR);
				$stmtInsert->bindValue(':logAction', $action, PDO::PARAM_STR);
				$stmtInsert->bindValue(':logActionUrl', $action_url, PDO::PARAM_STR);
				$stmtInsert->bindValue(':id', $ID, PDO::PARAM_STR);
				$stmtInsert->bindValue(':subId', $SUBID, PDO::PARAM_STR);
				$stmtInsert->bindValue(':createIp', $Rip, PDO::PARAM_STR);
				$stmtInsert->execute();

				if ($stmtInsert->rowCount() > 0) {
					$qryCleanup = "DELETE FROM bof_logs WHERE DATE_SUB(NOW(), INTERVAL 12 MONTH) >= CreateDate";
					$stmtCleanup = $this->_db->prepare($qryCleanup);
					$stmtCleanup->execute();
				}
			}
		} catch (PDOException $e) {
			// Error handling
		}
	}

	public function StaffLogDetail($LogID){
		global $_Config_live_site;
		$Return = array();

		try {
			$qryChkLog = "SELECT * FROM bof_logs WHERE LogID = :logId";
			$stmt = $this->_db->prepare($qryChkLog);
			$stmt->bindValue(':logId', $LogID, PDO::PARAM_STR);
			$stmt->execute();

			if ($stmt->rowCount() > 0) {
				$RowChkLog = $stmt->fetch(PDO::FETCH_ASSOC);

				$qryMenu = "SELECT SectionURL, SectionName FROM bof_section WHERE SectionID = :sectionId";
				$stmtMenu = $this->_db->prepare($qryMenu);
				$stmtMenu->bindValue(':sectionId', $RowChkLog["SectionID"], PDO::PARAM_STR);
				$stmtMenu->execute();
				$rowMenu = $stmtMenu->fetch(PDO::FETCH_ASSOC);

				$Return["Status"] = true;

				$Link = '';
        $Description ='';

			$Return["Description"] = $Description;
			$Return["Link"] = $Link;

			} else {
				$Return["Status"] = false;
			}
		} catch (PDOException $e) {
			// Error handling
			$Return["Status"] = false;
		}
		return $Return;
	}

	// All Stat Log -------------------------------------------------------------------------------------------------------------------------------------------------------------

	public function micrositeAuditContext()
	{
		global $Rip;

		return [
			'staff_id' => (int)($_SESSION['StaffID'] ?? $_SESSION['admin_id'] ?? 0),
			'ip' => (string)($Rip ?? ($_SERVER['REMOTE_ADDR'] ?? '')),
		];
	}

	public function transferStat($Type){
		global $_Config_table;
		$ID = 'ID';
		$TempLogTable = $Type."_log_temp";
		$LogTable = $Type."_log";

		if($Type != '' && $ID != ''){
			try {
				// Get IDs for logs that should be transferred
				$qryChkID = "SELECT $ID FROM $TempLogTable WHERE 1 AND
				DATE(CreateDate) < CURDATE()
				GROUP BY $ID
				ORDER BY $ID ASC";

				$stmtChkID = $this->_db->prepare($qryChkID);
				$stmtChkID->execute();

				while($rowChkID = $stmtChkID->fetch(PDO::FETCH_ASSOC)){
					// Get statistics for each ID
					$qryStatLog = "SELECT DATE(CreateDate) AS Date, COUNT(LogID) AS Amount
					FROM $TempLogTable WHERE
					$ID = :id AND
					DATE(CreateDate) < CURDATE()
					GROUP BY DATE(CreateDate)
					ORDER BY CreateDate ASC";

					$stmtStatLog = $this->_db->prepare($qryStatLog);
					$stmtStatLog->bindValue(':id', $rowChkID[$ID], PDO::PARAM_STR);
					$stmtStatLog->execute();

					while($rowStatLog = $stmtStatLog->fetch(PDO::FETCH_ASSOC)){
						// Check if log already exists
						$qryChkLog = "SELECT LogID FROM $LogTable WHERE
						$ID = :id AND
						DATE(LogDate) = :logDate";

						$stmtChkLog = $this->_db->prepare($qryChkLog);
						$stmtChkLog->bindValue(':id', $rowChkID[$ID], PDO::PARAM_STR);
						$stmtChkLog->bindValue(':logDate', $rowStatLog["Date"], PDO::PARAM_STR);
						$stmtChkLog->execute();

						if ($stmtChkLog->rowCount() == 0) {
							// Insert log record
							$qryInsLog = "INSERT INTO $LogTable ($ID, LogDate, LogNum)
							VALUES (:id, :logDate, :amount)";

							$stmtInsLog = $this->_db->prepare($qryInsLog);
							$stmtInsLog->bindValue(':id', $rowChkID[$ID], PDO::PARAM_STR);
							$stmtInsLog->bindValue(':logDate', $rowStatLog["Date"], PDO::PARAM_STR);
							$stmtInsLog->bindValue(':amount', $rowStatLog["Amount"], PDO::PARAM_STR);
							$stmtInsLog->execute();

							if($stmtInsLog->rowCount() > 0){
								// Delete from temp log after successful insert
								$qryDelTempLog = "DELETE FROM $TempLogTable WHERE
								$ID = :id AND
								DATE(CreateDate) = :createDate";

								$stmtDelTempLog = $this->_db->prepare($qryDelTempLog);
								$stmtDelTempLog->bindValue(':id', $rowChkID[$ID], PDO::PARAM_STR);
								$stmtDelTempLog->bindValue(':createDate', $rowStatLog["Date"], PDO::PARAM_STR);
								$stmtDelTempLog->execute();
							}
						}
					}
				}
			} catch (PDOException $e) {
				// Error handling
			}
		}
	}

	// All Stat Log -------------------------------------------------------------------------------------------------------------------------------------------------------------

	public function micrositeUploadFail($message)
	{
		$safeMessage = json_encode((string)$message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		echo "<script>alert(" . $safeMessage . "); window.history.back();</script>";
		exit;
	}

	public function micrositeUploadDirectory($baseDirectory, $slug)
	{
		$slug = trim((string)$slug);
		if ($slug === '' || !preg_match('/^[a-z0-9_-]+$/i', $slug)) {
			throw new RuntimeException('ไม่พบ slug ของหน่วยงาน หรือ slug มีรูปแบบไม่ถูกต้อง');
		}

		return rtrim($baseDirectory, '/\\') . DIRECTORY_SEPARATOR
			. 'microsite' . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR;
	}

	public function micrositeUploadErrorMessage($error)
	{
		$messages = [
			UPLOAD_ERR_INI_SIZE => 'ไฟล์มีขนาดใหญ่กว่าที่เซิร์ฟเวอร์อนุญาต',
			UPLOAD_ERR_FORM_SIZE => 'ไฟล์มีขนาดใหญ่กว่าที่แบบฟอร์มอนุญาต',
			UPLOAD_ERR_PARTIAL => 'อัปโหลดไฟล์มาไม่ครบ กรุณาลองใหม่',
			UPLOAD_ERR_NO_TMP_DIR => 'เซิร์ฟเวอร์ไม่มีโฟลเดอร์ชั่วคราวสำหรับอัปโหลด',
			UPLOAD_ERR_CANT_WRITE => 'เซิร์ฟเวอร์ไม่สามารถเขียนไฟล์ลงดิสก์ได้',
			UPLOAD_ERR_EXTENSION => 'ส่วนขยายของ PHP หยุดการอัปโหลดไฟล์',
		];

		return $messages[$error] ?? 'อัปโหลดไฟล์ไม่สำเร็จ (รหัส ' . (int)$error . ')';
	}

	public function micrositeStoreUpload(array $file, $uploadDirectory, array $allowedExtensions, $maxSize, $imageOnly = false)
	{
		$error = isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;
		if ($error === UPLOAD_ERR_NO_FILE) {
			return null;
		}
		if ($error !== UPLOAD_ERR_OK) {
			throw new RuntimeException($this->micrositeUploadErrorMessage($error));
		}

		$extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
		if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
			throw new RuntimeException('ประเภทไฟล์ไม่ถูกต้อง อนุญาตเฉพาะ: ' . implode(', ', $allowedExtensions));
		}
		if ((int)($file['size'] ?? 0) > (int)$maxSize) {
			throw new RuntimeException('ไฟล์มีขนาดใหญ่เกินกว่าที่ระบบกำหนด');
		}

		$temporaryFile = (string)($file['tmp_name'] ?? '');
		if ($temporaryFile === '' || !is_uploaded_file($temporaryFile)) {
			throw new RuntimeException('ไม่พบไฟล์อัปโหลดชั่วคราว กรุณาลองใหม่');
		}
		if ($imageOnly && @getimagesize($temporaryFile) === false) {
			throw new RuntimeException('ไฟล์ที่เลือกไม่ใช่ไฟล์รูปภาพที่ถูกต้อง');
		}

		if (!is_dir($uploadDirectory)
			&& !mkdir($uploadDirectory, 0775, true)
			&& !is_dir($uploadDirectory)) {
			throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์อัปโหลด: ' . $uploadDirectory);
		}
		if (!is_writable($uploadDirectory)) {
			throw new RuntimeException('ไม่มีสิทธิ์เขียนไฟล์ในโฟลเดอร์: ' . $uploadDirectory);
		}

			do {
				$fileName = date('ymdHis') . strtolower(FU::makeRandom()) . '.' . $extension;
			} while (is_file($uploadDirectory . $fileName));
		if (!move_uploaded_file($temporaryFile, $uploadDirectory . $fileName)) {
			throw new RuntimeException('ย้ายไฟล์ไปยังโฟลเดอร์ปลายทางไม่สำเร็จ');
		}

		return $fileName;
	}
} //End Class
