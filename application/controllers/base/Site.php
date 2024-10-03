<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Site extends MY_Controller {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function index() {
        $data=array(
            'iconfont'=> '//at.alicdn.com/t/font_1479756_emesrprxmy9.js',
        );
        json_success($data);
    }
}