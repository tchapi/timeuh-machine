<?php

declare(strict_types=1);

namespace App\Service;

final class QobuzApi
{
    private const AUTH_URL = 'https://www.qobuz.com/signin/oauth2';

    private const TOKEN_ENDPOINT = 'https://www.qobuz.com/api.json/0.2/oauth2/token';
    private const PLAYLISTS_ENDPOINT = 'https://www.qobuz.com/api.json/0.2/playlist/getUserPlaylists?sort=updated_at&order=desc&offset=0'; // &limit=5
    private const PLAYLIST_TRACKS_ENDPOINT = 'https://www.qobuz.com/api.json/0.2/playlist/get';
    private const PLAYLIST_CREATE_ENDPOINT = 'https://www.qobuz.com/api.json/0.2/playlist/create';
    private const PLAYLIST_ADD_TRACKS_ENDPOINT = 'https://www.qobuz.com/api.json/0.2/playlist/addTracks';

    /**
     * @var string
     */
    private $app_id;

    /**
     * @var string
     */
    private $app_secret;

    /**
     * @var string
     */
    private $client_id;

    /**
     * @var string
     */
    private $client_secret;

    /**
     * @var string
     */
    private $redirect_uri;

    /**
     * @var string
     */
    private $access_token;

    /**
     * @var string
     */
    private $expire;

    public function __construct(string $app_id, string $app_secret, string $client_id, string $client_secret, string $redirect_uri)
    {
        $this->app_id = $app_id;
        $this->app_secret = $app_secret;
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;

        $this->redirect_uri = $redirect_uri;
        $this->access_token = null;
        $this->expire = 0;
    }

    public function getAuthorizeUrl(?array $options): string
    {
        $boilerplate = [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
        ];

        $params = array_merge($boilerplate, $options);

        return self::AUTH_URL.'?'.http_build_query($params);
    }

    public function requestAccessToken(string $code): void
    {
        $formData = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirect_uri,
        ];

        $curlHandler = curl_init();

        curl_setopt_array($curlHandler, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => self::TOKEN_ENDPOINT,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $formData,
        ]);

        $response = curl_exec($curlHandler);
        curl_close($curlHandler);

        if (!$response) {
            throw new \Exception('Qobuz API: Bad response');
        }

        $token_info = json_decode($response);

        if (is_null($token_info)) {
            throw new \Exception('Qobuz API: Could not parse response');
        }

        if (isset($token_info->status) && 'error' === $token_info->status) {
            throw new \Exception('Qobuz API: Error. '.$token_info->message);
        }

        $this->access_token = $token_info->access_token;
        $this->expire = new \DateTime('now + '.((int) $token_info->expires_in).'seconds');
    }

    public function getUserPlaylists(): \stdClass
    {
        if (!$this->access_token || $this->expire < new \DateTime()) {
            throw new \Exception('Qobuz API: Missing access token or token expired');
        }

        $curlHandler = curl_init();

        curl_setopt_array($curlHandler, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $this->createApiUri(self::PLAYLISTS_ENDPOINT),
            CURLOPT_HTTPHEADER => $this->createApiHeaders(),
        ]);

        $response = curl_exec($curlHandler);
        curl_close($curlHandler);

        if (!$response) {
            throw new \Exception('Qobuz API: Could not get user playlists');
        }

        $playlists = json_decode($response, true);

        if (!isset($playlists['playlists']) || !isset($playlists['playlists']['items'])) {
            throw new \Exception('Qobuz API: Missing playlists>items key in the playlists response');
        }

        // Conform to Spotify API structure
        $items = [];
        foreach ($playlists['playlists']['items'] as $playlist) {
            $item = new \stdClass();
            $item->id = strval($playlist['id']);
            $item->name = $playlist['name'];
            $items[] = $item;
        }

        return (object) [
            'items' => $items,
        ];
    }

    public function createPlaylist(array $params): \stdClass
    {
        if (!$this->access_token || $this->expire < new \DateTime()) {
            throw new \Exception('Qobuz API: Missing access token or token expired');
        }

        $queryParams = ['name' => $params['name'], 'is_public' => false];

        $curlHandler = curl_init();
        curl_setopt_array($curlHandler, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST => 1,
            CURLOPT_URL => $this->createApiUri(self::PLAYLIST_CREATE_ENDPOINT, $queryParams),
            CURLOPT_HTTPHEADER => $this->createApiHeaders(),
        ]);

        $response = curl_exec($curlHandler);
        curl_close($curlHandler);

        if (!$response) {
            throw new \Exception('Qobuz API: Could not create playlist');
        }

        $result = json_decode($response, true);

        // Conform to Spotify API structure
        $playlist = new \stdClass();
        $playlist->id = strval($result['id']);

        return $playlist;
    }

    public function getPlaylistTracks(string $playlistId): \stdClass
    {
        if (!$this->access_token || $this->expire < new \DateTime()) {
            throw new \Exception('Qobuz API: Missing access token or token expired');
        }

        $queryParams = ['playlist_id' => $playlistId, 'extra' => 'tracks', 'limit' => 3000];

        $curlHandler = curl_init();
        curl_setopt_array($curlHandler, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $this->createApiUri(self::PLAYLIST_TRACKS_ENDPOINT, $queryParams),
            CURLOPT_HTTPHEADER => $this->createApiHeaders(),
        ]);

        $response = curl_exec($curlHandler);
        curl_close($curlHandler);

        if (!$response) {
            throw new \Exception('Qobuz API: Could not get tracks');
        }

        $tracks = json_decode($response, true);

        if (!isset($tracks['tracks']) || !isset($tracks['tracks']['items'])) {
            throw new \Exception('Qobuz API: Missing tracks>items key in the tracks response');
        }

        // Conform to Spotify API structure
        $items = [];
        foreach ($tracks['tracks']['items'] as $track) {
            $item = new \stdClass();
            $item->track = new \stdClass();
            $item->track->uri = strval($track['id']);
            $items[] = $item;
        }

        return (object) [
            'items' => $items,
        ];
    }

    public function addPlaylistTracks(string $playlistId, array $tracks): void
    {
        if (!$this->access_token || $this->expire < new \DateTime()) {
            throw new \Exception('Qobuz API: Missing access token or token expired');
        }

        $queryParams = ['playlist_id' => $playlistId, 'track_ids' => implode(',', $tracks)];

        $curlHandler = curl_init();
        curl_setopt_array($curlHandler, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST => 1,
            CURLOPT_URL => $this->createApiUri(self::PLAYLIST_ADD_TRACKS_ENDPOINT, $queryParams),
            CURLOPT_HTTPHEADER => $this->createApiHeaders(),
        ]);

        $response = curl_exec($curlHandler);
        curl_close($curlHandler);

        if (!$response) {
            throw new \Exception('Qobuz API: Could not add tracks to playlist');
        }
    }

    private function createApiUri(string $endpoint, array $params = [])
    {
        return $endpoint.'?'.http_build_query($params);
    }

    private function createApiHeaders()
    {
        return [
            'Content-Type: application/json',
            'X-App-Id: '.$this->app_id,
            'Authorization: Bearer '.$this->access_token,
        ];
    }
}
