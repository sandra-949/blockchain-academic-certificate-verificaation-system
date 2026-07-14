-- ============================================================
-- CertVerify Multi-Node Blockchain Setup
-- Run this entire file in phpMyAdmin SQL tab
-- It creates 3 independent database nodes with identical schema
-- ============================================================

-- ─── NODE 1 (Primary) ─────────────────────────────────────
CREATE DATABASE IF NOT EXISTS certverify_node1;
USE certverify_node1;

CREATE TABLE IF NOT EXISTS users (
    userID INT AUTO_INCREMENT PRIMARY KEY,
    fullName VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    passwordHash VARCHAR(255) NOT NULL,
    role ENUM('admin','institution','employer') NOT NULL DEFAULT 'employer',
    logoPath VARCHAR(255) DEFAULT NULL,
    primaryColor VARCHAR(7) DEFAULT '#1a3a6c',
    secondaryColor VARCHAR(7) DEFAULT '#e8a020',
    dateCreated DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active','inactive') DEFAULT 'active'
);

CREATE TABLE IF NOT EXISTS certificates (
    certificateID INT AUTO_INCREMENT PRIMARY KEY,
    studentName VARCHAR(150) NOT NULL,
    studentID VARCHAR(50) NOT NULL,
    program VARCHAR(200) NOT NULL,
    dateIssued DATE NOT NULL,
    hashValue VARCHAR(64) NOT NULL UNIQUE,
    issuedBy INT NOT NULL,
    status ENUM('valid','revoked') DEFAULT 'valid',
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    blockIndex INT DEFAULT NULL COMMENT 'Position in the chain',
    previousHash VARCHAR(64) DEFAULT '0000000000000000000000000000000000000000000000000000000000000000' COMMENT 'Hash of previous block',
    blockHash VARCHAR(64) DEFAULT NULL COMMENT 'Hash of this block including previousHash',
    FOREIGN KEY (issuedBy) REFERENCES users(userID)
);

CREATE TABLE IF NOT EXISTS transactions (
    transactionID INT AUTO_INCREMENT PRIMARY KEY,
    certificateID INT,
    verifiedBy INT,
    transactionType ENUM('issued','verified','revoked') NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    verificationStatus ENUM('valid','invalid','revoked') DEFAULT NULL,
    consensusResult VARCHAR(20) DEFAULT NULL COMMENT 'agreed / disputed',
    ipAddress VARCHAR(50),
    FOREIGN KEY (certificateID) REFERENCES certificates(certificateID),
    FOREIGN KEY (verifiedBy) REFERENCES users(userID)
);

-- Default users (same on all nodes)
INSERT INTO users (fullName, email, passwordHash, role) VALUES
('System Administrator', 'admin@certverify.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Cavendish University Zambia', 'institution@certverify.com', '$2y$10$TKh8H1.PJy4hS9l2vwKrhu0bELBLdm.kS.8L3XhMX1vKqzQZa2oCm', 'institution');

-- ─── NODE 2 ───────────────────────────────────────────────
CREATE DATABASE IF NOT EXISTS certverify_node2;
USE certverify_node2;

CREATE TABLE IF NOT EXISTS users (
    userID INT AUTO_INCREMENT PRIMARY KEY,
    fullName VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    passwordHash VARCHAR(255) NOT NULL,
    role ENUM('admin','institution','employer') NOT NULL DEFAULT 'employer',
    logoPath VARCHAR(255) DEFAULT NULL,
    primaryColor VARCHAR(7) DEFAULT '#1a3a6c',
    secondaryColor VARCHAR(7) DEFAULT '#e8a020',
    dateCreated DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active','inactive') DEFAULT 'active'
);

