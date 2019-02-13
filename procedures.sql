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

  DELETE FROM `months_mv` WHERE `year_n` = current_year;
  INSERT INTO `months_mv` (`title`, `album`, `artist`, `image`, `year_n`, `month_n`)
    SELECT `title`, `album`, `artist`, `image`, `year_n`, `month_n`
    FROM (
      SELECT *, YEAR(`started_at`) AS year_n, MONTH(`started_at`) AS month_n,
       ROW_NUMBER() OVER(PARTITION BY year_n, month_n) AS rank
      FROM `track`
      WHERE valid = 1 AND image != ''
      HAVING year_n = current_year
    ) ranked
    WHERE rank <= 16
  ;

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
    IN current_year INT,
    IN current_month INT
)
BEGIN

  DELETE FROM `days_mv` WHERE `year_n` = current_year AND `month_n` = current_month;
  INSERT INTO `days_mv` (`title`, `album`, `artist`, `image`, `year_n`, `month_n`, `day_n`)
    SELECT `title`, `album`, `artist`, `image`, `year_n`, `month_n`, `day_n`
    FROM (
      SELECT *, YEAR(`started_at`) AS year_n, MONTH(`started_at`) AS month_n, DAY(`started_at`) AS day_n,
       ROW_NUMBER() OVER(PARTITION BY year_n, month_n, day_n) AS rank
      FROM `track` WHERE valid = 1 AND image != '' HAVING year_n = current_year AND month_n = current_month
    ) ranked
    WHERE rank <= 8
  ;

END;
$$

DELIMITER ;