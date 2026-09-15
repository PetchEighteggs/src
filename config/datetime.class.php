<?php
//==================================
// TT&T CLASS
//==================================
class DT
{

	var $dToday;
	var $sYear;
	var $sMon;
	var $sMday;

	function stringVal($value)
	{
		if (is_object($value) && in_array("__toString", get_class_methods($value)))
			return strval($value->__toString());
		else
			return strval($value);
	}

	//Constructor
	public function __construct()
	{
		$dToday = null;
		$sYear = null;
		$sMon = null;
		$sMday = null;
	}

	public static function TimeCut($time)
	{
		$arrTime = explode(":", $time);
		$hh = $arrTime[0];
		$mm = $arrTime[1];
		return "$hh:$mm";
	}

	public static function TimeCutPoint($Tdate)
	{

		$arrDatetime = explode(" ", $Tdate);			//DateTime yyyy-mm-dd  2545-12-20 13:31:00.000
		$arrDate = explode("-", $arrDatetime[0]);		//Date
		$arrTime = explode(":", $arrDatetime[1]);		//Time

		//$hh = date('H', mktime($arrTime[0], $arrTime[1], 0, $arrDate[1], $arrDate[2], $arrDate[0]));
		$hh = date('G', mktime($arrTime[0], $arrTime[1], 0, $arrDate[1], $arrDate[2], $arrDate[0]));
		$mm = date('i', mktime($arrTime[0], $arrTime[1], 0, $arrDate[1], $arrDate[2], $arrDate[0]));
		$ss = date('s', mktime($arrTime[0], $arrTime[1], 0, $arrDate[1], $arrDate[2], $arrDate[0]));

		return "$hh:$mm:$ss";
		//return "$hh.$mm";
	}

	public static function TimeCuthh($Tdate)
	{

		$arrDatetime = explode(" ", $Tdate);			//DateTime yyyy-mm-dd  2545-12-20 13:31:00.000
		$arrDate = explode("-", $arrDatetime[0]);		//Date
		$arrTime = explode(":", $arrDatetime[1]);		//Time

		$hh = date('G', mktime($arrTime[0], $arrTime[1], 0, $arrDate[1], $arrDate[2], $arrDate[0]));

		if (strlen($hh) == 1) {
			$hh = "0" . DT::stringVal($hh);
		} else {
			$hh = DT::stringVal($hh);
		}

		return "$hh";
	}

	public static function TimeCutmm($Tdate)
	{

		$arrDatetime = explode(" ", $Tdate);			//DateTime yyyy-mm-dd  2545-12-20 13:31:00.000
		$arrDate = explode("-", $arrDatetime[0]);		//Date
		$arrTime = explode(":", $arrDatetime[1]);		//Time

		$mm = date('i', mktime($arrTime[0], $arrTime[1], 0, $arrDate[1], $arrDate[2], $arrDate[0]));

		if (strlen($mm) == 1) {
			$mm = "0" . DT::stringVal($mm);
		} else {
			$mm = DT::stringVal($mm);
		}

		return "$mm";
	}

	public static function TimeCutAMPM($time)
	{
		return date('H:i A', strtotime($time));
	}

	public static function diff_days($start_date, $end_date)
	{
		return floor(abs(strtotime($start_date) - strtotime($end_date)) / 86400);
	}

	//Check if start date is earlier than end date
	public static function BeforeEdit2($startdate, $enddate)
	{
		//split into an array (year,month,day,hour,minute,second)
		$arrDatetime = explode(" ", $startdate);				//DateTime yyyy-mm-dd  2545-12-20 13:31:00.000
		$arrDate = explode("-", $arrDatetime[0]);			//Date
		$arrTime = explode(":", $arrDatetime[1]);		//Time


		//convert to datetime
		$start = mktime($arrDate[1], $arrDate[2], $arrDate[3], $arrTime[1], $arrTime[2], $arrTime[0]);

		//split into an array (year,month,day,hour,minute,second)
		$arrDatetime = explode(" ", $enddate);				//DateTime yyyy-mm-dd  2545-12-20 13:31:00.000
		$arrDate = explode("-", $arrDatetime[0]);			//Date
		$arrTime = explode(":", $arrDatetime[1]);		//Time

		$end = mktime($arrDate[1], $arrDate[2], $arrDate[3], $arrTime[1], $arrTime[2], $arrTime[0]);

		return ($end < $start) ? false : true;
	}

	public static function BeforeEdit($startdate, $enddate)
	{
		//echo (strtotime($startdate) - strtotime($enddate));

		//if((strtotime($startdate) - strtotime($enddate)) == 0){
		//echo "Same";
		//}else
		if ((strtotime($startdate) - strtotime($enddate)) <= 0) {
			//echo "startdate small";
			return true;
		} else {
			//echo "OK";
			return false;
		}
	}

	public static function CutDate($strdate)
	{
		$arrDatetime = explode(" ", $strdate);
		return $arrDatetime[0];
	}

	public static function DateTimetkp($strdate)
	{
		$arrDatetime = explode(" ", $strdate);				//DateTime yyyy-mm-dd  2545-12-20 13:31:00.000
		$arrDate = explode("-", $arrDatetime[0]);			//Date
		$arrTime = explode(":", $arrDatetime[1]);		//Time

		//mktime(hh, mm, ss, month day year)
		//Date

		$day = date('j', mktime(0, 0, 0, $arrDate[1], $arrDate[2], $arrDate[0]));
		$month = date('m', mktime(0, 0, 0, $arrDate[1], $arrDate[2], $arrDate[0]));
		$year = date('Y', mktime(0, 0, 0, $arrDate[1], $arrDate[2], $arrDate[0]));	//$year += 543;

		$date = $day . " / " . $month . " / " . ($year + 543);
		return $date;
	}

