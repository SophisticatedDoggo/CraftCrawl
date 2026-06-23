<?php

function craftcrawl_render_poll_results(array $options, ?int $user_voted_option_id, int $total_votes): string {
    $html = '<div class="business-poll-results" data-poll-results>';

    foreach ($options as $option) {
        $opt_id = (int) $option['id'];
        $opt_text = htmlspecialchars($option['option_text'] ?? '', ENT_QUOTES, 'UTF-8');
        $vote_count = (int) ($option['vote_count'] ?? 0);
        $pct = $total_votes > 0 ? (int) round(($vote_count / $total_votes) * 100) : 0;
        $is_voted = $user_voted_option_id !== null && $user_voted_option_id === $opt_id;

        $html .= '<div class="business-poll-result' . ($is_voted ? ' is-voted' : '') . '">';
        $html .= '<div class="business-poll-bar-btn">';
        $html .= '<div class="business-poll-bar-fill" style="width:' . $pct . '%"></div>';
        $html .= '<span class="business-poll-bar-label">' . $opt_text . '</span>';
        $html .= '</div>';
        $html .= '<span class="business-poll-bar-pct">' . $pct . '%</span>';
        $html .= '</div>';
    }

    $html .= '<p class="business-poll-total">' . $total_votes . ' vote' . ($total_votes !== 1 ? 's' : '') . '</p>';
    $html .= '</div>';
    return $html;
}

