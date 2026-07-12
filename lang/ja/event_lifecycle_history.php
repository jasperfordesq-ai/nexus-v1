<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

return [
    'title' => 'ライフサイクル履歴',
    'description' => 'このイベントの公開状態と運用状態の変更を記録した変更不可の履歴です。',
    'link' => 'ライフサイクル履歴',
    'back_to_event' => 'イベントに戻る',
    'immutable_explanation' => 'この監査履歴は追記専用です。既存の記録を変更または削除することはできません。',
    'empty_title' => 'ライフサイクルの変更はまだありません',
    'empty_description' => 'イベントのライフサイクルが更新されると、ここに変更が表示されます。',
    'list_label' => 'イベントのライフサイクル変更',
    'version' => 'バージョン :version',
    'immutable' => '変更不可',
    'recorded_at' => '記録日時',
    'timestamp_unknown' => '時刻は記録されていません',
    'publication_label' => '公開状態',
    'operational_label' => '運用状態',
    'transition' => ':from から :to',
    'actor_label' => '変更者',
    'unknown_actor' => 'メンバー :id',
    'reason_label' => '理由',
    'evidence_title' => '運用上の証跡',
    'notifications_suppressed' => 'このシリーズ変更では重複通知が抑制されました。',
    'load_more' => '以前の履歴を表示',
    'pagination_label' => 'ライフサイクル履歴のページ',
    'states' => [
        'publication' => [
            'draft' => '下書き',
            'pending_review' => '審査待ち',
            'published' => '公開済み',
            'archived' => 'アーカイブ済み',
        ],
        'operational' => [
            'scheduled' => '予定済み',
            'postponed' => '延期',
            'cancelled' => '中止',
            'completed' => '完了',
        ],
    ],
    'cascade' => [
        'reminders_cancelled' => '中止されたリマインダー予定: :count',
        'waitlist_cancelled' => '取り消された待機リスト登録: :count',
        'registrations_cancelled' => '取り消された参加登録: :count',
    ],
    'series' => [
        'template' => '定期開催テンプレート :id',
        'occurrence' => '定期開催テンプレート :id の開催回',
    ],
];
