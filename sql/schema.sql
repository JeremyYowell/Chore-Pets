-- ============================================================
-- Choreskill – Database Schema
-- MySQL 5.7+ / MariaDB 10.3+
-- Safe to re-run on an existing database:
--   • CREATE TABLE IF NOT EXISTS skips existing tables (and their
--     inline KEY declarations), so no duplicate-index errors occur.
--   • All indexes are declared inline inside their CREATE TABLE block.
-- ============================================================

CREATE DATABASE IF NOT EXISTS choreskill
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE choreskill;

-- ── Households ────────────────────────────────────────────────────────────────
-- One row per Amazon account (identified by Alexa userId).
-- Supports multiple children per household.

CREATE TABLE IF NOT EXISTS households (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alexa_user_id VARCHAR(255) NOT NULL,
    setup_done    TINYINT(1)   NOT NULL DEFAULT 0,   -- 0 = onboarding in progress
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_alexa_user (alexa_user_id)
) ENGINE=InnoDB;

-- ── Children ──────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS children (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    household_id INT UNSIGNED NOT NULL,
    name         VARCHAR(50)  NOT NULL,
    pet_type     ENUM('cat','dog','hamster','panda') NOT NULL DEFAULT 'cat',
    pet_name     VARCHAR(50)  NULL DEFAULT NULL,       -- NULL until naming unlocked
    naming_unlocked TINYINT(1) NOT NULL DEFAULT 0,    -- set after 7-day streak
    sort_order   TINYINT      NOT NULL DEFAULT 0,
    active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Chores ────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS chores (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    child_id   INT UNSIGNED NOT NULL,
    name       VARCHAR(100) NOT NULL,
    sort_order TINYINT      NOT NULL DEFAULT 0,
    active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_chores_child (child_id, active),
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Daily Completions ─────────────────────────────────────────────────────────
-- One row per chore per day when completed.
-- UNIQUE prevents double-tapping from inserting duplicates.

CREATE TABLE IF NOT EXISTS chore_completions (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chore_id       INT UNSIGNED NOT NULL,
    child_id       INT UNSIGNED NOT NULL,
    completed_date DATE         NOT NULL,
    completed_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_chore_day (chore_id, completed_date),
    KEY idx_completions_child_date (child_id, completed_date),
    FOREIGN KEY (chore_id)  REFERENCES chores(id)   ON DELETE CASCADE,
    FOREIGN KEY (child_id)  REFERENCES children(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Pet Interactions ──────────────────────────────────────────────────────────
-- One row per interaction type (feed/water/play) per child per day.
-- Only available when pet state is 'thriving'. UNIQUE prevents duplicate uses.

CREATE TABLE IF NOT EXISTS pet_interactions (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    child_id         INT UNSIGNED NOT NULL,
    interaction_type ENUM('feed','water','play') NOT NULL,
    interaction_date DATE         NOT NULL,
    interacted_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_child_interaction_day (child_id, interaction_type, interaction_date),
    KEY idx_interactions_child_date (child_id, interaction_date),
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
) ENGINE=InnoDB;
