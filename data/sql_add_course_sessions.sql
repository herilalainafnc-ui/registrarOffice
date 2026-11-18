-- Migration: add course sessions mapping table
-- Run this SQL in your database (via phpMyAdmin or CLI)

CREATE TABLE IF NOT EXISTS `t_2023_course_sessions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id` INT NOT NULL,
  `session_label` VARCHAR(100) NOT NULL,
  `academic_year` VARCHAR(50) NOT NULL,
  `offered` TINYINT(1) NOT NULL DEFAULT 1,
  `date_entry` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_change` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_course_session_year` (`course_id`,`session_label`,`academic_year`),
  INDEX (`course_id`)
);

-- Example insert (mark all current courses offered for default current academic year and premier/deuxième semestre).
-- INSERT INTO t_2023_course_sessions (course_id, session_label, academic_year, offered)
-- SELECT id, 'Premier semestre', CONCAT(YEAR(CURDATE())-1, ' - ', YEAR(CURDATE())) , 1 FROM t_2023_cours;
