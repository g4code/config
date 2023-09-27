<?php

namespace G4\Config;

class VaultRepository
{
    /**
     * @var mixed
     */
    private $vaultUrl;
    /**
     * @var mixed
     */
    private $vaultApiToken;

    /**
     * @param string $vaultUrl
     * @param string $vaultApiToken
     */
    public function __construct(string $vaultUrl, string $vaultApiToken)
    {
        $this->vaultUrl = $vaultUrl;
        $this->vaultApiToken = $vaultApiToken;
    }

    public function getValueBySection($section)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->vaultUrl . '/v1/' . $section);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-Vault-Token: " . $this->vaultApiToken,
            "X-Vault-Namespace: " . $this->vaultUrl
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        } else {
            $json = json_decode($response, true);
            $param = $json['data'];
        }

        curl_close($ch);

        return $param;
    }
}
