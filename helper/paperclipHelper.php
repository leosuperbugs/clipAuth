<?php
/**
 * This helper is for external login
 * Providing some of the most commonly used functions for Wechat and Weibo login
 *
 * Author: Mark
 * Email: marktnie@gmail.com
 *
 * Date: 2019/1/18
 * Time: 8:55 PM
 */
if(!defined('DOKU_INC')) die();

require dirname(__FILE__).'/../vendor/autoload.php';

use \dokuwiki\paperclip;

define("__STATE__SPLTR__", "-");

/**
 * Help the Auth Plugin
 * Class ExtLoginHelper
 */
class helper_plugin_clipauth_paperclipHelper extends DokuWiki_Plugin {

    private $redis;
    private $settings;
    // oauth web provider
    private $provider;

    public function __construct()
    {

        require dirname(__FILE__).'/../settings.php';

        $this->redis = new \Redis();
        $this->redis->connect($this->settings['rhost'], $this->settings['rport']);
        $this->redis->auth($this->settings['rpassword']);
        $this->provider = new \Oakhope\OAuth2\Client\Provider\WebProvider([
            'appid' => $this->getConf('wechatAppId'),
            'secret' => $this->getConf('wechatSecret'),
            'redirect_uri' => $this->getConf('wechatRediURI')
        ]);
    }

    /**
     * Compose wechat login link
     *
     * @param $state
     * @return string
     */
    public function getAuthURL() {
        // Fetch the authorization URL from the provider; this returns the
        // urlAuthorize option and generates and applies any necessary parameters
        // (e.g. state).
        $authURL = $this->provider->getAuthorizationUrl();

        // Get the state generated for you and store it to the session.
        // I think I should start a session here
        $_SESSION['oauth2state'] = $this->provider->getState();

        return $authURL;
    }


    /**
     * To get the user info from Wechat
     *
     * @param $code
     */
    public function getWechatInfo($accessToken, $openid) {
        $query  = 'access_token=' . $accessToken . '&openid=' . $openid;
        $url = $this->getConf('wechatUserinfoURL') . '?' .$query;
        $data = file_get_contents($url);
        if (!$data) {
            return false;
        }
        $data = json_decode($data, true);
        return $data;
    }

    /**
     * To get the user info from Weibo
     *
     * @param $code
     */
    public function getWeiboInfo($accessToken) {

    }

    /**
     * To get wechat token
     *
     * @param $wechatTokenURL
     *
     * @return JSON
     */
    public function getAccessToken() {
        return ($this->provider->getAccessToken(
            'authorization_code',
            [
                'code' => $_GET['code'],
            ]
        ));
    }

}