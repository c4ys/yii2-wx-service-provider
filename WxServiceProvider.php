<?php

namespace c4ys\yii2wxserviceprovider;

use Yii;
use yii\base\BaseObject;
use c4ys\yii2wxserviceprovider\sdk\WXBizMsgCrypt;
use yii\httpclient\Client;
use yii\web\Response;

class WxServiceProvider extends BaseObject
{
    public $token;
    public $encodingAesKey;
    public $appId;
    public $appSecret;

    /**
     * http客户端
     * @var Client $httpClient
     */
    public $httpClient;

    public $httpConf = [
        'transport' => 'yii\httpclient\CurlTransport',
    ];
    /**
     * @var WXBizMsgCrypt $WXBizMsgCrypt ;
     */
    protected $WXBizMsgCrypt;

    public function init()
    {
        $this->WXBizMsgCrypt = new WXBizMsgCrypt($this->token, $this->encodingAesKey, $this->appId);
        $this->httpClient = new Client($this->httpConf);
        parent::init();
    }

    public function setComponentVerifyTicket($ticket, $expire = 3600)
    {
        $key = 'component_verify_ticket_' . $this->appId;
        return Yii::$app->cache->set($key, $ticket, $expire);
    }

    public function getComponentVerifyTicket()
    {
        $key = 'component_verify_ticket_' . $this->appId;
        return Yii::$app->cache->get($key);
    }

    public function setComponentAccessToken($ticket, $expire = 3600)
    {
        $key = 'component_access_token_' . $this->appId;
        return Yii::$app->cache->set($key, $ticket, $expire);
    }

    public function getComponentAccessToken()
    {
        $key = 'component_access_token_' . $this->appId;
        $token = Yii::$app->cache->get($key);
        if (!$token) {
            $url = 'https://api.weixin.qq.com/cgi-bin/component/api_component_token';
            $data = [
                "component_appid" => $this->appId,
                "component_appsecret" => $this->appSecret,
                "component_verify_ticket" => $this->getComponentVerifyTicket(),
            ];
            $response = $this->httpClient->post($url, $data)->send();
            $data = $response->setFormat(Client::FORMAT_JSON)->getData();
            if ($data) {
                $token = $data['component_access_token'];
                $this->setComponentAccessToken($token, $data['expires_in'] - 600);
            }
        }
        return $token;
    }

    public function getPreAuthCode()
    {
        $key = 'pre_auth_code_' . $this->appId;
        $pre_auth_code = Yii::$app->cache->get($key);
        if (!$pre_auth_code) {
            $url = 'https://api.weixin.qq.com/cgi-bin/component/api_create_preauthcode?component_access_token=' . $this->getComponentAccessToken();
            $data = [
                "component_appid" => $this->appId,
            ];
            $response = $this->httpClient->post($url, $data)->send();
            $data = $response->setFormat(Client::FORMAT_JSON)->getData();
            if ($data) {
                $pre_auth_code = $data['pre_auth_code'];
                Yii::$app->cache->set($key, $pre_auth_code, $data['expires_in'] - 600);
            }
        }
        return $pre_auth_code;
    }

    public function getMobileBindLink($redirect, $auth_type = 3, $biz_appid = null)
    {
        $params = [
            'auth_type' => $auth_type,
            'component_appid' => $this->appId,
            'pre_auth_code' => $this->getPreAuthCode(),
            'biz_appid' => $biz_appid,
            'redirect' => $redirect,
        ];
        if ($biz_appid) {
            $params['biz_appid'] = $biz_appid;
        }
        $query = http_build_query($params);
        $url = "https://mp.weixin.qq.com/safe/bindcomponent?action=bindcomponent&" . $query . "#wechat_redirect";
        return $url;
    }


    public function getPcBindLink($redirect, $auth_type = 3, $biz_appid = null)
    {
        $params = [
            'auth_type' => $auth_type,
            'component_appid' => $this->appId,
            'pre_auth_code' => $this->getPreAuthCode(),
            'biz_appid' => $biz_appid,
            'redirect_uri' => $redirect,
        ];
        if ($biz_appid) {
            $params['biz_appid'] = $biz_appid;
        }
        $query = http_build_query($params);
        $url = "https://mp.weixin.qq.com/cgi-bin/componentloginpage?" . $query;
        return $url;
    }

    /**
     * 使用授权码换取公众号或小程序的接口调用凭据和授权信息
     * @param $auth_code 授权码
     * @return mixed
     * @throws \yii\httpclient\Exception
     */
    public function queryAuth($auth_code)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/component/api_query_auth?component_access_token=' . $this->getComponentAccessToken();
        $data = [
            "component_appid" => $this->appId,
            "authorization_code" => $auth_code,
        ];
        $response = $this->httpClient->post($url, $data)->send();
        return $response->setFormat(Client::FORMAT_JSON)->getData();
    }

    /**
     * 获取（刷新）授权公众号或小程序的接口调用凭据（令牌）
     * @param $authorizer_appid
     * @param $authorizer_refresh_token
     * @return mixed
     * @throws \yii\httpclient\Exception
     */
    public function authorizerToken($authorizer_appid, $authorizer_refresh_token)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/component/api_authorizer_token?component_access_token=' . $this->getComponentAccessToken();
        $data = [
            "component_appid" => $this->appId,
            "authorizer_appid" => $authorizer_appid,
            "authorizer_refresh_token" => $authorizer_refresh_token,
        ];
        $response = $this->httpClient->post($url, $data)->send();
        return $response->setFormat(Client::FORMAT_JSON)->getData();
    }

    /**
     * 获取授权方的帐号基本信息
     * @param $authorizer_appid
     * @return mixed
     * @throws \yii\httpclient\Exception
     */
    public function authorizerInfo($authorizer_appid)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_info?component_access_token=' . $this->getComponentAccessToken();
        $data = [
            "component_appid" => $this->appId,
            "authorizer_appid" => $authorizer_appid,
        ];
        $response = $this->httpClient->post($url, $data)->send();
        return $response->setFormat(Client::FORMAT_JSON)->getData();

    }

    /**
     * 代公众号实现业务 get
     * @param $url
     * @return mixed
     * @throws \yii\httpclient\Exception
     */
    public function get($url)
    {
        $response = $this->httpClient->get($url)->send();
        return $response->setFormat(Client::FORMAT_JSON)->getData();
    }

    /**
     * 代公众号实现业务 post
     * @param $url
     * @param $params
     * @return mixed
     * @throws \yii\httpclient\Exception
     */
    public function post($url, $params)
    {
        $response = $this->httpClient->post($url, $params)->send();
        return $response->setFormat(Client::FORMAT_JSON)->getData();
    }
}
