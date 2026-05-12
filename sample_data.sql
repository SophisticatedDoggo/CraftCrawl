USE craft_crawl;

-- Sample user password: Password1!
INSERT INTO users (id, fName, lName, email, password_hash, total_xp, level, level_xp, auto_accept_friend_invites, friendsSeenAt, createdAt, emailVerifiedAt) VALUES
(1, 'Avery', 'Miller', 'avery@example.com', '$2y$10$ySMFSLimwbmfB.OO4Ytv9OkVqummwncw17PVNXv8KtmoSy6iKgn5.', 225, 2, 125, FALSE, '2026-05-09 09:00:00', '2026-05-01 09:00:00', '2026-05-01 09:05:00'),
(2, 'Jordan', 'Parker', 'jordan@example.com', '$2y$10$ySMFSLimwbmfB.OO4Ytv9OkVqummwncw17PVNXv8KtmoSy6iKgn5.', 325, 3, 25, TRUE, NULL, '2026-05-01 09:10:00', '2026-05-01 09:15:00'),
(3, 'Taylor', 'Reed', 'taylor@example.com', '$2y$10$ySMFSLimwbmfB.OO4Ytv9OkVqummwncw17PVNXv8KtmoSy6iKgn5.', 250, 2, 150, FALSE, NULL, '2026-05-01 09:20:00', '2026-05-01 09:25:00'),
(4, 'Morgan', 'Chen', 'morgan@example.com', '$2y$10$ySMFSLimwbmfB.OO4Ytv9OkVqummwncw17PVNXv8KtmoSy6iKgn5.', 100, 2, 0, FALSE, NULL, '2026-05-01 09:30:00', '2026-05-01 09:35:00'),
(5, 'Casey', 'Lopez', 'casey@example.com', '$2y$10$ySMFSLimwbmfB.OO4Ytv9OkVqummwncw17PVNXv8KtmoSy6iKgn5.', 0, 1, 0, TRUE, NULL, '2026-05-01 09:40:00', '2026-05-01 09:45:00'),
(6, 'Riley', 'Brooks', 'riley@example.com', '$2y$10$ySMFSLimwbmfB.OO4Ytv9OkVqummwncw17PVNXv8KtmoSy6iKgn5.', 0, 1, 0, FALSE, NULL, '2026-05-01 09:50:00', '2026-05-01 09:55:00');

