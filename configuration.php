<?php
require_once __DIR__ . '/config/env.php';

$_Config_absolute_path = dirname(__FILE__);
$ThisHostIP = (string)($_SERVER['SERVER_ADDR'] ?? '127.0.0.1');
define('ThisHostIP', $ThisHostIP);

$configuredEnvironment = strtolower((string)envValue('DGR_APP_ENV', ''));
$isLocalEnvironment = $configuredEnvironment !== ''
	? in_array($configuredEnvironment, ['local', 'development', 'testing'], true)
	: ($ThisHostIP == '::1' || $ThisHostIP == '127.0.0.1' || strpos($ThisHostIP, '172.') === 0);

if($isLocalEnvironment){ //For Localhost
   
	$_Config_live_site = (string)envValue('DGR_APP_URL', 'http://localhost:8076');
	$_Config_root = $_Config_live_site;
	$_Config_redirect_home = (string)envValue('DGR_APP_REDIRECT_HOME', rtrim($_Config_live_site, '/') . '/index.php');
	$_Config_htaccess_path = (string)envValue('DGR_APP_HTACCESS_PATH', '/');

	$DBServer = envRequired('DGR_DB_HOST');
	$DBName = envRequired('DGR_DB_NAME');
	$DBUsername = envRequired('DGR_DB_USERNAME');
	$DBPassword = envRequired('DGR_DB_PASSWORD');

	$CK_licenseName = envRequired('DGR_CK_LICENSE_NAME');
	$CK_licenseKey = envRequired('DGR_CK_LICENSE_KEY');

} else {

	$_Config_live_site = (string)envValue('DGR_APP_URL', "https://www2.dgr.go.th");
	$_Config_root = $_Config_live_site;
	$_Config_redirect_home = (string)envValue('DGR_APP_REDIRECT_HOME', rtrim($_Config_live_site, '/') . '/index.php');
	$_Config_htaccess_path = (string)envValue('DGR_APP_HTACCESS_PATH', '/');

	$DBServer = envRequired('DGR_DB_HOST');
	$DBName = envRequired('DGR_DB_NAME');
	$DBUsername = envRequired('DGR_DB_USERNAME');
	$DBPassword = envRequired('DGR_DB_PASSWORD');

	$CK_licenseName = envRequired('DGR_CK_LICENSE_NAME');
	$CK_licenseKey = envRequired('DGR_CK_LICENSE_KEY');
}

if (!defined('Config_web_full_url')) {
	define('Config_web_full_url', $_Config_live_site);
}
if (!defined('Config_web_prefix_url')) {
	define('Config_web_prefix_url', 'www2.dgr.go.th');
}
if (!defined('Config_web_prefix_cookie_url')) {
	define('Config_web_prefix_cookie_url', '.www2.dgr.go.th');
}
if (!defined('Config_live_site')) {
	define('Config_live_site', $_Config_absolute_path);
}

$_Config_sitename ="x";
$_Config_sitename_bof ="x";
$_Config_sitename_title = "x";

//Set Parameter for Upload Image and File
#==============================================================================================
if($isLocalEnvironment){
	$sServerImgDir = $_Config_absolute_path."/uploads/images/";
	$ImgPath = $_Config_live_site."/uploads/images/";
	$sServerDirFile =  $_Config_absolute_path."/uploads/files/";
	$FilePath = $_Config_live_site."/uploads/files/";
} else {
	$sServerImgDir = "/mnt/dgr-storage/upload/images/";
	$ImgPath = $_Config_live_site."/storage/upload/images/";
	$sServerDirFile = "/mnt/dgr-storage/upload/files/";
	$FilePath = $_Config_live_site."/storage/upload/files/";
}

# ขนาดไฟล์สูงสุดของรูป
define('CONF_IMG_MAX_MB', 5); // กำหนดขนาดสูงสุดของรูปภาพ (MB)
$SetMaxSize = CONF_IMG_MAX_MB * 1024 * 1024; //byte ขนาดไฟล์ที่ให้อัพโหลดของ Image
$SetMaxSize_text = ' ขนาดไฟล์ไม่เกิน ' . CONF_IMG_MAX_MB . ' MB. ';
#Image Allowed & Denied
$SetImageAllow_text = '(อนุญาติเฉพาะไฟล์ .jpg .jpeg .gif .png .svg เท่านั้น)';
$_Config['AllowedExtensions']['Image'] = array('jpg', 'jpeg', 'gif', 'png', 'svg') ;
$_Config['DeniedExtensions']['Image'] = array('zip', 'pdf', 'php','php3','php5','phtml','asp','aspx','ascx','jsp','cfm','cfc','pl','bat','exe','dll','reg','cgi') ;
#==============================================================================================

//Set Parameter for Upload File
#==============================================================================================
// $sServerDirFile and $FilePath are now configured in the environment block above.

