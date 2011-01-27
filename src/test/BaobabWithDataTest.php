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

require_once(dirname(__FILE__).DS.'../baobab.php');


class BaobabNamed extends Baobab {

    public function build() {
        if (parent::build()) {
            
            $result = $this->db->query("
                ALTER TABLE {$this->forest_name}
                ADD COLUMN label VARCHAR(50) DEFAULT '' NOT NULL");
            if (!$result) throw new sp_MySQL_Error($this->db);
        }
    }
    
}


class BaobabWithDataTest extends PHPUnit_Framework_TestCase {
    protected static $db;
    protected static $forest_name;
    protected $baobab;
    
    private $base_tree;
    
    public static function setUpBeforeClass() {
        require_once(dirname(__FILE__).DS."conf_database.php");
        if (!isset($DB_CONFIG)) self::fail("Missing or misconfigured conf_database.php");
        
        self::$db=@mysqli_connect(
                      $DB_CONFIG["host"],
                      $DB_CONFIG["username"],
                      $DB_CONFIG["password"],
                      $DB_CONFIG["dbname"],
                      $DB_CONFIG["port"]);
        
        //http://dev.mysql.com/doc/refman/5.1/en/charset-charsets.html
        mysqli_set_charset(self::$db,$DB_CONFIG["charset"]);
        
        if (mysqli_connect_error()) {
            self::fail(sprintf('Connect Error (%s): %s',mysqli_connect_errno(),mysqli_connect_error()));
        }
        
        self::$forest_name="testNamed";
    }
    
    public static function tearDownAfterClass(){
        mysqli_close(self::$db);
        self::$db=NULL;
    }
    
    public function setUp(){
        $this->base_tree=1;
        
        $this->baobab = new BaobabNamed(self::$db,self::$forest_name,$this->base_tree);
        $this->baobab->destroy(TRUE);
        $this->baobab->build();
    }
    
    public function tearDown(){
        //$this->baobab->destroy();
    }
    
    // clean the tree and insert a simple tree
    // require import to be yet tested
    function _fillGenericTree($tree_id){
        $t=new Baobab(self::$db,self::$forest_name,$tree_id);
        $t->clean();
        Baobab::import(self::$db,self::$forest_name,'[{'.
            ($tree_id ? '"tree_id":'.$tree_id .',' : '').
          ' "fields":["id","lft","rgt"],
            "values":
                [5,1,14,[
                    [3,2,7,[
                        [4,3,4,[]],
                        [6,5,6,[]]
                    ]],
                    [1,8,13,[
                        [2,9,10,[]],
                        [7,11,12,[]]
                    ]]
                ]]
            }'
        );
    }
    
    // clean the tree and insert a simple tree with labels
    // require import to be yet tested
    function _fillGenericLabelTree($tree_id){
        $t=new Baobab(self::$db,self::$forest_name,$tree_id);
        $t->clean();
        Baobab::import(self::$db,self::$forest_name,'[{'.
            ($tree_id ? '"tree_id":'.$tree_id .',' : '').
          ' "fields":["id","lft","label","rgt"],
            "values":
                [5,1,"AAA",14,[
                    [3,2,"BBB",7,[
                        [4,3,"CCC",4,[]],
                        [6,5,"DDD",6,[]]
                    ]],
                    [1,8,"EEE",13,[
                        [2,9,"FFF",10,[]],
                        [7,11,"GGG",12,[]]
                    ]]
                ]]
            }]'
        );
    }
    
    public function testUpdateNode(){
        $this->_fillGenericLabelTree($this->base_tree);
        
        $this->baobab->updateNode(3,array("label"=>"ciao riquito ' ! "));
        
        $this->assertEquals($this->baobab->getNodeData(3,array("lft","label")),
                            array("lft"=>2,"label"=>"ciao riquito ' ! "));
    }
    
    public function testImportExport(){
        /* ### test empty tree ### */
        
        $this->assertEquals(
            array(),
            json_decode(Baobab::export(self::$db,self::$forest_name),TRUE));
        
        
        Baobab::import(self::$db,self::$forest_name,'[]');
        $this->assertEquals(NULL,$this->baobab->getTree());
        
        $empty_json_tree='[{"fields":["id","lft","rgt","label"],"values":null}]';
        
        Baobab::import(self::$db,self::$forest_name,$empty_json_tree);
        $this->assertEquals(NULL,$this->baobab->getTree());
        
        /* ### test import/export not empty tree with non numeric values ### */
        
        $json_tree='[{
            "fields":["id","lft","label","rgt"],
            "values":
                [5,1,"AAA",14,[
                    [3,2,"BBB",7,[
                        [4,3,"CCC",4,[]],
                        [6,5,"DDD",6,[]]
                    ]],
                    [1,8,"EEE",13,[
                        [2,9,"FFF",10,[]],
                        [7,11,"GGG",12,[]]
                    ]]
                ]]
            }]';
        
        Baobab::import(self::$db,self::$forest_name,$json_tree);
        
        $this->assertEquals(
            array(array(
                "tree_id"=>1,
                "fields"=>array("id","label","rgt"),
                "values"=>array(
                    5,"AAA",14,array(
                        array(
                            3,"BBB",7,array(
                                array(4,"CCC",4,array()),
                                array(6,"DDD",6,array())
                            )
                        ),
                        array(
                            1,"EEE",13,array(
                                array(2,"FFF",10,array()),
                                array(7,"GGG",12,array())
                            )
                        )
                    )
                )
            )),
            json_decode(Baobab::export(self::$db,self::$forest_name,array("id","label","rgt")),TRUE)
        );
    }
}