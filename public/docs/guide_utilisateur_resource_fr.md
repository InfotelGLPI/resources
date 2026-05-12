# Guide utilisateur — Plugin GLPI Resources

## 1. Présentation

Le plugin **Resources** intègre la gestion des ressources humaines dans GLPI. Il couvre l'ensemble du cycle de vie d'un collaborateur :

- Déclaration à l'arrivée (wizard de création)
- Suivi administratif (informations employeur, habilitations, besoins IT)
- Gestion des périodes hors-contrat et des congés imposés
- Suivi budgétaire (secteur public)
- Gestion des emplois et postes (secteur public)
- Déclaration et suivi du départ
- Transfert inter-entités
- Annuaire consolidé utilisateur/ressource
- Import de ressources en masse

---

## 2. Gestion des droits

Chemin : `Administration > Profils > onglet Ressources humaines`

### 2.1 Droits disponibles

| Droit | Description |
|-------|-------------|
| `plugin_resources` | Accès principal : voir, créer, modifier, supprimer des ressources |
| `plugin_resources_task` | Gestion des tâches liées aux ressources |
| `plugin_resources_checklist` | Gestion des checklists arrivée/départ/transfert |
| `plugin_resources_employee` | Accès aux informations employeur (société, contrat, etc.) |
| `plugin_resources_employee_core_form` | Accès au formulaire employeur étendu |
| `plugin_resources_role` | Gestion des rôles de ressource |
| `plugin_resources_resting` | Gestion des périodes hors-contrat |
| `plugin_resources_holiday` | Gestion des congés imposés |
| `plugin_resources_habilitation` | Gestion des habilitations |
| `plugin_resources_employment` | Gestion des emplois (secteur public) |
| `plugin_resources_budget` | Gestion des budgets liés aux ressources |
| `plugin_resources_dropdown_public` | Gestion des intitulés secteur public (grade, filière, etc.) |
| `plugin_resources_import` | Import de ressources en masse |
| `plugin_resources_open_ticket` | Créer un ticket depuis une ressource |
| `plugin_resources_all` | Voir toutes les ressources (pas uniquement celles dont on est responsable) |
| `plugin_resources_leavinginformation` | Accès aux informations de départ |

> **Note :** Sans le droit `plugin_resources_all`, un utilisateur ne voit que les ressources dont il est le responsable désigné.

---

## 3. Configuration générale

Chemin : `Ressources humaines > Configuration`

La configuration comporte quatre onglets.

### 3.1 Onglet Wizard

Paramètre le comportement du wizard de création de ressource :

- Groupe de responsables par défaut
- Catégorie de ticket par défaut (pour ouverture automatique)
- Lien avec un gabarit Metademande (si le plugin Metademands est actif)

### 3.2 Onglet Arrivée / Départ

- Activation des notifications automatiques à l'arrivée et au départ
- Délai avant notification de départ
- Configuration du responsable commercial (sales manager)
- Activation du formulaire de démission (résignation)

### 3.3 Onglet Autre

- Champs à masquer dans l'annuaire (nom, prénom, matricule, téléphone, etc.)
- Quota de travail par défaut
- Options d'affichage

### 3.4 Onglet Lien Metademande (optionnel)

Visible uniquement si le plugin **Metademands** est activé. Permet d'associer un formulaire Metademande au wizard de création de ressource.

---

## 4. Types de contrat

Chemin : `Configuration > Intitulés > Type de contrat`

Le type de contrat contrôle les étapes du wizard de création. Chaque type peut activer ou désactiver indépendamment :

| Option | Description |
|--------|-------------|
| Code | Identifiant court du type de contrat |
| Informations employeur | Afficher l'onglet employeur dans le wizard |
| Besoins informatiques | Afficher l'étape de saisie des besoins matériels/logiciels |
| Photo | Afficher l'étape d'ajout de photo |
| Habilitations | Afficher l'étape de saisie des habilitations |
| Informations de recrutement | Source de recrutement, date d'entrée en poste |
| Deuxième liste d'employeurs | Activer un second champ de sélection d'employeur |
| Deuxième matricule | Activer un second champ matricule |
| Formulaire de démission | Activer le formulaire de démission lors du départ |
| Formulaire de documents | Activer l'onglet documents dans le wizard |

---

