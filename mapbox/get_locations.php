<?php
header('Content-Type: application/json; charset=utf-8');
include '../db.php';

$sql = "
    SELECT
        b.id AS legacy_business_id,
        l.id AS location_id,
        l.name,
        l.location_type,
        l.street_address,
        l.city,
        l.state,
        l.zip,
        l.longitude,
        l.latitude,
        l.visibility_status
    FROM locations l
    LEFT JOIN businesses b ON b.id = l.legacy_business_id
    WHERE l.visibility_status IN ('public_unclaimed', 'public_claimed')
      AND l.disabledAt IS NULL
";

$features = [];

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($location = $result->fetch_assoc()) {
        $features[] = [
            'type' => 'Feature',
            'properties' => [
                'id' => $location['location_id'],
                'locationId' => $location['location_id'],
                'legacyBusinessId' => $location['legacy_business_id'],
                'title' => $location['name'],
                'businessType' => $location['location_type'],
                'streetAddress' => $location['street_address'],
                'city' => $location['city'],
                'state' => $location['state'],
                'zip' => $location['zip'],
                'claimStatus' => $location['visibility_status']
            ],
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$location['longitude'], $location['latitude']]
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
