<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Article Builder - <?= htmlspecialchars($post['title']) ?></title>
    <link rel="stylesheet" href="https://unpkg.com/grapesjs/dist/css/grapes.min.css">
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
    <!-- Mobile Warning Banner -->
    <div id="mobile-warning" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.95); z-index:9999; padding:40px 20px; text-align:center; color:white;">
        <div style="max-width:400px; margin:0 auto;">
            <div style="font-size:4rem; margin-bottom:20px;">💻</div>
            <h2 style="margin:0 0 15px; font-size:1.5rem;">Desktop Recommended</h2>
            <p style="color:#9ca3af; line-height:1.6; margin-bottom:30px;">
                The visual page builder works best on a desktop or laptop computer.
                The drag-and-drop interface requires a larger screen for the best experience.
            </p>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/news" style="display:inline-block; background:#6366f1; color:white; padding:12px 30px; border-radius:8px; text-decoration:none; font-weight:600;">
                &larr; Back to News
            </a>
            <button onclick="document.getElementById('mobile-warning').style.display='none'" style="display:block; margin:20px auto 0; background:transparent; border:1px solid #555; color:#9ca3af; padding:10px 20px; border-radius:6px; cursor:pointer;">
                Continue Anyway
            </button>
        </div>
    </div>
    <script>
        // Show warning on small screens
        if (window.innerWidth < 900) {
            document.getElementById('mobile-warning').style.display = 'block';
        }
    </script>

    <div class="editor-row">
        <div class="editor-canvas">
            <div id="gjs">
                <?= $post['html_render'] ?? $post['content'] ?? '' ?>
            </div>
        </div>
        <div class="panel__right">
            <div class="panel-top">
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/news" style="color:#ccc; text-decoration:none; margin-right:auto;">&larr; Back</a>
                <button class="btn-save" onclick="savePage()">Save Article</button>
            </div>

            <div class="settings-box">
                <label>Article Title</label>
                <input type="text" id="page-title" value="<?= htmlspecialchars($post['title']) ?>">

                <label>Slug</label>
                <input type="text" id="page-slug" value="<?= htmlspecialchars($post['slug']) ?>">

                <label>Category</label>
                <select id="page-category" style="width:100%; padding:5px; margin-bottom:10px; background:#222; color:white; border:1px solid #555;">
                    <option value="">-- None --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($post['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?> (<?= $cat['type'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Featured Image URL</label>
                <input type="text" id="page-image" value="<?= htmlspecialchars($post['featured_image'] ?? '') ?>" placeholder="https://...">

                <label>Excerpt / Summary</label>
                <textarea id="page-excerpt" style="width:100%; height:60px; background:#222; border:1px solid #555; color:white; margin-bottom:10px;"><?= htmlspecialchars($post['excerpt'] ?? '') ?></textarea>

                <div style="display:flex; gap:10px; margin-top:10px;">
                    <label><input type="checkbox" id="is-published" <?= ($post['status'] === 'published') ? 'checked' : '' ?>> Publish</label>
                </div>
            </div>

            <!-- SEO Settings -->
            <div class="settings-box">
                <h4 style="margin-top:0; color:#aaa; font-size:0.9rem;">SEO Override (Optional)</h4>

                <label>Meta Title</label>
                <input type="text" id="meta-title" value="<?= htmlspecialchars($seo['meta_title'] ?? '') ?>" placeholder="Custom Tab Title">

                <label>Meta Description</label>
                <textarea id="meta-description" style="width:100%; height:60px; background:#222; border:1px solid #555; color:white; margin-bottom:10px;"><?= htmlspecialchars($seo['meta_description'] ?? '') ?></textarea>

                <label><input type="checkbox" id="noindex" <?= !empty($seo['noindex']) ? 'checked' : '' ?>> NoIndex (Hide from Google)</label>
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
        <?php if (!empty($post['content_json'])): ?>
            try {
                editor.loadProjectData(<?= $post['content_json'] ?>);
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
            const categoryId = document.getElementById('page-category').value;
            const image = document.getElementById('page-image').value;
            const excerpt = document.getElementById('page-excerpt').value;
            const published = document.getElementById('is-published').checked ? 1 : 0;

            // SEO
            const metaTitle = document.getElementById('meta-title').value;
            const metaDesc = document.getElementById('meta-description').value;
            const noindex = document.getElementById('noindex').checked ? 1 : 0;

            const formData = new FormData();
            formData.append('id', <?= $post['id'] ?>);
            formData.append('title', title);
            formData.append('slug', slug);
            if (categoryId) formData.append('category_id', categoryId);
            formData.append('featured_image', image);
            formData.append('excerpt', excerpt);
            formData.append('html', `<style>${css}</style>${html}`);
            formData.append('json', json);
            if (published) formData.append('is_published', 1);

            // SEO Data
            formData.append('meta_title', metaTitle);
            formData.append('meta_description', metaDesc);
            if (noindex) formData.append('noindex', 1);

            const btn = document.querySelector('.btn-save');
            btn.innerText = 'Saving...';

            fetch(basePath + '/admin/news/save-builder', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    btn.innerText = 'Save Article';
                    if (data.success) alert('Saved successfully!');
                    else alert('Error: ' + JSON.stringify(data));
                });
        }
    </script>
</body>

</html>