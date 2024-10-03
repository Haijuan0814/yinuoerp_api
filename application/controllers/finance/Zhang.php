<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Zhang extends MY_Controller {
    
    public $tablename = 'finance_zhang';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }
}