function craftcrawl_render_business_post(array $post): string {
    $post_id = (int) $post['id'];
    $post_type = $post['post_type'] ?? 'post';
    $title = htmlspecialchars($post['title'] ?? '', ENT_QUOTES, 'UTF-8');
    $body = $post['body'] ?? '';
    $created_at = htmlspecialchars(date('M j, Y', strtotime($post['created_at'] ?? '')), ENT_QUOTES, 'UTF-8');

    $html = '<article class="business-post-card' . ($post_type === 'poll' ? ' business-poll-card' : '') . '" data-post-id="' . $post_id . '">';
    $html .= '<div class="business-post-meta">';
    $html .= '<span class="business-post-type-badge">' . ($post_type === 'poll' ? 'Poll' : 'Post') . '</span>';
    $html .= '<time>' . $created_at . '</time>';
    $html .= '</div>';
    $html .= '<p class="business-post-title">' . $title . '</p>';

    if ($body !== '') {
        $html .= '<p class="business-post-body">' . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) . '</p>';
    }

    $item_key = htmlspecialchars($post['item_key'] ?? ('business_post:' . $post_id), ENT_QUOTES, 'UTF-8');

    if ($post_type === 'poll') {
        $options = $post['options'] ?? [];
        $user_voted = isset($post['user_voted_option_id']) ? (int) $post['user_voted_option_id'] : null;
        $total_votes = (int) ($post['total_votes'] ?? 0);
        $ends_at = $post['ends_at'] ?? null;
        $is_expired = $ends_at !== null && strtotime($ends_at) < time();

        if ($ends_at !== null) {
            if ($is_expired) {
                $html .= '<p class="business-poll-expiry is-closed">Poll closed</p>';
            } else {
                $closes_label = htmlspecialchars(date('M j, g:i A', strtotime($ends_at)), ENT_QUOTES, 'UTF-8');
                $html .= '<p class="business-poll-expiry">Closes ' . $closes_label . '</p>';
            }
        }

        if ($user_voted !== null) {
            $html .= craftcrawl_render_poll_results($options, $user_voted, $total_votes);
        } else {
            $html .= '<div class="business-poll-vote-options" data-feed-poll-section>';
            foreach ($options as $option) {
                $opt_id = (int) $option['id'];
                $opt_text = htmlspecialchars($option['option_text'] ?? '', ENT_QUOTES, 'UTF-8');
                $disabled = $is_expired ? ' disabled' : '';
                $html .= '<button type="button" class="business-poll-option-btn" data-feed-poll-vote data-item-key="' . $item_key . '" data-option-id="' . $opt_id . '"' . $disabled . '>' . $opt_text . '</button>';
            }
            $html .= '</div>';
        }
    }
    $comment_count = (int) ($post['comment_count'] ?? 0);
    $reactions = $post['reactions'] ?? [];
    $reaction_map = [];
    foreach ($reactions as $r) {
        $reaction_map[$r['type']] = $r;
    }

    $comment_label = $comment_count > 0 ? (string) $comment_count : '';
    $html .= '<div class="feed-action-row">';
    $html .= '<div class="feed-primary-actions">';
    $html .= '<button type="button" class="feed-comments-link" data-comments-sheet-trigger data-item-key="' . $item_key . '" aria-label="Show comments">';
    $html .= '<span class="feed-comments-icon" aria-hidden="true"></span>';
    if ($comment_label !== '') {
        $html .= '<span class="feed-comment-count">' . $comment_label . '</span>';
    }
    $html .= '</button>';
    $html .= '</div>';
    $html .= '<div class="feed-reactions">';
    foreach ([
        'heart' => [
            'icon' => '<span class="feed-reaction-icon feed-reaction-icon-heart" aria-hidden="true"></span>',
            'label' => 'Like this post',
            'icon_is_html' => true,
        ],
        'cheers' => [
            'icon' => '<svg class="feed-reaction-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-miterlimit="10" stroke-width="1.91"><path d="M17.75,6.27a1.9,1.9,0,0,1-.95,1.65V7.23H12a1,1,0,0,0-.95.95V9.61a1.44,1.44,0,1,1-2.87,0V8.18a.94.94,0,0,0-.95-.95H5.34v.69a1.9,1.9,0,0,1-1-1.65A1.92,1.92,0,0,1,6.3,4.36,1.91,1.91,0,0,1,8.2,2.45a1.93,1.93,0,0,1,1.07.33,1.9,1.9,0,0,1,3.59,0,2,2,0,0,1,1.07-.33,1.92,1.92,0,0,1,1.91,1.91A1.92,1.92,0,0,1,17.75,6.27Z"/><path d="M16.8,7.23V20.59a1.91,1.91,0,0,1-1.91,1.91H7.25a1.91,1.91,0,0,1-1.91-1.91V7.23H7.25a.94.94,0,0,1,.95.95V9.61a1.44,1.44,0,1,0,2.87,0V8.18A1,1,0,0,1,12,7.23Z"/><path d="M16.8,10.09H18.7A1.91,1.91,0,0,1,20.61,12v3.82a1.91,1.91,0,0,1-1.91,1.91H16.8a0,0,0,0,1,0,0V10.09A0,0,0,0,1,16.8,10.09Z"/></svg>',
            'label' => 'Say cheers to this post',
            'icon_is_html' => true,
        ],
        'nice_find' => [
            'icon' => '<svg class="feed-reaction-icon" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" clip-rule="evenodd" d="M10.0284 1.11813C9.69728 1.2952 9.53443 1.61638 9.49957 1.97965C9.48456 2.15538 9.46201 2.32986 9.43136 2.50363C9.3663 2.87248 9.24303 3.3937 9.01205 3.98313C8.5513 5.15891 7.67023 6.58926 5.96985 7.65195C3.57358 9.14956 2.68473 12.5146 3.06456 15.527C3.45234 18.6026 5.20871 21.7903 8.68375 22.9486C9.03 23.0641 9.41163 22.9817 9.67942 22.7337C10.0071 22.4303 10.0238 22.0282 9.94052 21.6223C9.87941 21.3244 9.74999 20.5785 9.74999 19.6875C9.74999 19.3992 9.76332 19.1034 9.79413 18.8068C10.3282 20.031 11.0522 20.9238 11.7758 21.5623C12.8522 22.5121 13.8694 22.8574 14.1722 22.9466C14.402 23.0143 14.6462 23.0185 14.8712 22.9284C17.5283 21.8656 19.2011 20.4232 20.1356 18.7742C21.068 17.1288 21.1993 15.3939 20.9907 13.8648C20.7833 12.3436 20.2354 10.9849 19.7537 10.0215C19.3894 9.29292 19.0534 8.77091 18.8992 8.54242C18.7101 8.26241 18.4637 8.04626 18.1128 8.00636C17.8332 7.97456 17.5531 8.06207 17.3413 8.24739L15.7763 9.61686C15.9107 7.44482 15.1466 5.61996 14.1982 4.24472C13.5095 3.24609 12.7237 2.47913 12.1151 1.96354C11.8094 1.70448 11.5443 1.50549 11.3525 1.36923C11.2564 1.30103 11.1784 1.24831 11.1224 1.21142C10.7908 0.99291 10.3931 0.923125 10.0284 1.11813ZM7.76396 20.256C7.75511 20.0744 7.74999 19.8842 7.74999 19.6875C7.75 18.6347 7.89677 17.3059 8.47802 16.0708C8.67271 15.6572 8.91614 15.254 9.21914 14.8753C9.47408 14.5566 9.89709 14.4248 10.2879 14.5423C10.6787 14.6598 10.959 15.003 10.9959 15.4094C11.2221 17.8977 12.2225 19.2892 13.099 20.0626C13.5469 20.4579 13.979 20.7056 14.292 20.8525C15.5 20.9999 17.8849 18.6892 18.3955 17.7882C19.0569 16.6211 19.1756 15.356 19.0091 14.1351C18.8146 12.7092 18.2304 11.3897 17.7656 10.5337L14.6585 13.2525C14.3033 13.5634 13.779 13.5835 13.401 13.3008C13.023 13.018 12.8942 12.5095 13.092 12.0809C14.4081 9.22933 13.655 6.97987 12.5518 5.38019C12.1138 4.74521 11.6209 4.21649 11.18 3.80695C11.0999 4.088 10.9997 4.39262 10.8742 4.71284C10.696 5.16755 10.4662 5.65531 10.1704 6.15187C9.50801 7.26379 8.51483 8.41987 7.02982 9.34797C5.57752 10.2556 4.71646 12.6406 5.04885 15.2768C5.29944 17.2643 6.20241 19.1244 7.76396 20.256Z"/></svg>',
            'label' => 'Say this post is fire',
            'icon_is_html' => true,
        ],
        'yuck' => [
            'icon' => '<span class="feed-reaction-icon feed-reaction-icon-yuck" aria-hidden="true"></span>',
            'label' => 'Say yuck to this post',
            'icon_is_html' => true,
        ],
        'want_to_go' => [
            'icon' => '<svg class="feed-reaction-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M15.9894 4.9502L16.52 4.42014L15.9894 4.9502ZM19.0716 8.03562L18.541 8.56568L19.0716 8.03562ZM8.73837 19.429L8.20777 19.9591L8.73837 19.429ZM4.62169 15.3081L5.15229 14.7781L4.62169 15.3081ZM17.5669 14.9943L17.3032 14.2922L17.5669 14.9943ZM15.6498 15.7146L15.9136 16.4167H15.9136L15.6498 15.7146ZM8.3322 8.38177L7.62798 8.12375L8.3322 8.38177ZM9.02665 6.48636L9.73087 6.74438V6.74438L9.02665 6.48636ZM5.84504 10.6735L6.04438 11.3965L5.84504 10.6735ZM7.30167 10.1351L6.86346 9.52646L6.86346 9.52646L7.30167 10.1351ZM7.67582 9.79038L8.24665 10.2768H8.24665L7.67582 9.79038ZM14.251 16.3805L14.742 16.9475L14.742 16.9475L14.251 16.3805ZM13.3806 18.2012L12.6574 18.0022V18.0022L13.3806 18.2012ZM13.9169 16.7466L13.3075 16.3094L13.3075 16.3094L13.9169 16.7466ZM2.71846 12.7552L1.96848 12.76L1.96848 12.76L2.71846 12.7552ZM2.93045 11.9521L2.28053 11.5778H2.28053L2.93045 11.9521ZM11.3052 21.3431L11.3064 20.5931H11.3064L11.3052 21.3431ZM12.0933 21.1347L11.7215 20.4833L11.7215 20.4833L12.0933 21.1347ZM11.6973 2.03606L11.8588 2.76845L11.6973 2.03606ZM1.4694 21.4699C1.17666 21.763 1.1769 22.2379 1.46994 22.5306C1.76298 22.8233 2.23786 22.8231 2.5306 22.5301L1.4694 21.4699ZM7.18383 17.8721C7.47657 17.5791 7.47633 17.1042 7.18329 16.8114C6.89024 16.5187 6.41537 16.5189 6.12263 16.812L7.18383 17.8721ZM15.4588 5.48026L18.541 8.56568L19.6022 7.50556L16.52 4.42014L15.4588 5.48026ZM9.26897 18.8989L5.15229 14.7781L4.09109 15.8382L8.20777 19.9591L9.26897 18.8989ZM17.3032 14.2922L15.386 15.0125L15.9136 16.4167L17.8307 15.6964L17.3032 14.2922ZM9.03642 8.63979L9.73087 6.74438L8.32243 6.22834L7.62798 8.12375L9.03642 8.63979ZM6.04438 11.3965C6.75583 11.2003 7.29719 11.0625 7.73987 10.7438L6.86346 9.52646C6.69053 9.65097 6.46601 9.72428 5.6457 9.95044L6.04438 11.3965ZM7.62798 8.12375C7.33502 8.92332 7.24338 9.14153 7.10499 9.30391L8.24665 10.2768C8.60041 9.86175 8.7823 9.33337 9.03642 8.63979L7.62798 8.12375ZM7.73987 10.7438C7.92696 10.6091 8.09712 10.4523 8.24665 10.2768L7.10499 9.30391C7.0337 9.38757 6.9526 9.46229 6.86346 9.52646L7.73987 10.7438ZM15.386 15.0125C14.697 15.2714 14.1716 15.4571 13.76 15.8135L14.742 16.9475C14.9028 16.8082 15.1192 16.7152 15.9136 16.4167L15.386 15.0125ZM14.1037 18.4001C14.329 17.5813 14.4021 17.3569 14.5263 17.1838L13.3075 16.3094C12.9902 16.7517 12.8529 17.2919 12.6574 18.0022L14.1037 18.4001ZM13.76 15.8135C13.5903 15.9605 13.4384 16.1269 13.3075 16.3094L14.5263 17.1838C14.5887 17.0968 14.6611 17.0175 14.742 16.9475L13.76 15.8135ZM5.15229 14.7781C4.50615 14.1313 4.06799 13.691 3.78366 13.3338C3.49835 12.9753 3.46889 12.8201 3.46845 12.7505L1.96848 12.76C1.97215 13.3422 2.26127 13.8297 2.61002 14.2679C2.95976 14.7073 3.47115 15.2176 4.09109 15.8382L5.15229 14.7781ZM5.6457 9.95044C4.80048 10.1835 4.10396 10.3743 3.58296 10.5835C3.06341 10.792 2.57116 11.0732 2.28053 11.5778L3.58038 12.3264C3.615 12.2663 3.71693 12.146 4.1418 11.9755C4.56523 11.8055 5.16337 11.6394 6.04438 11.3965L5.6457 9.95044ZM3.46845 12.7505C3.46751 12.6016 3.50616 12.4553 3.58038 12.3264L2.28053 11.5778C2.07354 11.9372 1.96586 12.3452 1.96848 12.76L3.46845 12.7505ZM8.20777 19.9591C8.83164 20.5836 9.34464 21.0987 9.78647 21.4506C10.227 21.8015 10.7179 22.0922 11.3041 22.0931L11.3064 20.5931C11.2369 20.593 11.0814 20.5644 10.721 20.2773C10.3618 19.9912 9.91923 19.5499 9.26897 18.8989L8.20777 19.9591ZM12.6574 18.0022C12.4133 18.8897 12.2462 19.4924 12.0751 19.9188C11.9033 20.3467 11.7821 20.4487 11.7215 20.4833L12.465 21.7861C12.974 21.4956 13.2573 21.0004 13.4671 20.4775C13.6776 19.9532 13.8694 19.2516 14.1037 18.4001L12.6574 18.0022ZM11.3041 22.0931C11.7112 22.0937 12.1114 21.9879 12.465 21.7861L11.7215 20.4833C11.595 20.5555 11.4519 20.5933 11.3064 20.5931L11.3041 22.0931ZM18.541 8.56568C19.6045 9.63022 20.3403 10.3695 20.7917 10.9788C21.2353 11.5774 21.2863 11.8959 21.2321 12.1464L22.6982 12.4634C22.8881 11.5854 22.5382 10.8162 21.9969 10.0857C21.4635 9.36592 20.6305 8.53486 19.6022 7.50556L18.541 8.56568ZM17.8307 15.6964C19.1921 15.1849 20.294 14.773 21.0771 14.3384C21.8718 13.8973 22.5083 13.3416 22.6982 12.4634L21.2321 12.1464C21.178 12.3968 21.0001 12.6655 20.3491 13.0268C19.6865 13.3946 18.7112 13.7632 17.3032 14.2922L17.8307 15.6964ZM16.52 4.42014C15.4841 3.3832 14.6481 2.54353 13.9246 2.00638C13.1908 1.46165 12.4175 1.10912 11.5357 1.30367L11.8588 2.76845C12.1086 2.71335 12.4277 2.7633 13.0304 3.21075C13.6433 3.66579 14.3876 4.40801 15.4588 5.48026L16.52 4.42014ZM9.73087 6.74438C10.2525 5.32075 10.6161 4.33403 10.9812 3.66315C11.3402 3.00338 11.609 2.82357 11.8588 2.76845L11.5357 1.30367C10.654 1.49819 10.1005 2.14332 9.66362 2.94618C9.23278 3.73793 8.82688 4.85154 8.32243 6.22834L9.73087 6.74438ZM2.5306 22.5301L7.18383 17.8721L6.12263 16.812L1.4694 21.4699L2.5306 22.5301Z"/></svg>',
            'label' => 'Want to Go',
            'icon_is_html' => true,
        ],
    ] as $reaction_type => $reaction_label) {
        $r = $reaction_map[$reaction_type] ?? ['count' => 0, 'reacted' => false];
        $active_class = $r['reacted'] ? ' is-active' : '';
        $reaction_count = (int) $r['count'];
        $count_hidden = $reaction_count > 0 ? '' : ' hidden';
        $html .= '<button type="button" class="' . $active_class . '" data-feed-reaction data-item-key="' . $item_key . '" data-reaction-type="' . $reaction_type . '" data-reaction-count="' . $reaction_count . '" aria-label="' . htmlspecialchars($reaction_label['label'], ENT_QUOTES, 'UTF-8') . '" aria-pressed="' . ($r['reacted'] ? 'true' : 'false') . '">';
        $icon_html = !empty($reaction_label['icon_is_html']) ? $reaction_label['icon'] : htmlspecialchars($reaction_label['icon'], ENT_QUOTES, 'UTF-8');
        $html .= $icon_html . '<span class="feed-reaction-count"' . $count_hidden . '>' . ($reaction_count > 0 ? $reaction_count : '') . '</span>';
        $html .= '</button>';
    }
    $html .= '</div>';
    $html .= '</div>';

    $html .= '</article>';
    return $html;
}

