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

    public function __construct($db,$err_str=NULL,$err_code=NULL) {
        if (!$err_str) $err_str=$db->error;
        if (!$err_code) $err_code=$db->errno;
        parent::__construct($err_str,$err_code);
   }
}


/**!
 * .. class:: sp_SQLUtil
 *    
 *    Class with helpers to work with SQL
 */
class sp_SQLUtil {
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
     *       php> echo sp_SQLUtil::vector_to_sql_tuple(array("i'm a string",28,NULL,FALSE));
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
     *       php> echo sp_SQLUtil::array_to_sql_assignments($myArray);
     *        `city address` = 'main street' , `married` = FALSE
     *       php> echo sp_SQLUtil::array_to_sql_assignments($myArray,"AND");
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
    public $attrs;

    public $parentId;
    public $children;

    public function __construct($id,$lft,$rgt,$parentId,$attrs=NULL) {
        $this->id=$id;
        $this->lft=$lft;
        $this->rgt=$rgt;
        $this->attrs=$attrs;
        $this->parentId=$parentId;
        
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
    
    public function __toString($indent="",$deep=True) {
        $out.=$indent."($this->id) [$this->lft,$this->rgt]";
        if (!$deep) return $out;
        foreach($this->children as $child) $out.="\n".$child->__toString($indent."    ");
        return $out;
    }
}


class Baobab  {
    protected $db;
    protected $tree_name;
    private $_must_check_ids=FALSE;
    
