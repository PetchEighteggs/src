<?php
/** ensure this file is being included by a parent file */
defined('_VALID_ACCESS') or die('Direct Access to this location is not allowed.  ms_com');

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'read_and_close' => true
    ]);
}

if (isset($_SESSION['StaffID'])) {
    $_SESSION['admin_id'] = $_SESSION['StaffID'];
}

if (sizeof($_POST) > 0) {
	$_FORM = $_POST;
} else if (sizeof($_GET) > 0) {
	$_FORM = $_GET;
} else {
	$_FORM = array("");
}
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

require_once($_Config_absolute_path . "/vendor/autoload.php");
require_once($_Config_absolute_path . "/config/PDOCache.php");
require_once($_Config_absolute_path . "/config/func.class.php");
$CACHE_DIR = $_Config_absolute_path . '/caching';
if (!file_exists($CACHE_DIR)) {
	mkdir($CACHE_DIR, 0755, true);
}

function mosConnectPDO()
{
	global $DBServer, $DBName, $DBUsername, $DBPassword;
	try {
		// Use PDOCache to get a cached connection or create a new one
		$DB = PDOCache::getInstance($DBServer, $DBName, $DBUsername, $DBPassword, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+07:00'"
		]);
		return $DB;
	} catch (PDOException $e) {
		//echo "Connection failed: " . $e->getMessage();
		error_log("Database connection failed: " . $e->getMessage());
		return null;
	}
}

function paginateWithCount($DB, $sql, $params = [], $page = 1, $limit = 20, $_SetOrder = '')
{
	// Calculate offset
	$offset = ($page - 1) * $limit;

	// Create SQL for counting total records
	$countSql = "SELECT COUNT(*) AS total FROM (" . $sql . ") AS count_query";
	//echo $countSql;

	// Prepare and execute count query
	$countStmt = $DB->prepare($countSql);

	// Bind parameters for count query
	foreach ($params as $key => $value) {
		$type = PDO::PARAM_STR;
		if (is_int($key)) {
			$countStmt->bindValue($key + 1, $value, $type);
		} else {
			$countStmt->bindValue($key, $value, $type);
		}
	}

	$countStmt->execute();
	$totalCount = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

	// Add ORDER BY if needed
	if ($_SetOrder != '') {
		$sql .= ' ORDER BY ' . $_SetOrder;
	} else if (stripos($sql, 'ORDER BY') === false) {
		$sql .= ' ORDER BY 1';  // Default order by first column
	}

	// MySQL uses LIMIT and OFFSET
	$sql .= " LIMIT :limit OFFSET :offset";

	// Prepare and execute main query
	$stmt = $DB->prepare($sql);

	// Bind parameters for main query
	foreach ($params as $key => $value) {
		$type = PDO::PARAM_STR;
		if (is_int($value)) {
			$type = PDO::PARAM_INT;
		} elseif (is_bool($value)) {
			$type = PDO::PARAM_BOOL;
		} elseif (is_null($value)) {
			$type = PDO::PARAM_NULL;
		}

		if (is_int($key)) {
			$stmt->bindValue($key + 1, $value, $type);
		} else {
			$stmt->bindValue($key, $value, $type);
		}
	}

	// Bind limit and offset
	$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
	$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

	$stmt->execute();
	$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Calculate total pages
	$totalPages = ceil($totalCount / $limit);

	return [
		'data' => $data,
		'pagination' => [
			'total' => $totalCount,
			'per_page' => $limit,
			'current_page' => $page,
			'total_pages' => $totalPages,
			'has_more' => $page < $totalPages
		]
	];
}

/**
 * Utility function to return a value from a named array or a specified default
 */
define("_MOS_NOTRIM", 0x0001);
define("_MOS_ALLOWHTML", 0x0002);

function trimArray($input)
{
	if (is_array($input)) {
		$key = array_keys($input);
		for ($i = 0; $i < sizeof($key); $i++) {
			$input[$key[$i]] =  stripslashes(trim($input[$key[$i]]));
		}
		return $input;
	} else {
		return $input;
	}
}

