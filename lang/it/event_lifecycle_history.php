<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

return [
    'title' => 'Cronologia del ciclo di vita',
    'description' => 'Un registro immutabile delle modifiche di pubblicazione e operative per questo evento.',
    'link' => 'Cronologia del ciclo di vita',
    'back_to_event' => 'Torna all’evento',
    'immutable_explanation' => 'Questa cronologia di controllo è di sola aggiunta. Le voci esistenti non possono essere modificate o eliminate.',
    'empty_title' => 'Nessuna modifica del ciclo di vita',
    'empty_description' => 'Le modifiche compariranno qui dopo l’aggiornamento del ciclo di vita dell’evento.',
    'list_label' => 'Modifiche del ciclo di vita dell’evento',
    'version' => 'Versione :version',
    'immutable' => 'Immutabile',
    'recorded_at' => 'Registrato il',
    'timestamp_unknown' => 'Ora non registrata',
    'publication_label' => 'Pubblicazione',
    'operational_label' => 'Stato operativo',
    'transition' => 'Da :from a :to',
    'actor_label' => 'Modificato da',
    'unknown_actor' => 'Membro :id',
    'reason_label' => 'Motivo',
    'evidence_title' => 'Evidenza operativa',
    'notifications_suppressed' => 'Le notifiche duplicate sono state soppresse per questa modifica della serie.',
    'load_more' => 'Visualizza la cronologia precedente',
    'pagination_label' => 'Pagine della cronologia del ciclo di vita',
    'states' => [
        'publication' => [
            'draft' => 'Bozza',
            'pending_review' => 'In attesa di revisione',
            'published' => 'Pubblicato',
            'archived' => 'Archiviato',
        ],
        'operational' => [
            'scheduled' => 'Pianificato',
            'postponed' => 'Rinviato',
            'cancelled' => 'Annullato',
            'completed' => 'Completato',
        ],
    ],
    'cascade' => [
        'reminders_cancelled' => 'Pianificazioni dei promemoria annullate: :count',
        'waitlist_cancelled' => 'Voci della lista d’attesa annullate: :count',
        'registrations_cancelled' => 'Iscrizioni annullate: :count',
    ],
    'series' => [
        'template' => 'Modello ricorrente :id',
        'occurrence' => 'Occorrenza del modello ricorrente :id',
    ],
];
