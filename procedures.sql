 -- For YEAR ARCHIVES
CREATE TABLE IF NOT EXISTS years_mv (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `year_n` INT(11) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `album` VARCHAR(255) DEFAULT NULL,
    `artist` VARCHAR(255) DEFAULT NULL,
    `image` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`id`)
);

DROP PROCEDURE IF EXISTS refresh_years_mv_now;

DELIMITER $$

CREATE PROCEDURE refresh_years_mv_now (
    IN current_year INT
)
BEGIN

  DECLARE selects MEDIUMTEXT;

  SET selects = CONCAT('(SELECT t.title, t.album, t.artist, t.image, ', current_year, ' as year_n FROM track AS t WHERE t.valid = 1 AND t.image != \'\' AND YEAR(t.started_at) = ', current_year, ' LIMIT 16)');

  SET selects = CONCAT('INSERT INTO `years_mv` (`title`, `album`, `artist`, `image`, `year_n`) ', selects);
  DELETE FROM `years_mv` WHERE `year_n` = current_year;
  PREPARE stmt FROM selects;
  EXECUTE stmt;

END;
$$

DELIMITER ;

 -- For MONTH ARCHIVES
CREATE TABLE IF NOT EXISTS months_mv (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `year_n` INT(11) NOT NULL,
    `month_n` INT(11) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `album` VARCHAR(255) DEFAULT NULL,
    `artist` VARCHAR(255) DEFAULT NULL,
    `image` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`id`)
);

DROP PROCEDURE IF EXISTS refresh_months_mv_now;

DELIMITER $$

CREATE PROCEDURE refresh_months_mv_now (
    IN current_year INT
)
BEGIN

  DECLARE current_month INT;
  DECLARE selects LONGTEXT;

  SET selects =  '';
  SET current_month = 1;
 
  WHILE current_month <= 12 DO
      SET selects = CONCAT(selects, '(SELECT t.title, t.album, t.artist, t.image, ', current_year, ' as year_n, ', current_month, ' as month_n FROM track AS t WHERE t.valid = 1 AND t.image != \'\' AND YEAR(t.started_at) = ', current_year, ' AND MONTH(t.started_at) = ', current_month, ' LIMIT 16)');

      IF current_month < 12 THEN 
        SET selects = CONCAT(selects, ' UNION ALL ');
      END  IF;
      SET current_month = current_month + 1; 
  END WHILE;

  SET selects = CONCAT('INSERT INTO `months_mv` (`title`, `album`, `artist`, `image`, `year_n`, `month_n`) ', selects);
  DELETE FROM `months_mv` WHERE `year_n` = current_year;
  PREPARE stmt FROM selects;
  EXECUTE stmt;

END;
$$

DELIMITER ;

 -- For DAY ARCHIVES
CREATE TABLE IF NOT EXISTS days_mv (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `year_n` INT(11) NOT NULL,
    `month_n` INT(11) NOT NULL,
    `day_n` INT(11) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `album` VARCHAR(255) DEFAULT NULL,
    `artist` VARCHAR(255) DEFAULT NULL,
    `image` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`id`)
);

DROP PROCEDURE IF EXISTS refresh_days_mv_now;

DELIMITER $$

CREATE PROCEDURE refresh_days_mv_now (
    IN current_year INT
)
BEGIN

  DECLARE current_month INT;
  DECLARE current_day INT;
  DECLARE selects LONGTEXT;

  SET selects =  '';
  SET current_month = 1;

  WHILE current_month <= 12 DO
      SET current_day = 1;

      WHILE current_day <= 31 DO
          SET selects = CONCAT(selects, '(SELECT t.title, t.album, t.artist, t.image, ', current_year, ' as year_n, ', current_month, ' as month_n, ', current_day, ' as day_n FROM track AS t WHERE t.valid = 1 AND t.image != \'\' AND YEAR(t.started_at) = ', current_year, ' AND MONTH(t.started_at) = ', current_month, ' AND DAY(t.started_at) = ', current_day, ' LIMIT 8)');

          IF current_day < 31 THEN 
            SET selects = CONCAT(selects, ' UNION ALL ');
          END IF;
          SET current_day = current_day + 1; 
      END WHILE;

      IF current_month < 12 THEN 
          SET selects = CONCAT(selects, ' UNION ALL ');
      END IF;
      SET current_month = current_month + 1; 
  END WHILE;

  SET selects = CONCAT('INSERT INTO `days_mv` (`title`, `album`, `artist`, `image`, `year_n`, `month_n`, `day_n`) ', selects);
  DELETE FROM `days_mv` WHERE `year_n` = current_year;
  PREPARE stmt FROM selects;
  EXECUTE stmt;

END;
$$

DELIMITER ;