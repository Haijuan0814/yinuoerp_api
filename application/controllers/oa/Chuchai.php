<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Chuchai extends MY_Controller {
    
    public $tablename = 'oa_chuchai';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }
}