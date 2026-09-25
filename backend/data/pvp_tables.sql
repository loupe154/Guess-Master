CREATE TABLE IF NOT EXISTS `pvp_matches` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(8) NOT NULL,
  `mode` varchar(30) NOT NULL DEFAULT 'game',
  `status` enum('waiting','ready','starting','playing','finished') NOT NULL DEFAULT 'waiting',
  `host_user_id` int(10) UNSIGNED NOT NULL,
  `guest_user_id` int(10) UNSIGNED DEFAULT NULL,
  `current_round` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `max_rounds` int(10) UNSIGNED NOT NULL DEFAULT 5,
  `round_duration` int(10) UNSIGNED NOT NULL DEFAULT 60,
  `winner_user_id` int(10) UNSIGNED DEFAULT NULL,
  `ready_deadline_at` datetime DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ranking_applied` tinyint(1) NOT NULL DEFAULT 0,
  `ranking_changes_json` text DEFAULT NULL,
  `post_match_choices_json` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `host_user_id` (`host_user_id`),
  KEY `guest_user_id` (`guest_user_id`),
  KEY `winner_user_id` (`winner_user_id`),
  CONSTRAINT `pvp_matches_host_fk` FOREIGN KEY (`host_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pvp_matches_guest_fk` FOREIGN KEY (`guest_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pvp_matches_winner_fk` FOREIGN KEY (`winner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pvp_ready` (
  `match_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `ready_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`match_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `pvp_ready_match_fk` FOREIGN KEY (`match_id`) REFERENCES `pvp_matches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pvp_ready_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pvp_rounds` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `match_id` int(10) UNSIGNED NOT NULL,
  `round_number` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `image` text NOT NULL,
  `hint` varchar(255) NOT NULL,
  `answers_json` text NOT NULL,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ends_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `match_round` (`match_id`,`round_number`),
  CONSTRAINT `pvp_rounds_match_fk` FOREIGN KEY (`match_id`) REFERENCES `pvp_matches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pvp_answers` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `match_id` int(10) UNSIGNED NOT NULL,
  `round_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `answer` varchar(255) NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `points` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `match_id` (`match_id`),
  KEY `round_id` (`round_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `pvp_answers_match_fk` FOREIGN KEY (`match_id`) REFERENCES `pvp_matches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pvp_answers_round_fk` FOREIGN KEY (`round_id`) REFERENCES `pvp_rounds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pvp_answers_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pvp_chat_messages` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `match_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `message` varchar(300) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `match_id` (`match_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `pvp_chat_match_fk` FOREIGN KEY (`match_id`) REFERENCES `pvp_matches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pvp_chat_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pvp_rankings` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `mode` varchar(30) NOT NULL,
  `points` int NOT NULL DEFAULT 0,
  `wins` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `losses` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`,`mode`),
  KEY `mode_points` (`mode`,`points`),
  CONSTRAINT `pvp_rankings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
