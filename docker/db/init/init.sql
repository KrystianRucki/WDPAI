-- Reevio complete PostgreSQL database export for WDPAI project baseline.
-- Covers relations 1:1, 1:N, M:N, SQL views, trigger, functions and sample data.

DROP VIEW IF EXISTS v_user_activity CASCADE;
DROP VIEW IF EXISTS v_review_feed CASCADE;
DROP VIEW IF EXISTS v_user_statistics CASCADE;

DROP TABLE IF EXISTS notifications CASCADE;
DROP TABLE IF EXISTS followers CASCADE;
DROP TABLE IF EXISTS user_favorite_films CASCADE;
DROP TABLE IF EXISTS watchlist CASCADE;
DROP TABLE IF EXISTS diary_entries CASCADE;
DROP TABLE IF EXISTS list_items CASCADE;
DROP TABLE IF EXISTS lists CASCADE;
DROP TABLE IF EXISTS review_likes CASCADE;
DROP TABLE IF EXISTS review_comments CASCADE;
DROP TABLE IF EXISTS reviews CASCADE;
DROP TABLE IF EXISTS film_people CASCADE;
DROP TABLE IF EXISTS people CASCADE;
DROP TABLE IF EXISTS film_genres CASCADE;
DROP TABLE IF EXISTS genres CASCADE;
DROP TABLE IF EXISTS films CASCADE;
DROP TABLE IF EXISTS user_notification_settings CASCADE;
DROP TABLE IF EXISTS user_profiles CASCADE;
DROP TABLE IF EXISTS audit_logs CASCADE;
DROP TABLE IF EXISTS users CASCADE;

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password TEXT NOT NULL,
    avatar_url TEXT,
    bio VARCHAR(64),
    role VARCHAR(20) NOT NULL DEFAULT 'user' CHECK (role IN ('user', 'admin')),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- One-to-one relation: one user has exactly one optional profile details row.
