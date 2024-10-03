<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Company extends MY_Controller {

    public $tablename = 'oa_article';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }
    
    public function index() {
        $page_name=$this->input->get('page')?$this->input->get('page'):"jianjie";
        $row = $this->db->where('page_name', $page_name)->get($this->tablename)->row();  
        $row ? json_success(array(
            "row" => $row,
        )):  json_fail(lang('undefined'));
    }
}