function mosGetParam(&$arr, $name, $def = null, $mask = 0, $filter = '')
{
	$return = null;
	if (isset($arr[$name])) {
		if (is_string($arr[$name])) {
			if (!($mask & _MOS_NOTRIM)) {
				$arr[$name] = trim($arr[$name]);
			}
			if (!($mask & _MOS_ALLOWHTML)) {
				$arr[$name] = strip_tags($arr[$name]);
			}
			// if (!get_magic_quotes_gpc()) {
			// 	$arr[$name] = addslashes($arr[$name]);
			// }
			if ($filter == 'Int') {
				$arr[$name] = preg_replace('/\D/', '', $arr[$name]);
			}
			if ($filter == 'TextOnly') {
				$arr[$name] = preg_replace('/[^ก-๙A-Za-z0-9_]/', '', $arr[$name]);
			}
			if ($filter == 'Text') {
				$arr[$name] = preg_replace('/[^!<>@&\/\sก-๙A-Za-z0-9_\"\']/', '', $arr[$name]);
			}

			$arr[$name] = str_replace('\\', '', $arr[$name]);
		}
		//return htmlspecialchars($arr[$name], ENT_QUOTES, 'utf-8');
		return $arr[$name];
	} else {
		//return htmlspecialchars($def, ENT_QUOTES, 'utf-8');
		return $def;
	}
}

function sqli_filter($string)
{
	$filtered_string = strip_tags($string);
	$filtered_string = str_replace(
		array(
			"--",
			";",
			"/*",
			"*/",
			"//",
			" ",
			"#",
			"||",
			"admin'",
			"UNION",
			"COLLATE",
			"DROP",
			"'",
			'"'
		),
		" ",
		$filtered_string
	);
	return $filtered_string;
}

function clean_input($input, $safe_level = 0)
{
	$output = $input;
	$changed = false;

	do {
		$changed = false;

		// Remove unwanted tags
		$output = strip_tags_in($input);
		if ($output !== $input) $changed = true;

		$output = strip_encoded_entities($output);
		if ($output !== $input) $changed = true;

		// Use 2nd input param if not empty or '0'
		if ($safe_level !== 0) {
			$output = strip_base64($output);
			if ($output !== $input) $changed = true;
		}

		// Update input for next loop
		$input = $output;
	} while ($changed);
	return $output;
}
/*
* Focuses on stripping encoded entities
* *** This appears to be why people use this sample code. Unclear how well Kses does this ***
*
* @param   string  $input  Content to be cleaned. It MAY be modified in output
* @return  string  $input  Modified $input string
*/
function strip_encoded_entities($input)
{
	// Fix &entity\n;
	$input = str_replace(array('&amp;', '&lt;', '&gt;'), array('&amp;amp;', '&amp;lt;', '&amp;gt;'), $input);
	$input = preg_replace('/(&#x?[0-9A-F]+);*/iu', '$1;', $input);
	$input = html_entity_decode($input, ENT_COMPAT, 'UTF-8');
	// Remove any attribute starting with "on" or xmlns
	$input = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+[>\b]?#iu', '$1>', $input);
	// Remove javascript: and vbscript: protocols
	$input = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $input);
	$input = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $input);
	return $input;
}
/*
* Focuses on stripping unencoded HTML tags & namespaces
*
* @param   string  $input  Content to be cleaned. It MAY be modified in output
* @return  string  $input  Modified $input string
*/
function strip_tags_in($input)
{
	$input = preg_replace('#<[^>]*?>#i', '', $input);
	$input = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i', '', $input);
	$input = preg_replace('#</*\w+:\w[^>]*+>#i', '', $input);
	$_DCheck = array("'", '"', ';', 'return', 'boolean', 'true', 'false');
	$input = str_replace($_DCheck, "", $input);
	return $input;
}
/*
* Focuses on stripping entities from Base64 encoded strings
*
* NOT ENABLED by default!
* To enable 2nd param of clean_input() can be set to anything other than 0 or '0':
* ie: xssClean->clean_input( $input_string, 1 )
*
* @param   string  $input      Maybe Base64 encoded string
* @return  string  $output     Modified & re-encoded $input string
*/
function strip_base64($input)
{
	$decoded = base64_decode($input);
	// $decoded = $this->strip_tags_in( $decoded );
	// $decoded = $this->strip_encoded_entities( $decoded );
	$decoded = strip_tags_in($decoded);
	$decoded = strip_encoded_entities($decoded);
	$output = base64_encode($decoded);
	return $output;
}

function mosStripslashes(&$value)
{
	if (is_array($value)) {
		array_walk_recursive($value, function (&$v) {
			$v = stripslashes($v);
		});
	} else {
		$value = stripslashes($value);
	}
	return $value;
}

#Clear Text
function clearText($data)
{
	//return htmlspecialchars
	if ($data === null || $data === '') {
		return '';
	}
	return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function mosRedirect($url, $msg = '')
{
	$redirect_url = $url;
	if (trim($msg)) {
		$redirect_url .= (strpos($url, '?') ? '&' : '?') . 'mosmsg=' . urlencode($msg);
	}
	header('Location: ' . $redirect_url);
	exit;
}
