<?php
require 'vendor/autoload.php';
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
class FeishuAPI
{
    const API_KEY = "文心一言appid";
    const SECRET_KEY = "文心一言密钥";
    private $app_id;
    private $app_secret;

    /**
     * @param $app_id
     * @param $app_secret
     */
    public function __construct($app_id, $app_secret)
    {
        $this->app_id = $app_id;
        $this->app_secret = $app_secret;
    }

    /**
     * 使用 AK，SK 生成鉴权签名（Access Token）
     * @return string 鉴权签名信息（Access Token）
     */

    /**
     * 文言一心接口
     * @param $message
     * @return bool|string
     */
    public function run($message) {
        $curl = curl_init();

        $me1 = [
            "messages"=>[
                [
                    'role'=>'user',
                    'content'=>$message
                ]
            ]
        ];
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop/chat/eb-instant?access_token={$this->getAccessToken()}",
            CURLOPT_TIMEOUT => 30,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST  => false,
            CURLOPT_CUSTOMREQUEST => 'POST',

            CURLOPT_POSTFIELDS =>json_encode($me1),

            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),

        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    /**
     * 飞书接口
     * @return mixed
     */
    private function getTenantAccessToken()
    {
        $client = new Client();
        $data = [
            "app_id" => $this->app_id,
            "app_secret" => $this->app_secret
        ];

        $response = $client->request('POST', 'https://open.feishu.cn/open-apis/auth/v3/tenant_access_token/internal', ['json' => $data]);
        $jsonToArray = json_decode($response->getBody()->getContents(), true);

        return $jsonToArray['tenant_access_token'];
    }

    private function getAccessToken(){
        $curl = curl_init();
        $postData = array(
            'grant_type' => 'client_credentials',
            'client_id' => self::API_KEY,
            'client_secret' => self::SECRET_KEY
        );
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://aip.baidubce.com/oauth/2.0/token',
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST  => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => http_build_query($postData)
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $rtn = json_decode($response);
        return $rtn->access_token;
    }

    /**
     * 飞书回调接口
     * @return void
     */
    public function replyUser()
    {
        $rawData = file_get_contents('php://input');
        $Server_data = json_decode($rawData, true);

        $usertext = json_decode($Server_data['event']['message']['content'],true);

        $tent_token = $this->getTenantAccessToken();

        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
            'Authorization' => 'Bearer ' . $tent_token
        ];
        $WenXinAi = json_decode($this->run($usertext['text']),true);
         $data = [
             'content' => json_encode([
                 'text' =>$WenXinAi['result']."\n\n本次提问消费如下：".$WenXinAi['usage']['total_tokens']
             ]),
             'msg_type' => 'text'
         ];


        $client = new Client();
        $response = $client->request('POST', "https://open.feishu.cn/open-apis/im/v1/messages/" . $Server_data['event']['message']['message_id'] . "/reply", [
            'json' => $data,
            'headers' => $headers
        ]);
    }
}

$app_id = '飞书appid';
$app_secret = '飞书密钥';
$feishuAPI = new FeishuAPI($app_id, $app_secret);
$feishuAPI->replyUser();

