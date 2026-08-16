-- ============================================================
-- Matcha — schéma de base (MySQL 8 / InnoDB / utf8mb4)
-- Idempotent : CREATE TABLE IF NOT EXISTS (rejouable via migrate)
-- Toutes les requêtes applicatives passent par des prepared statements.
-- ============================================================

-- ------------------------------------------------------------
-- Utilisateurs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    email            VARCHAR(190)     NOT NULL,
    username         VARCHAR(50)      NOT NULL,
    nom              VARCHAR(100)     NOT NULL,
    prenom           VARCHAR(100)     NOT NULL,
    password_hash    VARCHAR(255)     NOT NULL,
    genre            ENUM('homme','femme','autre') NULL,
    orientation      ENUM('hetero','homo','bi')    NULL,
    bio              TEXT             NULL,
    birthdate        DATE             NULL,
    note_popularite  DECIMAL(4,2)     NOT NULL DEFAULT 0.00,
    ville            VARCHAR(120)     NULL,
    lat              DECIMAL(10,7)    NULL,
    lng              DECIMAL(10,7)    NULL,
    gps_consent      TINYINT(1)       NOT NULL DEFAULT 0,
    email_verifie    TINYINT(1)       NOT NULL DEFAULT 0,
    actif            TINYINT(1)       NOT NULL DEFAULT 1,
    bloque_jusqua    DATETIME         NULL,
    derniere_connexion DATETIME       NULL,
    created_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_actif (actif),
    KEY idx_users_genre (genre),
    KEY idx_users_orientation (orientation),
    KEY idx_users_popularite (note_popularite),
    KEY idx_users_derniere_connexion (derniere_connexion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tags réutilisables (intérêts partagés)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tags (
    id     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name   VARCHAR(50)   NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tags_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_tags (
    user_id INT UNSIGNED NOT NULL,
    tag_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, tag_id),
    KEY idx_user_tags_tag (tag_id),
    CONSTRAINT fk_user_tags_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_tags_tag  FOREIGN KEY (tag_id)  REFERENCES tags (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Photos (max 5, une photo de profil)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS photos (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED  NOT NULL,
    path       VARCHAR(255)  NOT NULL,
    is_profile TINYINT(1)    NOT NULL DEFAULT 0,
    position   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_photos_user (user_id),
    CONSTRAINT fk_photos_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Likes (un seul like par paire)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS likes (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    from_user_id INT UNSIGNED NOT NULL,
    to_user_id   INT UNSIGNED NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_likes_pair (from_user_id, to_user_id),
    KEY idx_likes_to (to_user_id),
    CONSTRAINT fk_likes_from FOREIGN KEY (from_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_likes_to   FOREIGN KEY (to_user_id)   REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Historique de visites de profils (dernière visite par paire)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS visits (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    visitor_id INT UNSIGNED NOT NULL,
    visited_id INT UNSIGNED NOT NULL,
    viewed_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_visits_pair (visitor_id, visited_id),
    KEY idx_visits_visited (visited_id),
    CONSTRAINT fk_visits_visitor FOREIGN KEY (visitor_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_visits_visited FOREIGN KEY (visited_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Blocages (disparaît des recherches, plus de notifs, chat coupé)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blocks (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    blocker_id  INT UNSIGNED NOT NULL,
    blocked_id  INT UNSIGNED NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_blocks_pair (blocker_id, blocked_id),
    KEY idx_blocks_blocked (blocked_id),
    CONSTRAINT fk_blocks_blocker FOREIGN KEY (blocker_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_blocks_blocked FOREIGN KEY (blocked_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Signalements « faux compte » (un seul signalement par paire)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reports (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    reporter_id INT UNSIGNED  NOT NULL,
    reported_id INT UNSIGNED  NOT NULL,
    reason      VARCHAR(255)  NOT NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reports_pair (reporter_id, reported_id),
    CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_reports_reported FOREIGN KEY (reported_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Unlikes (retrait d'un like) : entrent dans la formule de popularité
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS unlikes (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    from_user_id INT UNSIGNED NOT NULL,
    to_user_id   INT UNSIGNED NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_unlikes_pair (from_user_id, to_user_id),
    KEY idx_unlikes_to (to_user_id),
    CONSTRAINT fk_unlikes_from FOREIGN KEY (from_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_unlikes_to   FOREIGN KEY (to_user_id)   REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Messages du chat (réservé aux utilisateurs connectés = like mutuel)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    from_user_id INT UNSIGNED NOT NULL,
    to_user_id   INT UNSIGNED NOT NULL,
    content      TEXT         NOT NULL,
    sent_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at      DATETIME     NULL,
    PRIMARY KEY (id),
    KEY idx_messages_to (to_user_id, read_at),
    KEY idx_messages_pair (from_user_id, to_user_id, sent_at),
    CONSTRAINT fk_messages_from FOREIGN KEY (from_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_to   FOREIGN KEY (to_user_id)   REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Notifications temps réel (like / visit / message / match / unlike)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    type       ENUM('like','visit','message','match','unlike') NOT NULL,
    actor_id   INT UNSIGNED NULL,
    read_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user (user_id, read_at),
    KEY idx_notifications_user_date (user_id, created_at),
    CONSTRAINT fk_notifications_user  FOREIGN KEY (user_id)  REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jetons : vérification d'email + réinitialisation de mot de passe
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tokens (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    type       ENUM('verify_email','reset_password') NOT NULL,
    token      CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tokens_token (token),
    KEY idx_tokens_user (user_id),
    CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
