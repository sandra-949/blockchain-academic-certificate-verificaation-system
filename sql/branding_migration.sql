-- ============================================================
-- CertVerify Branding Feature — Database Migration
-- Run this SQL in phpMyAdmin on your certverify_db database
-- ============================================================

USE certverify_db;

-- Add branding columns to the users table
-- These store each institution's logo path and color preferences
ALTER TABLE users
    ADD COLUMN logoPath      VARCHAR(255) DEFAULT NULL  COMMENT 'Path to uploaded institution logo',
    ADD COLUMN primaryColor  VARCHAR(7)   DEFAULT '#1a3a6c' COMMENT 'Primary color hex (header, border)',
    ADD COLUMN secondaryColor VARCHAR(7)  DEFAULT '#e8a020' COMMENT 'Secondary/accent color hex (dividers, highlights)';

-- Verify the columns were added
DESCRIBE users;
