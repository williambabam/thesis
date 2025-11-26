<?php

function getAllRegions($pdo) {
    $stmt = $pdo->query('SELECT DISTINCT region FROM philippines_locations ORDER BY region ASC');
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getProvincesByRegion($pdo, $region) {
    $stmt = $pdo->prepare('
        SELECT DISTINCT province 
        FROM philippines_locations 
        WHERE region = ? 
        ORDER BY province ASC
    ');
    $stmt->execute([$region]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getCitiesByProvince($pdo, $province) {
    $stmt = $pdo->prepare('
        SELECT DISTINCT city 
        FROM philippines_locations 
        WHERE province = ? AND city IS NOT NULL
        ORDER BY city ASC
    ');
    $stmt->execute([$province]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getAllLocations($pdo) {
    $stmt = $pdo->query('
        SELECT DISTINCT 
            CONCAT_WS(", ", province, city) as location
        FROM philippines_locations 
        WHERE city IS NOT NULL
        ORDER BY location ASC
    ');
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function searchLocations($pdo, $keyword) {
    $keyword = "%{$keyword}%";
    $stmt = $pdo->prepare('
        SELECT DISTINCT 
            CONCAT_WS(", ", province, city, region) as location
        FROM philippines_locations 
        WHERE province LIKE ? 
           OR city LIKE ? 
           OR region LIKE ?
        ORDER BY location ASC
        LIMIT 20
    ');
    $stmt->execute([$keyword, $keyword, $keyword]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getLocationDetails($pdo, $city, $province) {
    $stmt = $pdo->prepare('
        SELECT * FROM philippines_locations 
        WHERE city = ? AND province = ?
        LIMIT 1
    ');
    $stmt->execute([$city, $province]);
    return $stmt->fetch();
}

function formatLocation($city, $province, $region = null) {
    $parts = [];
    if ($city) $parts[] = $city;
    if ($province) $parts[] = $province;
    if ($region) $parts[] = $region;
    return implode(", ", array_filter($parts));
}
?>