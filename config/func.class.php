<?php
require_once __DIR__ . '/env.php';

if (file_exists($_Config_absolute_path . "/vendor/autoload.php")) {
    require_once( $_Config_absolute_path . "/vendor/autoload.php" );
}
use WebPConvert\WebPConvert;
class FU
{ 
	public static function GetIP(){
		return $_SERVER["REMOTE_ADDR"];
	}

	#=============================================================================
	# Send Mail
	#=============================================================================
	public static function send_mail($subject, $msg, $to_name, $to_email, $from_name, $from_email, $isSMTP = 0){

		//$mail = new PHPMailer();
		$mail = new PHPMailer\PHPMailer\PHPMailer();

		if ($isSMTP == "1") {

			$mail->IsSMTP();				
			$mail->SMTPDebug = 0;
			$mail->Host = "localhost";
			$mail->SMTPAuth = false;
			$mail->Username = "";
			$mail->Password = "";

			$mail->IsSMTP();
			$mail->SMTPDebug = 0;
			$mail->SMTPAuth = envBool('DGR_SMTP_AUTH', true);
			$mail->SMTPAutoTLS = envBool('DGR_SMTP_AUTO_TLS', false);
			$mail->Port = (int)envValue('DGR_SMTP_PORT', 587);
			$mail->CharSet = "utf-8";
			$mail->Host = envRequired('DGR_SMTP_HOST');
			$mail->Username = envRequired('DGR_SMTP_USERNAME');
			$mail->Password = envRequired('DGR_SMTP_PASSWORD');

		}

		$email = $to_email;
		$name = $to_name;
		$mail->Priority = 3;
		$mail->Encoding = "8bit";
		$mail->CharSet = "utf-8";
		$mail->From = $from_email;
		$mail->FromName = $from_name;
		$mail->WordWrap = 50; // set word wrap
		$mail->IsHTML(true); // send as HTML
		$mail->Subject = $subject;
		$mail->Body =  $msg;

		$mail->AltBody  =  "This is the text-only body";

		$mail->AddAddress($email, $name);

		if (!$mail->Send()) {
			// echo "<br><font color=red>Message was not sent <p>";
			// echo "Mailer Error: " . $mail->ErrorInfo;
			// echo "There has been a mail error sending to ". $email."</font><br>";
			$sendFlag = 0;
		} else {
			//Write $somecontent to our opened file.
			// $content = "Success send mail to ".$email." ". date("l dS of F Y h:i:s A") ."\n";
			// echo $content;
			$sendFlag = 1;
		}

		// Clear all addresses and attachments for next loop
		$mail->ClearAddresses();
		$mail->ClearAttachments();
		$mail->ClearCCs();

		unset($mail);
		$msg = "";
		unset($msg);

		return $sendFlag;
	} 

	public static function utf8_strlen($str){
		$c = strlen($str);
		$l = 0;
		for ($i = 0; $i < $c; ++$i) {
			if ((ord($str[$i]) & 0xC0) != 0x80) {
				++$l;
			}
		}
		return $l;
	}

	public static function str_replace_text($word){
		//$word= FU::ucname($word);
		$strwordArr_3 = array("&");
		$strCensor_3 = "And";
		foreach ($strwordArr_3 as $value_3) {
			$word = str_replace($value_3, $strCensor_3, $word);
		}

		$strwordArr_4 = array(" ");
		$strCensor_4 = "-"; //"_" ;
		foreach ($strwordArr_4 as $value_4) {
			$word = str_replace($value_4, $strCensor_4, $word);
		}

		$strwordArr = array("#", ":", "'", "\""); //,"-"
		$strCensor = "";
		foreach ($strwordArr as $value) {
			$word = str_replace($value, $strCensor, $word);
		}

		$strwordArr_2 = array("(", ")", "/");
		$strCensor_2 = "";
		foreach ($strwordArr_2 as $value_2) {
			$word = str_replace($value_2, $strCensor_2, $word);
		}

		$word = str_replace("--", "/", $word);

		return ($word);
		//return FU::ucname($word) ;
	}

	public static function urlencode($string){
		return urlencode(utf8_encode($string));
	}

	public static function ucname($string){
		$string = ucwords(strtolower($string));

		foreach (array('-', '\'') as $delimiter) {
			if (strpos($string, $delimiter) !== false) {
				$string = implode($delimiter, array_map('ucfirst', explode($delimiter, $string)));
			}
		}
		return $string;
	}
	