## 5. Gabarits de ressource

Chemin : `Ressources humaines > Gabarits`

Les gabarits permettent de pré-remplir les champs lors de la création d'une ressource. Un gabarit peut contenir :

- Informations générales (type de contrat, localisation, responsable, équipe)
- Informations employeur
- Tâches à créer automatiquement
- Checklists à déclencher

---

## 6. Règles

### 6.1 Règles sur le type de contrat

Chemin : `Administration > Règles > Règles sur le type de contrat`

Permettent, selon des critères (type de contrat, entité, etc.) de :
- **Imposer des valeurs** sur certains champs (champs en lecture seule)
- **Masquer des champs** selon le contexte
- **Déclencher une checklist** automatiquement

### 6.2 Règles de checklist

Chemin : `Administration > Règles > Règles de checklist`

Attribution automatique d'une checklist selon le type de contrat, l'entité ou d'autres critères.

---

## 7. Checklists

Chemin : `Ressources humaines > Checklists`

Une checklist est une liste d'actions à réaliser (non planifiées) associée à un événement ressource.

### Types de checklists

| Type | Déclenchement |
|------|---------------|
| **Arrivée** | Lors de la création d'une ressource |
| **Départ** | Lors de la déclaration de départ |
| **Transfert** | Lors d'un transfert inter-entités |

### Configuration d'une checklist

Chemin : `Ressources humaines > Configuration des checklists`

- Nom de la checklist
- Type (arrivée, départ, transfert)
- Actions à réaliser (sous-éléments)
- Assignation à un groupe ou technicien
- Lien avec une tâche GLPI

> Les checklists sont attribuées automatiquement via les règles (section 6.2), ou manuellement depuis la ressource.

---

## 8. Création d'une ressource (wizard)

Chemin : `Ressources humaines > Déclarer une ressource`

Le wizard guide la saisie en plusieurs étapes selon la configuration du type de contrat.

### Étape 1 — Informations générales

Champs disponibles :

| Champ | Description |
|-------|-------------|
| Prénom | Prénom du collaborateur |
| Nom | Nom du collaborateur |
| Type de contrat | Détermine les étapes suivantes du wizard |
| Localisation | Lieu de travail |
| Responsable | Utilisateur GLPI responsable de la ressource |
| Responsable commercial | (optionnel) Chef de projet / account manager |
| Service | Service d'appartenance |
| Département | Département |
| Équipe | Équipe de rattachement |
| Fonction | Fonction exercée |
| Rôle | Rôle dans l'organisation |
| Date d'arrivée | Date de début de contrat |
| Date de départ | Date de fin de contrat (optionnel à la création) |
| Matricule | Identifiant RH (optionnel) |
| Second matricule | Selon configuration du type de contrat |
| Quota | Taux d'activité (ex : 100 %) |
| Description / Autres | Champ libre |

### Étape 2 — Informations employeur (si activé)

| Champ | Description |
|-------|-------------|
| Employeur | Société employeur |
| Second employeur | Selon configuration |
| Situation contractuelle | CDI, CDD, intérim, etc. |
| Nature du contrat | Précision sur la nature |
| Grade | (secteur public) |
| Filière | (secteur public) |
| Spécialité | Spécialité professionnelle |
| Sensibilisé sécurité | Oui/Non |
| Charte sécurité lue | Oui/Non |

### Étape 3 — Besoins informatiques (si activé)

Saisie des équipements nécessaires : ordinateur, téléphone, moniteur, périphériques, logiciels, etc.

### Étape 4 — Photo (si activé)

Téléchargement d'une photo de la ressource.

### Étape 5 — Habilitations (si activé)

Attribution des habilitations requises pour le poste.

### Étape 6 — Informations de recrutement (si activé)

Source de recrutement, date d'entrée en poste, candidature.

### Étape 7 — Documents (si activé)

Ajout de documents au dossier de la ressource.

---

## 9. Onglets d'une ressource

Une fois créée, la ressource dispose de plusieurs onglets :

