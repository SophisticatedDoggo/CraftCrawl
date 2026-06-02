<?php

function craftcrawl_normalize_username($value) {
    return strtolower(trim((string) $value));
}

function craftcrawl_username_validation_message($username) {
    if ($username === '') {
        return 'Please enter a username.';
    }

    if (strlen($username) < 3 || strlen($username) > 24) {
        return 'Username must be 3 to 24 characters.';
    }

    if (!preg_match('/^[a-z0-9_]+$/', $username)) {
        return 'Username can only use letters, numbers, and underscores.';
    }

    return null;
}

function craftcrawl_username_is_available($conn, $username, $exclude_user_id = null) {
    $username = craftcrawl_normalize_username($username);

    if ($exclude_user_id !== null) {
        $exclude_user_id = (int) $exclude_user_id;
        $stmt = $conn->prepare("SELECT id FROM users WHERE username=? AND id<>? LIMIT 1");
        $stmt->bind_param('si', $username, $exclude_user_id);
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
        $stmt->bind_param('s', $username);
    }

    $stmt->execute();
    return !$stmt->get_result()->fetch_assoc();
}

function craftcrawl_username_available_message($conn, $username, $exclude_user_id = null) {
    $username = craftcrawl_normalize_username($username);
    $validation_message = craftcrawl_username_validation_message($username);

    if ($validation_message !== null) {
        return $validation_message;
    }

    if (!craftcrawl_username_is_available($conn, $username, $exclude_user_id)) {
        return 'That username is taken.';
    }

    return null;
}

function craftcrawl_username_base_from_text($value) {
    $base = strtolower(trim((string) $value));
    $base = preg_replace('/[^a-z0-9_]+/', '_', $base);
    $base = trim($base, '_');

    if ($base === '') {
        $base = 'crawler';
    }

    if (!preg_match('/[a-z]/', $base)) {
        $base = 'crawler_' . $base;
    }

    return substr($base, 0, 18);
}

function craftcrawl_generate_unique_username($conn, $base_text) {
    $base = craftcrawl_username_base_from_text($base_text);

    for ($attempt = 0; $attempt < 50; $attempt++) {
        $suffix = $attempt === 0 ? '' : '_' . random_int(1000, 999999);
        $candidate = substr($base, 0, 24 - strlen($suffix)) . $suffix;

        if (craftcrawl_username_validation_message($candidate) === null
            && craftcrawl_username_is_available($conn, $candidate)) {
            return $candidate;
        }
    }

    do {
        $candidate = 'crawler_' . bin2hex(random_bytes(5));
    } while (!craftcrawl_username_is_available($conn, $candidate));

    return $candidate;
}

?>
