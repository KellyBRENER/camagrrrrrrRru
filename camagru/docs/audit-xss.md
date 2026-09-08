# Audit XSS — 2026-09-08

Inspection des PHP et JS avant modification, puis recherche des sinks après correction.
Périmètre : contrôleurs, modèles, vues, routeur AJAX, galerie, studio, authentification,
configuration et scripts chargés. Les modifications préexistantes ont été conservées.

## Résultats et corrections

| Emplacement / fonction | Origine et contexte | Résultat |
| --- | --- | --- |
| `public/js/router.js`, `loadPage` | Paramètre page et réponse HTTP injectée avec innerHTML | Risque de confusion JSON/HTML : une réponse API réussie pouvait être interprétée comme HTML, y compris des commentaires bruts. Vérification stricte du Content-Type text/html avant lecture/injection. |
| `public/js/router.js`, `loadPageScript` | page issu de l'URL, import dynamique | Renforcement : seuls les membres propres de la table de modules sont utilisables (pas les propriétés héritées). Les noms de modules restent prédéfinis. |
| `public/js/pages/gallery.js`, `createPhotoCard` | comments_count venant de l'API, interpolation innerHTML | Remplacée par un gabarit constant et textContent. Le serveur convertissait déjà le compteur en entier : risque latent, pas une XSS stockée démontrée via le compteur actuel. |
| Vues header, register, resend_validation, reset_password, verify_confirm | Pseudo session/BDD, messages GET/session, token dans value | Tous les htmlspecialchars utilisent explicitement ENT_QUOTES \| ENT_SUBSTITUTE et UTF-8. Les sorties étaient déjà échappées ; harmonisation et gestion robuste des caractères invalides. |
| Vues register, resend_validation | GET error/success | Les tableaux/non-chaînes sont ignorés, évitant un TypeError au rendu. |
| `app/Core/Security.php`, `csrfField` | Token serveur dans value | Même échappement explicite ; pas de modification du token stocké. |
| `AuthController`, `sendVerificationEmail`, `forgotPassword` | Pseudo et URL dans email HTML | Même échappement explicite au rendu. URL construite avec APP_URL validée http/https et token encodé. |
| `public/js/image-paths.js`, `photoUrl`; galerie/studio | path de photo depuis BDD/API dans src | Liste blanche uploads/photos/{nombre}/{nombre}.png ; repli favicon pour les valeurs invalides. Aucun protocole/arbitraire distant accepté. Renforcement du contexte URL, pas une XSS prouvée par img.src seul. |
| `studio.js`, `loadFilters` | URL du manifeste local, ensuite src des filtres/stickers | N'accepte que les PNG sous /images/filters/frames ou /stickers avec nom simple. |

Aucun échappement HTML ajouté au stockage. Les commentaires et pseudos historiques
restent inchangés en BDD. La validation métier existante des nouveaux pseudos est conservée.
Aucun champ description fonctionnel trouvé ; à protéger au rendu s'il est ajouté.

## Contextes particuliers et usages conservés

- **JavaScript inline** : `layout.php` transmet username dans window.userConfig avec
  json_encode et JSON_HEX_TAG/AMP/APOS/QUOT + JSON_INVALID_UTF8_SUBSTITUTE, déjà présents.
  Cette sérialisation adaptée empêche de fermer le script avec un pseudo ; ne pas la
  remplacer par htmlspecialchars. Le paramètre de version du script est un hash serveur.
- **Attributs HTML** : pseudo du header échappé ; token email dans value échappé après
  validation du contrôleur ; champ CSRF échappé. Les alt/title/dataset du JS sont affectés
  par les API DOM, sans concaténation de balisage. Une chaîne contenant des guillemets
  ne crée pas de nouvel attribut. Aucun attribut événementiel alimenté par une donnée.
- **URL** : photos validées par photoUrl ; filtres validés au chargement. Webcam/crop
  utilisent canvas.toDataURL(image/png), uploads des URL blob créées localement et
  affectées exclusivement à des images. Les noms de fichiers clients ne sont pas rendus
  en HTML et le serveur génère ses propres chemins PNG. Les paramètres de requêtes sont
  assemblés avec URLSearchParams/encodeURIComponent ; redirections JS fixes.
- **CSS inline** : display dépend de constantes block/none ; positions, tailles, rotation
  du studio proviennent de calculs numériques et utilisent les propriétés style dédiées.
  Pas de texte utilisateur concaténé dans une balise style, cssText ou une URL CSS.
