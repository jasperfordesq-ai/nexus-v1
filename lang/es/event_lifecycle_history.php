<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

return [
    'title' => 'Historial del ciclo de vida',
    'description' => 'Un registro inmutable de los cambios de publicación y operativos de este evento.',
    'link' => 'Historial del ciclo de vida',
    'back_to_event' => 'Volver al evento',
    'immutable_explanation' => 'Este historial de auditoría solo admite nuevas entradas. Las entradas existentes no se pueden modificar ni eliminar.',
    'empty_title' => 'Aún no hay cambios del ciclo de vida',
    'empty_description' => 'Los cambios aparecerán aquí cuando se actualice el ciclo de vida del evento.',
    'list_label' => 'Cambios del ciclo de vida del evento',
    'version' => 'Versión :version',
    'immutable' => 'Inmutable',
    'recorded_at' => 'Registrado el',
    'timestamp_unknown' => 'Hora no registrada',
    'publication_label' => 'Publicación',
    'operational_label' => 'Estado operativo',
    'transition' => 'De :from a :to',
    'actor_label' => 'Modificado por',
    'unknown_actor' => 'Miembro :id',
    'reason_label' => 'Motivo',
    'evidence_title' => 'Evidencia operativa',
    'notifications_suppressed' => 'Se suprimieron las notificaciones duplicadas para este cambio de serie.',
    'load_more' => 'Ver historial anterior',
    'pagination_label' => 'Páginas del historial del ciclo de vida',
    'states' => [
        'publication' => [
            'draft' => 'Borrador',
            'pending_review' => 'Pendiente de revisión',
            'published' => 'Publicado',
            'archived' => 'Archivado',
        ],
        'operational' => [
            'scheduled' => 'Programado',
            'postponed' => 'Aplazado',
            'cancelled' => 'Cancelado',
            'completed' => 'Completado',
        ],
    ],
    'cascade' => [
        'reminders_cancelled' => 'Programaciones de recordatorios canceladas: :count',
        'waitlist_cancelled' => 'Entradas de la lista de espera canceladas: :count',
        'registrations_cancelled' => 'Inscripciones canceladas: :count',
    ],
    'series' => [
        'template' => 'Plantilla recurrente :id',
        'occurrence' => 'Instancia de la plantilla recurrente :id',
    ],
];
