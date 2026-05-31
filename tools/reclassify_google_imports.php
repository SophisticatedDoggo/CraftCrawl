<?php

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/location_classifier.php';

$options = getopt('', ['pending-only', 'dry-run']);
$pending_only = array_key_exists('pending-only', $options);
$dry_run = array_key_exists('dry-run', $options);
$chain_patterns = craftcrawl_active_chain_patterns($conn);

$sql = "
    SELECT gpi.id AS import_id, gpi.raw_place_json, gpi.search_term, l.*
    FROM google_place_imports gpi
    INNER JOIN locations l ON l.id=gpi.location_id
";
if ($pending_only) {
    $sql .= " WHERE l.visibility_status='pending_import_review'";
}
$sql .= " ORDER BY gpi.id";

$result = $conn->query($sql);
$counts = ['checked' => 0, 'auto_add' => 0, 'needs_review' => 0, 'reject' => 0, 'skipped' => 0];

while ($row = $result->fetch_assoc()) {
    $raw = json_decode((string) $row['raw_place_json'], true);
    if (!is_array($raw)) {
        $counts['skipped']++;
        continue;
    }

    $candidate = [
        'name' => $row['name'],
        'street_address' => $row['street_address'],
        'latitude' => $row['latitude'],
        'longitude' => $row['longitude'],
        'phone' => $row['phone'],
        'website' => $row['website'],
        'primary_type' => $raw['primaryType'] ?? '',
        'primary_type_display_name' => $raw['primaryTypeDisplayName']['text'] ?? '',
        'types' => $raw['types'] ?? [],
        'business_status' => $raw['businessStatus'] ?? '',
        'search_term' => $row['search_term'],
    ];
    $classification = craftcrawl_classify_location_candidate($candidate, $chain_patterns);
    $decision = $classification['decision'];
    $counts['checked']++;
    $counts[$decision]++;

    if ($dry_run) {
        echo "#{$row['id']} {$row['name']}: {$row['location_type']} / {$row['visibility_status']} -> {$classification['suggested_category']} / {$decision} / {$classification['score']}\n";
        continue;
    }

    $positive = json_encode($classification['positive_signals']);
    $negative = json_encode($classification['negative_signals']);
    $update_import = $conn->prepare("UPDATE google_place_imports SET fit_score=?,suggested_category=?,decision=?,positive_signals=?,negative_signals=?,decision_reason=? WHERE id=?");
    $update_import->bind_param('isssssi', $classification['score'], $classification['suggested_category'], $decision, $positive, $negative, $classification['decision_reason'], $row['import_id']);
    $update_import->execute();

    if ($row['visibility_status'] === 'pending_import_review' && $decision === 'auto_add') {
        $notes = trim((string) $row['adminNotes'] . "\nReclassified Google import score: " . $classification['score'] . "\nDecision: auto_add");
        $update_location = $conn->prepare("UPDATE locations SET location_type=?,visibility_status='public_unclaimed',adminNotes=?,approvedAt=NOW() WHERE id=?");
        $update_location->bind_param('ssi', $classification['suggested_category'], $notes, $row['id']);
        $update_location->execute();
    } else {
        $update_location = $conn->prepare("UPDATE locations SET location_type=? WHERE id=? AND visibility_status='pending_import_review'");
        $update_location->bind_param('si', $classification['suggested_category'], $row['id']);
        $update_location->execute();
    }
}

echo json_encode($counts, JSON_PRETTY_PRINT) . "\n";

?>
