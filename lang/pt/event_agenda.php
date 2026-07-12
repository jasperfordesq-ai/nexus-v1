<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

return [
    'capacity_label' => 'Capacidade da sessão',
    'capacity_hint' => 'Deixe em branco para não limitar lugares. Os lugares da sessão não alteram a inscrição no evento nem os bilhetes.',
    'capacity_unlimited' => ':registered inscritos · sem limite',
    'capacity_limited' => ':registered de :limit inscritos',
    'resources_title' => 'Recursos da sessão',
    'resources_hint' => 'Adicione ligações HTTPS pela ordem de apresentação. Transmissões e gravações devem ficar limitadas a pessoas inscritas ou à equipa.',
    'resource_number' => 'Recurso :number',
    'resource_type' => 'Tipo de recurso',
    'resource_visibility' => 'Quem pode aceder',
    'resource_title' => 'Título do recurso',
    'resource_url' => 'URL HTTPS seguro',
    'resource_url_hint' => 'Use um endereço completo iniciado por https://.',
    'resource_types' => ['link' => 'Ligação', 'document' => 'Documento', 'slides' => 'Diapositivos', 'download' => 'Transferência', 'stream' => 'Transmissão em direto', 'recording' => 'Gravação'],
    'opens_new_window' => 'Abre numa nova janela',
    'resource_unavailable' => 'Ligação indisponível',
    'registered_success' => 'A inscrição na sessão foi concluída.',
    'withdrawn_success' => 'A inscrição na sessão foi cancelada.',
    'register_action' => 'Inscrever na sessão',
    'withdraw_action' => 'Cancelar inscrição',
    'registered_state' => 'Inscrição confirmada nesta sessão',
    'ineligible_state' => 'A inscrição no evento já não permite o acesso a esta sessão.',
    'full_state' => 'Esta sessão está lotada.',
    'session_full_error' => 'Já não há lugares nesta sessão.',
    'eligibility_error' => 'Confirme a inscrição no evento antes de se inscrever numa sessão.',
];