CREATE TABLE IF NOT EXISTS certificates (
    certificateID INT AUTO_INCREMENT PRIMARY KEY,
    studentName VARCHAR(150) NOT NULL,
    studentID VARCHAR(50) NOT NULL,
    program VARCHAR(200) NOT NULL,
    dateIssued DATE NOT NULL,
    hashValue VARCHAR(64) NOT NULL UNIQUE,
    issuedBy INT NOT NULL,
    status ENUM('valid','revoked') DEFAULT 'valid',
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    blockIndex INT DEFAULT NULL,
    previousHash VARCHAR(64) DEFAULT '0000000000000000000000000000000000000000000000000000000000000000',
    blockHash VARCHAR(64) DEFAULT NULL,
    FOREIGN KEY (issuedBy) REFERENCES users(userID)
);

CREATE TABLE IF NOT EXISTS transactions (
    transactionID INT AUTO_INCREMENT PRIMARY KEY,
    certificateID INT,
    verifiedBy INT,
    transactionType ENUM('issued','verified','revoked') NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    verificationStatus ENUM('valid','invalid','revoked') DEFAULT NULL,
    consensusResult VARCHAR(20) DEFAULT NULL,
    ipAddress VARCHAR(50),
    FOREIGN KEY (certificateID) REFERENCES certificates(certificateID),
    FOREIGN KEY (verifiedBy) REFERENCES users(userID)
);

INSERT INTO users (fullName, email, passwordHash, role) VALUES
('System Administrator', 'admin@certverify.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Cavendish University Zambia', 'institution@certverify.com', '$2y$10$TKh8H1.PJy4hS9l2vwKrhu0bELBLdm.kS.8L3XhMX1vKqzQZa2oCm', 'institution');

-- ─── NODE 3 ───────────────────────────────────────────────
CREATE DATABASE IF NOT EXISTS certverify_node3;
USE certverify_node3;

CREATE TABLE IF NOT EXISTS users (
    userID INT AUTO_INCREMENT PRIMARY KEY,
    fullName VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    passwordHash VARCHAR(255) NOT NULL,
    role ENUM('admin','institution','employer') NOT NULL DEFAULT 'employer',
    logoPath VARCHAR(255) DEFAULT NULL,
    primaryColor VARCHAR(7) DEFAULT '#1a3a6c',
    secondaryColor VARCHAR(7) DEFAULT '#e8a020',
    dateCreated DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active','inactive') DEFAULT 'active'
);

CREATE TABLE IF NOT EXISTS certificates (
    certificateID INT AUTO_INCREMENT PRIMARY KEY,
    studentName VARCHAR(150) NOT NULL,
    studentID VARCHAR(50) NOT NULL,
    program VARCHAR(200) NOT NULL,
    dateIssued DATE NOT NULL,
    hashValue VARCHAR(64) NOT NULL UNIQUE,
    issuedBy INT NOT NULL,
    status ENUM('valid','revoked') DEFAULT 'valid',
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    blockIndex INT DEFAULT NULL,
    previousHash VARCHAR(64) DEFAULT '0000000000000000000000000000000000000000000000000000000000000000',
    blockHash VARCHAR(64) DEFAULT NULL,
    FOREIGN KEY (issuedBy) REFERENCES users(userID)
);

CREATE TABLE IF NOT EXISTS transactions (
    transactionID INT AUTO_INCREMENT PRIMARY KEY,
    certificateID INT,
    verifiedBy INT,
    transactionType ENUM('issued','verified','revoked') NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    verificationStatus ENUM('valid','invalid','revoked') DEFAULT NULL,
    consensusResult VARCHAR(20) DEFAULT NULL,
    ipAddress VARCHAR(50),
    FOREIGN KEY (certificateID) REFERENCES certificates(certificateID),
    FOREIGN KEY (verifiedBy) REFERENCES users(userID)
);

INSERT INTO users (fullName, email, passwordHash, role) VALUES
('System Administrator', 'admin@certverify.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Cavendish University Zambia', 'institution@certverify.com', '$2y$10$TKh8H1.PJy4hS9l2vwKrhu0bELBLdm.kS.8L3XhMX1vKqzQZa2oCm', 'institution');
