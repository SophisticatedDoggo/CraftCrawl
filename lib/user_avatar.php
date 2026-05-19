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

function craftcrawl_avatar_normalize_frame_key($frame_key) {
    $frame_key = preg_replace('/[^a-z0-9_-]/i', '', (string) $frame_key);
    $legacy_aliases = [
        'bronze' => 'frame_1',
        'amber' => 'frame_2',
        'copper' => 'frame_3',
        'foam' => 'frame_4',
        'slate' => 'frame_5',
        'berry' => 'frame_5_1',
        'silver' => 'frame_6',
        'teal' => 'frame_7',
        'crimson' => 'frame_8',
        'emerald' => 'frame_9',
        'lime' => 'frame_10',
        'sapphire' => 'frame_11',
        'indigo' => 'frame_12',
        'amethyst' => 'frame_13',
        'coral' => 'frame_14',
        'gold' => 'frame_15',
        'pearl' => 'frame_16',
        'rose' => 'frame_17',
        'mint' => 'frame_18',
        'obsidian' => 'frame_19',
        'ember' => 'frame_20',
        'legend' => 'frame_23',
        'metal1' => 'frame_7',
        'nature1' => 'frame_1',
        'hot1' => 'frame_15',
        'ice1' => 'frame_14',
        'metal2' => 'frame_8',
        'nature2' => 'frame_2',
        'hot2' => 'frame_17',
        'ice2' => 'frame_16',
        'metal3' => 'frame_9',
        'nature3' => 'frame_3',
        'hot3' => 'frame_19',
        'ice3' => 'frame_20',
        'metal4' => 'frame_13',
        'nature4' => 'frame_4',
        'hot4' => 'frame_22',
        'ice4' => 'frame_21',
        'metal5' => 'frame_10',
        'nature5' => 'frame_5_1',
        'hot5' => 'frame_23',
        'metal6' => 'frame_12',
        'nature6' => 'frame_5',
        'metal7' => 'frame_18',
        'metal8' => 'frame_13',
        'skull1' => 'frame_23',
    ];

    return $legacy_aliases[$frame_key] ?? $frame_key;
}

function craftcrawl_render_user_avatar($user, $size_class = 'medium', $extra_class = '') {
    $frame = craftcrawl_avatar_normalize_frame_key($user['selected_profile_frame'] ?? '');
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
