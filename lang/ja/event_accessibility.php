<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [
    'form' => [
        'title' => '会場のアクセシビリティ',
        'hint' => '会場で利用できる設備を記録してください。主催者が確認していない場合は「不明」を選んでください。「なし」とは異なります。',
        'parking_details' => '駐車場と到着経路',
        'parking_details_hint' => '優先駐車区画、乗降場所、入口までの経路を説明してください。',
        'transit_details' => '公共交通機関',
        'transit_details_hint' => '最寄りの停留所や駅、経路上の障害を説明してください。',
        'assistance_contact' => 'アクセシビリティ支援の連絡先',
        'assistance_contact_hint' => 'アクセスに関する質問用の公開連絡先を記載してください。参加者の個人情報は入力しないでください。',
        'notes' => 'その他のアクセス情報',
        'notes_hint' => '入口、エレベーター、路面、照明、感覚環境などの役立つ情報を記載してください。',
        'privacy_note' => 'この情報はイベントのメンバーに表示されます。個別の配慮依頼はここではなく登録フォームに記入してください。',
    ],
    'features' => [
        'step_free_access' => '段差のないアクセス',
        'accessible_toilet' => 'バリアフリートイレ',
        'hearing_loop' => 'ヒアリングループ',
        'quiet_space' => '静かなスペース',
        'seating_available' => '座席あり',
        'accessible_parking' => '優先駐車場',
    ],
    'status' => ['yes' => 'あり', 'no' => 'なし', 'unknown' => '不明'],
    'filters' => [
        'step_free_label' => '会場の段差のないアクセス',
        'step_free_hint' => '主催者が確認した会場のアクセス情報で絞り込みます。',
        'step_free_options' => ['any' => 'すべての会場', 'yes' => '段差のないアクセスを確認済み', 'no' => '段差のないアクセスなし', 'unknown' => '段差のないアクセスは不明'],
        'step_free_active' => '段差のないアクセス：:value',
    ],
    'detail' => [
        'title' => '会場のアクセシビリティ',
        'intro' => 'イベント主催者が提供したアクセス情報です。対応を確認する必要がある場合は主催者に連絡してください。',
        'features_label' => '会場のアクセシビリティ設備',
        'parking_details' => '駐車場と到着経路',
        'transit_details' => '公共交通機関',
        'assistance_contact' => 'アクセシビリティ支援',
        'notes' => 'その他のアクセス情報',
    ],
];