INSERT INTO businesses (
    id,
    bName,
    bEmail,
    bPhone,
    password_hash,
    street_address,
    apt_suite,
    city,
    state,
    zip,
    latitude,
    longitude,
    bWebsite,
    bType,
    bAbout,
    createdAt,
    approved
) VALUES
(1, 'Iron Ridge Brewing', 'hello@ironridgebrewing.example', '724-555-0101', '$2y$10$samplehashsamplehashsamplehashsamplehashsamplehashsamplehash', '101 Main St', NULL, 'Greensburg', 'PA', '15601', 40.301459, -79.538929, 'https://ironridgebrewing.example', 'brewery', 'Neighborhood brewery with rotating IPAs, lagers, and a small pub menu.', '2026-05-01 10:00:00', TRUE),
(2, 'Maple Run Winery', 'visit@maplerunwinery.example', '724-555-0102', '$2y$10$samplehashsamplehashsamplehashsamplehashsamplehashsamplehash', '22 Vineyard Ln', NULL, 'Latrobe', 'PA', '15650', 40.321181, -79.379482, 'https://maplerunwinery.example', 'winery', 'Family-run winery pouring dry reds, fruit wines, and seasonal sangria.', '2026-05-01 10:05:00', TRUE),
(3, 'Keystone Barrel House', 'info@keystonebarrel.example', '724-555-0103', '$2y$10$samplehashsamplehashsamplehashsamplehashsamplehashsamplehash', '315 Depot St', NULL, 'Ligonier', 'PA', '15658', 40.243129, -79.238092, 'https://keystonebarrel.example', 'brewery', 'Small-batch beers brewed downtown with frequent food truck nights.', '2026-05-01 10:10:00', TRUE),
(4, 'Orchard Fork Cidery', 'cheers@orchardfork.example', '724-555-0104', '$2y$10$samplehashsamplehashsamplehashsamplehashsamplehashsamplehash', '75 Apple Rd', NULL, 'Mt Pleasant', 'PA', '15666', 40.148960, -79.541149, 'https://orchardfork.example', 'cidery', 'Dry, semi-sweet, and hopped ciders made from Pennsylvania apples.', '2026-05-01 10:15:00', TRUE),
(5, 'Summit Stillworks', 'tours@summitstillworks.example', '724-555-0105', '$2y$10$samplehashsamplehashsamplehashsamplehashsamplehashsamplehash', '8 Summit Ave', NULL, 'Donegal', 'PA', '15628', 40.112573, -79.382174, 'https://summitstillworks.example', 'distillery', 'Mountain-side distillery specializing in whiskey, gin, and cocktail flights.', '2026-05-01 10:20:00', TRUE),
(6, 'Honey Hollow Meadery', 'hello@honeyhollow.example', '724-555-0106', '$2y$10$samplehashsamplehashsamplehashsamplehashsamplehashsamplehash', '412 Meadow Dr', NULL, 'Murrysville', 'PA', '15668', 40.428401, -79.697546, 'https://honeyhollow.example', 'meadery', 'Modern meadery with traditional honey wines and sparkling session meads.', '2026-05-01 10:25:00', TRUE),
(7, 'Trailhead Tapworks', 'info@trailheadtapworks.example', '724-555-0107', '$2y$10$samplehashsamplehashsamplehashsamplehashsamplehashsamplehash', '64 Trailhead Way', NULL, 'Irwin', 'PA', '15642', 40.324515, -79.701984, 'https://trailheadtapworks.example', 'brewery', 'Casual brewery near the trail with crisp ales and a dog-friendly patio.', '2026-05-01 10:30:00', TRUE),
(8, 'Stone Bridge Cellars', 'tastings@stonebridgecellars.example', '724-555-0108', '$2y$10$samplehashsamplehashsamplehashsamplehashsamplehashsamplehash', '190 Bridge St', NULL, 'Scottdale', 'PA', '15683', 40.100350, -79.586983, 'https://stonebridgecellars.example', 'winery', 'Relaxed tasting room featuring local grapes and weekend acoustic music.', '2026-05-01 10:35:00', TRUE),
(9, 'Copper Kettle Distilling', 'hello@copperkettle.example', '724-555-0109', '$2y$10$samplehashsamplehashsamplehashsamplehashsamplehashsamplehash', '520 Industrial Blvd', NULL, 'Jeannette', 'PA', '15644', 40.328126, -79.615319, 'https://copperkettle.example', 'distillery', 'Craft spirits, classic cocktails, and tours of the production floor.', '2026-05-01 10:40:00', TRUE),
(10, 'Laurel Highlands Brewing', 'taproom@laurelhighlandsbrewing.example', '724-555-0110', '$2y$10$samplehashsamplehashsamplehashsamplehashsamplehashsamplehash', '88 Highland Ave', NULL, 'Latrobe', 'PA', '15650', 40.312896, -79.389799, 'https://laurelhighlandsbrewing.example', 'brewery', 'Highlands-inspired taproom with pale ales, stouts, and seasonal releases.', '2026-05-01 10:45:00', TRUE),
(11, 'Red Barn Cider Co.', 'info@redbarncider.example', '724-555-0111', '$2y$10$samplehashsamplehashsamplehashsamplehashsamplehashsamplehash', '17 Barn View Rd', NULL, 'Greensburg', 'PA', '15601', 40.285482, -79.503841, 'https://redbarncider.example', 'cidery', 'Farm-style cidery with picnic seating and rotating fruit blends.', '2026-05-01 10:50:00', TRUE),
(12, 'Oak & Ember Winery', 'events@oakember.example', '724-555-0112', '$2y$10$samplehashsamplehashsamplehashsamplehashsamplehashsamplehash', '244 Oak Ln', NULL, 'New Stanton', 'PA', '15672', 40.219036, -79.609773, 'https://oakember.example', 'winery', 'Cozy winery pouring barrel-aged reds and easy patio whites.', '2026-05-01 10:55:00', TRUE),
(13, 'Riverbend Brewhouse', 'hello@riverbendbrew.example', '724-555-0113', '$2y$10$samplehashsamplehashsamplehashsamplehashsamplehashsamplehash', '9 Water St', NULL, 'West Newton', 'PA', '15089', 40.209793, -79.766977, 'https://riverbendbrew.example', 'brewery', 'Riverside brewhouse with approachable beers and plenty of bike parking.', '2026-05-01 11:00:00', TRUE),
(14, 'Black Bear Spirits', 'info@blackbearspirits.example', '724-555-0114', '$2y$10$samplehashsamplehashsamplehashsamplehashsamplehashsamplehash', '301 Forest Rd', NULL, 'Ligonier', 'PA', '15658', 40.246639, -79.220255, 'https://blackbearspirits.example', 'distillery', 'Small distillery crafting rye, rum, and botanical spirits.', '2026-05-01 11:05:00', TRUE),
(15, 'Golden Hive Mead', 'taste@goldenhive.example', '724-555-0115', '$2y$10$samplehashsamplehashsamplehashsamplehashsamplehashsamplehash', '56 Clover Ct', NULL, 'Delmont', 'PA', '15626', 40.413972, -79.570762, 'https://goldenhive.example', 'meadery', 'Bright meads with citrus, berry, and spice-forward seasonal pours.', '2026-05-01 11:10:00', TRUE),
(16, 'Foundry Lane Brewing', 'contact@foundrylane.example', '724-555-0116', '$2y$10$samplehashsamplehashsamplehashsamplehashsamplehashsamplehash', '611 Foundry Ln', NULL, 'Jeannette', 'PA', '15644', 40.331777, -79.620411, 'https://foundrylane.example', 'brewery', 'Industrial taproom with hop-forward beers and trivia nights.', '2026-05-01 11:15:00', TRUE),
(17, 'Pine Hill Winery', 'hello@pinehillwine.example', '724-555-0117', '$2y$10$samplehashsamplehashsamplehashsamplehashsamplehashsamplehash', '700 Pine Hill Rd', NULL, 'Export', 'PA', '15632', 40.417305, -79.624408, 'https://pinehillwine.example', 'winery', 'Hilltop winery with scenic tastings and charcuterie boards.', '2026-05-01 11:20:00', TRUE),
(18, 'Switchback Cider Works', 'info@switchbackcider.example', '724-555-0118', '$2y$10$samplehashsamplehashsamplehashsamplehashsamplehashsamplehash', '38 Switchback Rd', NULL, 'Youngwood', 'PA', '15697', 40.240847, -79.577646, 'https://switchbackcider.example', 'cidery', 'Crisp cider house with dry farmhouse blends and seasonal releases.', '2026-05-01 11:25:00', TRUE),
(19, 'Old Mill Brewing', 'taproom@oldmillbrewing.example', '724-555-0119', '$2y$10$samplehashsamplehashsamplehashsamplehashsamplehashsamplehash', '4 Mill St', NULL, 'Scottdale', 'PA', '15683', 40.101783, -79.590184, 'https://oldmillbrewing.example', 'brewery', 'Historic mill taproom with classic styles and rotating guest food vendors.', '2026-05-01 11:30:00', TRUE),
(20, 'Ridge Road Reserve', 'visit@ridgeroadreserve.example', '724-555-0120', '$2y$10$samplehashsamplehashsamplehashsamplehashsamplehashsamplehash', '920 Ridge Rd', NULL, 'Murrysville', 'PA', '15668', 40.434917, -79.668459, 'https://ridgeroadreserve.example', 'winery', 'Quiet reserve-style winery with tastings, bottle sales, and sunset views.', '2026-05-01 11:35:00', TRUE);

