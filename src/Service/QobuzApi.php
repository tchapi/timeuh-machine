<?php

declare(strict_types=1);

namespace App\Service;

final class QobuzApi
{
    private const AUTH_URL = 'https://www.qobuz.com/signin/oauth2';

    private const TOKEN_ENDPOINT = 'https://www.qobuz.com/api.json/0.2/oauth2/token';
    private const PLAYLISTS_ENDPOINT = 'https://www.qobuz.com/api.json/0.2/playlist/getUserPlaylists?sort=updated_at&order=desc&offset=0'; // &limit=5
    private const PLAYLISTS_TRACKS_ENDPOINT = 'https://www.qobuz.com/api.json/0.2/playlist/get?playlist_id=%s&extra=tracks'; // &limit=2

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
            CURLOPT_POSTFIELDS => $formData
        ]);

        $response = curl_exec($curlHandler);
        curl_close($curlHandler);

        if (!$response) {
            throw new \Exception('Qobuz API: Bad response');
        }

        $token_info = json_decode($response);

        if (is_null($token_info) || $token_info->status === "error") {
            throw new \Exception('Qobuz API: Could not parse response');
        }

        if ($token_info->status === "error") {
            throw new \Exception('Qobuz API: Error. '.$token_info->message);
        }

        $this->access_token = $token_info->access_token;
        $this->expire = new \DateTime('now + '.((int) $token_info->expires_in).'seconds');
    }

    public function getUserPlaylists()
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

        // Conform to Spotify API structure
        $items = [];
        foreach ($playlists['data'] as $playlist) {
            $item = new \stdClass();
            $item->id = strval($playlist['id']);
            $item->name = $playlist['title'];
            $items[] = $item;
        }

        return (object) [
            'items' => $items,
        ];
    }

    public function createPlaylist(array $params)
    {
        if (!$this->access_token || $this->expire < new \DateTime()) {
            throw new \Exception('Qobuz API: Missing access token or token expired');
        }

        $curlHandler = curl_init();

        curl_setopt_array($curlHandler, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST => 1,
            CURLOPT_URL => $this->createApiUri(self::PLAYLISTS_ENDPOINT),
            CURLOPT_POSTFIELDS => http_build_query(['title' => $params['name']]),
            CURLOPT_HTTPHEADER => $this->createApiHeaders(),
        ]);

        $response = curl_exec($curlHandler);
        curl_close($curlHandler);

        if (!$response) {
            throw new \Exception('Qobuz API: Could not create playlist');
        }

        $playlist = json_decode($response);

        // Conform to Spotify API structure
        $playlist->id = strval($playlist->id);

        return $playlist;
    }

    public function getPlaylistTracks(string $playlistId)
    {
        if (!$this->access_token || $this->expire < new \DateTime()) {
            throw new \Exception('Qobuz API: Missing access token or token expired');
        }

        $endpoint = str_replace('%s', $playlistId, self::PLAYLISTS_TRACKS_ENDPOINT);

        $curlHandler = curl_init();

        curl_setopt_array($curlHandler, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $this->createApiUri($endpoint),
            CURLOPT_HTTPHEADER => $this->createApiHeaders(),
        ]);

        $response = curl_exec($curlHandler);
        curl_close($curlHandler);

        if (!$response) {
            throw new \Exception('Qobuz API: Could not get tracks');
        }

        $tracks = json_decode($response, true);

        // Conform to Spotify API structure
        $items = [];
        foreach ($tracks['data'] as $track) {
            $item = new \stdClass();
            $item->track = new \stdClass();
            $item->track->uri = $track['id'];
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

        $tracks_param = implode(',', $tracks);
        $endpoint = str_replace('%s', $playlistId, self::PLAYLISTS_TRACKS_ENDPOINT);

        $curlHandler = curl_init();

        curl_setopt_array($curlHandler, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST => 1,
            CURLOPT_URL => $this->createApiUri($endpoint),
            CURLOPT_POSTFIELDS => http_build_query(['songs' => $tracks_param]),
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
            'Authorization: Bearer '.$this->access_token
        ];
    }
}
