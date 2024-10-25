<?php

header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods:POST,GET,PUT,DELETE,PATCH');
header('Access-Control-Max-Age:9999999');
header('Access-Control-Allow-Credentials:true');
header('Access-Control-Allow-Headers:authorization,content-type,token');
header('Content-Type', 'application/json;charset=utf-8');

defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller {
    public function __construct() {
        parent::__construct();
        if ($this->input->method() == 'options') {
            exit();
        }
        $this->check();
    }

    public function check() {
        if (!isset($_SERVER['HTTP_TOKEN'])) json_fail('token undefined');
        $user = $this->db->select('id,name,department_id,department_name,avatar,roles,token')->where('token', $_SERVER['HTTP_TOKEN'])->get('user')->row();
        if (!$user) json_fail('token error');
        $this->user = $user;
    }
}
