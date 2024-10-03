<?php

if (!defined('BASEPATH'))  exit('No direct script access allowed');

class Setting_model extends CI_Model {
    
    public $tablename = 'base_setting';

    public function __construct() {
        parent::__construct();        
    }

    public function item($key, $array = true) {
        $row = $this->db->where('key',$key)->get($this->tablename)->row();
        if($row){
            switch ($row->mode) {
                case 'json':
                    return json_decode($row->data, $array); //return object
                    break;
                case 'userid':
                    return explode(',', $row->data); //return array
                    break;
                default :
                    return $row->data;
                    break;
            }
        }
    }
}