| Onglet | Contenu |
|--------|---------|
| **Ressource** | Informations générales |
| **Employeur** | Données contractuelles et RH (si droit `plugin_resources_employee`) |
| **Habilitations** | Habilitations attribuées |
| **Tâches** | Tâches planifiées liées à la ressource |
| **Checklists** | Actions d'arrivée/départ à effectuer |
| **Éléments associés** | Équipements liés (PC, téléphone, etc.) et utilisateur GLPI associé |
| **Emploi** | Emploi/poste (secteur public, droit `plugin_resources_employment`) |
| **Budget** | Lignes budgétaires associées (droit `plugin_resources_budget`) |
| **Périodes hors-contrat** | Congés maternité, arrêts longue durée, etc. |
| **Congés imposés** | Jours fériés ou congés imposés |
| **Départ** | Déclaration et informations de départ |
| **Tickets/Changements** | Tickets et changements liés |
| **Documents** | Documents attachés |
| **Historique** | Journal des modifications |
| **Notes** | Notes internes |

---

## 10. Liaison avec un utilisateur GLPI

Un utilisateur GLPI peut être associé à une ressource de deux façons.

### Depuis la ressource

1. Ouvrir la ressource
2. Aller dans l'onglet **Éléments associés**
3. Sélectionner l'utilisateur dans la liste déroulante

### Depuis l'utilisateur GLPI

1. Ouvrir la fiche utilisateur
2. Aller dans l'onglet **Ressources humaines**
3. Associer à une ressource existante ou créer une nouvelle ressource

---

## 11. Tâches

Chemin : `Ressources humaines > Tâches`

Les tâches sont des actions planifiées associées à une ressource.

### Champs d'une tâche

| Champ | Description |
|-------|-------------|
| Nom | Libellé de la tâche |
| Type | Type de tâche (intitulé configurable) |
| Ressource | Ressource parente |
| Responsable | Technicien assigné |
| Groupe | Groupe assigné |
| Date de début / fin | Planification |
| Statut | En cours / Terminée |
| Commentaire | Description |

> **Alerte automatique :** L'action automatique `ResourcesTask` envoie une notification pour les tâches non terminées dont la date de fin est dépassée.

---

## 12. Périodes hors-contrat et congés imposés

### Périodes hors-contrat

Chemin : Onglet **Périodes hors-contrat** de la ressource

Gèrent les interruptions de contrat (congé maternité, arrêt maladie longue durée, etc.) avec :
- Date de début / fin
- Type de période
- Localisation de détachement
- Commentaire

Notifications déclenchées : `newresting`, `updateresting`, `deleteresting`.

### Congés imposés

Chemin : Onglet **Congés imposés** de la ressource

Gèrent les jours de congé imposés à la ressource, distincts des congés habituels.

Notifications déclenchées : `newholiday`, `updateholiday`, `deleteholiday`.

---

## 13. Habilitations

Chemin : `Configuration > Intitulés > Habilitations`

Les habilitations sont organisées en arborescence (hiérarchie). Elles représentent des accréditations ou autorisations d'accès attribuées à une ressource.

### Attribution

Depuis l'onglet **Habilitations** de la ressource :
- Sélectionner l'habilitation
- Définir la date d'obtention et d'expiration
- Ajouter un commentaire

---

## 14. Emploi (secteur public)

Chemin : Onglet **Emploi** de la ressource (droit `plugin_resources_employment`)

Gère les données de poste spécifiques au secteur public :

| Champ | Description |
|-------|-------------|
| État de l'emploi | Actif, vacant, etc. |
| Catégorie de profession | Catégorie A, B, C... |
| Filière professionnelle | Filière d'appartenance |
| Ligne de métier | Sous-filière |
| Grade | Grade fonctionnaire |
| Échelon | Échelon dans le grade |

Intitulés configurables : `Configuration > Intitulés > [Emploi]`

---

## 15. Budget (secteur public)

Chemin : `Ressources humaines > Budgets` ou onglet **Budget** d'une ressource

Permet d'associer des lignes budgétaires à une ressource :

| Champ | Description |
|-------|-------------|
| Type de budget | Nature de la dépense |
| Volume budgétaire | Enveloppe allouée |
| Commentaire | Précision |

---

## 16. Déclaration de départ

### Étapes

1. Ouvrir la fiche ressource
2. Aller dans l'onglet **Départ**
3. Renseigner :
   - Date de départ
   - Motif de départ
   - Informant (qui déclare le départ)
   - Commentaire
4. Si le formulaire de démission est activé pour ce type de contrat : renseigner les informations supplémentaires (motif de résiliation, préavis, etc.)

