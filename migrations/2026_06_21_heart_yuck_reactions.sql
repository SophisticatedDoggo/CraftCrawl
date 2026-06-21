DELETE FROM feed_reactions
WHERE reaction_type = '';

ALTER TABLE feed_reactions
    MODIFY COLUMN reaction_type ENUM(
        'cheers',
        'nice_find',
        'want_to_go',
        'good_review',
        'great_spot',
        'trophy',
        'heart',
        'yuck'
    ) NOT NULL;