INSERT INTO reviews (id, rating, user_id, business_id, notes, business_response, business_responseAt) VALUES
(1, 5, 1, 1, 'Great tap list and friendly staff.', 'Thanks for stopping in. We hope to see you again soon.', '2026-05-03 14:00:00'),
(2, 4, 2, 2, 'Loved the fruit wine flight.', NULL, NULL),
(3, 5, 3, 3, 'Cozy spot after walking around town.', NULL, NULL),
(4, 4, 4, 4, 'The dry cider was the standout.', NULL, NULL),
(5, 5, 1, 5, 'Excellent cocktail flight and tour.', 'Glad you enjoyed the tour.', '2026-05-04 11:30:00'),
(6, 4, 2, 6, 'Unique meads and relaxed seating.', NULL, NULL),
(7, 5, 3, 7, 'Dog-friendly patio was a big plus.', NULL, NULL),
(8, 3, 4, 8, 'Nice tasting room, a little busy on Saturday.', NULL, NULL),
(9, 5, 1, 9, 'The gin cocktail was fantastic.', NULL, NULL),
(10, 4, 2, 10, 'Solid beers across the board.', NULL, NULL),
(11, 5, 3, 11, 'Great farmhouse vibe.', NULL, NULL),
(12, 4, 4, 12, 'The barrel-aged red was excellent.', NULL, NULL),
(13, 5, 1, 13, 'Perfect stop along the river trail.', NULL, NULL),
(14, 4, 2, 14, 'Fun tasting and knowledgeable staff.', NULL, NULL),
(15, 5, 3, 15, 'The berry mead was bright and balanced.', NULL, NULL),
(16, 4, 4, 16, 'Trivia night was lively.', NULL, NULL),
(17, 5, 1, 17, 'Beautiful view from the patio.', NULL, NULL),
(18, 4, 2, 18, 'Crisp ciders and easy parking.', NULL, NULL),
(19, 5, 3, 19, 'Loved the old building and stout.', NULL, NULL),
(20, 4, 4, 20, 'Quiet spot with a great white blend.', NULL, NULL);

