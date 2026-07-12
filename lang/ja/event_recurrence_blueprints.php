<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return array (
  'tab' => '今後の設定',
  'title' => '今後の開催回の設定',
  'description' => '新しい開催回の作成時に適用するイベント定義を選択します。',
  'definition_only_title' => '定義のみ',
  'definition_only_description' => '参加者、登録、出席、支払い、リマインダー、分析、配信履歴はコピーされず、既存の開催回も変更されません。',
  'effective_from_label' => '適用開始の繰り返し ID',
  'effective_from_help' => 'この安定した ID は選択した開催回のものです。変更後の開始時刻から再計算されません。',
  'sections_title' => '引き継ぐ定義',
  'sections_description' => '各セクションは明示的に選択します。スタッフ割り当てが自動選択されることはありません。',
  'sections' => 
  array (
    'agenda' => 
    array (
      'label' => 'プログラム',
      'description' => '予定済みセッション、登壇者、保護されたリソースの定義。',
    ),
    'ticket_types' => 
    array (
      'label' => 'チケット種別',
      'description' => '無料または下書きのチケット定義と販売期間。',
    ),
    'registration' => 
    array (
      'label' => '登録',
      'description' => '登録設定と現在公開中のフォーム。',
    ),
    'safety' => 
    array (
      'label' => '安全要件',
      'description' => '現在公開中の安全および参加資格要件。',
    ),
    'staff' => 
    array (
      'label' => 'スタッフ割り当て',
      'description' => '高リスクの明示選択：有効な役割を今後の新しい開催回に引き継ぎます。',
    ),
  ),
  'section_not_permitted' => 'あなたのイベント役割では、このセクションを引き継げません。',
  'no_sections_title' => '1 つ以上のセクションを選択してください',
  'no_sections_description' => '今後の設定を保存する前にプレビューが必要です。',
  'preview_button' => '今後の設定をプレビュー',
  'previewing' => 'プレビューを準備中',
  'preview_title' => '定義のプレビュー',
  'preview_description' => '確認に進む前に、上限付きの件数と競合を確認してください。',
  'preview_expires' => 'プレビューの有効期限：:date',
  'review_button' => '確認して確定',
  'refresh_preview' => 'プレビューを更新',
  'conflicts_title' => '先にこれらの競合を解決してください',
  'conflicts' => 
  array (
    'definition_limit_exceeded' => ':section が安全な定義上限を超えています（:count 件）。',
    'speaker_limit_exceeded' => 'プログラムが安全な登壇者上限を超えています（:count 件）。',
    'invalid_speaker_reference' => 'プログラムに無効な登壇者参照が :count 件あります。',
    'resource_limit_exceeded' => 'プログラムが安全なリソース上限を超えています（:count 件）。',
    'unsupported_active_time_credit_ticket' => '有効な時間クレジットのチケット種別 :count 件は引き継げません。',
    'published_form_missing' => '公開済み登録フォームを確認できませんでした。',
    'question_limit_exceeded' => '公開済みフォームが安全な質問上限を超えています（:count 件）。',
    'published_requirement_version_missing' => '公開済み安全要件の版を確認できませんでした。',
    'invalid_staff_reference' => 'スタッフ割り当て :count 件が利用できないメンバーを参照しています。',
    'nonportable_staff_expiry' => 'スタッフ割り当て :count 件は今後の開催回より前に期限切れとなるため引き継げません。',
  ),
  'counts' => 
  array (
    'none' => '選択したセクションに定義はありません。',
    'sessions' => 'セッション',
    'speakers' => '登壇者',
    'resources' => 'リソース',
    'ticket_types' => 'チケット種別',
    'registration_settings' => '登録設定',
    'published_forms' => '公開フォーム',
    'form_questions' => 'フォームの質問',
    'safety_requirements' => '安全要件',
    'staff_assignments' => 'スタッフ割り当て',
  ),
  'errors' => 
  array (
    'preview_error' => 
    array (
      'title' => 'プレビューを準備できませんでした',
      'description' => '選択した定義を確認して、もう一度お試しください。',
    ),
    'preview_expired' => 
    array (
      'title' => 'プレビューの有効期限が切れました',
      'description' => '確定前にプレビューを更新してください。保存はされていません。',
    ),
    'preview_stale' => 
    array (
      'title' => 'プレビュー後に定義が変更されました',
      'description' => '最新の定義で新しいプレビューを作成してください。',
    ),
    'commit_conflict' => 
    array (
      'title' => '今後の設定は保存されませんでした',
      'description' => '別の版または競合するリクエストが先に保存されました。更新して再確認してください。',
    ),
    'commit_error' => 
    array (
      'title' => '今後の設定を保存できませんでした',
      'description' => '安定した再試行キーは保持されています。もう一度確定するか、期限切れならプレビューを更新してください。',
    ),
  ),
  'success_created_title' => '今後の設定を保存しました',
  'success_created_description' => '変更不能な定義版 :version は、今後新しく作成される開催回にのみ適用されます。',
  'success_replay_title' => '今後の設定は保存済みです',
  'success_replay_description' => '版 :version はこの再試行と一致します。重複は作成されませんでした。',
  'history_title' => '変更不能な版の履歴',
  'history_description' => '保存した各定義版は、上限付き件数と適用開始の繰り返し ID とともに保持されます。',
  'history_loading' => '履歴を読み込み中',
  'history_error_title' => '履歴を読み込めませんでした',
  'history_error_description' => '変更不能な版を取得するには、もう一度お試しください。',
  'history_empty_title' => '今後の設定版はまだありません',
  'history_empty_description' => 'プレビューを確定すると、ここに版が表示されます。',
  'history_list_label' => '今後の開催回設定の版',
  'history_version' => '版 :version',
  'history_sections' => '含まれる定義',
  'immutable' => '変更不能',
  'history_load_more' => 'さらに版を読み込む',
  'history_loading_more' => 'さらに版を読み込み中',
  'load_more_error_title' => '追加の版を読み込めませんでした',
  'load_more_error_description' => '次のページをもう一度読み込んでください。',
  'retry' => '再試行',
  'time_unknown' => '時刻の記録なし',
  'confirm_title' => '今後の開催回設定を確定',
  'confirm_scope_title' => '新しい開催回のみ',
  'confirm_scope_description' => 'この版は表示された ID から有効です。既存の開催回と参加者データは変更されません。',
  'staff_risk_title' => 'スタッフの引き継ぎが選択されています',
  'staff_risk_description' => '有効な役割により、新しい開催回ごとに運用アクセスが付与される場合があります。この選択を慎重に確認してください。',
  'confirm_ack' => '今後のみに適用するこの定義版を確定します',
  'confirm_ack_description' => '選択セクション、件数、競合、適用開始の繰り返し ID を確認しました。',
  'cancel' => 'キャンセル',
  'commit_button' => '変更不能な版を保存',
  'committing' => '版を保存中',
);
