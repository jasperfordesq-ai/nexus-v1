-- Add video_url column for embedded YouTube videos on KB articles
ALTER TABLE knowledge_base_articles
  ADD COLUMN IF NOT EXISTS video_url VARCHAR(500) DEFAULT NULL AFTER content_type;
