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

// ============ activity.json ============
applyTranslations('activity.json', {
  'meta.title': 'Painel de Atividade',
  'meta.description': 'A sua visão geral de atividade — horas, competências, conexões e ações recentes.',
  'page_title': 'Painel de Atividade',
  'page_subtitle': 'A sua participação comunitária num relance.',
  'error_load_failed': 'Falha ao carregar o painel de atividade',
  'error_load_failed_detail': 'Falha ao carregar os seus dados de atividade. Tente novamente.',
  'unable_to_load': 'Não foi possível carregar',
  'try_again': 'Tentar Novamente',
  'loading': 'A carregar dados de atividade...',
  'refresh': 'Atualizar',
  'stats.hours_given': 'Horas Dadas',
  'stats.hours_received': 'Horas Recebidas',
  'stats.connections': 'Conexões',
  'stats.exchanges': 'Trocas',
  'stats.pending_requests': 'Pedidos Pendentes',
  'stats.transactions_given': 'Transações Dadas',
  'stats.transactions_received': 'Transações Recebidas',
  'chart.given': 'Dados',
  'chart.received': 'Recebidos',
  'chart.monthly_activity': 'Atividade Mensal',
  'chart.no_data': 'Sem dados de gráfico disponíveis',
  'recent_activity': 'Atividade Recente',
  'empty_title': 'Sem atividade recente',
  'empty_description': 'Comece a participar na sua comunidade para ver a sua atividade aqui.',
  'quick_stats': 'Estatísticas Rápidas',
  'groups_joined': 'Grupos Aderidos',
  'posts_30d': 'Publicações (30d)',
  'comments_30d': 'Comentários (30d)',
  'likes_given_30d': 'Gostos Dados (30d)',
  'likes_received_30d': 'Gostos Recebidos (30d)',
  'net_balance': 'Saldo Líquido',
  'my_skills': 'As Minhas Competências',
  'skill_offer': 'Oferta',
  'skill_request': 'Pedido',
  'activity_types.exchange': 'Troca',
  'activity_types.listing': 'Anúncio',
  'activity_types.connection': 'Conexão',
  'activity_types.event': 'Evento',
  'activity_types.message': 'Mensagem',
  'activity_types.review': 'Avaliação',
  'activity_types.post': 'Publicação',
  'activity_types.default': 'Atividade'
});

// ============ auth.json ============
applyTranslations('auth.json', {
  'login.hide_password': 'Ocultar palavra-passe',
  'login.show_password': 'Mostrar palavra-passe',
  'register.type_individual': 'Individual',
  'register.hide_password': 'Ocultar palavra-passe',
  'register.show_password': 'Mostrar palavra-passe',
  'register.phone_error': 'Introduza um número internacional válido (ex.: +1 555 123 4567)',
  'reset_password.hide_password': 'Ocultar palavra-passe',
  'reset_password.show_password': 'Mostrar palavra-passe'
});

// ============ chat.json ============
applyTranslations('chat.json', {
  'page_title': 'Assistente de IA',
  'aria_chat': 'Chat de IA',
  'header_title': 'Assistente de IA',
  'header_subtitle': 'Alimentado por IA',
  'limits_left_today': '{{count}} restantes hoje',
  'new_conversation_aria': 'Iniciar nova conversa',
  'typing_aria': 'A IA está a escrever',
  'error_label': 'Erro',
  'input_aria': 'Mensagem',
  'input_placeholder': 'Pergunte o que quiser... (Enter para enviar, Shift+Enter para nova linha)',
  'send_aria': 'Enviar mensagem',
  'disclaimer': 'As respostas da IA podem nem sempre ser precisas. Verifique informações importantes.',
  'empty_title': 'Assistente de IA',
  'empty_description': 'Pergunte-me qualquer coisa sobre timebanking, a sua conta ou esta comunidade.',
  'try_asking': 'Experimente perguntar...',
  'feature_unavailable_title': 'Assistente de IA Indisponível',
  'feature_unavailable_description': 'A funcionalidade de Assistente de IA não está ativada para esta comunidade. Contacte o administrador do seu banco de tempo para saber mais.',
  'error_generic': 'Algo correu mal. Tente novamente.',
  'error_rate_limit': 'Atingiu o seu limite de utilização por agora. Tente novamente mais tarde.',
  'error_connection': 'Falha ao conectar ao serviço de IA. Verifique a sua ligação e tente novamente.',
  'toast_rate_limit': 'Limite de utilização atingido',
  'toast_connection_error': 'Falha ao contactar o serviço de IA',
  'starter_q1': 'Que créditos de tempo tenho e como posso usá-los?',
  'starter_q2': 'Que competências os membros da comunidade estão a oferecer?',
  'starter_q3': 'Como funciona o timebanking?',
  'starter_q4': 'Que eventos se aproximam?',
  'starter_q5': 'Como crio um anúncio para oferecer as minhas competências?'
});

