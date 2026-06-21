ALTER TABLE xp_log
    MODIFY source_type ENUM('first_time_visit', 'repeat_visit', 'review', 'badge', 'quest', 'quest_chain') NOT NULL;

CREATE TABLE IF NOT EXISTS quest_chains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT NOT NULL,
    template_key VARCHAR(64) NOT NULL,
    chain_name VARCHAR(100) NOT NULL,
    chain_description VARCHAR(500),
    step_count TINYINT NOT NULL,
    xp_reward INT NOT NULL DEFAULT 0,
    status ENUM('available', 'active', 'completed', 'abandoned') NOT NULL DEFAULT 'available',
    user_latitude DECIMAL(9,6) NOT NULL,
    user_longitude DECIMAL(9,6) NOT NULL,
    generation_batch VARCHAR(40) NOT NULL,
    activatedAt DATETIME,
    completedAt DATETIME,
    abandonedAt DATETIME,
    createdAt DATETIME NOT NULL,
    KEY idx_quest_chains_owner_status (owner_user_id, status),
    KEY idx_quest_chains_batch (owner_user_id, generation_batch),
    CONSTRAINT fk_quest_chains_ownerUserId FOREIGN KEY (owner_user_id)
    REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS quest_chain_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chain_id INT NOT NULL,
    step_order TINYINT NOT NULL,
    action_type ENUM('checkin', 'review', 'event_want_to_go', 'feed_reaction') NOT NULL,
    location_id INT NOT NULL,
    location_name VARCHAR(255) NOT NULL,
    location_city VARCHAR(100),
    location_state VARCHAR(2),
    event_id INT,
    UNIQUE KEY unique_chain_step_order (chain_id, step_order),
    KEY idx_quest_chain_steps_chain (chain_id),
    KEY idx_quest_chain_steps_location (location_id),
    CONSTRAINT fk_quest_chain_steps_chainId FOREIGN KEY (chain_id)
    REFERENCES quest_chains(id),
    CONSTRAINT fk_quest_chain_steps_locationId FOREIGN KEY (location_id)
    REFERENCES locations(id)
);

CREATE TABLE IF NOT EXISTS quest_chain_step_completions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chain_id INT NOT NULL,
    step_id INT NOT NULL,
    user_id INT NOT NULL,
    completedAt DATETIME NOT NULL,
    UNIQUE KEY unique_chain_step_user (step_id, user_id),
    KEY idx_chain_step_completions_chain_user (chain_id, user_id),
    CONSTRAINT fk_chain_step_completion_chainId FOREIGN KEY (chain_id)
    REFERENCES quest_chains(id),
    CONSTRAINT fk_chain_step_completion_stepId FOREIGN KEY (step_id)
    REFERENCES quest_chain_steps(id),
    CONSTRAINT fk_chain_step_completion_userId FOREIGN KEY (user_id)
    REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS quest_chain_completions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chain_id INT NOT NULL,
    user_id INT NOT NULL,
    xp_awarded INT NOT NULL DEFAULT 0,
    completedAt DATETIME NOT NULL,
    UNIQUE KEY unique_chain_completion_user (chain_id, user_id),
    KEY idx_quest_chain_completions_user (user_id),
    CONSTRAINT fk_chain_completion_chainId FOREIGN KEY (chain_id)
    REFERENCES quest_chains(id),
    CONSTRAINT fk_chain_completion_userId FOREIGN KEY (user_id)
    REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS quest_chain_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chain_id INT NOT NULL,
    user_id INT NOT NULL,
    invited_by_user_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined', 'left') NOT NULL DEFAULT 'pending',
    joinedAt DATETIME,
    leftAt DATETIME,
    createdAt DATETIME NOT NULL,
    UNIQUE KEY unique_chain_member (chain_id, user_id),
    KEY idx_quest_chain_members_user_status (user_id, status),
    KEY idx_quest_chain_members_chain (chain_id, status),
    CONSTRAINT fk_chain_member_chainId FOREIGN KEY (chain_id)
    REFERENCES quest_chains(id),
    CONSTRAINT fk_chain_member_userId FOREIGN KEY (user_id)
    REFERENCES users(id),
    CONSTRAINT fk_chain_member_invitedByUserId FOREIGN KEY (invited_by_user_id)
    REFERENCES users(id)
);
