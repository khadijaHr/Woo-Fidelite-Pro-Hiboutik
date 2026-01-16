# Fidélité Pro — Points & Récompenses Hiboutik

Un plugin WordPress/WooCommerce complet pour gérer un programme de fidélité avec synchronisation automatique via l'API Hiboutik.

## 📋 Description

**Fidélité Pro** est un système complet de gestion des points de fidélité pour WooCommerce, synchronisé avec Hiboutik. Il permet aux clients de gagner des points sur leurs achats, de les consulter dans leur compte, et de les utiliser comme réduction ou pour obtenir des produits gratuits. Le plugin offre une synchronisation automatique des clients et des commandes, un historique détaillé, et une intégration complète avec l'API Hiboutik.

## ✨ Fonctionnalités

### Pour les clients
- **Affichage des points** : Visualisation des points de fidélité dans le panier WooCommerce et le compte client
- **Utilisation flexible** : Application des points comme réduction sur le panier ou comme produit offert
- **Historique détaillé** : Consultation de l'historique des points gagnés et utilisés dans "Mon compte"
- **Interface intuitive** : Interface utilisateur moderne et responsive

### Pour les administrateurs
- **Synchronisation automatique** : Synchronisation des clients et de leurs points depuis Hiboutik
- **Gestion centralisée** : Page d'administration dédiée "Fidélité Clients" avec liste complète des clients et leurs points
- **Historique des commandes** : Logs détaillés de toutes les transactions de points
- **Synchronisation des commandes** : Synchronisation automatique des commandes Hiboutik avec gestion des logs
- **Intégration Make/Integromat** : Support pour notifier la fin de synchronisation via webhooks

## 🚀 Installation

### Prérequis
- WordPress 6.2 ou supérieur
- PHP 7.0 ou supérieur
- WooCommerce activé
- Compte Hiboutik avec accès API

### Étapes d'installation

1. **Télécharger le plugin**
   ```bash
   # Cloner le repository ou télécharger le ZIP
   git clone https://github.com/khadijahr/loyalty-points-plugin.git
   ```

2. **Installer le plugin**
   - Placez le dossier du plugin dans `wp-content/plugins/`
   - Ou installez-le via l'interface WordPress (Plugins > Ajouter)

3. **Activer le plugin**
   - Allez dans **Plugins** > **Plugins installés**
   - Activez "Fidélité Pro — Points & Récompenses Hiboutik"

4. **Configuration**
   - Configurez les options Hiboutik dans les réglages WordPress :
     - `hiboutik_account` : Nom de votre compte Hiboutik
     - `hiboutik_user` : Nom d'utilisateur API Hiboutik
     - `hiboutik_key` : Clé API Hiboutik

## ⚙️ Configuration

### Options WordPress