define('CONF_FILE_MAX_MB', 10); // กำหนดขนาดสูงสุดของไฟล์เอกสาร (MB) ตามที่คุณต้องการ
$SetFileSize = CONF_FILE_MAX_MB * 1024 * 1024;  //byte ขนาดไฟล์ที่ให้อัพโหลดของ File
$SetFileSize_text = ' ขนาดไฟล์ไม่เกิน ' . CONF_FILE_MAX_MB . ' MB. ';
$SetFileAllow_text = '(อนุญาติเฉพาะไฟล์ .zip, .pdf, .rar, .doc, .docx, .xls, .xlsx เท่านั้น)';
$SetFileSizePDF_text = ' ขนาดไฟล์ไม่เกิน ' . CONF_FILE_MAX_MB . ' MB. ';
$SetFileAllowPDF_text = '(อนุญาติเฉพาะไฟล์ .pdf เท่านั้น)';
$SetFileSizeMember_text = ' ขนาดไฟล์ไม่เกิน 5 MB. ';
$SetFileAllowMember_text = '(อนุญาติเฉพาะไฟล์ .pdf .jpg .jpeg .gif .png เท่านั้น)';
$SetFileSizeExcel_text = ' ขนาดไฟล์ไม่เกิน 5 MB. ';
$SetFileAllowExcel_text = '(อนุญาติเฉพาะไฟล์ .xls .xlsx เท่านั้น)';
$SetFileSizeVDO_text = ' ขนาดไฟล์ไม่เกิน 50 MB. ';
$SetFileAllowVDO_text = '(อนุญาติเฉพาะไฟล์ .mp4 เท่านั้น)';

#File Allowed & Denied
$_Config['AllowedExtensions']['File'] = array('zip', 'pdf','rar', 'doc', 'docx', 'xls', 'xlsx') ;
$_Config['DeniedExtensions']['File'] = array('php','php3','php5','phtml','asp','aspx','ascx','jsp','cfm','cfc','pl','bat','exe','dll','reg','cgi', 'mp3', 'wma');
$_Config['AllowedExtensions']['FilePDF'] = array('pdf', 'mp4', 'mov', 'webm', 'avi') ;
$_Config['DeniedExtensions']['FilePDF'] = array('php','php3','php5','phtml','asp','aspx','ascx','jsp','cfm','cfc','pl','bat','exe','dll','reg','cgi', 'mp3', 'wma', 'zip', 'rar');
$_Config['AllowedExtensions']['FileMember'] = array('pdf', 'jpg', 'jpeg', 'gif', 'png') ;
$_Config['DeniedExtensions']['FileMember'] = array('php','php3','php5','phtml','asp','aspx','ascx','jsp','cfm','cfc','pl','bat','exe','dll','reg','cgi', 'mp3', 'wma', 'zip', 'rar');
$_Config['AllowedExtensions']['FileExcel'] = array('xls', 'xlsx' );
$_Config['DeniedExtensions']['FileExcel'] = array('php','php3','php5','phtml','asp','aspx','ascx','jsp','cfm','cfc','pl','bat','exe','dll','reg','cgi', 'mp3', 'wma', 'zip', 'rar');
$_Config['AllowedExtensions']['FileVDO'] = array('mp4');
$_Config['DeniedExtensions']['FileVDO'] = array('php','php3','php5','phtml','asp','aspx','ascx','jsp','cfm','cfc','pl','bat','exe','dll','reg','cgi', 'mp3', 'wma', 'zip', 'rar', 'xls', 'xlsx');
#==============================================================================================

$errCode='';
$errText='';
date_default_timezone_set('Asia/Bangkok');
$ThisToday = date("Y-m-d");
$ThisDateTime = date("Y-m-d H:i:s");
$ThisTodayST = strtotime($ThisToday);
$ThisDateTimeST = strtotime($ThisDateTime);
$StaffTokenCode = md5($_Config_sitename.microtime());
$CooKieExpire = time() + 3600 * 24 * 365;
$_LoginFailNumber = 5;

$Rip = $_SERVER['REMOTE_ADDR'] ?? '';
if(!isset($_SESSION['SESS_ID']) && empty($_SESSION['SESS_ID'])){
	$_SESSION['SESS_ID'] = session_id();
}

$requestScheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$requestHost = (string)($_SERVER['HTTP_HOST'] ?? parse_url($_Config_live_site, PHP_URL_HOST) ?? '');
$requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
$ThisFullURL = $requestScheme . '://' . $requestHost . $requestUri;
$thaiMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
define('Config_Salt', envRequired('DGR_CONFIG_SALT'));
function salt_pass($pass) {
	return envRequired('DGR_LEGACY_PASSWORD_PREFIX') . $pass;
}

$LangSet = 2;
$Lang1 = "ภาษาไทย";
$Lang2 = "ภาษาอังกฤษ";

$_EmailSender_name = "Noreply by กรมทรัพยากรน้ำบาดาล";	//Define @ [configuration.php]
$_EmailSender_from = "noreply@eighteggs.work";		//Define @ [configuration.php]

# Img Set -----------------------------------------------------------------------
$SetMaxWidth_Large_All='3000';

# Upload & Image Configurations (Centralized)
define('CONF_UPLOAD_MAX_MB', 2); // ขนาดไฟล์สูงสุดที่อนุญาต (MB)
define('CONF_IMG_MAX_DIM', 2000); // ขนาดภาพสูงสุดก่อนเริ่มกระบวนการย่อ (px)
define('CONF_IMG_REC_W', 800); // ความกว้างภาพที่แนะนำ/ที่ถูกย่อ (px)
define('CONF_IMG_REC_H', 600); // ความสูงภาพที่แนะนำ/ที่ถูกย่อ (px)
?>
