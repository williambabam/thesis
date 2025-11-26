<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/location_helper.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_regions':
            // Get all regions
            $regions = getAllRegions($pdo);
            echo json_encode($regions);
            break;

        case 'get_provinces':
            // Get provinces by region
            $region = $_GET['region'] ?? '';
            if (empty($region)) {
                echo json_encode([]);
                exit;
            }
            $provinces = getProvincesByRegion($pdo, $region);
            echo json_encode($provinces);
            break;

        case 'get_cities':
            // Get cities by province
            $province = $_GET['province'] ?? '';
            if (empty($province)) {
                echo json_encode([]);
                exit;
            }
            $cities = getCitiesByProvince($pdo, $province);
            echo json_encode($cities);
            break;

        case 'search':
            // Search locations
            $keyword = $_GET['keyword'] ?? '';
            if (strlen($keyword) < 2) {
                echo json_encode([]);
                exit;
            }
            $locations = searchLocations($pdo, $keyword);
            echo json_encode($locations);
            break;

        case 'get_all_regions':
            // Get all regions for dropdown initialization
            $regions = getAllRegions($pdo);
            echo json_encode([
                'success' => true,
                'regions' => $regions
            ]);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>