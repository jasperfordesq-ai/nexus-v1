<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

return [
    'title' => 'Lebenszyklusverlauf',
    'description' => 'Ein unveränderliches Protokoll der Veröffentlichungs- und Betriebsänderungen dieser Veranstaltung.',
    'link' => 'Lebenszyklusverlauf',
    'back_to_event' => 'Zurück zur Veranstaltung',
    'immutable_explanation' => 'Dieser Prüfverlauf kann nur ergänzt werden. Vorhandene Einträge können weder geändert noch gelöscht werden.',
    'empty_title' => 'Noch keine Lebenszyklusänderungen',
    'empty_description' => 'Änderungen erscheinen hier, sobald der Veranstaltungslebenszyklus aktualisiert wird.',
    'list_label' => 'Lebenszyklusänderungen der Veranstaltung',
    'version' => 'Version :version',
    'immutable' => 'Unveränderlich',
    'recorded_at' => 'Erfasst am',
    'timestamp_unknown' => 'Zeitpunkt nicht erfasst',
    'publication_label' => 'Veröffentlichung',
    'operational_label' => 'Betriebsstatus',
    'transition' => ':from zu :to',
    'actor_label' => 'Geändert von',
    'unknown_actor' => 'Mitglied :id',
    'reason_label' => 'Grund',
    'evidence_title' => 'Betrieblicher Nachweis',
    'notifications_suppressed' => 'Doppelte Benachrichtigungen wurden für diese Serienänderung unterdrückt.',
    'load_more' => 'Älteren Verlauf anzeigen',
    'pagination_label' => 'Seiten des Lebenszyklusverlaufs',
    'states' => [
        'publication' => [
            'draft' => 'Entwurf',
            'pending_review' => 'Prüfung ausstehend',
            'published' => 'Veröffentlicht',
            'archived' => 'Archiviert',
        ],
        'operational' => [
            'scheduled' => 'Geplant',
            'postponed' => 'Verschoben',
            'cancelled' => 'Abgesagt',
            'completed' => 'Abgeschlossen',
        ],
    ],
    'cascade' => [
        'reminders_cancelled' => 'Stornierte Erinnerungspläne: :count',
        'waitlist_cancelled' => 'Stornierte Wartelisteneinträge: :count',
        'registrations_cancelled' => 'Stornierte Anmeldungen: :count',
    ],
    'series' => [
        'template' => 'Wiederkehrende Vorlage :id',
        'occurrence' => 'Termin der wiederkehrenden Vorlage :id',
    ],
];