// ============ common.json ============
applyTranslations('common.json', {
  'accessibility.scroll_to_top': 'Voltar ao topo',
  'accessibility.breadcrumb': 'Migalhas de pão',
  'loading': 'A carregar...',
  'feature_error.load_failed': 'Não foi possível carregar {{feature}}. Esta secção pode estar temporariamente indisponível.',
  'dev_banner.aria_label': 'Estado de desenvolvimento',
  'dev_banner.status': 'Estado de desenvolvimento: {{stage}}',
  'dev_banner.read_more': 'Ler mais',
  'skills.browse_title': 'Diretório de Competências da Comunidade',
  'skills.load_failed': 'Falha ao carregar categorias de competências',
  'skills.load_failed_retry': 'Falha ao carregar categorias de competências. Tente novamente.',
  'skills.offering_label': 'A Oferecer',
  'skills.offering_desc': 'membros dispostos a partilhar esta competência com outros',
  'skills.requesting_label': 'A Procurar',
  'skills.requesting_desc': 'membros que procuram aprender ou receber ajuda com esta competência',
  'skills.add_your_skills': 'Adicionar as suas competências',
  'skills.categories': 'Categorias',
  'skills.sub_categories': 'Subcategorias',
  'skills.total_skills': 'Competências Listadas',
  'skills.search_placeholder': 'Pesquisar categorias de competências...',
  'skills.no_categories': 'Nenhuma categoria encontrada',
  'skills.no_match': 'Nenhuma categoria corresponde a "{{query}}"',
  'skills.no_categories_yet': 'Ainda não existem categorias de competências.',
  'skills.members_with_skills': 'membros qualificados',
  'skills.loading_skills': 'A carregar competências...',
  'skills.skills_in_category': 'Competências',
  'skills.offering': 'a oferecer',
  'skills.requesting': 'a procurar',
  'skills.no_skills_yet': 'Ainda não existem competências nesta categoria.',
  'skills.members_with': 'Membros com "{{skill}}"',
  'skills.offers': 'Ofertas',
  'skills.wants': 'Procuras',
  'skills.no_members': 'Nenhum membro encontrado com esta competência.',
  'skills.proficiency.beginner': 'Principiante',
  'skills.proficiency.intermediate': 'Intermédio',
  'skills.proficiency.advanced': 'Avançado',
  'skills.proficiency.expert': 'Especialista'
});

