<?php
// อนุญาตให้เข้าถึงข้ามโดเมนได้ (CORS) สำหรับ Frontend React
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// ระบบ Cache: เพื่อไม่ให้ดึงข้อมูลจากเว็บต้นทางบ่อยเกินไป (อัปเดตวันละ 1 ครั้ง)
$cacheFile = __DIR__ . '/mapservice_cache.json';
$cacheTime = 86400; // 24 ชั่วโมง

if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    // ดึงจาก Cache จะเร็วมาก!
    echo file_get_contents($cacheFile);
    exit;
}

// 0. โหลด mapservice.json เดิมมาเป็น "โครงสร้างตั้งต้น (Template)"
$templatePath = __DIR__ . '/../../data/templates/mapservice.json';
$baseData = json_decode(file_get_contents($templatePath), true);

// 1. URL ของหน้าเว็บที่เราจะไปดูดข้อมูลพิกัด
$url = "https://datum.dgr.go.th/mapgroundwater/index.php";

// 2. ใช้ cURL โหลด HTML ของหน้าเว็บ
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
$html = curl_exec($ch);
curl_close($ch);

if (!$html) {
    // ถ้าเว็บล่ม ให้พ่นข้อมูลจากไฟล์ json สำรองเก่ากลับไปก่อน
    echo json_encode($baseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// 3. แปลง HTML เป็น DOM Tree เพื่อใช้ XPath ค้นหา
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="UTF-8">' . $html); // @ เพื่อข้าม warning กรณีโค้ด HTML ต้นทางไม่สมบูรณ์
$xpath = new DOMXPath($dom);

// 4. สร้างตัวแปรเก็บรวบรวม path สดๆ จากเว็บไซต์ โดยใช้ ID เป็น key
$scrapedPaths = [];
$gNodes = $xpath->query('//svg//g[@id]');

foreach ($gNodes as $gNode) {
    $id = $gNode->getAttribute('id');
    $pathNode = $xpath->query('.//path', $gNode)->item(0);
    if ($pathNode) {
        $scrapedPaths[$id] = $pathNode->getAttribute('d');
    }
}

// 5. นำ path สดใหม่ที่เพิ่งดูดมา หยอดกลับไปใส่ Template ที่มีข้อมูล Province Code, Name, ฯลฯ
if (isset($baseData['mapData']['provinces'])) {
    foreach ($baseData['mapData']['provinces'] as $key => $province) {
        $id = $province['id'];
        if (isset($scrapedPaths[$id])) {
            // อัปเดต path ใหม่ทับของเก่า
            $baseData['mapData']['provinces'][$key]['path'] = $scrapedPaths[$id];
        }
    }
}

// 6. แปลงเป็น JSON string
$jsonOutput = json_encode($baseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// 7. เซฟเก็บไว้เป็น Cache ในรอบต่อไปจะได้ไม่ต้องไปโหลดใหม่
file_put_contents($cacheFile, $jsonOutput);

// 8. พ่นออกให้ Frontend นำไปใช้งาน
echo $jsonOutput;
?>
