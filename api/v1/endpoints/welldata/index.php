<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// อนุญาตให้เข้าถึงข้ามโดเมนได้ (CORS) สำหรับ Frontend React
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// ระบบ Cache
$cacheFile = __DIR__ . '/welldata_cache.json';
$cacheTime = 86400; // 24 ชั่วโมง

if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    echo file_get_contents($cacheFile);
    exit;
}

// โหลด welldata.json เดิมมาเป็น "โครงสร้างตั้งต้น (Template)"
$templatePath = __DIR__ . '/../../data/templates/welldata.json';
$baseData = json_decode(file_get_contents($templatePath), true);

// 1. URL ของระบบบริการข้อมูลบ่อน้ำบาดาล
$url = "http://app.dgr.go.th/newpasutara/xml/index.php";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
$htmlRaw = curl_exec($ch);
curl_close($ch);

if (!$htmlRaw) {
    // ถ้าเว็บล่ม ให้ส่งข้อมูลเก่ากลับไปก่อน
    echo json_encode($baseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ลบ meta charset ออกก่อนโหลดเข้า DOMDocument เพื่อป้องกันการแปลความหมายผิดเพี้ยน
$htmlRaw = preg_replace('/<meta[^>]+charset[^>]+>/i', '', $htmlRaw);

// ตัวเว็บต้นทางเป็น UTF-8 อยู่แล้ว
$dom = new DOMDocument();
// ข้าม error HTML ไม่สมบูรณ์ และระบุว่าเป็น UTF-8
@$dom->loadHTML('<?xml encoding="UTF-8">' . $htmlRaw);
$xpath = new DOMXPath($dom);

// ฟังก์ชันสำหรับซ่อมแซมข้อความที่มาจากฐานข้อมูล TIS-620 แต่ถูกแทรกอยู่ในหน้าเว็บ UTF-8
function repairText($text) {
    if (preg_match('/[\x{00C0}-\x{00FF}]/u', $text)) {
        // แปลงอักขระละตินประหลาดๆ กลับเป็นไบต์ดิบ
        $rawBytes = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $text);
        
        // ถ้าไบต์ดิบสามารถอ่านเป็น UTF-8 ได้สมบูรณ์ แสดงว่ามันคือ UTF-8 ตั้งแต่แรก (เช่น "บ่อ")
        if (mb_check_encoding($rawBytes, 'UTF-8')) {
            return $rawBytes;
        } else {
            // ถ้าไม่ใช่ แสดงว่ามันคือ TIS-620 ตั้งแต่แรก (เช่น "ภาคกลาง")
            return @iconv('TIS-620', 'UTF-8//IGNORE', $rawBytes);
        }
    }
    return $text;
}

// === 1. อัปเดตข้อมูลสถิติ 4 กล่อง (Stats) ===
$cardTitles = $xpath->query('//h4[contains(@class, "card-title")]');
// กล่องที่ 1-4 คือ stats (ไม่รวมอันหลังๆ ที่เป็นชื่อตาราง)
if ($cardTitles->length >= 4) {
    $baseData['stats'][0]['value'] = repairText(trim($cardTitles->item(0)->nodeValue)); // จำนวนบ่อ ภาครัฐ
    $baseData['stats'][1]['value'] = repairText(trim($cardTitles->item(1)->nodeValue)); // ปริมาณน้ำ ภาครัฐ
    $baseData['stats'][2]['value'] = repairText(trim($cardTitles->item(2)->nodeValue)); // จำนวนบ่อ เอกชน
    $baseData['stats'][3]['value'] = repairText(trim($cardTitles->item(3)->nodeValue)); // ปริมาณน้ำ เอกชน
}

// === 2. อัปเดตข้อมูล Marquee (ตัววิ่ง) ===
$marqueeNode = $xpath->query('//marquee')->item(0);
if ($marqueeNode) {
    $baseData['marquee']['text'] = repairText(trim($marqueeNode->nodeValue));
    $baseData['marquee']['timestamp'] = date('Y-m-d H:i:s');
}

// === 3. อัปเดตข้อมูลตารางรายโครงการ (Projects) ===
// หาตารางแรกที่อยู่ใต้ card-title ที่มีคำว่า "โครงการ"
$projectRows = $xpath->query('//table[1]//tbody/tr');
if ($projectRows->length > 0) {
    $projects = [];
    foreach ($projectRows as $row) {
        $text = repairText(trim($row->nodeValue));
        // รูปแบบ text ของต้นทาง: "โครงการ... ( จำนวน : 5 บ่อ)"
        if (preg_match('/^(.*?)\(\s*จำนวน\s*:\s*([\d,]+)\s*บ่อ\s*\)/i', $text, $matches)) {
            $projects[] = [
                "name" => trim($matches[1]),
                "count" => (int) str_replace(',', '', $matches[2])
            ];
        }
    }
    if (count($projects) > 0) {
        $baseData['projects']['items'] = $projects;
    }
}

// === 4. อัปเดตข้อมูลตาราง Summary (ประเภทบ่อ, ความลึก, ภาค) ===
// หา div class tab-pane ทั้งหมด
$tabPanes = $xpath->query('//div[contains(@class, "tab-pane")]');
if ($tabPanes->length >= 3) {
    
    // Tab 1: ประเภทบ่อ (profile)
    $typeRows = $xpath->query('.//table/tbody/tr', $tabPanes->item(0));
    $typeData = [];
    foreach ($typeRows as $row) {
        $cols = $xpath->query('./td', $row);
        if ($cols->length >= 3) {
            $typeData[] = [
                "ID" => repairText(trim($cols->item(0)->nodeValue)),
                "ประเภทบ่อ" => repairText(trim($cols->item(1)->nodeValue)),
                "จำนวน" => repairText(trim($cols->item(2)->nodeValue))
            ];
        }
    }
    if (count($typeData) > 0) $baseData['summary']['tabs'][0]['data'] = $typeData;

    // Tab 2: ความลึกพัฒนา (messages)
    $depthRows = $xpath->query('.//table/tbody/tr', $tabPanes->item(1));
    $depthData = [];
    foreach ($depthRows as $row) {
        $cols = $xpath->query('./td', $row);
        if ($cols->length >= 3) {
            $depthData[] = [
                "ID" => repairText(trim($cols->item(0)->nodeValue)),
                "ความลึก" => repairText(trim($cols->item(1)->nodeValue)),
                "จำนวนบ่อ" => repairText(trim($cols->item(2)->nodeValue))
            ];
        }
    }
    if (count($depthData) > 0) $baseData['summary']['tabs'][1]['data'] = $depthData;

    // Tab 3: ภาค (settings)
    $regionRows = $xpath->query('.//table/tbody/tr', $tabPanes->item(2));
    $regionData = [];
    foreach ($regionRows as $row) {
        $cols = $xpath->query('./td', $row);
        if ($cols->length >= 5) {
            $regionData[] = [
                "ID" => repairText(trim($cols->item(0)->nodeValue)),
                "ภาค" => repairText(trim($cols->item(1)->nodeValue)),
                "จำนวนบ่อ" => repairText(trim($cols->item(2)->nodeValue)),
                "ปริมาณน้ำ" => repairText(trim($cols->item(3)->nodeValue)),
                "ความลึกเฉลี่ย" => repairText(trim($cols->item(4)->nodeValue))
            ];
        }
    }
    if (count($regionData) > 0) {
        $baseData['summary']['tabs'][2]['data'] = $regionData;
        $baseData['summary']['tabs'][2]['updateNote'] = "อัพเดท: " . date('Y-m-d H:i:s');
    }
}

// แปลงผลลัพธ์ที่อัปเดตแล้วเป็น JSON
$jsonOutput = json_encode($baseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

if ($jsonOutput === false) {
    // ถ้าเข้ารหัส JSON ไม่สำเร็จ ให้ดีบักปัญหา
    echo json_encode(["error" => "json_encode error: " . json_last_error_msg()]);
    exit;
}

// เซฟแคช
file_put_contents($cacheFile, $jsonOutput);

// พ่นออกให้ Frontend นำไปใช้งาน
echo $jsonOutput;
?>
