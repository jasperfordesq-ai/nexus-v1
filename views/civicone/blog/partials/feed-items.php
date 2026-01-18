<?php
// Partial: Feed Items
// Receives $posts array

$basePath = \Nexus\Core\TenantContext::getBasePath();

foreach ($posts as $post):
    $wordCount = str_word_count(strip_tags($post['content']));
    $readingTime = max(1, ceil($wordCount / 200));
?>
    <article class="news-card">
        <a href="<?= $basePath ?>/blog/<?= $post['slug'] ?>" class="news-card-link" style="text-decoration: none;">
            <div class="news-card-image">
                <?php if ($post['featured_image']): ?>
                    <img src="<?= htmlspecialchars($post['featured_image']) ?>" loading="lazy" alt="<?= htmlspecialchars($post['title']) ?>" loading="lazy">
                <?php else: ?>
                    <div class="news-card-image-placeholder">
                        <i class="fa-solid fa-newspaper"></i>
                    </div>
                <?php endif; ?>
            </div>
        </a>

        <div class="news-card-body">
            <div class="news-card-meta">
                <span class="news-card-date">
                    <i class="fa-regular fa-calendar"></i>
                    <?= date('M j, Y', strtotime($post['created_at'])) ?>
                </span>
            </div>

            <h3 class="news-card-title">
                <a href="<?= $basePath ?>/blog/<?= $post['slug'] ?>">
                    <?= htmlspecialchars($post['title']) ?>
                </a>
            </h3>

            <p class="news-card-excerpt">
                <?= htmlspecialchars(substr($post['excerpt'] ?: strip_tags($post['content']), 0, 120)) ?>...
            </p>

            <div class="news-card-action">
                <a href="<?= $basePath ?>/blog/<?= $post['slug'] ?>" class="news-btn">
                    Read Article
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
                <span class="news-card-reading-time">
                    <i class="fa-regular fa-clock"></i>
                    <?= $readingTime ?> min read
                </span>
            </div>
        </div>
    </article>
<?php endforeach; ?>