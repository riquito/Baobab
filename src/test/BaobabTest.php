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
        $this->baobab->destroy();
        $this->baobab->clean();
        $this->baobab->build();
    }
    
    public function tearDown(){
        //$this->baobab->destroy();
    }
    
    public function testGetTree(){
        
        $this->assertTrue(NULL===$this->baobab->get_tree());
        
        $this->_fillComplexTree();
        
        $this->assertEquals(<<<HER
(8) [1,38]
    (15) [2,15]
        (14) [3,8]
            (2) [4,7]
                (16) [5,6]
        (12) [9,14]
            (10) [10,13]
                (7) [11,12]
    (9) [16,23]
        (1) [17,20]
            (17) [18,19]
        (4) [21,22]
    (18) [24,37]
        (11) [25,30]
            (3) [26,29]
                (6) [27,28]
        (13) [31,36]
            (5) [32,35]
                (19) [33,34]
HER
,$this->baobab->get_tree()->stringify());
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
,$this->baobab->get_tree()->stringify());
        
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
        try {
            call_user_func_array(array($this->baobab,$whatToTest["methodName"]),$whatToTest["params"]);
            if (isset($whatToTest["error"])) $this->fail("Expecting exception ".$whatToTest["error"]);
        } catch (Exception $e) {
            if (isset($whatToTest["error"]))
                $this->assertTrue($whatToTest["error"]===$e->getCode());
            else throw $e;
        }
        
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
            $this->fail("was expecting an sp_Error Exception to be raised");
        } catch (sp_Error $e) {
            $this->assertTrue($e->getCode()===1100);
        }
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
        
        try {
            $this->baobab->insertNodeBefore(100);
            $this->fail("was expecting an sp_Error Exception to be raised");
        } catch (sp_Error $e) {
            $this->assertTrue($e->getCode()===1400);
        }
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
    
    // require import to be yet tested
    function _fillGenericTree(){
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
    
    // require import to be yet tested
    function _fillComplexTree(){
        $this->baobab->clean();
        $this->baobab->import(array(
            "fields"=>array("id","lft","rgt"),
            "values"=>array(
                array(8,1,38),
                    array(15,2,15),
                        array(14,3,8),
                            array(2,4,7),
                                array(16,5,6),
                        array(12,9,14),
                            array(10,10,13),
                                array(7,11,12),
                    array(9,16,23),
                        array(1,17,20),
                            array(17,18,19),
                        array(4,21,22),
                    array(18,24,37),
                        array(11,25,30),
                            array(3,26,29),
                                array(6,27,28),
                        array(13,31,36),
                            array(5,32,35),
                                array(19,33,34)
            )
        ));
    }
    
    function testDeleteSubtree(){
        
        // drop root
        $this->_fillComplexTree();
        $this->baobab->delete_subtree(8);
        $this->assertTrue(NULL===$this->baobab->get_tree());
        
        // drop leaf
        $this->_fillComplexTree();
        $this->baobab->delete_subtree(7);
        $this->assertEquals(<<<HER
(8) [1,36]
    (15) [2,13]
        (14) [3,8]
            (2) [4,7]
                (16) [5,6]
        (12) [9,12]
            (10) [10,11]
    (9) [14,21]
        (1) [15,18]
            (17) [16,17]
        (4) [19,20]
    (18) [22,35]
        (11) [23,28]
            (3) [24,27]
                (6) [25,26]
        (13) [29,34]
            (5) [30,33]
                (19) [31,32]
HER
,$this->baobab->get_tree()->stringify());
        
        // drop parent single child
        $this->_fillComplexTree();
        $this->baobab->delete_subtree(1);
        $this->assertEquals(<<<HER
(8) [1,34]
    (15) [2,15]
        (14) [3,8]
            (2) [4,7]
                (16) [5,6]
        (12) [9,14]
            (10) [10,13]
                (7) [11,12]
    (9) [16,19]
        (4) [17,18]
    (18) [20,33]
        (11) [21,26]
            (3) [22,25]
                (6) [23,24]
        (13) [27,32]
            (5) [28,31]
                (19) [29,30]
HER
,$this->baobab->get_tree()->stringify());
        
        // drop parent multiple children (or drop right sibling with children)
        $this->_fillComplexTree();
        $this->baobab->delete_subtree(18);
        $this->assertEquals(<<<HER
(8) [1,24]
    (15) [2,15]
        (14) [3,8]
            (2) [4,7]
                (16) [5,6]
        (12) [9,14]
            (10) [10,13]
                (7) [11,12]
    (9) [16,23]
        (1) [17,20]
            (17) [18,19]
        (4) [21,22]
HER
,$this->baobab->get_tree()->stringify());
        
        // drop left sibling with children
        $this->_fillComplexTree();
        $this->baobab->delete_subtree(15);
        $this->assertEquals(<<<HER
(8) [1,24]
    (9) [2,9]
        (1) [3,6]
            (17) [4,5]
        (4) [7,8]
    (18) [10,23]
        (11) [11,16]
            (3) [12,15]
                (6) [13,14]
        (13) [17,22]
            (5) [18,21]
                (19) [19,20]
HER
,$this->baobab->get_tree()->stringify());
        
        // drop unexistent node
        $this->_fillGenericTree();
        $this->baobab->delete_subtree(600);
        $this->assertEquals(<<<HER
(5) [1,14]
    (3) [2,7]
        (4) [3,4]
        (6) [5,6]
    (1) [8,13]
        (2) [9,10]
        (7) [11,12]
HER
,$this->baobab->get_tree()->stringify());
        
    
    }
    
    function _provider_deleteSubtreeAndNotUpdateNumbering(){
        return $this->_getTreeTestData("delete_subtree.php");
    }
    
    /**
     * @dataProvider _provider_deleteSubtreeAndNotUpdateNumbering
     */
    function testDeleteSubtreeAndNotUpdateNumbering($whatToTest){
        $this->_useTreeTestData($whatToTest);
    }
    
    function _provider_closeGaps(){
        return $this->_getTreeTestData("close_gaps.php");
    }
    
    /**
     * @dataProvider _provider_closeGaps
     */
    function testCloseGaps($whatToTest){
        $this->_useTreeTestData($whatToTest);
    }
    
    function testGetTreeHeight() {
        // test empty tree
        $this->assertTrue(0===$this->baobab->get_tree_height());
        
        $this->_fillComplexTree();
        $this->assertTrue(5===$this->baobab->get_tree_height());
        
        
        $this->_fillGenericTree();
        $this->assertTrue(3===$this->baobab->get_tree_height());
        
    }
    
    
    function _provider_moveSubtreeAfter(){
        return $this->_getTreeTestData("moveSubtreeAfter.php");
    }
    
    /**
     * @dataProvider _provider_moveSubtreeAfter
     */
    function testMoveSubtreeAfter($whatToTest){
        $this->_useTreeTestData($whatToTest);
    }
    
    
    function _provider_moveSubtreeBefore(){
        return $this->_getTreeTestData("moveSubtreeBefore.php");
    }
    
    /**
     * @dataProvider _provider_moveSubtreeBefore
     */
    function testMoveSubtreeBefore($whatToTest){
        $this->_useTreeTestData($whatToTest);
    }
    
    
    function _provider_moveSubtreeAtIndex(){
        return $this->_getTreeTestData("moveSubtreeAtIndex.php");
    }
    
    /**
     * @dataProvider _provider_moveSubtreeAtIndex
     */
    function testMoveSubtreeAtIndex($whatToTest){
        $this->_useTreeTestData($whatToTest);
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
}

?>