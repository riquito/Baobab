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


/**!
 * .. class:: sp_Error
 *    
 *    Root exception. Each Exception thrown in a Sideralis Programs library
 *      derive from this class
 */
class sp_Error extends Exception { }

/**!
 * .. class:: sp_MySQL_Error
 *    
 *    Exception holding informations about errors occurred when using mysql
 *
 *    :param $conn_or_msg: database connection object or an exception message
 *    :type $conn_or_msg:  mysqli or string
 *    :param $err_code: mysql error code
 *    :type $err_code:  int
 */
class sp_MySQL_Error extends sp_Error {

    public function __construct($conn_or_msg,$err_code=NULL) {
        if ($conn_or_msg instanceof mysqli || $conn_or_msg instanceof mysql) {
            $err_str=$conn_or_msg->error;
            $err_code=$conn_or_msg->errno;
        } else {
            $err_str=$conn_or_msg;
        }
        
        parent::__construct($err_str,$err_code);
    }
}


/**!
 * .. class:: sp_SQLUtils
 *    
 *    Class with helpers to work with SQL
 *
 *    :param $conn: database connection object
 *    :type $conn:  mysqli
 */
class sp_SQLUtils {
    private $conn;
    
    public function __construct($conn){
        $this->conn=$conn;
    }
    
    /**!
     * .. method:: vector_to_sql_tuple($ar)
     *    
     *    Transform an array in a valid SQL tuple. The array can contain only
     *      values of type int,float,boolean,string.
     *
     *    :param $ar: an array to convert (only array values are used)
     *    :type $ar:  array
     *
     *    :return: the generated SQL snippet
     *    :rtype:  string
     * 
     *    Example:
     *    .. code-block::
     *       
     *       php> $conn=new mysqli(  connection data )
     *       php> $sql_utils=new sp_SQLUtils($conn)
     *       php> echo $sql_utils->vector_to_sql_tuple(array("i'm a string",28,NULL,FALSE));
     *       ( 'i\'m a string','28',NULL,FALSE )
     * 
     */
    public function vector_to_sql_tuple($ar) {
        $tmp=array();
        foreach($ar as $value) {
            if ($value===NULL) $tmp[]="NULL";
            else if (is_bool($value)) $tmp[]=($value ? "TRUE" : "FALSE");
            else $tmp[]="'".($this->conn->real_escape_string($value))."'";
        }
        return sprintf("( %s )",join(",",$tmp));
    }
    
    /**!
     * .. method:: array_to_sql_assignments($ar[,$sep=","])
     *    
     *    Convert an associative array in a series of "columnName = value" expressions
     *     as valid SQL.
     *    The expressions are separated using the parameter $sep (defaults to ",").
     *    The array can contain only values of type int,float,boolean,string.
     *
     *    :param $ar: an associative array to convert
     *    :type $ar: array
     *    :param $sep: expression separator
     *    :type $sep: string
     *
     *    :return: the generated SQL snippet
     *    :rtype:  string
     *
     *    Example:
     *    .. code-block::
     *       
     *       php> $conn=new mysqli( connection data )
     *       php> $sql_utils=new sp_SQLUtils($conn)
     *       php> $myArray=array("city address"=>"main street","married"=>false);
     *       php> echo $sql_utils->array_to_sql_assignments($myArray);
     *        `city address` = 'main street' , `married` = FALSE
     *       php> echo $sql_utils->array_to_sql_assignments($myArray,"AND");
     *        `city address` = 'main street' AND `married` = FALSE 
     */
    public function array_to_sql_assignments($ar,$sep=",") {
        $tmp=array();
        foreach($ar as $key=>$value) {
            if ($value===NULL) $value="NULL";
            else if (is_bool($value)) $value=($value ? "TRUE" : "FALSE");
            else $value= "'".($this->conn->real_escape_string($value))."'";
            
            $tmp[]=sprintf(" `%s` = %s ",str_replace("`","``",$key),$value);
        }
        return join($sep,$tmp);
    }
    
    /**!
     * .. method:: flush_results()
     *    
     *    Empty connection results of the last single or multi query.
     *    If the last query generated an error, a sp_MySQL_Error exception
     *    is raised.
     *    Mostly useful after calling sql functions or procedures.
     */
    public function flush_results(){
        $conn=$this->conn;
        while($conn->more_results()) {
            if ($result = $conn->use_result()) $result->close();
            $conn->next_result();
        }
        
        if ($conn->errno) throw new sp_MySQL_Error($conn);
    }
    
    /**!
     * .. method:: get_table_fields($table_name)
     *    
     *    Retrieve the names of the fields in a table.
     *    
     *    :param $table_name: name of the table
     *    :type $table_name:  string
     *
     *    :return: fields' names
     *    :rtype:  array
     *    
     *    .. warning::
     *       The table name is not sanitized at all, use this method only
     *       if you're directly providing the correct name, otherwise
     *       sql injection is a given.
     */
    public function &get_table_fields($table_name){
        $table_name=str_replace('`','``',$table_name);
        
        $result=$this->conn->query("SHOW COLUMNS FROM `{$table_name}`;",MYSQLI_STORE_RESULT);
        if (!$result)  throw new sp_MySQL_Error($this->conn);
        
        $fields=array();
        while($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $fields[]=$row["Field"];
        }
        $result->close();
        return $fields;
    }
    
    /**!
     * .. method:: table_exists($table_name[,$db_name=NULL])
     *    
     *    Check if a table exists.
     *    
     *    :param $table_name: name of the table
     *    :type $table_name:  string
     *    :param $db_name: name of the database (if different from the current in use)
     *    :type $db_name:  string
     *    
     *    :return: true if exists, false otherwise
     *    :rtype:  boolean
     */
    public function table_exists($table_name,$db_name=NULL){
        if (!$db_name) {
            $result = $this->conn->query("SELECT DATABASE()",MYSQLI_STORE_RESULT);
            if (!$result) throw new sp_MySQL_Error($this->conn);
            $row = $result->fetch_row();
            $db_name=$row[0];
            $result->close();
        }
        
        $result =$this->conn->query("
            SELECT COUNT(*)
            FROM information_schema.tables 
            WHERE table_schema = '{$db_name}' 
            AND ".($this->array_to_sql_assignments(array(
                 "table_name"=>$table_name)))
        );
        
        if (!$result) throw new sp_MySQL_Error($this->conn);
        $row = $result->fetch_row();
        $result->close();
        
        return $row[0]!=0;
    }
}

/**!
 * .. class:: sp_Lib
 *    
 *    Generic utilities
 */
class sp_Lib {
    
    /**!
     * .. method:: map_method($array,$obj,$methodName)
     *    Call an object method on each item in an array and return an array
     *      with the results.
     *
     *   :param $array: values to pass to the method
     *   :type $array:  array
     *   :param $obj: an object instance
     *   :type $obj:  object
     *   :param $methodName: a callable method of $obj
     *   :type $methodName:  string
     *   
     *   :return: the computed results
     *   :rtype:  array
     */
    public static function &map_method(&$array,$obj,$methodName) {
        $tmp=array();
        foreach ($array as $item){
            $tmp[]=$obj->$methodName($item);
        }
        return $tmp;
    }
}


/**!
 * .. class:: BaobabNode($id,$lft,$rgt,$parentId[,$fields=NULL])
 *    
 *    Node of a Baobab tree
 *
 *    :param $id: the node id
 *    :type $id: int
 *    :param $lft: the node left bound
 *    :type $lft: int
 *    :param $rgt: the node right bound
 *    :type $rgt: int
 *    :param $parentId: the parent's node id, if any
 *    :type $parentId: int or NULL
 *    :param $fields_values: additional fields of the node (mapping fieldName=>value)
 *    :type $fields_values: array or NULL
 *
 *    ..note: this class doesn't involve database interaction, its purposes is
 *        just to have a runtime representation of a Baobab tree
 *
 *    ..note: this class doesn't has any kind of data control, so it expects
 *        that the data used makes sense in a Baobab tree
 * 
 */