- **HTML interprété volontairement** : seul le routeur de production injecte une réponse
  HTML dynamique : vues locales issues du routeur PHP, sorties utilisateur échappées.
  La vérification du MIME n'est PAS un sanitizer : chaque future vue doit respecter les
  mêmes règles. Le serveur ne doit jamais étiqueter les réponses API comme text/html.
- Les autres innerHTML sont des chaînes vides ou des gabarits constants (chargement,
  erreurs fixes, icônes). Conservés car ils ne contiennent aucune interpolation utilisateur.
  Aucun outerHTML, insertAdjacentHTML, document.write ou eval trouvé dans l'application.
- Commentaires, auteurs, hashtags, erreurs API utilisent textContent/innerText. Les
  réponses JSON ne sont pas pré-échappées HTML et sont servies avec application/json.
- Les messages PDO de configuration restent un sujet de durcissement distinct : aucun
  flux utilisateur vers ces messages n'a été identifié ici, mais ne pas exposer les erreurs
  techniques au visiteur ; désactiver display_errors pour le rendu final.

## CSP proposée (non activée)

Chargement actuel : modules locaux app.js/router.js/pages/*.js, imports dynamiques locaux,
un script inline window.userConfig, CSS Bootstrap et police Bootstrap Icons depuis
cdn.jsdelivr.net. Aucun JavaScript tiers nécessaire. Le studio utilise data:/blob: pour
ses images, getUserMedia/srcObject pour la webcam et plusieurs styles inline.

Politique cible à envoyer comme en-tête HTTP après adaptation du script inline :

```text
Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-VALEUR_ALEATOIRE_PAR_REPONSE'; script-src-attr 'none'; style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' data: blob:; media-src 'self' blob:; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'
```

1. Générer un nonce cryptographique neuf par réponse HTML, le transmettre dans l'en-tête
   et sur le seul script inline autorisé de layout.php. Ne jamais utiliser le token CSRF
   ou une constante comme nonce. Alternative : déplacer la configuration dans des données
   non exécutables correctement encodées et la lire depuis un module externe.
2. Essayer d'abord Content-Security-Policy-Report-Only sur l'application réelle ; tester
   navigation AJAX, login, galerie, webcam, upload, crop, images et styles/icônes CDN.
   Prévoir un collecteur local si des rapports persistants sont souhaités.
3. Passer en enforcement après ces vérifications. Aucun unsafe-inline ni unsafe-eval
   nécessaire pour les scripts. unsafe-inline ci-dessus concerne uniquement les styles
   existants ; déplacer les styles statiques vers CSS avant de durcir cette directive.
4. Héberger CSS/polices localement permettrait ensuite de supprimer jsdelivr des sources.

La CSP n'est pas activée par cet audit afin de ne pas bloquer window.userConfig ou casser
le studio. Elle complète les corrections de sortie et ne les remplace pas.
Référence : https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy/script-src

## Vérifications réalisées

- `docker compose exec -T app php tests/security.php` depuis camagru : **84 vérifications
  réussies**, base temporaire isolée, mails capturés sans envoi externe. Nouveaux cas :
  messages GET complets/AJAX, paramètres tableaux, pseudos historiques, conservation des
  originaux en BDD et commentaires JSON. Les parcours existants auth/CSRF/montages passent.
- `tests/xss-dom.html` dans Chrome headless : **PASS**, vrais modules galerie/routeur avec
  réponses API simulées : pseudos, commentaires, compteur API forgé, alt, hashtags,
  chemins invalides, rejet JSON et navigation HTML. Aucune alerte exécutée, aucun attribut
  onerror créé. Test réalisé sans CSP pour vérifier les protections de sortie elles-mêmes.
- Charges testées : `<img src=x onerror=alert('XSS')>`, `">alert('XSS')` et fermeture de script.
- Syntaxe de tous les PHP et JS : OK ; git diff --check : OK.
- Nouvelle recherche des sinks : uniquement les usages justifiés ci-dessus subsistent.

Rejouer le test DOM depuis la racine : `node camagru/src/tests/xss-server.cjs`, puis ouvrir
http://127.0.0.1:8793/ et vérifier PASS. Ce serveur local de test ne doit pas être déployé.
Arrêter avec Ctrl-C. Aucun ajout de dépendance JS à l'application.

Limites : pas de recette interactive complète Firefox/mobile/webcam physique, ni de
validation de la CSP en enforcement. Les tests DOM utilisent des API simulées ; ils
complètent les tests HTTP réels, sans constituer une garantie absolue d'absence de faille.