### Effets du départ

- Déclenchement de la **checklist de départ**
- Envoi de la notification `LeavingResource` aux destinataires configurés
- L'action automatique `Resources` vérifie les ressources dont la date de départ est dépassée et envoie `AlertLeavingResources`

---

## 17. Transfert inter-entités

Chemin : `Ressources humaines > [ressource] > Actions > Transférer`

Permet de déplacer une ressource vers une autre entité GLPI.

Notifications déclenchées (`transfer`) :
- Groupe de l'entité source
- Groupe de l'entité cible
- Responsable du groupe source
- Responsable du groupe cible

---

## 18. Notifications

Chemin : `Configuration > Notifications`

### Liste complète des événements

| Événement | Déclencheur |
|-----------|-------------|
| `new` | Création d'une ressource |
| `update` | Modification d'une ressource |
| `delete` | Suppression d'une ressource |
| `newtask` | Ajout d'une tâche |
| `updatetask` | Modification d'une tâche |
| `deletetask` | Suppression d'une tâche |
| `LeavingResource` | Déclaration de départ |
| `AlertLeavingResources` | Ressources dont la date de départ est dépassée |
| `AlertArrivalChecklists` | Actions à effectuer sur les nouvelles ressources |
| `AlertLeavingChecklists` | Actions à effectuer sur les ressources partantes |
| `AlertExpiredTasks` | Tâches non terminées après leur date de fin |
| `AlertCommercialManager` | Liste des ressources par responsable commercial |
| `AlertLeavingRessourceManager` | Alerte au responsable pour compléter le formulaire de départ |
| `report` | Rapport de création de la ressource |
| `newresting` | Ajout d'une période hors-contrat |
| `updateresting` | Modification d'une période hors-contrat |
| `deleteresting` | Suppression d'une période hors-contrat |
| `newholiday` | Ajout d'un congé imposé |
| `updateholiday` | Modification d'un congé imposé |
| `deleteholiday` | Suppression d'un congé imposé |
| `transfer` | Transfert inter-entités |
| `other` | Notification libre |

### Destinataires disponibles

| Destinataire | Description |
|-------------|-------------|
| Responsable ressource | `users_id` de la ressource |
| Responsable commercial | `users_id_sales` |
| Demandeur | Auteur de la création/modification |
| Informant du départ | Utilisateur ayant déclaré le départ |
| Utilisateur lié | Compte GLPI associé à la ressource |
| Technicien de la tâche | Responsable de la tâche (événements tâche) |
| Groupe de la tâche | Groupe assigné à la tâche (événements tâche) |
| Groupe entité source | (transfert) |
| Groupe entité cible | (transfert) |
| Responsable groupe source | (transfert) |
| Responsable groupe cible | (transfert) |

### Variables disponibles dans les modèles

```
##resource_gender##           Civilité
##resource_name##             Nom
##resource_firstname##        Prénom
##resource_phone##            Téléphone
##resource_cellphone##        Téléphone mobile
##resource_locations_id##     Localisation
##resource_users_id##         Responsable
##resource_users_id_sales##   Responsable commercial
##resource_plugin_resources_departments_id##   Département
##resource_plugin_resources_services_id##      Service
##resource_plugin_resources_functions_id##     Fonction
##resource_plugin_resources_teams_id##         Équipe
##resource_date_begin##       Date d'arrivée
##resource_date_end##         Date de départ
##resource_comment##          Description
##resource_quota##            Quota
##resource_matricule##        Matricule
##resource_matricule_second## Second matricule
##resource_plugin_resources_ranks_id##             Grade
##resource_plugin_resources_resourcesituations_id## Situation
##resource_plugin_resources_contractnatures_id##    Nature du contrat
##resource_plugin_resources_resourcespecialities_id## Spécialité
##resource_plugin_resources_roles_id##             Rôle
##resource_sensitize_security##  Sensibilisé sécurité
##resource_read_chart##          Charte sécurité lue
```

---

## 19. Actions automatiques

Chemin : `Configuration > Actions automatiques`