class BaobabNode {
    public $id;
    public $lft;
    public $rgt;
    public $parentNode;
    public $fields_values;
    
    public $children;

    public function __construct($id,$lft,$rgt,$parentNode,&$fields_values=NULL) {
        $this->id=$id;
        $this->lft=$lft;
        $this->rgt=$rgt;
        $this->parentNode=$parentNode;
        $this->fields_values=$fields_values;
        
        $this->children=array();
    }
    
    /**!
     * .. method:: add_child($child)
     *
     *    Add a child to the node
     *
     *    :param $child: append a node to the list of this node children
     *    :type $child: :class:`BaobabNode`
     *
     */
    public function add_child($child) {
        $this->children[]=$child;
    }
    
    /**!
     * .. method:: stringify([$fields=NULL[,$diveInto=TRUE[,$indentChar=" "[,$indentLevel=0]]]])
     *
     *    Return a representation of the tree as a string.
     *
     *    :param $fields: what node fields include in the output. id,lft and rgt
     *                      are always included.
     *    :type $fields:  array
     *    :param $diveInto: wheter to continue with node's children or not
     *    :type $diveInto:  boolean
     *    :param $indentChar: character to use to indent
     *    :type $indentChar:  string
     *    :param $indentLevel: how deep we are indenting
     *    :type $indentLevel:  int
     *    
     *    :return: tree or node representation
     *    :rtype:  string
     *    
     *    .. note::
     *       $indentLevel is meant for internal use only.
     *
     *    .. todo::
     *       $fields is currently unused
     */
    public function stringify($fields=NULL,$diveInto=TRUE,$indentChar=" ",$indentLevel=0) {
        // XXX TODO $fields is not used at present (and remove the notice from documentation)
        $out=str_repeat($indentChar,$indentLevel*4)."({$this->id}) [{$this->lft},{$this->rgt}]";
        if (!$diveInto) return $out;
        foreach($this->children as $child) $out.="\n".$child->stringify(
                            $fields,TRUE,$indentChar,$indentLevel+1);
        return $out;
    }
    
    /**!
     * .. method:: is_rightmost()
     *    
     *    Check if the node is rightmost between his siblings.
     *
     *    :return: whether if the node is the rightmost or not
     *    :rtype:  boolean
     *
     *    .. note:
     *       root node is considered to be rightmost
     */
    public function is_rightmost(){
        if (!$this->parentNode) return TRUE;
        
        return $this->parentNode->children[count($this->parentNode->children)-1]->id===$this->id;
    }
    
    /**!
     * .. method:: is_leftmost()
     *    
     *    Check if the node is leftmost between his siblings.
     *
     *    :return: whether if the node is the leftmost or not
     *    :rtype:  boolean
     *    
     *    .. note:
     *       root node is considered to be leftmost
     */
    public function is_leftmost(){
        if (!$this->parentNode) return TRUE;
        
        return $this->parentNode->children[0]->id===$this->id;
    }
}


class Baobab  {
    protected $db;
    protected $tree_name;
    protected $sql_utils;
    protected $fields;
    public $tree_id;
    private $_refresh_fields;
    private $_must_check_ids;
    private $_errors;
    
    /**!
     * .. class:: Baobab($db,$tree_name[,$tree_id=NULL[,$must_check_ids=FALSE]])
     *    
     *    This class lets you create, populate search and destroy a tree stored
     *    using the Nested Set Model described by Joe Celko's
     *
     *    :param $db: mysqli database connection in object oriented style
     *    :type $db:  an instance of mysqli_connect
     *    :param $tree_name: suffix to append to the table, wich will result in
     *                       Baobab_{$tree_name}
     *    :type $tree_name: string
     *    :param $tree_id: id of the tree (to create a new tree it must be NULL or an unused tree_id number).
     *                      If there is only a tree in the table, you can load it with -1;
     *                      A new tree created using NULL has $tree_id = 0 until an appendChild occurs.
     *    :type $tree_id: int or NULL
     *    
     *    :param $must_check_ids: whether to constantly check the id consistency or not
     *    :type $must_check_ids: boolean
     */
    public function __construct($db,$tree_name,$tree_id=NULL,$must_check_ids=FALSE) {
        $this->db=$db;
        $this->sql_utils=new sp_SQLUtils($db);
        $this->tree_name=$tree_name;
        $this->fields=array();
        $this->_refresh_fields=TRUE;
        $this->enableIdCheck($must_check_ids);
        
        // load error's information from db (if tables were created)
        try { $this->_load_errors();
        } catch (sp_Error $e) {}
        
        // if $tree_id is -1 we suppose there is one and only one tree yet in
        //   the table, and we automatically retrieve his id
        if ($tree_id== -1 ) {
            $query="SELECT DISTINCT tree_id FROM Baobab_{$this->tree_name} LIMIT 2";
            if ($result=$this->db->query($query,MYSQLI_STORE_RESULT)) {
                if ($result->num_rows==1) {
                    // one and only, let's use it
                    $row = $result->fetch_row();
                    $tree_id=intval($row[0]);
                }
                else if ($result->num_rows==0) {
                    // no trees ? it will be the first
                }
                $result->close();
            } else {
                if ($this->db->errno==1146) {
                    // table does not exist (skip), so it will be the first tree
                } else throw new sp_MySQL_Error($this->db);
            }
            
            if ($tree_id) $this->tree_id=$tree_id;
            else {
                throw new sp_Error("Too many trees found");
            }
        }
        
        $this->tree_id=intval($tree_id);
    }
    
    /**
     * .. method:: _load_errors()
     *
     *    Fill the member $_errors with informations about error codes and 
     *      messages
     */
    private function _load_errors(){
        $this->_errors=array("by_code"=>array(),"by_name"=>array());
        if ($result=$this->db->query("SELECT code,name,msg FROM Baobab_Errors_{$this->tree_name}")) {
            
            while($row=$result->fetch_assoc()) {
                $this->_errors["by_code"][$row["code"]]=$row;
                $this->_errors["by_name"][$row["name"]]=&$this->_errors["by_code"][$row["code"]];
            }
            $result->close();
            
        } else throw new sp_Error("Cannot read info about errors (d'oh!)");
    }

    /**
     * .. method:: _check_id($id)
     * 
     *    Check an id for validity (it must be an integer present in
     *      the Baobab table used by the current instance).
     *    Throws a sp_Error if $id is not valid.
     *
     *    Any activity of this function can be stopped setting the instance
     *      member "must_check_ids" to FALSE at construction time or runtime.
     */
    private function _check_id($id) {
        if (!$this->_must_check_ids) return;

        $id=intVal($id);
        if ($id>0 && ($result = $this->db->query("SELECT id FROM Baobab_{$this->tree_name} WHERE id = {$id}",MYSQLI_STORE_RESULT))) {
            if ($result->num_rows) {
                $result->close();
                return;
            }
        }
        throw new sp_Error("Not a valid id: {$id}");
    }
    
    /**!
     * .. method:: enableIdCheck($bool)
     *    
     *    When enabled, if a Baobab method is requested to use an id it checks
     *      for his existence beforehand.
     *
     *    :param $bool: wheter to enable id check or not
     *    :type $bool: boolean
     *
     */
    public function enableIdCheck($bool) {
        $this->_must_check_ids=$bool;
    }
    
