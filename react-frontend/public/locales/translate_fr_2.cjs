// French translation script - Part 2 (events through groups)
const fs = require('fs');
const path = require('path');
const enDir = path.join(__dirname, 'en');
const frDir = path.join(__dirname, 'fr');

function setNested(obj, kp, v) { const p = kp.split('.'); let c = obj; for (let i = 0; i < p.length-1; i++) { if (!c[p[i]]) c[p[i]] = {}; c = c[p[i]]; } c[p[p.length-1]] = v; }
function getNested(obj, kp) { const p = kp.split('.'); let c = obj; for (const k of p) { if (c == null) return undefined; c = c[k]; } return c; }
function apply(fn, t) {
  const ff = path.join(frDir, fn), ef = path.join(enDir, fn);
  if (!fs.existsSync(ff)||!fs.existsSync(ef)) return 0;
  const fd = JSON.parse(fs.readFileSync(ff,'utf8')), ed = JSON.parse(fs.readFileSync(ef,'utf8'));
  let n = 0;
  for (const [k,v] of Object.entries(t)) { const ev = getNested(ed,k), cv = getNested(fd,k); if (cv===ev && v!==ev) { setNested(fd,k,v); n++; } }
  if (n>0) fs.writeFileSync(ff, JSON.stringify(fd,null,2)+'\n','utf8');
  console.log(`${fn}: ${n}`); return n;
}
let total = 0;

total += apply('events.json', {
  "loading_aria": "Chargement des événements",
  "detail.date_label": "Date",
  "form.error_not_found": "Événement introuvable",
  "form.error_load_failed": "Impossible de charger l'événement. Veuillez réessayer.",
  "form.recurring_toggle": "Rendre cet événement récurrent",
  "form.recurring_desc": "Créer automatiquement plusieurs occurrences",
  "form.recurring_toggle_aria": "Activer/désactiver l'événement récurrent",
  "form.recurrence_frequency": "Fréquence",
  "form.recurrence_frequency_aria": "Fréquence de récurrence",
  "form.freq_daily": "Quotidien",
  "form.freq_weekly": "Hebdomadaire",
  "form.freq_biweekly": "Bimensuel (toutes les 2 semaines)",
  "form.freq_monthly": "Mensuel",
  "form.recurrence_days": "Répéter le",
  "form.recurrence_end_type": "Se termine",
  "form.recurrence_end_type_aria": "Comment la série se termine",
  "form.end_after_count": "Après X occurrences",
  "form.end_on_date": "À une date spécifique",
  "form.recurrence_count": "Nombre d'occurrences",
  "form.recurrence_end_date": "Date de fin de la série",
  "reminder_settings": "Paramètres de rappel d'événement",
  "reminder.title": "Rappels d'événements",
  "reminder.coming_soon_desc": "Les préférences de rappel globales arrivent bientôt. En attendant, vous pouvez définir des rappels sur les événements individuels depuis leur page de détail."
});

total += apply('exchanges.json', {
  "detail.service": "Service",
  "detail.prep_time": "Temps de préparation",
  "detail.ratings": "Évaluations",
  "detail.rate_exchange": "Évaluer cet échange"
});

total += apply('explore.json', {
  "page_title": "Découvrir",
  "heading": "Découvrir",
  "subtitle": "Trouvez des compétences, événements, groupes et plus dans votre communauté",
  "search_placeholder": "Découvrir des compétences, événements, groupes...",
  "stats.members": "Membres",
  "stats.exchanges_this_month": "Échanges ce mois-ci",
  "stats.hours_exchanged": "Heures échangées",
  "stats.active_listings": "Annonces actives",
  "trending_posts.title": "Publications tendance",
  "trending_posts.subtitle": "Les publications les plus engageantes cette semaine",
  "popular_listings.title": "Annonces populaires",
  "popular_listings.subtitle": "Les offres et demandes les plus consultées",
  "upcoming_events.title": "Événements à venir",
  "upcoming_events.subtitle": "Rejoignez votre communauté",
  "upcoming_events.online": "En ligne",
  "upcoming_events.attending": "{{count}} participants",
  "active_groups.title": "Groupes actifs",
  "active_groups.subtitle": "Connectez-vous avec des membres partageant les mêmes intérêts",
  "active_groups.members": "{{count}} membres",
  "active_groups.view_group": "Voir le groupe",
  "top_contributors.title": "Meilleurs contributeurs",
  "top_contributors.subtitle": "Leaders de la communauté par expérience",
  "top_contributors.level": "Niv.{{level}}",
  "trending_hashtags.title": "Hashtags tendance",
  "trending_hashtags.subtitle": "Sujets populaires dans votre communauté",
  "recommended.title": "Recommandé pour vous",
  "recommended.subtitle": "Basé sur vos intérêts et votre activité",
  "recommended.by_author": "par {{name}}",
  "new_members.title": "Bienvenue à nos nouveaux membres",
  "new_members.subtitle": "Dites bonjour aux derniers arrivants",
  "new_members.connect": "Se connecter",
  "featured_challenges.title": "Défis en vedette",
  "featured_challenges.subtitle": "Partagez vos idées et faites la différence",
  "featured_challenges.ideas_count": "{{count}} idées",
  "featured_challenges.ends": "Se termine le {{date}}",
  "time_ago.just_now": "À l'instant",
  "time_ago.hours_ago": "il y a {{count}}h",
  "time_ago.days_ago": "il y a {{count}}j"
});

