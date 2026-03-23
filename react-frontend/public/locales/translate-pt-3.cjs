const fs = require('fs');
const path = require('path');

const ptDir = path.join(__dirname, 'pt');

function setNestedKey(obj, dotKey, value) {
  const keys = dotKey.split('.');
  let current = obj;
  for (let i = 0; i < keys.length - 1; i++) {
    if (!current[keys[i]] || typeof current[keys[i]] !== 'object') {
      current[keys[i]] = {};
    }
    current = current[keys[i]];
  }
  current[keys[keys.length - 1]] = value;
}

function applyTranslations(filename, translations) {
  const ptPath = path.join(ptDir, filename);
  const ptData = JSON.parse(fs.readFileSync(ptPath, 'utf8'));

  for (const [dotKey, value] of Object.entries(translations)) {
    setNestedKey(ptData, dotKey, value);
  }

  fs.writeFileSync(ptPath, JSON.stringify(ptData, null, 2) + '\n', 'utf8');
  console.log('Updated: ' + filename + ' (' + Object.keys(translations).length + ' keys)');
}

// ============ feed.json ============
applyTranslations('feed.json', {
  'page_title': 'Feed',
  'session_expired': 'A sua sessão expirou. Inicie sessão novamente.',
  'filter.jobs': 'Empregos',
  'filter.challenges': 'Desafios',
  'filter.volunteering': 'Voluntariado',
  'filter.blogs': 'Blogue',
  'compose.template_event_workshop': 'Workshop',
  'card.type_post': 'Publicação',
  'card.type_listing': 'Anúncio',
  'card.type_event': 'Evento',
  'card.type_poll': 'Sondagem',
  'card.type_goal': 'Objetivo',
  'card.type_review': 'Avaliação',
  'card.type_challenge': 'Desafio',
  'card.type_volunteer': 'Voluntariado',
  'card.type_blog': 'Blogue',
  'card.type_discussion': 'Discussão',
  'card.post_options': 'Opções da publicação',
  'card.post_actions': 'Ações da publicação',
  'card.send_comment': 'Enviar comentário',
  'card.share_action': 'Partilhar',
  'card.shared_success': 'Publicação partilhada no seu feed',
  'card.share_failed': 'Falha ao partilhar',
  'card.reaction': 'reação',
  'card.reactions': 'reações',
  'card.reactions_title': 'Reações',
  'card.no_reactions': 'Sem reações ainda',
  'card.load_more': 'Carregar Mais',
  'card.other': 'outro',
  'card.others': 'outros',
  'card.detail_listing': 'Ver Anúncio',
  'card.detail_event': 'Ver Evento',
  'card.detail_goals': 'Ver Objetivos',
  'card.detail_profile': 'Ver Perfil',
  'card.detail_job': 'Ver Emprego',
  'card.detail_challenge': 'Ver Desafio',
  'card.detail_volunteer': 'Ver Oportunidade',
  'card.detail_blog': 'Ler Artigo',
  'hashtag.title': 'Hashtag',
  'hashtag.page_description': 'Publicações com a etiqueta #{{tag}}',
  'sidebar.profile.listings': 'Anúncios',
  'sidebar.profile.given': 'Dados',
  'sidebar.profile.received': 'Recebidos',
  'sidebar.profile.offers': 'Ofertas',
  'sidebar.profile.requests': 'Pedidos',
  'sidebar.friends.title': 'Amigos',
  'sidebar.friends.see_all': 'Ver Todos',
  'sidebar.friends.online': 'Online agora',
  'sidebar.friends.active_today': 'Ativo hoje',
  'sidebar.pulse.title': 'Pulso da Comunidade',
  'sidebar.pulse.members': 'Membros',
  'sidebar.pulse.listings': 'Anúncios',
  'sidebar.pulse.events': 'Eventos',
  'sidebar.pulse.groups': 'Grupos',
  'sidebar.suggested.title': 'Sugerido Para Si',
  'sidebar.suggested.see_all': 'Ver Todos',
  'sidebar.suggested.by': 'por {{name}}',
  'sidebar.categories.title': 'Categorias Principais',
  'sidebar.categories.all_listings': 'Todos os Anúncios',
  'sidebar.people.title': 'Pessoas que Pode Conhecer',
  'sidebar.people.see_all': 'Ver Todos',
  'sidebar.people.view': 'Ver',
  'sidebar.events.title': 'Próximos Eventos',
  'sidebar.events.see_all': 'Ver Todos',
  'sidebar.groups.title': 'Grupos Populares',
  'sidebar.groups.see_all': 'Ver Todos',
  'sidebar.actions.title': 'Ações Rápidas',
  'sidebar.actions.create_listing': 'Criar Novo Anúncio',
  'sidebar.actions.create_listing_desc': 'Partilhe as suas competências com a comunidade',
  'sidebar.actions.host_event': 'Organizar Evento',
  'sidebar.actions.create_poll': 'Criar Sondagem',
  'sidebar.actions.set_goal': 'Definir Objetivo',
  'sidebar.actions.groups': 'Grupos',
  'mode.for_you': 'Para Si',
  'mode.recent': 'Recentes',
  'location.global': 'Global',
  'location.nearby': 'Perto de Mim',
  'location.add_location': 'Adicionar localização no perfil',
  'subfilter.offers': 'Ofertas',
  'subfilter.requests': 'Pedidos',
  'stories.add': 'Adicionar História',
  'stories.your_story': 'A Sua História',
  'stories.scroll_left': 'Deslocar histórias para a esquerda',
  'stories.scroll_right': 'Deslocar histórias para a direita',
  'stories.create_your_story': 'Criar a sua história',
  'stories.view_story_from': 'Ver história de {{name}}',
  'fab.create': 'Criar publicação',
  'carousel.aria_label': 'Carrossel de imagens, {{current}} de {{total}}',
  'carousel.image_of': 'Imagem {{current}} de {{total}}',
  'carousel.view_image': 'Ver imagem {{current}} de {{total}}',
  'carousel.previous': 'Imagem anterior',
  'carousel.next': 'Próxima imagem',
  'carousel.go_to_image': 'Ir para imagem {{number}}',
  'lightbox.aria_label': 'Visualizador de imagens',
  'lightbox.close': 'Fechar visualizador de imagens',
  'reaction.like': 'Gosto',
  'reaction.love': 'Adorar',
  'reaction.laugh': 'Rir',
  'reaction.celebrate': 'Celebrar',
  'reaction.clap': 'Aplaudir',
  'reaction.time_credit': 'Crédito de Tempo',
  'reaction.react_to_post': 'Reagir a esta publicação',
  'video.select': 'Adicionar Vídeo',
  'video.too_large': 'O vídeo deve ter menos de {{size}}MB',
  'video.invalid_type': 'Formato de vídeo inválido. Suportados: MP4, WebM, MOV',
  'video.remove': 'Remover vídeo'
});