    /**!
     * .. method:: isIdCheckEnabled()
     *    
     *    Verify if id checking is enabled. See :class:`Baobab.enableIdCheck`.
     *
     *    :return: wheter to enable id checking is enabled or not
     *    :rtype:  boolean
     *
     */
    public function isIdCheckEnabled() {
        return $this->_must_check_ids;
    }
    
    /**
     * .. method:: _sql_check_fields($fields)
     *
     *    Check that the supplied fields exists in this Baobab table.
     *    Throws an sp_Error if something is wrong.
     *
     *    :param $fields: names of the fields to check for
     *    :type $fields:  array
     *
     *    :note: it is public but for internal use only (see static methods import/export )
     *
     */
    public function _sql_check_fields(&$fields) {
        
        $real_fields=$this->_get_fields();
        // check that the requested fields exist
        foreach($fields as $fieldName) {
            if (!isset($real_fields[$fieldName])) throw new sp_Error("`{$fieldName}` wrong field name for table Baobab_{$this->tree_name}");
        }
    }
    
    /**
     * .. method:: _get_fields()
     *
     *    Return the fields' names of the Baobab main table.
     *    It mantains the fields array in memory, and refresh it if the
     *    private variable _refresh_fields is TRUE
     *
     *    :return: associative array fieldName=>TRUE
     *    :rtype:  array
     *
     *    .. note::
     *
     *       An associative array is being returned because it's quicker to
     *       check for fields existence inside it.
     *     
     *    :note: it is public but for internal use only (see static methods import/export )
     */
    public function &_get_fields(){
        
        if ($this->_refresh_fields) {
            $fields=$this->sql_utils->get_table_fields("Baobab_{$this->tree_name}");
            $this->fields=array();
            for($i=0,$il=count($fields);$i<$il;$i++) {
                $this->fields[$fields[$i]]=TRUE;
            }
            $this->_refresh_fields=FALSE;
        }
        
        return $this->fields;
    }
    
    /**!
     * .. method:: build()
     *
     *    Apply the database schema.
     *
     *    .. warning::
     *
     *       Running this method on a database which has yet loaded the schema
     *         for the same tree name will end up in errors. The table
     *         Baobab_{tree_name} will remain intact thought.
     *    
     */
    public function build() {

        $sql=file_get_contents(dirname(__FILE__).DIRECTORY_SEPARATOR."schema_baobab.sql");
        if (!$this->db->multi_query(str_replace("GENERIC",$this->tree_name,$sql))) {
            throw new sp_MySQL_Error($this->db);
        }
        
        $this->sql_utils->flush_results();
        $this->_load_errors();
        
    }
    