	public static function CleanURL($Url){
		$pattern = array('?', ' ', ',', '.', '/ą/', '/Ą/', '/ć/', '/Ć/', '/ę/', '/Ę/', '/ł/', '/Ł/', '/ń/', '/Ń/', '/ó/', '/Ó/', '/ś/', '/Ś/', '/ź/', '/Ź/', '/ż/', '/Ż/', '&', "(", ")", "/", '"', "'", "%", "|", "*", '°', '®', '”', '™');
		$replacement = array('-', '-', '-', '-', 'a', 'A', 'c', 'C', 'e', 'E', 'l', 'L', 'n', 'N', 'o', 'O', 's', 'S', 'z', 'Z', 'z', 'Z', 'and', '-', '-', '-', '-', '-', '-percent', '-', '-', '-', '', '', '');
		$string_url = strtolower(str_replace($pattern, $replacement, $Url));
		$string_url = str_replace('--', '-', $string_url);
		if ($string_url[strlen($string_url) - 1] == '-') {
			$string_url = substr($string_url, 0, -1);
		}
		$string_url = str_replace('--', '-', $string_url);
		if ($string_url[strlen($string_url) - 1] == '-') {
			$string_url = substr($string_url, 0, -1);
		}
		$string_url = str_replace('----', '-', $string_url);
		$string_url = str_replace('---', '-', $string_url);
		$string_url = str_replace('--', '-', $string_url);
		return $string_url;
	}

	public static function rip_tags($string){
		// ----- remove HTML TAGs -----
		$string = preg_replace('/<[^>]*>/', ' ', $string);
		// ----- remove control characters -----
		$string = str_replace("\r", '', $string);    // --- replace with empty space
		$string = str_replace("\n", ' ', $string);   // --- replace with space
		$string = str_replace("\t", ' ', $string);   // --- replace with space
		// ----- remove multiple spaces -----
		$string = trim(preg_replace('/ {2,}/', ' ', $string));
		return $string;
	}

	public static function PureUrl($x){
		$url = preg_replace('/https?:\/\/|www./', '', $x);
		if (strpos($url, '/') !== false) {
			$ex = explode('/', $url);
			$url = $ex['0'];
		}
		return $url;
	}

	public static function sanitizeItem($var, $type){
		$flags = NULL;
		switch ($type) {
			case 'url':
				$filter = FILTER_SANITIZE_URL;
				break;
			case 'int':
				$filter = FILTER_SANITIZE_NUMBER_INT;
				break;
			case 'float':
				$filter = FILTER_SANITIZE_NUMBER_FLOAT;
				$flags = FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND;
				break;
			case 'email':
				$var = substr($var, 0, 254);
				$filter = FILTER_SANITIZE_EMAIL;
				break;
			case 'string':
			default:
				$filter = FILTER_SANITIZE_STRING;
				$flags = FILTER_FLAG_NO_ENCODE_QUOTES;
				break;
		}
		$output = filter_var($var, $filter, $flags);

		$output = trim($output);
		$output = stripslashes($output);
		$output = htmlspecialchars($output);
		return ($output);
	}

	public static function validateItem($var, $type){
		$regexes = array(
			'date' => "^[0-9]{1,2}[-/][0-9]{1,2}[-/][0-9]{4}\$",
			'amount' => "^[-]?[0-9]+\$",
			'number' => "^[-]?[0-9,]+\$",
			'alfanum' => "^[0-9a-zA-Z ,.-_\\s\?\!]+\$",
			'not_empty' => "[a-z0-9A-Z]+",
			'words' => "^[A-Za-z]+[A-Za-z \\s]*\$",
			'phone' => "^[0-9]{10,11}\$",
			'zipcode' => "^[1-9][0-9]{3}[a-zA-Z]{2}\$",
			'plate' => "^([0-9a-zA-Z]{2}[-]){2}[0-9a-zA-Z]{2}\$",
			'price' => "^[0-9.,]*(([.,][-])|([.,][0-9]{2}))?\$",
			'2digitopt' => "^\d+(\,\d{2})?\$",
			'2digitforce' => "^\d+\,\d\d\$",
			'anything' => "^[\d\D]{1,}\$"
		);

		if (array_key_exists($type, $regexes)) {
			$returnval =  filter_var($var, FILTER_VALIDATE_REGEXP, array("options" => array("regexp" => '!' . $regexes[$type] . '!i'))) !== false;
			return ($returnval);
		}
		$filter = false;
		switch ($type) {
			case 'email':
				$var = substr($var, 0, 254);
				$filter = FILTER_VALIDATE_EMAIL;
				break;
			case 'int':
				$filter = FILTER_VALIDATE_INT;
				break;
			case 'boolean':
				$filter = FILTER_VALIDATE_BOOLEAN;
				break;
			case 'ip':
				$filter = FILTER_VALIDATE_IP;
				break;
			case 'url':
				$filter = FILTER_VALIDATE_URL;
				break;
		}
		if($filter === false){
			return false;
		}else{
			if(filter_var($var, $filter) !== false){
				return true;
			}else{
				return false;
			}
		}
	}


