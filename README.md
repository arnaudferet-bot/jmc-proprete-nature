# JMC Propreté Nature

Site vitrine HTML/CSS/JavaScript avec point d’entrée PHP pour le formulaire.

## Hébergement
Les fichiers publics sont directement à la racine de la branche `main`. Le contenu de cette branche est destiné au répertoire public associé au domaine dans OVH. Pas de compilation, serveur Node.js, Composer ou base de données nécessaire en production. PHP 8.2+ et HTTPS sont requis pour le formulaire.

## Configuration du formulaire
Le serveur lit uniquement un fichier extérieur à la racine publique : `../jmc-private/contact-config.php` depuis `contact.php`. Cette configuration, le secret anti-spam et les compteurs ne sont jamais versionnés. L’absence de configuration laisse l’envoi désactivé. Installer la configuration séparément par SFTP hors de tout répertoire exposé, avec des permissions restrictives.

## État de validation
Le code PHP est préparé ; son exécution et la réception réelle d’un message sur OVH restent à tester. Le site est conservé sans modification éditoriale ou graphique. Les mentions du médiateur restent à compléter. L’indexation demeure bloquée (`noindex,nofollow` et `robots.txt`) jusqu’aux tests et à la validation finale.

## Déploiement
Ne pas associer ni déployer sur OVH avant accord. Vérifier tous les domaines rattachés au répertoire cible. Le guide OVH explique la configuration GitHub publique et la branche : https://docs.ovhcloud.com/fr/guides/web-cloud/web-hosting/git-integration-webhosting

## Ressources
Police Montserrat hébergée localement, licence incluse dans `assets/fonts/OFL.txt`. Les autres contenus graphiques restent soumis aux droits de leurs titulaires. Ce dépôt n’accorde pas de licence de réutilisation des photographies et de l’identité visuelle JMC.
