<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [
    'privacy' => [
        'title' => 'Registro privado y resistente',
        'body' => 'Los códigos firmados no contienen nombres, correos electrónicos ni teléfonos. Puedes introducir un código abajo sin usar una cámara.',
        'no_wallet' => 'Las acciones de asistencia nunca modifican saldos ni conceden créditos de tiempo.',
    ],
    'code' => [
        'title' => 'Introducir un código de asistente firmado',
        'intro' => 'Usa esta alternativa en línea si no están disponibles la cámara o el dispositivo sin conexión del personal. La búsqueda manual por nombre aparece más abajo.',
        'label' => 'Código del asistente',
        'hint' => 'Pega el código completo que empieza por nqx2_. El código no se guarda en el registro de auditoría.',
        'action' => 'Acción de asistencia',
        'reason' => 'Motivo de la corrección',
        'reason_hint' => 'Se exige un motivo para deshacer una acción. No incluyas información sensible.',
        'confirm' => 'He comprobado al asistente y he seleccionado la acción prevista.',
        'submit' => 'Aplicar la acción con código firmado',
    ],
    'actions' => [
        'check_in' => 'Registrar entrada',
        'check_out' => 'Registrar salida',
        'no_show' => 'Marcar ausencia',
        'undo' => 'Deshacer la última acción',
    ],
    'attendee' => [
        'manage_link' => 'Gestionar mi código de registro',
        'title' => 'Tu código de registro del evento',
        'intro' => 'Crea un código firmado para mostrárselo al personal del evento en pantalla o en una copia impresa.',
        'privacy' => 'El código identifica únicamente esta inscripción al evento. No contiene nombre, correo electrónico ni número de teléfono.',
        'notice_issued' => 'Tu nuevo código de registro se muestra a continuación.',
        'notice_replaced' => 'Tu código anterior ya no funciona. El sustituto se muestra a continuación.',
        'notice_revoked' => 'Tu código de registro se ha revocado.',
        'notice_already_active' => 'Ya existe un código activo. Sustitúyelo si no tienes disponible tu copia.',
        'notice_invalid' => 'Confirma la acción solicitada e inténtalo de nuevo.',
        'notice_failed' => 'No se pudo cambiar el código de registro. Actualiza la página e inténtalo de nuevo.',
        'status_heading' => 'Estado del código',
        'status_active' => 'Activo',
        'status_rotated' => 'Sustituido',
        'status_revoked' => 'Revocado',
        'status_expired' => 'Caducado',
        'expires' => 'Caduca el :date',
        'one_shot_heading' => 'Guarda este código ahora',
        'one_shot' => 'Por seguridad, el código completo solo se muestra al crearlo o sustituirlo.',
        'code_label' => 'Código de registro firmado',
        'code_hint' => 'Selecciona y copia el código completo que comienza por nqx2_.',
        'print_hint' => 'Puedes imprimir esta página o guardar una copia accesible. Mantén el código privado hasta el registro.',
        'print' => 'Imprimir este código',
        'issue_confirm' => 'Entiendo que el código completo solo se mostrará una vez.',
        'issue' => 'Crear código de registro',
        'replace' => 'Sustituir código copiado o perdido',
        'replace_hint' => 'Sustituir el código invalida inmediatamente cualquier copia guardada o impresa.',
        'replace_confirm' => 'Entiendo que mi código actual dejará de funcionar.',
        'revoke' => 'Revocar código',
        'reason' => 'Motivo de la revocación',
        'reason_hint' => 'Indica un motivo operativo breve sin información confidencial.',
        'revoke_confirm' => 'Entiendo que este código dejará de funcionar inmediatamente.',
    ],
    'device' => [
        'lost' => 'Si se pierde un dispositivo del personal, revócalo de inmediato en el espacio de eventos estándar. Continúa aquí por nombre o código firmado.',
    ],
];
