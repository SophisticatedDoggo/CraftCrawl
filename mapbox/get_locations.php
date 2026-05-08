<?php
header('Content-Type: application/json; charset=utf-8');
include '../db.php';

$sql = "SELECT * FROM businesses WHERE approved = TRUE";

$features = [];

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($business = $result->fetch_assoc()) {
        $features[] = [
            'type' => 'Feature',
            'properties' => [
                'id' => $business['id'],
                'title' => $business['bName'],
                'businessType' => $business['bType'],
                'streetAddress' => $business['street_address'],
                'city' => $business['city'],
                'state' => $business['state'],
                'zip' => $business['zip']
            ],
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$business['longitude'], $business['latitude']]
            ]
        ];
    }
} catch (Exception $e) {
    error_log('Location feed failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load locations.']);
    exit();
}

echo json_encode($features);
?>
