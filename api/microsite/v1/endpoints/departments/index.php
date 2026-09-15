<?php
// src/api/microsite/v1/endpoints/departments.php
require_once '../../config.php';
require_once '../../helper.php';

// Parameters
$slug = $_GET['slug'] ?? '';
$action = $_GET['action'] ?? 'get'; // action=list or action=get

try {
    if ($action === 'list') {
        // Return a list of all active departments
        $stmt = $DB->prepare("SELECT DeptID, Slug, NameTH, NameEN, DeptGroup, DefaultLayout, Image, UpdateDate 
                              FROM microsite_departments 
                              WHERE IsActive = 'Y' 
                              ORDER BY UpdateDate DESC");
        $stmt->execute();
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format data
        $result = array_map(function($row) {
            return [
                'id' => $row['DeptID'],
                'slug' => $row['Slug'],
                'nameTh' => $row['NameTH'],
                'nameEn' => $row['NameEN'],
                'group' => $row['DeptGroup'],
                'layout' => $row['DefaultLayout'],
                'logo' => getImageUrl($row['Image'], $row['Slug']),
                'updatedAt' => $row['UpdateDate']
            ];
        }, $departments);

        sendResponse($result, 200, "Departments retrieved successfully");

    } else {
        // Return details for a specific department
        if (empty($slug)) {
            sendError('Department slug is required', 400);
        }

        $stmt = $DB->prepare("SELECT * FROM microsite_departments WHERE Slug = ? AND IsActive = 'Y' LIMIT 1");
        $stmt->execute([$slug]);
        $dept = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dept) {
            sendError('Department not found', 404);
        }

        // Format to match deptdata.json -> contact
        $contact = [
            'nameTh' => $dept['NameTH'],
            'nameEn' => $dept['NameEN'],
            'subtitleTh' => 'กรมทรัพยากรน้ำบาดาล',
            'subtitleEn' => 'Department of Groundwater Resources',
            'addressTh' => $dept['Address'],
            'addressEn' => $dept['AddressEN'] ?? '',
            'phone' => $dept['Tel'],
            'fax' => $dept['Fax'],
            'email' => $dept['Email'],
            'facebook' => $dept['Facebook'],
            'youtube' => $dept['YouTube'],
            'mapLat' => $dept['Latitude'],
            'mapLng' => $dept['Longitude'],
            'mapQuery' => $dept['Latitude'] . ',' . $dept['Longitude'],
            'mapDownload' => getImageUrl($dept['MapImage'], $dept['Slug']),
            'logo' => getImageUrl($dept['Image'], $dept['Slug']),
            'seoTitle' => $dept['SEOTitle'],
            'seoDescription' => $dept['SEODescription'],
            'seoKeywords' => $dept['SEOKeywords']
        ];

        // Construct full department data object
        $result = [
            'contact' => $contact
        ];

        sendResponse($result, 200, "Department data retrieved successfully");
    }

} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