Le plugin utilise les options WordPress suivantes (à configurer via code ou plugin de gestion d'options) :

```php
// Configuration Hiboutik
update_option('hiboutik_account', 'votre-compte');
update_option('hiboutik_user', 'votre-utilisateur');
update_option('hiboutik_key', 'votre-cle-api');

// Pourcentage de points gagnés (optionnel)
update_option('lp_percentage_points', 5); // 5% du montant TTC
```

### Structure de la base de données

Le plugin crée automatiquement deux tables lors de l'activation :

- **`wp_loyalty_points`** : Stocke les informations des clients et leurs points
- **`wp_loyalty_details`** : Historique détaillé des transactions de points

## 📖 Utilisation

### Synchronisation des clients

#### Méthode 1 : Interface d'administration
1. Allez dans **Fidélité Clients** dans le menu WordPress
2. Cliquez sur **Synchroniser les Clients**
3. Les clients et leurs points seront synchronisés depuis Hiboutik

#### Méthode 2 : Synchronisation automatique
- Le plugin exécute une synchronisation automatique toutes les heures via un cron WordPress

#### Méthode 3 : Endpoint manuel
```
https://votre-site.com/trigger-lp-sync/?key=MaCleSuperSecrete123!
```

#### Méthode 4 : REST API
```
GET https://votre-site.com/wp-json/lp/v1/sync-customers/?key=MaKeySecreteMystore123!
```

### Application des points dans le panier

1. **Réduction directe** : Les clients peuvent appliquer leurs points comme réduction sur le total du panier
2. **Produits offerts** : Les clients peuvent utiliser leurs points pour obtenir des produits gratuits
3. **Suivi automatique** : Les points utilisés sont automatiquement enregistrés dans la commande et synchronisés avec Hiboutik

### Administration

#### Page "Fidélité Clients"
- Liste complète des clients avec leurs points
- Recherche et filtrage des clients
- Synchronisation manuelle
- Accès aux logs individuels
- Pagination pour une meilleure performance

#### Page "Logs"
- Historique détaillé des commandes et des points pour chaque client
- Affichage des points gagnés, utilisés et totaux
- Dates et détails de chaque transaction
- Synchronisation des commandes Hiboutik

### Affichage dans "Mon compte"

Les clients peuvent consulter :
- Leur solde de points actuel
- L'historique de leurs transactions
- Les points gagnés et utilisés par commande
- Les détails de chaque transaction

## 🎨 Personnalisation

### Pourcentage de points

Le pourcentage de points gagnés par commande est configurable :

```php
// Définir le pourcentage (ex: 5% = 5)
update_option('lp_percentage_points', 5);
```

### Styles CSS

Le plugin utilise des fichiers CSS séparés pour une meilleure organisation :

- **Admin** : `assets/css/loyalty-points-admin.css`
- **Frontend** : `assets/css/loyalty-points-frontend.css`

Vous pouvez personnaliser les styles en modifiant ces fichiers ou en ajoutant vos propres règles CSS.

### Endpoints et clés secrètes

Les endpoints et clés secrètes peuvent être modifiés dans le code du plugin pour une sécurité renforcée.

## 🔒 Sécurité

- **Protection des endpoints** : Tous les endpoints de synchronisation sont protégés par une clé secrète
- **Sécurisation AJAX** : Les actions AJAX utilisent des nonces WordPress
- **Validation des données** : Toutes les entrées utilisateur sont validées et sanitizées
- **Permissions** : Seuls les administrateurs peuvent accéder aux pages d'administration

## 📁 Structure du projet

```
loyalty-points-plugin/
├── loyalty-points.php          # Fichier principal du plugin
├── README.md                   # Documentation
├── assets/
│   ├── css/
│   │   ├── loyalty-points-admin.css      # Styles administration
│   │   └── loyalty-points-frontend.css    # Styles frontend
│   └── js/
│       ├── loyalty1.js                    # Scripts panier
│       └── admin-orders.js               # Scripts administration
└── ...
```

## 🔧 Dépendances

- **WooCommerce** : Plugin e-commerce WordPress
- **jQuery** : Bibliothèque JavaScript (incluse avec WordPress)
- **Select2** : Plugin de sélection améliorée (chargé via CDN)
- **SweetAlert2** : Bibliothèque de notifications (chargée via CDN)

## 🐛 Dépannage

### Les points ne se synchronisent pas
- Vérifiez que les identifiants Hiboutik sont correctement configurés
- Vérifiez les permissions de l'utilisateur API Hiboutik
- Consultez les logs WordPress pour les erreurs éventuelles

### Les points ne s'affichent pas dans le panier
- Vérifiez que l'utilisateur est connecté
- Vérifiez que le client est bien synchronisé dans la table `wp_loyalty_points`
- Vérifiez que WooCommerce est activé et fonctionnel

### Erreurs de synchronisation
- Vérifiez la connexion à l'API Hiboutik
- Vérifiez que le compte Hiboutik est actif
- Consultez les logs d'erreur WordPress

## 📝 Changelog

### Version 1.3.6
- Séparation des styles CSS dans des fichiers dédiés
- Amélioration de la structure du code
- Optimisation du chargement des assets
- Correction de bugs mineurs

## 🤝 Contribution

Les contributions sont les bienvenues ! N'hésitez pas à :
- Ouvrir une issue pour signaler un bug
- Proposer des améliorations
- Soumettre une pull request

## 📄 Licence

Ce plugin est sous licence **GPL v3 ou ultérieure**.

Voir le fichier [LICENSE](https://www.gnu.org/licenses/gpl-3.0.html) pour plus de détails.

## 👤 Auteur

**Khadija Har**

- GitHub: [@khadijahr](https://github.com/khadijahr/)
- URI: https://github.com/khadijahr/

## 🙏 Remerciements

- Hiboutik pour l'API de gestion
- WooCommerce pour la plateforme e-commerce
- La communauté WordPress

---

**Note** : Ce plugin nécessite un compte Hiboutik actif pour fonctionner. Pour plus d'informations sur Hiboutik, visitez [hiboutik.com](https://www.hiboutik.com).
