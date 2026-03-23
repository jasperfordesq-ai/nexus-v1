const fs = require('fs');

// Common translation patterns for admin UI
const translations = {
  de: {
    'Save': 'Speichern', 'Save Changes': 'Änderungen speichern', 'Cancel': 'Abbrechen', 'Delete': 'Löschen',
    'Edit': 'Bearbeiten', 'Create': 'Erstellen', 'Add': 'Hinzufügen', 'Remove': 'Entfernen',
    'Update': 'Aktualisieren', 'Submit': 'Absenden', 'Confirm': 'Bestätigen', 'Close': 'Schließen',
    'Back': 'Zurück', 'Next': 'Weiter', 'Previous': 'Vorherige', 'Search': 'Suchen',
    'Filter': 'Filtern', 'Reset': 'Zurücksetzen', 'Refresh': 'Aktualisieren', 'Export': 'Exportieren',
    'Import': 'Importieren', 'Download': 'Herunterladen', 'Upload': 'Hochladen',
    'Enable': 'Aktivieren', 'Disable': 'Deaktivieren', 'Enabled': 'Aktiviert', 'Disabled': 'Deaktiviert',
    'Active': 'Aktiv', 'Inactive': 'Inaktiv', 'Pending': 'Ausstehend', 'Approved': 'Genehmigt', 'Rejected': 'Abgelehnt',
    'Yes': 'Ja', 'No': 'Nein', 'None': 'Keine', 'All': 'Alle', 'Select': 'Auswählen',
    'Loading...': 'Laden...', 'Loading': 'Laden', 'Saving...': 'Speichern...', 'Deleting...': 'Löschen...',
    'Name': 'Name', 'Title': 'Titel', 'Description': 'Beschreibung', 'Status': 'Status',
    'Type': 'Typ', 'Date': 'Datum', 'Time': 'Zeit', 'Email': 'E-Mail', 'Phone': 'Telefon',
    'Address': 'Adresse', 'Location': 'Standort', 'Category': 'Kategorie', 'Tags': 'Tags',
    'Notes': 'Notizen', 'Comments': 'Kommentare', 'Actions': 'Aktionen', 'Options': 'Optionen',
    'Settings': 'Einstellungen', 'Configuration': 'Konfiguration', 'Permissions': 'Berechtigungen',
    'Users': 'Benutzer', 'Members': 'Mitglieder', 'Groups': 'Gruppen', 'Events': 'Veranstaltungen',
    'Reports': 'Berichte', 'Analytics': 'Analysen', 'Dashboard': 'Dashboard',
    'Are you sure?': 'Sind Sie sicher?', 'No data found': 'Keine Daten gefunden',
    'No results found': 'Keine Ergebnisse gefunden', 'Try again': 'Erneut versuchen',
    'Something went wrong': 'Etwas ist schiefgelaufen', 'View': 'Ansehen', 'View All': 'Alle anzeigen',
    'Changes saved successfully': 'Änderungen erfolgreich gespeichert', 'Publish': 'Veröffentlichen',
    'Failed to save': 'Speichern fehlgeschlagen', 'Failed to load': 'Laden fehlgeschlagen',
    'Failed to delete': 'Löschen fehlgeschlagen', 'Successfully deleted': 'Erfolgreich gelöscht',
    'Successfully created': 'Erfolgreich erstellt', 'Successfully updated': 'Erfolgreich aktualisiert',
    'Checking permissions...': 'Berechtigungen werden überprüft...', 'Required': 'Erforderlich',
    'Optional': 'Optional', 'Preview': 'Vorschau', 'Published': 'Veröffentlicht', 'Draft': 'Entwurf',
    'Total': 'Gesamt', 'Average': 'Durchschnitt', 'Manage': 'Verwalten', 'Details': 'Details',
    'Overview': 'Übersicht', 'Content': 'Inhalt', 'History': 'Verlauf', 'Archive': 'Archiv',
    'Archived': 'Archiviert', 'Restore': 'Wiederherstellen', 'Duplicate': 'Duplizieren',
    'Send': 'Senden', 'Reply': 'Antworten', 'Forward': 'Weiterleiten', 'Message': 'Nachricht',
    'Notification': 'Benachrichtigung', 'Notifications': 'Benachrichtigungen',
    'Warning': 'Warnung', 'Error': 'Fehler', 'Success': 'Erfolg', 'Info': 'Info',
    'Clear': 'Leeren', 'Clear All': 'Alles leeren', 'Select All': 'Alles auswählen',
    'Deselect All': 'Alles abwählen', 'Sort': 'Sortieren', 'Sort by': 'Sortieren nach',
    'Ascending': 'Aufsteigend', 'Descending': 'Absteigend', 'Apply': 'Anwenden',
  },
  es: {
    'Save': 'Guardar', 'Save Changes': 'Guardar cambios', 'Cancel': 'Cancelar', 'Delete': 'Eliminar',
    'Edit': 'Editar', 'Create': 'Crear', 'Add': 'Añadir', 'Remove': 'Eliminar',
    'Update': 'Actualizar', 'Submit': 'Enviar', 'Confirm': 'Confirmar', 'Close': 'Cerrar',
    'Back': 'Volver', 'Next': 'Siguiente', 'Previous': 'Anterior', 'Search': 'Buscar',
    'Filter': 'Filtrar', 'Reset': 'Restablecer', 'Refresh': 'Actualizar', 'Export': 'Exportar',
    'Import': 'Importar', 'Download': 'Descargar', 'Upload': 'Subir',
    'Enable': 'Activar', 'Disable': 'Desactivar', 'Enabled': 'Activado', 'Disabled': 'Desactivado',
    'Active': 'Activo', 'Inactive': 'Inactivo', 'Pending': 'Pendiente', 'Approved': 'Aprobado', 'Rejected': 'Rechazado',
    'Yes': 'Sí', 'No': 'No', 'None': 'Ninguno', 'All': 'Todos', 'Select': 'Seleccionar',
    'Loading...': 'Cargando...', 'Loading': 'Cargando', 'Saving...': 'Guardando...', 'Deleting...': 'Eliminando...',
    'Name': 'Nombre', 'Title': 'Título', 'Description': 'Descripción', 'Status': 'Estado',
    'Type': 'Tipo', 'Date': 'Fecha', 'Time': 'Hora', 'Email': 'Correo electrónico', 'Phone': 'Teléfono',
    'Address': 'Dirección', 'Location': 'Ubicación', 'Category': 'Categoría',
    'Notes': 'Notas', 'Comments': 'Comentarios', 'Actions': 'Acciones', 'Options': 'Opciones',
    'Settings': 'Configuración', 'Permissions': 'Permisos',
    'Users': 'Usuarios', 'Members': 'Miembros', 'Groups': 'Grupos', 'Events': 'Eventos',
    'Reports': 'Informes', 'Analytics': 'Analíticas', 'Dashboard': 'Panel',
    'Are you sure?': '¿Está seguro?', 'No data found': 'No se encontraron datos',
    'Try again': 'Intentar de nuevo', 'Something went wrong': 'Algo salió mal',
    'Failed to save': 'Error al guardar', 'Failed to load': 'Error al cargar',
    'Failed to delete': 'Error al eliminar', 'Successfully deleted': 'Eliminado correctamente',
    'Successfully created': 'Creado correctamente', 'Successfully updated': 'Actualizado correctamente',
    'Checking permissions...': 'Verificando permisos...', 'View': 'Ver', 'View All': 'Ver todo',
    'Required': 'Obligatorio', 'Optional': 'Opcional', 'Preview': 'Vista previa',
    'Published': 'Publicado', 'Draft': 'Borrador', 'Total': 'Total', 'Average': 'Promedio',
    'Manage': 'Gestionar', 'Details': 'Detalles', 'Overview': 'Resumen', 'Content': 'Contenido',
    'History': 'Historial', 'Archive': 'Archivar', 'Archived': 'Archivado',
    'Publish': 'Publicar', 'Send': 'Enviar', 'Reply': 'Responder', 'Message': 'Mensaje',
    'Warning': 'Advertencia', 'Error': 'Error', 'Success': 'Éxito', 'Clear': 'Limpiar',
  },
  fr: {
    'Save': 'Enregistrer', 'Save Changes': 'Enregistrer les modifications', 'Cancel': 'Annuler', 'Delete': 'Supprimer',
    'Edit': 'Modifier', 'Create': 'Créer', 'Add': 'Ajouter', 'Remove': 'Retirer',
    'Update': 'Mettre à jour', 'Submit': 'Soumettre', 'Confirm': 'Confirmer', 'Close': 'Fermer',
    'Back': 'Retour', 'Next': 'Suivant', 'Previous': 'Précédent', 'Search': 'Rechercher',
    'Filter': 'Filtrer', 'Reset': 'Réinitialiser', 'Refresh': 'Actualiser', 'Export': 'Exporter',
    'Import': 'Importer', 'Download': 'Télécharger', 'Upload': 'Téléverser',
    'Enable': 'Activer', 'Disable': 'Désactiver', 'Enabled': 'Activé', 'Disabled': 'Désactivé',
    'Active': 'Actif', 'Inactive': 'Inactif', 'Pending': 'En attente', 'Approved': 'Approuvé', 'Rejected': 'Rejeté',
    'Yes': 'Oui', 'No': 'Non', 'None': 'Aucun', 'All': 'Tous', 'Select': 'Sélectionner',
    'Loading...': 'Chargement...', 'Loading': 'Chargement', 'Saving...': 'Enregistrement...', 'Deleting...': 'Suppression...',
    'Name': 'Nom', 'Title': 'Titre', 'Description': 'Description', 'Status': 'Statut',
    'Type': 'Type', 'Date': 'Date', 'Time': 'Heure', 'Email': 'E-mail', 'Phone': 'Téléphone',
    'Address': 'Adresse', 'Location': 'Emplacement', 'Category': 'Catégorie',
    'Notes': 'Notes', 'Comments': 'Commentaires', 'Actions': 'Actions', 'Options': 'Options',
    'Settings': 'Paramètres', 'Permissions': 'Autorisations',
    'Users': 'Utilisateurs', 'Members': 'Membres', 'Groups': 'Groupes', 'Events': 'Événements',
    'Reports': 'Rapports', 'Analytics': 'Statistiques', 'Dashboard': 'Tableau de bord',
    'Are you sure?': 'Êtes-vous sûr ?', 'No data found': 'Aucune donnée trouvée',
    'Try again': 'Réessayer', 'Something went wrong': 'Une erreur est survenue',
    "Failed to save": "Échec de l'enregistrement", "Failed to load": "Échec du chargement",
    "Failed to delete": "Échec de la suppression", 'Successfully deleted': 'Supprimé avec succès',
    'Successfully created': 'Créé avec succès', 'Successfully updated': 'Mis à jour avec succès',
    'Checking permissions...': 'Vérification des autorisations...', 'View': 'Voir', 'View All': 'Tout voir',
    'Required': 'Obligatoire', 'Optional': 'Facultatif', 'Preview': 'Aperçu',
    'Published': 'Publié', 'Draft': 'Brouillon', 'Total': 'Total', 'Average': 'Moyenne',
    'Manage': 'Gérer', 'Details': 'Détails', 'Overview': 'Aperçu général', 'Content': 'Contenu',
    'History': 'Historique', 'Archive': 'Archiver', 'Archived': 'Archivé',
    'Publish': 'Publier', 'Send': 'Envoyer', 'Reply': 'Répondre', 'Message': 'Message',
    'Warning': 'Avertissement', 'Error': 'Erreur', 'Success': 'Succès', 'Clear': 'Effacer',
  },
  ga: {
    'Save': 'Sábháil', 'Save Changes': 'Sábháil athruithe', 'Cancel': 'Cealaigh', 'Delete': 'Scrios',
    'Edit': 'Cuir in eagar', 'Create': 'Cruthaigh', 'Add': 'Cuir leis', 'Remove': 'Bain',
    'Update': 'Nuashonraigh', 'Submit': 'Seol', 'Confirm': 'Deimhnigh', 'Close': 'Dún',
    'Back': 'Ar ais', 'Next': 'Ar aghaidh', 'Previous': 'Roimhe', 'Search': 'Cuardaigh',
    'Filter': 'Scag', 'Reset': 'Athshocraigh', 'Refresh': 'Athnuaigh', 'Export': 'Easpórtáil',
    'Import': 'Iompórtáil', 'Download': 'Íoslódáil', 'Upload': 'Uaslódáil',
    'Enable': 'Cumasaigh', 'Disable': 'Díchumasaigh', 'Enabled': 'Cumasaithe', 'Disabled': 'Díchumasaithe',
    'Active': 'Gníomhach', 'Inactive': 'Neamhghníomhach', 'Pending': 'Ar feitheamh', 'Approved': 'Ceadaithe', 'Rejected': 'Diúltaithe',
    'Yes': 'Tá', 'No': 'Níl', 'None': 'Aon cheann', 'All': 'Gach', 'Select': 'Roghnaigh',
    'Loading...': 'Ag lódáil...', 'Loading': 'Ag lódáil', 'Saving...': 'Ag sábháil...', 'Deleting...': 'Ag scriosadh...',
    'Name': 'Ainm', 'Title': 'Teideal', 'Description': 'Cur síos', 'Status': 'Stádas',
    'Type': 'Cineál', 'Date': 'Dáta', 'Time': 'Am', 'Email': 'Ríomhphost', 'Phone': 'Fón',
    'Address': 'Seoladh', 'Location': 'Suíomh', 'Category': 'Catagóir',
    'Notes': 'Nótaí', 'Comments': 'Tuairimí', 'Actions': 'Gníomhartha', 'Options': 'Roghanna',
    'Settings': 'Socruithe', 'Permissions': 'Ceadanna',
    'Users': 'Úsáideoirí', 'Members': 'Baill', 'Groups': 'Grúpaí', 'Events': 'Imeachtaí',
    'Reports': 'Tuairiscí', 'Analytics': 'Anailísíocht', 'Dashboard': 'Deais',
    'Are you sure?': 'An bhfuil tú cinnte?', 'No data found': 'Níor aimsíodh aon sonraí',
    'Try again': 'Bain triail eile as', 'Something went wrong': 'Chuaigh rud éigin mícheart',
    'Failed to save': 'Theip ar shábháil', 'Failed to load': 'Theip ar lódáil',
    'Failed to delete': 'Theip ar scriosadh', 'Successfully deleted': 'Scriosta go rathúil',
    'Successfully created': 'Cruthaithe go rathúil', 'Successfully updated': 'Nuashonraithe go rathúil',
    'Checking permissions...': 'Ceadanna á seiceáil...', 'View': 'Féach', 'View All': 'Féach ar fad',
    'Required': 'Riachtanach', 'Optional': 'Roghnach', 'Preview': 'Réamhamharc',
    'Published': 'Foilsithe', 'Draft': 'Dréacht', 'Total': 'Iomlán', 'Manage': 'Bainistigh',
    'Details': 'Sonraí', 'Overview': 'Forbhreathnú', 'Content': 'Ábhar',
    'History': 'Stair', 'Send': 'Seol', 'Reply': 'Freagair', 'Message': 'Teachtaireacht',
    'Warning': 'Rabhadh', 'Error': 'Earráid', 'Success': 'Rath', 'Clear': 'Glan',
  },
  it: {
    'Save': 'Salva', 'Save Changes': 'Salva modifiche', 'Cancel': 'Annulla', 'Delete': 'Elimina',
    'Edit': 'Modifica', 'Create': 'Crea', 'Add': 'Aggiungi', 'Remove': 'Rimuovi',
    'Update': 'Aggiorna', 'Submit': 'Invia', 'Confirm': 'Conferma', 'Close': 'Chiudi',
    'Back': 'Indietro', 'Next': 'Avanti', 'Previous': 'Precedente', 'Search': 'Cerca',
    'Filter': 'Filtra', 'Reset': 'Ripristina', 'Refresh': 'Aggiorna', 'Export': 'Esporta',
    'Import': 'Importa', 'Download': 'Scarica', 'Upload': 'Carica',
    'Enable': 'Attiva', 'Disable': 'Disattiva', 'Enabled': 'Attivato', 'Disabled': 'Disattivato',
    'Active': 'Attivo', 'Inactive': 'Inattivo', 'Pending': 'In attesa', 'Approved': 'Approvato', 'Rejected': 'Rifiutato',
    'Yes': 'Sì', 'None': 'Nessuno', 'All': 'Tutti', 'Select': 'Seleziona',
    'Loading...': 'Caricamento...', 'Loading': 'Caricamento', 'Saving...': 'Salvataggio...', 'Deleting...': 'Eliminazione...',
    'Name': 'Nome', 'Title': 'Titolo', 'Description': 'Descrizione', 'Status': 'Stato',
    'Type': 'Tipo', 'Date': 'Data', 'Time': 'Ora', 'Email': 'E-mail', 'Phone': 'Telefono',
    'Address': 'Indirizzo', 'Location': 'Posizione', 'Category': 'Categoria',
    'Notes': 'Note', 'Comments': 'Commenti', 'Actions': 'Azioni', 'Options': 'Opzioni',
    'Settings': 'Impostazioni', 'Permissions': 'Permessi',
    'Users': 'Utenti', 'Members': 'Membri', 'Groups': 'Gruppi', 'Events': 'Eventi',
    'Reports': 'Rapporti', 'Analytics': 'Analisi', 'Dashboard': 'Pannello',
    'Are you sure?': 'Sei sicuro?', 'No data found': 'Nessun dato trovato',
    'Try again': 'Riprova', 'Something went wrong': 'Qualcosa è andato storto',
    'Failed to save': 'Salvataggio fallito', 'Failed to load': 'Caricamento fallito',
    'Failed to delete': 'Eliminazione fallita', 'Successfully deleted': 'Eliminato con successo',
    'Successfully created': 'Creato con successo', 'Successfully updated': 'Aggiornato con successo',
    'Checking permissions...': 'Verifica autorizzazioni...', 'View': 'Visualizza', 'View All': 'Visualizza tutto',
    'Required': 'Obbligatorio', 'Optional': 'Facoltativo', 'Preview': 'Anteprima',
    'Published': 'Pubblicato', 'Draft': 'Bozza', 'Total': 'Totale', 'Manage': 'Gestisci',
    'Details': 'Dettagli', 'Overview': 'Panoramica', 'Content': 'Contenuto',
    'History': 'Cronologia', 'Send': 'Invia', 'Reply': 'Rispondi', 'Message': 'Messaggio',
    'Warning': 'Avvertenza', 'Error': 'Errore', 'Success': 'Successo', 'Clear': 'Cancella',
  },
  pt: {
    'Save': 'Guardar', 'Save Changes': 'Guardar alterações', 'Cancel': 'Cancelar', 'Delete': 'Eliminar',
    'Edit': 'Editar', 'Create': 'Criar', 'Add': 'Adicionar', 'Remove': 'Remover',
    'Update': 'Atualizar', 'Submit': 'Submeter', 'Confirm': 'Confirmar', 'Close': 'Fechar',
    'Back': 'Voltar', 'Next': 'Seguinte', 'Previous': 'Anterior', 'Search': 'Pesquisar',
    'Filter': 'Filtrar', 'Reset': 'Repor', 'Refresh': 'Atualizar', 'Export': 'Exportar',
    'Import': 'Importar', 'Download': 'Descarregar', 'Upload': 'Carregar',
    'Enable': 'Ativar', 'Disable': 'Desativar', 'Enabled': 'Ativado', 'Disabled': 'Desativado',
    'Active': 'Ativo', 'Inactive': 'Inativo', 'Pending': 'Pendente', 'Approved': 'Aprovado', 'Rejected': 'Rejeitado',
    'Yes': 'Sim', 'None': 'Nenhum', 'All': 'Todos', 'Select': 'Selecionar',
    'Loading...': 'A carregar...', 'Loading': 'A carregar', 'Saving...': 'A guardar...', 'Deleting...': 'A eliminar...',
    'Name': 'Nome', 'Title': 'Título', 'Description': 'Descrição', 'Status': 'Estado',
    'Type': 'Tipo', 'Date': 'Data', 'Time': 'Hora', 'Email': 'E-mail', 'Phone': 'Telefone',
    'Address': 'Morada', 'Location': 'Localização', 'Category': 'Categoria',
    'Notes': 'Notas', 'Comments': 'Comentários', 'Actions': 'Ações', 'Options': 'Opções',
    'Settings': 'Definições', 'Permissions': 'Permissões',
    'Users': 'Utilizadores', 'Members': 'Membros', 'Groups': 'Grupos', 'Events': 'Eventos',
    'Reports': 'Relatórios', 'Analytics': 'Análises', 'Dashboard': 'Painel',
    'Are you sure?': 'Tem a certeza?', 'No data found': 'Sem dados encontrados',
    'Try again': 'Tentar novamente', 'Something went wrong': 'Algo correu mal',
    'Failed to save': 'Falha ao guardar', 'Failed to load': 'Falha ao carregar',
    'Failed to delete': 'Falha ao eliminar', 'Successfully deleted': 'Eliminado com sucesso',
    'Successfully created': 'Criado com sucesso', 'Successfully updated': 'Atualizado com sucesso',
    'Checking permissions...': 'A verificar permissões...', 'View': 'Ver', 'View All': 'Ver tudo',
    'Required': 'Obrigatório', 'Optional': 'Opcional', 'Preview': 'Pré-visualização',
    'Published': 'Publicado', 'Draft': 'Rascunho', 'Total': 'Total', 'Manage': 'Gerir',
    'Details': 'Detalhes', 'Overview': 'Visão geral', 'Content': 'Conteúdo',
    'History': 'Histórico', 'Send': 'Enviar', 'Reply': 'Responder', 'Message': 'Mensagem',
    'Warning': 'Aviso', 'Error': 'Erro', 'Success': 'Sucesso', 'Clear': 'Limpar',
  }
};

