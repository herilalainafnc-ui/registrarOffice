# Système d'Autorisation de Réinscription des Étudiants

## Vue d'ensemble

Ce système permet aux caissiers/trésoriers d'autoriser ou de refuser l'accès à la réinscription pour les étudiants. Les étudiants non autorisés ne pourront pas commencer le processus d'inscription et verront un avertissement.

---

## Architecture et Composants

### 1. Base de Données

**Migration SQL:** `data/sql_authorized_reinscription.sql`

Trois nouveaux champs sont ajoutés à la table `tbl_2024_etudiant`:
- `authorized_reinscription` (TINYINT): 0 = non autorisé, 1 = autorisé
- `authorized_by` (INT): ID de l'utilisateur qui a donné l'autorisation
- `authorized_date` (DATETIME): Date de l'autorisation

**À exécuter:** 
```sql
-- Exécuter le fichier SQL:
MySQL> source data/sql_authorized_reinscription.sql;
```

### 2. Interface de Gestion (Caisse)

**Fichier:** `src/finance/authorized_reinscription.php`
**Route:** `/finance/authorized-reinscription`
**Accès:** Caissiers, Trésoriers, Admins Finance

#### Fonctionnalités:
- Liste de tous les étudiants actifs
- Recherche en temps réel (matricule, nom, filière)
- Cases à cocher pour autoriser/refuser
- Affichage du dernier modificateur et de la date
- Mise à jour instantanée via AJAX

**Rôles autorisés:**
- caissier
- tresorier
- finance_admin
- superadmin
- admin

### 3. API de Mise à Jour

**Fichier:** `src/api/update-authorization.php`
**Endpoint:** `POST /api/update-authorization`

#### Paramètres:
```json
{
  "student_id": 123,
  "authorized_reinscription": 1
}
```

#### Réponses:
```json
// Succès:
{
  "success": true,
  "message": "Étudiant autorisé pour la réinscription",
  "authorized": 1
}

// Erreur:
{
  "success": false,
  "message": "Erreur descriptive"
}
```

### 4. Page d'Inscription

**Fichier modifié:** `inscription/inscription.php`

#### Modifications:
- Vérification du champ `authorized_reinscription` après récupération du profil
- Affichage d'un avertissement rouge si l'étudiant n'est pas autorisé
- Désactivation des boutons et éléments d'interaction
- Message d'alerte si l'étudiant tente d'accéder aux étapes

### 5. Routes

**Fichier modifié:** `routes.php`

Routes ajoutées:
```php
// Dans FinanceController
$router->any('/finance/authorized-reinscription', 'FinanceController@authorizedReinscription');

// Dans ApiController
$router->any('/api/update-authorization', 'ApiController@updateAuthorization');
```

### 6. Contrôleurs

**Fichiers modifiés:**
- `controllers/FinanceController.php`: Nouvelle méthode `authorizedReinscription()`
- `controllers/ApiController.php`: Nouvelle méthode `updateAuthorization()`

---

## Flux d'Utilisation

### Pour les Caissiers:

1. Accéder à **Finances > Autorisation de Réinscription**
2. Rechercher l'étudiant (par matricule, nom ou filière)
3. Cocher/décocher la case d'autorisation
4. L'enregistrement se fait automatiquement
5. Un message de succès apparaît

### Pour les Étudiants:

1. Accéder à la page d'inscription
2. **Si non autorisé:**
   - Message d'avertissement rouge s'affiche
   - Les boutons et options sont désactivés
   - Clic sur un bouton affiche: "Cette étudiant n'a pas été autorisé pour la réinscription"
3. **Si autorisé:**
   - Le processus d'inscription continue normalement

---

## Instructions d'Installation

### 1. Mettre à jour la Base de Données

Exécuter la migration SQL:
```bash
mysql -u root -p registrar_db < data/sql_authorized_reinscription.sql
```

Ou via votre client MySQL:
```sql
-- Copiez-collez le contenu de data/sql_authorized_reinscription.sql
```

### 2. Vérifier les Fichiers

Les fichiers suivants ont été créés/modifiés:

**Créés:**
- ✅ `data/sql_authorized_reinscription.sql` - Migration
- ✅ `src/finance/authorized_reinscription.php` - Interface de gestion
- ✅ `src/api/update-authorization.php` - API de mise à jour

**Modifiés:**
- ✅ `routes.php` - Routes ajoutées
- ✅ `controllers/FinanceController.php` - Nouvelle méthode
- ✅ `controllers/ApiController.php` - Nouvelle méthode
- ✅ `inscription/inscription.php` - Vérification de l'autorisation

### 3. Test

1. Accédez à `/finance/authorized-reinscription`
2. Testez la recherche d'un étudiant
3. Cochez/décochez un étudiant
4. Vérifiez que le changement est enregistré
5. Allez sur la page d'inscription d'un étudiant non autorisé
6. Vérifiez que l'avertissement s'affiche et les boutons sont désactivés

---

## Variables de Session Utilisées

L'API utilise les fonctions middleware:
- `isLoggedIn()` - Vérifier l'authentification
- `getCurrentUserRole()` - Récupérer le rôle de l'utilisateur
- `getCurrentUserId()` - Récupérer l'ID de l'utilisateur

Ces fonctions sont définies dans `data/middleware.php`.

---

## Sécurité

✅ **Authentification vérifiée** - Seuls les utilisateurs connectés
✅ **Autorisation vérifiée** - Seuls les rôles autorisés
✅ **Injection SQL prévenue** - Requêtes préparées avec PDO
✅ **CSRF protection** - Middleware intégré
✅ **Logs** - Les modifications sont enregistrées avec user ID et date

---

## Logs et Audit

Les modifications sont enregistrées automatiquement:
- `authorized_by`: ID de l'utilisateur qui a modifié
- `authorized_date`: Date/heure de la modification
- `authorized_reinscription`: Statut actuel (0 ou 1)

---

## Troubleshooting

### L'API retourne 403 (Accès refusé)
- Vérifiez que l'utilisateur a l'un des rôles: caissier, tresorier, finance_admin, superadmin, admin
- Vérifiez la fonction `getCurrentUserRole()` dans le middleware

### L'étudiant voit toujours l'avertissement après autorisation
- Videz le cache du navigateur (Ctrl+Shift+Del)
- Rechargez la page
- Vérifiez que la base de données a bien reçu l'update

### La page d'autorisation est vide (pas d'étudiants)
- Vérifiez que les étudiants ne sont pas marqués comme `remove = 1` ou `graduated = 1`
- Vérifiez la connexion à la base de données

---

## API JavaScript Example

```javascript
// Exemple d'appel API
const formData = new FormData();
formData.append('student_id', 123);
formData.append('authorized_reinscription', 1);

fetch('/api/update-authorization', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Autorisé:', data.authorized);
    } else {
        console.error('Erreur:', data.message);
    }
});
```

---

## Support et Maintenance

Pour toute question ou modification, consultez:
- Structure de la base de données: `data/sql_*.sql`
- Middleware: `data/middleware.php`
- Contrôleurs: `controllers/*.php`
- Routes: `routes.php`

---

**Version:** 1.0  
**Date:** 5 Mars 2026  
**Auteur:** Infinit Registrar Team
