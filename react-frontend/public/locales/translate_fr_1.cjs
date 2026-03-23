// French translation script - Part 1 (about through dashboard)
const fs = require('fs');
const path = require('path');

const enDir = path.join(__dirname, 'en');
const frDir = path.join(__dirname, 'fr');

function setNested(obj, keyPath, value) {
  const parts = keyPath.split('.');
  let cur = obj;
  for (let i = 0; i < parts.length - 1; i++) {
    if (!cur[parts[i]]) cur[parts[i]] = {};
    cur = cur[parts[i]];
  }
  cur[parts[parts.length - 1]] = value;
}

function getNested(obj, keyPath) {
  const parts = keyPath.split('.');
  let cur = obj;
  for (const p of parts) {
    if (cur == null) return undefined;
    cur = cur[p];
  }
  return cur;
}

function applyTranslations(filename, trans) {
  const frFile = path.join(frDir, filename);
  const enFile = path.join(enDir, filename);
  if (!fs.existsSync(frFile) || !fs.existsSync(enFile)) { console.log(`SKIP: ${filename}`); return 0; }
  const frData = JSON.parse(fs.readFileSync(frFile, 'utf8'));
  const enData = JSON.parse(fs.readFileSync(enFile, 'utf8'));
  let count = 0;
  for (const [key, frValue] of Object.entries(trans)) {
    const enValue = getNested(enData, key);
    const curFr = getNested(frData, key);
    if (curFr === enValue && frValue !== enValue) {
      setNested(frData, key, frValue);
      count++;
    }
  }
  if (count > 0) fs.writeFileSync(frFile, JSON.stringify(frData, null, 2) + '\n', 'utf8');
  console.log(`${filename}: ${count} translated`);
  return count;
}

let total = 0;

// about.json — only translate actual text, skip proper nouns/numbers
total += applyTranslations('about.json', {
  "strategic_plan.pillar_initiative_header": "Initiative",
  "strategic_plan.impact_header": "Impact"
});

// activity.json
total += applyTranslations('activity.json', {
  "meta.title": "Tableau d'activité",
  "meta.description": "Aperçu de votre activité — heures, compétences, connexions et actions récentes.",
  "page_title": "Tableau d'activité",
  "page_subtitle": "Votre participation communautaire en un coup d'œil.",
  "error_load_failed": "Impossible de charger le tableau d'activité",
  "error_load_failed_detail": "Impossible de charger vos données d'activité. Veuillez réessayer.",
  "unable_to_load": "Impossible de charger",
  "try_again": "Réessayer",
  "loading": "Chargement des données d'activité...",
  "refresh": "Actualiser",
  "stats.hours_given": "Heures données",
  "stats.hours_received": "Heures reçues",
  "stats.connections": "Connexions",
  "stats.exchanges": "Échanges",
  "stats.pending_requests": "Demandes en attente",
  "stats.transactions_given": "Transactions données",
  "stats.transactions_received": "Transactions reçues",
  "chart.given": "Données",
  "chart.received": "Reçues",
  "chart.monthly_activity": "Activité mensuelle",
  "chart.no_data": "Aucune donnée de graphique disponible",
  "recent_activity": "Activité récente",
  "empty_title": "Aucune activité récente",
  "empty_description": "Commencez à participer à votre communauté pour voir votre activité ici.",
  "quick_stats": "Statistiques rapides",
  "groups_joined": "Groupes rejoints",
  "posts_30d": "Publications (30j)",
  "comments_30d": "Commentaires (30j)",
  "likes_given_30d": "J'aime donnés (30j)",
  "likes_received_30d": "J'aime reçus (30j)",
  "net_balance": "Solde net",
  "my_skills": "Mes compétences",
  "skill_offer": "Offre",
  "skill_request": "Demande",
  "endorsements_count": "{{count}} recommandation",
  "endorsements_count_plural": "{{count}} recommandations",
  "activity_types.exchange": "Échange",
  "activity_types.listing": "Annonce",
  "activity_types.connection": "Connexion",
  "activity_types.event": "Événement",
  "activity_types.message": "Message",
  "activity_types.review": "Avis",
  "activity_types.post": "Publication",
  "activity_types.default": "Activité"
});