    /**!
     * .. method:: destroy()
     *
     *    Remove every table, procedure or view that were created via
     *      :class:`Baobab.build` for the current tree name
     *
     *    .. warning::
     *
     *       You're going to loose all the data in the table
     *         Baobab_{tree_name} too.
     *    
     */
    public function destroy() {
        if (!$this->db->multi_query(str_replace("GENERIC",$this->tree_name,"
                DROP PROCEDURE IF EXISTS Baobab_getNthChild_GENERIC;
                DROP PROCEDURE IF EXISTS Baobab_MoveSubtree_real_GENERIC;
                DROP PROCEDURE IF EXISTS Baobab_MoveSubtreeAtIndex_GENERIC;
                DROP PROCEDURE IF EXISTS Baobab_MoveSubtreeBefore_GENERIC;
                DROP PROCEDURE IF EXISTS Baobab_MoveSubtreeAfter_GENERIC;
                DROP PROCEDURE IF EXISTS Baobab_InsertChildAtIndex_GENERIC;
                DROP PROCEDURE IF EXISTS Baobab_InsertNodeBefore_GENERIC;
                DROP PROCEDURE IF EXISTS Baobab_InsertNodeAfter_GENERIC;
                DROP PROCEDURE IF EXISTS Baobab_AppendChild_GENERIC;
                DROP PROCEDURE IF EXISTS Baobab_DropTree_GENERIC;
                DROP PROCEDURE IF EXISTS Baobab_Close_Gaps_GENERIC;
                DROP VIEW IF EXISTS Baobab_AdjTree_GENERIC;
                DROP TABLE IF EXISTS Baobab_GENERIC;
                
                DROP TABLE IF EXISTS Baobab_Errors_GENERIC;
                DROP FUNCTION IF EXISTS Baobab_getErrCode_GENERIC;
                
                "))) {
            throw new sp_MySQL_Error($this->db);
        }
        
        $this->sql_utils->flush_results();
    }
    
    /**!
     * .. method:: clean([$tree_id])
     *    
     *    Delete all the record from the table Baobab_{yoursuffix}, or from one
     *      of his trees.
     *
     *    :param $tree_id: if set only the nodes of this tree will be removed
     *    :type $tree_id:  int
     *
     */
    public function clean($tree_id=NULL) {
        if (!$this->db->query(
            "DELETE FROM Baobab_{$this->tree_name} ".
            ($tree_id===NULL ? "" : " WHERE tree_id=".intval($tree_id))
            )) {
            if ($this->db->errno!==1146) // do not count "missing table" as an error
                throw new sp_MySQL_Error($this->db);
        }
    }


    /**!
     * .. method:: get_root()
     *    
     *    Return the id of the first node of the tree.
     *
     *    :return: id of the root, or NULL if empty
     *    :rtype:  int or NULL
     *
     */
    public function get_root(){

        $query="
          SELECT id AS root
          FROM Baobab_{$this->tree_name}
          WHERE lft = 1;
        ";

        $out=NULL;

        if ($result=$this->db->query($query,MYSQLI_STORE_RESULT)) {
            if ($result->num_rows) {
                $row = $result->fetch_row();
                $out=intval($row[0]);
            }
            $result->close();
        
        } else throw new sp_MySQL_Error($this->db);
        
        return $out;
    }


    /**!
     * .. method:: get_tree_size([$id_node=NULL])
     *    
     *    Retrieve the number of nodes of the subtree starting at $id_node (or
     *      at tree root if $id_node is NULL).
     *    
     *    :param $id_node: id of the node to count from (or NULL to count from root)
     *    :type $id_node:  int or NULL
     *    
     *    :return: the number of nodes in the selected subtree
     *    :rtype:  int
     */
    public function get_tree_size($id_node=NULL) {
        if ($id_node!==NULL) $this->_check_id($id_node);

        $query="
          SELECT (rgt-lft+1) DIV 2
          FROM Baobab_{$this->tree_name}
          WHERE ". ($id_node!==NULL ? "id = ".intval($id_node) : "lft = 1");
        
        $out=0;

        if ($result = $this->db->query($query,MYSQLI_STORE_RESULT)) {
            $row = $result->fetch_row();
            $out=intval($row[0]);
            $result->close();

        } else throw new sp_MySQL_Error($this->db);

        return $out;

    }
    
    /**!
     * .. method:: get_descendants([$id_node=NULL])
     *    
     *    Retrieve all the descendants of a node
     *    
     *    :param $id_node: id of the node whose descendants we're searching for,
     *                       or NULL to start from the tree root.
     *    :type $id_node:  int or NULL
     *    
     *    :return: the ids of node's descendants, in ascending order
     *    :rtype:  array
     *
     */
    public function &get_descendants($id_node=NULL) {

        if ($id_node===NULL) {
            // we search for descendants of root
            $query="SELECT id FROM Baobab_{$this->tree_name} WHERE lft <> 1 ORDER BY id";
        } else {
            // we search for a node descendants
            $id_node=intval($id_node);
            
            $query="
              SELECT id
              FROM Baobab_{$this->tree_name}
              WHERE lft > (SELECT lft FROM Baobab_{$this->tree_name} WHERE id = {$id_node})
                AND rgt < (SELECT rgt FROM Baobab_{$this->tree_name} WHERE id = {$id_node})
              ORDER BY id
            ";
        }
        
        $ar_out=array();

        if ($result = $this->db->query($query,MYSQLI_STORE_RESULT)) {
            while($row = $result->fetch_row()) {
                array_push($ar_out,intval($row[0]));
            }
            $result->close();

        } else throw new sp_MySQL_Error($this->db);

        return $ar_out;

    }
    
    /**!
     * .. method:: get_leaves([$id_node=NULL])
     *
     *    Find the leaves of a subtree.
     *    
     *    :param $id_node: id of a node or NULL to start from the tree root
     *    :type $id_node:  int or NULL
     *
     *    :return: the ids of the leaves, ordered from left to right
     *    :rtype:  array
     */
    public function &get_leaves($id_node=NULL){
        if ($id_node!==NULL) $this->_check_id($id_node);
        
        $query="
          SELECT id AS leaf
          FROM Baobab_{$this->tree_name}
          WHERE lft = (rgt - 1)";
        
        if ($id_node!==NULL) {
            // check only leaves of a subtree adding a "where" condition
            
            $id_node=intval($id_node);
        
            $query.=" AND lft > (SELECT lft FROM Baobab_{$this->tree_name} WHERE id = {$id_node}) ".
                    " AND rgt < (SELECT rgt FROM Baobab_{$this->tree_name} WHERE id = {$id_node}) ";
        }
        
        $query.=" ORDER BY lft";
        
        $ar_out=array();
        
        if ($result = $this->db->query($query,MYSQLI_STORE_RESULT)) {
            while($row = $result->fetch_row()) {
                array_push($ar_out,intval($row[0]));
            }
            $result->close();
            
        } else throw new sp_MySQL_Error($this->db);

        return $ar_out;
    }
    
    /**!
     * .. method:: get_levels()
     *
     *    Find at what level of the tree each node is.
     *    
     *    :param $id_node: id of a node or NULL to start from the tree root
     *    :type $id_node:  int or NULL
     *
     *    :return: associative arrays with id=>number,level=>number, unordered
     *    :rtype:  array
     *
     *    .. note::
     *       tree root is at level 0
     */
    public function &get_levels(){
    
        $query="
          SELECT T2.id as id, (COUNT(T1.id) - 1) AS level
          FROM Baobab_{$this->tree_name} AS T1, Baobab_{$this->tree_name} AS T2
          WHERE T2.lft BETWEEN T1.lft AND T1.rgt
          GROUP BY T2.lft
          ORDER BY T2.lft ASC;
        ";

        $ar_out=array();

        if ($result = $this->db->query($query,MYSQLI_STORE_RESULT)) {
            while($row = $result->fetch_assoc()) {
                array_push($ar_out,array("id"=>intval($row["id"]),"level"=>intval($row["level"])));
            }
            $result->close();

        } else throw new sp_MySQL_Error($this->db);

        return $ar_out;
    }

    /**!
     * .. method:: get_path($id_node[,$fields=NULL[,$squash=FALSE]])
     *    
     *    Find all the nodes between tree root and a node.
     *    
     *    :param $id_node: id of the node used to calculate the path to
     *    :type $id_node:  int
     *    :param $fields: if not NULL, a string with a Baobab tree field name or 
     *                      an array of field names
     *    :type $fields:  mixed
     *    :param $squash: if TRUE the method will return an array with just the 
     *                      values of the first field in $fields (if $fields is
     *                      empty it will default to "id" )
     *    :type $squash:  boolean
     *
     *    :return: sequence of associative arrays mapping for each node
     *               fieldName=>value, where field names are the one present
     *               in $fields plus the field "id" (unless $squash was set),
     *               ordered from root to $id_node
     *    :rtype:  array
     *
     *    Example (considering a tree with two nodes with a field 'name'):
     *    .. code-block::
     *       
     *       php> $tree->get_path(2,array("name"))
     *       array([0]=>array([id]=>1,[name]=>'rootName'),array([id]=>2,[name]=>'secondNodeName']))
     *       php> join("/",$tree->get_path(2,"name",TRUE))
     *       "rootName/secondNodeName"
     * 
     */
    public function &get_path($id_node,$fields=NULL,$squash=FALSE){
        $id_node=intval($id_node);
        
        $this->_check_id($id_node);
        
        if (empty($fields)) {
            if ($squash) $fields=array("id");
            else $fields=array(); // ensure it is not NULL
        }
        else if (is_string($fields)) $fields=array($fields);
        
        // append the field "id" if missing
        if (FALSE===array_search("id",$fields)) $fields[]="id";
        
        $this->_sql_check_fields($fields);
        
        $fields_escaped=array();
        foreach($fields as $fieldName) {
            $fields_escaped[]=sprintf("`%s`", str_replace("`","``",$fieldName));
        }
        
        $query="".
        " SELECT ".join(",",$fields_escaped).
        " FROM Baobab_{$this->tree_name}".
        " WHERE ( SELECT lft FROM Baobab_{$this->tree_name} WHERE id = {$id_node} ) BETWEEN lft AND rgt".
        " ORDER BY lft";

        $result = $this->db->query($query,MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->db);

        $ar_out=array();
        if ($squash) {
            reset($fields);
            $fieldName=current($fields);
            while($rowAssoc = $result->fetch_assoc()) $ar_out[]=$rowAssoc[$fieldName];
            
        } else {
            while($rowAssoc = $result->fetch_assoc()) {
                $tmp_ar=array();
                foreach($fields as $fieldName) {
                    $tmp_ar[$fieldName]=$rowAssoc[$fieldName];
                }
                $ar_out[]=$tmp_ar;
            }
        }
        
        $result->close();

        return $ar_out;
    }
    
    /**!
     *  .. method:: get_some_children($id_parent[,$howMany=NULL[,$fromLeftToRight=TRUE]])
     *
     *     Find all node's children
     *
     *     :param $id_parent: id of the parent node
     *     :type $id_parent:  int
     *     :param $howMany: maximum number of children to retrieve (NULL means all)
     *     :type $howMany:  int or NULL
     *     :param $fromLeftToRight: what order the children must follow
     *     :type $fromLeftToRight:  boolean
     *     
     *     :return: ids of the children nodes, ordered from left to right
     *     :rtype:  array
     *
     */
    public function &get_some_children($id_parent,$howMany=NULL,$fromLeftToRight=TRUE){
        $id_parent=intval($id_parent);
        $howMany=intval($howMany);
        
        $this->_check_id($id_parent);
        
        $query=" SELECT child FROM Baobab_AdjTree_{$this->tree_name} ".
               " WHERE parent = {$id_parent} ".
               " ORDER BY lft ".($fromLeftToRight ? 'ASC' : 'DESC').
               ($howMany ? " LIMIT $howMany" : "");
        
        $result = $this->db->query($query,MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->db);
        
        $ar_out=array();
        while($row = $result->fetch_row()) {
            $ar_out[]=intval($row[0]);
        }
        $result->close();
        
        return $ar_out;
    }
    
    /**!
     *  .. method:: get_children($id_parent)
     *
     *     Find all node's children
     *
     *     :param $id_parent: id of the parent node
     *     :type $id_parent:  int
     *     
     *     :return: ids of the children nodes, ordered from left to right
     *     :rtype:  array
     *
     */
    public function &get_children($id_parent) {
        return $this->get_some_children($id_parent);
    }
    
    /**!
     *  .. method:: get_first_child($id_parent)
     *
     *     Find the leftmost child of a node
     *
     *     :param $id_parent: id of the parent node
     *     :type $id_parent:  int
     *     
     *     :return: id of the leftmost child node, or 0 if not found
     *     :rtype:  int
     *
     */
    public function get_first_child($id_parent) {
        $res=$this->get_some_children($id_parent,1,TRUE);
        return empty($res) ? 0 : current($res);
    }
    
    /**!
     *  .. method:: get_last_child($id_parent)
     *
     *     Find the rightmost child of a node
     *
     *     :param $id_parent: id of the parent node
     *     :type $id_parent:  int
     *     
     *     :return: id of the rightmost child node, or 0 if not found
     *     :rtype:  int
     *
     */
    public function get_last_child($id_parent) {
        $res=$this->get_some_children($id_parent,1,FALSE);
        return empty($res) ? 0 : current($res);
    }
    
    /**!
     *  .. method:: get_child_at_index($id_parent,$index)
     *
     *     Find the nth child of a parent node
     *
     *     :param $id_parent: id of the parent node
     *     :type $id_parent:  int
     *     :param $index: position between his siblings (0 is first).
     *                    Negative indexes are allowed (-1 is the last sibling).
     *     :type $index:  int
     *     
     *     :return: id of the nth child node
     *     :rtype:  int
     *
     */
    public function get_child_at_index($id_parent,$index){
        $id_parent=intval($id_parent);
        $index=intval($index);
        
        if (!$this->db->multi_query("
                CALL Baobab_getNthChild_{$this->tree_name}({$id_parent},{$index},@child_id,@error_code);
                SELECT @child_id as child_id,@error_code as error_code"))
                throw new sp_MySQL_Error($this->db);

        $res=$this->_readLastResult('child_id');
        return intval($res['child_id']);
    }
    
    
    /**!
     * .. method: get_tree([$className="BaobabNode"[,$addChild="add_child"]])
     *
     *    Create a tree from the database data.
     *    It's possible to use a default tree or use custom classes/functions
     *      (it must have the same constructor and public members of class
     *      :class:`BaobabNode`)
     *
     *    :param $className: name of the class holding a node's information
     *    :type $className:  string
     *    :param $addChild: method of $className to call to append a node
     *    :type $addChild:  string
     *
     *    :return: a node instance
     *    :rtype:  instance of $className
     *
     */
    public function &get_tree($className="BaobabNode",$addChild="add_child") {
        
        // this is a specialized version of the query found in get_level()
        //   (the difference lying in the fact that here we retrieve all the
        //    fields of the table)
        $query="
          SELECT (COUNT(T1.id) - 1) AS level ,T2.*
          FROM Baobab_{$this->tree_name} AS T1 JOIN Baobab_{$this->tree_name} AS T2
                on T1.tree_id={$this->tree_id} AND T1.tree_id = T2.tree_id
          WHERE T2.lft BETWEEN T1.lft AND T1.rgt
          GROUP BY T2.lft
          ORDER BY T2.lft ASC;
        ";
        
        $root=NULL;
        $parents=array();
        
        if ($result = $this->db->query($query,MYSQLI_STORE_RESULT)) {
            
            while($row = $result->fetch_assoc()) {
                
                $numParents=count($parents);
                
                $id=$row["id"];
                $lft=$row["lft"];
                $rgt=$row["rgt"];
                $level=$row["level"];
                $parentNode=count($parents) ? $parents[$numParents-1] : NULL;
                
                unset($row["id"]);
                unset($row["lft"]);
                unset($row["rgt"]);
                unset($row["level"]);
                
                $node=new $className($id,$lft,$rgt,$parentNode,$row);
                
                if (!$root) $root=$node;
                else $parents[$numParents-1]->$addChild($node);
                
                if ($rgt-$lft!=1) { // not a leaf
                    $parents[$numParents]=$node;
                }
                else if (!empty($parents) && $rgt+1==$parents[$numParents-1]->rgt) {
                    
                    $k=$numParents-1;
                    $me=$node;
                    while ($k>-1 && $me->rgt+1 == $parents[$k]->rgt) {
                        $me=$parents[$k];
                        unset($parents[$k--]);
                    }
                    
                    /*
                    // alternative way using levels ($parents would have both the parent node and his level)
                    
                    // previous parent is the first one with a level minor than ours
                    if ($parents[count($parents)-1][1]>=$level) {
                        // remove all the previous subtree "parents" until our real parent
                        for($i=count($parents)-1;$parents[$i--][1]>=$level;)
                            array_pop($parents);
                    }
                    */
                }
            }
            $result->close();
            
        } else throw new sp_MySQL_Error($this->db);
        
        return $root;
    }

    /**!
     * .. method:: delete_subtree($id_node[,$close_gaps=True])
     *
     *    Delete a node and all of his children. If $close_gaps is TRUE, mantains
     *      the Modified Preorder Tree consistent closing gaps.
     *
     *    :param $id_node: id of the node to drop
     *    :type $id_node:  int
     *    :param $close_gaps: whether to close the gaps in the tree or not (default TRUE)
     *    :type $close_gaps:  boolean
     *
     *    .. warning::
     *       If the gaps are not closed, you can't use most of the API. Usually
     *         you want to avoid closing gaps when you're delete different
     *         subtrees and want to update the numbering just once
     *         (see :class:`Baobab.update_numbering`)
     */
    public function delete_subtree($id_node,$close_gaps=TRUE) {
        $id_node=intval($id_node);
        $close_gaps=$close_gaps ? 1 : 0;
        
        $this->_check_id($id_node);
        
        if (!$this->db->multi_query("CALL Baobab_DropTree_{$this->tree_name}({$id_node},{$close_gaps})"))
            throw new sp_MySQL_Error($this->db);
        
        $this->sql_utils->flush_results();
        
    }
    
    /**!
     * .. method:: close_gaps
     *    
     *    Update right and left values of each node to ensure there are no
     *      gaps in the tree.
     *
     *    .. warning::
     *       
     *       This is a really slow function, use it only if needed (e.g.
     *         to delete multiple subtrees and close gaps just once)
     */
    public function close_gaps() {
        if (!$this->db->multi_query("CALL Baobab_Close_Gaps_{$this->tree_name}({$this->tree_id})"))
            throw new sp_MySQL_Error($this->db);
        
        $this->sql_utils->flush_results();

    }

    /**!
     * .. method:: get_tree_height()
     *    
     *    Calculate the height of the tree
     *
     *    :return: the height of the tree
     *    :rtype:  int
     *    
     *    .. note::
     *       A tree with one node has height 1.
     * 
     */
    public function get_tree_height(){
        
        $query="
        SELECT MAX(level)+1 as height
        FROM (SELECT t2.id as id,(COUNT(t1.id)-1) as level
              FROM Baobab_{$this->tree_name} as t1, Baobab_{$this->tree_name} as t2
              WHERE t2.lft  BETWEEN t1.lft AND t1.rgt
              GROUP BY t2.id
             ) as ID_LEVELS";
        
        $result = $this->db->query($query,MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->db);

        $row = $result->fetch_row();
        $out=intval($row[0]);
        
        $result->close();
        return $out;
    }

    /**!
     * .. method:: updateNode($id_node,$fields_values)
     *    Update data associeted to a node
     *
     *    :param $id_node: id of the node to update
     *    :type $id_node:  int
     *    :param $fields_values: mapping fields=>values to update
     *                     (only supported types are string,int,float,boolean)
     *    :type $fields_values:  array
     * 
     */
    public function updateNode($id_node,$fields_values){
        $id_node=intval($id_node);
        
        $this->_check_id($id_node);
        
        if (empty($fields_values)) throw new sp_Error("\$fields_values cannot be empty");
        
        $fields=array_keys($fields_values);
        $this->_sql_check_fields($fields);
        
        $query="".
         " UPDATE Baobab_{$this->tree_name}".
         " SET ".( $this->sql_utils->array_to_sql_assignments($fields_values) ).
         " WHERE id = $id_node";
        
        $result = $this->db->query($query,MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->db);
    }
    
    /**!
     * .. method:: getNodeData($id_node[,$fields=NULL])
     *    Retrieve informations about a node.
     *    
     *    :param $id_node: id of the node
     *    :type $id_node:  int
     *    :param $fields: fields' names to read values from
     *    :type $fields:  array
     *
     *    :return: the informations found
     *    :rtype:  array
     * 
     */
    public function getNodeData($id_node,$fields=NULL){
        $id_node=intval($id_node);
        
        $this->_check_id($id_node);
        
        if (!empty($fields)) $this->_sql_check_fields($fields);
        
        $query="".
        " SELECT ".($fields===NULL ? "*" : join(",",$fields)).
        " FROM Baobab_{$this->tree_name} ".
        " WHERE id = $id_node";
        
        $result = $this->db->query($query,MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->db);
        
        return $result->fetch_assoc();
    }
    
    /**!
     * .. method:: appendChild([$id_parent=NULL[,$fields_values=NULL]])
     *    
     *    Create and append a node as last child of a parent node.
     *
     *    :param $id_parent: id of the parent node
     *    :type $id_parent: int or NULL
     *    :param $fields_values: mapping fields=>values to assign to the new node
     *    :type $fields_values: array or NULL
     *    
     *    :return: id of the root, or 0 if empty
     *    :rtype:  int
     *
     */
    public function appendChild($id_parent=NULL,$fields_values=NULL){
        
        $id_parent=intval($id_parent);
        
        if ($id_parent) $this->_check_id($id_parent);
        
        if (!$this->db->multi_query("
                CALL Baobab_AppendChild_{$this->tree_name}({$this->tree_id},{$id_parent},@new_id,@cur_tree_id);
                SELECT @new_id as new_id,@cur_tree_id as tree_id"))
                throw new sp_MySQL_Error($this->db);
        
        $res=$this->_readLastResult(array('new_id','tree_id'));
        $id=intval($res['new_id']);
        $this->tree_id=intval($res['tree_id']);
        
        //update the node if needed
        if ($fields_values!==NULL) $this->updateNode($id,$fields_values);
        
        return $id;
    }
    
    /**!
     * .. method:: insertNodeAfter($id_sibling[,$fields_values=NULL])
     *
     *    Create a new node and insert it as the next sibling of the node
     *      chosen (which can not be root)
     *
     *    :param $id_sibling: id of a node in the tree (can not be root)
     *    :type $id_sibling:  int
     *    :param $fields_values: mapping fields=>values to assign to the new node
     *    :type $fields_values: array or NULL
     *
     *    :return: id of the new node
     *    :rtype:  int
     * 
     */
    public function insertNodeAfter($id_sibling,$fields_values=NULL) {
        $id_sibling=intval($id_sibling);
        
        $this->_check_id($id_sibling);

        if (!$this->db->multi_query("
                CALL Baobab_InsertNodeAfter_{$this->tree_name}({$id_sibling},@new_id,@error_code);
                SELECT @new_id as new_id,@error_code as error_code"))
                throw new sp_MySQL_Error($this->db);

        $res=$this->_readLastResult('new_id');
        
        //update the node if needed
        if ($fields_values!==NULL) $this->updateNode($res['new_id'],$fields_values);
        
        return intval($res['new_id']);
    }

    /**!
     * .. method:: insertNodeBefore($id_sibling[,$fields_values=NULL])
     *
     *    Create a new node and insert it as the previous sibling of the node
     *      chosen (which can not be root)
     *
     *    :param $id_sibling: id of a node in the tree (can not be root)
     *    :type $id_sibling:  int
     *    :param $fields_values: mapping fields=>values to assign to the new node
     *    :type $fields_values: array or NULL
     *
     *    :return: id of the new node
     *    :rtype:  int
     * 
     */
    public function insertNodeBefore($id_sibling,$fields_values=NULL) {
        $id_sibling=intval($id_sibling);
        
        $this->_check_id($id_sibling);

        if (!$this->db->multi_query("
                CALL Baobab_InsertNodeBefore_{$this->tree_name}({$id_sibling},@new_id,@error_code);
                SELECT @new_id as new_id,@error_code as error_code"))
                throw new sp_MySQL_Error($this->db);

        $res=$this->_readLastResult('new_id');
        
        //update the node if needed
        if ($fields_values!==NULL) $this->updateNode($res['new_id'],$fields_values);
        
        return intval($res['new_id']);
    }

    /**!
     * .. method:: insertChildAtIndex($id_parent,$index[,$fields_values=NULL])
     *
     *    Create a new node and insert it as the nth child of the parent node
     *      chosen
     *
     *    :param $id_parent: id of a node in the tree
     *    :type $id_parent:  int
     *    :param $index: new child position between his siblings (0 is first).
     *                   You cannot insert a child as last sibling.
     *                   Negative indexes are allowed (-1 is the position before
     *                     the last sibling).
     *    :type $index:  int
     *    :param $fields_values: mapping fields=>values to assign to the new node
     *    :type $fields_values: array or NULL
     *
     *    :return: id of the new node
     *    :rtype:  int
     * 
     */
    public function insertChildAtIndex($id_parent,$index,$fields_values=NULL) {
        $id_parent=intval($id_parent);
        $index=intval($index);
        
        $this->_check_id($id_parent);

        if (!$this->db->multi_query("
                CALL Baobab_InsertChildAtIndex_{$this->tree_name}({$id_parent},{$index},@new_id,@error_code);
                SELECT @new_id as new_id,@error_code as error_code"))
            throw new sp_MySQL_Error($this->db);
        
        $res=$this->_readLastResult('new_id');
        
        //update the node if needed
        if ($fields_values!==NULL) $this->updateNode($res['new_id'],$fields_values);
        
        return intval($res['new_id']);
    }
    
    /**!
     * .. method:: moveSubTreeAfter($id_to_move,$reference_node)
     *
     *    Move a node and all of his children as right sibling of another node.
     *
     *    :param $id_to_move: id of a node in the tree
     *    :type $id_to_move:  int
     *    :param $reference_node: the node that will become the left sibling
     *                              of $id_to_move
     *    :type $reference_node:  int
     *    
     *    .. warning:
     *       Moving a subtree after/before root or as a child of hisself will
     *         throw a sp_Error exception
     * 
     */
    public function moveSubTreeAfter($id_to_move,$reference_node) {
        $id_to_move=intval($id_to_move);
        $reference_node=intval($reference_node);
        
        $this->_check_id($id_to_move);
        $this->_check_id($reference_node);

        if (!$this->db->multi_query("
                CALL Baobab_MoveSubtreeAfter_{$this->tree_name}({$id_to_move},{$reference_node},@error_code);
                SELECT @error_code as error_code"))
            throw new sp_MySQL_Error($this->db);
        
        $this->_readLastResult();
    }
    
    /**!
     * .. method:: moveSubTreeBefore($id_to_move,$reference_node)
     *
     *    Move a node and all of his children as left sibling of another node.
     *
     *    :param $id_to_move: id of a node in the tree
     *    :type $id_to_move:  int
     *    :param $reference_node: the node that will become the left sibling
     *                              of $id_to_move
     *    :type $reference_node:  int
     *    
     *    .. warning:
     *       Moving a subtree after/before root or as a child of hisself will
     *         throw a sp_Error exception
     * 
     */
    public function moveSubTreeBefore($id_to_move,$reference_node) {
        $id_to_move=intval($id_to_move);
        $reference_node=intval($reference_node);
        
        $this->_check_id($id_to_move);
        $this->_check_id($reference_node);

        if (!$this->db->multi_query("
                CALL Baobab_MoveSubtreeBefore_{$this->tree_name}({$id_to_move},{$reference_node},@error_code);
                SELECT @error_code as error_code"))
            throw new sp_MySQL_Error($this->db);
        
        $this->_readLastResult();
    }
    
    /**!
     * .. method:: moveSubTreeAtIndex($id_to_move,$id_parent,$index)
     *
     *    Move a subtree's root as nth child of another node
     *
     *    :param $id_to_move: id of a node in the tree
     *    :type $id_to_move:  int
     *    :param $id_parent: id of a node that will become $id_to_move's parent
     *    :type $id_parent:  int
     *    :param $index: new child position between his siblings (0 is first).
     *                   Negative indexes are allowed (-1 is the position before
     *                   the last sibling).
     *    :type $index:  int
     *    
     *    .. warning:
     *       Moving a subtree after/before root or as a child of hisself will
     *         throw a sp_Error exception
     * 
     */
    public function moveSubTreeAtIndex($id_to_move,$id_parent,$index) {
        $id_to_move=intval($id_to_move);
        $id_parent=intval($id_parent);
        $index=intval($index);
        
        $this->_check_id($id_to_move);
        $this->_check_id($id_parent);
        
        if (!$this->db->multi_query("
                CALL Baobab_MoveSubtreeAtIndex_{$this->tree_name}({$id_to_move},{$id_parent},{$index},@error_code);
                SELECT @error_code as error_code"))
            throw new sp_MySQL_Error($this->db);
        
        $this->_readLastResult();
    }
    
    /**
     * .. method:: _readLastResult([$fields=NULL[,$error_field="error_code"[,$numResults=2]]])
     *    
     *    Read $numResults query results and return the values mapped to $fields.
     *    If a field named as the value of $error_field is found throw an 
     *      exception using that error informations.
     *
     *    :param $fields: name of a field to read or an array of fields names
     *    :type $fields:  string or array
     *    :param $error_field: name of the field that could contain an error code
     *    :type $error_field:  string
     *    :param $numResults: number of expected mysql results
     *    :type $numResults:  int
     *    
     *    :return: array, mapping $fields=>values found in the last result
     *    :rtype:  int
     */
    private function &_readLastResult($fields=NULL,$error_field="error_code",$numResults=2){
        if (is_string($fields)) $fields=array($fields);
        else if ($fields===NULL) $fields=array();
        
        $k=1;
        while($k++ < $numResults) {
            if ($result = $this->db->use_result()) { $result->close(); }
            if ($this->db->errno) throw new sp_MySQL_Error($this->db);
            $this->db->next_result();
        }
        
        $result = $this->db->use_result();
        $record=$result->fetch_assoc();
        
        if (isset($record[$error_field]) && $record[$error_field]!=0) {
            $error_code=intval($record[$error_field]);
            $result->close();
            throw new sp_Error(sprintf("[%s] %s",
                $this->_errors["by_code"][$error_code]["name"],
                $this->_errors["by_code"][$error_code]["msg"]),$error_code);
        }
        
        $ar_out=array();
        foreach($fields as $fieldName) {
            $ar_out[$fieldName]=$record[$fieldName];
        }
        $result->close();
        
        return $ar_out;
    }
    
    /**!
     * .. method:: import($db,$tree_name,$data)
     *    
     *    [static] Load data about a single tree (as generated by the export method).
     *    
     *    :param $db: mysqli database connection in object oriented style
     *    :type $db:  an instance of mysqli_connect
     *    :param $tree_name: suffix to append to the table, wich will result in
     *                         Baobab_{$tree_name}
     *    :type $tree_name:  string
     *    :param $data: data to import
     *    :type $data:  string (JSON) or array
     *    
     *    :return: an array of Baobab tree instances
     *    :rtype:  array
     *    
     *    $data JSON format is like the following
     *    
     *    .. code-block:: json
     *       
     *       [
     *        {
     *          "tree_id": 6, //optional, it could also be one of the fields, or none at all
     *          "fields" : ["id","lft", "rgt"],
     *          "values" : 
     *              [1,1,4,[
     *                  [2,2,3,[]]
     *              ]]
     *        }
     *        // more could follow ...
     *       ]
     *    
     *    .. note::
     *      If "id" field is used and the nodes values are not NULL, mustn't
     *        exist in the table a record with that same value.
     *      
     *    .. note::
     *      If "tree_id" is used (as attribute or field) and not NULL, mustn't
     *        exist in the table records belonging to that same tree.
     */
    public static function &import($db,$tree_name,$data){
        if (is_string($data)) $data=json_decode($data,true);
        if (!$data || empty($data)) {$x=NULL;return $x;} // nothing to import
        
        // check if the table exists before doing anything else
        $sql_utils=new sp_SQLUtils($db);
        if ( ! $sql_utils->table_exists("Baobab_".$tree_name)){
            throw new sp_Error("Table `{$db_name}`.`Baobab_{$tree_name}` does not exists");
        }
        
        $ar_out=array();
        
        $db->query("START TRANSACTION");
        
        try {
            
            foreach($data as $tmp_data) {
                
                if (empty($tmp_data["values"])) continue;
                
                // get the tree id, if any
                $tree_id=NULL;
                if (isset($tmp_data["tree_id"])) $tree_id=intval($tmp_data["tree_id"]);
                else {
                    $idx=array_search("tree_id",$tmp_data["fields"]);
                    if (FALSE!==$idx) {
                        // all the tree ids are equals, get the first
                        $tree_id=intval($tmp_data["values"][0][$idx]);
                    }
                }
                
                if (!$tree_id) { // check both NULL and 0
                    // there isn't a tree_id, we must get one
                    
                    // find a new tree_id
                    $query="SELECT IFNULL(MAX(tree_id),0)+1 as new_id FROM Baobab_{$tree_name}";
                    $result = $db->query($query,MYSQLI_STORE_RESULT);
                    if (!$result) throw new sp_MySQL_Error($db);
                    $row = $result->fetch_row();
                    $tree_id=intval($row[0]);
                    $result->close();
                    
                } else {
                    // ensure the tree_id isn't in use
                    $query="SELECT DISTINCT tree_id FROM Baobab_{$tree_name} WHERE tree_id={$tree_id}";
                    $result = $db->query($query,MYSQLI_STORE_RESULT);
                    if (!$result) throw new sp_MySQL_Error($db);
                    $tree_exists=$result->num_rows!=0;
                    $result->close();
                    
                    if ($tree_exists) throw new sp_Error("ImportError: tree_id {$tree_id} yet in use");
                }
                
                $tree=new Baobab($db,$tree_name,$tree_id);
                
                // if there are only standard fields we can also try to build the table
                //   (otherwise it must yet exist because we can't guess field types)
                
                $standard_fields=array("id","lft","rgt","tree_id");
                if (count($tmp_data["fields"])<=4) {
                    $is_standard=TRUE;
                    foreach($tmp_data["fields"] as $fieldName) {
                        if (FALSE===array_search($fieldName,$standard_fields)) {
                            $is_standard=FALSE;
                            break;
                        }
                    }
                    if ($is_standard) {
                        try{
                            $tree->build(); // if yet exists is harmless
                        } catch (sp_MySQL_Error $e) {
                            if ($db->errno!=1050) throw $e; // 1050 is "table exists"
                        }
                    }
                }
                
                // check that the table has the involved fields
                $tree->_sql_check_fields($tmp_data["fields"]);
                
                $values=array();
                
                $nodes=array($tmp_data["values"]);
                
                while (!empty($nodes)) {
                    // get data of current node
                    $last_node=array_pop($nodes);
                     //save his children's array
                    $children=array_pop($last_node);
                    // append the children to the nodes to iterate over
                    if (count($children)) $nodes=array_merge($nodes,$children);
                    
                    // keep the data
                    $values[]=$last_node;
                }
                
                // get the tree_id to use
                
                if (FALSE===array_search("tree_id",$tmp_data["fields"])) {
                    // add tree_id to fields
                    $tmp_data["fields"][]="tree_id";
                    
                    // add tree_id to the each row
                    foreach($values as &$row){
                        $row[]=$tree_id;
                    }
                }
                
                // add the values
                $result=$db->query(
                        "INSERT INTO Baobab_{$tree_name}(".join(",",$tmp_data["fields"]).") VALUES ".
                        join(", ",sp_Lib::map_method($values,$sql_utils,"vector_to_sql_tuple"))
                    ,MYSQLI_STORE_RESULT);
                if (!$result)  throw new sp_MySQL_Error($db);
                
                $ar_out[]=$tree;
                
            } // end foreach $data
        
        } catch (Exception $e) {
            // whatever happens we must rollback
            $db->query("ROLLBACK");
            throw $e;
        }
        
        $result=$db->query("COMMIT");
        if (!$result) throw new sp_MySQL_Error($db);
        
        
        return $ar_out;
    }
    
    /**
     * ..method:: _traverse_tree_to_export_data($node,&$data,&$fieldsFlags,&$fieldsOrder)
     *
     *   Traverse a baobab tree and create an array holding the data about each node.
     *   Each resulting node is represented as an array holding his values ordered as
     *     $fieldsOrder, with an array as most right element holding children nodes
     *     (in the same format).
     *
     *   :param $node: current node to retrieve values from
     *   :type $node:  BaobabNode
     */
    private static function _traverse_tree_to_export_data($node,&$data,&$fieldsFlags,&$fieldsOrder){
        
        $len_data=count($data);
        
        $tmp_ar=array();
        
        $i=0;
        
        // get fields and values in the correct order
        foreach($fieldsOrder as $fieldName) {
            if ($fieldName=='id') $value=$node->id;
            else if ($fieldName=='lft') $value=$node->lft;
            else if ($fieldName=='rgt') $value=$node->rgt;
            else $value=$node->fields_values[$fieldName];
            
            if ($fieldsFlags[$i++]&MYSQLI_NUM_FLAG!=0) $value=floatval($value);
            
            $tmp_ar[]=$value;
        }
        
        $tmp_ar[]=array(); // the last element holds the children
        
        // append the child data to parent data
        $data[$len_data-1][]=&$tmp_ar;
        
        foreach($node->children as $childNode) {
            self::_traverse_tree_to_export_data($childNode,$tmp_ar,$fieldsFlags,$fieldsOrder);
        }
        
    }
    
    /**!
     * .. method:: export($db,$tree_name[,$fields=NULL[,$tree_id=NULL]])
     *    
     *    [static] Create a JSON dump of one or more trees
     *    
     *    :param $db: mysqli database connection in object oriented style
     *    :type $db:  an instance of mysqli_connect
     *    :param $tree_name: suffix to append to the table, wich will result in
     *                         Baobab_{$tree_name}
     *    :type $tree_name:  string
     *    :param $fields: optional, the fields to be exported
     *    :type $fields:  array
     *    :param $tree_id: optional, a single tree_id to be exported, or an array of them.
     *                      If NULL all the trees in the table will be exported;
     *    :type $tree_id:  array or int
     *    
     *    :return: a dump of the trees in JSON format
     *    :rtype:  string
     *
     *    .. note::
     *      if 'tree_id' is passed as field, it will not appear in the field
     *        list because redundat (it will be present once in the tree_id attribute)
     *    
     *    Example of an exported tree
     *    
     *    .. code-block:: json
     *
     *       [
     *        {
     *          "tree_id": 6, 
     *          "fields" : ["id","lft", "rgt"], // tree_id is stripped if requested via fields because redundant
     *          "values" : 
     *              [1,1,4,[
     *                  [2,2,3,[]]
     *              ]]
     *        }
     *        // more could follow ...
     *       ]
     * 
     */
    public static function export($db,$tree_name,$fields=NULL,$tree_id=NULL){
        
        // check if the table exists before doing anything else
        $sql_utils=new sp_SQLUtils($db);
        if ( ! $sql_utils->table_exists("Baobab_".$tree_name)){
            throw new sp_Error("Table `{$db_name}`.`Baobab_{$tree_name}` does not exists");
        }
        
        // get all the fields to export or check if the passed fields are valid
        $tree=new Baobab($db,$tree_name,NULL); // use a unexistent tree in the right table
        if ($fields!==NULL) $tree->_sql_check_fields($fields);
        else $fields=array_keys($tree->_get_fields());
        
        // remove tree_id from $fields to avoid writing it n times
        //   ( we give him a single property in the json format )
        $idx_treeId_field=array_search("tree_id",$fields);
        if (FALSE!==$idx_treeId_field) {
            unset($fields[$idx_treeId_field]);
            $fields=array_values($fields); // I want to mantain a correct index sequence
        }
        
        // get the ids of the trees to export
        $ar_tree_id=array();
        if ($tree_id) {
            if (!is_array($tree_id)) $tree_id=array($tree_id);
            // ensure $tree_id contains numbers
            foreach($tree_id as $tmp_id) $ar_tree_id[]=intval($tmp_id);
        }
        else {
            $query="SELECT DISTINCT tree_id FROM Baobab_{$tree_name}";
            $result = $db->query($query,MYSQLI_STORE_RESULT);
            if (!$result) throw new sp_MySQL_Error($db);
            while ($row=$result->fetch_row()) $ar_tree_id[]=intval($row[0]);
            $result->close();
        }
        
        // get the type of the columns mainly to write numbers as ... numbers
        $fieldsFlags=array(); // each index will have the field flag, to know his type
        $result=$db->query(
            "SELECT ".join(",",$fields)." FROM Baobab_{$tree_name} LIMIT 1",MYSQLI_STORE_RESULT);
        if (!$result)  throw new sp_MySQL_Error($db);
        // retrieve the column names and their types
        while ($finfo = $result->fetch_field()) {
            $fieldsFlags[]=$finfo->flags;
        }
        $result->close();
        
        // parse each tree and build an array to jsonize later
        $ar_out=array();
        foreach ($ar_tree_id as $tree_id) {
            $tmp_ar=array("tree_id"=>$tree_id,"fields"=> $fields,"values"=>null);
            
            // retrieve the data
            $tree=new Baobab($db,$tree_name,$tree_id);
            $root=$tree->get_tree();
            
            if ($root!==NULL) {
                $data=array(array()); // the inner array emulate a node to gain root as child
                self::_traverse_tree_to_export_data($root,$data,$fieldsFlags,$tmp_ar["fields"]);
                if (!empty($data[0][0])) $tmp_ar["values"]=&$data[0][0];
                
                $ar_out[]=$tmp_ar;
            }
        }
        
        return json_encode($ar_out);
    }


}



?>