	// //รับค่าของปีเข้ามาเป็น พ.ศ.
	// public static function DateTimeShortFormat($strdate, $type = 0, $time = 0, $lan = "En")
	// {
	// 	$dt = new DateTime($strdate);
	// 	$monthNameTh = array("01" => "ม.ค.", "02" => "ก.พ.", "03" => "มี.ค", "04" => "เม.ย", "05" => "พ.ค.", "06" => "มิ.ย",  "07" => "ก.ค.", "08" => "ส.ค.", "09" => "ก.ย.", "10" => "ต.ค.", "11" => "พ.ย.", "12" => "ธ.ค.");
	// 	$monthNameThFull = array("01" => "มกราคม", "02" => "กุมภาพันธ์", "03" => "มีนาคม", "04" => "เมษายน", "05" => "พฤษภาคม", "06" => "มิถุนายน",  "07" => "กรกฎาคม", "08" => "สิงหาคม", "09" => "กันยายน", "10" => "ตุลาคม", "11" => "พฤศจิกายน", "12" => "ธันวาคม");
	// 	$monthNameEn = array("01" => "Jan", "02" => "Feb", "03" => "Mar", "04" => "Apr", "05" => "May", "06" => "Jun",  "07" => "Jul", "08" => "Aug.", "09" => "Sep", "10" => "Oct", "11" => "Nov", "12" => "Dec");
	// 	$monthNameEnFull = array("01" => "January", "02" => "February", "03" => "March", "04" => "April", "05" => "May", "06" => "June",  "07" => "July", "08" => "August", "09" => "September", "10" => "October", "11" => "November", "12" => "December");

	// 	$day = $dt->format('d');
	// 	$month = $dt->format('m');
	// 	$year = $dt->format('Y');

	// 	//String Date & String Time
	// 	if ($type == 0) {
	// 		if ($lan == "Th")
	// 			$strDate = "$day $monthNameTh[$month] " . ($year + 543);
	// 		elseif ($lan == "En")
	// 			$strDate = "$day $monthNameEn[$month] $year";
	// 	} elseif ($type == 1) {
	// 		if ($lan == "Th")
	// 			$strDate = "$day $monthNameThFull[$month] " . ($year + 543);
	// 		elseif ($lan == "En")
	// 			$strDate = "$day $monthNameEnFull[$month] $year";
	// 	}

	// 	$strTime = $dt->format('H:i');

	// 	if ($time == 0)
	// 		return "$strDate $strTime";
	// 	elseif ($time == 1)
	// 		return "$strDate";
	// }

	public static function DateTimeShortFormat($strdate, $type = 0, $time = 0, $lan = "En")
	{ //type =0 ย่อ 1 เต็ม, time 0 มีเวลา ---1 ไม่มี
		$monthNameTh = array("01" => "ม.ค.", "02" => "ก.พ.", "03" => "มี.ค", "04" => "เม.ย", "05" => "พ.ค.", "06" => "มิ.ย",  "07" => "ก.ค.", "08" => "ส.ค.", "09" => "ก.ย.", "10" => "ต.ค.", "11" => "พ.ย.", "12" => "ธ.ค.");
		$monthNameThFull = array("01" => "มกราคม", "02" => "กุมภาพันธ์", "03" => "มีนาคม", "04" => "เมษายน", "05" => "พฤษภาคม", "06" => "มิถุนายน",  "07" => "กรกฎาคม", "08" => "สิงหาคม", "09" => "กันยายน", "10" => "ตุลาคม", "11" => "พฤศจิกายน", "12" => "ธันวาคม");

		$monthNameEn = array("01" => "Jan", "02" => "Feb", "03" => "Mar", "04" => "Apr", "05" => "May", "06" => "Jun",  "07" => "Jul", "08" => "Aug.", "09" => "Sep", "10" => "Oct", "11" => "Nov", "12" => "Dec");
		$monthNameEnFull = array("01" => "January", "02" => "February", "03" => "March", "04" => "April", "05" => "May", "06" => "June",  "07" => "July", "08" => "August", "09" => "September", "10" => "October", "11" => "November", "12" => "December");

		$arrDatetime = explode(" ", $strdate);			//DateTime yyyy-mm-dd  2545-12-20 13:31:00.000
		$arrDate = explode("-", $arrDatetime[0]);		//Date
		$arrTime = explode(":", $arrDatetime[1]);		//Time

		//mktime(hh, mm, ss, month day year)
		$day = $arrDate[2];
		$month = $arrDate[1];
		$year = $arrDate[0];	//$year += 543;

		//Time
		$hh = $arrTime[0];
		$mm = $arrTime[1];
		$ampm = 00;

		//String Date & String Time
		if ($type == 0) {
			if ($lan == "Th")
				$strDate = $day . " " . $monthNameTh[$month]." พ.ศ.".$year += 543;
			elseif ($lan == "En")
				$strDate = $day . " " . $monthNameEn[$month]." ".$year;
		} elseif ($type == 1) {
			if ($lan == "Th")
				$strDate = $day . " " . $monthNameThFull[$month]." พ.ศ.".$year += 543;
			elseif ($lan == "En")
				$strDate = $day . " " . $monthNameEnFull[$month]." ".$year;
		}

		$strTime = "$hh:$mm";

		if ($time == 0)
			return "$strDate $strTime";
		elseif ($time == 1)
			return "$strDate";
	}

