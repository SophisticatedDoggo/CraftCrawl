ALTER TABLE feed_reactions
    MODIFY COLUMN reaction_type ENUM('cheers','nice_find','want_to_go','good_review','great_spot','trophy') NOT NULL;
