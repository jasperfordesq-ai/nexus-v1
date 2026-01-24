<?php
/**
 * GOV.UK Task List Component
 * Reusable task list following GOV.UK Design System v5.14.0
 * Used to show users a summary of tasks they need to complete
 *
 * @param array $items - Array of tasks with 'title', 'href' (optional), 'status', 'hint' (optional)
 *                       Status can be: 'completed', 'in-progress', 'not-started', 'cannot-start'
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/task-list.php'; echo civicone_govuk_task_list([
 *     'items' => [
 *         ['title' => 'Company information', 'href' => '/company', 'status' => 'completed'],
 *         ['title' => 'Your contact details', 'href' => '/contact', 'status' => 'in-progress'],
 *         ['title' => 'List directors', 'href' => '/directors', 'status' => 'not-started'],
 *         ['title' => 'Submit application', 'status' => 'cannot-start', 'hint' => 'Complete all sections first'],
 *     ]
 * ]); ?>
 */

function civicone_govuk_task_list($args = []) {
    $defaults = [
        'items' => [],
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    if (empty($args['items'])) {
        return '';
    }

    $classes = ['govuk-task-list'];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }

    $statusConfig = [
        'completed' => ['tag' => 'Completed', 'class' => 'govuk-task-list__status--completed'],
        'in-progress' => ['tag' => 'In progress', 'class' => 'govuk-tag govuk-tag--light-blue'],
        'not-started' => ['tag' => 'Not yet started', 'class' => 'govuk-tag govuk-tag--blue'],
        'cannot-start' => ['tag' => 'Cannot start yet', 'class' => 'govuk-tag govuk-tag--grey'],
    ];

    $html = '<ul class="' . implode(' ', $classes) . '">';

    foreach ($args['items'] as $index => $item) {
        $taskId = 'task-' . ($index + 1);
        $hintId = $taskId . '-hint';
        $statusId = $taskId . '-status';
        $status = $item['status'] ?? 'not-started';
        $statusInfo = $statusConfig[$status] ?? $statusConfig['not-started'];
        $hasHref = !empty($item['href']) && $status !== 'cannot-start';

        $html .= '<li class="govuk-task-list__item';
        if ($hasHref) {
            $html .= ' govuk-task-list__item--with-link';
        }
        $html .= '">';

        // Task name
        $html .= '<div class="govuk-task-list__name-and-hint">';

        if ($hasHref) {
            $html .= '<a class="govuk-link govuk-task-list__link" href="' . htmlspecialchars($item['href']) . '" ';
            $html .= 'aria-describedby="' . htmlspecialchars($statusId);
            if (!empty($item['hint'])) {
                $html .= ' ' . htmlspecialchars($hintId);
            }
            $html .= '">';
            $html .= htmlspecialchars($item['title'] ?? '');
            $html .= '</a>';
        } else {
            $html .= '<div class="govuk-task-list__name">';
            $html .= htmlspecialchars($item['title'] ?? '');
            $html .= '</div>';
        }

        // Hint
        if (!empty($item['hint'])) {
            $html .= '<div id="' . htmlspecialchars($hintId) . '" class="govuk-task-list__hint">';
            $html .= htmlspecialchars($item['hint']);
            $html .= '</div>';
        }

        $html .= '</div>';

        // Status
        $html .= '<div class="govuk-task-list__status" id="' . htmlspecialchars($statusId) . '">';

        if ($status === 'completed') {
            $html .= '<span class="' . $statusInfo['class'] . '">' . $statusInfo['tag'] . '</span>';
        } else {
            $html .= '<strong class="' . $statusInfo['class'] . '">' . $statusInfo['tag'] . '</strong>';
        }

        $html .= '</div>';

        $html .= '</li>';
    }

    $html .= '</ul>';

    return $html;
}