	public static function qstr($string, $magic_quotes = false) {
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

	public static function uploadNewFile($FVARS, $sServerDir, $arAllowed, $arDenied, $maxSize, $rename = 1){
		$sFileName = "";
		$oFile = $FVARS;

		if (!empty($oFile['name'])) {

			// Get the uploaded file name and extension.
			$sFileName = $oFile['name'];

			//File size
			if ($oFile['size'] > $maxSize) {
				$resultReturn["Flag"] = 1;
				$resultReturn["Msg"] = "ไฟล์รูปที่ท่าน Upload มีขนาดใหญ่เกินที่กำหนดไว้";
				//echo $resultReturn["Msg"];
			} else {
				$resultReturn["Flag"] = 0;

				//File Allowed
				$sOriginalFileName = $sFileName;
				$sExtension = substr($sFileName, (strrpos($sFileName, '.') + 1));
				$sExtension = strtolower($sExtension);

				// Check if it is an allowed extension.
				if ((count($arAllowed) > 0 && !in_array($sExtension, $arAllowed)) || (count($arDenied) > 0 && in_array($sExtension, $arDenied))) {
					$resultReturn["Flag"] = 1;
					$resultReturn["Msg"] = "ไฟล์รูปที่ท่าน Upload ไม่ได้รับอนุญาตครับ";
					//echo $resultReturn["Msg"];
				} else {
					$resultReturn["Flag"] = 0;
					$iCounter = 0;

					while (true) {
						// Compose the file path.
						if ($rename == 1)
							$sFileName = date('ymdHis') . FU::makeRandom() . "." . $sExtension;
						$sFilePath = $sServerDir . $sFileName;

						// If a file with that name already exists.
						if (is_file($sFilePath)) {
							$iCounter++;
							$sFileName = FU::RemoveExtension($sOriginalFileName) . '(' . $iCounter . ').' . $sExtension;
						} else {
							if (!is_dir($sServerDir)) {
								@mkdir($sServerDir, 0777, true);
							}
							//move_uploaded_file( $oFile['tmp_name'], $sFilePath ) ;
							move_uploaded_file(iconv('TIS-620', 'UTF-8', $oFile['tmp_name']), $sFilePath);

							if (is_file($sFilePath)) {
								$oldumask = umask(0);
								chmod($sFilePath, 0777);
								umask($oldumask);
							}
							break;
						}
					}
				}
			}
			if ($resultReturn["Flag"]  == 1)
				$resultReturn["sFileName"] = "";
		} //if( !empty( $_FILES['file1'] ) )
		$resultReturn["sFileName"] = $sFileName;

		return $resultReturn;
	}
	#======================================================================================================================

	#//Upload Edit File
	public static function uploadEditFile($FVARS, $sServerDir, $arAllowed, $arDenied, $maxSize, $OldFile, $rename = 1){
		$sFileName = "";
		$oFile = $FVARS;

		//Upload and delete old files, Get the posted file.
		if (!empty($oFile['name'])) {

			// Get the uploaded file name and extension.
			$sFileName = $oFile['name'];

			//File size
			if ($oFile['size'] > $maxSize) {
				$resultReturn["Flag"] = 1;
				$resultReturn["Msg"] = "ไฟล์รูปที่ท่าน Upload มีขนาดใหญ่เกินที่กำหนดไว้";
				//echo "File1 ". $resultReturn["Msg"];
			} else {
				$resultReturn["Flag"]  = 0;

				//File Allowed
				$sOriginalFileName = $sFileName;
				$sExtension = substr($sFileName, (strrpos($sFileName, '.') + 1));
				$sExtension = strtolower($sExtension);

				// Check if it is an allowed extension.
				if ((count($arAllowed) > 0 && !in_array($sExtension, $arAllowed)) || (count($arDenied) > 0 && in_array($sExtension, $arDenied))) {
					$resultReturn["Flag"]  = 1;
					$resultReturn["Msg"] = "ไฟล์รูปที่ท่าน Upload ไม่ได้รับอนุญาตครับ";
					//echo "File1 ". $resultReturn["Msg"];
				} else {
					$resultReturn["Flag"]  = 0;

					//echo "Here Step 3";

					//Delete Old Picture
					//echo $sServerDir.$OldFile;
					//echo "<br>";
					if (file_exists($sServerDir . $OldFile)) {
						//echo "exists";
						//echo $sServerDir.$OldFile;
						@unlink($sServerDir . $OldFile);
					} else {
						//echo "Not exists";
					}
					//exit;

					// Initializes the counter used to rename the file, if another one with the same name already exists.
					$iCounter = 0;

					while (true) {
						// Compose the file path.
						if ($rename == 1)
							$sFileName = date('ymdHis') . FU::makeRandom() . "." . $sExtension;
						$sFilePath = $sServerDir . $sFileName;

						// If a file with that name already exists.
						if (is_file($sFilePath)) {
							$iCounter++;
							$sFileName = FU::RemoveExtension($sOriginalFileName) . '(' . $iCounter . ').' . $sExtension;
						} else {
							if (!is_dir($sServerDir)) {
								@mkdir($sServerDir, 0777, true);
							}
							move_uploaded_file(iconv('TIS-620', 'UTF-8', $oFile['tmp_name']), $sFilePath);

							if (is_file($sFilePath)) {
								$oldumask = umask(0);
								chmod($sFilePath, 0777);
								umask($oldumask);
							}

							break;
						}
					}
				}
			}

			$resultReturn["sFileName"] = $sFileName;

			if ($resultReturn["Flag"]  == 1)
				$resultReturn["sFileName"] = $OldFile;
		} //if( !empty( $_FILES['file1'] ) )
		else {
			$resultReturn["sFileName"] = $OldFile;
		}

		return $resultReturn;
	}

	//================================= upload =======================================//

	public static function ConvertImgToWebP($sServerDir, $fieldname){
		if($sServerDir != '' && $fieldname != ''){
			$WOptions = [];

			$dir = $sServerDir;
			$name = $fieldname;
			$source = $dir.basename($name);
			$namefile = pathinfo($name, PATHINFO_FILENAME);
			$destination = $dir.$namefile . '.webp';
			WebPConvert::convert($source, $destination, $WOptions);

			return basename($destination);
		}else{
			return '';
		}
	}
	public static function DirFileWebp($sServerDir, $fieldname){
    if( $sServerDir != '' && $fieldname != '' ){
			$dir = $sServerDir;
			$name = $fieldname;
			$source = $dir.basename($name);
			$namefile = pathinfo($name, PATHINFO_FILENAME);
			$destination_file = $dir.$namefile . '.webp';

			if (is_file($destination_file)) {
				 $destination_name = $namefile . '.webp';
			}else{
         $destination_name = ''; 
			}

			return $destination_name;

		}else{
			return '';
		}
	}

	public static function removeFilename($url){
		$file_info = pathinfo($url);
		return isset($file_info['extension'])? str_replace($file_info['filename'] . "." . $file_info['extension'], "", $url) : $url;
	}

	public static function compressImage($source, $maxSize){
		$info = getimagesize($source);
		$quality = 75;
		$image = null;

		switch ($info['mime']) {
				case 'image/jpeg':
						$image = imagecreatefromjpeg($source);
						break;
				case 'image/png':
						$image = imagecreatefrompng($source);
						$quality = 7;
						break;
		}

		if ($image) {
				ob_start();
				if ($info['mime'] == 'image/jpeg') {
						imagejpeg($image, null, $quality);
				} elseif ($info['mime'] == 'image/png') {
						imagepng($image, null, $quality);
				}
				$compressedData = ob_get_clean();
				$file = fopen($source, 'wb');
				if ($file) {
					fwrite($file, $compressedData);
					fclose($file);
				}
				imagedestroy($image);
		}
		return $source;
	}

	public static function resizeImage($file, $maxWidth, $maxHeight, $extension){
    list($origWidth, $origHeight) = getimagesize($file);
    $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
    $newWidth = $origWidth * $ratio;
    $newHeight = $origHeight * $ratio;
    
    $imageResized = imagecreatetruecolor($newWidth, $newHeight);
    
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            $image = imagecreatefromjpeg($file);
            break;
        case 'png':
            $image = imagecreatefrompng($file);
            imagealphablending($imageResized, false);
            imagesavealpha($imageResized, true);
            break;
        default:
            return $file;
    }
    
    imagecopyresampled($imageResized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
    
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($imageResized, $file, 80);
            break;
        case 'png':
            imagepng($imageResized, $file, 8);
            break;
    }
    
    imagedestroy($image);
    imagedestroy($imageResized);
    
    return $file;
	}

