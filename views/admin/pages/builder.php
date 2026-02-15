<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Page Builder - <?= htmlspecialchars($page['title']) ?></title>
    <link rel="stylesheet" href="https://unpkg.com/grapesjs/dist/css/grapes.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/grapesjs"></script>
    <script src="https://unpkg.com/grapesjs-preset-webpage"></script>
    <link rel="stylesheet" href="https://unpkg.com/grapesjs-preset-webpage/dist/grapesjs-preset-webpage.min.css">
    <style>
        body,
        html {
            margin: 0;
            padding: 0;
            height: 100%;
        }

        #gjs {
            height: 100%;
            border: 3px solid #444;
        }

        .panel-top {
            padding: 10px;
            background: #333;
            color: white;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .panel-top input {
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #555;
            background: #222;
            color: white;
        }

        .btn-save {
            background: #6366f1;
            color: white;
            border: none;
            padding: 8px 15px;
            cursor: pointer;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="editor-row">
        <div class="editor-canvas">
            <div id="gjs">
                <?= $page['html_render'] ?? '' ?>
            </div>
        </div>
        <div class="panel__right">
            <div class="panel-top">
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin-legacy/pages" style="color:#ccc; text-decoration:none; margin-right:auto;">&larr; Back</a>
                <button class="btn-save" onclick="savePage()">Save</button>
            </div>

            <div class="settings-box">
                <label>Page Title</label>
                <input type="text" id="page-title" value="<?= htmlspecialchars($page['title']) ?>">

                <label>Slug</label>
                <input type="text" id="page-slug" value="<?= htmlspecialchars($page['slug']) ?>">

                <div style="display:flex; gap:10px; margin-top:10px;">
                    <label><input type="checkbox" id="is-published" <?= ($page['is_published']) ? 'checked' : '' ?>> Publish</label>
                    <label><input type="checkbox" id="is-front-page"> Front Page</label>
                </div>
            </div>

            <!-- SEO Settings -->
            <div class="settings-box">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h4 style="margin:0; color:#aaa; font-size:0.9rem;">SEO Override (Optional)</h4>
                    <button type="button" onclick="generatePageSEO()" class="ai-gen-btn" title="Generate SEO with AI">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                    </button>
                </div>

                <label>Meta Title</label>
                <input type="text" id="meta-title" value="<?= htmlspecialchars($seo['meta_title'] ?? '') ?>" placeholder="Custom Tab Title">

                <label>Meta Description</label>
                <textarea id="meta-description" style="width:100%; height:60px; background:#222; border:1px solid #555; color:white; margin-bottom:10px;"><?= htmlspecialchars($seo['meta_description'] ?? '') ?></textarea>

                <label><input type="checkbox" id="noindex" <?= !empty($seo['noindex']) ? 'checked' : '' ?>> NoIndex (Hide from Google)</label>
            </div>

            <!-- AI Content Generation -->
            <div class="settings-box ai-section">
                <h4 style="margin-top:0; color:#a78bfa; font-size:0.9rem; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> AI Content Generator
                </h4>

                <label>Describe what you want:</label>
                <textarea id="ai-prompt" placeholder="e.g., A hero section about our timebank community with a welcome message..." style="width:100%; height:70px; background:#222; border:1px solid #555; color:white; margin-bottom:10px; font-size: 0.85rem;"></textarea>

                <div class="ai-buttons">
                    <button type="button" onclick="generateAISection('hero')" class="ai-section-btn">
                        <i class="fa-solid fa-star"></i> Hero Section
                    </button>
                    <button type="button" onclick="generateAISection('features')" class="ai-section-btn">
                        <i class="fa-solid fa-th-large"></i> Features Grid
                    </button>
                    <button type="button" onclick="generateAISection('cta')" class="ai-section-btn">
                        <i class="fa-solid fa-bullhorn"></i> Call to Action
                    </button>
                    <button type="button" onclick="generateAISection('text')" class="ai-section-btn">
                        <i class="fa-solid fa-align-left"></i> Text Section
                    </button>
                    <button type="button" onclick="generateAISection('testimonials')" class="ai-section-btn">
                        <i class="fa-solid fa-quote-left"></i> Testimonials
                    </button>
                    <button type="button" onclick="generateAISection('faq')" class="ai-section-btn">
                        <i class="fa-solid fa-question-circle"></i> FAQ Section
                    </button>
                </div>

                <div id="ai-status" style="display:none; margin-top:10px; padding:8px; border-radius:4px; font-size:0.8rem;"></div>
            </div>

            <div id="blocks"></div>
        </div>
    </div>

    <style>
        body,
        html {
            margin: 0;
            height: 100%;
            overflow: hidden;
            background: #222;
            color: #ddd;
            font-family: sans-serif;
        }

        .editor-row {
            display: flex;
            height: 100%;
        }

        .editor-canvas {
            flex-grow: 1;
        }

        .panel__right {
            width: 250px;
            background: #333;
            border-left: 1px solid #444;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .panel-top {
            padding: 10px;
            border-bottom: 1px solid #444;
            display: flex;
            align-items: center;
        }

        .settings-box {
            padding: 15px;
            border-bottom: 1px solid #444;
        }

        .settings-box input[type="text"] {
            width: 100%;
            margin-bottom: 10px;
            padding: 5px;
            background: #222;
            border: 1px solid #555;
            color: white;
        }

        .btn-save {
            background: #6366f1;
            color: white;
            border: none;
            padding: 5px 15px;
            cursor: pointer;
            border-radius: 4px;
            font-weight: bold;
        }

        /* GrapesJS Overrides */
        .gjs-block {
            width: auto;
            height: auto;
            min-height: auto;
        }

        /* AI Section Styles */
        .ai-section {
            background: linear-gradient(180deg, rgba(139, 92, 246, 0.1) 0%, transparent 100%);
            border-top: 2px solid #8b5cf6;
        }

        .ai-gen-btn {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.7rem;
            transition: all 0.2s;
        }

        .ai-gen-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.4);
        }

        .ai-gen-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .ai-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }

        .ai-section-btn {
            background: #2d2d2d;
            border: 1px solid #444;
            color: #ccc;
            padding: 8px 6px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.7rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }

        .ai-section-btn:hover {
            background: #3d3d3d;
            border-color: #8b5cf6;
            color: #fff;
        }

        .ai-section-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .ai-section-btn i {
            font-size: 1rem;
            color: #8b5cf6;
        }

        #ai-status.loading {
            background: rgba(139, 92, 246, 0.2);
            color: #a78bfa;
            border: 1px solid #8b5cf6;
        }

        #ai-status.success {
            background: rgba(16, 185, 129, 0.2);
            color: #6ee7b7;
            border: 1px solid #10b981;
        }

        #ai-status.error {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border: 1px solid #ef4444;
        }
    </style>

    <script>
        const basePath = "<?= Nexus\Core\TenantContext::getBasePath() ?>"; // Inject PHP Path into JS

        const editor = grapesjs.init({
            container: '#gjs',
            height: '100%',
            fromElement: true,
            storageManager: false,
            plugins: ['gjs-preset-webpage'], // Use The Preset
            pluginsOpts: {
                'gjs-preset-webpage': {
                    modalImportTitle: 'Import',
                    modalImportLabel: '<div style="margin-bottom: 10px; font-size: 13px;">Paste your HTML/CSS here</div>',
                    modalImportContent: function(editor) {
                        return editor.getHtml() + '<style>' + editor.getCss() + '</style>'
                    },
                }
            },
            assetManager: {
                upload: basePath + '/api/upload',
                uploadName: 'files',
                autoAdd: 1,
            },
            blockManager: {
                appendTo: '#blocks',
            },
            // Let the preset handle panels or customize if needed
        });

        // Load previous JSON if available
        <?php if (!empty($page['content_json'])): ?>
            try {
                editor.loadProjectData(<?= $page['content_json'] ?>);
            } catch (e) {
                console.error('Load Error', e);
            }
        <?php endif; ?>

        // Smart Blocks
        const bm = editor.BlockManager;

        // Members Grid
        bm.add('members-grid', {
            label: 'Members Grid (3 Col)',
            content: `
                <div class="smart-block-wrapper" style="padding:20px; text-align:center; border:2px dashed #6366f1; background:rgba(99,102,241,0.1); color:#fff; margin-bottom:20px;">
                    <div class="smart-block" data-type="members-grid" style="font-weight:bold;">[Members Grid: Display 6]</div>
                </div>`,
            category: 'Smart Modules',
            attributes: {
                class: 'gjs-fonts gjs-f-b1'
            }
        });

        // Hubs Grid
        bm.add('groups-grid', {
            label: 'Hubs Grid (3 Col)',
            content: `
                <div class="smart-block-wrapper" style="padding:20px; text-align:center; border:2px dashed #10b981; background:rgba(16,185,129,0.1); color:#fff; margin-bottom:20px;">
                    <div class="smart-block" data-type="groups-grid" style="font-weight:bold;">[Hubs Grid: Display 6]</div>
                </div>`,
            category: 'Smart Modules',
            attributes: {
                class: 'gjs-fonts gjs-f-h1p'
            }
        });

        // Listings Grid
        bm.add('listings-grid', {
            label: 'Listings Grid (3 Col)',
            content: `
                <div class="smart-block-wrapper" style="padding:20px; text-align:center; border:2px dashed #ec4899; background:rgba(236,72,153,0.1); color:#fff; margin-bottom:20px;">
                    <div class="smart-block" data-type="listings-grid" style="font-weight:bold;">[Listings Grid: Display 6]</div>
                </div>`,
            category: 'Smart Modules',
            attributes: {
                class: 'gjs-fonts gjs-f-text'
            }
        });

        // Standard Text Section
        bm.add('section-text', {
            label: 'Text Section',
            category: 'Basic',
            content: `<section style="padding:50px 20px; text-align:center;">
                <h2>Headline Here</h2>
                <p>Add your content here...</p>
            </section>`
        });

        function savePage() {
            const html = editor.getHtml();
            const css = editor.getCss();
            const json = JSON.stringify(editor.getProjectData());

            const title = document.getElementById('page-title').value;
            const slug = document.getElementById('page-slug').value;
            // Removed order from UI to save space, default to 0
            const published = document.getElementById('is-published').checked ? 1 : 0;
            const front = document.getElementById('is-front-page').checked ? 1 : 0;

            // SEO
            const metaTitle = document.getElementById('meta-title').value;
            const metaDesc = document.getElementById('meta-description').value;
            const noindex = document.getElementById('noindex').checked ? 1 : 0;

            const formData = new FormData();
            formData.append('id', <?= $page['id'] ?>);
            formData.append('title', title);
            formData.append('slug', slug);
            formData.append('html', `<style>${css}</style>${html}`);
            formData.append('json', json);
            if (published) formData.append('is_published', 1);
            if (front) formData.append('is_front_page', 1);

            // SEO Data
            formData.append('meta_title', metaTitle);
            formData.append('meta_description', metaDesc);
            if (noindex) formData.append('noindex', 1);

            const btn = document.querySelector('.btn-save');
            btn.innerText = 'Saving...';

            fetch(basePath + '/admin-legacy/pages/save', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    btn.innerText = 'Save';
                    if (data.success) alert('Saved successfully!');
                    else alert('Error: ' + JSON.stringify(data));
                });
        }

        // AI Content Generation Functions
        function showAIStatus(message, type = 'loading') {
            const status = document.getElementById('ai-status');
            status.textContent = message;
            status.className = type;
            status.style.display = 'block';
        }

        function hideAIStatus() {
            document.getElementById('ai-status').style.display = 'none';
        }

        function setAIButtonsDisabled(disabled) {
            document.querySelectorAll('.ai-section-btn, .ai-gen-btn').forEach(btn => {
                btn.disabled = disabled;
            });
        }

        // Generate AI Section
        async function generateAISection(sectionType) {
            const prompt = document.getElementById('ai-prompt').value.trim();
            const pageTitle = document.getElementById('page-title').value;

            if (!prompt && sectionType !== 'text') {
                alert('Please describe what you want the AI to generate.');
                document.getElementById('ai-prompt').focus();
                return;
            }

            setAIButtonsDisabled(true);
            showAIStatus('Generating ' + sectionType + ' section...', 'loading');

            try {
                const response = await fetch(basePath + '/api/ai/generate/page', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        type: sectionType,
                        context: {
                            prompt: prompt,
                            page_title: pageTitle,
                            existing_content: editor.getHtml().substring(0, 500)
                        }
                    })
                });

                const data = await response.json();

                if (data.success && data.content) {
                    // Add the generated HTML to the editor
                    const wrapper = editor.getWrapper();
                    const component = wrapper.append(data.content);

                    showAIStatus('Section added! You can edit it in the canvas.', 'success');
                    setTimeout(hideAIStatus, 3000);

                    // Clear the prompt
                    document.getElementById('ai-prompt').value = '';
                } else {
                    showAIStatus('Error: ' + (data.error || 'Could not generate content'), 'error');
                }
            } catch (error) {
                console.error('AI Section generation error:', error);
                showAIStatus('Failed to generate content. Please try again.', 'error');
            } finally {
                setAIButtonsDisabled(false);
            }
        }

        // Generate SEO
        async function generatePageSEO() {
            const btn = event.target.closest('button');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            btn.disabled = true;

            const pageTitle = document.getElementById('page-title').value;
            const pageContent = editor.getHtml();

            try {
                const response = await fetch(basePath + '/api/ai/generate/page', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        type: 'seo',
                        context: {
                            page_title: pageTitle,
                            existing_content: pageContent.substring(0, 2000)
                        }
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
</body>

</html>