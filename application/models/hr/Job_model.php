<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Job_model extends CI_Model {

    public $tablename = 'user_job';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }
    
    public function name($id) {
        $row = $this->db->where(array('id' => $id))->get($this->tablename)->row();
        return $row ? $row->name : "";
    }
}
