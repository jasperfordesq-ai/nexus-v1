<?php

/**
 * Component: Code Block
 *
 * Syntax-highlighted code display with copy button.
 * Used on: master dashboard, admin pages builder, native app, cron setup
 *
 * @param string $code Code content (required)
 * @param string $language Language for syntax highlighting (default: 'text')
 * @param string $id Element ID (default: auto-generated)
 * @param string $title Block title/filename
 * @param bool $showLineNumbers Show line numbers (default: true)
 * @param bool $showCopy Show copy button (default: true)
 * @param int $maxHeight Max height in px before scroll (default: 400) - kept as inline for truly dynamic value
 * @param bool $wrap Wrap long lines (default: false)
 * @param array $highlight Lines to highlight (array of line numbers)
 * @param string $class Additional CSS classes
 */

$code = $code ?? '';
$language = $language ?? 'text';
$id = $id ?? 'code-' . md5($code . microtime());
$title = $title ?? '';
$showLineNumbers = $showLineNumbers ?? true;
$showCopy = $showCopy ?? true;
$maxHeight = $maxHeight ?? 400;
$wrap = $wrap ?? false;
$highlight = $highlight ?? [];
$class = $class ?? '';

if (empty($code)) {
    return;
}

// Language display names
$languageNames = [
    'php' => 'PHP',
    'js' => 'JavaScript',
    'javascript' => 'JavaScript',
    'ts' => 'TypeScript',
    'typescript' => 'TypeScript',
    'html' => 'HTML',
    'css' => 'CSS',
    'scss' => 'SCSS',
    'json' => 'JSON',
    'sql' => 'SQL',
    'bash' => 'Bash',
    'shell' => 'Shell',
    'python' => 'Python',
    'ruby' => 'Ruby',
    'java' => 'Java',
    'csharp' => 'C#',
    'cpp' => 'C++',
    'go' => 'Go',
    'rust' => 'Rust',
    'yaml' => 'YAML',
    'xml' => 'XML',
    'markdown' => 'Markdown',
    'text' => 'Plain Text',
];
$languageDisplay = $languageNames[$language] ?? strtoupper($language);

// Split code into lines
$lines = explode("\n", rtrim($code));
$lineCount = count($lines);

$wrapperClass = trim('component-code-block ' . $class);
$wrapClass = $wrap ? ' component-code-block__pre--wrap' : '';

// Dynamic max-height as inline style (acceptable)
$maxHeightStyle = "max-height: {$maxHeight}px;";
?>

<div class="<?= htmlspecialchars($wrapperClass) ?>" id="<?= htmlspecialchars($id) ?>-wrapper">
    <!-- Header -->
    <div class="component-code-block__header">
        <div class="component-code-block__info">
            <?php if ($title): ?>
                <span class="component-code-block__title">
                    <?= htmlspecialchars($title) ?>
                </span>
            <?php endif; ?>
            <span class="component-code-block__lang">
                <?= htmlspecialchars($languageDisplay) ?>
            </span>
        </div>
        <?php if ($showCopy): ?>
            <button
                type="button"
                class="component-code-block__copy-btn"
                id="<?= htmlspecialchars($id) ?>-copy"
                onclick="copyCodeBlock('<?= htmlspecialchars($id) ?>')"
                title="Copy code"
            >
                <i class="fa-regular fa-copy"></i>
                <span>Copy</span>
            </button>
        <?php endif; ?>
    </div>

    <!-- Code Content -->
    <div class="component-code-block__content" style="<?= $maxHeightStyle ?>">
        <pre class="component-code-block__pre<?= $wrapClass ?>"><code id="<?= htmlspecialchars($id) ?>" class="language-<?= htmlspecialchars($language) ?>"><?php
// Output with line numbers
foreach ($lines as $i => $line):
    $lineNum = $i + 1;
    $isHighlighted = in_array($lineNum, $highlight);
    $lineClass = $isHighlighted ? ' component-code-block__line--highlighted' : '';
?>
<?php if ($showLineNumbers): ?><span class="component-code-block__line-number"><?= $lineNum ?></span><?php endif; ?><span class="component-code-block__line-content<?= $lineClass ?>"><?= htmlspecialchars($line) ?></span>
<?php endforeach; ?></code></pre>
    </div>

    <!-- Hidden textarea for copying -->
    <textarea id="<?= htmlspecialchars($id) ?>-raw" class="visually-hidden"><?= htmlspecialchars($code) ?></textarea>
</div>

<script>
function copyCodeBlock(id) {
    const raw = document.getElementById(id + '-raw');
    const btn = document.getElementById(id + '-copy');

    navigator.clipboard.writeText(raw.value).then(function() {
        btn.classList.add('component-code-block__copy-btn--copied');
        btn.innerHTML = '<i class="fa-solid fa-check"></i> <span>Copied!</span>';

        setTimeout(function() {
            btn.classList.remove('component-code-block__copy-btn--copied');
            btn.innerHTML = '<i class="fa-regular fa-copy"></i> <span>Copy</span>';
        }, 2000);
    }).catch(function() {
        // Fallback
        raw.classList.remove('visually-hidden');
        raw.select();
        try {
            document.execCommand('copy');
            btn.classList.add('component-code-block__copy-btn--copied');
            btn.innerHTML = '<i class="fa-solid fa-check"></i> <span>Copied!</span>';
            setTimeout(function() {
                btn.classList.remove('component-code-block__copy-btn--copied');
                btn.innerHTML = '<i class="fa-regular fa-copy"></i> <span>Copy</span>';
            }, 2000);
        } catch (e) {
            alert('Failed to copy code');
        }
        raw.classList.add('visually-hidden');
    });
}
</script>
