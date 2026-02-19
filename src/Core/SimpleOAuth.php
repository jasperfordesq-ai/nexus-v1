<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

class SimpleOAuth
{
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $provider; // 'google', 'microsoft'

    public function __construct($provider, $config)
    {
        $this->provider = $provider;
        $this->clientId = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
        $this->redirectUri = $config['redirect_uri'];
    }

    public function getAuthUrl()
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;

        if ($this->provider === 'google') {
            return "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
                'client_id' => $this->clientId,
                'redirect_uri' => $this->redirectUri,
                'response_type' => 'code',
                'scope' => 'email profile openid',
                'state' => $state,
                'access_type' => 'offline'
            ]);
        }

        // Add Microsoft later if needed
        return '#';
    }

    public function getAccessToken($code)
    {
        $url = ($this->provider === 'google')
            ? 'https://oauth2.googleapis.com/token'
            : '';

        $postData = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    public function getUserInfo($accessToken)
    {
        if ($this->provider === 'google') {
            $url = 'https://www.googleapis.com/oauth2/v3/userinfo';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);
            return [
                'id' => $data['sub'] ?? null,
                'email' => $data['email'] ?? null,
                'first_name' => $data['given_name'] ?? '',
                'last_name' => $data['family_name'] ?? '',
                'avatar' => $data['picture'] ?? ''
            ];
        }
        return null;
    }
}