	public static function uploadNewImageAndResize($FVARS, $sServerDir, $arAllowed, $arDenied, $maxSize, $maxWidth, $reduceW, $reduceH, $rename = 1)
	{
			$resultReturn = ["Flag" => 0, "Msg" => "", "sFileName" => "", "sThumbnail" => "", "sWebPFile" => ""];
			$oFile = $FVARS;

			if (!empty($oFile['name'])) {
					$sFileName = $oFile['name'];
					$sExtension = strtolower(pathinfo($sFileName, PATHINFO_EXTENSION));

					if ($oFile['size'] > $maxSize) {
							$oFile['tmp_name'] = FU::compressImage($oFile['tmp_name'], $maxSize);
					}

					if (!in_array($sExtension, $arAllowed) || in_array($sExtension, $arDenied)) {
							$resultReturn["Flag"] = 1;
							$resultReturn["Msg"] = "ไฟล์รูปที่ท่าน Upload ไม่ได้รับอนุญาตครับ";
							return $resultReturn;
					}

					$size = getimagesize($oFile['tmp_name']);
					$width = $size[0];
					$height = $size[1];

					// if ($width > $maxWidth) {
					// 		$resultReturn["Flag"] = 1;
					// 		$resultReturn["Msg"] = "ไฟล์รูปที่ท่าน Upload มีความกว้างเกินที่กำหนดไว้";
					// 		return $resultReturn;
					// }

					if ($rename == 1) {
							$sFileName = date('ymdHis') . FU::makeRandom() . "." . $sExtension;
					}

					if (!is_dir($sServerDir)) {
						@mkdir($sServerDir, 0777, true);
					}
					
					$sFilePath = $sServerDir . $sFileName;
					move_uploaded_file($oFile['tmp_name'], $sFilePath);
					if (is_file($sFilePath)) {
						$oldumask = umask(0);
						chmod($sFilePath, 0777);
						umask($oldumask);
					}

					if ($width > $reduceW || $height > $reduceH) {
						$resizedFile = FU::resizeImage($sFilePath, $reduceW, $reduceH, $sExtension);
						$sFileName = pathinfo($resizedFile, PATHINFO_BASENAME);
						$resultReturn["sFileName"] = $sFileName;
					} else {
						$resultReturn["sFileName"] = $sFileName;
					}


					if ($resultReturn["Flag"]  == 1) {
						$resultReturn["sFileName"] = "";
					} else {
						if ($resultReturn["sThumbnail"] != "") {
							if (stripos($resultReturn["sFileName"], "gif") === false) {
								// $resultReturn["sWebPFile"] = FU::ConvertImgToWebP($sServerDir,$resultReturn["sFileName"]);
								$resultReturn["sWebPFile"] = "";
							}else{
								$resultReturn["sFileName"] = $sFileName;
							}
						} else {
							if (stripos($resultReturn["sFileName"], "gif") === false) {
								// $resultReturn["sWebPFile"] = FU::ConvertImgToWebP($sServerDir, $resultReturn["sFileName"]);
								$resultReturn["sWebPFile"] = "";
							}else{
								$resultReturn["sFileName"] = $sFileName;
							}
						}
					}
			}

		return $resultReturn;
	}

