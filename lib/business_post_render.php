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
            // Not voted yet — show option buttons (disabled if expired so they can't vote)
            $html .= '<div class="business-poll-vote-options" data-poll-options data-post-id="' . $post_id . '">';
            foreach ($options as $option) {
                $opt_id = (int) $option['id'];
                $opt_text = htmlspecialchars($option['option_text'] ?? '', ENT_QUOTES, 'UTF-8');
                $disabled = $is_expired ? ' disabled' : '';
                $html .= '<button type="button" class="business-poll-option-btn" data-vote-option data-option-id="' . $opt_id . '"' . $disabled . '>' . $opt_text . '</button>';
            }
            $html .= '</div>';
        }
    }

    $item_key = htmlspecialchars($post['item_key'] ?? ('business_post:' . $post_id), ENT_QUOTES, 'UTF-8');
    $comment_count = (int) ($post['comment_count'] ?? 0);
    $reactions = $post['reactions'] ?? [];
    $reaction_map = [];
    foreach ($reactions as $r) {
        $reaction_map[$r['type']] = $r;
    }

    $comment_label = $comment_count > 0 ? (string) $comment_count : '';
    $html .= '<div class="feed-action-row">';
    $html .= '<div class="feed-primary-actions">';
    $html .= '<a class="feed-comments-link" href="user/feed_post.php?item=' . $item_key . '" aria-label="Comments">';
    $html .= '<span aria-hidden="true">💬</span>';
    if ($comment_label !== '') {
        $html .= '<span>' . $comment_label . '</span>';
    }
    $html .= '</a>';
    $html .= '</div>';
    $html .= '<div class="feed-reactions">';
    foreach (['cheers' => '🍻 Cheers', 'want_to_go' => '📍 Want to Go'] as $reaction_type => $reaction_label) {
        $r = $reaction_map[$reaction_type] ?? ['count' => 0, 'reacted' => false];
        $active_class = $r['reacted'] ? ' is-active' : '';
        $count_text = $r['count'] > 0 ? ' ' . $r['count'] : '';
        $html .= '<button type="button" class="' . $active_class . '" data-post-reaction data-item-key="' . $item_key . '" data-reaction-type="' . $reaction_type . '">';
        $html .= htmlspecialchars($reaction_label, ENT_QUOTES, 'UTF-8') . $count_text;
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
