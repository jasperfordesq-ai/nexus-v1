<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

return [
    'title' => 'Levenscyclusgeschiedenis',
    'description' => 'Een onveranderlijk overzicht van publicatie- en operationele wijzigingen voor dit evenement.',
    'link' => 'Levenscyclusgeschiedenis',
    'back_to_event' => 'Terug naar evenement',
    'immutable_explanation' => 'Deze controlegeschiedenis kan alleen worden aangevuld. Bestaande vermeldingen kunnen niet worden gewijzigd of verwijderd.',
    'empty_title' => 'Nog geen levenscycluswijzigingen',
    'empty_description' => 'Wijzigingen verschijnen hier nadat de levenscyclus van het evenement is bijgewerkt.',
    'list_label' => 'Levenscycluswijzigingen van het evenement',
    'version' => 'Versie :version',
    'immutable' => 'Onveranderlijk',
    'recorded_at' => 'Vastgelegd op',
    'timestamp_unknown' => 'Tijd niet vastgelegd',
    'publication_label' => 'Publicatie',
    'operational_label' => 'Operationele status',
    'transition' => 'Van :from naar :to',
    'actor_label' => 'Gewijzigd door',
    'unknown_actor' => 'Lid :id',
    'reason_label' => 'Reden',
    'evidence_title' => 'Operationeel bewijs',
    'notifications_suppressed' => 'Dubbele meldingen zijn onderdrukt voor deze seriewijziging.',
    'load_more' => 'Oudere geschiedenis bekijken',
    'pagination_label' => 'Pagina’s van de levenscyclusgeschiedenis',
    'states' => [
        'publication' => [
            'draft' => 'Concept',
            'pending_review' => 'Wacht op beoordeling',
            'published' => 'Gepubliceerd',
            'archived' => 'Gearchiveerd',
        ],
        'operational' => [
            'scheduled' => 'Gepland',
            'postponed' => 'Uitgesteld',
            'cancelled' => 'Geannuleerd',
            'completed' => 'Voltooid',
        ],
    ],
    'cascade' => [
        'reminders_cancelled' => 'Geannuleerde herinneringsschema’s: :count',
        'waitlist_cancelled' => 'Geannuleerde wachtlijstvermeldingen: :count',
        'registrations_cancelled' => 'Geannuleerde inschrijvingen: :count',
    ],
    'series' => [
        'template' => 'Terugkerend sjabloon :id',
        'occurrence' => 'Moment van terugkerend sjabloon :id',
    ],
];