total += apply('federation.json', {
  "onboarding.step_communication": "Communication",
  "onboarding.summary_communication": "Communication",
  "onboarding.summary_transactions": "Transactions",
  "events.breadcrumb_federation": "Fédération",
  "partners.breadcrumb_federation": "Fédération",
  "partner_detail.breadcrumb_federation": "Fédération",
  "member_profile.breadcrumb_federation": "Fédération",
  "settings.breadcrumb_federation": "Fédération",
  "settings.federation_toggled_title": "Fédération {{action}}",
  "settings.communication": "Communication",
  "connections.page_title": "Connexions",
  "connections.breadcrumb_federation": "Fédération",
  "connections.breadcrumb_connections": "Connexions",
  "connections.title": "Connexions de fédération",
  "connections.subtitle": "Gérez vos connexions inter-communautaires",
  "connections.tab_connected": "Connectés",
  "connections.tab_received": "Reçues",
  "connections.tab_sent": "Envoyées",
  "connections.loading": "Chargement des connexions...",
  "connections.empty_connected": "Aucune connexion pour le moment",
  "connections.empty_received": "Aucune demande en attente",
  "connections.empty_sent": "Aucune demande envoyée",
  "connections.empty_connected_desc": "Connectez-vous avec des membres de communautés partenaires pour développer votre réseau.",
  "connections.empty_received_desc": "Lorsque quelqu'un vous envoie une demande de connexion, elle apparaîtra ici.",
  "connections.empty_sent_desc": "Les demandes de connexion que vous envoyez apparaîtront ici.",
  "connections.browse_members": "Parcourir les membres de la fédération",
  "connections.unknown_member": "Membre de la fédération",
  "connections.remove": "Supprimer la connexion",
  "connections.accept": "Accepter",
  "connections.decline": "Refuser",
  "connections.pending": "En attente",
  "connections.accepted_success": "Connexion acceptée !",
  "connections.rejected_success": "Demande de connexion refusée",
  "connections.removed_success": "Connexion supprimée",
  "connections.action_failed": "Action échouée",
  "members.aria_search": "Rechercher des membres fédérés",
  "members.aria_filter_community": "Filtrer par communauté partenaire",
  "members.aria_filter_skills": "Filtrer par compétences",
  "messages.aria_back_to_list": "Retour à la liste des messages",
  "messages.aria_read": "Lu",
  "messages.aria_delivered": "Livré",
  "messages.aria_reply": "Répondre au message",
  "messages.aria_send_reply": "Envoyer la réponse",
  "partner_detail.federation_partner_badge": "Partenaire de fédération"
});

total += apply('gamification.json', {
  "achievements.tab_collections": "Collections",
  "achievements.daily_reward.loading": "Chargement de la récompense quotidienne",
  "achievements.loading": "Chargement des réalisations",
  "goals.loading": "Chargement des objectifs",
  "goals.detail.social": "Social",
  "leaderboard.loading": "Chargement du classement",
  "leaderboard.season.participants": "{{count}} participants",
  "leaderboard.season.active": "Actif",
  "leaderboard.season.loading": "Chargement de la saison"
});

total += apply('goals.json', {
  "checkin.title": "Suivi",
  "checkin.new_checkin": "Nouveau suivi",
  "checkin.history": "Historique",
  "checkin.no_checkins": "Aucun suivi pour le moment. Enregistrez votre premier !",
  "checkin.progress_label": "Progression",
  "checkin.mood_label": "Comment vous sentez-vous ?",
  "checkin.note_label": "Note (facultatif)",
  "checkin.note_placeholder": "Comment ça se passe ? Des réussites ou des difficultés ?",
  "checkin.cancel": "Annuler",
  "checkin.submit": "Enregistrer le suivi",
  "checkin.check_in_button": "Suivi",
  "mood.great": "Super",
  "mood.good": "Bien",
  "mood.okay": "Correct",
  "mood.struggling": "Difficile",
  "mood.motivated": "Motivé",
  "mood.grateful": "Reconnaissant",
  "reminder.active": "Rappel actif",
  "reminder.set": "Définir un rappel",
  "reminder.currently": "Actuellement : {{frequency}}",
  "reminder.remove": "Supprimer le rappel",
  "reminder.aria_active": "Rappel actif",
  "reminder.aria_set": "Définir un rappel",
  "reminder.reminder_set_success": "Rappel défini : {{frequency}}",
  "frequency.daily": "Quotidien",
  "frequency.weekly": "Hebdomadaire",
  "frequency.biweekly": "Toutes les 2 semaines",
  "frequency.monthly": "Mensuel",
  "template.title": "Commencer à partir d'un modèle",
  "template.loading_error": "Impossible de charger les modèles. Veuillez réessayer.",
  "template.try_again": "Réessayer",
  "template.no_templates": "Aucun modèle disponible pour le moment.",
  "template.all_categories": "Tous",
  "template.no_in_category": "Aucun modèle dans cette catégorie.",
  "template.target_label": "Objectif : {{value}}",
  "template.duration_days": "{{days}}j",
  "template.use_template_aria": "Utiliser le modèle : {{title}}",
  "template.cancel": "Annuler",
  "history.load_failed": "Impossible de charger l'historique.",
  "history.retry": "Réessayer",
  "history.no_activity": "Aucune activité enregistrée pour le moment.",
  "history.progress_aria": "Progression : {{percent}} %"
});

