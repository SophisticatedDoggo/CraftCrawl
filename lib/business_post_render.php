<?php

function craftcrawl_render_poll_results(array $options, int $user_voted_option_id, int $total_votes): string {
    $html = '<div class="business-poll-results" data-poll-results>';

    foreach ($options as $option) {
        $opt_id = (int) $option['id'];
        $opt_text = htmlspecialchars($option['option_text'] ?? '', ENT_QUOTES, 'UTF-8');
        $vote_count = (int) ($option['vote_count'] ?? 0);
        $pct = $total_votes > 0 ? (int) round(($vote_count / $total_votes) * 100) : 0;
        $is_voted = $user_voted_option_id === $opt_id;

        $html .= '<div class="business-poll-result' . ($is_voted ? ' is-voted' : '') . '">';
        $html .= '<div class="business-poll-result-label"><span>' . $opt_text . '</span>';
        if ($is_voted) {
            $html .= '<span class="business-poll-voted-indicator">Your vote</span>';
        }
        $html .= '</div>';
        $html .= '<div class="business-poll-bar"><span style="width:' . $pct . '%"></span></div>';
        $html .= '<span class="business-poll-result-count">' . $pct . '% (' . $vote_count . ')</span>';
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

        if ($user_voted !== null) {
            $html .= craftcrawl_render_poll_results($options, $user_voted, $total_votes);
        } else {
            $html .= '<div class="business-poll-vote-options" data-poll-options data-post-id="' . $post_id . '">';
            foreach ($options as $option) {
                $opt_id = (int) $option['id'];
                $opt_text = htmlspecialchars($option['option_text'] ?? '', ENT_QUOTES, 'UTF-8');
                $html .= '<button type="button" class="business-poll-option-btn" data-vote-option data-option-id="' . $opt_id . '">' . $opt_text . '</button>';
            }
            $html .= '</div>';
            $html .= '<p class="business-poll-total">' . $total_votes . ' vote' . ($total_votes !== 1 ? 's' : '') . '</p>';
        }
    }

    $html .= '</article>';
    return $html;
}

function craftcrawl_load_posts_with_poll_data(mysqli $conn, int $user_id, array $posts_raw): array {
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

    $posts = [];

    foreach ($posts_raw as $post) {
        $pid = (int) $post['id'];
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
        $posts[] = $post;
    }

    return $posts;
}
