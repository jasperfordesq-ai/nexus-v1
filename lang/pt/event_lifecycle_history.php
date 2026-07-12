<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

return [
    'title' => 'Histórico do ciclo de vida',
    'description' => 'Um registo imutável das alterações de publicação e operacionais deste evento.',
    'link' => 'Histórico do ciclo de vida',
    'back_to_event' => 'Voltar ao evento',
    'immutable_explanation' => 'Este histórico de auditoria apenas permite novas entradas. As entradas existentes não podem ser alteradas nem eliminadas.',
    'empty_title' => 'Ainda não existem alterações do ciclo de vida',
    'empty_description' => 'As alterações aparecerão aqui após a atualização do ciclo de vida do evento.',
    'list_label' => 'Alterações do ciclo de vida do evento',
    'version' => 'Versão :version',
    'immutable' => 'Imutável',
    'recorded_at' => 'Registado em',
    'timestamp_unknown' => 'Hora não registada',
    'publication_label' => 'Publicação',
    'operational_label' => 'Estado operacional',
    'transition' => 'De :from para :to',
    'actor_label' => 'Alterado por',
    'unknown_actor' => 'Membro :id',
    'reason_label' => 'Motivo',
    'evidence_title' => 'Evidência operacional',
    'notifications_suppressed' => 'As notificações duplicadas foram suprimidas para esta alteração da série.',
    'load_more' => 'Ver histórico mais antigo',
    'pagination_label' => 'Páginas do histórico do ciclo de vida',
    'states' => [
        'publication' => [
            'draft' => 'Rascunho',
            'pending_review' => 'A aguardar revisão',
            'published' => 'Publicado',
            'archived' => 'Arquivado',
        ],
        'operational' => [
            'scheduled' => 'Agendado',
            'postponed' => 'Adiado',
            'cancelled' => 'Cancelado',
            'completed' => 'Concluído',
        ],
    ],
    'cascade' => [
        'reminders_cancelled' => 'Agendamentos de lembretes cancelados: :count',
        'waitlist_cancelled' => 'Entradas da lista de espera canceladas: :count',
        'registrations_cancelled' => 'Inscrições canceladas: :count',
    ],
    'series' => [
        'template' => 'Modelo recorrente :id',
        'occurrence' => 'Ocorrência do modelo recorrente :id',
    ],
];
