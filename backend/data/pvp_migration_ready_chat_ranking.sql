ALTER TABLE `pvp_matches`
  MODIFY `status` enum('waiting','ready','starting','playing','finished') NOT NULL DEFAULT 'waiting',
  ADD COLUMN IF NOT EXISTS `ready_deadline_at` datetime DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `starts_at` datetime DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `ranking_applied` tinyint(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `ranking_changes_json` text DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `post_match_choices_json` text DEFAULT NULL;
