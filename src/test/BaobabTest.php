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
        
        try {
            $this->baobab->insertNodeAfter($root_id);
        } catch (sp_Error $e) {
            return;
        }
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
}

?>