INSERT INTO events (
    id,
    eName,
    eDescription,
    eventDate,
    startTime,
    endTime,
    isRecurring,
    recurrenceRule,
    recurrenceEnd,
    createdAt,
    business_id,
    cover_photo_id
) VALUES
(1, 'Friday Flight Night', 'Discounted beer flights every Friday evening.', '2026-05-15', '17:00:00', '21:00:00', TRUE, 'weekly', '2026-08-28', '2026-05-01 12:00:00', 1, NULL),
(2, 'Spring Wine Tasting', 'Guided tasting of spring releases.', '2026-05-16', '14:00:00', '16:00:00', FALSE, NULL, NULL, '2026-05-01 12:05:00', 2, NULL),
(3, 'Food Truck Saturday', 'Local food truck parked outside the taproom.', '2026-05-17', '12:00:00', '20:00:00', TRUE, 'weekly', '2026-07-26', '2026-05-01 12:10:00', 3, NULL),
(4, 'Cider & Donuts', 'Fresh donuts paired with seasonal ciders.', '2026-05-23', '11:00:00', '15:00:00', FALSE, NULL, NULL, '2026-05-01 12:15:00', 4, NULL),
(5, 'Distillery Tour', 'Behind-the-scenes look at production with a tasting.', '2026-05-24', '13:00:00', '14:30:00', TRUE, 'monthly', '2026-10-25', '2026-05-01 12:20:00', 5, NULL),
(6, 'Mead & Music', 'Live acoustic music in the tasting room.', '2026-05-29', '18:00:00', '21:00:00', FALSE, NULL, NULL, '2026-05-01 12:25:00', 6, NULL),
(7, 'Patio Pint Night', 'Outdoor pours and yard games.', '2026-06-05', '17:30:00', '21:30:00', TRUE, 'weekly', '2026-09-04', '2026-05-01 12:30:00', 7, NULL),
(8, 'Acoustic Cellar Session', 'Singer-songwriter set in the tasting room.', '2026-06-06', '19:00:00', '21:00:00', FALSE, NULL, NULL, '2026-05-01 12:35:00', 8, NULL),
(9, 'Cocktail Workshop', 'Learn to build three classic cocktails.', '2026-06-07', '15:00:00', '17:00:00', FALSE, NULL, NULL, '2026-05-01 12:40:00', 9, NULL),
(10, 'Summer Release Party', 'New seasonal beer release with live music.', '2026-06-13', '16:00:00', '22:00:00', FALSE, NULL, NULL, '2026-05-01 12:45:00', 10, NULL),
(11, 'Farm Market Pop-Up', 'Local vendors and cider pours.', '2026-06-14', '10:00:00', '14:00:00', TRUE, 'monthly', '2026-09-13', '2026-05-01 12:50:00', 11, NULL),
(12, 'Barrel Room Tasting', 'Small group tasting in the barrel room.', '2026-06-20', '15:00:00', '17:00:00', FALSE, NULL, NULL, '2026-05-01 12:55:00', 12, NULL),
(13, 'Trail Ride Meetup', 'Meet at the brewhouse after a local trail ride.', '2026-06-21', '12:30:00', '16:00:00', FALSE, NULL, NULL, '2026-05-01 13:00:00', 13, NULL),
(14, 'Rye Release Tasting', 'First taste of the newest rye batch.', '2026-06-27', '17:00:00', '20:00:00', FALSE, NULL, NULL, '2026-05-01 13:05:00', 14, NULL),
(15, 'Honey Harvest Preview', 'Preview meads made with early-season honey.', '2026-06-28', '13:00:00', '16:00:00', FALSE, NULL, NULL, '2026-05-01 13:10:00', 15, NULL);

INSERT INTO liked_businesses (id, user_id, business_id, createdAt) VALUES
(1, 1, 1, '2026-05-02 08:00:00'),
(2, 1, 5, '2026-05-02 08:05:00'),
(3, 1, 13, '2026-05-02 08:10:00'),
(4, 2, 2, '2026-05-02 08:15:00'),
(5, 2, 10, '2026-05-02 08:20:00'),
(6, 2, 18, '2026-05-02 08:25:00'),
(7, 3, 7, '2026-05-02 08:30:00'),
(8, 3, 11, '2026-05-02 08:35:00'),
(9, 3, 19, '2026-05-02 08:40:00'),
(10, 4, 4, '2026-05-02 08:45:00'),
(11, 4, 8, '2026-05-02 08:50:00'),
(12, 4, 20, '2026-05-02 08:55:00');

