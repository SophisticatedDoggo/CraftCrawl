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
    die($e);
}

echo json_encode($features);
?>
