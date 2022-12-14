<?php

require_once(__DIR__ . '/../lib/readEnv.php');
require_once(__DIR__ . '/../lib/existUrl.php');

class GetHazard
{
    private string $area;
    private string $apiKey;
    private array $geocoding;

    public function __construct(private array $address)
    {
        $this->area = $this->address['prefectures'] . $this->address['municipalities'] . $this->address['street'] . $this->address['extendAddress'];
        $this->apiKey = readEnv()[5];
        $this->geocoding = $this->getGeocoding();
    }

    public function getGeocoding(): array
    {
        $geocodeApiUrl = "https://maps.googleapis.com/maps/api/geocode/json?key=" . $this->apiKey . '&address=' . urlencode($this->area);

        $geocodeJson = file_get_contents($geocodeApiUrl);
        $geocodeData = json_decode($geocodeJson, true);

        // 該当住所が見つからない時のエラー処理
        if ($geocodeData['status'] === 'ZERO_RESULTS') {
            return ['ZERO_RESULTS'];
        }

        $lon = $geocodeData["results"][0]["geometry"]["location"]["lng"];
        $lat = $geocodeData["results"][0]["geometry"]["location"]["lat"];

        return [
            'lon' => $lon,
            'lat' => $lat
        ];
    }

    public function getMaxDepth(): float|int|null
    {
        $query = http_build_query($this->geocoding, '', '&', PHP_QUERY_RFC3986);
        $url = 'https://suiboumap.gsi.go.jp/shinsuimap/Api/Public/GetMaxDepth?' . $query;

        $json = file_get_contents($url);
        $maxDepthInfo = json_decode($json, true);
        if ($maxDepthInfo === []) {
            return null;
        }
        return $maxDepthInfo['Depth'];
    }

    public function getBreakPoint(): array|bool
    {
        $query = http_build_query($this->geocoding, '', '&', PHP_QUERY_RFC3986);
        $url = 'https://suiboumap.gsi.go.jp/shinsuimap/Api/Public/GetBreakPoint?' . $query . '&returnparams=EntryRiverName';

        if (existUrl($url)) {
            $json = file_get_contents($url);
            $arr = json_decode($json, true);
            $breakPoint = array_unique(array_column($arr, 'EntryRiverName'));
            return $breakPoint;
        }
        return false;
    }

    public function evaluate(): array
    {
        // 該当住所が見つからない時のエラー処理
        if (in_array('ZERO_RESULTS', $this->geocoding) === true) {
            return ['ZERO_RESULTS'];
        }

        // 該当住所が見つかった時の処理
        $result = [];
        $result['category'] = '災害';

        if ($this->address['extendAddress'] === '') {
            $result['score'] = 0;
            $result['message'][] = '番地が入力されていないため、分析できませんでした。';
            return $result;
        }

        $maxDepth = $this->getMaxDepth();
        $result['statisticsData']['maxDepth'] = $maxDepth;


        if ($maxDepth === null) {
            $result['score'] = 2;
            $result['message'][] = 'この地域は浸水が想定されていない区域であるため、水害の可能性は低いです。まだシミュレーションデータが登録されていないだけの可能性もあるため、詳細は自治体のハザードマップをご確認ください。';
        } else {
            $result['score'] = 1;
            $result['message'][] = 'この地域は浸水想定区域です。この地点の洪水時の想定最大侵水深は' . $maxDepth . 'mです。';
        }
        return $result;
    }
}
