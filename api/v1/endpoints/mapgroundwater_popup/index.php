<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// รับค่ารหัสจังหวัด (province_code) จาก URL เช่น ?code=30
$provinceCode = isset($_GET['code']) ? $_GET['code'] : '';

if (empty($provinceCode)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "กรุณาระบุรหัสจังหวัด (code)"]);
    exit;
}

// ระบบ Cache (แยกตามรหัสจังหวัด)
$cacheFile = __DIR__ . "/popup_cache_{$provinceCode}.json";
$cacheTime = 86400; // 24 ชั่วโมง

if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    echo file_get_contents($cacheFile);
    exit;
}

// โหลดหน้าเว็บ popup ของกรมทรัพยากรน้ำบาดาล
$url = "https://datum.dgr.go.th/mapgroundwater/popup.php?province_code=" . urlencode($provinceCode);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
$htmlRaw = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if (!$htmlRaw) {
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "เชื่อมต่อเว็บ DGR ไม่สำเร็จ",
        "error_detail" => $curlError
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ลบ meta charset ออกก่อนโหลดเข้า DOMDocument เพื่อป้องกันการอ่านเป็น ISO-8859-1
$htmlRaw = preg_replace('/<meta[^>]+charset[^>]+>/i', '', $htmlRaw);

$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="UTF-8">' . $htmlRaw);
$xpath = new DOMXPath($dom);

$baseUrl = "https://datum.dgr.go.th/mapgroundwater/";

// 1. ดึงชื่อจังหวัด
$nameNode = $xpath->query('//table//tbody//tr//td/b')->item(0);
$provinceName = $nameNode ? trim($nameNode->nodeValue) : "";

// 2. ดึงลิงก์รูปขนาดเล็ก
$smallMapNode = $xpath->query('//a[@id="id1"]')->item(0);
$smallMapUrl = $smallMapNode ? $baseUrl . $smallMapNode->getAttribute('href') : "";

// 3. ดึงลิงก์รููปขนาดใหญ่ (อาจเป็นเว็บภายนอก http://img.dgr.go.th/...)
$bigMapNode = $xpath->query('//a[@id="id2"]')->item(0);
$bigMapUrl = "";
if ($bigMapNode) {
    $href = trim($bigMapNode->getAttribute('href'));
    if (strpos($href, 'http') === 0) {
        $bigMapUrl = $href;
    } else {
        $bigMapUrl = $baseUrl . ltrim($href, '/');
    }
}

// 4. ดึงลิงก์ Shapefile
$shpNode = $xpath->query('//a[@id="id3"]')->item(0);
$shpUrl = $shpNode ? $baseUrl . $shpNode->getAttribute('href') : "";

// 5. ดึงรูป Preview
$previewNode = $xpath->query('//img[contains(@src, "files/previews")]')->item(0);
$previewUrl = $previewNode ? $baseUrl . $previewNode->getAttribute('src') : "";

// ส่งออกเป็น JSON
$result = [
    "provinceCode" => $provinceCode,
    "provinceName" => $provinceName,
    "smallMapUrl" => $smallMapUrl,
    "bigMapUrl" => $bigMapUrl,
    "shapefileUrl" => $shpUrl,
    "previewUrl" => $previewUrl,
    "sourceUrl" => $url
];

$jsonOutput = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// เซฟแคช
file_put_contents($cacheFile, $jsonOutput);

echo $jsonOutput;
?>
