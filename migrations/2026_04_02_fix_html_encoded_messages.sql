-- Fix HTML-encoded entities in message body (bug: htmlspecialchars was applied before storage)
-- Decodes &#039; → ', &amp; → &, &quot; → ", &lt; → <, &gt; → >

UPDATE messages SET body = REPLACE(body, '&#039;', '''') WHERE body LIKE '%&#039;%';
UPDATE messages SET body = REPLACE(body, '&quot;', '"') WHERE body LIKE '%&quot;%';
UPDATE messages SET body = REPLACE(body, '&amp;', '&') WHERE body LIKE '%&amp;%';
UPDATE messages SET body = REPLACE(body, '&lt;', '<') WHERE body LIKE '%&lt;%';
UPDATE messages SET body = REPLACE(body, '&gt;', '>') WHERE body LIKE '%&gt;%';
