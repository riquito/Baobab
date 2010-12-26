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
    protected static $tree_name;
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
        
        if (mysqli_connect_error()) {
            self::fail(sprintf('Connect Error (%s): %s',mysqli_connect_errno(),mysqli_connect_error()));
        }
        
        //http://dev.mysql.com/doc/refman/5.1/en/charset-charsets.html
        mysqli_set_charset(self::$db,$DB_CONFIG["charset"]);
        
        self::$tree_name="test";
    }
    
    public static function tearDownAfterClass(){
        mysqli_close(self::$db);
        self::$db=NULL;
    }
    
    public function setUp(){
        $this->base_tree=1;
        $this->baobab = new Baobab(self::$db,self::$tree_name,$this->base_tree);
        $this->baobab->destroy(TRUE);
        $this->baobab->build();
    }
    
    public function tearDown(){
        //$this->baobab->destroy(TRUE);
    }
    
    public function testImportExport(){
        /* ### test empty tree ### */
        
        $this->assertEquals(
            array(),
            json_decode(Baobab::export(self::$db,self::$tree_name),TRUE));
        
        
        Baobab::import(self::$db,self::$tree_name,'[]');
        $this->assertEquals(NULL,$this->baobab->getTree());
        
        $empty_json_tree='[{"fields":["id","lft","rgt"],"values":null}]';
        
        Baobab::import(self::$db,self::$tree_name,$empty_json_tree);
        $this->assertEquals(NULL,$this->baobab->getTree());
        
        
        /* ### test nested tree ### */
        
        $nested_json_tree='[{
            "fields":["id","lft","rgt"],
            "values":
                [1,1,8,[
                    [2,2,5,[
                        [3,3,4,[]]
                    ]],
                    [4,6,7,[]]
                ]]
            }]';
        
        Baobab::import(self::$db,self::$tree_name,$nested_json_tree);
        
        $nested_json_tree='[{
            "tree_id":1,
            "fields":["id","lft","rgt"],
            "values":
                [1,1,8,[
                    [2,2,5,[
                        [3,3,4,[]]
                    ]],
                    [4,6,7,[]]
                ]]
            }]';
        
        
        $this->assertEquals(
            json_decode($nested_json_tree,TRUE),
            json_decode(Baobab::export(self::$db,self::$tree_name),TRUE)
        );
        
        
        $inner_treeId_json_tree='[{
            "fields":["tree_id","id","lft","rgt"],
            "values":
                [3,100,1,8,[
                    [3,200,2,5,[
                        [3,300,3,4,[]]
                    ]],
                    [3,400,6,7,[]]
                ]]
            }]';
            
        // import a different tree while mantaining the previous
        Baobab::import(self::$db,self::$tree_name,$inner_treeId_json_tree);
        
        // check single tree export
        $this->assertEquals(
            json_decode('[
            {
            "tree_id":3,
            "fields":["id","lft","rgt"],
            "values":
                [100,1,8,[
                    [200,2,5,[
                        [300,3,4,[]]
                    ]],
                    [400,6,7,[]]
                ]]
            }
            ]',TRUE),
            json_decode(Baobab::export(self::$db,self::$tree_name,NULL,3),TRUE)
        );
        
        // check all trees export
        $this->assertEquals(
            json_decode('[
            {
            "tree_id":1,
            "fields":["id","lft","rgt"],
            "values":
                [1,1,8,[
                    [2,2,5,[
                        [3,3,4,[]]
                    ]],
                    [4,6,7,[]]
                ]]
            },
            {
            "tree_id":3,
            "fields":["id","lft","rgt"],
            "values":
                [100,1,8,[
                    [200,2,5,[
                        [300,3,4,[]]
                    ]],
                    [400,6,7,[]]
                ]]
            }
            ]',TRUE),
            json_decode(Baobab::export(self::$db,self::$tree_name),TRUE)
        );
        
        // check fields export
        $this->assertEquals(
            json_decode('[
            {
            "tree_id":3,
            "fields":["rgt","id"],
            "values":
                [8,100,[
                    [5,200,[
                        [4,300,[]]
                    ]],
                    [7,400,[]]
                ]]
            }
            ]',TRUE),
            json_decode(Baobab::export(self::$db,self::$tree_name,array("rgt","id"),3),TRUE)
        );
    }
    
    public function testGetTree(){
        
        // check empty tree
        $this->assertTrue(NULL===$this->baobab->getTree());
        
        // add a tree with a certain tree_id
        $b_id=5;
        $b=new Baobab(self::$db,self::$tree_name,$b_id);
        
        $b->appendChild();
        
        $this->_fillAnyIdTree(4);
        
        // check that we get correctly the first tree
        $this->assertEquals(
            json_decode('[{"tree_id":'.$b_id.',"fields":["id","lft","rgt"],"values":[1,1,2,[]]}]',TRUE),
            json_decode(Baobab::export(self::$db,self::$tree_name,NULL,$b_id),TRUE)
        );
        $b->clean();
        
        // once again but with a bigger tree
        $this->_fillComplexTree($b_id);
        $this->_fillAnyIdTree(4);
        
        $this->assertEquals(
            json_decode('[{"fields":["id","lft","rgt"],"values":[8,1,38,[[15,2,15,[[14,3,8,
                        [[2,4,7,[[16,5,6,[]]]]]],[12,9,14,[[10,10,13,[[7,11,12,[]]]]]]]],
                        [9,16,23,[[1,17,20,[[17,18,19,[]]]],[4,21,22,[]]]],[18,24,37,
                        [[11,25,30,[[3,26,29,[[6,27,28,[]]]]]],[13,31,36,[[5,32,35,
                        [[19,33,34,[]]]]]]]]]],"tree_id":'.$b_id.'}]',TRUE),
            json_decode(Baobab::export(self::$db,self::$tree_name,NULL,$b_id),TRUE)
        );
    }
    
    public function testAppendChildAsRootInEmptyTree(){
        $root_id=$this->baobab->appendChild();
        $this->assertTrue(1===$root_id);
        
        // check with two trees in the table
        $b_id=5;
        $b=new Baobab(self::$db,self::$tree_name,$b_id);
        $b_root_id=$b->appendChild();
        $this->assertTrue(2===$b_root_id);
    }
    
    public function testEmptyRoot(){
        $root_id=$this->baobab->getRoot();
        $this->assertNull($root_id);
        
        // add a tree with a certain tree_id
        // now add another tree
        $nested_json_tree='[{
            "fields":["tree_id","id","lft","rgt"],
            "values":
                [4,100,1,8,[
                    [4,200,2,5,[
                        [4,300,3,4,[]]
                    ]],
                    [4,400,6,7,[]]
                ]]
            }]';
        Baobab::import(self::$db,self::$tree_name,$nested_json_tree);
        
        $root_id=$this->baobab->getRoot();
        $this->assertNull($root_id);
    }
    
    /*
     * @depends testAppendChildAsRootInEmptyTree
     */
    public function testRoot(){
        
        // test with only one tree
        $this->baobab->appendChild();
        $this->assertTrue(1===$this->baobab->getRoot());
        
        // add a couple more trees
        $this->baobab->clean();
        
        $tree_a='[{
            "fields":["tree_id","id","lft","rgt"],
            "values":[1,1,1,2,[]]
            }]';
        Baobab::import(self::$db,self::$tree_name,$tree_a);
        
        $this->baobab->appendChild();
        
        $this->_fillAnyIdTree(2);
        
        $this->assertTrue(2===$this->baobab->getRoot());
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
        // add a tree with a different id
        $this->_fillAnyIdTree(2);
        
        $this->baobab->appendChild();
        
        try {
            $this->baobab->insertNodeBefore(100);
            $this->fail("was expecting an sp_Error Exception to be raised");
        } catch (sp_Error $e) {
            $this->assertTrue($e->getCode()===1400);
        }
    }
    
    function testInsertChildAtIndexNotExistent(){
        // add a tree with a different id
        $this->_fillAnyIdTree(2);
        
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
    
    function testGetParent(){
        
        $this->_fillGenericTree($this->base_tree);
        // add a tree with a different id
        $this->_fillAnyIdTree(2);
        
        $this->assertTrue(NULL===$this->baobab->getParent(5));
        $this->assertTrue(5===$this->baobab->getParent(3));
        
        try {
            // test unexistent node id
            $this->baobab->getParent(-1);
            $this->fail("was expecting an sp_Error Exception to be raised");
        } catch (sp_Error $e) {
            $this->assertTrue($e->getCode()===1400);
        }
    }
    
    function testGetTreeSize(){
        // add a tree with a different id
        $this->_fillAnyIdTree(2);
        
        // test empty tree
        $this->assertTrue(0===$this->baobab->getTreeSize());
        
        $this->_fillGenericTree($this->base_tree);
        
        $this->assertTrue(7===$this->baobab->getTreeSize());
        $this->assertTrue(3===$this->baobab->getTreeSize(1));
        $this->assertTrue(0===$this->baobab->getTreeSize(-1));
    }
    
    function testGetDescendants(){
        // add a tree with a different id
        $this->_fillAnyIdTree(2);
        
        // test empty tree
        $this->assertEquals(array(),$this->baobab->getDescendants());
        
        $this->_fillGenericTree($this->base_tree);
        
        $this->assertEquals(array(1,2,3,4,6,7),$this->baobab->getDescendants());
        $this->assertEquals(array(4,6),$this->baobab->getDescendants(3));
        $this->assertEquals(array(),$this->baobab->getDescendants(-1));
    }
    
    function testGetLeaves(){
        // add a tree with a different id
        $this->_fillAnyIdTree(2);
        
        // test empty tree
        $this->assertEquals(array(),$this->baobab->getLeaves());
        
        $this->_fillGenericTree($this->base_tree);
        
        $this->assertEquals(array(4,6,2,7),$this->baobab->getLeaves());
        $this->assertEquals(array(4,6),$this->baobab->getLeaves(3));
        $this->assertEquals(array(),$this->baobab->getLeaves(-1));
    }
    
    function testGetLevels(){
        // add a tree with a different id
        $this->_fillAnyIdTree(2);
        
        // test empty tree
        $this->assertEquals(array(),$this->baobab->getLevels());
        
        $this->_fillGenericTree($this->base_tree);
        
        $res=$this->baobab->getLevels();
        
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
        // add a tree with a different id
        $this->_fillAnyIdTree(2);
        
        // test empty tree
        $this->assertEquals(array(),$this->baobab->getPath(-1));
        
        $this->_fillGenericTree($this->base_tree);
        
        // test root
        $this->assertEquals(array(array("id"=>5)),$this->baobab->getPath(5));
        $this->assertEquals(array(5),$this->baobab->getPath(5,NULL,TRUE));
        
        // test generic node
        $this->assertEquals(array(array("id"=>5),array("id"=>3),array("id"=>4)),$this->baobab->getPath(4));
        $this->assertEquals(array(5,3,4),$this->baobab->getPath(4,NULL,TRUE));
        $this->assertEquals(array(5,3,4),$this->baobab->getPath(4,array(),TRUE));
        $this->assertEquals(array(5,3,4),$this->baobab->getPath(4,array("id"),TRUE));
        
        $this->assertEquals(array(
                array("id"=>5,"lft"=>1),
                array("id"=>3,"lft"=>2),
                array("id"=>4,"lft"=>3)
                ),
                $this->baobab->getPath(4,"lft"));
        
        $this->assertEquals(array(
                array("id"=>5,"lft"=>1),
                array("id"=>3,"lft"=>2),
                array("id"=>4,"lft"=>3)
                ),
                $this->baobab->getPath(4,array("lft")));
        
        $this->assertEquals(array(
                array("id"=>5,"lft"=>1),
                array("id"=>3,"lft"=>2),
                array("id"=>4,"lft"=>3)
                ),
                $this->baobab->getPath(4,array("lft","id")));
    }
    
    function testGetChildren(){
        // add a tree with a different id
        $this->_fillAnyIdTree(2);
        
        // test empty tree
        $this->assertEquals(array(),$this->baobab->getChildren(-1));
        
        $this->_fillGenericTree($this->base_tree);
        $this->assertEquals(array(2,7),$this->baobab->getChildren(1));
    }
    
    function testGetFirstChild(){
        // add a tree with a different id
        $this->_fillAnyIdTree(2);
        
        $this->_fillGenericTree($this->base_tree);
        
        // find first child of a node with children
        $this->assertTrue(2===$this->baobab->getFirstChild(1));
        
        // find first child of unexistent node
        $this->assertTrue(0===$this->baobab->getFirstChild(-1));
        
        // find first child of a node without children
        $this->assertTrue(0===$this->baobab->getFirstChild(2));
    }
    
    function testGetLastChild(){
        // add a tree with a different id
        $this->_fillAnyIdTree(2);
        
        $this->_fillGenericTree($this->base_tree);
        
        // find last child of a node with children
        $this->assertTrue(7===$this->baobab->getLastChild(1));
        
        // find last child of unexistent node
        $this->assertTrue(0===$this->baobab->getLastChild(-1));
        
        // find last child of a node without children
        $this->assertTrue(0===$this->baobab->getLastChild(2));
    }
    
    function testGetTreeHeight() {
        // add a tree with a different id
        $this->_fillAnyIdTree(2);
        
        // test empty tree
        $this->assertTrue(0===$this->baobab->getTreeHeight());
        
        $this->_fillComplexTree($this->base_tree);
        $this->assertTrue(5===$this->baobab->getTreeHeight());
        
        
        $this->_fillGenericTree($this->base_tree);
        $this->assertTrue(3===$this->baobab->getTreeHeight());
        
    }
    
    function testGetChildAtIndex(){
        // add a tree with a different id
        $this->_fillAnyIdTree(2);
        
        $this->_fillComplexTree($this->base_tree);
        
        $this->assertTrue($this->baobab->getChildAtIndex(15,0)===14);
        $this->assertTrue($this->baobab->getChildAtIndex(15,1)===12);
        $this->assertTrue($this->baobab->getChildAtIndex(15,-2)===14);
        $this->assertTrue($this->baobab->getChildAtIndex(15,-1)===12);
        $this->assertTrue($this->baobab->getChildAtIndex(2,0)===16);
        $this->assertTrue($this->baobab->getChildAtIndex(2,-1)===16);
        
        try {
            // index too high
            $this->baobab->getChildAtIndex(15,2);
            $this->fail("was expecting an sp_Error Exception to be raised");
        } catch (sp_Error $e) {
            $this->assertTrue($e->getCode()===1300);
        }
        
        try {
            // index too high
            $this->baobab->getChildAtIndex(15,-3);
            $this->fail("was expecting an sp_Error Exception to be raised");
        } catch (sp_Error $e) {
            $this->assertTrue($e->getCode()===1300);
        }
        
        try {
            // index too high
            $this->baobab->getChildAtIndex(17,0);
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
        
        $random_tree=isset($json_obj["random_tree"]) ? $json_obj["random_tree"] :
            array("tree_id"=>1000,"from_node_id"=>100000,"num_children"=>100);
        
        $ar_out=array();
        foreach($real_data as $single_test_info) {
            $single_test_info["methodName"]=$methodName;
            $single_test_info["fields"]=$fields;
            $single_test_info["random_tree"]=$random_tree;
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
        
        // add a fake tree
        $random_tree=$whatToTest["random_tree"];
        $this->_fillFakeTree($random_tree["tree_id"],$random_tree["from_node_id"],$random_tree["num_children"]);
        
        // load the data
        Baobab::import(self::$db,self::$tree_name,array(array(
                "tree_id"=>$this->base_tree,
                "fields"=>$whatToTest["fields"],
                "values"=>$whatToTest["from"])));
        // call the func to test
        try {
            call_user_func_array(array($this->baobab,$whatToTest["methodName"]),$whatToTest["params"]);
            if (isset($whatToTest["error"])) $this->fail("Expecting exception ".$whatToTest["error"]);
            
            // get the current tree state (pop because this function work always on a single array)
            $treeState=current(json_decode(Baobab::export(self::$db,self::$tree_name,NULL,$this->base_tree),TRUE));
            
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
    function _fillGenericTree($tree_id){
        $this->baobab->clean($tree_id);
        Baobab::import(self::$db,self::$tree_name,'[{'.
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
            }]'
        );
    }
    
    // clean the tree and insert a not trivial tree
    // require import to be yet tested
    function _fillComplexTree($tree_id){
        $this->baobab->clean($tree_id);
        Baobab::import(self::$db,self::$tree_name,'[{'.
            ($tree_id ? '"tree_id":'.$tree_id .',' : '').
          ' "fields":["id","lft","rgt"],
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
            }]'
        );
    }
    
    // just add a tree with ids greater than 100000
    function _fillAnyIdTree($tree_id){
        $this->baobab->clean($tree_id);
        Baobab::import(self::$db,self::$tree_name,'[{'.
            ($tree_id ? '"tree_id":'.$tree_id .',' : '').
          ' "fields":["id","lft","rgt"],
            "values":
                [100001,1,14,[
                    [100002,2,7,[
                        [100003,3,4,[]],
                        [100004,5,6,[]]
                    ]],
                    [100005,8,13,[
                        [100006,9,10,[]],
                        [100007,11,12,[]]
                    ]]
                ]]
            }]'
        );
    }
    
    // we don't really need a tree, but something similar
    function _fillFakeTree($tree_id,$from_node_id,$num_children){
        
        $query="INSERT INTO ".(self::$tree_name)."(tree_id,id,lft,rgt)".
               "VALUES ";
        
        $values=array();
        for($i=0;$i<$num_children;$i++) {
            $values[]="(".join(",",array($tree_id,$from_node_id+$i,rand(1,$num_children*2),rand(1,$num_children*2))).")";
        }
        
        self::$db->query($query.join(",",$values));
    }
}

?>