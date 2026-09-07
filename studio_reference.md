# Reference studio

Ce document resume le fonctionnement de la page studio et sert de repere rapide avant de modifier `studio.php`, `studio.js` ou le CSS associe.

## Fichiers concernes

- `camagru/src/app/Views/studio.php` : structure HTML de la page studio.
- `camagru/src/public/js/pages/studio.js` : logique navigateur du studio.
- `camagru/src/public/css/style.css` : mise en page, preview carree, modales, stickers, filtres, responsive.
- `camagru/src/public/images/filters/filters.json` : liste des cadres et stickers disponibles.
- `camagru/src/config/routes.php` : routes backend utilisees par le studio.
- `camagru/src/app/Controllers/PhotoController.php` : creation, liste et suppression des montages.

## Role de `studio.php`

`studio.php` ne contient presque pas de logique PHP active : il fournit surtout le DOM que `studio.js` va recuperer avec des `id`.

Structure principale :

- `.studio-page` : racine de la page.
- `.studio-workspace` : grille principale en trois zones.
- `.studio-filters-panel` : colonne des filtres disponibles.
- `.studio-main` : zone centrale avec l'apercu et les boutons.
- `.studio-side` : liste des montages deja produits par l'utilisateur.

Zones de filtres :

- `#frameFiltersList` recoit les cadres depuis `filters.json`.
- `#stickerFiltersList` recoit les stickers depuis `filters.json`.
- Le HTML ne hardcode pas les filtres : ils sont generes en JS.

Zone d'apercu :

- `#cameraPreview` est le cadre carre principal.
- `#webcam` affiche le flux camera.
- `#overlay` est un canvas technique utilise pour capturer, rogner et exporter une image.
- Les images de preview, cadres et stickers sont ajoutes dynamiquement par `studio.js`.

Boutons :

- `#startCamera` demarre la camera.
- `#uploadBtn` declenche le champ cache `#uploadPhoto`.
- `#capturePhoto` capture une image depuis la camera.
- `#createMontage` ouvre la modale des mots-cles.
- `#cropPhoto` existe encore comme bouton de validation de crop, mais il est cache dans le flux actuel.

Modales :

- `#hashtagsModal` demande les mots-cles avant la creation du montage.
- `#photoViewerModal` affiche un montage en grand avec navigation precedent/suivant.
- `#imageCropModal` permet de rogner une photo uploadee dans un cadre carre.

Important : si un `id` est renomme dans `studio.php`, il faut mettre a jour `studio.js`, sinon l'init s'arrete avec l'erreur "La page studio n'a pas tous les elements attendus."

## Role general de `studio.js`

`studio.js` exporte une seule fonction :

```js
export async function init()
```

Cette fonction est appelee par le routeur frontend quand la page studio est chargee. Elle :

1. recupere tous les elements HTML necessaires ;
2. initialise les etats internes ;
3. cree les overlays dynamiques pour le cadre et les stickers ;
4. branche les ecouteurs d'evenements ;
5. charge les filtres ;
6. charge les montages deja crees par l'utilisateur ;
7. synchronise l'etat initial des boutons.

## Etats importants dans `studio.js`

- `stream` : flux camera actif.
- `uploadedImageUrl` : URL temporaire d'une image uploadee.
- `cropState` : donnees du rognage en cours.
- `selectedFrame` : cadre actif, limite a un seul cadre.
- `stickerInstances` : stickers ajoutes dans l'apercu, limite a 10.
- `layers` : ordre d'empilement des cadres/stickers, dans l'ordre d'ajout utilisateur.
- `activeStickerUid` : sticker actuellement selectionne/deplace/redimensionne/tourne.
- `createdPhotos` : montages de l'utilisateur affiches dans "Mes creations".

Les stickers sont stockes en pourcentages :

- `x`, `y` : position du centre dans le carre.
- `width` : largeur du sticker en pourcentage du carre.
- `rotation` : rotation en degres.