// ============ legal.json ============
applyTranslations('legal.json', {
  'privacy.nav_cookies': 'Cookies',
  'cookies.provider_sentry': 'Sentry',
  'cookies.third_party_sentry_label': 'Sentry',
  'cookies.third_party_pusher_label': 'Pusher',
  'cookies.browser_chrome': 'Chrome',
  'cookies.browser_firefox': 'Firefox',
  'cookies.browser_safari': 'Safari',
  'cookies.browser_edge': 'Edge',
  'accessibility.feedback_title': 'Comentários',
  'version_history.original': 'Original',
  'platform.effective_date': 'Efetivo {{date}}',
  'platform.platform_chip': 'Plataforma Project NEXUS',
  'platform.provider_notice_title': 'Aviso do Fornecedor da Plataforma',
  'platform.provider_notice_body': 'Este documento rege a relação entre si e o Project NEXUS (o fornecedor da plataforma). É separado de quaisquer termos estabelecidos por {{tenant}} (o operador da sua comunidade).',
  'platform.view_tenant_legal': 'Ver documentos legais de {{tenant}}',
  'platform.contents': 'Índice',
  'platform.related_documents': 'Documentos Relacionados da Plataforma',
  'platform.cta_title': 'Questões Sobre Este Documento?',
  'platform.cta_body': 'Se tiver questões sobre este documento da plataforma, contacte a equipa do Project NEXUS. Para questões sobre as políticas da sua comunidade, contacte {{tenant}} diretamente.',
  'platform.nexus_website': 'Website do Project NEXUS',
  'platform.contact_tenant': 'Contactar {{tenant}}',
  'platform_disclaimer.page_title': 'Aviso Legal da Plataforma',
  'platform_disclaimer.title': 'Aviso Legal da Plataforma',
  'platform_disclaimer.subtitle': 'Avisos legais importantes e limitações de responsabilidade da plataforma Project NEXUS',
  'platform_disclaimer.link_terms': 'Termos de Serviço da Plataforma',
  'platform_disclaimer.link_privacy': 'Política de Privacidade da Plataforma',
  'platform_privacy.page_title': 'Política de Privacidade da Plataforma',
  'platform_privacy.title': 'Política de Privacidade da Plataforma',
  'platform_privacy.subtitle': 'Como o Project NEXUS trata os dados ao nível da infraestrutura da plataforma',
  'platform_privacy.link_terms': 'Termos de Serviço da Plataforma',
  'platform_privacy.link_disclaimer': 'Aviso Legal da Plataforma',
  'platform_terms.page_title': 'Termos de Serviço da Plataforma',
  'platform_terms.title': 'Termos de Serviço da Plataforma',
  'platform_terms.subtitle': 'Os termos que regem a infraestrutura da plataforma Project NEXUS',
  'platform_terms.link_privacy': 'Política de Privacidade da Plataforma',
  'platform_terms.link_disclaimer': 'Aviso Legal da Plataforma',
  'gate.title': 'Documentos legais atualizados',
  'gate.subtitle_one': 'Um documento foi atualizado. Reveja e aceite para continuar.',
  'gate.type_terms': 'Termos de Serviço',
  'gate.type_privacy': 'Política de Privacidade',
  'gate.type_cookies': 'Política de Cookies',
  'gate.type_accessibility': 'Declaração de Acessibilidade',
  'gate.type_community_guidelines': 'Diretrizes da Comunidade',
  'gate.type_acceptable_use': 'Política de Utilização Aceitável',
  'gate.updated': 'Atualizado',
  'gate.read': 'Ler',
  'gate.consent_text': 'Ao clicar em Aceitar, confirma que leu e concorda com os documentos listados acima.',
  'gate.accept_aria': 'Aceitar todos os documentos legais atualizados e continuar',
  'gate.accepting': 'A aceitar\u2026',
  'gate.accept_continue': 'Aceitar e Continuar',
  'custom.effective_label': 'Efetivo: {{date}}',
  'custom.contents': 'Índice',
  'custom.view_previous_versions': 'Ver versões anteriores deste documento',
  'custom.cta_title': 'Tem Questões?',
  'custom.cta_body': 'Se tiver alguma questão sobre este documento, contacte-nos.',
  'custom.contact_us': 'Contacte-nos'
});

