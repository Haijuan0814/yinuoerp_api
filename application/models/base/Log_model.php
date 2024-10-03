<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Log_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->tablename = 'base_log';
    }

    public function insert($tablename, $parent, $content) {
        $this->db->set(array(
            "uid" => isset($this->user) ? $this->user->id : 0,
            "tablename" => $tablename,
            "parent" => $parent,
            "content" => $content,
            'insert_time ' => time(),
        ))->insert($this->tablename);
        return $this->db->insert_id();
    }

}
