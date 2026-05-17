-- USE craft_crawl;

ALTER TABLE users
    ADD COLUMN level INT NOT NULL DEFAULT 1 AFTER total_xp,
    ADD COLUMN level_xp INT NOT NULL DEFAULT 0 AFTER level;

ALTER TABLE xp_log
    ADD COLUMN level_before INT NOT NULL DEFAULT 1 AFTER description,
    ADD COLUMN level_after INT NOT NULL DEFAULT 1 AFTER level_before,
    ADD COLUMN level_xp_after INT NOT NULL DEFAULT 0 AFTER level_after;

DROP PROCEDURE IF EXISTS craftcrawl_backfill_level_state;

DELIMITER //

CREATE PROCEDURE craftcrawl_backfill_level_state()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE current_user_id INT;
    DECLARE current_total_xp INT;
    DECLARE current_level INT;
    DECLARE current_level_xp INT;
    DECLARE required_xp INT;
    DECLARE user_cursor CURSOR FOR SELECT id, total_xp FROM users ORDER BY id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN user_cursor;

    user_loop: LOOP
        FETCH user_cursor INTO current_user_id, current_total_xp;

        IF done = 1 THEN
            LEAVE user_loop;
        END IF;

        SET current_level = 1;
        SET current_level_xp = GREATEST(0, current_total_xp);

        level_loop: WHILE current_level < 100 DO
            SET required_xp = current_level * 100;

            IF current_level_xp < required_xp THEN
                LEAVE level_loop;
            END IF;

            SET current_level_xp = current_level_xp - required_xp;
            SET current_level = current_level + 1;
        END WHILE;

        IF current_level >= 100 THEN
            SET current_level_xp = 0;
        END IF;

        UPDATE users
        SET level = current_level,
            level_xp = current_level_xp
        WHERE id = current_user_id;
    END LOOP;

    CLOSE user_cursor;
END //

DELIMITER ;

CALL craftcrawl_backfill_level_state();

DROP PROCEDURE craftcrawl_backfill_level_state;

DROP PROCEDURE IF EXISTS craftcrawl_backfill_xp_log_levels;

DELIMITER //

CREATE PROCEDURE craftcrawl_backfill_xp_log_levels()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE current_xp_id INT;
    DECLARE current_user_id INT;
    DECLARE previous_user_id INT DEFAULT 0;
    DECLARE current_amount INT;
    DECLARE current_level INT DEFAULT 1;
    DECLARE current_level_xp INT DEFAULT 0;
    DECLARE before_level INT DEFAULT 1;
    DECLARE required_xp INT;
    DECLARE xp_cursor CURSOR FOR SELECT id, user_id, amount FROM xp_log ORDER BY user_id, createdAt, id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN xp_cursor;

    xp_loop: LOOP
        FETCH xp_cursor INTO current_xp_id, current_user_id, current_amount;

        IF done = 1 THEN
            LEAVE xp_loop;
        END IF;

        IF previous_user_id <> current_user_id THEN
            SET current_level = 1;
            SET current_level_xp = 0;
            SET previous_user_id = current_user_id;
        END IF;

        SET before_level = current_level;

        IF current_level < 100 THEN
            SET current_level_xp = current_level_xp + GREATEST(0, current_amount);

            replay_level_loop: WHILE current_level < 100 DO
                SET required_xp = current_level * 100;

                IF current_level_xp < required_xp THEN
                    LEAVE replay_level_loop;
                END IF;

                SET current_level_xp = current_level_xp - required_xp;
                SET current_level = current_level + 1;
            END WHILE;
        END IF;

        IF current_level >= 100 THEN
            SET current_level_xp = 0;
        END IF;

        UPDATE xp_log
        SET level_before = before_level,
            level_after = current_level,
            level_xp_after = current_level_xp
        WHERE id = current_xp_id;
    END LOOP;

    CLOSE xp_cursor;
END //

DELIMITER ;

CALL craftcrawl_backfill_xp_log_levels();

DROP PROCEDURE craftcrawl_backfill_xp_log_levels;