// admin_dashboard.json
total += applyTranslations('admin_dashboard.json', {
  "title": "Centre de contrôle",
  "stats.transactions": "Transactions",
  "modules.pages": "Pages",
  "modules.blog": "Blog",
  "modules.ai_assistant": "Assistant IA",
  "modules.algorithm_settings_desc": "MatchRank et CommunityRank",
  "modules.timebanking": "Banque de temps",
  "modules.seo": "Gestionnaire SEO",
  "modules.cron_jobs": "Tâches planifiées",
  "quick_actions.gamification": "Ludification",
  "enterprise.configuration": "Configuration",
  "system_status.cache": "Cache",
  "system_status.queue": "Tâches planifiées"
});

// admin_nav.json
total += applyTranslations('admin_nav.json', {
  "transactions": "Transactions",
  "messages": "Messages",
  "blog": "Blog",
  "pages": "Pages",
  "newsletters": "Bulletins d'information",
  "gamification": "Ludification",
  "enterprise": "Entreprise",
  "super_admin": "Super administrateur",
  "sections.intelligence": "Intelligence",
  "sections.engagement": "Engagement"
});

// auth.json
total += applyTranslations('auth.json', {
  "login.hide_password": "Masquer le mot de passe",
  "login.show_password": "Afficher le mot de passe",
  "register.hide_password": "Masquer le mot de passe",
  "register.show_password": "Afficher le mot de passe",
  "register.phone_error": "Entrez un numéro international valide (ex. : +1 555 123 4567)",
  "reset_password.hide_password": "Masquer le mot de passe",
  "reset_password.show_password": "Afficher le mot de passe"
});

// blog.json
total += applyTranslations('blog.json', {
  "min_read_short": "{{count}} min"
});

// chat.json
total += applyTranslations('chat.json', {
  "page_title": "Assistant IA",
  "aria_chat": "Chat IA",
  "header_title": "Assistant IA",
  "header_subtitle": "Propulsé par l'IA",
  "limits_left_today": "{{count}} restants aujourd'hui",
  "new_conversation_aria": "Démarrer une nouvelle conversation",
  "typing_aria": "L'IA est en train d'écrire",
  "error_label": "Erreur",
  "input_placeholder": "Posez-moi une question... (Entrée pour envoyer, Maj+Entrée pour une nouvelle ligne)",
  "send_aria": "Envoyer le message",
  "disclaimer": "Les réponses de l'IA ne sont pas toujours exactes. Vérifiez les informations importantes.",
  "empty_title": "Assistant IA",
  "empty_description": "Posez-moi des questions sur la banque de temps, votre compte ou cette communauté.",
  "try_asking": "Essayez de demander...",
  "feature_unavailable_title": "Assistant IA non disponible",
  "feature_unavailable_description": "La fonctionnalité Assistant IA n'est pas activée pour cette communauté. Contactez l'administrateur de votre banque de temps pour en savoir plus.",
  "error_generic": "Une erreur s'est produite. Veuillez réessayer.",
  "error_rate_limit": "Vous avez atteint votre limite d'utilisation pour le moment. Veuillez réessayer plus tard.",
  "error_connection": "Impossible de se connecter au service IA. Veuillez vérifier votre connexion et réessayer.",
  "toast_rate_limit": "Limite d'utilisation atteinte",
  "toast_connection_error": "Impossible de joindre le service IA",
  "starter_q1": "Quels crédits temps ai-je et comment puis-je les utiliser ?",
  "starter_q2": "Quelles compétences les membres de la communauté proposent-ils actuellement ?",
  "starter_q3": "Comment fonctionne la banque de temps ?",
  "starter_q4": "Quels événements à venir se préparent ?",
  "starter_q5": "Comment créer une annonce pour proposer mes compétences ?"
});

