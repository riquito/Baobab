<?php

/**
 * Baobab (an implementation of Nested Set Model)
 * 
 * Copyright 2010 Riccardo Attilio Galli <riccardo@sideralis.org> [http://www.sideralis.org]
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *    http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

if (!defined("DS")) define("DS",DIRECTORY_SEPARATOR);

require_once('PHPUnit/Framework.php');
require_once(dirname(__FILE__).DS.'../baobab.php');



class BaobabWithDataTest extends PHPUnit_Framework_TestCase {
    protected static $db;
    protected $baobab;
    
    public static function setUpBeforeClass() {
        require_once(dirname(__FILE__).DS."conf_database.php");
        if (!isset($DB_CONFIG)) self::fail("Missing or misconfigured conf_database.php");
        
        self::$db=@mysqli_connect(
                      $DB_CONFIG["host"],
                      $DB_CONFIG["username"],
                      $DB_CONFIG["password"],
                      $DB_CONFIG["dbname"],
                      $DB_CONFIG["port"]);
        
        if (mysqli_connect_error()) {
            self::fail(sprintf('Connect Error (%s): %s',mysqli_connect_errno(),mysqli_connect_error()));
        }
        
    }
    
    public static function tearDownAfterClass(){
        mysqli_close(self::$db);
        self::$db=NULL;
    }
    
    public function setUp(){
        $this->baobab = new BaobabNamed(self::$db,"testNamed");
        $this->baobab->destroy();
        $this->baobab->clean();
        $this->baobab->build();
    }
    
    public function tearDown(){
        //$this->baobab->destroy();
    }
    
    // require import to be yet tested
    private function _fillGenericTree(){
        $this->baobab->clean();
        $this->baobab->import(array(
            "fields"=>array("id","lft","rgt"),
            "values"=>array(
                array(5,1,14),
                    array(3,2,7),
                        array(4,3,4),
                        array(6,5,6),
                    array(1,8,13),
                        array(2,9,10),
                        array(7,11,12)
            )
        ));
    }
    
    public function testUpdateNode(){
        $this->_fillGenericTree();
        
        $this->baobab->updateNode(3,array("label"=>"ciao riquito ' ! "));
        
        $this->assertEquals($this->baobab->getNodeData(3,array("lft","label")),
                            array("lft"=>2,"label"=>"ciao riquito ' ! "));
    }
}