function craftcrawl_load_posts_with_poll_data(mysqli $conn, int $user_id, array $posts_raw): array {
    if (empty($posts_raw)) {
        return [];
    }

    $poll_post_ids = [];
    foreach ($posts_raw as $p) {
        if ($p['post_type'] === 'poll') {
            $poll_post_ids[] = (int) $p['id'];
        }
    }

    $poll_options_by_post = [];
    $user_votes = [];

    if (!empty($poll_post_ids)) {
        $ids_ph = implode(',', array_fill(0, count($poll_post_ids), '?'));
        $id_types = str_repeat('i', count($poll_post_ids));

        $opt_stmt = $conn->prepare("
            SELECT bpo.id, bpo.post_id, bpo.option_text, bpo.sort_order, COUNT(bpv.id) AS vote_count
            FROM business_poll_options bpo
            LEFT JOIN business_poll_votes bpv ON bpv.option_id = bpo.id
            WHERE bpo.post_id IN ($ids_ph)
            GROUP BY bpo.id
            ORDER BY bpo.sort_order
        ");
        $opt_params = [$id_types];
        foreach ($poll_post_ids as $idx => $pid) {
            $opt_params[] = &$poll_post_ids[$idx];
        }
        call_user_func_array([$opt_stmt, 'bind_param'], $opt_params);
        $opt_stmt->execute();
        $opt_result = $opt_stmt->get_result();
        while ($opt = $opt_result->fetch_assoc()) {
            $poll_options_by_post[(int) $opt['post_id']][] = $opt;
        }

        $vote_stmt = $conn->prepare("
            SELECT post_id, option_id
            FROM business_poll_votes
            WHERE user_id=? AND post_id IN ($ids_ph)
        ");
        $vote_params = ['i' . $id_types, &$user_id];
        foreach ($poll_post_ids as $idx => $pid) {
            $vote_params[] = &$poll_post_ids[$idx];
        }
        call_user_func_array([$vote_stmt, 'bind_param'], $vote_params);
        $vote_stmt->execute();
        $vote_result = $vote_stmt->get_result();
        while ($vote = $vote_result->fetch_assoc()) {
            $user_votes[(int) $vote['post_id']] = (int) $vote['option_id'];
        }
    }

    // Build item keys for reactions + comment counts
    $item_keys = [];
    foreach ($posts_raw as $p) {
        $item_keys[] = 'business_post:' . (int) $p['id'];
    }

    $reactions_by_key = [];
    $comment_counts_by_key = [];

    $key_ph = implode(',', array_fill(0, count($item_keys), '?'));
    $key_types = str_repeat('s', count($item_keys));

    $react_keys = $item_keys;
    $react_stmt = $conn->prepare("
        SELECT feed_item_key, reaction_type, user_id
        FROM feed_reactions
        WHERE feed_item_key IN ($key_ph)
        ORDER BY createdAt ASC, id ASC
    ");
    $react_params = [$key_types];
    foreach ($react_keys as $idx => $k) {
        $react_params[] = &$react_keys[$idx];
    }
    call_user_func_array([$react_stmt, 'bind_param'], $react_params);
    $react_stmt->execute();
    $react_result = $react_stmt->get_result();
    while ($react = $react_result->fetch_assoc()) {
        $key = $react['feed_item_key'];
        $type = $react['reaction_type'];
        $reactor_id = (int) $react['user_id'];
        if (!isset($reactions_by_key[$key][$type])) {
            $reactions_by_key[$key][$type] = ['type' => $type, 'count' => 0, 'reacted' => false];
        }
        $reactions_by_key[$key][$type]['count']++;
        if ($reactor_id === $user_id) {
            $reactions_by_key[$key][$type]['reacted'] = true;
        }
    }

    $comment_keys = $item_keys;
    $comment_stmt = $conn->prepare("
        SELECT feed_item_key, COUNT(*) AS total
        FROM feed_comments
        WHERE deletedAt IS NULL AND feed_item_key IN ($key_ph)
        GROUP BY feed_item_key
    ");
    $comment_params = [$key_types];
    foreach ($comment_keys as $idx => $k) {
        $comment_params[] = &$comment_keys[$idx];
    }
    call_user_func_array([$comment_stmt, 'bind_param'], $comment_params);
    $comment_stmt->execute();
    $comment_result = $comment_stmt->get_result();
    while ($cc = $comment_result->fetch_assoc()) {
        $comment_counts_by_key[$cc['feed_item_key']] = (int) $cc['total'];
    }

    $posts = [];
    foreach ($posts_raw as $post) {
        $pid = (int) $post['id'];
        $item_key = 'business_post:' . $pid;

        if ($post['post_type'] === 'poll') {
            $opts = $poll_options_by_post[$pid] ?? [];
            $total_votes = 0;
            foreach ($opts as $o) {
                $total_votes += (int) $o['vote_count'];
            }
            $post['options'] = $opts;
            $post['user_voted_option_id'] = $user_votes[$pid] ?? null;
            $post['total_votes'] = $total_votes;
        }

        $post['item_key'] = $item_key;
        $post['reactions'] = array_values($reactions_by_key[$item_key] ?? []);
        $post['comment_count'] = $comment_counts_by_key[$item_key] ?? 0;
        $posts[] = $post;
    }

    return $posts;
}
