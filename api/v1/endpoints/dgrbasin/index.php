<?php
// อนุญาตให้เข้าถึงข้ามโดเมนได้ (CORS) สำหรับ Frontend React
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// ระบบ Cache: เซฟไว้เพื่อไม่ต้องไปดึงเว็บเค้าทุกครั้ง (อัปเดตทุก 24 ชั่วโมง)
$cacheFile = __DIR__ . '/basinmap_cache.json';
$cacheTime = 86400; // 24 ชั่วโมง

if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    echo file_get_contents($cacheFile);
    exit;
}

// 1. URL หน้าเว็บแอ่งน้ำบาดาล
$url = "https://datum.dgr.go.th/mapgroundwater/dgrbasin.php";

// 2. ใช้ cURL โหลด HTML ของหน้าเว็บ
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
$html = curl_exec($ch);
curl_close($ch);

if (!$html) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "ดึงข้อมูลจากเว็บ DGR ไม่สำเร็จ"]);
    exit;
}

// 3. แปลง HTML เป็น DOM Tree
// หน้าเว็บ dgrbasin เป็น UTF-8 อยู่แล้ว แต่ไม่มี meta charset ทำให้ DOMDocument อ่านเพี้ยน 
// จึงต้องบังคับใส่ <?xml encoding="UTF-8"> เข้าไปตอน loadHTML
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
$xpath = new DOMXPath($dom);

$basins = [];
$baseUrl = "https://datum.dgr.go.th/mapgroundwater/";

// 4. แอดข้อมูลตั้งต้นสำหรับ "แอ่งน้ำบาดาลทั้งหมด 27 แอ่ง" เข้าไปก่อน (เป็น id 0)
$basins[] = [
    "id" => 0,
    "name" => "แอ่งน้ำบาดาล 27 แอ่ง",
    "nameEn" => "27 Groundwater Basins",
    "downloadUrl" => $baseUrl . "files/dgrbasin/shp.zip",
    "thumbnailUrl" => $baseUrl . "files/etc/shp.jpg",
    "type" => "all"
];

// 5. ค้นหาแถวในตารางทั้งหมด <tr> (นี่คือจุดที่รายชื่อแอ่งน้ำซ่อนอยู่)
$rows = $xpath->query('//table/tbody/tr');

$idCounter = 1;
foreach ($rows as $index => $row) {
    // ข้ามแถวแรก เพราะมันคือข้อมูลรวม 27 แอ่งที่เราใส่ไปแล้วในข้อ 4
    if ($index === 0) continue;

    $cols = $xpath->query('./td', $row);
    if ($cols->length >= 2) {
        // ดึงชื่อแอ่งน้ำจากคอลัมน์แรก เช่น "1. แอ่งเชียงราย-พะเยา"
        $nameRaw = trim($cols->item(0)->nodeValue);
        // ใช้ Regular Expression เพื่อตัดตัวเลขด้านหน้าทิ้ง ให้เหลือแค่ "แอ่งเชียงราย-พะเยา"
        $name = preg_replace('/^\d+\.\s*/', '', $nameRaw);
        
        // ค้นหารูปภาพที่ซ่อนอยู่ใน Modal จากคอลัมน์ที่ 2
        $imgNode = $xpath->query('.//div[contains(@class, "modal")]//img', $cols->item(1))->item(0);
        $imgUrl = "";
        $nameEn = ""; 
        
        if ($imgNode) {
            $src = $imgNode->getAttribute('src');
            // ทำ URL รูปภาพให้สมบูรณ์ (เผื่อไฟล์มีเว้นวรรค ให้แปลงเป็น %20)
            $imgUrl = $baseUrl . str_replace(" ", "%20", $src); 
            
            // ดึงชื่อภาษาอังกฤษออกมาจากชื่อไฟล์รูปภาพ 
            // (เช่น "1CHIANGRAI_PAYAO_BASIN_th.jpg" -> "Chiangrai Payao Basin")
            $basename = basename($src);
            $basename = preg_replace('/^\d+/', '', $basename); // ลบตัวเลขข้างหน้า
            $basename = str_ireplace(['_th.jpg', ' th.jpg', '.jpg'], '', $basename); // ลบคำว่า _th.jpg
            $basename = str_replace('_', ' ', $basename); // เปลี่ยน _ เป็นช่องว่าง
            $nameEn = ucwords(strtolower($basename)); // ทำให้ตัวแรกของคำเป็นพิมพ์ใหญ่
        }

        // จับข้อมูลยัดใส่ JSON ตามโครงสร้าง
        $basins[] = [
            "id" => $idCounter,
            "name" => $name,
            "nameEn" => $nameEn,
            "imageUrl" => $imgUrl,
            "type" => "individual"
        ];
        $idCounter++;
    }
}

$result = [
    "overviewImage" => $baseUrl . "files/dgrbasin/dgrbasinresize.jpg",
    "basins" => $basins
];

// แปลงผลลัพธ์เป็น JSON และเซฟแคชไว้
$jsonOutput = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
file_put_contents($cacheFile, $jsonOutput);

// ส่งคืน JSON กลับให้ Frontend
echo $jsonOutput;
?>