// ============ connections.json ============
applyTranslations('connections.json', {
  'title': 'Conexões',
  'subtitle': 'Gerir as suas conexões comunitárias',
  'search_placeholder': 'Pesquisar por nome ou localização...',
  'tab_my_connections': 'As Minhas Conexões',
  'tab_pending': 'Pendentes',
  'tab_sent': 'Enviados',
  'connected_since': 'Conectado desde {{date}}',
  'message': 'Mensagem',
  'disconnect': 'Desconectar',
  'wants_to_connect': 'Quer conectar-se consigo',
  'accept': 'Aceitar',
  'decline': 'Recusar',
  'request_pending': 'Pedido pendente',
  'cancel_request': 'Cancelar Pedido',
  'load_more': 'Carregar mais',
  'find_members': 'Encontrar Membros',
  'empty_no_connections_title': 'Sem conexões ainda',
  'empty_no_connections_search': 'Nenhuma conexão corresponde à sua pesquisa.',
  'empty_no_connections_description': 'Encontre membros para se conectar e construir a sua rede comunitária.',
  'empty_no_pending_title': 'Sem pedidos pendentes',
  'empty_no_pending_search': 'Nenhum pedido pendente corresponde à sua pesquisa.',
  'empty_no_pending_description': 'Quando alguém lhe enviar um pedido de conexão, aparecerá aqui.',
  'empty_no_sent_title': 'Sem pedidos enviados',
  'empty_no_sent_search': 'Nenhum pedido enviado corresponde à sua pesquisa.',
  'empty_no_sent_description': 'Os pedidos de conexão que enviar aparecerão aqui até serem aceites ou recusados.',
  'toast_load_failed': 'Falha ao carregar conexões',
  'toast_accepted': 'Conexão aceite!',
  'toast_accept_failed': 'Falha ao aceitar conexão',
  'toast_declined': 'Pedido recusado',
  'toast_decline_failed': 'Falha ao recusar pedido',
  'toast_disconnected': 'Desconectado',
  'toast_disconnect_failed': 'Falha ao desconectar',
  'toast_cancelled': 'Pedido cancelado',
  'toast_cancel_failed': 'Falha ao cancelar pedido'
});

// ============ dashboard.json ============
applyTranslations('dashboard.json', {
  'sections.endorsements': 'Recomendações'
});

// ============ exchanges.json ============
applyTranslations('exchanges.json', {
  'detail.prep_time': 'Tempo de Preparação',
  'detail.ratings': 'Avaliações',
  'detail.rate_exchange': 'Avaliar Esta Troca'
});

// ============ explore.json ============
applyTranslations('explore.json', {
  'page_title': 'Descobrir',
  'heading': 'Descobrir',
  'subtitle': 'Encontre competências, eventos, grupos e mais na sua comunidade',
  'search_placeholder': 'Descobrir competências, eventos, grupos...',
  'stats.members': 'Membros',
  'stats.exchanges_this_month': 'Trocas Este Mês',
  'stats.hours_exchanged': 'Horas Trocadas',
  'stats.active_listings': 'Anúncios Ativos',
  'trending_posts.title': 'Publicações em Destaque',
  'trending_posts.subtitle': 'As publicações mais envolventes desta semana',
  'popular_listings.title': 'Anúncios Populares',
  'popular_listings.subtitle': 'Ofertas e pedidos mais vistos',
  'upcoming_events.title': 'Próximos Eventos',
  'upcoming_events.subtitle': 'Junte-se à sua comunidade',
  'active_groups.title': 'Grupos Ativos',
  'active_groups.subtitle': 'Conecte-se com membros de interesses semelhantes',
  'active_groups.view_group': 'Ver Grupo',
  'top_contributors.title': 'Principais Contribuidores',
  'top_contributors.subtitle': 'Líderes comunitários por experiência',
  'top_contributors.level': 'Nv.{{level}}',
  'trending_hashtags.title': 'Hashtags em Destaque',
  'trending_hashtags.subtitle': 'Tópicos populares na sua comunidade',
  'recommended.title': 'Recomendado Para Si',
  'recommended.subtitle': 'Com base nos seus interesses e atividade',
  'recommended.by_author': 'por {{name}}',
  'new_members.title': 'Bem-vindos aos Novos Membros',
  'new_members.subtitle': 'Diga olá aos membros recentes',
  'new_members.connect': 'Conectar',
  'featured_challenges.title': 'Desafios em Destaque',
  'featured_challenges.subtitle': 'Partilhe as suas ideias e cause impacto',
  'featured_challenges.ends': 'Termina {{date}}',
  'time_ago.just_now': 'Agora mesmo'
});