// ============ wallet.json ============
applyTranslations('wallet.json', {
  'donate': 'Doar',
  'donate_credits': 'Doar Créditos',
  'donate_to': 'Doar para',
  'donate_to_community': 'Doar ao Fundo Comunitário',
  'donated': 'Doado',
  'donate_amount': 'Montante (horas)',
  'donate_message': 'Mensagem (opcional)',
  'donate_confirm': 'Doar',
  'donate_success': 'Doação enviada!',
  'donate_success_desc': 'Obrigado pela sua generosidade.',
  'donate_failed': 'Doação falhou',
  'donate_community_fund': 'Fundo Comunitário',
  'donate_community_desc': 'Apoie o fundo de créditos de tempo da sua comunidade',
  'donate_member': 'Outro Membro',
  'donate_member_desc': 'Oferecer créditos diretamente a outro membro',
  'donate_recipient_id': 'ID do Destinatário',
  'donate_recipient_placeholder': 'Introduza o ID do membro',
  'donate_message_placeholder': 'Adicione uma nota à sua doação...',
  'donate_balance_info': 'O seu saldo: {{balance}} horas',
  'donate_invalid_amount': 'Montante inválido',
  'donate_invalid_amount_desc': 'Introduza um montante superior a 0',
  'donate_insufficient': 'Saldo insuficiente',
  'donate_insufficient_desc': 'Não tem créditos suficientes',
  'donate_recipient_required': 'Destinatário obrigatório',
  'donate_recipient_required_desc': 'Introduza um ID de destinatário',
  'donate_error': 'Ocorreu um erro. Tente novamente.',
  'cancel': 'Cancelar',
  'community_fund': 'Fundo Comunitário',
  'community_fund_desc': 'Fundo partilhado de créditos de tempo',
  'community_fund_hours': 'horas disponíveis',
  'community_fund_deposited': 'Depositado',
  'community_fund_withdrawn': 'Levantado'
});

// ============ admin_dashboard.json (1 key) ============
applyTranslations('admin_dashboard.json', {
  'modules.timebanking': 'Banco de Tempo'
});

// ============ admin_nav.json (3 keys) ============
applyTranslations('admin_nav.json', {
  'newsletters': 'Newsletters',
  'enterprise': 'Enterprise',
  'super_admin': 'Super Admin'
});

console.log('\n--- Batch 3 complete ---');