INSERT INTO business_hours (business_id, day_of_week, opens_at, closes_at, is_closed)
SELECT b.id, d.day_of_week, '00:00:00', '23:59:59', FALSE
FROM businesses b
CROSS JOIN (
    SELECT 0 AS day_of_week
    UNION ALL SELECT 1
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
    UNION ALL SELECT 6
) d
WHERE b.id BETWEEN 1 AND 20;

UPDATE business_hours
SET opens_at=NULL, closes_at=NULL, is_closed=TRUE
WHERE business_id=4 AND day_of_week=1;

INSERT INTO user_friends (id, user_id, friend_user_id, createdAt) VALUES
(1, 1, 2, '2026-05-10 12:00:00'),
(2, 2, 1, '2026-05-10 12:00:00'),
(3, 1, 3, '2026-05-08 12:00:00'),
(4, 3, 1, '2026-05-08 12:00:00');

INSERT INTO friend_requests (id, requester_user_id, addressee_user_id, status, createdAt, respondedAt) VALUES
(1, 2, 1, 'accepted', '2026-05-10 11:58:00', '2026-05-10 12:00:00'),
(2, 3, 1, 'accepted', '2026-05-08 11:58:00', '2026-05-08 12:00:00'),
(3, 4, 1, 'pending', '2026-05-11 09:00:00', NULL),
(4, 1, 6, 'pending', '2026-05-11 09:15:00', NULL);

INSERT INTO user_visits (id, user_id, business_id, visit_type, xp_awarded, user_latitude, user_longitude, distance_meters, checkedInAt) VALUES
(1, 1, 1, 'first_time', 100, 40.301500, -79.538900, 5.20, '2026-05-03 13:20:00'),
(2, 1, 5, 'first_time', 100, 40.112600, -79.382200, 4.90, '2026-05-04 15:10:00'),
(3, 2, 1, 'first_time', 100, 40.301500, -79.538900, 6.10, '2026-05-06 17:05:00'),
(4, 2, 2, 'first_time', 100, 40.321200, -79.379500, 4.70, '2026-05-08 16:30:00'),
(5, 2, 10, 'first_time', 100, 40.312900, -79.389800, 3.80, '2026-05-10 18:40:00'),
(6, 3, 7, 'first_time', 100, 40.324500, -79.702000, 5.00, '2026-05-05 17:45:00'),
(7, 3, 11, 'first_time', 100, 40.285500, -79.503800, 6.30, '2026-05-06 19:15:00'),
(8, 4, 4, 'first_time', 100, 40.149000, -79.541100, 4.20, '2026-05-09 14:10:00');

INSERT INTO xp_log (id, user_id, amount, source_type, source_id, description, level_before, level_after, level_xp_after, createdAt) VALUES
(1, 1, 100, 'first_time_visit', '1', 'Iron Ridge Brewing', 1, 2, 0, '2026-05-03 13:20:00'),
(2, 1, 100, 'first_time_visit', '5', 'Summit Stillworks', 2, 2, 100, '2026-05-04 15:10:00'),
(3, 1, 25, 'review', '1', 'Review', 2, 2, 125, '2026-05-04 16:00:00'),
(4, 2, 100, 'first_time_visit', '1', 'Iron Ridge Brewing', 1, 2, 0, '2026-05-06 17:05:00'),
(5, 2, 25, 'review', '2', 'Review', 2, 2, 25, '2026-05-07 12:15:00'),
(6, 2, 100, 'first_time_visit', '2', 'Maple Run Winery', 2, 2, 125, '2026-05-08 16:30:00'),
(7, 2, 100, 'first_time_visit', '10', 'Laurel Highlands Brewing', 2, 3, 25, '2026-05-10 18:40:00'),
(8, 3, 100, 'first_time_visit', '7', 'Trailhead Tapworks', 1, 2, 0, '2026-05-05 17:45:00'),
(9, 3, 100, 'first_time_visit', '11', 'Red Barn Cider Co.', 2, 2, 100, '2026-05-06 19:15:00'),
(10, 3, 50, 'badge', 'first_review', 'First Review', 2, 2, 150, '2026-05-07 10:00:00'),
(11, 4, 100, 'first_time_visit', '4', 'Orchard Fork Cidery', 1, 2, 0, '2026-05-09 14:10:00');

INSERT INTO user_badges (id, user_id, badge_key, badge_name, badge_description, xp_awarded, earnedAt) VALUES
(1, 3, 'first_review', 'First Review', 'Leave your first review.', 50, '2026-05-07 10:00:00');
