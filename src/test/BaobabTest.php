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

require_once(dirname(__FILE__).DS.'..'.DS.'baobab.php');


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
        
        //http://dev.mysql.com/doc/refman/5.1/en/charset-charsets.html
        mysqli_set_charset(self::$db,$DB_CONFIG["charset"]);
        
    }
    
    public static function tearDownAfterClass(){
        mysqli_close(self::$db);
        self::$db=NULL;
    }
    
    public function setUp(){
        $this->baobab = new Baobab(self::$db,"test");
        $this->baobab->destroy();
        $this->baobab->clean();
        $this->baobab->build();
    }
    
    public function tearDown(){
        //$this->baobab->destroy();
    }
    
    public function testImportExport(){
        /* ### test empty tree ### */
        
        $empty_json_tree='{"fields":["id","lft","rgt"],"values":null}';
        
        $this->assertEquals(
            json_decode($empty_json_tree,TRUE),
            json_decode($this->baobab->export(),TRUE)
        );
        
        $this->baobab->import($empty_json_tree);
        $this->assertEquals(NULL,$this->baobab->get_tree());
        
        $this->assertEquals(
            json_decode($empty_json_tree,TRUE),
            json_decode($this->baobab->export(),TRUE)
        );
        
        /* ### test nested tree ### */
        
        $nested_json_tree='{
            "fields":["id","lft","rgt"],
            "values":
                [1,1,8,[
                    [2,2,5,[
                        [3,3,4,[]]
                    ]],
                    [4,6,7,[]]
                ]]
            }';
        
        $this->baobab->import($nested_json_tree);
        
        $this->assertEquals(
            json_decode($nested_json_tree,TRUE),
            json_decode($this->baobab->export(),TRUE)
        );
        
    }
    
    public function testGetTree(){
        
        $this->assertTrue(NULL===$this->baobab->get_tree());
        
        $this->baobab->appendChild();
        $this->assertEquals(
            json_decode('{"fields":["id","lft","rgt"],"values":[1,1,2,[]]}',TRUE),
            json_decode($this->baobab->export(),TRUE)
        );
        $this->baobab->clean();
        
        $this->_fillComplexTree();
        
        $this->assertEquals(
            json_decode('{"fields":["id","lft","rgt"],"values":[8,1,38,[[15,2,15,[[14,3,8,
                        [[2,4,7,[[16,5,6,[]]]]]],[12,9,14,[[10,10,13,[[7,11,12,[]]]]]]]],
                        [9,16,23,[[1,17,20,[[17,18,19,[]]]],[4,21,22,[]]]],[18,24,37,
                        [[11,25,30,[[3,26,29,[[6,27,28,[]]]]]],[13,31,36,[[5,32,35,
                        [[19,33,34,[]]]]]]]]]]}',TRUE),
            json_decode($this->baobab->export(),TRUE)
        );
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
    
    function testInsertNodeAfterRoot(){
        $root_id=$this->baobab->appendChild();
        
        try {
            $this->baobab->insertNodeAfter($root_id);
            $this->fail("was expecting an sp_Error Exception to be raised");
        } catch (sp_Error $e) {
            $this->assertTrue($e->getCode()===1100);
        }
    }
    
    function testInsertNodeAfterUnexistentId(){
        $this->baobab->appendChild();
        
        try {
            $this->baobab->insertNodeAfter(100);
            $this->fail("was expecting an sp_Error Exception to be raised");
        } catch (sp_Error $e) {
            $this->assertTrue($e->getCode()===1400);
        }
    }
    
    function testInsertNodeBeforeRoot(){
        $root_id=$this->baobab->appendChild();
        
        try {
            $this->baobab->insertNodeBefore($root_id);
            $this->fail("was expecting an sp_Error Exception to be raised");
        } catch (sp_Error $e) {
            $this->assertTrue($e->getCode()===1100);
        }
    }
    
    function testInsertNodeBeforeUnexistentId(){
        $this->baobab->appendChild();
        
        try {
            $this->baobab->insertNodeBefore(100);
            $this->fail("was expecting an sp_Error Exception to be raised");
        } catch (sp_Error $e) {
            $this->assertTrue($e->getCode()===1400);
        }
    }
    
    function testInsertChildAtIndexNotExistent(){
        $root_id=$this->baobab->appendChild();
        $this->baobab->appendChild($root_id);
        $this->baobab->appendChild($root_id);
        
        try {
            // index too high
            $this->baobab->insertChildAtIndex(1,2);
            $this->fail("was expecting an sp_Error Exception to be raised");
        } catch (sp_Error $e) {
            $this->assertTrue($e->getCode()===1300);
        }
        
        try {
            // index too low
            $this->baobab->insertChildAtIndex(1,-3);
            $this->fail("was expecting an sp_Error Exception to be raised");
        } catch (sp_Error $e) {
            $this->assertTrue($e->getCode()===1300);
        }
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
    
    function testGetChildren(){
        // test empty tree
        $this->assertEquals(array(),$this->baobab->get_children(-1));
        
        $this->_fillGenericTree();
        $this->assertEquals(array(2,7),$this->baobab->get_children(1));
    }
    
    function testGetFirstChild(){
        $this->_fillGenericTree();
        
        // find first child of a node with children
        $this->assertTrue(2===$this->baobab->get_first_child(1));
        
        // find first child of unexistent node
        $this->assertTrue(0===$this->baobab->get_first_child(-1));
        
        // find first child of a node without children
        $this->assertTrue(0===$this->baobab->get_first_child(2));
    }
    
    function testGetLastChild(){
        $this->_fillGenericTree();
        
        // find last child of a node with children
        $this->assertTrue(7===$this->baobab->get_last_child(1));
        
        // find last child of unexistent node
        $this->assertTrue(0===$this->baobab->get_last_child(-1));
        
        // find last child of a node without children
        $this->assertTrue(0===$this->baobab->get_last_child(2));
    }
    
    function testGetTreeHeight() {
        // test empty tree
        $this->assertTrue(0===$this->baobab->get_tree_height());
        
        $this->_fillComplexTree();
        $this->assertTrue(5===$this->baobab->get_tree_height());
        
        
        $this->_fillGenericTree();
        $this->assertTrue(3===$this->baobab->get_tree_height());
        
    }
    
    function testGetChildAtIndex(){
        $this->_fillComplexTree();
        
        $this->assertTrue($this->baobab->get_child_at_index(15,0)===14);
        $this->assertTrue($this->baobab->get_child_at_index(15,1)===12);
        $this->assertTrue($this->baobab->get_child_at_index(15,-2)===14);
        $this->assertTrue($this->baobab->get_child_at_index(15,-1)===12);
        $this->assertTrue($this->baobab->get_child_at_index(2,0)===16);
        $this->assertTrue($this->baobab->get_child_at_index(2,-1)===16);
        
        try {
            // index too high
            $this->baobab->get_child_at_index(15,2);
            $this->fail("was expecting an sp_Error Exception to be raised");
        } catch (sp_Error $e) {
            $this->assertTrue($e->getCode()===1300);
        }
        
        try {
            // index too high
            $this->baobab->get_child_at_index(15,-3);
            $this->fail("was expecting an sp_Error Exception to be raised");
        } catch (sp_Error $e) {
            $this->assertTrue($e->getCode()===1300);
        }
        
        try {
            // index too high
            $this->baobab->get_child_at_index(17,0);
            $this->fail("was expecting an sp_Error Exception to be raised");
        } catch (sp_Error $e) {
            $this->assertTrue($e->getCode()===1300);
        }
    }
    
    /**
     * Retrieve the data to make a test from a correctly formatted JSON file
     *   and return it formatted as a result for a provider
     */
    function &_getJsonTestData($fname,$dir="cases"){
        
        $json_text=file_get_contents(dirname(__FILE__).DS.$dir.DS.$fname);
        $json_obj=json_decode($json_text,TRUE);
        
        // ensure we pass each case as a single parameter
        $methodName=$json_obj["method"];
        $fields=$json_obj["fields"];
        $real_data=$json_obj["cases"];
        
        $ar_out=array();
        foreach($real_data as $single_test_info) {
            $single_test_info["methodName"]=$methodName;
            $single_test_info["fields"]=$fields;
            $ar_out[]=array($single_test_info);
        }
        return $ar_out;
    }
    
    /**
     * .. method:: _useTreeTestData($whatToTest)
     *    Apply a method to a starting tree and check if the resulted tree is 
     *      equal to the tree expected.
     *
     *    :param $whatToTest: one of the values returned by _getJsonTestData
     *    :type  $whatToTest: array
     */
    function _useTreeTestData(&$whatToTest){
        
        // load the data
        $this->baobab->import(array("tree_id"=>1,"fields"=>$whatToTest["fields"],"values"=>$whatToTest["from"]));
        // call the func to test
        try {
            call_user_func_array(array($this->baobab,$whatToTest["methodName"]),$whatToTest["params"]);
            if (isset($whatToTest["error"])) $this->fail("Expecting exception ".$whatToTest["error"]);
            
            // get the current tree state
            $treeState=json_decode($this->baobab->export(),TRUE);
            
            // check that the tree state is what we expected
            $this->assertEquals($whatToTest["to"],$treeState["values"]);
            
        } catch (Exception $e) {
            if (isset($whatToTest["error"]))
                $this->assertTrue($whatToTest["error"]===$e->getCode());
            else throw $e;
        }
        
    }
    
    /**
     * @dataProvider _provider_testCloseGaps
     */
    function testCloseGapsJson($whatToTest){ $this->_useTreeTestData($whatToTest); }
    function _provider_testCloseGaps(){ return $this->_getJsonTestData("close_gaps.json"); }
    
    /**
     * @dataProvider _provider_testAppendChild
     */
    function testAppendChild($whatToTest){ $this->_useTreeTestData($whatToTest); }
    function _provider_testAppendChild(){ return $this->_getJsonTestData("appendChild.json"); }
    
    /**
     * @dataProvider _provider_testDeleteSubtree
     */
    function testDeleteSubtree($whatToTest){ $this->_useTreeTestData($whatToTest); }
    function _provider_testDeleteSubtree(){ return $this->_getJsonTestData("delete_subtree.json"); }
    
    /**
     * @dataProvider _provider_testInsertChildAtIndexNegative
     */
    function testInsertChildAtIndexNegative($whatToTest){ $this->_useTreeTestData($whatToTest); }
    function _provider_testInsertChildAtIndexNegative(){ return $this->_getJsonTestData("insertChildAtIndexNegative.json"); }
    
    /**
     * @dataProvider _provider_testInsertChildAtIndexPositive
     */
    function testInsertChildAtIndexPositive($whatToTest){ $this->_useTreeTestData($whatToTest); }
    function _provider_testInsertChildAtIndexPositive(){ return $this->_getJsonTestData("insertChildAtIndexPositive.json"); }
    
    /**
     * @dataProvider _provider_testInsertNodeAfter
     */
    function testInsertNodeAfter($whatToTest){ $this->_useTreeTestData($whatToTest); }
    function _provider_testInsertNodeAfter(){ return $this->_getJsonTestData("insertNodeAfter.json"); }
    
    /**
     * @dataProvider _provider_testInsertNodeBefore
     */
    function testInsertNodeBefore($whatToTest){ $this->_useTreeTestData($whatToTest); }
    function _provider_testInsertNodeBefore(){ return $this->_getJsonTestData("insertNodeBefore.json"); }
    
    /**
     * @dataProvider _provider_testMoveSubtreeAfter
     */
    function testMoveSubtreeAfter($whatToTest){ $this->_useTreeTestData($whatToTest); }
    function _provider_testMoveSubtreeAfter(){ return $this->_getJsonTestData("moveSubtreeAfter.json"); }
    
    /**
     * @dataProvider _provider_testMoveSubtreeBefore
     */
    function testMoveSubtreeBefore($whatToTest){ $this->_useTreeTestData($whatToTest); }
    function _provider_testMoveSubtreeBefore(){ return $this->_getJsonTestData("moveSubtreeBefore.json"); }
    
    /**
     * @dataProvider _provider_testMoveSubtreeAtIndex
     */
    function testMoveSubtreeAtIndex($whatToTest){ $this->_useTreeTestData($whatToTest); }
    function _provider_testMoveSubtreeAtIndex(){ return $this->_getJsonTestData("moveSubtreeAtIndex.json"); }
    
    
    // clean the tree and insert a simple tree
    // require import to be yet tested
    function _fillGenericTree(){
        $this->baobab->clean();
        $this->baobab->import('{
            "fields":["id","lft","rgt"],
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
    
    // clean the tree and insert a not trivial tree
    // require import to be yet tested
    function _fillComplexTree(){
        $this->baobab->clean();
        $this->baobab->import('{
            "fields":["id","lft","rgt"],
            "values":
                [8,1,38,[
                    [15,2,15,[
                        [14,3,8,[
                            [2,4,7,[
                                [16,5,6,[]]
                            ]]
                        ]],
                        [12,9,14,[
                            [10,10,13,[
                                [7,11,12,[]]
                            ]]
                        ]]
                    ]],
                    [9,16,23,[
                        [1,17,20,[
                            [17,18,19,[]]
                        ]],
                        [4,21,22,[]]
                    ]],
                    [18,24,37,[
                        [11,25,30,[
                            [3,26,29,[
                                [6,27,28,[]]
                            ]]
                        ]],
                        [13,31,36,[
                            [5,32,35,[
                                [19,33,34,[]]
                            ]]
                        ]]
                    ]]
                ]]
            }'
        );
    }
}

?>