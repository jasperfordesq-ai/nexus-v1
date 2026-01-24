<?php

/**
 * Component: Table
 *
 * Data table with optional sorting and styling.
 *
 * @param array $headers Array of header configs: ['key' => '', 'label' => '', 'sortable' => false, 'align' => 'left', 'width' => '']
 * @param array $rows Array of row data (associative arrays matching header keys)
 * @param string $class Additional CSS classes
 * @param string $variant Style variant: 'default', 'striped', 'bordered' (default: 'default')
 * @param bool $hoverable Add hover effect to rows (default: true)
 * @param bool $compact Compact spacing (default: false)
 * @param string $emptyMessage Message when no data (default: 'No data available')
 */

$headers = $headers ?? [];
$rows = $rows ?? [];
$class = $class ?? '';
$variant = $variant ?? 'default';
$hoverable = $hoverable ?? true;
$compact = $compact ?? false;
$emptyMessage = $emptyMessage ?? 'No data available';

$variantClasses = [
    'default' => 'htb-table component-table',
    'striped' => 'htb-table component-table component-table--striped',
    'bordered' => 'htb-table component-table component-table--bordered',
];
$baseClass = $variantClasses[$variant] ?? $variantClasses['default'];

$cssClass = trim(implode(' ', array_filter([
    $baseClass,
    $hoverable ? 'component-table--hoverable' : '',
    $compact ? 'component-table--compact' : '',
    $class
])));
?>

<div class="table-responsive">
    <table class="<?= e($cssClass) ?>">
        <thead>
            <tr>
                <?php foreach ($headers as $header): ?>
                    <?php
                    $key = $header['key'] ?? '';
                    $label = $header['label'] ?? $key;
                    $sortable = $header['sortable'] ?? false;
                    $align = $header['align'] ?? 'left';
                    $width = $header['width'] ?? '';

                    // Build align class
                    $alignClass = 'component-table__cell--' . $align;
                    $widthClass = '';
                    // Width is truly dynamic, keep as inline style
                    $widthStyle = $width ? "width: {$width};" : '';
                    ?>
                    <th class="<?= e($alignClass) ?>" <?php if ($widthStyle): ?>style="<?= e($widthStyle) ?>"<?php endif; ?> <?php if ($sortable): ?>data-sortable="<?= e($key) ?>"<?php endif; ?>>
                        <?= e($label) ?>
                        <?php if ($sortable): ?>
                            <span class="sort-icon"><i class="fa-solid fa-sort"></i></span>
                        <?php endif; ?>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="<?= count($headers) ?>" class="component-table__empty">
                        <?= e($emptyMessage) ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($headers as $header): ?>
                            <?php
                            $key = $header['key'] ?? '';
                            $align = $header['align'] ?? 'left';
                            $value = $row[$key] ?? '';
                            $alignClass = 'component-table__cell--' . $align;

                            // Support for render callback
                            if (isset($header['render']) && is_callable($header['render'])) {
                                $cellContent = $header['render']($value, $row);
                            } else {
                                $cellContent = e($value);
                            }
                            ?>
                            <td class="<?= e($alignClass) ?>"><?= $cellContent ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
