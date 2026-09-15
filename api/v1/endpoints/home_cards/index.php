<?php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    sendError('Method not allowed', 405);
}

$id = isset($_GET['id']) ? max(0, (int)$_GET['id']) : 0;
$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
$limit = isset($_GET['limit']) ? min(100, max(0, (int)$_GET['limit'])) : 0;

try {
    $sql = "SELECT CardID, Slug, Title_TH, Title_EN, Description_TH, Description_EN,
                   Link_TH, Link_EN, BgClass, Image, Direction
            FROM home_cards
            WHERE DelData='N' AND IsActive='Y'";
    $params = [];
    if ($id > 0) {
        $sql .= ' AND CardID=:id';
        $params[':id'] = $id;
    }
    if ($slug !== '') {
        $sql .= ' AND Slug=:slug';
        $params[':slug'] = $slug;
    }
    $sql .= ' ORDER BY OrderNo ASC, CardID DESC';
    if ($limit > 0) {
        $sql .= ' LIMIT :limit';
        $params[':limit'] = $limit;
    }

    $stmt = $DB->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    $response = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $image = trim((string)($row['Image'] ?? ''));
        if ($image !== '' && !preg_match('/^(?:\/|https?:\/\/)/i', $image)) {
            $image = $ImgPath . 'home_cards/' . $image;
        }
        $response[] = [
            'slug' => (string)$row['Slug'],
            'title' => (string)$row['Title_TH'],
            'titleEn' => (string)($row['Title_EN'] ?? ''),
            'description' => (string)($row['Description_TH'] ?? ''),
            'descriptionEn' => (string)($row['Description_EN'] ?? ''),
            'link' => (string)($row['Link_TH'] ?? ''),
            'linkEn' => (string)($row['Link_EN'] ?? ''),
            'bgClass' => (string)$row['BgClass'],
            'icon' => $image,
            'direction' => (string)$row['Direction'],
        ];
    }

    if ($id > 0 || $slug !== '') {
        sendResponse($response[0] ?? null, $response ? 200 : 404, $response ? 'Success' : 'Not found');
    }
    sendResponse($response);
} catch (PDOException $e) {
    error_log('home_cards endpoint: ' . $e->getMessage());
    sendError('Unable to load home cards', 500);
}
