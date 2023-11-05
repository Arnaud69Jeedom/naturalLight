# Changelog plugin naturalLight

>**IMPORTANT**
>
>S'il n'y a pas d'information sur la mise à jour, c'est que celle-ci concerne uniquement de la mise à jour de documentation, de traduction ou de texte.

# 05/11/2023 - STABLE

- NEW : Optimisation des calculs lors d'un cron

# 26/04/2023 - DEBUG

- NEW : Optimisation des calculs lors d'un cron

# 24/04/2023 - STABLE

- NEW : Gestion d'un commande pour les horaires matin et soir de Luminosité
- NEW : Gestion des horaires donnés par le plugin Héliotrope (600 pour 6H00)
- DEBUG : Gestion de lampe avec Couleur en % (1 - 100)
- DEBUG : Gestion de lampe avec le status On/Off (et non 1/0)
- Debug : Ne plus bloquer si le type générique n'est pas le bon
          Tentative pour gérer une lampe Yeelight

# 14/04/2023 - DEBUG

- DEBUG : Gestion de lampe avec Couleur en % (1 - 100)
- DEBUG : Inversion sur la gestion en %

# 12/04/2023 - DEBUG

- DEBUG : Gestion de lampe avec le status On/Off (et non 1/0)

# 11/04/2023 - DEBUG

- NEW : Gestion des horaires donnés par le plugin Héliotrope (600 pour 6H00)

# 10/04/2023 - DEBUG

- Debug : Ne plus bloquer si le type générique n'est pas le bon
          Tentative pour gérer une lampe Yeelight
- NEW : Gestion d'un commande pour les horaires matin et soir de Luminosité

# 29/03/2023 - DEBUG & STABLE

- Debug : Le type devrait aussi pouvoir etre « LIGHT_STATE_BOOL »
(merci @scanab)

# 26/03/2023 - STABLE

- Ajout de la gestion de la luminosité

# 12/03/2023 - DEBUG

- Debug sur la gestion des options désactivées

# 11/03/2023 - DEBUG

- Ajout de la gestion de la Luminosité en fonction de l'heure

# 24/02/2023 - DEBUG

- DEBUG : Permettre toutes les commandes de type Info pour la Condition

# 23/02/2023

- Tooltip pour la Condition de fonctionnement
- Gestion du plugin ikea avec :
    Température chaude = 100
    Température froide = 0
- Gestion d'un plugin potentiel en Kelvin

# 22/02/2023

- Paramétrage de valeurs min et max de la température couleur
  Les valeurs par défaut sont récupérées du paramétrage de la lampe
  La mise à jour est effectuée lors de l'enregistrement de l'équipement
- Gestion d'une condition pour modifier ou non la température de l'ampoule
- Optimisation du calcul : Eviter si non nécessaire (lampe éteinte et pas d'historisation)
- Nettoyage du code

# 21/02/2023

- Refactorisation du code
- DEBUG : Retour sur la formule d'origine
- DEBUG : Gérer min = 0
- DEBUG : Prendre la formule d'origine plutot que celle des heures : trop blanc
- DEBUG : Suppression de la correction altitude
- DEBUG : Cron au lieu de Cron5

# 08/02/2023

- Debug divers : formule, nom de classe

# 16/01/2023

- Gestion via Listener

# 15/01/2023

- Création du plugin
