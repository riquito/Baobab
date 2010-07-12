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


class sp_Error extends Exception { }

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
 */
class sp_SQLUtils {
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
     *    .. code-block:: php
     *       php> echo sp_SQLUtils::vector_to_sql_tuple(array("i'm a string",28,NULL,FALSE));
     *       ( 'i\'m a string','28',NULL,FALSE )
     * 
     */
    public static function vector_to_sql_tuple($ar) {
        $tmp=array();
        foreach($ar as $value) {
            if ($value===NULL) $tmp[]="NULL";
            else if (is_bool($value)) $tmp[]=($value ? "TRUE" : "FALSE");
            else $tmp[]="'".addslashes($value)."'";
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
     *    .. code-block:: php
     *       php> $myArray=array("city address"=>"main street","married"=>false);
     *       php> echo sp_SQLUtils::array_to_sql_assignments($myArray);
     *        `city address` = 'main street' , `married` = FALSE
     *       php> echo sp_SQLUtils::array_to_sql_assignments($myArray,"AND");
     *        `city address` = 'main street' AND `married` = FALSE 
     */
    public static function array_to_sql_assignments($ar,$sep=",") {
        $tmp=array();
        foreach($ar as $key=>$value) {
            if ($value===NULL) $value="NULL";
            else if (is_bool($value)) $value=($value ? "TRUE" : "FALSE");
            else $value= "'".addslashes($value)."'";
            
            $tmp[]=sprintf(" `%s` = %s ",str_replace("`","``",$key),$value);
        }
        return join($sep,$tmp);
    }
    
    /**!
     * .. method:: flush_results($conn)
     *    
     *    Empty connection results of the last single or multi query.
     *    If the last query generated an error, a sp_MySQL_Error exception
     *      is raised.
     *
     *    :param $conn: mysqli connection, object oriented version
     *    :type $conn:  mysqli instance
     **/
    public function flush_results($conn){
        while($conn->more_results()) {
            if ($result = $conn->use_result()) $result->close();
            $conn->next_result();
        }
        
        if ($conn->errno) throw new sp_MySQL_Error($conn);
    }
}


/**!
 * .. class:: BaobabNode($id,$lft,$rgt,$parentId[,$attrs=NULL])
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
 *    :param $attrs: additional fields of the node, as fieldName=>value
 *    :type $attrs: array or NULL
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
    public $fields;

    
    public $children;

    public function __construct($id,$lft,$rgt,&$parentNode,$fields=NULL) {
        $this->id=$id;
        $this->lft=$lft;
        $this->rgt=$rgt;
        $this->parentNode=&$parentNode;
        $this->fields=$fields;
        
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
     **/
    public function add_child($child) {
        $this->children[]=$child;
    }
    
    public function stringify($indent="",$deep=True) {
        $out.=$indent."({$this->id}) [{$this->lft},{$this->rgt}]";
        if (!$deep) return $out;
        foreach($this->children as $child) $out.="\n".$child->stringify($indent."    ");
        return $out;
    }
    
    public function is_rightmost(){
        if (!$this->parentNode) return TRUE;
        
        return $this->parentNode->children[count($this->parentNode->children)-1]->id===$this->id;
    }
}


class Baobab  {
    protected $db;
    protected $tree_name;
    private $_must_check_ids;
    private $_errors;
    