Ce choix evite que la taille de l'ecran influence le montage final.

## Chargement des filtres

La fonction `loadFilters()` fait un `fetch` sur :

```txt
/images/filters/filters.json
```

Chaque entree doit contenir au minimum :

```json
{
  "id": "mon-filtre",
  "name": "Nom visible",
  "file": "/images/filters/stickers/mon-filtre.png",
  "type": "sticker"
}
```

Types :

- `frame` : cadre fixe, plein carre, non deplacable.
- `sticker` : element deplacable, redimensionnable, rotatif, supprimable.

Les cadres sont ajoutes dans `#frameFiltersList`, les stickers dans `#stickerFiltersList`.

## Camera et capture

Le bouton `#startCamera` :

- verifie le contexte HTTPS/local ;
- demande `getUserMedia()` ;
- essaye d'abord `facingMode: "user"` ;
- retente avec `video: true` si necessaire ;
- stocke le flux dans `stream` et `window.currentStream`.

Le bouton `#capturePhoto` :

- verifie que la camera est active ;
- prend le plus grand carre possible dans le flux video ;
- dessine ce carre dans le canvas ;
- exporte en PNG via `canvas.toDataURL("image/png")` ;
- affiche l'image capturee dans `#capturedPhotoPreview`.

La capture est possible des que la camera est active, meme sans filtre. En revanche, la creation du montage final demande une image capturee/uploadee et au moins un cadre ou sticker.

## Upload et rognage

Le bouton `#uploadBtn` ouvre `#uploadPhoto`.

Formats acceptes cote front :

- PNG ;
- JPEG.

Limite actuelle :

- 10 Mo maximum.

Quand une image est uploadee :

- la camera active est stoppee ;
- une URL temporaire est creee avec `URL.createObjectURL()` ;
- la modale `#imageCropModal` s'ouvre ;
- le crop est force en carre ;
- l'utilisateur peut deplacer l'image et zoomer.

Validation du crop :

- le JS calcule la zone source dans les pixels de l'image originale ;
- il dessine cette zone dans le canvas ;
- il exporte une image PNG ;
- il affiche le resultat comme image capturee.

## Cadres et stickers

Cadres :

- un seul cadre maximum ;
- cliquer le meme cadre le retire ;
- choisir un autre cadre remplace l'ancien ;
- le cadre couvre toujours tout le carre (`100% x 100%`) ;
- il est envoye comme layer de type `frame`.

Stickers :

- 10 stickers maximum ;
- chaque clic sur un sticker du panneau ajoute une nouvelle instance ;
- chaque instance a une croix de suppression ;
- chaque instance a 4 poignees de redimensionnement ;
- chaque instance a une poignee de rotation ;
- les contours et poignees sont invisibles sauf au survol ou quand l'element est actif sur tactile.

Les fonctions principales :

- `addSticker(filter)` : cree une instance de sticker.
- `removeSticker(uid)` : supprime une instance.
- `createStickerBox(sticker, cropMode)` : cree le DOM d'un sticker.
- `applyStickerPosition(sticker, box)` : applique position, taille, rotation et z-index.
- `syncFilterOverlays()` : synchronise cadre, stickers, et etat visuel des boutons.

## Creation du montage

Le bouton `#createMontage` n'envoie pas directement le montage. Il ouvre `#hashtagsModal`.

Les mots-cles sont lus par `parseHashtags()` :

- separation par espaces ou virgules ;
- passage en minuscules francaises ;
- 25 caracteres maximum par mot-cle ;
- lettres et tirets uniquement ;
- pas de tiret seul ou en debut/fin, grace au motif `^\p{L}+(?:-\p{L}+)*$`.

Limite :

- 5 mots-cles uniques maximum.

Quand l'utilisateur valide, `createMontage()` appelle `buildCreatePayload()`, puis envoie :

```txt
POST /?page=photo_create
```

Payload simplifie :