	public static function DateTimeShortFormatV2($strdate, $type = 0, $time = 0, $lan = "En")
	{ //type =0 ย่อ 1 เต็ม, time 0 มีเวลา ---1 ไม่มี
		$monthNameTh = array("01" => "ม.ค.", "02" => "ก.พ.", "03" => "มี.ค", "04" => "เม.ย", "05" => "พ.ค.", "06" => "มิ.ย",  "07" => "ก.ค.", "08" => "ส.ค.", "09" => "ก.ย.", "10" => "ต.ค.", "11" => "พ.ย.", "12" => "ธ.ค.");
		$monthNameThFull = array("01" => "มกราคม", "02" => "กุมภาพันธ์", "03" => "มีนาคม", "04" => "เมษายน", "05" => "พฤษภาคม", "06" => "มิถุนายน",  "07" => "กรกฎาคม", "08" => "สิงหาคม", "09" => "กันยายน", "10" => "ตุลาคม", "11" => "พฤศจิกายน", "12" => "ธันวาคม");

		$monthNameEn = array("01" => "Jan", "02" => "Feb", "03" => "Mar", "04" => "Apr", "05" => "May", "06" => "Jun",  "07" => "Jul", "08" => "Aug.", "09" => "Sep", "10" => "Oct", "11" => "Nov", "12" => "Dec");
		$monthNameEnFull = array("01" => "January", "02" => "February", "03" => "March", "04" => "April", "05" => "May", "06" => "June",  "07" => "July", "08" => "August", "09" => "September", "10" => "October", "11" => "November", "12" => "December");

		$arrDatetime = explode(" ", $strdate);			//DateTime yyyy-mm-dd  2545-12-20 13:31:00.000
		$arrDate = explode("-", $arrDatetime[0]);		//Date
		$arrTime = explode(":", $arrDatetime[1]);		//Time

		//mktime(hh, mm, ss, month day year)
		$day = $arrDate[2];
		$month = $arrDate[1];
		$year = $arrDate[0];	//$year += 543;

		//Time
		$hh = $arrTime[0];
		$mm = $arrTime[1];
		$ampm = 0;

		//String Date & String Time
		if ($type == 0) {
			if ($lan == "Th")
				$strDate = "$day $monthNameTh[$month]  " . $year += 543;
			elseif ($lan == "En")
				$strDate = "$day $monthNameEn[$month] $year";
		} elseif ($type == 1) {
			if ($lan == "Th")
				$strDate = "$day $monthNameThFull[$month]  " . $year += 543;
			elseif ($lan == "En")
				$strDate = "$day $monthNameEnFull[$month] $year";
		}

		$strTime = "$hh:$mm";

		if ($time == 0)
			return "$strDate $strTime";
		elseif ($time == 1)
			return "$strDate";
	}

	public static function DateTimeFullFormat($strdate, $type = 0, $time = 0, $lan = "En")
	{ //type =0 ย่อ 1 เต็ม, time 0 มีเวลา ---1 ไม่มี
		$dt = new DateTime($strdate);

		$monthNameTh = array("01" => "ม.ค.", "02" => "ก.พ.", "03" => "มี.ค", "04" => "เม.ย", "05" => "พ.ค.", "06" => "มิ.ย",  "07" => "ก.ค.", "08" => "ส.ค.", "09" => "ก.ย.", "10" => "ต.ค.", "11" => "พ.ย.", "12" => "ธ.ค.");
		$monthNameThFull = array("01" => "มกราคม", "02" => "กุมภาพันธ์", "03" => "มีนาคม", "04" => "เมษายน", "05" => "พฤษภาคม", "06" => "มิถุนายน",  "07" => "กรกฎาคม", "08" => "สิงหาคม", "09" => "กันยายน", "10" => "ตุลาคม", "11" => "พฤศจิกายน", "12" => "ธันวาคม");

		$monthNameEn = array("01" => "Jan", "02" => "Feb", "03" => "Mar", "04" => "Apr", "05" => "May", "06" => "Jun",  "07" => "Jul", "08" => "Aug.", "09" => "Sep", "10" => "Oct", "11" => "Nov", "12" => "Dec");
		$monthNameEnFull = array("01" => "January", "02" => "February", "03" => "March", "04" => "April", "05" => "May", "06" => "June",  "07" => "July", "08" => "August", "09" => "September", "10" => "October", "11" => "November", "12" => "December");

		$date = $dt->format('D');
		$day = $dt->format('j');
		$month = $dt->format('m');
		$year = $dt->format('Y');

		//String Date & String Time
		if ($type == 0) {
			if ($lan == "Th")
				$strDate = "วัน" . DT::getDay($date) . "ที่ $day $monthNameTh[$month] พ.ศ. " . $year += 543;
			elseif ($lan == "En")
				$strDate = "$monthNameEn[$month] $day, $year";
		} elseif ($type == 1) {
			if ($lan == "Th")
				$strDate = "วัน" . DT::getDay($date) . "ที่ $day $monthNameThFull[$month] พ.ศ. " . $year += 543;
			elseif ($lan == "En")
				$strDate = "$monthNameEnFull[$month] $day, $year";
		}

		$strTime = $dt->format('H') . ":" . $dt->format('i');

		if ($time == 0)
			return "$strDate $strTime";
		elseif ($time == 1)
			return "$strDate";
	}

	public static function DateTimeFullFormatTimeLine($strdate, $type = 0, $time = 0, $lan = "En")
	{ //type =0 ย่อ 1 เต็ม, time 0 มีเวลา ---1 ไม่มี

		$arrDatetime = explode(" ", $strdate);
		$arrDate = explode("-", $arrDatetime[0]);		//Date

		$dt = new DateTime($strdate);
		$day = $dt->format('d');
		$month = $dt->format('m');
		$year = $dt->format('Y');

		$hh = $dt->format('H');
		$mm = $dt->format('i');

		if ($lan == "Th") {
			if ($type == 1) {
				$year += 543;
			} else {
				$year = substr($year += 543, -2);
			}
			$strDate = $day . "/" . $month . "/" . $year;
		} elseif ($lan == "En") {
			if ($type == 0) {
				$year = substr($year, -2);
			}
			$strDate = $day . "/" . $month . "/" . $year;
		}

		$strTime = "$hh:$mm";

		if ($time == 0) {
			return $strDate . " " . $strTime;
		} elseif ($time == 1) {
			return $strDate;
		}
	}