	public static function uploadEditImageAndResize($FVARS, $sServerDir, $arAllowed, $arDenied, $maxSize, $maxWidth, $reduceW, $reduceH, $OldPicture, $rename = 1){
    $resultReturn = ["Flag" => 0, "Msg" => "", "sFileName" => $OldPicture, "sWebPFile" => ""];
    $oFile = $FVARS;

    if (!empty($oFile['name'])) {
        $sFileName = $oFile['name'];
        $sExtension = strtolower(pathinfo($sFileName, PATHINFO_EXTENSION));

        if ($oFile['size'] > $maxSize) {
            $oFile['tmp_name'] = FU::compressImage($oFile['tmp_name'], $maxSize);
        }

        if (!in_array($sExtension, $arAllowed) || in_array($sExtension, $arDenied)) {
          $resultReturn["Flag"] = 1;
          $resultReturn["Msg"] = "ไฟล์รูปที่ท่าน Upload ไม่ได้รับอนุญาตครับ";
            return $resultReturn;
        }

        $size = getimagesize($oFile['tmp_name']);
        $width = $size[0];
        $height = $size[1];

        // if ($width > $maxWidth) {
				// 	$resultReturn["Flag"] = 1;
				// 	$resultReturn["Msg"] = "ไฟล์รูปที่ท่าน Upload มีความกว้างเกินที่กำหนดไว้";
				// 	return $resultReturn;
        // }

        if (file_exists($sServerDir . $OldPicture)) {
            $OldPictureWebp = FU::DirFileWebp($sServerDir, $OldPicture);
            if ($OldPictureWebp != "") {
                @unlink($sServerDir . $OldPictureWebp);
            }
            @unlink($sServerDir . $OldPicture);
        }

        if ($rename == 1) {
            $sFileName = date('ymdHis') . FU::makeRandom() . "." . $sExtension;
        }

        if (!is_dir($sServerDir)) {
            @mkdir($sServerDir, 0777, true);
        }
        $sFilePath = $sServerDir . $sFileName;
        move_uploaded_file($oFile['tmp_name'], $sFilePath);
				if (is_file($sFilePath)) {
					$oldumask = umask(0);
					chmod($sFilePath, 0777);
					umask($oldumask);
				}

        if ($width > $reduceW || $height > $reduceH) {
            $resizedFile = FU::resizeImage($sFilePath, $reduceW, $reduceH, $sExtension);
						$sFileName = pathinfo($resizedFile, PATHINFO_BASENAME);
            $resultReturn["sFileName"] = $resizedFile;
        } else {
            $resultReturn["sFileName"] = $sFileName;
        }

				if ($resultReturn["sFileName"] != "") {
						$resultReturn["sFileName"] = $sFileName;

					if (stripos($resultReturn["sFileName"], "gif") === false) {
						// $resultReturn["sWebPFile"] = FU::ConvertImgToWebP($sServerDir, $resultReturn["sFileName"]);
						$resultReturn["sWebPFile"] = "";
					}else{
						$resultReturn["sFileName"] = $sFileName;
					}
	
				} else {
					$resultReturn["sFileName"] = $sFileName;
	
					if (stripos($sFileName, "gif") === false) {
						// $resultReturn["sWebPFile"] = FU::ConvertImgToWebP($sServerDir, $resultReturn["sFileName"]);
						$resultReturn["sWebPFile"] = "";
					}else{
						$resultReturn["sFileName"] = $sFileName;
					}
				}
				if ($resultReturn["Flag"]  == 1) {
					$resultReturn["sFileName"] = $OldPicture;
				}
    }else{
		  $resultReturn["sFileName"] = $OldPicture;	
		}
    return $resultReturn;
  }

	public static function makeRandom($length = 6) {
		$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, strlen($characters) - 1)];
		}
		return $randomString;
	}
}
/////////////////////////////////////////////// End Function ///////////////////////////////////////////



/**
 * บันทึกประวัติการใช้งานระบบ (Log)
 * @param PDO $DB Database Connection
 * @param string $action ข้อความการกระทำ เช่น 'เข้าชมหน้า: ผู้ใช้งานระบบ', 'เพิ่มข้อมูล: กลุ่มผู้ใช้งาน'
 */
function addBofLog($DB, $action) {
    if(!isset($_SESSION['StaffID'])) return false;
    
    $fullUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $ip = $_SERVER['REMOTE_ADDR'];
    $staffId = $_SESSION['StaffID'];
    
    try {
        $stmt = $DB->prepare("INSERT INTO bof_logs (StaffID, LogAction, LogActionUrl, CreateIP, CreateDate) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$staffId, $action, $fullUrl, $ip]);
        return true;
    } catch(PDOException $e) {
        return false;
    }
}
?>
