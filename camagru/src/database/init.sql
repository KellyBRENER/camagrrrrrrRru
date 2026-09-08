CREATE DATABASE IF NOT EXISTS camagru 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE camagru;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    email_notifications BOOLEAN NOT NULL DEFAULT TRUE,
    token VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS photos (
    photo_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_photos_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_photos_user_id (user_id),
    INDEX idx_photos_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stickers (
    sticker_id VARCHAR(50) PRIMARY KEY,
    path VARCHAR(255) NOT NULL,
    width INT NOT NULL,
    height INT NOT NULL,
    hashtag VARCHAR(25) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_stickers_width_positive CHECK (width > 0),
    CONSTRAINT chk_stickers_height_positive CHECK (height > 0),
    INDEX idx_stickers_hashtag (hashtag),
    INDEX idx_stickers_is_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS photo_likes (
    photo_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (photo_id, user_id),
    CONSTRAINT fk_photo_likes_photo
        FOREIGN KEY (photo_id)
        REFERENCES photos(photo_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_photo_likes_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_photo_likes_user_id (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    photo_id INT NOT NULL,
    user_id INT NOT NULL,
    comment VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_comments_photo
        FOREIGN KEY (photo_id)
        REFERENCES photos(photo_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_comments_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_comments_photo_id (photo_id),
    INDEX idx_comments_user_id (user_id),
    INDEX idx_comments_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hashtags (
    hashtag_id INT AUTO_INCREMENT PRIMARY KEY,
    hashtag VARCHAR(25) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hashtags_hashtag (hashtag)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS photo_hashtags (
    photo_id INT NOT NULL,
    hashtag_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (photo_id, hashtag_id),
    CONSTRAINT fk_photo_hashtags_photo
        FOREIGN KEY (photo_id)
        REFERENCES photos(photo_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_photo_hashtags_hashtag
        FOREIGN KEY (hashtag_id)
        REFERENCES hashtags(hashtag_id)
        ON DELETE CASCADE,
    INDEX idx_photo_hashtags_hashtag_id (hashtag_id)
) ENGINE=InnoDB;

INSERT INTO stickers (sticker_id, path, width, height, hashtag)
VALUES
    ('leopard-sticker', 'images/filters/stickers/leopard-sticker.png', 1537, 1023, 'leopard'),
    ('leopard-cub-sticker', 'images/filters/stickers/leopard-cub-sticker.png', 1015, 989, 'leopard'),
    ('lion-profile-sticker', 'images/filters/stickers/lion-profile-sticker.png', 963, 1479, 'lion'),
    ('kitten-sticker', 'images/filters/stickers/kitten-sticker.png', 1070, 1070, 'chaton'),
    ('simba-friends-sticker', 'images/filters/stickers/simba-friends-sticker.png', 640, 342, 'savane'),
    ('black-cat-sticker', 'images/filters/stickers/black-cat-sticker.png', 1222, 886, 'chat-noir'),
    ('angry-cat-sticker', 'images/filters/stickers/angry-cat-sticker.png', 1346, 804, 'chat-furieux'),
    ('scared-kitten-sticker', 'images/filters/stickers/scared-kitten-sticker.png', 1296, 868, 'chat-herisse'),
    ('meowing-cat-sticker', 'images/filters/stickers/meowing-cat-sticker.png', 768, 1404, 'chat-miauleur')
ON DUPLICATE KEY UPDATE
    path = VALUES(path),
    width = VALUES(width),
    height = VALUES(height),
    hashtag = VALUES(hashtag),
    is_active = TRUE;
