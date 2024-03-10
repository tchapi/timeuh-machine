This is the Timeuh Machine
===

  <h3>Vous aimez RadioMeuh ? Moi oui 😍 !</h3>

  <p>Et comme ils passent quotidiennement du son de <strong>très bonne qualité</strong>, j'ai voulu sauvegarder leurs choix musicaux et permettre à tout le monde de retrouver les titres passés sur la radio, en dehors des dix derniers titres déjà disponibles (<a href="http://player.radiomeuh.com/playlist/">ici</a>).</p>

  <p>Pour chaque titre, une recherche est faite sur <a href="https://tuneefy.com" target="_blank">Tuneefy</a> pour trouver la piste sur les plateformes de streaming, vente en ligne et scrobbling actuelles (<em>Tuneefy supporte actuellement les services suivants : Spotify, Deezer, Last.fm, Soundcloud, Tidal, Groove Music, Amazon Music, Youtube, Mixcloud, iTunes, Qobuz, Napster, Google Play Music</em>).</p>

  <p>Chaque titre renvoie également sur une page <a href="https://musicbrainz.org/" target="_blank">MusicBrainz</a> de recherche sur l'artiste, si jamais vous voulez en savoir plus.</p>

  <p>Si le titre est trouvé, la pochette est ajoutée, ainsi qu'un lien direct vers la page de partage Tuneefy — <strong>super pratique</strong> !</p>

  <h3>Filtres</h3>

  <p>Les podcasts ou émissions ne sont pas prises en compte sur <a href="http://timeuh-machine.com">timeuh-machine.com</a>, cela n'aurait pas trop de sens - je me concentre sur les titres musicaux. Mais retrouvez tous les podcasts de RadioMeuh <a href="http://www.radiomeuh.com/category/podcasts/">là</a>.</p>

  <h3>Archives</h3>

  <p>Les archives contiennent la liste de tous les titres, par année, mois et par jour. Il y en a beaucoup, donc la navigation est un peu fastidieuse pour le moment.</p>

  <h3>Playlist Spotify automatiques</h3>

  <p>Pour chaque mois et chaque jour dans les archives, vous avez la possibilité de créer automatiquement <strong>la playlist Spotify correspondante</strong>. Tous les titres ne sont pas forcément présents : seuls ceux dont une version a été trouvée sur Spotify seront ajoutés, bien évidemment.</p>

  <p>Intelligent : si vous créez la playlist du mois de Septembre en cours de mois, vous pouvez recliquer sur le bouton par la suite : cela ajoutera les pistes manquantes sans créer de doublons dans votre playlist ! Wow !</p>

  <h3>Légal</h3>

  <p>Tu es de RadioMeuh ? J'ai fait un truc qui fallait pas faire ? Vraiment le logo ca va pas du tout, <strong>je dois l'enlever</strong> ? Les huissiers arrivent ? Aucun souci, je suis dispo pour en parler : <a href="mailto:tchap[at]tchap[dot]me">mail</a></p>

 - - -

  <h3>Développement</h3>

  Tu veux me donner un coup de main pour améliorer cet outil ? Rien de plus simple, clone le repo et :

      composer install
      php -S localhost:9000 -t public
      # L'interface est maintenant dispo à http://localhost:9000

Basé sur [Symfony 7](https://symfony.com/).

Toutes les pull requests sont les bienvenues !
