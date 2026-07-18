-- CertVerify Database Setup
-- Run this SQL in your MySQL database (e.g. via phpMyAdmin or MySQL CLI)
CREATE DATABASE IF NOT EXISTS certverify_db;

USE certverify_db;

-- Users Table
CREATE TABLE IF NOT EXISTS
    users (
        userID INT AUTO_INCREMENT PRIMARY KEY,
        fullName VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        passwordHash VARCHAR(255) NOT NULL,
        role ENUM('admin', 'institution', 'employer') NOT NULL DEFAULT 'employer',
        dateCreated DATETIME DEFAULT CURRENT_TIMESTAMP,
        status ENUM('active', 'inactive') DEFAULT 'active'
    );

-- Certificates Table
CREATE TABLE IF NOT EXISTS
    certificates (
        certificateID INT AUTO_INCREMENT PRIMARY KEY,
        studentName VARCHAR(150) NOT NULL,
        studentID VARCHAR(50) NOT NULL,
        program VARCHAR(200) NOT NULL,
        dateIssued DATE NOT NULL,
        hashValue VARCHAR(64) NOT NULL UNIQUE,
        issuedBy INT NOT NULL,
        status ENUM('valid', 'revoked') DEFAULT 'valid',
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (issuedBy) REFERENCES users (userID)
    );

-- Transactions Table
CREATE TABLE IF NOT EXISTS
    transactions (
        transactionID INT AUTO_INCREMENT PRIMARY KEY,
        certificateID INT,
        verifiedBy INT,
        transactionType ENUM('issued', 'verified', 'revoked') NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        verificationStatus ENUM('valid', 'invalid', 'revoked') DEFAULT NULL,
        ipAddress VARCHAR(50),
        FOREIGN KEY (certificateID) REFERENCES certificates (certificateID),
        FOREIGN KEY (verifiedBy) REFERENCES users (userID)
    );

-- Default Admin User (password: admin123)
INSERT INTO
    users (fullName, email, passwordHash, role)
VALUES
    (
        'System Administrator',
        'admin@certverify.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'admin'
    );

-- Sample Institution User (password: institution123)
INSERT INTO
    users (fullName, email, passwordHash, role)
VALUES
    (
        'Cavendish University Zambia',
        'institution@certverify.com',
        '$2y$10$TKh8H1.PJy4hS9l2vwKrhu0bELBLdm.kS.8L3XhMX1vKqzQZa2oCm',
        'institution'
    );

-- Add verificationCode column if it doesn't exist
ALTER TABLE certificates
ADD COLUMN verificationCode VARCHAR(30) UNIQUE AFTER hashValue;

-- Add indexes
CREATE INDEX idx_verificationCode ON certificates (verificationCode);

CREATE INDEX idx_studentID ON certificates (studentID);

-- Generate verification codes for existing certificates
-- This creates codes like: STUDENTID-2026-A3F7K
UPDATE certificates
SET
    verificationCode = CONCAT(
        studentID,
        '-',
        YEAR(dateIssued),
        '-',
        UPPER(SUBSTRING(MD5(RAND()), 1, 5))
    )
WHERE
    verificationCode IS NULL;