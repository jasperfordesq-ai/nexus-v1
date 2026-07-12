<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

return [
    'title' => 'Stair na saolré',
    'description' => 'Taifead do-athraithe ar athruithe foilsithe agus oibríochtúla don imeacht seo.',
    'link' => 'Stair na saolré',
    'back_to_event' => 'Ar ais chuig an imeacht',
    'immutable_explanation' => 'Is stair iniúchta breisithe amháin í seo. Ní féidir iontrálacha atá ann a athrú ná a scriosadh.',
    'empty_title' => 'Níl aon athrú saolré ann fós',
    'empty_description' => 'Beidh athruithe le feiceáil anseo nuair a nuashonrófar saolré an imeachta.',
    'list_label' => 'Athruithe ar shaolré an imeachta',
    'version' => 'Leagan :version',
    'immutable' => 'Do-athraithe',
    'recorded_at' => 'Taifeadta ag',
    'timestamp_unknown' => 'Níor taifeadadh an t-am',
    'publication_label' => 'Foilsiú',
    'operational_label' => 'Stádas oibríochtúil',
    'transition' => ':from go :to',
    'actor_label' => 'Athraithe ag',
    'unknown_actor' => 'Ball :id',
    'reason_label' => 'Cúis',
    'evidence_title' => 'Fianaise oibríochtúil',
    'notifications_suppressed' => 'Cuireadh fógraí dúblacha faoi chois don athrú sraithe seo.',
    'load_more' => 'Féach ar stair níos sine',
    'pagination_label' => 'Leathanaigh stair na saolré',
    'states' => [
        'publication' => [
            'draft' => 'Dréacht',
            'pending_review' => 'Ag feitheamh ar athbhreithniú',
            'published' => 'Foilsithe',
            'archived' => 'Cartlannaithe',
        ],
        'operational' => [
            'scheduled' => 'Sceidealaithe',
            'postponed' => 'Curtha ar athló',
            'cancelled' => 'Cealaithe',
            'completed' => 'Críochnaithe',
        ],
    ],
    'cascade' => [
        'reminders_cancelled' => 'Sceidil mheabhrúcháin cealaithe: :count',
        'waitlist_cancelled' => 'Iontrálacha liosta feithimh cealaithe: :count',
        'registrations_cancelled' => 'Clárúcháin cealaithe: :count',
    ],
    'series' => [
        'template' => 'Teimpléad athfhillteach :id',
        'occurrence' => 'Tarlú de theimpléad athfhillteach :id',
    ],
];