| Action | Description |
|--------|-------------|
| `Resources` | Vérifie les ressources dont la date de départ est dépassée → envoie `AlertLeavingResources` |
| `ResourcesChecklist` | Vérifie les checklists d'arrivée/départ non traitées → envoie `AlertArrivalChecklists` et `AlertLeavingChecklists` |
| `ResourcesTask` | Vérifie les tâches non terminées dont la date de fin est dépassée → envoie `AlertExpiredTasks` |

Chaque action automatique est configurable (activation, mode, fréquence) dans la page dédiée.

---

## 20. Annuaire

Chemin : `Ressources humaines > Annuaire`

Vue consolidée des utilisateurs GLPI avec leurs informations de ressource associées.

- Recherche multicritère (nom, prénom, service, localisation, etc.)
- Affichage configurable (colonnes masquables selon la configuration)
- Export possible
- Champs masquables configurés dans `Configuration > Autre` : nom, prénom, matricule, téléphone, mobile, localisation, quota

---

## 21. Import de ressources

Chemin : `Ressources humaines > Import`

Permet l'import en masse de ressources depuis un fichier CSV ou via une source AD/LDAP.

### Import CSV

1. Préparer un fichier CSV avec les colonnes correspondant aux champs ressource
2. Configurer le mapping des colonnes (`Configuration des colonnes d'import`)
3. Lancer l'import et vérifier le rapport

### Import AD/LDAP

Chemin : `Ressources humaines > Configuration LDAP`

- Configuration de la connexion AD/LDAP
- Mapping des attributs LDAP vers les champs ressource
- Import ponctuel ou synchronisation

---

## 22. Fiche ressource (carte et badge)

### Carte ressource

Chemin : `[ressource] > Actions > Imprimer la carte`

Génère une carte de visite de la ressource au format PDF.

### Badge

Chemin : `[ressource] > Actions > Badge`

Génère un badge imprimable pour la ressource.

### Export PDF

Chemin : `[ressource] > Actions > Exporter en PDF`

Génère un rapport PDF complet de la fiche ressource incluant toutes les informations configurées dans `Configuration > Rapport`.

---

## 23. Récapitulatif

Chemin : `Ressources humaines > Récapitulatif`

Vue synthétique des ressources par période, permettant de visualiser les arrivées et départs planifiés sur une plage de dates.

---

## 24. Intitulés configurables

Chemin : `Configuration > Intitulés`

| Intitulé | Description |
|----------|-------------|
| Type de contrat | Voir section 4 |
| Département | Organigramme |
| Service | Subdivision du département |
| Équipe | Groupe de travail |
| Fonction | Intitulé de poste |
| Rôle | Rôle dans l'organisation |
| Profession | Métier exercé |
| Catégorie de profession | Regroupement de professions |
| Filière professionnelle | (secteur public) |
| Ligne de métier | (secteur public) |
| Grade | (secteur public) |
| Rang | (secteur public) |
| Nature du contrat | Précision sur le type contractuel |
| Situation de la ressource | Statut RH |
| Spécialité | Domaine d'expertise |
| Habilitation | Accréditation/autorisation (arborescence) |
| Niveau d'habilitation | Niveau associé à une habilitation |
| Type de tâche | Catégorie de tâche |
| Motif de départ | Raison de fin de contrat |
| Motif de démission | Raison de résiliation |
| Source de recrutement | Canal de recrutement |
| Profil d'action | Actions à effectuer selon le profil |
| Type de budget | Catégorie budgétaire |
| Volume budgétaire | Enveloppe par ligne budgétaire |
| Unité organisationnelle | Structure organisationnelle |
| Client | Client associé |

---

## 25. Bonnes pratiques

- **Configurer les types de contrat** avant de créer des ressources pour que le wizard affiche les bonnes étapes
- **Utiliser les gabarits** pour les profils récurrents (ex : stagiaire, prestataire CDI)
- **Mettre en place les règles de checklist** pour automatiser l'attribution des actions d'arrivée/départ
- **Activer les actions automatiques** `Resources`, `ResourcesChecklist` et `ResourcesTask` pour les alertes proactives
- **Configurer les notifications** `AlertExpiredTasks` et `AlertLeavingResources` pour ne pas manquer les échéances critiques
- **Donner le droit `plugin_resources_all`** uniquement aux RH et managers ayant besoin de voir l'ensemble des collaborateurs
- **Masquer les champs sensibles** dans l'annuaire (matricule, téléphone) si des profils à droits limités y ont accès
