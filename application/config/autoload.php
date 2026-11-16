<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$autoload['packages'] = array();
$autoload['libraries'] = array('database','session','form_validation');
$autoload['drivers'] = array();
// url y form son helpers de CI3; barberia contiene las funciones propias.
$autoload['helper'] = array('url','form','barberia');
$autoload['config'] = array();
$autoload['language'] = array();
$autoload['model'] = array();
