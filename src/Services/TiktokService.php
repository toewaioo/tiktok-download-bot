<?php

namespace TikTokDownloadBot\Services;

class TikTokService
{
    public function getData($tiktokUrl)
    {
        $apiEndpoint = 'https://tikwm.com/api/';
        $params = ['url' => $tiktokUrl, 'hd' => 1];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiEndpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            error_log("cURL Error (TikTok API): " . curl_error($ch));
            curl_close($ch);
            return false;
        }

        curl_close($ch);
        $data = json_decode($response, true);

        if (isset($data['code']) && $data['code'] === 0 && isset($data['data'])) {
            return $data['data'];
        }

        error_log("TikTok API response error: " . ($data['msg'] ?? 'Unknown error'));
        return false;
    }
}
