CREATE TABLE IF NOT EXISTS `event_config` (
  `id` INT unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary Key: Event ID.',
  `event_name` VARCHAR(255) NOT NULL COMMENT 'Name of the event.',
  `category` VARCHAR(100) NOT NULL COMMENT 'Event category (Conference, Workshop, etc).',
  `event_date` VARCHAR(20) NOT NULL COMMENT 'Date of the event.',
  `start_date` VARCHAR(20) NOT NULL COMMENT 'Registration start date.',
  `end_date` VARCHAR(20) NOT NULL COMMENT 'Registration end date.',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Stores event definitions and configurations.';

CREATE TABLE IF NOT EXISTS `event_registrations` (
  `id` INT unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary Key: Registration ID.',
  `event_id` INT unsigned NOT NULL COMMENT 'Foreign Key: Event ID.',
  `full_name` VARCHAR(255) NOT NULL COMMENT 'Participant full name.',
  `email` VARCHAR(255) NOT NULL COMMENT 'Participant email address.',
  `college` VARCHAR(255) NOT NULL COMMENT 'Participant college/institute.',
  `department` VARCHAR(255) NOT NULL COMMENT 'Participant department.',
  `category` VARCHAR(100) NOT NULL COMMENT 'Category selected at time of registration.',
  `created_at` INT NOT NULL COMMENT 'Timestamp of registration.',
  PRIMARY KEY (`id`),
  INDEX `email_event` (`email`, `event_id`),
  CONSTRAINT `fk_event_id` FOREIGN KEY (`event_id`) REFERENCES `event_config` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Stores user registrations for events.';