function applyTranslations(enObj, tgtObj, dict) {
  let count = 0;
  for (const [k, v] of Object.entries(enObj)) {
    if (typeof v === 'object' && v !== null) {
      if (!tgtObj[k]) tgtObj[k] = {};
      if (typeof tgtObj[k] === 'object') count += applyTranslations(v, tgtObj[k], dict);
    } else if (typeof v === 'string' && tgtObj[k] === v && v.length > 2) {
      // Exact match first
      if (dict[v]) { tgtObj[k] = dict[v]; count++; continue; }
      // Try prefix matching for patterns like "Failed to load users"
      const sortedKeys = Object.keys(dict).sort((a, b) => b.length - a.length);
      for (const eng of sortedKeys) {
        if (v.startsWith(eng + ' ') || v.startsWith(eng + '.') || v.startsWith(eng + ':')) {
          tgtObj[k] = dict[eng] + v.substring(eng.length);
          count++;
          break;
        }
      }
    }
  }
  return count;
}

const locales = Object.keys(translations);
const files = fs.readdirSync('en/').filter(f => f.endsWith('.json'));

for (const loc of locales) {
  let total = 0;
  for (const f of files) {
    const en = JSON.parse(fs.readFileSync('en/' + f, 'utf8'));
    const tgtPath = loc + '/' + f;
    const tgt = JSON.parse(fs.readFileSync(tgtPath, 'utf8'));
    const count = applyTranslations(en, tgt, translations[loc]);
    if (count > 0) {
      fs.writeFileSync(tgtPath, JSON.stringify(tgt, null, 2) + '\n');
      total += count;
    }
  }
  console.log(loc + ': translated ' + total + ' strings via pattern matching');
}