// common.json
total += applyTranslations('common.json', {
  "user_menu.admin": "Administration",
  "user_menu.legacy_admin": "Admin classique",
  "search.suggestions": "Suggestions",
  "search.actions": "Actions",
  "admin_tools.legacy_admin": "Admin classique",
  "sections.support": "Assistance",
  "sections.admin": "Administration",
  "sections.impact": "Impact",
  "accessibility.notifications": "Notifications",
  "accessibility.scroll_to_top": "Retour en haut",
  "accessibility.breadcrumb": "Fil d'Ariane",
  "footer.support": "Assistance",
  "mobile_tab.menu": "Menu",
  "loading": "Chargement...",
  "feature_error.load_failed": "Nous n'avons pas pu charger {{feature}}. Cette section est peut-être temporairement indisponible.",
  "dev_banner.aria_label": "État de développement",
  "dev_banner.status": "État de développement : {{stage}}",
  "dev_banner.read_more": "En savoir plus",
  "skills.browse_title": "Répertoire des compétences communautaires",
  "skills.load_failed": "Impossible de charger les catégories de compétences",
  "skills.load_failed_retry": "Impossible de charger les catégories de compétences. Veuillez réessayer.",
  "skills.offering_label": "Proposent",
  "skills.offering_desc": "membres disposés à partager cette compétence avec les autres",
  "skills.requesting_label": "Recherchent",
  "skills.requesting_desc": "membres souhaitant apprendre ou recevoir de l'aide pour cette compétence",
  "skills.add_your_skills": "Ajoutez vos propres compétences",
  "skills.categories": "Catégories",
  "skills.sub_categories": "Sous-catégories",
  "skills.total_skills": "Compétences répertoriées",
  "skills.search_placeholder": "Rechercher des catégories de compétences...",
  "skills.no_categories": "Aucune catégorie trouvée",
  "skills.no_match": "Aucune catégorie ne correspond à « {{query}} »",
  "skills.no_categories_yet": "Aucune catégorie de compétences disponible pour le moment.",
  "skills.members_with_skills": "membres qualifiés",
  "skills.loading_skills": "Chargement des compétences...",
  "skills.skills_in_category": "Compétences",
  "skills.offering": "proposent",
  "skills.requesting": "recherchent",
  "skills.no_skills_yet": "Aucune compétence répertoriée dans cette catégorie pour le moment.",
  "skills.members_with": "Membres avec « {{skill}} »",
  "skills.offers": "Offres",
  "skills.wants": "Demandes",
  "skills.no_members": "Aucun membre trouvé avec cette compétence.",
  "skills.proficiency.beginner": "Débutant",
  "skills.proficiency.intermediate": "Intermédiaire",
  "skills.proficiency.advanced": "Avancé",
  "skills.proficiency.expert": "Expert"
});

// connections.json
total += applyTranslations('connections.json', {
  "title": "Connexions",
  "subtitle": "Gérez vos connexions communautaires",
  "pending_count": "{{count}} en attente",
  "search_placeholder": "Rechercher par nom ou lieu...",
  "tab_my_connections": "Mes connexions",
  "tab_pending": "En attente",
  "tab_sent": "Envoyées",
  "connected_since": "Connecté depuis le {{date}}",
  "disconnect": "Se déconnecter",
  "wants_to_connect": "Souhaite se connecter avec vous",
  "accept": "Accepter",
  "decline": "Refuser",
  "request_pending": "Demande en attente",
  "cancel_request": "Annuler la demande",
  "load_more": "Charger plus",
  "find_members": "Trouver des membres",
  "empty_no_connections_title": "Aucune connexion pour le moment",
  "empty_no_connections_search": "Aucune connexion ne correspond à votre recherche.",
  "empty_no_connections_description": "Trouvez des membres avec qui vous connecter et développez votre réseau communautaire.",
  "empty_no_pending_title": "Aucune demande en attente",
  "empty_no_pending_search": "Aucune demande en attente ne correspond à votre recherche.",
  "empty_no_pending_description": "Lorsque quelqu'un vous envoie une demande de connexion, elle apparaîtra ici.",
  "empty_no_sent_title": "Aucune demande envoyée",
  "empty_no_sent_search": "Aucune demande envoyée ne correspond à votre recherche.",
  "empty_no_sent_description": "Les demandes de connexion que vous envoyez apparaîtront ici jusqu'à ce qu'elles soient acceptées ou refusées.",
  "toast_load_failed": "Impossible de charger les connexions",
  "toast_accepted": "Connexion acceptée !",
  "toast_accept_failed": "Impossible d'accepter la connexion",
  "toast_declined": "Demande refusée",
  "toast_decline_failed": "Impossible de refuser la demande",
  "toast_disconnected": "Déconnecté",
  "toast_disconnect_failed": "Impossible de se déconnecter",
  "toast_cancelled": "Demande annulée",
  "toast_cancel_failed": "Impossible d'annuler la demande"
});

// dashboard.json
total += applyTranslations('dashboard.json', {
  "sections.endorsements": "Recommandations",
  "quick_actions.notifications": "Notifications",
  "gamification.badges": "Badges"
});

console.log(`\nPart 1 total: ${total}`);
