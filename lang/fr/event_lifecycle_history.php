<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

return [
    'title' => 'Historique du cycle de vie',
    'description' => 'Un registre immuable des changements de publication et d’exploitation de cet événement.',
    'link' => 'Historique du cycle de vie',
    'back_to_event' => 'Retour à l’événement',
    'immutable_explanation' => 'Cet historique d’audit est uniquement cumulatif. Les entrées existantes ne peuvent être ni modifiées ni supprimées.',
    'empty_title' => 'Aucun changement de cycle de vie pour le moment',
    'empty_description' => 'Les changements apparaîtront ici après la mise à jour du cycle de vie de l’événement.',
    'list_label' => 'Changements du cycle de vie de l’événement',
    'version' => 'Version :version',
    'immutable' => 'Immuable',
    'recorded_at' => 'Enregistré le',
    'timestamp_unknown' => 'Heure non enregistrée',
    'publication_label' => 'Publication',
    'operational_label' => 'État opérationnel',
    'transition' => 'De :from à :to',
    'actor_label' => 'Modifié par',
    'unknown_actor' => 'Membre :id',
    'reason_label' => 'Motif',
    'evidence_title' => 'Preuve opérationnelle',
    'notifications_suppressed' => 'Les notifications en double ont été supprimées pour ce changement de série.',
    'load_more' => 'Voir l’historique antérieur',
    'pagination_label' => 'Pages de l’historique du cycle de vie',
    'states' => [
        'publication' => [
            'draft' => 'Brouillon',
            'pending_review' => 'En attente de validation',
            'published' => 'Publié',
            'archived' => 'Archivé',
        ],
        'operational' => [
            'scheduled' => 'Planifié',
            'postponed' => 'Reporté',
            'cancelled' => 'Annulé',
            'completed' => 'Terminé',
        ],
    ],
    'cascade' => [
        'reminders_cancelled' => 'Planifications de rappel annulées : :count',
        'waitlist_cancelled' => 'Entrées de liste d’attente annulées : :count',
        'registrations_cancelled' => 'Inscriptions annulées : :count',
    ],
    'series' => [
        'template' => 'Modèle récurrent :id',
        'occurrence' => 'Occurrence du modèle récurrent :id',
    ],
];