// ============ events.json ============
applyTranslations('events.json', {
  'loading_aria': 'A carregar eventos',
  'category.workshop': 'Workshop',
  'category.social': 'Social',
  'detail.tab_checkin': 'Check-in',
  'form.error_not_found': 'Evento não encontrado',
  'form.error_load_failed': 'Falha ao carregar o evento. Tente novamente.',
  'form.recurring_toggle': 'Tornar este um evento recorrente',
  'form.recurring_desc': 'Criar automaticamente múltiplas ocorrências',
  'form.recurring_toggle_aria': 'Alternar evento recorrente',
  'form.recurrence_frequency': 'Frequência',
  'form.recurrence_frequency_aria': 'Frequência de recorrência',
  'form.freq_daily': 'Diário',
  'form.freq_weekly': 'Semanal',
  'form.freq_biweekly': 'Quinzenal (a cada 2 semanas)',
  'form.freq_monthly': 'Mensal',
  'form.recurrence_days': 'Repetir em',
  'form.recurrence_end_type': 'Termina',
  'form.recurrence_end_type_aria': 'Como a série termina',
  'form.end_after_count': 'Após X ocorrências',
  'form.end_on_date': 'Numa data específica',
  'form.recurrence_count': 'Número de ocorrências',
  'form.recurrence_end_date': 'Data de fim da série',
  'reminder_settings': 'Configurações de Lembrete de Eventos',
  'reminder.title': 'Lembretes de Eventos',
  'reminder.coming_soon_desc': 'As preferências globais de lembrete estarão disponíveis em breve. Entretanto, pode definir lembretes em eventos individuais a partir da sua página de detalhes.'
});

// ============ gamification.json ============
applyTranslations('gamification.json', {
  'achievements.daily_reward.loading': 'A carregar recompensa diária',
  'achievements.loading': 'A carregar conquistas',
  'goals.loading': 'A carregar objetivos',
  'goals.detail.social': 'Social',
  'leaderboard.loading': 'A carregar tabela de classificação',
  'leaderboard.season.loading': 'A carregar temporada'
});

// ============ group_exchanges.json ============
applyTranslations('group_exchanges.json', {
  'create.no_participants_yet': 'Ainda não foram adicionados participantes'
});

// ============ ideation.json ============
applyTranslations('ideation.json', {
  'media.url_placeholder': 'https://...',
  'media.type_link': 'Ligação'
});

// ============ listings.json ============
applyTranslations('listings.json', {
  'saved_success': 'Anúncio guardado',
  'unsaved_success': 'Anúncio removido dos guardados',
  'save_error': 'Falha ao atualizar anúncio guardado',
  'featured': 'Destaque',
  'featured_badge': 'Anúncio em destaque',
  'skill_tags.label': 'Etiquetas de Competências',
  'skill_tags.placeholder': 'Escreva uma competência e prima Enter...',
  'skill_tags.aria_add': 'Adicionar etiqueta de competência',
  'analytics.title': 'Análises do Anúncio',
  'analytics.total_views': 'Total de Visualizações',
  'analytics.contacts': 'Contactos',
  'analytics.saves': 'Guardados',
  'analytics.trend_7day': 'Tendência de 7 Dias',
  'analytics.vs_previous_week': 'vs. semana anterior',
  'analytics.views_last_days': 'Visualizações (Últimos {{days}} Dias)'
});

// ============ profile.json ============
applyTranslations('profile.json', {
  'listing_image_alt': 'Imagem do anúncio',
  'credits_sent_success': 'Créditos enviados para {{name}}'
});

// ============ utility.json ============
applyTranslations('utility.json', {
  'custom_page.default_title': 'Página',
  'custom_page.not_found_description': 'A página que procura não existe ou já não está disponível.',
  'custom_page.back_to_home': 'Voltar ao Início'
});

console.log('\n--- Batch 1 complete ---');
