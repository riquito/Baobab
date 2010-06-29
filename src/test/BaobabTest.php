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



class BaobabTest extends PHPUnit_Framework_TestCase {
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
        $this->baobab = new Baobab(self::$db,"GENERIC");
        $this->baobab->clean();
        $this->baobab->build();
    }
    
    public function tearDown(){
        //$baobab->destroy();
    }
    
    public function testAppendChildAsRootInEmptyTree(){
        $root_id=$this->baobab->appendChild();
        $this->assertTrue(1===$root_id);
    }
    
    public function testEmptyRoot(){
        $root_id=$this->baobab->get_root();
        $this->assertNull($root_id);
    }
    
    /*
     * @depends testAppendChildAsRootInEmptyTree
     */
    public function testRoot(){
        $this->baobab->appendChild();
        $this->assertTrue(1===$this->baobab->get_root());
    }
    
    public function testImport(){
        
        $this->baobab->import(array(
            "fields"=>array("id","lft","rgt"),
            "values"=>array()
        ));
        
        $this->assertEquals(NULL,$this->baobab->get_tree());
        
        
        $this->baobab->import(array(
            "fields"=>array("id","lft","rgt"),
            "values"=>array(
                array(1,1,8),
                array(2,2,3),
                array(3,4,5),
                array(4,6,7)
            )
        ));
        
        $this->assertEquals(<<<HER
(1) [1,8]
    (2) [2,3]
    (3) [4,5]
    (4) [6,7]
HER
,(string)$this->baobab->get_tree());
        
    }
    
    public function testExport(){
        $data=array(
            "fields"=>array("id","lft","rgt"),
            "values"=>array(
                array(1,1,8),
                array(2,2,3),
                array(3,4,5),
                array(4,6,7)
            )
        );
        $this->baobab->import($data);
        $this->assertEquals(json_encode($data),$this->baobab->export());
    }
    
    /**
     * Retrieve the data to make a test from a correctly formatted file
     *   and return it formatted as a result for a provider
     **/
    function _getTreeTestData($fname,$dir="data"){
        require_once(dirname(__FILE__).DS.$dir.DS.$fname);
        // ensure we pass each case as a single parameter
        
        $methodName=current(array_keys($data));
        $real_data=current(array_values($data));
        $ar_out=array();
        foreach($real_data as $single_test_info) {
            $single_test_info["methodName"]=$methodName;
            $ar_out[]=array($single_test_info);
        }
        return $ar_out;
    }
    
    function _useTreeTestData($whatToTest){
        // load the data
        $this->baobab->import(array("fields"=>array("id","lft","rgt"),"values"=>$whatToTest["from"]));
        // call the func to test
        call_user_func_array(array($this->baobab,$whatToTest["methodName"]),$whatToTest["params"]);
        // get the current tree state
        $treeState=json_decode($this->baobab->export(),TRUE);
        // check that the tree state is what we expected
        $this->assertEquals($whatToTest["to"],$treeState["values"]);
    }
    
    function _provider_appendChild(){
        return $this->_getTreeTestData("appendChild.php");
    }
    
    /**
     * @dataProvider _provider_appendChild
     */
    function testAppendChild($whatToTest){
        $this->_useTreeTestData($whatToTest);
    }
    
    function testInsertNodeAfterRoot(){
        $root_id=$this->baobab->appendChild();
        
        try { $this->baobab->insertNodeAfter($root_id);
        } catch (sp_Error $e) { return; }
        $this->fail("was expecting an sp_Error Exception to be raised");
    }
    
    function _provider_insertNodeAfter(){
        return $this->_getTreeTestData("insertNodeAfter.php");
    }
    
    /**
     * @dataProvider _provider_insertNodeAfter
     */
    function testInsertNodeAfter($whatToTest){
        $this->_useTreeTestData($whatToTest);
    }
    
    function testInsertNodeAfterUnexistentId(){
        $this->baobab->appendChild();
        
        try { $this->baobab->insertNodeAfter(100);
        } catch (sp_Error $e) { return; }
        $this->fail("was expecting an sp_Error Exception to be raised");
    }
    
    function testInsertNodeBeforeRoot(){
        $root_id=$this->baobab->appendChild();
        
        try {
            $this->baobab->insertNodeBefore($root_id);
        } catch (sp_Error $e) {
            return;
        }
        $this->fail("was expecting an sp_Error Exception to be raised");
    }
    
    function _provider_insertNodeBefore(){
        return $this->_getTreeTestData("insertNodeBefore.php");
    }
    
    /**
     * @dataProvider _provider_insertNodeBefore
     */
    function testInsertNodeBefore($whatToTest){
        $this->_useTreeTestData($whatToTest);
    }
    
    function testInsertNodeBeforeUnexistentId(){
        $this->baobab->appendChild();
        
        try { $this->baobab->insertNodeBefore(100);
        } catch (sp_Error $e) { return; }
        $this->fail("was expecting an sp_Error Exception to be raised");
    }
    
    function _provider_insertChildAtIndexPositive(){
        return $this->_getTreeTestData("insertChildAtIndexPositive.php");
    }
    
    /**
     * @dataProvider _provider_insertChildAtIndexPositive
     */
    function testInsertChildAtIndexPositive($whatToTest){
        $this->_useTreeTestData($whatToTest);
    }
    
    function _provider_insertChildAtIndexNegative(){
        return $this->_getTreeTestData("insertChildAtIndexNegative.php");
    }
    
    /**
     * @dataProvider _provider_insertChildAtIndexNegative
     */
    function testInsertChildAtIndexNegative($whatToTest){
        $this->_useTreeTestData($whatToTest);
    }
    
    function testInsertChildAtIndexNotExistent(){
        $root_id=$this->baobab->appendChild();
        $this->baobab->appendChild($root_id);
        $this->baobab->appendChild($root_id);
        
        try {
            // index too high
            $this->baobab->insertChildAtIndex(1,2);
            $this->fail("was expecting an sp_Error Exception to be raised");
        } catch (sp_Error $e) {}
        
        try {
            // index too low
            $this->baobab->insertChildAtIndex(1,-3);
            $this->fail("was expecting an sp_Error Exception to be raised");
        } catch (sp_Error $e) {}
    }
    
    // require import to be yet tested
    function _fillGenericTree(){
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
    
    function testGetTreeSize(){
        // test empty tree
        $this->assertTrue(0===$this->baobab->get_tree_size());
        
        $this->_fillGenericTree();
        
        $this->assertTrue(7===$this->baobab->get_tree_size());
        $this->assertTrue(3===$this->baobab->get_tree_size(1));
        $this->assertTrue(0===$this->baobab->get_tree_size(-1));
    }
    
    function testGetDescendants(){
        // test empty tree
        $this->assertEquals(array(),$this->baobab->get_descendants());
        
        $this->_fillGenericTree();
        
        $this->assertEquals(array(1,2,3,4,6,7),$this->baobab->get_descendants());
        $this->assertEquals(array(4,6),$this->baobab->get_descendants(3));
        $this->assertEquals(array(),$this->baobab->get_descendants(-1));
    }
    
    function testGetLeaves(){
        // test empty tree
        $this->assertEquals(array(),$this->baobab->get_leaves());
        
        $this->_fillGenericTree();
        
        $this->assertEquals(array(4,6,2,7),$this->baobab->get_leaves());
        $this->assertEquals(array(4,6),$this->baobab->get_leaves(3));
        $this->assertEquals(array(),$this->baobab->get_leaves(-1));
    }
    
    function testGetLevels(){
        // test empty tree
        $this->assertEquals(array(),$this->baobab->get_levels());
        
        $this->_fillGenericTree();
        
        $res=$this->baobab->get_levels();
        
        $ordered_ar=array();
        foreach($res as $item) $ordered_ar[$item["id"]]=$item;
        ksort($ordered_ar);
        
        $this->assertEquals(array(
            1=>array("id"=>1,"level"=>1),
            2=>array("id"=>2,"level"=>2),
            3=>array("id"=>3,"level"=>1),
            4=>array("id"=>4,"level"=>2),
            5=>array("id"=>5,"level"=>0),
            6=>array("id"=>6,"level"=>2),
            7=>array("id"=>7,"level"=>2)
          ),$ordered_ar);
    }
    
    function testGetPath(){
        // test empty tree
        $this->assertEquals(array(),$this->baobab->get_path(-1));
        
        $this->_fillGenericTree();
        
        // test root
        $this->assertEquals(array(array("id"=>5)),$this->baobab->get_path(5));
        $this->assertEquals(array(5),$this->baobab->get_path(5,NULL,TRUE));
        
        // test generic node
        $this->assertEquals(array(array("id"=>5),array("id"=>3),array("id"=>4)),$this->baobab->get_path(4));
        $this->assertEquals(array(5,3,4),$this->baobab->get_path(4,NULL,TRUE));
        $this->assertEquals(array(5,3,4),$this->baobab->get_path(4,array(),TRUE));
        $this->assertEquals(array(5,3,4),$this->baobab->get_path(4,array("id"),TRUE));
        
        $this->assertEquals(array(
                array("id"=>5,"lft"=>1),
                array("id"=>3,"lft"=>2),
                array("id"=>4,"lft"=>3)
                ),
                $this->baobab->get_path(4,"lft"));
        
        $this->assertEquals(array(
                array("id"=>5,"lft"=>1),
                array("id"=>3,"lft"=>2),
                array("id"=>4,"lft"=>3)
                ),
                $this->baobab->get_path(4,array("lft")));
        
        $this->assertEquals(array(
                array("id"=>5,"lft"=>1),
                array("id"=>3,"lft"=>2),
                array("id"=>4,"lft"=>3)
                ),
                $this->baobab->get_path(4,array("lft","id")));
    }
}

?>