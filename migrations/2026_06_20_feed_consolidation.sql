ALTER TABLE user_visits
    ADD COLUMN caption VARCHAR(360) DEFAULT NULL AFTER photo_id;

ALTER TABLE user_badges
    ADD COLUMN visit_id INT DEFAULT NULL AFTER user_id,
    ADD KEY idx_user_badges_visit_id (visit_id),
    ADD CONSTRAINT fk_user_badges_visitId FOREIGN KEY (visit_id) REFERENCES user_visits(id);

ALTER TABLE user_quest_completions
    ADD COLUMN visit_id INT DEFAULT NULL AFTER user_id,
    ADD KEY idx_user_quest_completions_visit_id (visit_id),
    ADD CONSTRAINT fk_user_quest_completions_visitId FOREIGN KEY (visit_id) REFERENCES user_visits(id);
