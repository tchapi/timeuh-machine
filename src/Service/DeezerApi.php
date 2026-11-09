<?php

declare(strict_types=1);

namespace App\Service;

final class DeezerApi
{
    private const AUTH_URL = 'https://connect.deezer.com/oauth/auth.php';

    private const TOKEN_ENDPOINT = 'https://connect.deezer.com/oauth/access_token.php';
    private const PLAYLISTS_ENDPOINT = 'https://api.deezer.com/user/me/playlists';
    private const PLAYLISTS_TRACKS_ENDPOINT = 'https://api.deezer.com/playlist/%s/tracks';

    private ?string $access_token = null;

    private ?\DateTime $expire = null;

    public function __construct(
        private readonly string $app_id,
        private readonly string $secret,
        private readonly string $redirect_uri,
    ) {
    }

    /**
     * @param array<string, mixed>|null $options
     */
    public function getAuthorizeUrl(?array $options): string
    {
        $boilerplate = [
            'app_id' => $this->app_id,
            'redirect_uri' => $this->redirect_uri,
        ];

        $params = array_merge($boilerplate, $options ?? []);

        return self::AUTH_URL.'?'.http_build_query($params);
    }

    public function requestAccessToken(string $code): void
    {
        $boilerplate = [
            'app_id' => $this->app_id,
            'secret' => $this->secret,
        ];

        $params = array_merge($boilerplate, [
            'code' => $code,
            'output' => 'json',
        ]);

        $curlHandler = curl_init();

        curl_setopt_array($curlHandler, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => self::TOKEN_ENDPOINT.'?'.http_build_query($params),
        ]);

        $response = curl_exec($curlHandler);
        curl_close($curlHandler);

        if (!$response || 'wrong code' === $response) {
            throw new \Exception('Deezer API: Bad response');
        }

        $token_info = json_decode($response);

        if (is_null($token_info)) {
            throw new \Exception('Deezer API: Could not parse response');
        }

        $this->access_token = $token_info->access_token;
        $this->expire = new \DateTime('now + '.((int) $token_info->expires).'seconds');
    }

    private function assertAuthenticated(): void
    {
        if (null === $this->access_token || '' === $this->access_token || null === $this->expire || $this->expire < new \DateTime()) {
            throw new \Exception('Deezer API: Missing access token or token expired');
        }
    }

    public function getUserPlaylists(): \stdClass
    {
        $this->assertAuthenticated();

        $curlHandler = curl_init();

        curl_setopt_array($curlHandler, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $this->createApiUri(self::PLAYLISTS_ENDPOINT),
        ]);

        $response = curl_exec($curlHandler);
        curl_close($curlHandler);

        if (!$response) {
            throw new \Exception('Deezer API: Could not get user playlists');
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

    /**
     * @param array{name: string} $params
     */
    public function createPlaylist(array $params): \stdClass
    {
        $this->assertAuthenticated();

        $curlHandler = curl_init();

        curl_setopt_array($curlHandler, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_URL => $this->createApiUri(self::PLAYLISTS_ENDPOINT),
            CURLOPT_POSTFIELDS => http_build_query(['title' => $params['name']]),
        ]);

        $response = curl_exec($curlHandler);
        curl_close($curlHandler);

        if (!$response) {
            throw new \Exception('Deezer API: Could not create playlist');
        }

        $playlist = json_decode($response);

        // Conform to Spotify API structure
        $playlist->id = strval($playlist->id);

        return $playlist;
    }

    public function getPlaylistTracks(string $playlistId): \stdClass
    {
        $this->assertAuthenticated();

        $endpoint = str_replace('%s', $playlistId, self::PLAYLISTS_TRACKS_ENDPOINT);

        $curlHandler = curl_init();

        curl_setopt_array($curlHandler, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $this->createApiUri($endpoint, ['limit' => 3000]),
        ]);

        $response = curl_exec($curlHandler);
        curl_close($curlHandler);

        if (!$response) {
            throw new \Exception('Deezer API: Could not get tracks');
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

    /**
     * @param list<string> $tracks
     */
    public function addPlaylistTracks(string $playlistId, array $tracks): void
    {
        $this->assertAuthenticated();

        $tracks_param = implode(',', $tracks);
        $endpoint = str_replace('%s', $playlistId, self::PLAYLISTS_TRACKS_ENDPOINT);

        $curlHandler = curl_init();

        curl_setopt_array($curlHandler, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_URL => $this->createApiUri($endpoint),
            CURLOPT_POSTFIELDS => http_build_query(['songs' => $tracks_param]),
        ]);

        $response = curl_exec($curlHandler);
        curl_close($curlHandler);

        if (!$response) {
            throw new \Exception('Deezer API: Could not add tracks to playlist');
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function createApiUri(string $endpoint, array $params = []): string
    {
        $query = array_merge([
            'access_token' => $this->access_token,
        ], $params);

        return $endpoint.'?'.http_build_query($query);
    }
}