    /**!
     * .. class:: Baobab($db,$tree_name,$must_check_ids=FALSE)
     *    
     *    This class lets you create, populate search and destroy a tree stored
     *    using the Nested Set Model described by Joe Celko's
     *
     *    :param $db: mysqli database connection
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
        throw new sp_Error("not a valid id: $id");
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

        while($this->db->more_results()) {
            $result = $this->db->use_result();
            if ($result) $result->close();
            $this->db->next_result();
        }
        
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
        if (!$this->db->multi_query("
                DROP PROCEDURE IF EXISTS Baobab_getNthChild_$this->tree_name;
                DROP PROCEDURE IF EXISTS Baobab_MoveSubtree_real_$this->tree_name;
                DROP PROCEDURE IF EXISTS Baobab_MoveSubtreeAtIndex_$this->tree_name;
                DROP PROCEDURE IF EXISTS Baobab_MoveSubtreeBefore_$this->tree_name;
                DROP PROCEDURE IF EXISTS Baobab_MoveSubtreeAfter_$this->tree_name;
                DROP PROCEDURE IF EXISTS Baobab_InsertChildAtIndex_$this->tree_name;
                DROP PROCEDURE IF EXISTS Baobab_InsertNodeBefore_$this->tree_name;
                DROP PROCEDURE IF EXISTS Baobab_InsertNodeAfter_$this->tree_name;
                DROP PROCEDURE IF EXISTS Baobab_AppendChild_$this->tree_name;
                DROP PROCEDURE IF EXISTS Baobab_DropTree_$this->tree_name;
                DROP VIEW IF EXISTS Baobab_AdjTree_$this->tree_name;
                DROP TABLE IF EXISTS Baobab_$this->tree_name")) {
            throw new sp_MySQL_Error($this->db);
        }

        while($this->db->more_results()) {
            $result = $this->db->use_result();
            if ($result) $result->close();
            $this->db->next_result();
        }



    }
    
    /**!
     * .. method:: clean()
     *    
     *    Delete all the record from the Baobab_{yoursuffix} table and
     *      reset the index conter.
     *
     */
    public function clean() {
        if (!$this->db->query("TRUNCATE TABLE Baobab_$this->tree_name")) {
            return sp_MySQL_Error($this->db);
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
          FROM Baobab_$this->tree_name
          WHERE lft = 1;
        ";

        $out=NULL;

        if ($result=$this->db->query($query,MYSQLI_STORE_RESULT)) {
            if ($result->num_rows===0) {
                $result->close();
                return NULL;
            }

            $row = $result->fetch_row();
            $out=intval($row[0]);
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

    /*
     *
     */
    public function get_path($id_node,$attrName="id"){
        $this->_check_id($id_node);

        $query="".
        " SELECT id".sprintf(", `%s`", str_replace("`","``",$attrName)).
        " FROM Baobab_$this->tree_name".
        " WHERE ( SELECT lft FROM Baobab_$this->tree_name WHERE id = $id_node ) BETWEEN lft AND rgt".
        " ORDER BY lft";

        $result = $this->db->query($query,MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->db);

        $ar_out=array();
        while($row = $result->fetch_row()) {
            array_push($ar_out,array($row[0],$row[1]));
        }
        $result->close();

        return $ar_out;
    }

    public function get_children($id_node) {
        $this->_check_id($id_node);

        $query="SELECT child FROM Baobab_AdjTree_$this->tree_name WHERE parent = $id_node";

        $result = $this->db->query($query,MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->db);

        $ar_out=array();
        while($row = $result->fetch_row()) {
            array_push($ar_out,$row[0]);
        }
        $result->close();

        return $ar_out;
    }

    public function get_first_child($id_node) {
        $this->_check_id($id_node);
        
        $query="
          SELECT child
          FROM Baobab_AdjTree_$this->tree_name
          WHERE parent = $id_node AND lft = (SELECT min(lft) FROM Baobab_AdjTree_$this->tree_name WHERE PARENT = $id_node )";
        $result = $this->db->query($query,MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->db);

        $row = $result->fetch_row();
        $out=$row[0];

        $result->close();
        return $out;
    }
    
    public function get_last_child($id_node) {
        $this->_check_id($id_node);

        $query="
          SELECT child
          FROM Baobab_AdjTree_$this->tree_name
          WHERE parent = $id_node AND lft = (SELECT max(lft) FROM Baobab_AdjTree_$this->tree_name WHERE PARENT = $id_node )";
        $result = $this->db->query($query,MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->db);

        $row = $result->fetch_row();
        $out=$row[0];

        $result->close();
        return $out;
    }

    // O(n)
    public function get_tree($className="BaobabNode") {

        // this is a specialized versione of the query found in get_level()

        $query="
          SELECT (COUNT(T1.id) - 1) AS level ,T2.*
          FROM Baobab_$this->tree_name AS T1, Baobab_$this->tree_name AS T2
          WHERE T2.lft BETWEEN T1.lft AND T1.rgt
          GROUP BY T2.lft
          ORDER BY T2.lft ASC;
        ";



        $root=NULL;
        $parents=array();

        if ($result = $this->db->query($query,MYSQLI_STORE_RESULT)) {
            while($row = $result->fetch_assoc()) {




                $tmp_node=new $className($row["id"],$row["lft"],$row["rgt"],NULL);
                
                $attrs=array();
                foreach($row as $key=>$value) {
                    if (! in_array($key,array("id","lft","rgt","level")))
                        $attrs[$key]=$value;
                }
                $tmp_node->attrs=$attrs;

                if ($root===NULL) {
                    $root=$tmp_node;
                    array_push($parents,array($tmp_node,0));
                    continue;
                }

                // previous parent is the first one with a level minor than ours
                if ($parents[count($parents)-1][1]>=$row["level"]) {
                    // remove all the previous subtree "parents" until our real parent
                    for($i=count($parents)-1;$parents[$i--][1]>=$row["level"];)
                        array_pop($parents);

                }

                $idx=count($parents)-1;
                $tmp->node->parent=$parents[$idx][0];
                $parents[$idx][0]->add_child($tmp_node);
                array_push($parents,array($tmp_node,$row["level"]));

                $tmp_node->parentId=$parents[$idx][0]->id;


            }
            $result->close();

        } else throw new sp_MySQL_Error($this->db);

        return $root;



        return;
        ////////////////////////////////////////////

        $root=NULL;
        $parents=array();
        
        foreach($this->get_levels() as $tmp_ar) {

            $tmp_node=new $className($tmp_ar["id"], NULL);
            if ($root===NULL) {
                $root=$tmp_node;
                array_push($parents,array($tmp_node,0));
                continue;
            }

            // previous parent is the first one with a level minor than ours
            if ($parents[count($parents)-1][1]>=$tmp_ar["level"]) {
                // remove all the previous subtree "parents" until our real parent
                for($i=count($parents)-1;$parents[$i--][1]>=$tmp_ar["level"];)
                    array_pop($parents);

            }

            $idx=count($parents)-1;
            $tmp->node->parent=$parents[$idx][0];
            $parents[$idx][0]->add_child($tmp_node);
            array_push($parents,array($tmp_node,$tmp_ar["level"]));
        }

        return $root;

    }

    /* Delete $id_node and all of his children
     * If $update_numbering is true (default), keep the Modified Preorder Tree consistent closing gaps
     */
    public function delete_subtree($id_node,$update_numbering=True) {
        $this->_check_id($id_node);
        
        if ($update_numbering) {
            
            if (!$this->db->multi_query("CALL Baobab_DropTree_$this->tree_name($id_node)"))
                throw new sp_MySQL_Error($this->db);

            while($this->db->more_results()) {
                $result = $this->db->use_result();
                if ($result) $result->close();
                $this->db->next_result();
            }
        } else {
            throw new Error("unimplemented"); // XXX TODO Add or remove functionality
        }
    }

    public function update_numbering() {

        $query="
          UPDATE Baobab_$this->tree_name
          SET lft = (SELECT COUNT(*)
                     FROM (
                           SELECT lft as seq_nbr FROM Baobab_$this->tree_name
                           UNION ALL
                           SELECT rgt FROM Baobab_$this->tree_name
                          ) AS LftRgt
                     WHERE seq_nbr <= lft
                    ),
              rgt = (SELECT COUNT(*)
                     FROM (
                           SELECT lft as seq_nbr FROM Baobab_$this->tree_name
                           UNION ALL
                           SELECT rgt FROM Baobab_$this->tree_name
                          ) AS LftRgt
                     WHERE seq_nbr <= rgt
                    );
        
        ";

        if(!$this->db->query($query)) {
            throw new sp_MySQL_Error($this->db);
        }
        

    }

    /*
     * Calculate the height of a subtree.
     * If $id_node is NULL use tree root to start calculating the height
     * 
     */
    public function get_tree_height($id_node=NULL){
        if ($id_node!==NULL) $this->_check_id($id_node);

        $query="
        SELECT MAX(level)+1 as height
        FROM (SELECT t2.id as id,(COUNT(t1.id)-1) as level
              FROM Baobab_$this->tree_name as t1, Baobab_$this->tree_name as t2
              WHERE t2.lft  BETWEEN t1.lft AND t1.rgt
              GROUP BY t2.id
             ) as ID_LEVELS
        ".($id_node!==NULL ?
           "WHERE id = $id_node" : "");
        
        $result = $this->db->query($query,MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->db);

        $row = $result->fetch_row();
        $out=$row[0];

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
         " UPDATE Baobab_$this->tree_name".
         " SET ".( sp_SQLUtil::array_to_sql_assignments($attrs) ).
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

        if ($id_parent===NULL) $id_parent=0;
        else $this->_check_id($id_parent);

        if (!$this->db->multi_query("
                CALL Baobab_AppendChild_$this->tree_name($id_parent,@new_id);
                SELECT @new_id as id"))
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
        $this->_check_id($id_sibling);

        if (!$this->db->multi_query("
                CALL Baobab_InsertNodeAfter_{$this->tree_name}({$id_sibling},@new_id);
                SELECT @new_id as id"))
                throw new sp_MySQL_Error($this->db);

        $this->db->next_result();
        $result = $this->db->use_result();
        $new_id=intval(array_pop($result->fetch_row()));
        $result->close();
        
        if ($new_id===0) throw new sp_Error("Can't add to root");

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
        $this->_check_id($id_sibling);

        if (!$this->db->multi_query("
                CALL Baobab_InsertNodeBefore_{$this->tree_name}({$id_sibling},@new_id);
                SELECT @new_id as id"))
                throw new sp_MySQL_Error($this->db);

        $this->db->next_result();
        $result = $this->db->use_result();
        $new_id=intval(array_pop($result->fetch_row()));
        $result->close();
        
        if ($new_id===0) throw new sp_Error("Can't add to root");

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
     *                   Negative indexes are allowed.
     *    :type $index:  int
     *
     *    :return: id of the new node
     *    :rtype:  int
     *
     *    .. note::
     *       Using -1 will cause the node to be inserted before the last sibling
     * 
     */
    public function insertChildAtIndex($id_parent,$index) {
        $this->_check_id($id_parent);

        if (!$this->db->multi_query("
                CALL Baobab_InsertChildAtIndex_{$this->tree_name}({$id_parent},{$index},@new_id);
                SELECT @new_id as id"))
            throw new sp_MySQL_Error($this->db);

        $this->db->next_result();
        $result = $this->db->use_result();
        $new_id=intval(array_pop($result->fetch_row()));
        $result->close();

        if ($new_id===0) throw new sp_Error("Index out of range (parent[$id_parent],index[$index])");

        return $new_id;
    }
    
    public function moveSubTreeAfter($id_to_move,$reference_node) {
        $this->_check_id($id_to_move);
        $this->_check_id($reference_node);

        if (!$this->db->multi_query("
                CALL Baobab_MoveSubtreeAfter_$this->tree_name($id_to_move,$reference_node)"))
            throw new sp_MySQL_Error($this->db);
    }

    public function moveSubTreeBefore($id_to_move,$reference_node) {
        $this->_check_id($id_to_move);
        $this->_check_id($reference_node);

        if (!$this->db->multi_query("
                CALL Baobab_MoveSubtreeBefore_$this->tree_name($id_to_move,$reference_node)"))
            throw new sp_MySQL_Error($this->db);
    }

    public function moveSubTreeAtIndex($id_to_move,$id_parent,$index) {
        $this->_check_id($id_to_move);
        $this->_check_id($id_parent);
        
        if (!$this->db->multi_query("
                CALL Baobab_MoveSubtreeAtIndex_$this->tree_name($id_to_move,$id_parent,$index,@error_code);
                SELECT @error_code as error_id"))
            throw new sp_MySQL_Error($this->db);

        $this->db->next_result();
        $result = $this->db->use_result();
        $error_code=intVal(array_pop($result->fetch_row()));
        $result->close();
        
        if ($error_code!==0) throw new sp_Error("Index out of range (parent[$id_parent],index[$index])");
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
        $result=$this->db->query("SELECT * FROM Baobab_$this->tree_name ORDER BY lft ASC",MYSQLI_STORE_RESULT);
        if (!$result)  throw new sp_MySQL_Error($this->db);
        
        // retrieve the column names
        $fieldFlags=array();
        while ($finfo = mysqli_fetch_field($result)) {
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
                join(", ",array_map("sp_SQLUtil::vector_to_sql_tuple",$data["values"]))
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