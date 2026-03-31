-- Knowledge Base Attachments table
-- Allows PDF, Markdown, DOCX, and other files to be attached to KB articles

CREATE TABLE IF NOT EXISTS `knowledge_base_attachments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `article_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_url` varchar(500) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(10) unsigned NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kb_attach_article_tenant` (`article_id`, `tenant_id`),
  KEY `idx_kb_attach_tenant` (`tenant_id`),
  CONSTRAINT `fk_kb_attach_article` FOREIGN KEY (`article_id`)
    REFERENCES `knowledge_base_articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
