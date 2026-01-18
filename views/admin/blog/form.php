<?php
// Layout: Default (Admin)
$layout = 'default';
$isEdit = isset($post);
$basePath = \Nexus\Core\TenantContext::getBasePath();
$action = $isEdit ? $basePath . "/admin/blog/update/" . $post['id'] : $basePath . "/admin/blog/store";
?>

<div class="nexus-container" style="max-width: 800px;">
    <div style="margin-bottom: 30px;">
        <a href="<?= $basePath ?>/admin/blog" class="nexus-link">&larr; Back to Articles</a>
        <h1 style="margin-top: 10px;"><?= $isEdit ? 'Edit Article' : 'Write New Article' ?></h1>
    </div>

    <div class="nexus-card">
        <form action="<?= $action ?>" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>

            <!-- Title with AI -->
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                    <label style="font-weight:600;">Title</label>
                    <button type="button" onclick="generateBlogTitle()" class="ai-gen-btn" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: 500; display: flex; align-items: center; gap: 4px;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Generate
                    </button>
                </div>
                <input type="text" name="title" id="blog-title" required value="<?= $isEdit ? htmlspecialchars($post['title']) : '' ?>"
                    class="nexus-input" style="width: 100%; font-size: 1.2rem; padding: 12px;">
                <div id="ai-title-suggestions" style="display: none; margin-top: 10px; background: #f5f3ff; border: 1px solid #c4b5fd; border-radius: 8px; padding: 10px;"></div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display:block; font-weight:600; margin-bottom:5px;">Slug (URL)</label>
                <input type="text" name="slug" placeholder="auto-generated-from-title" value="<?= $isEdit ? htmlspecialchars($post['slug']) : '' ?>"
                    class="nexus-input" style="width: 100%; background: #f9fafb;">
                <small style="color:#6b7280;">Leave empty to auto-generate.</small>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display:block; font-weight:600; margin-bottom:5px;">Category</label>
                <select name="category_id" id="blog-category" class="nexus-input" style="width: 100%;">
                    <option value="">-- No Category --</option>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($isEdit && $cat['id'] == $post['category_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display:block; font-weight:600; margin-bottom:5px;">Featured Image URL</label>
                <input type="url" name="featured_image" placeholder="https://..." value="<?= $isEdit ? htmlspecialchars($post['featured_image']) : '' ?>"
                    class="nexus-input" style="width: 100%;">
            </div>

            <!-- Excerpt with AI -->
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                    <label style="font-weight:600;">Excerpt</label>
                    <button type="button" onclick="generateBlogExcerpt()" class="ai-gen-btn" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: 500; display: flex; align-items: center; gap: 4px;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Generate
                    </button>
                </div>
                <textarea name="excerpt" id="blog-excerpt" rows="3" class="nexus-input" style="width: 100%;"><?= $isEdit ? htmlspecialchars($post['excerpt']) : '' ?></textarea>
                <small style="color:#6b7280;">Short summary for the index page.</small>
            </div>

            <!-- Content with AI -->
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                    <label style="font-weight:600;">Content (HTML supported)</label>
                    <button type="button" onclick="generateBlogContent()" class="ai-gen-btn" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: 500; display: flex; align-items: center; gap: 4px;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Generate Article
                    </button>
                </div>
                <textarea name="content" id="blog-content" rows="15" required class="nexus-input" style="width: 100%; font-family: monospace;"><?= $isEdit ? htmlspecialchars($post['content']) : '' ?></textarea>
            </div>

            <div style="margin-bottom: 30px;">
                <label style="display:block; font-weight:600; margin-bottom:5px;">Status</label>
                <select name="status" class="nexus-input" style="width: 200px;">
                    <option value="draft" <?= ($isEdit && $post['status'] === 'draft') ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= ($isEdit && $post['status'] === 'published') ? 'selected' : '' ?>>Published</option>
                </select>
            </div>

            <!-- SEO Section with AI -->
            <div style="margin-bottom: 30px; border: 1px solid #e5e7eb; padding: 20px; border-radius: 8px; background: #f9fafb;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0;">SEO Settings (Optional)</h3>
                    <button type="button" onclick="generateBlogSEO()" class="ai-gen-btn" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: 500; display: flex; align-items: center; gap: 4px;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Generate SEO
                    </button>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display:block; font-weight:600; font-size: 0.9rem;">Meta Title</label>
                    <input type="text" name="meta_title" id="meta-title" value="<?= htmlspecialchars($seo['meta_title'] ?? '') ?>"
                        class="nexus-input" style="width: 100%;" placeholder="Custom search result title">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display:block; font-weight:600; font-size: 0.9rem;">Meta Description</label>
                    <textarea name="meta_description" id="meta-description" rows="2" class="nexus-input" style="width: 100%;" placeholder="Summary for search engines"><?= htmlspecialchars($seo['meta_description'] ?? '') ?></textarea>
                </div>

                <div>
                    <label style="font-size: 0.9rem;">
                        <input type="checkbox" name="noindex" value="1" <?= !empty($seo['noindex']) ? 'checked' : '' ?>>
                        Hide this article from search engines (NoIndex)
                    </label>
                </div>
            </div>

            <div style="display:flex; justify-content: flex-end; gap: 10px;">
                <?php if ($isEdit): ?>
                    <button type="button" onclick="if(confirm('Delete this post?')) location.href='<?= $basePath ?>/admin/blog/delete/<?= $post['id'] ?>'" class="nexus-btn-danger" style="margin-right: auto; background:none; color: red; border:none; cursor:pointer;">Delete Post</button>
                <?php endif; ?>

                <a href="<?= $basePath ?>/admin/blog" class="nexus-btn-secondary">Cancel</a>
                <button type="submit" class="nexus-btn-primary"><?= $isEdit ? 'Update Article' : 'Publish Article' ?></button>
            </div>
        </form>
    </div>
</div>

<!-- AI Generation Functions -->
<script>
const basePath = '<?= $basePath ?>';

// Generate Blog Title
async function generateBlogTitle() {
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    btn.disabled = true;

    const categorySelect = document.getElementById('blog-category');
    const category = categorySelect?.options[categorySelect.selectedIndex]?.text || '';

    try {
        const response = await fetch(basePath + '/api/ai/generate/blog', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'title',
                context: { category: category }
            })
        });

        const data = await response.json();
        if (data.success && data.content) {
            const suggestions = data.content.split('\n').filter(line => line.trim());
            showTitleSuggestions(suggestions);
        } else {
            alert('Error: ' + (data.error || 'Could not generate titles'));
        }
    } catch (error) {
        console.error('AI Title generation error:', error);
        alert('Failed to generate titles.');
    } finally {
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    }
}