	public static function DateTimeFullFormatTimeLineFull($strdate, $lan = "En", $time = 0)
	{ //type =0 ย่อ 1 เต็ม, time 0 มีเวลา ---1 ไม่มี

		$arrDatetime = explode(" ", $strdate);
		$arrDate = explode("-", $arrDatetime[0]);		//Date
		if ($arrDatetime[1] == '') {
			$arrDatetime[1] = "00:00:00";
		}
		$arrTime = explode(":", $arrDatetime[1]);		//Time

		// //Date
		// $day = date('d', mktime(0, 0, 0, $arrDate[1], $arrDate[2], $arrDate[0]));
		// $month = date('m', mktime(0, 0, 0, $arrDate[1], $arrDate[2], $arrDate[0]));
		// $year = date('Y', mktime(0, 0, 0, $arrDate[1], $arrDate[2], $arrDate[0]));	//$year += 543;

		// //Time
		// $hh = date('H', mktime($arrTime[0], $arrTime[1], $arrTime[2], $arrDate[1], $arrDate[2], $arrDate[0]));
		// $mm = date('i', mktime($arrTime[0], $arrTime[1], $arrTime[2], $arrDate[1], $arrDate[2], $arrDate[0]));
		// $ss = date('s', mktime($arrTime[0], $arrTime[1], $arrTime[2], $arrDate[1], $arrDate[2], $arrDate[0]));

		//Date
		$day = date('d', strtotime($strdate));
		$month = date('m', strtotime($strdate));
		$year = date('Y', strtotime($strdate));	//$year += 543;

		//Time
		$hh = date('H', strtotime($strdate));
		$mm = date('i', strtotime($strdate));
		$ss = date('s', strtotime($strdate));

		if ($lan == "Th") {
			$year = substr($year += 543, -2);
			$strDate = $day . "/" . $month . "/" . $year;
		} elseif ($lan == "En") {
			$year = substr($year, -2);
			$strDate = $day . "/" . $month . "/" . $year;
		}

		if ($time == 0 ) {
			$strTime = " ".$hh.":".$mm;
			if ($lan == "Th") {
				$strTime .= " น.";
			}
		} else {
			$strTime = '';
		}


		return $strDate . $strTime;
	}

	public static function DateTimeFullFormatDot($strdate, $type = 0, $time = 0, $lan = "En")
	{ //type =0 ย่อ 1 เต็ม, time 0 มีเวลา ---1 ไม่มี

		$arrDatetime = explode(" ", $strdate);
		$arrDate = explode("-", $arrDatetime[0]);		//Date
		$arrTime = explode(":", $arrDatetime[1]);		//Time

		// //Date
		// $day = date('d', mktime(0, 0, 0, $arrDate[1], $arrDate[2], $arrDate[0]));
		// $month = date('m', mktime(0, 0, 0, $arrDate[1], $arrDate[2], $arrDate[0]));
		// $year = date('Y', mktime(0, 0, 0, $arrDate[1], $arrDate[2], $arrDate[0]));	//$year += 543;

		// //Time
		// $hh = date('H', mktime($arrTime[0], $arrTime[1], 0, $arrDate[1], $arrDate[2], $arrDate[0]));
		// $mm = date('i', mktime($arrTime[0], $arrTime[1], 0, $arrDate[1], $arrDate[2], $arrDate[0]));

		//Date
		$day = date('d', strtotime($strdate));
		$month = date('m', strtotime($strdate));
		$year = date('Y', strtotime($strdate));	//$year += 543;

		//Time
		$hh = date('H', strtotime($strdate));
		$mm = date('i', strtotime($strdate));

		if ($lan == "Th") {
			if ($type == 1) {
				$year += 543;
			} else {
				$year = substr($year += 543, -2);
			}
			$strDate = $day . "." . $month . "." . $year;
		} elseif ($lan == "En") {
			if ($type == 0) {
				$year = substr($year, -2);
			}
			$strDate = $day . "." . $month . "." . $year;
		}

		$strTime = "$hh:$mm";

		if ($time == 0) {
			return $strDate . " " . $strTime;
		} elseif ($time == 1) {
			return $strDate;
		}
	}

	public static function monthName($monthnum, $type = 0, $lan = "En")
	{ //0 ย่อ  1 เต็ม
		$monthNameThShort = array("01" => "ม.ค.", "02" => "ก.พ.", "03" => "มี.ค", "04" => "เม.ย", "05" => "พ.ค.", "06" => "มิ.ย",  "07" => "ก.ค.", "08" => "ส.ค.", "09" => "ก.ย.", "10" => "ต.ค.", "11" => "พ.ย.", "12" => "ธ.ค.");
		$monthNameThFull = array("01" => "มกราคม", "02" => "กุมภาพันธ์", "03" => "มีนาคม", "04" => "เมษายน", "05" => "พฤษภาคม", "06" => "มิถุนายน",  "07" => "กรกฎาคม", "08" => "สิงหาคม", "09" => "กันยายน", "10" => "ตุลาคม", "11" => "พฤศจิกายน", "12" => "ธันวาคม");

		$monthNameEnShort = array("01" => "Jan", "02" => "Feb", "03" => "Mar", "04" => "Apr", "05" => "May", "06" => "Jun",  "07" => "Jul", "08" => "Aug.", "09" => "Sep", "10" => "Oct", "11" => "Nov", "12" => "Dec");
		$monthNameEnFull = array("01" => "January", "02" => "February", "03" => "March", "04" => "April", "05" => "May", "06" => "June",  "07" => "July", "08" => "August", "09" => "September", "10" => "October", "11" => "November", "12" => "December");

		if ($type == 0) {
			if ($lan == "Th")
				return $monthNameThShort[$monthnum];
			elseif ($lan == "En")
				return $monthNameEnShort[$monthnum];
		} elseif ($type == 1) {
			if ($lan == "Th")
				return $monthNameThFull[$monthnum];
			elseif ($lan == "En")
				return $monthNameEnFull[$monthnum];
		}
	}

	public static function getDay($dateEn)
	{
		if ($dateEn == "Mon") {
			return "จันทร์";
		} elseif ($dateEn == "Tue") {
			return "อังคาร";
		} elseif ($dateEn == "Wed") {
			return "พุธ";
		} elseif ($dateEn == "Thu") {
			return "พฤหัสบดี";
		} elseif ($dateEn == "Fri") {
			return "ศุกร์";
		} elseif ($dateEn == "Sat") {
			return "เสาร์";
		} elseif ($dateEn == "Sun") {
			return "อาทิตย์";
		}
	}

