<?php

namespace App\Service;

final class DeezerApi
{
    const AUTH_URL = 'https://connect.deezer.com/oauth/auth.php';

    const TOKEN_ENDPOINT = 'https://connect.deezer.com/oauth/access_token.php';
    const PLAYLISTS_ENDPOINT = 'https://api.deezer.com/user/me/playlists';
    const PLAYLISTS_TRACKS_ENDPOINT = 'https://api.deezer.com/playlist/%s/tracks';

    /**
     * @var string
     */
    private $app_id;

    /**
     * @var string
     */
    private $secret;

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

    public function __construct(string $app_id, string $secret, string $redirect_uri)
    {
        $this->app_id = $app_id;
        $this->secret = $secret;
        $this->redirect_uri = $redirect_uri;
        $this->access_token = null;
        $this->expire = 0;
    }

    public function getAuthorizeUrl(?array $options): string
    {
        $boilerplate = [
            'app_id' => $this->app_id,
            'redirect_uri' => $this->redirect_uri,
        ];

        $params = array_merge($boilerplate, $options);

        return self::AUTH_URL.'?'.http_build_query($params);
    }

    private function createApiUri(string $endpoint)
    {
        $auth = [
            'access_token' => $this->access_token,
        ];

        return $endpoint.'?'.http_build_query($auth);
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

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => self::TOKEN_ENDPOINT.'?'.http_build_query($params),
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response || 'wrong code' === $response) {
            throw new \Exception('Bad response from Deezer API');
        }

        $token_info = json_decode($response);

        if (is_null($token_info)) {
            throw new \Exception('Could not parse response from Deezer API');
        }

        $this->access_token = $token_info->access_token;
        $this->expire = new \DateTime('now + '.((int) $token_info->expires).'seconds');
    }

    public function getUserPlaylists()
    {
        if (!$this->access_token || $this->expire < new \DateTime()) {
            throw new \Exception('Missing access token or token expired');
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $this->createApiUri(self::PLAYLISTS_ENDPOINT),
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            throw new \Exception('Could not get user playlists from Deezer API');
        }

        $playlists = json_decode($response);

        return $playlists;
    }

    public function createPlaylist(array $params)
    {
        if (!$this->access_token || $this->expire < new \DateTime()) {
            throw new \Exception('Missing access token or token expired');
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST => 1,
            CURLOPT_URL => $this->createApiUri(self::PLAYLISTS_ENDPOINT),
            CURLOPT_POSTFIELDS => http_build_query(['title' => $params['title']]),
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            throw new \Exception('Could not create playlist from Deezer API');
        }

        $data = json_decode($response);

        return $data;
    }

    public function getPlaylistTracks(string $playlistId)
    {
        if (!$this->access_token || $this->expire < new \DateTime()) {
            throw new \Exception('Missing access token or token expired');
        }

        $endpoint = str_replace('%s', $playlistId, self::PLAYLISTS_TRACKS_ENDPOINT);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $this->createApiUri($endpoint),
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            throw new \Exception('Could not get playlist tracks from Deezer API');
        }

        $tracks = json_decode($response);

        return $tracks;
    }

    public function addPlaylistTracks(string $playlistId, array $tracks)
    {
        if (!$this->access_token || $this->expire < new \DateTime()) {
            throw new \Exception('Missing access token or token expired');
        }

        $tracks_param = implode(',', $tracks);
        $endpoint = str_replace('%s', $playlistId, self::PLAYLISTS_TRACKS_ENDPOINT);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST => 1,
            CURLOPT_URL => $this->createApiUri($endpoint),
            CURLOPT_POSTFIELDS => http_build_query(['songs' => $tracks_param]),
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            throw new \Exception('Could not add tracks to playlist from Deezer API');
        }
    }
}