function showTitleSuggestions(suggestions) {
    const container = document.getElementById('ai-title-suggestions');
    if (!container) return;

    let html = '<div style="font-size: 0.8rem; font-weight: 600; color: #4f46e5; margin-bottom: 8px;">✨ Click to use:</div>';
    suggestions.forEach(suggestion => {
        const clean = suggestion.replace(/^\d+[\.\)]\s*/, '').trim();
        if (clean) {
            html += `<button type="button" onclick="useTitleSuggestion(this)" data-title="${clean.replace(/"/g, '&quot;')}"
                style="display: block; width: 100%; text-align: left; background: white; border: 1px solid #e9d5ff; padding: 8px 12px; border-radius: 6px; margin-bottom: 6px; cursor: pointer; color: #374151; font-size: 0.85rem; transition: all 0.2s;"
                onmouseover="this.style.borderColor='#8b5cf6'; this.style.background='#faf5ff';"
                onmouseout="this.style.borderColor='#e9d5ff'; this.style.background='white';">
                ${clean}
            </button>`;
        }
    });
    container.innerHTML = html;
    container.style.display = 'block';
}

function useTitleSuggestion(btn) {
    document.getElementById('blog-title').value = btn.dataset.title;
    document.getElementById('ai-title-suggestions').style.display = 'none';
}

// Generate Blog Excerpt
async function generateBlogExcerpt() {
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    btn.disabled = true;

    const title = document.getElementById('blog-title')?.value || '';
    const content = document.getElementById('blog-content')?.value || '';

    try {
        const response = await fetch(basePath + '/api/ai/generate/blog', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'excerpt',
                context: { title: title, existing_content: content }
            })
        });

        const data = await response.json();
        if (data.success && data.content) {
            document.getElementById('blog-excerpt').value = data.content.trim();
        } else {
            alert('Error: ' + (data.error || 'Could not generate excerpt'));
        }
    } catch (error) {
        console.error('AI Excerpt generation error:', error);
        alert('Failed to generate excerpt.');
    } finally {
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    }
}

// Generate Blog Content
async function generateBlogContent() {
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';
    btn.disabled = true;

    const title = document.getElementById('blog-title')?.value || '';
    const categorySelect = document.getElementById('blog-category');
    const category = categorySelect?.options[categorySelect.selectedIndex]?.text || '';

    if (!title) {
        alert('Please enter a title first to generate relevant content.');
        btn.innerHTML = originalHTML;
        btn.disabled = false;
        return;
    }

    try {
        const response = await fetch(basePath + '/api/ai/generate/blog', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'content',
                context: { title: title, category: category }
            })
        });

        const data = await response.json();
        if (data.success && data.content) {
            document.getElementById('blog-content').value = data.content;
        } else {
            alert('Error: ' + (data.error || 'Could not generate content'));
        }
    } catch (error) {
        console.error('AI Content generation error:', error);
        alert('Failed to generate content.');
    } finally {
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    }
}

// Generate SEO
async function generateBlogSEO() {
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    btn.disabled = true;

    const title = document.getElementById('blog-title')?.value || '';
    const content = document.getElementById('blog-content')?.value || '';

    try {
        const response = await fetch(basePath + '/api/ai/generate/blog', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'seo',
                context: { title: title, existing_content: content }
            })
        });

        const data = await response.json();
        if (data.success && data.content) {
            // Parse the SEO response
            const lines = data.content.split('\n');
            lines.forEach(line => {
                if (line.startsWith('META_TITLE:')) {
                    document.getElementById('meta-title').value = line.replace('META_TITLE:', '').trim();
                } else if (line.startsWith('META_DESCRIPTION:')) {
                    document.getElementById('meta-description').value = line.replace('META_DESCRIPTION:', '').trim();
                }
            });
        } else {
            alert('Error: ' + (data.error || 'Could not generate SEO'));
        }
    } catch (error) {
        console.error('AI SEO generation error:', error);
        alert('Failed to generate SEO.');
    } finally {
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    }
}
</script>