    /**!
     * .. class:: Baobab($db,$tree_name[,$must_check_ids=FALSE])
     *    
     *    This class lets you create, populate search and destroy a tree stored
     *    using the Nested Set Model described by Joe Celko's
     *
     *    :param $db: mysqli database connection in object oriented style
     *    :type $db:  an instance of mysqli_connect
     *    :param $tree_name: suffix to append to the table, wich will result in
     *                       Baobab_{$tree_name}
     *    :type $tree_name: string
     *    :param $must_check_ids: whether to constantly check the id consistency or not
     *    :type $must_check_ids: boolean
     */
    public function __construct($db,$tree_name,$must_check_ids=FALSE) {
        $this->db=$db;
        $this->tree_name=$tree_name;
        $this->enableIdCheck($must_check_ids);
        
        // load error's information from db (if tables were created)
        try { $this->_load_errors();
        } catch (sp_Error $e) {}
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
     *    Verify if id checking is enabled. See :method:`Baobab.enableIdCheck`.
     *
     *    :return: wheter to enable id checking is enabled or not
     *    :rtype:  boolean
     *
     */
    public function isIdCheckEnabled() {
        return $this->_must_check_ids;
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
        
        sp_SQLUtils::flush_results($this->db);
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
        
        sp_SQLUtils::flush_results($this->db);
    }
    
    /**!
     * .. method:: clean()
     *    
     *    Delete all the record from the table Baobab_{yoursuffix} and
     *      reset the index conter.
     *
     */
    public function clean() {
        if (!$this->db->query("TRUNCATE TABLE Baobab_{$this->tree_name}")) {
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
     **/
    public function get_descendants($id_node=NULL) {

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
    public function get_leaves($id_node=NULL){
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
    public function get_levels(){
    
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
     *    .. code-block:: php
     *       
     *       php> $tree->get_path(2,"name")
     *       array([0]=>array([id]=>1,[name]=>'rootName'),array([id]=>2,[name]=>'secondNodeName']))
     *       php> join("/",$tree->get_path(2,array("name"),TRUE))
     *       "rootName/secondNodeName"
     * 
     */
    public function get_path($id_node,$fields=NULL,$squash=FALSE){
        $id_node=intval($id_node);
        
        $this->_check_id($id_node);
        
        if (empty($fields)) {
            if ($squash) $fields=array("id");
            else $fields=array(); // ensure it is not NULL
        }
        else if (is_string($fields)) $fields=array($fields);
        
        // append the field "id" if missing
        if (FALSE===array_search("id",$fields)) $fields[]="id";
        
        $fields_escaped=array();
        foreach($fields as $fieldName) {
            // XXX at present $fields are not checked and SQL injections are possible
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
    public function get_some_children($id_parent,$howMany=NULL,$fromLeftToRight=TRUE){
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
    public function get_children($id_parent) {
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

        $this->db->next_result(); // skip the CALL result
        $result = $this->db->use_result();
        $row=$result->fetch_assoc();
        $child_id=intval($row["child_id"]);
        $error_code=intval($row["error_code"]);
        $result->close();
        
        if ($error_code) {
            throw new sp_Error(sprintf("[%s] %s",
                $this->_errors["by_code"][$error_code]["name"],
                $this->_errors["by_code"][$error_code]["msg"]),$error_code);
        }
        
        return $child_id;
    }
    
    
    /**!
     * .. method: get_tree([$className="BaobabNode"[,$addChild="add_child"]])
     *
     *    Create a tree from the database data.
     *    It's possible to use a default tree or use cusom classes/functions
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
    public function get_tree($className="BaobabNode",$addChild="add_child") {
        
        // this is a specialized version of the query found in get_level()
        //   (the difference lying in the fact that here we retrieve all the
        //    fields of the table)
        $query="
          SELECT (COUNT(T1.id) - 1) AS level ,T2.*
          FROM Baobab_{$this->tree_name} AS T1, Baobab_{$this->tree_name} AS T2
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
                
                $parentsList=array();
                foreach($parents as $key=>$abc) {
                    $parentsList[]="{$key}^".$abc->id;
                }
                $parentsList=array_reverse($parentsList);
                
                if (!$root) $root=$node;
                else $parents[$numParents-1]->$addChild($node);
                
                if ($rgt-$lft!=1) {
                    $parents[$numParents]=$node;
                }
                else if ($rgt+1==$parents[$numParents-1]->rgt) {
                    
                    $k=$numParents-1;
                    $me=$node;
                    while ($me->rgt+1 == $parents[$k]->rgt) {
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
     *         (see :Baobab:`update_numbering`)
     */
    public function delete_subtree($id_node,$close_gaps=TRUE) {
        $id_node=intval($id_node);
        $close_gaps=$close_gaps ? 1 : 0;
        
        $this->_check_id($id_node);
        
        if (!$this->db->multi_query("CALL Baobab_DropTree_{$this->tree_name}({$id_node},{$close_gaps})"))
            throw new sp_MySQL_Error($this->db);
        
        sp_SQLUtils::flush_results($this->db);
        
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
        
        if (!$this->db->multi_query("CALL Baobab_Close_Gaps_{$this->tree_name}()"))
            throw new sp_MySQL_Error($this->db);
        
        sp_SQLUtils::flush_results($this->db);

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

    /*
     *
     * while usable, $disableCheck is meant for internal use only.
     * it gives the same behaviour of
     *      $tmpValue=$this->isIdCheckEnabled();
     *      $this->enableIdCheck(false);
     *      $this->updateNode($foo,$moo);
     *      $this->enableIdCheck($tmpValue);
     *
     */
    public function updateNode($id_node,$attrs,$disableCheck=False){
        if (!$disableCheck) $this->_check_id($id_node);

        if (!$attrs) throw new sp_Error("\$attrs must be a non empty array");

        $query="".
         " UPDATE Baobab_{$this->tree_name}".
         " SET ".( sp_SQLUtils::array_to_sql_assignments($attrs) ).
         " WHERE id = @new_id";
        
        $result = $this->db->query($query,MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->db);
    }
    
    /**!
     * .. method:: appendChild([$id_parent,[$attrs]])
     *    
     *    Create and append a node as last child of a parent node.
     *
     *    :param $id_parent: id of the parent node
     *    :type $id_parent: int or NULL
     *    :param $attrs: array fields=>values to assign to the new node
     *    :type $attrs: array or NULL
     *    
     *    :return: id of the root, or NULL if empty
     *    :rtype:  int or NULL
     *
     */
    public function appendChild($id_parent=NULL,$attrs=NULL){
        
        $id_parent=intval($id_parent);
        
        if ($id_parent) $this->_check_id($id_parent);

        if (!$this->db->multi_query("
                CALL Baobab_AppendChild_{$this->tree_name}({$id_parent},@new_id);
                SELECT @new_id as new_id"))
                throw new sp_MySQL_Error($this->db);

        // reach the last result and read it
        while($this->db->more_results()) $this->db->next_result();
        $result = $this->db->use_result();
        $new_id=intval(array_pop($result->fetch_row()));
        $result->close();

        //update the node if needed
        if ($attrs!==NULL) $this->updateNode($new_id,$attrs,TRUE);

        return $new_id;
    }
    
    /**!
     * .. method:: insertNodeAfter($id_sibling[,$attrs=NULL])
     *
     *    Create a new node and insert it as the next sibling of the node
     *      chosen (which can not be root)
     *
     *    :param $id_sibling: id of a node in the tree (can not be root)
     *    :type $id_sibling:  int
     *    :param $attrs: additional fields of the new node, as fieldName=>value
     *    :type $attrs:  array
     *
     *    :return: id of the new node
     *    :rtype:  int
     * 
     */
    public function insertNodeAfter($id_sibling,$attrs=NULL) {
        $id_sibling=intval($id_sibling);
        
        $this->_check_id($id_sibling);

        if (!$this->db->multi_query("
                CALL Baobab_InsertNodeAfter_{$this->tree_name}({$id_sibling},@new_id,@error_code);
                SELECT @new_id as new_id,@error_code as error_code"))
                throw new sp_MySQL_Error($this->db);

        $this->db->next_result(); // skip the CALL result
        $result = $this->db->use_result();
        $row=$result->fetch_assoc();
        $new_id=intval($row["new_id"]);
        $error_code=intval($row["error_code"]);
        $result->close();
        
        if ($error_code) {
            throw new sp_Error(sprintf("[%s] %s",
                $this->_errors["by_code"][$error_code]["name"],
                $this->_errors["by_code"][$error_code]["msg"]),$error_code);
        }

        //update the node if needed
        if ($attrs!==NULL) $this->updateNode($new_id,$attrs,TRUE);

        return $new_id;
    }

    /**!
     * .. method:: insertNodeBefore($id_sibling[,$attrs=NULL])
     *
     *    Create a new node and insert it as the previous sibling of the node
     *      chosen (which can not be root)
     *
     *    :param $id_sibling: id of a node in the tree (can not be root)
     *    :type $id_sibling:  int
     *    :param $attrs: additional fields of the new node, as fieldName=>value
     *    :type $attrs:  array
     *
     *    :return: id of the new node
     *    :rtype:  int
     * 
     */
    public function insertNodeBefore($id_sibling,$attrs=NULL) {
        $id_sibling=intval($id_sibling);
        
        $this->_check_id($id_sibling);

        if (!$this->db->multi_query("
                CALL Baobab_InsertNodeBefore_{$this->tree_name}({$id_sibling},@new_id,@error_code);
                SELECT @new_id as new_id,@error_code as error_code"))
                throw new sp_MySQL_Error($this->db);

        $this->db->next_result(); // skip the CALL result
        $result = $this->db->use_result();
        $row=$result->fetch_assoc();
        $new_id=intval($row["new_id"]);
        $error_code=intval($row["error_code"]);
        $result->close();
        
        if ($error_code) {
            throw new sp_Error(sprintf("[%s] %s",
                $this->_errors["by_code"][$error_code]["name"],
                $this->_errors["by_code"][$error_code]["msg"]),$error_code);
        }

        //update the node if needed
        if ($attrs!==NULL) $this->updateNode($new_id,$attrs,TRUE);

        return $new_id;
    }

    /**!
     * .. method:: insertChildAtIndex($id_parent,$index)
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
     *
     *    :return: id of the new node
     *    :rtype:  int
     * 
     */
    public function insertChildAtIndex($id_parent,$index) {
        $id_parent=intval($id_parent);
        $index=intval($index);
        
        $this->_check_id($id_parent);

        if (!$this->db->multi_query("
                CALL Baobab_InsertChildAtIndex_{$this->tree_name}({$id_parent},{$index},@new_id,@error_code);
                SELECT @new_id as new_id,@error_code as error_code"))
            throw new sp_MySQL_Error($this->db);

        $this->db->next_result(); // skip the CALL result
        $result = $this->db->use_result();
        $row=$result->fetch_assoc();
        $new_id=intval($row["new_id"]);
        $error_code=intval($row["error_code"]);
        $result->close();
        
        if ($error_code) {
            throw new sp_Error(sprintf("[%s] %s",
                $this->_errors["by_code"][$error_code]["name"],
                $this->_errors["by_code"][$error_code]["msg"]),$error_code);
        }

        return $new_id;
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
                SELECT @error_code as code"))
            throw new sp_MySQL_Error($this->db);
        
        $this->db->next_result();
        $result = $this->db->use_result();
        $error_code=intval(array_pop($result->fetch_row()));
        $result->close();
        
        if ($error_code!==0) {
            throw new sp_Error(sprintf("[%s] %s",
                $this->_errors["by_code"][$error_code]["name"],
                $this->_errors["by_code"][$error_code]["msg"]),$error_code);
        }
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
        
        $this->db->next_result();
        $result = $this->db->use_result();
        $error_code=intVal(array_pop($result->fetch_row()));
        $result->close();
        
        if ($error_code!==0) {
            throw new sp_Error(sprintf("[%s] %s",
                $this->_errors["by_code"][$error_code]["name"],
                $this->_errors["by_code"][$error_code]["msg"]),$error_code);
        }
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
                SELECT @error_code as error_id"))
            throw new sp_MySQL_Error($this->db);

        $this->db->next_result();
        $result = $this->db->use_result();
        $error_code=intVal(array_pop($result->fetch_row()));
        $result->close();
        
        if ($error_code!==0) {
            throw new sp_Error(sprintf("[%s] %s",
                $this->_errors["by_code"][$error_code]["name"],
                $this->_errors["by_code"][$error_code]["msg"]),$error_code);
        }
    }


    /**!
     * .. method:: export()
     *    
     *    Create a JSON dump of the tree
     *    
     *    :return: a dump of the tree in JSON format
     *    :rtype:  string
     * 
     */
    public function export() {

        $ar_out=array("fields"=>array(),"values"=>array());
        
        // retrieve the data
        $result=$this->db->query("SELECT * FROM Baobab_{$this->tree_name} ORDER BY lft ASC",MYSQLI_STORE_RESULT);
        if (!$result)  throw new sp_MySQL_Error($this->db);
        
        // retrieve the column names
        $fieldFlags=array();
        while ($finfo = $result->fetch_field()) {
            $ar_out["fields"][]=$finfo->name;
            $fieldFlags[]=$finfo->flags;
        }
        
        // fill the value array
        while($row = $result->fetch_array(MYSQLI_NUM)) {
            $i=0;
            $tmp_ar=array();
            foreach($row as $fieldValue) {
                if ($fieldFlags[$i]&MYSQLI_NUM_FLAG!=0) $fieldValue=floatval($fieldValue);
                $tmp_ar[]=$fieldValue;
                $i++;
            }
            $ar_out["values"][]=$tmp_ar;
        }
        
        $result->close();
        
        return json_encode($ar_out);
    }

    /**!
     * .. method:: import($data)
     *    
     *    Load data previously exported via the export method.
     *    
     *    :param $data: data to import, a json string or his decoded equivalent
     *    :type $data: string(json) or array
     *    
     *    :return: id of the root, or NULL if empty
     *    :rtype:  int or NULL
     *    
     *    Associative array format is something like
     *
     *    .. code-block:: php
     *    
     *       array(
     *         "fields" => array("id","lft", "rgt"),
     *         "values" => array(
     *             array(1,1,4),
     *             array(2,2,3)
     *         )
     *       )
     *    
     *    .. note::
     *      If "id" in used and not NULL, there must not be any record on the
     *        table with that same value.
     */
    public function import($data){
        if (is_string($data)) $data=json_decode($data,true);
        if (!$data || empty($data["values"])) return;
        
        // retrieve the column names
        
        $result=$this->db->query("SHOW COLUMNS FROM Baobab_GENERIC;",MYSQLI_STORE_RESULT);
        if (!$result)  throw new sp_MySQL_Error($this->db);
        
        $real_cols=array();
        while($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $real_cols[$row["Field"]]=TRUE;
        }
        $result->close();
        
        // check that the requested fields exist
        foreach($data["fields"] as $fieldName) {
            if (!isset($real_cols[$fieldName])) throw new sp_Error("`{$fieldName}` wrong field name for table Baobab_{$this->tree_name}");
        }
        
        
        $result=$this->db->query(
                "INSERT INTO Baobab_{$this->tree_name}(".join(",",$data["fields"]).") VALUES ".
                join(", ",array_map("sp_SQLUtils::vector_to_sql_tuple",$data["values"]))
            ,MYSQLI_STORE_RESULT);
        if (!$result)  throw new sp_MySQL_Error($this->db);
        
    }


}


class BaobabNamed extends Baobab {

    public function build() {
        parent::build();

        $result = $this->db->query("ALTER TABLE Baobab_$this->tree_name ADD COLUMN label TEXT DEFAULT '' NOT NULL",MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->db);
    }
    

}

?>