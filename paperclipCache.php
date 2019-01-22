<?php
namespace dokuwiki\paperclip;
/**
 * Created by PhpStorm.
 * User: leo
 * Date: 2019/1/21
 * Time: 6:42 PM
 */


class paperclipCache
{
    private $settings;
    private $redis;



    public function __construct()
    {
        require dirname(__FILE__).'/settings.php';

        $this->redis = new \Redis();
        $this->redis->connect($this->settings['rhost'], $this->settings['rport']);
        $this->redis->auth($this->settings['rpassword']);
    }
}