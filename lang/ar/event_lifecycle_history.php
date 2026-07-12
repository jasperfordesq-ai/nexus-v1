<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

return [
    'title' => 'سجل دورة الحياة',
    'description' => 'سجل غير قابل للتغيير لتغييرات النشر والتشغيل الخاصة بهذه الفعالية.',
    'link' => 'سجل دورة الحياة',
    'back_to_event' => 'العودة إلى الفعالية',
    'immutable_explanation' => 'سجل التدقيق هذا مخصص للإضافة فقط. لا يمكن تغيير الإدخالات الحالية أو حذفها.',
    'empty_title' => 'لا توجد تغييرات في دورة الحياة بعد',
    'empty_description' => 'ستظهر التغييرات هنا بعد تحديث دورة حياة الفعالية.',
    'list_label' => 'تغييرات دورة حياة الفعالية',
    'version' => 'الإصدار :version',
    'immutable' => 'غير قابل للتغيير',
    'recorded_at' => 'وقت التسجيل',
    'timestamp_unknown' => 'لم يتم تسجيل الوقت',
    'publication_label' => 'النشر',
    'operational_label' => 'الحالة التشغيلية',
    'transition' => 'من :from إلى :to',
    'actor_label' => 'تم التغيير بواسطة',
    'unknown_actor' => 'العضو :id',
    'reason_label' => 'السبب',
    'evidence_title' => 'الدليل التشغيلي',
    'notifications_suppressed' => 'تم منع الإشعارات المكررة لهذا التغيير في السلسلة.',
    'load_more' => 'عرض السجل الأقدم',
    'pagination_label' => 'صفحات سجل دورة الحياة',
    'states' => [
        'publication' => [
            'draft' => 'مسودة',
            'pending_review' => 'بانتظار المراجعة',
            'published' => 'منشور',
            'archived' => 'مؤرشف',
        ],
        'operational' => [
            'scheduled' => 'مجدول',
            'postponed' => 'مؤجل',
            'cancelled' => 'ملغى',
            'completed' => 'مكتمل',
        ],
    ],
    'cascade' => [
        'reminders_cancelled' => 'جداول التذكير الملغاة: :count',
        'waitlist_cancelled' => 'إدخالات قائمة الانتظار الملغاة: :count',
        'registrations_cancelled' => 'التسجيلات الملغاة: :count',
    ],
    'series' => [
        'template' => 'قالب متكرر :id',
        'occurrence' => 'موعد من القالب المتكرر :id',
    ],
];
