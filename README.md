# JMC Propreté Nature

Site vitrine HTML/CSS/JavaScript avec point d’entrée PHP pour le formulaire.

## Hébergement
Les fichiers publics sont directement à la racine de `main`, à déployer dans le répertoire public associé au domaine OVH. Aucune compilation, aucun serveur Node.js, Composer ni base de données en production. PHP 8.2+ et HTTPS sont requis.

## Formulaire sans configuration manuelle
`contact.php` utilise PHP `mail()` vers `arnaud.feret@jmcpropretenature.fr`. L’expéditeur est cette adresse du domaine ; l’adresse du visiteur sert uniquement de `Reply-To`. Aucun mot de passe, clé SMTP ou fichier `jmc-private/contact-config.php` n’est nécessaire.

À la première requête, PHP crée automatiquement un dossier `.jmc-contact-…` hors de la racine publique : d’abord dans le dossier parent du site (habituellement à côté de `www`), sinon dans le répertoire temporaire système. Le chemin réel est vérifié par rapport au répertoire du script et à `DOCUMENT_ROOT`. Sur l’hébergement, ces emplacements ne doivent pas être exposés par un autre domaine ou alias web. Les permissions sont contrôlées : dossiers `0700`, fichiers `0600`, propriétaire vérifié lorsque l’extension POSIX est disponible. Les liens symboliques sont refusés aux emplacements créés. Si aucun emplacement privé ne convient, l’envoi échoue avec HTTP 503 : aucune protection n’est désactivée.

Le secret aléatoire est généré sur le serveur, jamais dans GitHub. Le stockage contient les sessions et des compteurs verrouillés : empreintes HMAC des adresses IP et horodatages, sans contenu des demandes ni adresse IP brute. Les événements de plus de 15 minutes sont supprimés lors du prochain envoi valide. Les sessions expirent après une heure et sont nettoyées par le ramasse-miettes PHP. Ce stockage ne doit pas être versionné, rendu public ou effacé à chaque déploiement ; un nettoyage du temporaire système peut réinitialiser les compteurs.

Protections : validation serveur, téléphone obligatoire, listes de valeurs autorisées, refus des caractères de contrôle et injections d’en-têtes, jeton de session expirant, cookie sécurisé HttpOnly/SameSite, champ piège, délai minimal, verrouillage des compteurs, cinq tentatives par IP sur 15 minutes et cent tentatives globales sur 15 minutes (échecs de `mail()` compris). Un même jeton déjà envoyé ne renvoie pas un deuxième message. Aucun contenu personnel ni argument d’exception n’est journalisé par l’application. Les journaux d’accès de l’hébergeur restent sous sa responsabilité ; les données du formulaire passent en POST, jamais dans une URL.

## Vérification effectuée
Syntaxe et scénarios serveur exécutés avec PHP 8.2.33 (WebAssembly local) : initialisation sans configuration, permissions privées, validation, jeton, champ piège, délai, anti-injection, plafonds individuel/global, expiration des compteurs, refus du stockage public ou indisponible, échec/succès simulés et prévention des doubles envois. Six scénarios JavaScript client passent également. `mail()` a été simulé : aucun message réel envoyé. Le stockage natif et la délivrabilité OVH restent à valider sur l’hébergement.

## Récupérer la correction sur OVH
1. Espace client OVHcloud : **Web Cloud → Hébergements → votre hébergement → Mes sites**.
2. Sur le site déjà associé à ce dépôt et à `main`, menu **⋮ → Déployer Git → Confirmer**. Ne pas forcer l’écrasement de modifications locales sans les vérifier. Si un webhook a été configuré, il peut déjà avoir déclenché cette mise à jour automatiquement.
3. Vérifier **Informations du dernier déploiement**. Conserver PHP 8.2 ou une version ultérieure compatible et HTTPS.
4. Ouvrir le site HTTPS dans une nouvelle fenêtre privée. Remplir tous les champs, téléphone compris, avec une adresse de test extérieure ; attendre quelques secondes et envoyer une seule demande clairement identifiée comme test.
5. La confirmation indique uniquement que `mail()` a accepté le message. Vérifier sa réception effective dans `arnaud.feret@jmcpropretenature.fr`, y compris les indésirables, le contenu de tous les champs et l’adresse de réponse.
6. Si la confirmation apparaît sans réception, contrôler le suivi des e-mails automatisés de l’hébergement OVH (blocage/quota/erreurs) et la configuration du domaine et de la messagerie avec OVH. Si HTTP 503 apparaît, faire vérifier par OVH les sessions PHP et l’accès en écriture à un stockage privé hors du répertoire web ; ne pas déplacer les données dans `www` pour contourner le problème.

Guide officiel : https://docs.ovhcloud.com/fr/guides/web-cloud/web-hosting/git-integration-webhosting
PHP `mail()` : https://www.php.net/manual/fr/function.mail.php (acceptation ne signifie pas réception).

## État du site
Design et contenus inchangés par cette correction. Les mentions du médiateur restent à compléter. L’exploration et l’indexation sont autorisées pour les pages publiques ; le sitemap déclare leurs URL HTTPS sans www. Cela ne garantit ni leur indexation effective ni leur classement par les moteurs. Aucun déploiement OVH n’est effectué par cette correction GitHub.

## Ressources
Police Montserrat hébergée localement, licence dans `assets/fonts/OFL.txt`. Les autres contenus graphiques restent soumis aux droits de leurs titulaires. Ce dépôt n’accorde pas de licence de réutilisation des photographies et de l’identité visuelle JMC.