CREATE TABLE user_profiles (
    user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    location VARCHAR(120),
    favorite_genre VARCHAR(80),
    website_url TEXT,
    display_theme VARCHAR(30) NOT NULL DEFAULT 'dark-cinematic',
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_notification_settings (
    user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    new_followers BOOLEAN NOT NULL DEFAULT TRUE,
    review_likes BOOLEAN NOT NULL DEFAULT TRUE,
    review_comments BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE films (
    id SERIAL PRIMARY KEY,
    tmdb_id INTEGER UNIQUE,
    title VARCHAR(180) NOT NULL,
    original_title VARCHAR(180),
    release_year SMALLINT CHECK (release_year BETWEEN 1888 AND 2100),
    director VARCHAR(120),
    description TEXT,
    poster_url TEXT,
    poster_path TEXT,
    backdrop_url TEXT,
    backdrop_path TEXT,
    runtime_minutes SMALLINT CHECK (runtime_minutes > 0),
    tmdb_vote_average NUMERIC(4,2),
    tmdb_vote_count INTEGER,
    tmdb_popularity NUMERIC(10,3),
    tmdb_raw JSONB NOT NULL DEFAULT '{}'::jsonb,
    tmdb_synced_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE genres (
    id SERIAL PRIMARY KEY,
    tmdb_id INTEGER UNIQUE,
    name VARCHAR(80) NOT NULL UNIQUE,
    tmdb_synced_at TIMESTAMPTZ
);

-- Many-to-many relation: films can have many genres, genres can describe many films.
CREATE TABLE film_genres (
    film_id INTEGER NOT NULL REFERENCES films(id) ON DELETE CASCADE,
    genre_id INTEGER NOT NULL REFERENCES genres(id) ON DELETE RESTRICT,
    PRIMARY KEY (film_id, genre_id)
);

CREATE TABLE people (
    id SERIAL PRIMARY KEY,
    tmdb_id INTEGER UNIQUE,
    full_name VARCHAR(140) NOT NULL,
    biography TEXT,
    photo_url TEXT,
    profile_path TEXT,
    known_for_department VARCHAR(80),
    tmdb_synced_at TIMESTAMPTZ
);

CREATE TABLE film_people (
    film_id INTEGER NOT NULL REFERENCES films(id) ON DELETE CASCADE,
    person_id INTEGER NOT NULL REFERENCES people(id) ON DELETE CASCADE,
    credit_type VARCHAR(40) NOT NULL CHECK (credit_type IN ('director', 'actor', 'writer', 'composer')),
    character_name VARCHAR(140),
    job VARCHAR(120),
    department VARCHAR(120),
    cast_order INTEGER,
    PRIMARY KEY (film_id, person_id, credit_type)
);

-- One-to-many relation: one user can create many reviews, one film can have many reviews.
CREATE TABLE reviews (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    film_id INTEGER NOT NULL REFERENCES films(id) ON DELETE CASCADE,
    rating NUMERIC(2,1) NOT NULL CHECK (rating >= 0 AND rating <= 5),
    title VARCHAR(220) NOT NULL,
    content TEXT NOT NULL,
    is_public BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, film_id)
);

CREATE TABLE review_comments (
    id SERIAL PRIMARY KEY,
    review_id INTEGER NOT NULL REFERENCES reviews(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    content TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE review_likes (
    review_id INTEGER NOT NULL REFERENCES reviews(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (review_id, user_id)
);

CREATE TABLE lists (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(160) NOT NULL,
    description TEXT,
    is_public BOOLEAN NOT NULL DEFAULT TRUE,
    is_ranked BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE list_items (
    list_id INTEGER NOT NULL REFERENCES lists(id) ON DELETE CASCADE,
    film_id INTEGER NOT NULL REFERENCES films(id) ON DELETE CASCADE,
    position INTEGER NOT NULL CHECK (position > 0),
    note TEXT,
    added_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (list_id, film_id),
    UNIQUE (list_id, position)
);

CREATE TABLE diary_entries (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    film_id INTEGER NOT NULL REFERENCES films(id) ON DELETE CASCADE,
    watched_on DATE NOT NULL,
    rating NUMERIC(2,1) CHECK (rating >= 0 AND rating <= 5),
    is_rewatch BOOLEAN NOT NULL DEFAULT FALSE,
    is_public BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, film_id, watched_on)
);

CREATE TABLE watchlist (
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    film_id INTEGER NOT NULL REFERENCES films(id) ON DELETE CASCADE,
    added_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, film_id)
);

CREATE TABLE user_films (
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    film_id INTEGER NOT NULL REFERENCES films(id) ON DELETE CASCADE,
    added_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, film_id)
);

CREATE TABLE user_favorite_films (
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    film_id INTEGER NOT NULL REFERENCES films(id) ON DELETE CASCADE,
    position INTEGER NOT NULL CHECK (position BETWEEN 1 AND 4),
    added_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, film_id),
    UNIQUE (user_id, position)
);

CREATE TABLE followers (
    follower_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    followed_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, followed_id),
    CHECK (follower_id <> followed_id)
);

CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    actor_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    type VARCHAR(30) NOT NULL CHECK (type IN ('new_follower', 'review_like', 'review_comment')),
    review_id INTEGER REFERENCES reviews(id) ON DELETE CASCADE,
    comment_id INTEGER REFERENCES review_comments(id) ON DELETE CASCADE,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE audit_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_reviews_film_id ON reviews(film_id);
CREATE INDEX idx_reviews_user_id ON reviews(user_id);
CREATE INDEX idx_comments_review_id ON review_comments(review_id);
CREATE INDEX idx_notifications_user_id ON notifications(user_id, is_read);
CREATE INDEX idx_users_search ON users USING gin (to_tsvector('simple', username || ' ' || email));
CREATE INDEX idx_films_tmdb_id ON films(tmdb_id);
CREATE INDEX idx_people_tmdb_id ON people(tmdb_id);
CREATE INDEX idx_genres_tmdb_id ON genres(tmdb_id);

CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER users_updated_at
BEFORE UPDATE ON users
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TRIGGER profiles_updated_at
BEFORE UPDATE ON user_profiles
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TRIGGER lists_updated_at
BEFORE UPDATE ON lists
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TRIGGER reviews_updated_at
BEFORE UPDATE ON reviews
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE OR REPLACE FUNCTION film_average_rating(p_film_id INTEGER)
RETURNS NUMERIC AS $$
DECLARE
    result NUMERIC;
BEGIN
    SELECT COALESCE(ROUND(AVG(rating), 2), 0) INTO result
    FROM reviews
    WHERE film_id = p_film_id;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION block_user(p_user_id INTEGER, p_blocked BOOLEAN)
RETURNS BOOLEAN AS $$
BEGIN
    UPDATE users
    SET is_active = NOT p_blocked
    WHERE id = p_user_id;

    INSERT INTO audit_logs(user_id, action, metadata)
    VALUES (p_user_id, CASE WHEN p_blocked THEN 'blocked_user' ELSE 'unblocked_user' END, jsonb_build_object('blocked', p_blocked));

    RETURN FOUND;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION notify_review_interaction()
RETURNS TRIGGER AS $$
DECLARE
    recipient_id INTEGER;
BEGIN
    SELECT user_id INTO recipient_id
    FROM reviews
    WHERE id = NEW.review_id;

    IF recipient_id IS NOT NULL AND recipient_id <> NEW.user_id THEN
        IF TG_TABLE_NAME = 'review_comments' THEN
            INSERT INTO notifications(user_id, actor_id, type, review_id, comment_id)
            VALUES (
                recipient_id,
                NEW.user_id,
                'review_comment',
                NEW.review_id,
                NEW.id
            );
        ELSE
            INSERT INTO notifications(user_id, actor_id, type, review_id, comment_id)
            VALUES (
                recipient_id,
                NEW.user_id,
                'review_like',
                NEW.review_id,
                NULL
            );
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER review_comment_notification
AFTER INSERT ON review_comments
FOR EACH ROW EXECUTE FUNCTION notify_review_interaction();

CREATE TRIGGER review_like_notification
AFTER INSERT ON review_likes
FOR EACH ROW EXECUTE FUNCTION notify_review_interaction();

-- SQL views with joins across several tables.
CREATE VIEW v_review_feed AS
SELECT
    r.id AS review_id,
    r.title AS review_title,
    r.content,
    r.rating,
    r.created_at,
    u.id AS user_id,
    u.username,
    f.id AS film_id,
    f.title AS film_title,
    f.release_year,
    f.director,
    COUNT(DISTINCT rl.user_id) AS likes_count,
    COUNT(DISTINCT rc.id) AS comments_count
FROM reviews r
JOIN users u ON u.id = r.user_id
JOIN films f ON f.id = r.film_id
LEFT JOIN review_likes rl ON rl.review_id = r.id
LEFT JOIN review_comments rc ON rc.review_id = r.id
WHERE r.is_public = TRUE AND u.is_active = TRUE
GROUP BY r.id, u.id, f.id;

CREATE VIEW v_user_activity AS
SELECT
    de.id AS activity_id,
    de.created_at,
    de.watched_on,
    de.rating,
    u.id AS user_id,
    u.username,
    f.id AS film_id,
    f.title AS film_title,
    f.release_year,
    'diary_entry'::TEXT AS activity_type
FROM diary_entries de
JOIN users u ON u.id = de.user_id
JOIN films f ON f.id = de.film_id
WHERE de.is_public = TRUE AND u.is_active = TRUE;

CREATE VIEW v_user_statistics AS
SELECT
    u.id AS user_id,
    u.username,
    COUNT(DISTINCT r.id) AS reviews_count,
    COUNT(DISTINCT de.id) AS diary_entries_count,
    COUNT(DISTINCT l.id) AS lists_count,
    COUNT(DISTINCT w.film_id) AS watchlist_count,
    COUNT(DISTINCT f1.follower_id) AS followers_count,
    COUNT(DISTINCT f2.followed_id) AS following_count
FROM users u
LEFT JOIN reviews r ON r.user_id = u.id
LEFT JOIN diary_entries de ON de.user_id = u.id
LEFT JOIN lists l ON l.user_id = u.id
LEFT JOIN watchlist w ON w.user_id = u.id
LEFT JOIN followers f1 ON f1.followed_id = u.id
LEFT JOIN followers f2 ON f2.follower_id = u.id
GROUP BY u.id;

-- Password for all demo users is: password
INSERT INTO users (id, username, email, password, avatar_url, bio, role, is_active) VALUES
(1, 'admin', 'admin@reevio.test', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Platform administrator.', 'admin', TRUE),
(2, 'jakub', 'jakub@reevio.test', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Watching, rating and collecting films.', 'user', TRUE),
(3, 'lenaframes', 'lena.frames@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Editorial cinema fan.', 'user', TRUE),
(4, 'noirnina', 'nina.noir@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Noir, crime and moody lighting.', 'user', FALSE),
(5, 'marcust', 'marcus.thorne@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Reviewing sci-fi epics.', 'user', TRUE),
(6, 'elena', 'elena.rostova@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Festival watcher.', 'user', TRUE),
(7, 'davidkim', 'david.kim@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Korean thrillers and classics.', 'user', TRUE),
(8, 'sarahj', 'sarah.j@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'A24 and arthouse.', 'user', TRUE),
(9, 'miaw', 'mia.wallace@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Lists and film marathons.', 'user', FALSE),
(10, 'ryancooper', 'ryan.cooper@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Blockbusters and soundtracks.', 'user', TRUE),
(11, 'sofiaframes', 'sofia.chen@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Animated masterpieces.', 'user', TRUE),
(12, 'tomscuts', 'tom.sanders@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Editing nerd.', 'user', TRUE),
(13, 'norab', 'nora.blythe@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Slow cinema enthusiast.', 'user', FALSE),
(14, 'stonecinema', 'adam.stone@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Action classics.', 'user', TRUE),
(15, 'juliahart', 'julia.hart@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Film essays and lists.', 'user', TRUE),
(16, 'oscar_lane', 'oscar.lane@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Awards watcher.', 'user', TRUE),
(17, 'irisnovak', 'iris.novak@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'European cinema.', 'user', TRUE),
(18, 'leogrant', 'leo.grant@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Documentaries and music films.', 'user', TRUE),
(19, 'clarawatches', 'clara.west@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Weekend watchlists.', 'user', FALSE),
(20, 'ethanmoore', 'ethan.moore@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'New releases.', 'user', TRUE),
(21, 'avabrooks', 'ava.brooks@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Horror and fantasy.', 'user', TRUE),
(22, 'felixward', 'felix.ward@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Sci-fi archive.', 'user', TRUE),
(23, 'martasilva', 'marta.silva@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'World cinema.', 'user', TRUE),
(24, 'victorreyes', 'victor.reyes@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Neo-noir collector.', 'user', TRUE),
(25, 'hanalee', 'hana.lee@example.com', '$2y$12$d2JWyEQKjm6XGupNIfJKf.MQKA9obXRZL3I41hajN8dmlIOM9udyS', NULL, 'Romance and dramas.', 'user', FALSE);

SELECT setval('users_id_seq', (SELECT MAX(id) FROM users));

INSERT INTO user_profiles (user_id, location, favorite_genre, website_url) VALUES
(1, 'Warsaw', 'All cinema', 'https://reevio.test'),
(2, 'Krakow', 'Science Fiction', NULL),
(3, 'Berlin', 'Drama', NULL),
(5, 'London', 'Science Fiction', NULL),
(8, 'New York', 'Arthouse', NULL);

INSERT INTO user_notification_settings (user_id)
SELECT id FROM users;

INSERT INTO films (id, title, release_year, director, description, runtime_minutes) VALUES
(1, 'Dune: Part Two', 2024, 'Denis Villeneuve', 'Paul Atreides unites with Chani and the Fremen while seeking revenge against the conspirators who destroyed his family.', 166),
(2, 'Past Lives', 2023, 'Celine Song', 'Two childhood friends confront destiny, love and the choices that make a life.', 106),
(3, 'Poor Things', 2023, 'Yorgos Lanthimos', 'A fantastical story of Bella Baxter and her journey of self-discovery.', 141),
(4, 'Blade Runner 2049', 2017, 'Denis Villeneuve', 'A young blade runner discovers a secret that could plunge society into chaos.', 164),
(5, 'The Grand Budapest Hotel', 2014, 'Wes Anderson', 'The adventures of a legendary concierge and his lobby boy.', 99),
(6, 'Inception', 2010, 'Christopher Nolan', 'A thief who steals secrets through dream-sharing technology receives an inverse task.', 148),
(7, 'Arrival', 2016, 'Denis Villeneuve', 'A linguist works with the military to communicate with alien lifeforms.', 116),
(8, 'The Matrix', 1999, 'The Wachowskis', 'A hacker discovers reality is not what it seems.', 136),
(9, 'Her', 2013, 'Spike Jonze', 'A lonely writer develops an unusual relationship with an operating system.', 126),
(10, 'The Curious Case of Benjamin Button', 2008, 'David Fincher', 'A man ages in reverse and experiences love and loss across decades.', 166);

SELECT setval('films_id_seq', (SELECT MAX(id) FROM films));

UPDATE films SET tmdb_id = CASE id
    WHEN 1 THEN 693134
    WHEN 2 THEN 666277
    WHEN 3 THEN 792307
    WHEN 4 THEN 335984
    WHEN 5 THEN 120467
    WHEN 6 THEN 27205
    WHEN 7 THEN 329865
    WHEN 8 THEN 603
    WHEN 9 THEN 152601
    WHEN 10 THEN 4922
    ELSE tmdb_id
END;


INSERT INTO genres (id, name) VALUES
(1, 'Science Fiction'),
(2, 'Drama'),
(3, 'Romance'),
(4, 'Adventure'),
(5, 'Comedy'),
(6, 'Fantasy'),
(7, 'Action'),
(8, 'Mystery');
SELECT setval('genres_id_seq', (SELECT MAX(id) FROM genres));

UPDATE genres SET tmdb_id = CASE name
    WHEN 'Science Fiction' THEN 878
    WHEN 'Drama' THEN 18
    WHEN 'Romance' THEN 10749
    WHEN 'Adventure' THEN 12
    WHEN 'Comedy' THEN 35
    WHEN 'Fantasy' THEN 14
    WHEN 'Action' THEN 28
    WHEN 'Mystery' THEN 9648
    ELSE tmdb_id
END;


INSERT INTO film_genres (film_id, genre_id) VALUES
(1,1),(1,4),(1,2),
(2,2),(2,3),
(3,2),(3,5),(3,6),
(4,1),(4,2),(4,8),
(5,5),(5,4),
(6,1),(6,7),(6,8),
(7,1),(7,2),
(8,1),(8,7),
(9,1),(9,2),(9,3),
(10,2),(10,3),(10,6);

INSERT INTO people (id, full_name, biography) VALUES
(1, 'Denis Villeneuve', 'Canadian filmmaker known for atmospheric science fiction and thrillers.'),
(2, 'Timothée Chalamet', 'Actor.'),
(3, 'Zendaya', 'Actor.'),
(4, 'Celine Song', 'Filmmaker and playwright.'),
(5, 'Christopher Nolan', 'Filmmaker.'),
(6, 'David Fincher', 'Filmmaker.');
SELECT setval('people_id_seq', (SELECT MAX(id) FROM people));

INSERT INTO film_people (film_id, person_id, credit_type, character_name) VALUES
(1,1,'director',NULL),(1,2,'actor','Paul Atreides'),(1,3,'actor','Chani'),
(2,4,'director',NULL),
(6,5,'director',NULL),
(10,6,'director',NULL);

INSERT INTO reviews (id, user_id, film_id, rating, title, content, is_public) VALUES
(1,2,1,5.0,'A visual symphony that redefines the modern blockbuster.','Denis Villeneuve builds a monumental film with precise atmosphere and unforgettable scale.',TRUE),
(2,3,2,4.5,'Quiet, precise and emotionally devastating.','Past Lives understands how memory changes the shape of love.',TRUE),
(3,5,4,4.8,'Neon melancholy done right.','A patient, massive sequel that earns its silence.',TRUE),
(4,8,3,4.7,'Strange, funny and alive.','A bold film with wild production design and a fierce central performance.',TRUE),
(5,7,7,5.0,'Language as cinema.','Arrival remains one of the best modern science-fiction dramas.',TRUE),
(6,11,5,4.2,'A candy box with a sad heart.','A playful but surprisingly moving story about memory and friendship.',TRUE),
(7,14,8,5.0,'Still wired into the future.','The Matrix remains a clean blueprint for action and cyberpunk storytelling.',TRUE),
(8,15,9,4.6,'Tender technology.','Her makes the future feel intimate and lonely.',TRUE),
(9,16,6,4.4,'Puzzle-box spectacle.','Inception is still one of the most entertaining blockbuster concepts.',TRUE),
(10,2,10,5.0,'Melancholy time capsule.','A beautiful film about time, memory and everything we lose.',TRUE);
SELECT setval('reviews_id_seq', (SELECT MAX(id) FROM reviews));

INSERT INTO review_comments (review_id, user_id, content) VALUES
(1,5,'Completely agree about the sound design.'),
(1,6,'The final act was overwhelming in the best way.'),
(2,8,'This review captures exactly why the film stayed with me.'),
(10,3,'Beautiful note. I need to rewatch it.'),
(4,2,'The production design is unreal.');

INSERT INTO review_likes (review_id, user_id) VALUES
(1,3),(1,5),(1,6),(1,8),(2,2),(2,5),(3,2),(4,2),(10,3),(10,8);

INSERT INTO lists (id, user_id, title, description, is_public, is_ranked) VALUES
(1,2,'Sci-Fi Essentials','Essential science-fiction movies for big screens and late nights.',TRUE,TRUE),
(2,2,'Films to Rewatch','Movies I want to experience again.',TRUE,FALSE),
(3,8,'A24 Energy','Films with a specific modern indie mood.',TRUE,FALSE),
(4,5,'Desert Epics','Sweeping sand, myth and scale.',TRUE,TRUE);
SELECT setval('lists_id_seq', (SELECT MAX(id) FROM lists));

INSERT INTO list_items (list_id, film_id, position, note) VALUES
(1,1,1,'Modern epic.'),(1,4,2,'Visual benchmark.'),(1,7,3,'Smart and emotional.'),(1,8,4,'Classic cyberpunk.'),
(2,10,1,'Return to the melancholy.'),(2,2,2,'Need another quiet evening.'),
(3,2,1,'Emotional precision.'),(3,3,2,'Wild design.'),
(4,1,1,'The big one.');

INSERT INTO diary_entries (user_id, film_id, watched_on, rating, is_rewatch, is_public) VALUES
(2,1,CURRENT_DATE,5.0,FALSE,TRUE),
(2,10,CURRENT_DATE - 1,5.0,FALSE,TRUE),
(3,2,CURRENT_DATE - 1,4.5,FALSE,TRUE),
(5,4,CURRENT_DATE - 2,4.8,TRUE,TRUE),
(8,3,CURRENT_DATE - 3,4.7,FALSE,TRUE);

INSERT INTO watchlist (user_id, film_id) VALUES
(2,2),(2,3),(2,4),(2,7),(3,1),(5,1),(8,10),(11,9);

INSERT INTO user_favorite_films (user_id, film_id, position) VALUES
(2,1,1),(2,10,2),(2,7,3),(2,4,4),
(3,2,1),(3,1,2),(5,4,1),(8,3,1);

INSERT INTO followers (follower_id, followed_id) VALUES
(3,2),(5,2),(6,2),(8,2),(2,3),(2,5),(7,2),(11,2),(14,2),(15,2);

INSERT INTO notifications (user_id, actor_id, type, is_read) VALUES
(2,3,'new_follower',FALSE),
(2,5,'new_follower',FALSE),
(2,6,'new_follower',FALSE),
(3,2,'new_follower',TRUE);

-- Example transaction with a defined isolation level: create a list and add related films atomically.
BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ;
    INSERT INTO lists (user_id, title, description, is_public, is_ranked)
    VALUES (2, 'Cyberpunk Dreams', 'A compact list created in one transaction.', TRUE, TRUE);

    INSERT INTO list_items (list_id, film_id, position)
    VALUES (currval('lists_id_seq'), 4, 1), (currval('lists_id_seq'), 8, 2), (currval('lists_id_seq'), 9, 3);
COMMIT;
