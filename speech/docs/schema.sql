CREATE DATABASE IF NOT EXISTS speech_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE speech_db;

CREATE TABLE campuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE speakers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    affiliation VARCHAR(255),
    position VARCHAR(100)
);

CREATE TABLE videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content_path VARCHAR(500) NOT NULL,
    thumbnail_path VARCHAR(500),
    event_date DATE,
    campus_id INT,
    speaker_id INT,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campus_id) REFERENCES campuses(id),
    FOREIGN KEY (speaker_id) REFERENCES speakers(id)
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255),
    display_name VARCHAR(100),
    email VARCHAR(255),
    role ENUM('member', 'manager') DEFAULT 'member',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Initial Data
INSERT INTO campuses (name) VALUES ('大林'), ('花蓮'), ('台中'), ('台北'), ('法人');
