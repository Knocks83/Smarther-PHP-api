<?php

class Smarther
{
    /**
     * Send a request via cURL
     * 
     * @param String $url The URL to send the request to
     * @param Array $data (optional) The POST data to send
     * 
     * @return String|bool The cURL response
     */
    private function Request(String $url, Array $data = [])
    {
        if ($data == []) {
            curl_setopt($this->curl, CURLOPT_POST, false);
        } else {
            curl_setopt_array($this->curl, [
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => $data,
            ]);
        }
        curl_setopt_array($this->curl, [
            CURLOPT_URL        => $url,
        ]);

        return curl_exec($this->curl);
    }

    /**
     * Refresh the config file with the new added data
     */
    private function refreshConfig()
    {
        file_put_contents($this->configPath, json_encode($this->config, JSON_PRETTY_PRINT));
    }

    /**
     * Create the Smarther object
     * 
     * @param String $configPath The path for the json config file
     */
    public function __construct(String $configPath)
    {
        global $api, $oauth;
        $this->configPath = $configPath;
        $this->config = json_decode(file_get_contents($configPath));
        $this->authEndpoint = 'https://partners-login.eliotbylegrand.com/';
        $this->apiEndpoint = 'https://api.developer.legrand.com/smarther/v2.0/';

        $this->curl = curl_init();
        curl_setopt_array($this->curl, [
            CURLOPT_FORBID_REUSE   => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        if (!property_exists($this->config, 'refreshToken') || is_null($this->config->refreshToken)) {
            $authCode = $this->getAuthCode();
            $temp = $this->getRefreshToken($authCode);
            $this->config->refreshToken = $temp->refresh_token;
            $this->accessToken = $temp->access_token;

            $this->refreshConfig();
        } else {
            $temp = $this->refreshTokenFlow();
            $this->config->refreshToken = $temp->refresh_token;
            $this->accessToken = $temp->access_token;
        }

        curl_setopt($this->curl, CURLOPT_HTTPHEADER, array(
            'Ocp-Apim-Subscription-Key: ' . $this->config->subscriptionKey,
            'Authorization: Bearer ' . $this->accessToken,
        ));
    }

    // Unused but left because the docs use it
    /**
     * Set the auth endpoint
     */
    /*
    private function refreshAuthEndpoints() {
        $response = json_decode($this->Request('https://login.eliotbylegrand.com/0d8816d5-3e7f-4c86-8229-645137e0f222/v2.0/.well-known/openid-configuration?p=B2C_1_Eliot-SignUpOrSignIn'));
        $this->authEndpoint = $response->authorization_endpoint;
    }*/

    /**
     * Get the authorization code
     * 
     * @return String The Authorization code
     */
    private function getAuthCode()
    {
        // The Auth token expires if the application doesn't connect for 90 days
        $params = 'authorize?client_id=' . $this->config->clientId . '&response_type=code&redirect_uri=https://www.google.com';

        print($this->authEndpoint . $params);
        $code = readline(PHP_EOL . 'The link above will redirect you to the login page, when you log in it should redirect you to your redirect url?code=<something>. Type in that something.' . PHP_EOL);
        return $code;
    }

    /**
     * Get the refresh token
     * 
     * @param String $authCode The authorization code
     * 
     * @return mixed The JSON-decoded response
     */
    private function getRefreshToken(String $authCode)
    {
        $json = $this->Request($this->authEndpoint . 'token', [
            'client_id' => $this->config->clientId,
            'grant_type' => 'authorization_code',
            'code' => $authCode,
            'client_secret' => $this->config->clientSecret
        ]);

        return json_decode($json);
    }

    /**
     * Refresh the access token and refresh token
     * 
     * @return mixed The JSON-decoded response
     */
    private function refreshTokenFlow()
    {
        $params = 'token';
        $json = $this->Request($this->authEndpoint . $params, [
            'client_id' => $this->config->clientId,
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->config->refreshToken,
            'client_secret' => $this->config->clientSecret
        ]);

        return json_decode($json);
    }

    /**
     * Get list of plants associated with the account
     * 
     * @return mixed An object containing the plants
     */
    public function getPlants()
    {
        $json = $this->Request($this->apiEndpoint . 'plants');

        $response = json_decode($json);
        if (isset($response->statusCode)) {
            if ($response->statusCode == 200) {
                return $response->plants;
            } else {
                throw new Exception($response->message, $response->statusCode);
            }
        } else {
            return $response;
        }
    }

    /**
     * Get the topology of a given plant
     * 
     * @param String $plantId The plant of which to check the topology of
     * 
     * @return mixed An object containing the list of the modules in the plant
     */
    public function getPlantTopology(String $plantId)
    {

        $json = $this->Request($this->apiEndpoint . 'plants/' . $plantId . '/topology');

        $response = json_decode($json);
        if (isset($response->statusCode)) {
            if ($response->statusCode == 200) {
                return $response->plant;
            } else {
                throw new Exception($response->message, $response->statusCode);
            }
        } else {
            return $response;
        }
    }

    /**
     * Get the current humidity and temperature of a module
     * 
     * @param String $plantId The plant where to get the module from
     * @param String $moduleId The module where to get the data
     * 
     * @return mixed Object containing the humidity and the temperature measured by the module
     */
    function getDeviceMeasures(String $plantId, String $moduleId)
    {
        $json = $this->Request($this->apiEndpoint . 'chronothermostat/thermoregulation/addressLocation/plants/' . $plantId . '/modules/parameter/id/value/' . $moduleId . '/measures');

        $response = json_decode($json);

        if (isset($response->statusCode)) {
            if ($response->statusCode == 200) {
                return $response;
            } else {
                throw new Exception($response->message, $response->statusCode);
            }
        } else {
            return $response;
        }
    }
}
