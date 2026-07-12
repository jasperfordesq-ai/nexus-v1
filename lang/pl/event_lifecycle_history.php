<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

return [
    'title' => 'Historia cyklu życia',
    'description' => 'Niezmienny rejestr zmian publikacji i zmian operacyjnych tego wydarzenia.',
    'link' => 'Historia cyklu życia',
    'back_to_event' => 'Powrót do wydarzenia',
    'immutable_explanation' => 'Ta historia audytu może być tylko uzupełniana. Istniejących wpisów nie można zmieniać ani usuwać.',
    'empty_title' => 'Brak zmian cyklu życia',
    'empty_description' => 'Zmiany pojawią się tutaj po aktualizacji cyklu życia wydarzenia.',
    'list_label' => 'Zmiany cyklu życia wydarzenia',
    'version' => 'Wersja :version',
    'immutable' => 'Niezmienne',
    'recorded_at' => 'Zarejestrowano',
    'timestamp_unknown' => 'Nie zarejestrowano czasu',
    'publication_label' => 'Publikacja',
    'operational_label' => 'Stan operacyjny',
    'transition' => 'Z :from na :to',
    'actor_label' => 'Zmienione przez',
    'unknown_actor' => 'Członek :id',
    'reason_label' => 'Powód',
    'evidence_title' => 'Dowód operacyjny',
    'notifications_suppressed' => 'Duplikaty powiadomień zostały pominięte dla tej zmiany serii.',
    'load_more' => 'Zobacz starszą historię',
    'pagination_label' => 'Strony historii cyklu życia',
    'states' => [
        'publication' => [
            'draft' => 'Wersja robocza',
            'pending_review' => 'Oczekuje na weryfikację',
            'published' => 'Opublikowane',
            'archived' => 'Zarchiwizowane',
        ],
        'operational' => [
            'scheduled' => 'Zaplanowane',
            'postponed' => 'Przełożone',
            'cancelled' => 'Anulowane',
            'completed' => 'Zakończone',
        ],
    ],
    'cascade' => [
        'reminders_cancelled' => 'Anulowane harmonogramy przypomnień: :count',
        'waitlist_cancelled' => 'Anulowane wpisy na liście oczekujących: :count',
        'registrations_cancelled' => 'Anulowane rejestracje: :count',
    ],
    'series' => [
        'template' => 'Szablon cykliczny :id',
        'occurrence' => 'Wystąpienie szablonu cyklicznego :id',
    ],
];
