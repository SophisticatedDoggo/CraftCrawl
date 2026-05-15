<?php

require_once __DIR__ . '/cloudinary_upload.php';

function craftcrawl_user_avatar_url($user, $size = 96) {
    $size = max(32, min(320, (int) $size));

    if (!empty($user['profile_photo_object_key'])) {
        return craftcrawl_cloudinary_delivery_url(
            $user['profile_photo_object_key'],
            'f_auto,q_auto,c_fill,g_face,w_' . $size . ',h_' . $size
        );
    }

    if (!empty($user['profile_photo_url'])) {
        return $user['profile_photo_url'];
    }

    return null;
}

function craftcrawl_user_initials($user) {
    $first = trim((string) ($user['fName'] ?? ''));
    $last = trim((string) ($user['lName'] ?? ''));
    $initials = '';

    if ($first !== '') {
        $initials .= strtoupper(substr($first, 0, 1));
    }
    if ($last !== '') {
        $initials .= strtoupper(substr($last, 0, 1));
    }

    return $initials !== '' ? $initials : 'CC';
}

function craftcrawl_avatar_escape($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function craftcrawl_render_user_avatar($user, $size_class = 'medium', $extra_class = '') {
    $frame = preg_replace('/[^a-z0-9_-]/i', '', (string) ($user['selected_profile_frame'] ?? ''));
    $frame_style = preg_replace('/[^a-z0-9_-]/i', '', (string) ($user['selected_profile_frame_style'] ?? 'solid'));
    $frame_classes = $frame !== ''
        ? 'has-frame-' . $frame . ' has-frame-style-' . ($frame_style !== '' ? $frame_style : 'solid')
        : '';
    $classes = trim('user-avatar user-avatar-' . $size_class . ' ' . $frame_classes . ' ' . $extra_class);
    $url = craftcrawl_user_avatar_url($user, $size_class === 'large' ? 180 : 96);
    $name = trim(($user['fName'] ?? '') . ' ' . ($user['lName'] ?? ''));
    $alt = $name !== '' ? $name . ' profile photo' : 'Profile photo';

    if ($url !== null) {
        return '<span class="' . craftcrawl_avatar_escape($classes) . '"><img src="' . craftcrawl_avatar_escape($url) . '" alt="' . craftcrawl_avatar_escape($alt) . '" loading="lazy"></span>';
    }

    return '<span class="' . craftcrawl_avatar_escape($classes) . '" aria-label="' . craftcrawl_avatar_escape($alt) . '"><span>' . craftcrawl_avatar_escape(craftcrawl_user_initials($user)) . '</span></span>';
}

?>