	//ใช้ในการสร้าง option ที่เป็นการรับเดือนเข้าสู่ระบบ โดยที่ $sel จะเป็นการชี้ไปที่เดือนที่เราได้เลือกไว้
	public static function SelectMonth($sel = 0, $lan = 'Th')
	{
		$strOption = "";
		for ($i = 1; $i <= 12; $i++) {
			$selected = ($sel == $i) ? " selected" : "";

			$strOption .= "<option value=\"$i\"$selected>" . DT::monthName($i, 1, $lan) . "</option>\r\n";
		}
		return $strOption;
	}

	public static function date2db($date, $delimiter = "-")
	{
		$d = array();
		$d['year'] = substr($date, 0, 4);
		$d['month'] = substr($date, 4, 2);
		$d['day'] = substr($date, 6, 2);

		return $d['year'] . $delimiter . $d['month'] . $delimiter . $d['day'];  //yyyy-mm-dd
	}

	public static function currentDateTime()
	{
		return date('Y-m-d H:i:s', mktime(date('H'), date('i'), date('s'), date('m'), date('d'), date('Y')));
	}

	#Check date between start and end date
	public static function checkDateBetween($today, $startdate, $enddate)
	{
		return (strtotime($today) >= strtotime($startdate) && strtotime($today) <= strtotime($enddate)) ? true : false;
	}