total += apply('groups.json', {
  "detail.tab_discussion": "Discussion",
  "detail.discussions_heading": "Discussions",
  "detail.tab_feed": "Fil d'actualité",
  "detail.tab_files": "Fichiers",
  "detail.tab_announcements": "Annonces",
  "detail.tab_channels": "Canaux",
  "detail.tab_tasks": "Tâches",
  "detail.join_to_access_title": "Réservé aux membres",
  "detail.join_to_access_desc": "Rejoignez ce groupe pour accéder à cette fonctionnalité.",
  "detail.leave_group_title": "Quitter le groupe",
  "detail.leave_group_confirm": "Êtes-vous sûr de vouloir quitter {{name}} ? Vous perdrez l'accès aux discussions et fichiers du groupe.",
  "detail.report_title": "Signaler la publication",
  "detail.report_description": "Aidez-nous à comprendre pourquoi vous signalez cette publication.",
  "detail.report_reason_label": "Raison",
  "detail.report_reason_placeholder": "Décrivez pourquoi cette publication est inappropriée...",
  "detail.report_submit": "Signaler",
  "detail.join_to_see_feed_title": "Rejoignez pour voir le fil",
  "detail.join_to_see_feed_desc": "Rejoignez ce groupe pour voir les publications et participer aux conversations.",
  "detail.feed_whats_on_your_mind": "Quoi de neuf ?",
  "detail.feed_empty_title": "Aucune publication pour le moment",
  "detail.feed_empty_desc": "Soyez le premier à partager quelque chose avec ce groupe !",
  "detail.feed_create_post": "Créer une publication",
  "detail.feed_load_more": "Charger plus",
  "detail.member_actions_aria": "Actions sur le membre",
  "announcements.title_label": "Titre",
  "announcements.title_placeholder": "Titre de l'annonce",
  "announcements.content_label": "Contenu",
  "announcements.content_placeholder": "Rédigez votre annonce...",
  "announcements.actions_aria": "Actions",
  "announcements.dropdown_aria": "Actions de l'annonce",
  "announcements.load_failed": "Impossible de charger les annonces",
  "announcements.created": "Annonce créée",
  "announcements.create_failed": "Impossible de créer l'annonce",
  "announcements.unpinned": "Désépinglée",
  "announcements.pinned_success": "Épinglée",
  "announcements.update_failed": "Impossible de mettre à jour l'annonce",
  "announcements.deleted": "Annonce supprimée",
  "announcements.delete_failed": "Impossible de supprimer l'annonce",
  "files.coming_soon": "Bientôt disponible",
  "files.coming_soon_description": "Le partage de fichiers en groupe est en cours de développement. Bientôt, vous pourrez envoyer, télécharger et gérer des fichiers au sein de votre groupe.",
  "toast.feed_load_failed": "Impossible de charger le fil",
  "toast.post_hidden": "Publication masquée",
  "toast.hide_failed": "Impossible de masquer la publication",
  "toast.user_muted": "Utilisateur mis en sourdine",
  "toast.mute_failed": "Impossible de mettre en sourdine",
  "toast.provide_reason": "Veuillez fournir une raison",
  "toast.reported": "Publication signalée",
  "toast.report_failed": "Impossible de signaler la publication",
  "toast.vote_failed": "Impossible de voter",
  "loading_aria": "Chargement des groupes"
});

total += apply('group_exchanges.json', {
  "role_participant": "Participant",
  "create.no_participants_yet": "Aucun participant ajouté pour le moment"
});

total += apply('ideation.json', {
  "tags.label": "Étiquettes",
  "media.type_video": "Vidéo",
  "chatrooms.general": "Général",
  "categories.title": "Catégories"
});

console.log(`\nPart 2 total: ${total}`);
