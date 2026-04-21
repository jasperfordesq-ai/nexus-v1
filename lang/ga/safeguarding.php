<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [
    'errors' => [
        'vetting_required' => 'Éilíonn an ball seo grinnfhiosrúchán breise sula bhféadfá teagmháil a dhéanamh leis. Comhlánaigh na seiceálacha riachtanacha i do phróifíl le leanúint ar aghaidh.',
        'vetting_check_failed' => 'Níorbh fhéidir linn do stádas grinnfhiosrúcháin a dheimhniú faoi láthair. Bain triail eile as i gceann tamaill.',
        'statement_required' => 'Teastaíonn Ráiteas Cosanta Leanaí (PDF) sula bhféadfaidh tú a dhearbhú go n-oibríonn an pobal seo le leanaí nó le daoine fásta leochaileacha. Uaslódáil ceann chun leanúint ar aghaidh.',
        'invalid_file' => 'Níorbh fhéidir an comhad uaslódáilte a léamh. Bain triail as comhad PDF bailí.',
        'pdf_required' => 'Caithfidh an ráiteas cosanta a bheith ina chomhad PDF.',
        'file_too_large' => 'Tá an comhad ráitis cosanta ró-mhór. 10MB an uasmhéid.',
        'storage_failed' => 'Níorbh fhéidir linn an comhad a shábháil. Bain triail eile as.',
        'statement_missing' => 'Níl aon ráiteas cosanta ar comhad don phobal seo.',
        'file_missing' => 'Níorbh fhéidir an comhad ráitis cosanta a aimsiú ar an bhfreastalaí. Uaslódáil arís é.',
        'revoke_failed' => 'Níorbh fhéidir an rogha sin a chúlghairm. B’fhéidir go raibh sí cúlghairthe cheana.',
    ],
    'confirmation' => [
        'title' => 'Sábháladh do shainroghanna cosanta',
        'intro' => 'Go raibh maith agat as an eolas seo a roinnt. Seo achoimre ar do rogha, cé a fheiceann é, agus cad a thosaíonn mar thoradh air.',
        'your_selections' => 'Do roghanna',
        'no_selections' => 'Níor roghnaigh tú aon rogha cosanta.',
        'who_can_see_heading' => 'Cé a fheiceann é',
        'who_can_see_body' => 'Ní fheiceann ach comhordaitheoirí agus riarthóirí an phobail na sainroghanna seo. Ní fheiceann baill eile iad. Déantar gach rochtain a logáil.',
        'what_activates_heading' => 'Cad a thosaíonn mar thoradh air',
        'activation_broker_review' => 'Déanfaidh comhordaitheoir athbhreithniú ar theachtaireachtaí a sheolann tú agus a fhaigheann tú.',
        'activation_match_approval' => 'Ceadóidh comhordaitheoir meaitseálacha a bhaineann leat sula moltar don bhall eile iad.',
        'activation_discovery_hidden' => 'Beidh tú i bhfolach ó fhionnachtain ag baill nach bhfuil an grinnfhiosrúchán riachtanach acu.',
        'activation_notification' => 'Cuireadh comhordaitheoir ar an eolas agus rachaidh sé/sí i dteagmháil leat chun plé a dhéanamh ar conas is féidir linn cabhrú.',
        'activation_none' => 'Ní thosaíonn aon chosaint uathoibríoch ó na roghanna seo. Cuirtear do shainroghanna ar taifead d’fheasacht an chomhordaitheora.',
        'revoke_heading' => 'Conas iad seo a athrú nó a chúlghairm aon uair',
        'revoke_body' => 'Is féidir leat aon cheann de na sainroghanna seo a athbhreithniú nó a chúlghairm aon uair ó do shocruithe próifíle. Ní gá duit iarraidh ar riarthóir é seo a dhéanamh.',
        'revoke_cta' => 'Téigh go socruithe cosanta',
        'continue_cta' => 'Lean ar aghaidh',
    ],
    'settings' => [
        'page_title' => 'Sainroghanna cosanta',
        'intro' => 'Athbhreithnigh nó cúlghair na sainroghanna cosanta a shocraigh tú le linn an dul isteach. Feiceann na comhordaitheoirí iad seo ach ní fheiceann baill eile iad.',
        'no_preferences' => 'Níl aon sainroghanna cosanta gníomhacha agat. Is féidir leat iad seo a shocrú aon uair ó leathanach cabhrach na cosanta.',
        'selected_on' => 'Roghnaithe ar :date',
        'revoke_button' => 'Cúlghair',
        'revoke_confirm_title' => 'Cúlghair an rogha seo?',
        'revoke_confirm_body' => 'Ní bheidh feidhm ag an rogha seo a thuilleadh ar do chuntas. Cuirfear do chomhordaitheoirí ar an eolas faoin athrú.',
        'revoke_confirm_yes' => 'Tá, cúlghair',
        'revoke_confirm_no' => 'Coinnigh í',
        'revoked_toast' => 'Cúlghaireadh an rogha.',
        'revoke_error_toast' => 'Chuaigh rud éigin amú. Bain triail eile as.',
    ],
    'review' => [
        'reminder_subject' => 'Déan athbhreithniú ar do shainroghanna cosanta',
        'reminder_title' => 'Am chun athbhreithniú a dhéanamh ar do shainroghanna cosanta',
        'reminder_body' => 'Tá níos mó ná bliain caite ó shocraigh tú do shainroghanna cosanta le haghaidh :community. Tóg nóiméad chun athbhreithniú a dhéanamh agus a dheimhniú go bhfuil siad fós i bhfeidhm, nó cúlghair aon cheann nach bhfuil.',
        'reminder_cta' => 'Athbhreithniú a dhéanamh ar shainroghanna',
        'escalation_subject' => 'Athbhreithniú cosanta baill gan freagairt',
        'escalation_title' => 'Athbhreithniú bliantúil cosanta gan freagairt',
        'escalation_body' => 'Níor fhreagair :name iarratas chun athbhreithniú a dhéanamh ar a shainroghanna cosanta i 30 lá. Fanann a shainroghanna gníomhach — tá an ceart ag an mball iad a choinneáil. Téigh i dteagmháil go díreach más maith leat labhairt leo.',
        'escalation_cta' => 'Féach ar bhall sa deais cosanta',
    ],
];
