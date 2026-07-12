<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [
    'privacy' => [
        'title' => 'Prywatna i niezawodna rejestracja obecności',
        'body' => 'Podpisane kody uczestników nie zawierają imienia i nazwiska, adresu e-mail ani numeru telefonu. Kod można wpisać poniżej bez używania aparatu.',
        'no_wallet' => 'Działania dotyczące obecności nigdy nie zmieniają sald ani nie przyznają kredytów czasowych.',
    ],
    'code' => [
        'title' => 'Wpisz podpisany kod uczestnika',
        'intro' => 'Użyj tej opcji online, gdy aparat lub urządzenie personelu do pracy offline jest niedostępne. Ręczne wyszukiwanie według nazwiska pozostaje dostępne niżej.',
        'label' => 'Kod uczestnika',
        'hint' => 'Wklej pełny kod zaczynający się od nqx2_. Kod nie jest zapisywany w dzienniku audytu.',
        'action' => 'Działanie dotyczące obecności',
        'reason' => 'Powód korekty',
        'reason_hint' => 'Przy cofnięciu działania wymagany jest powód. Nie podawaj informacji poufnych.',
        'confirm' => 'Uczestnik został sprawdzony, a zamierzone działanie wybrane.',
        'submit' => 'Zastosuj działanie z podpisanego kodu',
    ],
    'actions' => [
        'check_in' => 'Zarejestruj wejście',
        'check_out' => 'Zarejestruj wyjście',
        'no_show' => 'Oznacz nieobecność',
        'undo' => 'Cofnij ostatnie działanie',
    ],
    'attendee' => [
        'manage_link' => 'Zarządzaj moim kodem odprawy',
        'title' => 'Twój kod odprawy na wydarzenie',
        'intro' => 'Utwórz podpisany kod, który można pokazać obsłudze wydarzenia na ekranie lub na wydruku.',
        'privacy' => 'Kod identyfikuje tylko tę rejestrację na wydarzenie. Nie zawiera imienia i nazwiska, adresu e-mail ani numeru telefonu.',
        'notice_issued' => 'Twój nowy kod odprawy jest widoczny poniżej.',
        'notice_replaced' => 'Poprzedni kod już nie działa. Kod zastępczy jest widoczny poniżej.',
        'notice_revoked' => 'Twój kod odprawy został unieważniony.',
        'notice_already_active' => 'Aktywny kod już istnieje. Zastąp go, jeśli nie masz dostępu do swojej kopii.',
        'notice_invalid' => 'Potwierdź żądaną czynność i spróbuj ponownie.',
        'notice_failed' => 'Nie udało się zmienić kodu odprawy. Odśwież stronę i spróbuj ponownie.',
        'status_heading' => 'Status kodu',
        'status_active' => 'Aktywny',
        'status_rotated' => 'Zastąpiony',
        'status_revoked' => 'Unieważniony',
        'status_expired' => 'Wygasły',
        'expires' => 'Wygasa :date',
        'one_shot_heading' => 'Zapisz ten kod teraz',
        'one_shot' => 'Ze względów bezpieczeństwa pełny kod jest wyświetlany tylko podczas tworzenia lub zastępowania.',
        'code_label' => 'Podpisany kod odprawy',
        'code_hint' => 'Zaznacz i skopiuj cały kod zaczynający się od nqx2_.',
        'print_hint' => 'Możesz wydrukować tę stronę lub zapisać dostępną kopię. Zachowaj kod w tajemnicy do czasu odprawy.',
        'print' => 'Wydrukuj ten kod',
        'issue_confirm' => 'Rozumiem, że pełny kod zostanie wyświetlony tylko raz.',
        'issue' => 'Utwórz kod odprawy',
        'replace' => 'Zastąp skopiowany lub utracony kod',
        'replace_hint' => 'Zastąpienie kodu natychmiast unieważnia każdą zapisaną lub wydrukowaną kopię.',
        'replace_confirm' => 'Rozumiem, że mój obecny kod przestanie działać.',
        'revoke' => 'Unieważnij kod',
        'reason' => 'Powód unieważnienia',
        'reason_hint' => 'Podaj krótki powód operacyjny bez informacji wrażliwych.',
        'revoke_confirm' => 'Rozumiem, że ten kod natychmiast przestanie działać.',
    ],
    'device' => [
        'lost' => 'W razie zgubienia urządzenia personelu natychmiast cofnij jego dostęp w standardowym obszarze wydarzeń. Tutaj kontynuuj według nazwiska lub podpisanego kodu.',
    ],
];
