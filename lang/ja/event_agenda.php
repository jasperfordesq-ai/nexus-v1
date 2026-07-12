<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

return [
    'withdraw_confirmation' => ':title の席が解放され、別の参加者が利用できるようになることを理解しています。',
    'capacity_label' => 'セッション定員',
    'capacity_hint' => '無制限の場合は空欄にします。セッション枠はイベント登録やチケットを変更しません。',
    'capacity_unlimited' => '登録 :registered 人・定員なし',
    'capacity_limited' => ':limit 人中 :registered 人登録',
    'resources_title' => 'セッション資料',
    'resources_hint' => '表示順に HTTPS リンクを追加します。配信と録画は登録者またはスタッフのみに制限してください。',
    'resource_number' => '資料 :number',
    'resource_type' => '資料の種類',
    'resource_visibility' => 'アクセスできる人',
    'resource_title' => '資料名',
    'resource_url' => '安全な HTTPS URL',
    'resource_url_hint' => 'https:// で始まる完全なアドレスを入力してください。',
    'resource_types' => ['link' => 'リンク', 'document' => '文書', 'slides' => 'スライド', 'download' => 'ダウンロード', 'stream' => 'ライブ配信', 'recording' => '録画'],
    'opens_new_window' => '新しいウィンドウで開きます',
    'resource_unavailable' => 'リンクを利用できません',
    'registered_success' => 'セッションに登録しました。',
    'withdrawn_success' => 'セッション登録を取り消しました。',
    'register_action' => 'セッションに登録',
    'withdraw_action' => 'セッション登録を取り消す',
    'registered_state' => 'このセッションに登録済み',
    'ineligible_state' => 'イベント登録ではこのセッションを利用できなくなりました。',
    'full_state' => 'このセッションは満席です。',
    'session_full_error' => 'このセッションに空きはありません。',
    'eligibility_error' => 'セッションに登録する前にイベント登録を確定してください。',
];