```json
{
  "image": "data:image/png;base64,...",
  "frame_id": "id-du-cadre-ou-null",
  "crop": {
    "x_percent": 0,
    "y_percent": 0,
    "width_percent": 100,
    "height_percent": 100
  },
  "layers": [
    {
      "type": "frame",
      "frame_id": "leopard-frame"
    },
    {
      "type": "sticker",
      "sticker_id": "black-cat-sticker",
      "center_x_percent": 50,
      "center_y_percent": 50,
      "width_percent": 62,
      "rotation_degrees": 0
    }
  ],
  "hashtags": ["chat", "savane"]
}
```

Le backend cree le montage final, stocke l'image, puis renvoie `photo_id` et `path`. Le JS ajoute alors la miniature en tete de "Mes creations".

## Liste des montages utilisateur

Au chargement du studio, `loadUserPhotos()` appelle :

```txt
GET /?page=photo_mine
```

La reponse remplit `createdPhotos`, puis `renderCreatedPhotoItem()` cree les miniatures.

Chaque miniature :

- affiche l'image depuis `photo.path` ;
- ouvre `#photoViewerModal` au clic ;
- contient une icone poubelle.

Suppression :

```txt
POST /?page=photo_delete
```

Le JS demande confirmation avec `confirm()`, supprime la miniature du DOM et met a jour `createdPhotos`.

## Visionneuse de montage

La modale `#photoViewerModal` affiche la photo courante en grand.

Navigation :

- bouton precedent ;
- bouton suivant ;
- fleches clavier gauche/droite ;
- fermeture avec Escape ;
- fermeture avec la croix ;
- fermeture en cliquant hors de la modale.

L'image reste dans un cadre carre, sans deformation.

## Liens avec le CSS

Le CSS est essentiel pour que le studio reste utilisable.

Layout global :

- `.studio-workspace` organise les trois zones : filtres, apercu, creations.
- desktop : trois colonnes.
- mobile portrait : une colonne, ordre filtres -> apercu -> creations.
- mobile paysage : filtres et apercu cote a cote si possible, creations dessous.

Apercu central :

- `.studio-main > .savane-card` sert de container de mesure CSS.
- `.studio-capture-layout` calcule la plus grande taille possible du carre.
- `.studio-preview` garde `aspect-ratio: 1 / 1`.
- les boutons `.studio-tool-btn` restent dans une colonne minimale a droite.

Regle a respecter :

- ne pas fixer une taille arbitraire a `.studio-preview` par breakpoint ;
- changer plutot la taille du container parent ;
- laisser la formule CSS calculer le plus grand carre possible avec les marges minimales et la colonne de boutons.

Stickers :

- `.studio-sticker-box` est positionne en absolu dans le carre.
- les positions viennent du JS en pourcentages.
- `transform: translate(-50%, -50%) rotate(...)` permet de positionner le centre puis de tourner.
- les poignees `.studio-sticker-resize-*`, `.studio-sticker-rotate` et `.studio-sticker-remove` sont masquees par defaut et visibles au survol/focus.

Modales :

- `.studio-crop-modal` est l'overlay commun.
- `.studio-crop-frame` force le crop carre.
- `.studio-photo-viewer-frame` force l'affichage carre du montage.

## Points de vigilance avant modification

- Garder le preview, le crop, le montage final et la visionneuse en carre.
- Ne pas renommer un `id` dans `studio.php` sans mettre a jour `studio.js`.
- Les stickers doivent rester en pourcentages, pas en pixels.
- La rotation doit rester envoyee au backend via `rotation_degrees`.
- L'ordre des layers doit rester celui des ajouts utilisateur.
- Un seul cadre maximum doit etre actif.
- Les chemins des filtres doivent rester coherents entre `filters.json`, la base de donnees/init SQL et les fichiers reels.
- Les erreurs backend doivent renvoyer du JSON, sinon `studio.js` echouera sur `response.json()`.