	public static function isDate($date)
	{
		if ($date == "" || $date == "0000-00-00" || $date == "0000-00-00 00:00:00" || $date === null) {
			//return 'n';
			return false;
		} else {
			if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $date)) {
				//return 'y';
				return true;
			} else {
				//return 'n';
				return false;
			}
			//return true;
		}
	}

	public static function isValidDateTime($date) {
    $formats = ['Y-m-d', 'Y-m-d H:i:s'];
    foreach ($formats as $format) {
      $d = DateTime::createFromFormat($format, $date);
      if ($d && $d->format($format) === $date) {
        return true;
      }
    }
    return false;
	}

	public static function getDatesFromRange($startDate, $endDate)
	{
		$return = array($startDate);
		$start = $startDate;
		$i = 1;
		if (strtotime($startDate) < strtotime($endDate)) {
			while (strtotime($start) < strtotime($endDate)) {
				$start = date('Y-m-d', strtotime($startDate . '+' . $i . ' days'));
				$return[] = $start;
				$i++;
			}
		}
		return $return;
	}

	public static function get_months($startstring, $endstring)
	{
		$start = $month = strtotime($startstring);
		$end = strtotime($endstring);
		$months = array();
		$i = 0;
		while ($month <= $end) {
			$months[$i] = date('Y', $month) . "-" . date('m', $month); //date('m', $month);
			$month = strtotime("+1 month", $month);
			$i++;
		}

		return $months;
	}

	public static function rangeMonth($datestr)
	{
		date_default_timezone_set(date_default_timezone_get());
		$dt = strtotime($datestr);
		$res['start'] = date('Y-m-d', strtotime('first day of this month', $dt));
		$res['end'] = date('Y-m-d', strtotime('last day of this month', $dt));
		return $res;
	}

	public static function rangeWeek($datestr)
	{
		date_default_timezone_set(date_default_timezone_get());
		$dt = strtotime($datestr);
		$res['start'] = date('N', $dt) == 1 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('last monday', $dt));
		$res['end'] = date('N', $dt) == 7 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('next sunday', $dt));
		return $res;
	}

	#ถ้าเก็บเวลาในรูปแบบ  datetime (ตัวอย่าง 2011-03-24 15:30:50)
	#$date_you="2011-03-24 15:30:50";
	#echo fb_date(strtotime($date_you));
	#สร้างรูปแบบ วันที่ คล้าย วันที่ใน facebook comment
	public static function fb_date($timestamp)
	{
		$difference = time() - $timestamp;
		$periods = array("second", "minute", "hour");
		$ending = " ago";
		if ($difference < 60) {
			$j = 0;
			$periods[$j] .= ($difference != 1) ? "s" : "";
			$difference = ($difference == 3 || $difference == 4) ? "a few " : $difference;
			$text = "$difference $periods[$j] $ending";
		} elseif ($difference < 3600) {
			$j = 1;
			$difference = round($difference / 60);
			$periods[$j] .= ($difference != 1) ? "s" : "";
			$difference = ($difference == 3 || $difference == 4) ? "a few " : $difference;
			$text = "$difference $periods[$j] $ending";
		} elseif ($difference < 86400) {
			$j = 2;
			$difference = round($difference / 3600);
			$periods[$j] .= ($difference != 1) ? "s" : "";
			$difference = ($difference != 1) ? $difference : "about an ";
			$text = "$difference $periods[$j] $ending";
		} elseif ($difference < 172800) {
			$difference = round($difference / 86400);
			$periods[$j] .= ($difference != 1) ? "s" : "";
			$text = "Yesterday at " . date("g:ia", $timestamp);
		} else {
			if ($timestamp < strtotime(date("Y-01-01 00:00:00"))) {
				$text = date("l j, Y", $timestamp) . " at " . date("g:ia", $timestamp);
			} else {
				$text = date("l j", $timestamp) . " at " . date("g:ia", $timestamp);
			}
		}
		return $text;
	}

	public static function getWeekRange(&$start_date, &$end_date, $offset = 0)
	{
		$start_date = '';
		$end_date = '';
		$week = date('W');


		$week = $week + $offset;

		$date = date('Y-m-d');

		$i = 0;

		while (date('W', strtotime("-$i day")) >= $week) {
			$start_date = date('Y-m-d, ', strtotime("-$i day"));
			$i++;
		}

		list($yr, $mo, $da) = explode('-', $start_date);
		$end_date = date('Y-m-d', mktime(0, 0, 0, $mo, $da + 6, $yr));
		//$start_date = date('l M d, Y, ', strtotime("$start_date -1 day"));
		//$end_date = date('l M d, Y, ', strtotime("$end_date -1 day"));

		$start_date = date('Y-m-d', strtotime("$start_date -1 day"));
		$end_date = date('Y-m-d', strtotime("$end_date -1 day"));

		$start_date = date('Y-m-d', strtotime("$start_date +7 day"));
		$end_date = date('Y-m-d', strtotime("$end_date +7 day"));
	}

	public static function getNextWeekRange(&$start_date, &$end_date, $UseStart, $UseEnd)
	{
		$start_date = date('Y-m-d', strtotime("$UseStart +1 week"));
		$end_date = date('Y-m-d ', strtotime("$UseEnd +1 week"));
	}
	public static function getLastWeekRange(&$start_date, &$end_date, $UseStart, $UseEnd)
	{
		$start_date = date('Y-m-d', strtotime("$UseStart -1 week"));
		$end_date = date('Y-m-d ', strtotime("$UseEnd -1 week"));
	}

	public static function getNextMonthRange(&$start_date, $UseStart)
	{
		$start_date = date('Y-m-d', strtotime("$UseStart +1 month"));
	}
	public static function getLastMonthRange(&$start_date, $UseStart)
	{
		$start_date = date('Y-m-d', strtotime("$UseStart -1 month"));
	}

	public static function getNextYearRange(&$start_date, $UseStart)
	{
		$start_date = date('Y-m-d', strtotime("$UseStart +1 year"));
	}
	public static function getLastYearRange(&$start_date, $UseStart)
	{
		$start_date = date('Y-m-d', strtotime("$UseStart -1 year"));
	}

	/**
	 * @param $start_date
	 * @param $end_date
	 * @return mixed
	 */
	// public static function DateBetweenDisplay($start_date, $end_date, $Lang = 'Th')
	// {
	// 	$arrDate_start = explode("-", $start_date); //Date
	// 	$start_dd = date('j', mktime(0, 0, 0, $arrDate_start[1], $arrDate_start[2], $arrDate_start[0]));
	// 	$start_mm = date('m', mktime(0, 0, 0, $arrDate_start[1], $arrDate_start[2], $arrDate_start[0]));
	// 	$start_yy = date('Y', mktime(0, 0, 0, $arrDate_start[1], $arrDate_start[2], $arrDate_start[0])); //$year += 543;

	// 	$arrDate = explode("-", $end_date); //Date
	// 	$end_dd = date('j', mktime(0, 0, 0, $arrDate[1], $arrDate[2], $arrDate[0]));
	// 	$end_mm = date('m', mktime(0, 0, 0, $arrDate[1], $arrDate[2], $arrDate[0]));
	// 	$end_yy = date('Y', mktime(0, 0, 0, $arrDate[1], $arrDate[2], $arrDate[0])); //$year += 543;

	// 	if (($start_dd == $end_dd) && ($start_mm == $end_mm) && ($start_yy == $end_yy)) {
	// 		return DT::DateTimeShortFormat($end_date, 0, 1, $Lang);
	// 	} elseif (($start_dd != $end_dd) && ($start_mm == $end_mm) && ($start_yy == $end_yy)) {
	// 		return $start_dd . "&nbsp;-&nbsp;" . DT::DateTimeShortFormat($end_date, 0, 1, $Lang);
	// 	} elseif ((($start_dd != $end_dd) || ($start_dd == $end_dd)) && ($start_mm != $end_mm) && ($start_yy == $end_yy)) {
	// 		return $start_dd . "&nbsp;" . DT::monthName($start_mm, 0, $Lang) . "&nbsp;-&nbsp;" . DT::DateTimeShortFormat($end_date, 0, 1, $Lang);
	// 	} elseif (($start_yy != $end_yy)) {
	// 		return DT::DateTimeShortFormat($start_date, 0, 1, $Lang) . "&nbsp;-&nbsp;" . DT::DateTimeShortFormat($end_date, 0, 1, $Lang);
	// 	}
	// }

	public static function DateBetweenDisplay($start_date, $end_date, $Lang = 'Th')
	{
		//$arrDate_start = explode("-", $start_date); //Date
		$start_dd = date('j', strtotime($start_date));
		$start_mm = date('m', strtotime($start_date));
		$start_yy = date('Y', strtotime($start_date)); //$year += 543;

		//$arrDate = explode("-", $end_date); //Date
		$end_dd = date('j', strtotime($end_date));
		$end_mm = date('m', strtotime($end_date));
		$end_yy = date('Y', strtotime($end_date)); //$year += 543;

		if (($start_dd == $end_dd) && ($start_mm == $end_mm) && ($start_yy == $end_yy)) {
			return DT::DateTimeShortFormat($end_date, 0, 1, $Lang);
		} elseif (($start_dd != $end_dd) && ($start_mm == $end_mm) && ($start_yy == $end_yy)) {
			return $start_dd . "&nbsp;-&nbsp;" . DT::DateTimeShortFormat($end_date, 0, 1, $Lang);
		} elseif ((($start_dd != $end_dd) || ($start_dd == $end_dd)) && ($start_mm != $end_mm) && ($start_yy == $end_yy)) {
			return $start_dd . "&nbsp;" . DT::monthName($start_mm, 0, $Lang) . "&nbsp;-&nbsp;" . DT::DateTimeShortFormat($end_date, 0, 1, $Lang);
		} elseif (($start_yy != $end_yy)) {
			return DT::DateTimeShortFormat($start_date, 0, 1, $Lang) . "&nbsp;-&nbsp;" . DT::DateTimeShortFormat($end_date, 0, 1, $Lang);
		}
	}

	public static function DateBetweenDisplayWithMinute($start_date, $end_date)
	{
		$arrDate_start = explode("-", $start_date); //Date
		$start_dd = date('j', mktime(0, 0, 0, $arrDate_start[1], $arrDate_start[2], $arrDate_start[0]));
		$start_mm = date('m', mktime(0, 0, 0, $arrDate_start[1], $arrDate_start[2], $arrDate_start[0]));
		$start_yy = date('Y', mktime(0, 0, 0, $arrDate_start[1], $arrDate_start[2], $arrDate_start[0])); //$year += 543;

		$arrDate = explode("-", $end_date); //Date
		$end_dd = date('j', mktime(0, 0, 0, $arrDate[1], $arrDate[2], $arrDate[0]));
		$end_mm = date('m', mktime(0, 0, 0, $arrDate[1], $arrDate[2], $arrDate[0]));
		$end_yy = date('Y', mktime(0, 0, 0, $arrDate[1], $arrDate[2], $arrDate[0])); //$year += 543;

		if (($start_dd == $end_dd) && ($start_mm == $end_mm) && ($start_yy == $end_yy)) {
			return DT::DateTimeFullFormatDot($end_date, 0, 1, "Th");
		} elseif (($start_dd != $end_dd) && ($start_mm == $end_mm) && ($start_yy == $end_yy)) {
			return $start_dd . "&nbsp;-&nbsp;" . DT::DateTimeFullFormatDot($end_date, 0, 1, "Th");
		} elseif ((($start_dd != $end_dd) || ($start_dd == $end_dd)) && ($start_mm != $end_mm) && ($start_yy == $end_yy)) {
			return $start_dd . "&nbsp;" . DT::monthName($start_mm, 0, "Th") . "&nbsp;-&nbsp;" . DT::DateTimeFullFormatDot($end_date, 0, 1, "Th");
		} elseif (($start_yy != $end_yy)) {
			return DT::DateTimeFullFormatDot($start_date, 0, 1, "Th") . "&nbsp;-&nbsp;" . DT::DateTimeFullFormatDot($end_date, 0, 1, "Th");
		}
	}

	public static function DateBetweenDisplayFormatTimeLine($start_date, $end_date, $Lang = 'Th')
	{
		$arrDate_start = explode("-", $start_date); //Date
		$start_dd = date('j', mktime(0, 0, 0, $arrDate_start[1], $arrDate_start[2], $arrDate_start[0]));
		$start_mm = date('m', mktime(0, 0, 0, $arrDate_start[1], $arrDate_start[2], $arrDate_start[0]));
		$start_yy = date('Y', mktime(0, 0, 0, $arrDate_start[1], $arrDate_start[2], $arrDate_start[0])); //$year += 543;

		$arrDate = explode("-", $end_date); //Date
		$end_dd = date('j', mktime(0, 0, 0, $arrDate[1], $arrDate[2], $arrDate[0]));
		$end_mm = date('m', mktime(0, 0, 0, $arrDate[1], $arrDate[2], $arrDate[0]));
		$end_yy = date('Y', mktime(0, 0, 0, $arrDate[1], $arrDate[2], $arrDate[0])); //$year += 543;

		if (($start_dd == $end_dd) && ($start_mm == $end_mm) && ($start_yy == $end_yy)) {
			return DT::DateTimeFullFormatTimeLine($end_date, 0, 1, $Lang);
		} elseif (($start_dd != $end_dd) && ($start_mm == $end_mm) && ($start_yy == $end_yy)) {
			return $start_dd . " - " . DT::DateTimeFullFormatTimeLine($end_date, 0, 1, $Lang);
		} elseif ((($start_dd != $end_dd) || ($start_dd == $end_dd)) && ($start_mm != $end_mm) && ($start_yy == $end_yy)) {
			return $start_dd . " " . DT::monthName($start_mm, 0, $Lang) . " - " . DT::DateTimeFullFormatTimeLine($end_date, 0, 1, $Lang);
		} elseif (($start_yy != $end_yy)) {
			return DT::DateTimeFullFormatTimeLine($start_date, 0, 1, $Lang) . " - " . DT::DateTimeFullFormatTimeLine($end_date, 0, 1, $Lang);
		}
	}

	public static function getNumDate($start_date, $end_date)
	{
		$startDate = new DateTime($start_date);
		$endDate = new DateTime($end_date);
		$interval = $startDate->diff($endDate);
		return $interval->format('%a') + 1;
	}

	public static function thaiDateToSqlDate($thaiDate)
	{
		// Define Thai month names and their numeric values
		$thaiMonths = [
			'มกราคม' => '01',
			'กุมภาพันธ์' => '02',
			'มีนาคม' => '03',
			'เมษายน' => '04',
			'พฤษภาคม' => '05',
			'มิถุนายน' => '06',
			'กรกฎาคม' => '07',
			'สิงหาคม' => '08',
			'กันยายน' => '09',
			'ตุลาคม' => '10',
			'พฤศจิกายน' => '11',
			'ธันวาคม' => '12'
		];

		// Extract parts from Thai date string
		preg_match('/(\d+)\s+(\p{Thai}+)\s+พ\.ศ\.(\d+)/u', $thaiDate, $matches);

		if (count($matches) !== 4) {
			return false; // Invalid format
		}

		$day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
		$thaiMonth = $matches[2];
		$buddhistYear = (int)$matches[3];

		// Convert month name to number
		if (!isset($thaiMonths[$thaiMonth])) {
			return false; // Unknown month
		}
		$month = $thaiMonths[$thaiMonth];

		// Convert Buddhist year to Gregorian year
		$gregorianYear = $buddhistYear - 543;

		// Format as SQL date (YYYY-MM-DD)
		return "$gregorianYear-$month-$day";
	}

	public static function sqlDateToThaiDate($sqlDate, $showtime = 0)
	{
		$dateTime = new DateTime($sqlDate);
		$thaiMonths = [
			1 => 'มกราคม',
			2 => 'กุมภาพันธ์',
			3 => 'มีนาคม',
			4 => 'เมษายน',
			5 => 'พฤษภาคม',
			6 => 'มิถุนายน',
			7 => 'กรกฎาคม',
			8 => 'สิงหาคม',
			9 => 'กันยายน',
			10 => 'ตุลาคม',
			11 => 'พฤศจิกายน',
			12 => 'ธันวาคม'
		];
		// Get date components
		$day = $dateTime->format('j'); // Day without leading zeros
		$monthNum = (int)$dateTime->format('n'); // Month number without leading zeros
		$gregorianYear = (int)$dateTime->format('Y'); // Gregorian year
		$time = $dateTime->format('H.i'); // Time in 24-hour format with a dot between hours and minutes
		$buddhistYear = $gregorianYear + 543;
		$thaiMonth = $thaiMonths[$monthNum];
		if ($showtime == 1) {
			return "$day $thaiMonth พ.ศ.$buddhistYear $time";
		} else {
			return "$day $thaiMonth พ.ศ.$buddhistYear";
		}
	}

	// // Example usage
	// $sqlDate = "2025-03-18 18:30:40.8066667 +00:00";
	// $thaiDate = sqlDateToThaiDate($sqlDate);
	// echo $thaiDate; // Outputs: 18 มีนาคม พ.ศ.2568 18.30


	public static function convertToThaiDateFlexible($englishDate, $includeTime = false)
	{
		// Try to parse the date
		$timestamp = strtotime($englishDate);
		if ($timestamp === false) {
			// Try alternative parsing for unusual formats
			$cleanDate = preg_replace('/\s+/', ' ', trim($englishDate));

			// Handle specific format: "Apr 11 2025 12:00:00:AM"
			if (preg_match('/^([A-Za-z]{3})\s+(\d{1,2})\s+(\d{4})\s+(\d{1,2}):(\d{2}):(\d{2}):([AP]M)$/', $cleanDate, $matches)) {
				$month = $matches[1];
				$day = $matches[2];
				$year = $matches[3];
				$hour = $matches[4];
				$minute = $matches[5];
				$second = $matches[6];
				$ampm = $matches[7];

				// Convert to 24-hour format if PM
				if ($ampm === 'PM' && $hour < 12) {
					$hour += 12;
				} else if ($ampm === 'AM' && $hour == 12) {
					$hour = 0;
				}

				// Create a formatted date string that strtotime can understand
				$reformattedDate = "$month $day $year $hour:$minute:$second";
				$timestamp = strtotime($reformattedDate);

				if ($timestamp === false) {
					return "Invalid date format";
				}
			} else {
				return "Invalid date format";
			}
		}

		// Get date components
		$day = date('j', $timestamp);
		$monthNumber = date('n', $timestamp);
		$year = date('Y', $timestamp);

		// Convert year to Buddhist calendar (CE + 543)
		$buddhistYear = $year + 543;

		// Define Thai month names
		$thaiMonths = [
			1 => 'มกราคม',
			2 => 'กุมภาพันธ์',
			3 => 'มีนาคม',
			4 => 'เมษายน',
			5 => 'พฤษภาคม',
			6 => 'มิถุนายน',
			7 => 'กรกฎาคม',
			8 => 'สิงหาคม',
			9 => 'กันยายน',
			10 => 'ตุลาคม',
			11 => 'พฤศจิกายน',
			12 => 'ธันวาคม'
		];

		// Get Thai month name
		$thaiMonth = $thaiMonths[$monthNumber];

		// Format the Thai date
		$thaiDate = "$day $thaiMonth พ.ศ. $buddhistYear";

		// Add time if requested
		if ($includeTime) {
			$time = date('H:i:s', $timestamp);
			$thaiDate .= " $time น.";
		}

		return $thaiDate;
	}


	public static function cleanDate($englishDate, $includeTime = false)
	{
		// Try to parse the date
		$timestamp = strtotime($englishDate);
		if ($timestamp === false) {
			// Try alternative parsing for unusual formats
			$cleanDate = preg_replace('/\s+/', ' ', trim($englishDate));

			// Handle specific format: "Apr 11 2025 12:00:00:AM"
			if (preg_match('/^([A-Za-z]{3})\s+(\d{1,2})\s+(\d{4})\s+(\d{1,2}):(\d{2}):(\d{2}):([AP]M)$/', $cleanDate, $matches)) {
				$month = $matches[1];
				$day = $matches[2];
				$year = $matches[3];
				$hour = $matches[4];
				$minute = $matches[5];
				$second = $matches[6];
				$ampm = $matches[7];

				// Convert to 24-hour format if PM
				if ($ampm === 'PM' && $hour < 12) {
					$hour += 12;
				} else if ($ampm === 'AM' && $hour == 12) {
					$hour = 0;
				}

				// Create a formatted date string that strtotime can understand
				$reformattedDate = "$month $day $year $hour:$minute:$second";
				$timestamp = strtotime($reformattedDate);

				if ($timestamp === false) {
					return "Invalid date format";
				}
			} else {
				return "Invalid date format";
			}
		}

		// Get date components
		//$day = date('j', $timestamp);
		$day = date('d', $timestamp);
		$monthNumber = date('m', $timestamp);
		$year = date('Y', $timestamp);

		return $year . '-' . $monthNumber . '-' . $day;
	}

	public static function ISODatetoPHPFormat($isoDateString,$DateFormat = 'Y-m-d H:i:s')
	{
		$dateTime = new DateTime($isoDateString);
		return $dateTime->format($DateFormat);
	}

	public static function convertDateFormat($dateString) {
    // Remove the colon before AM/PM if it exists
    $dateString = str_replace(':AM', ' AM', $dateString);
    $dateString = str_replace(':PM', ' PM', $dateString);
    // Create DateTime object from the input string
    $date = DateTime::createFromFormat('M j Y h:i:s A', $dateString);
    if ($date === false) {
      // Try alternative format if the first one fails
      $date = DateTime::createFromFormat('M j Y g:i:s A', $dateString);
    }
    if ($date === false) {
      return "Error: Invalid date format";
    }
    // Format the date to the desired output format
    return $date->format('Y-m-d H:i:s');
	}

	public static function isValidDate($date, $format = 'Y-m-d H:i:s') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
	}

	public static function getTimeFormat($datetime) {
		return date('H:i', strtotime($datetime));
	}
}