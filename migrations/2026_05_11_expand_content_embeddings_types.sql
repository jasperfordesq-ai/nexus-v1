-- Migration: Expand content_embeddings.content_type ENUM
--
-- Adds kb_article, job, marketplace to support semantic search across the
-- full content surface used by the AI chat tool (Phase 2 vector RAG).
--
-- Idempotent — re-running ALTER TABLE ... MODIFY COLUMN with the full
-- ENUM list is safe.

ALTER TABLE content_embeddings
    MODIFY COLUMN content_type ENUM(
        'listing',
        'user',
        'event',
        'group',
        'kb_article',
        'job',
        'marketplace'
    ) NOT NULL;
