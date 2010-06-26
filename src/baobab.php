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

    function __construct($db,$err_str=NULL,$err_code=NULL) {
        if (!$err_str) $err_str=$db->error;
        if (!$err_code) $err_code=$db->errno;
        parent::__construct($err_str,$err_code);
   }
}

/*
 * Static representation of a baobab node.
 *
 * The add_child method doesn't affect the database, it's meant only
 *   for tree construction and his use should be avoided
 */
class BaobabNode {
    public $id;
    public $lft;
    public $rgt;
    public $attrs;

    public $parentId;
    public $children;

    function __construct($id,$lft,$rgt,$parentId,$attrs=NULL) {
        $this->id=$id;
        $this->lft=$lft;
        $this->rgt=$rgt;
        $this->attrs=$attrs;
        $this->parentId=$parentId;
        
        $this->children=array();
    }

    function add_child($child) {
        array_push($this->children,$child);
    }
    
    function __toString($indent="",$deep=True) {
        $out.=$indent."node ($this->id) [$this->lft,$this->rgt]";
        if (!$deep) return $out;
        foreach($this->children as $child) $out.="\n".$child->__toString($indent."    ");
        return $out;
    }
}

class Baobab  {
    
    /* transform an array in a valid SQL tuple
     * 
     * e.g
     *      echo vector_to_sql_tuple(array("i'm a string",28,NULL,FALSE));
     *       => ('i''m a string', 28, NULL, FALSE)
     * 
     */
    private static function vector_to_sql_tuple($ar) {
        $tmp="";
        for($i=0,$il=count($ar);$i<$il;$i++) {
            if ($ar[$i]===NULL) $tmp.="NULL";
            else if (is_bool($ar[$i])) $tmp.=($ar[$i] ? "TRUE" : "FALSE");
            else $tmp.= "'".addslashes($ar[$i])."'";

            if ($i+1!==$il) $tmp.=", ";
        }
        return "( ".$tmp." )";
    }

    /*
     * Convert an associative array in a series of "columnName = value" expressions
     *  as valid SQL.
     * The expressions are separeted using the parameter $sep, defaults to ","
     *
     * e.g.
     *   echo assoc_to_sql_assignments(array("city address"=>"main street","married"=>false));
     *   => `city address` = 'main street' , `married` = FALSE
     *
     *   echo assoc_to_sql_assignments(array("city address"=>"main street","married"=>false),"AND"); // <-- notice the AND
     *   => `city address` = 'main street' AND `married` = FALSE
     */
    private static function assoc_to_sql_assignments($ar,$sep=",") {
        $tmp="";
        $i=0;
        $il=count($ar);
        foreach($ar as $key=>$value) {

            if ($value===NULL) $value="NULL";
            else if (is_bool($value)) $value=($value ? "TRUE" : "FALSE");
            else $value= "'".addslashes($value)."'";

            $tmp.=sprintf(" `%s` = %s ",str_replace("`","``",$key),$value);

            if ($i+1!==$il) $tmp.=$sep;
            $i++;
        }
        return $tmp;
    }

    /*
     * Check an id for validity (it must be an integer present in
     *   the Baobab table used by the current instance).
     * Throws an sp_Error if $id is not valid
     *
     * Any activity of this function can be stopped setting the instance
     *   member "must_check_ids" to FALSE at construction time or runtime
     */
    private function check_id($id) {
        if (!$this->must_check_ids) return;

        $id=intVal($id);
        if ($id>0 && ($result = $this->conn->query("SELECT id FROM Baobab_$this->tree_name WHERE id = $id",MYSQLI_STORE_RESULT))) {
            if ($result->num_rows) {
                $result->close();
                return;
            }
        }
        throw new sp_Error("not a valid id: $id");
    }

    public function __construct($conn,$tree_name,$must_check_ids=TRUE) {
        $this->conn=$conn;
        $this->tree_name=$tree_name;
        $this->enableCheck($must_check_ids);
    }

    public function enableCheck($bool) {
        $this->must_check_ids=$bool;
    }

    public function isCheckEnabled() {
        return $this->must_check_ids;
    }
    
    public function build() {

        $sql=file_get_contents(dirname(__FILE__).DIRECTORY_SEPARATOR."schema_baobab.sql");

        if (!$this->conn->multi_query(str_replace("GENERIC",$this->tree_name,$sql))) {
            throw new sp_MySQL_Error($this->conn);
        }

        while($this->conn->more_results()) {
            $result = $this->conn->use_result();
            if ($result) $result->close();
            $this->conn->next_result();
        }
        
    }

    public function destroy() {
        if (!$this->conn->multi_query("
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
            throw new sp_MySQL_Error($this->conn);
        }

        while($this->conn->more_results()) {
            $result = $this->conn->use_result();
            if ($result) $result->close();
            $this->conn->next_result();
        }



    }

    /**
     * .. method:: clean()
     *    
     *    Delete all the record from the Baobab_{yoursuffix} table and
     *      reset the index conter.
     *
     */
    function clean() {
        if (!$this->conn->query("TRUNCATE TABLE Baobab_$this->tree_name")) {
            return sp_MySQL_Error($this->conn);
        }
    }


    /*
     * .. method:: get_root()
     *    
     *    Return the id of the first node of the tree.
     *
     *    :return: id of the root, or NULL if empty
     *    :rtype:  int or NULL
     *
     */
    function get_root(){

        $query="
          SELECT id AS root
          FROM Baobab_$this->tree_name
          WHERE lft = 1;
        ";

        $out=NULL;

        if ($result=$this->conn->query($query,MYSQLI_STORE_RESULT)) {
            if ($result->num_rows===0) {
                $result->close();
                return NULL;
            }

            $row = $result->fetch_row();
            $out=$row[0];
            $result->close();

        } else throw new sp_MySQL_Error($this->conn);

        return $out;
    }


    /*
     * Return the number of nodes of the subtree starting at $id_node,
     *  counting $id_node too.
     *
     * If $id_node is NULL (default) the nodes are counted starting
     *   from the root of the tree
     */
    function get_tree_size($id_node=NULL) {
        if ($id_node!==NULL) $this->check_id($id_node);

        $query="
          SELECT (rgt-lft+1) DIV 2
          FROM Baobab_$this->tree_name
          WHERE ". ($id_node!==NULL ? "id = ".addslashes($id_node) : "lft = 1");
        
        $out=NULL;

        if ($result = $this->conn->query($query,MYSQLI_STORE_RESULT)) {
            $row = $result->fetch_row();
            $out=$row[0];
            $result->close();

        } else throw new sp_MySQL_Error($this->conn);

        return $out;

    }

    function get_descendants($id_node=NULL) {

        if ($id_node===NULL) {
            // we search for descendants of root
            $query="SELECT id FROM Baobab_$this->tree_name WHERE lft <> 1";
        } else {
            // we search for a node descendants
            $query="
              SELECT id
              FROM Baobab_$this->tree_name
              WHERE lft > (SELECT lft FROM Baobab_$this->tree_name WHERE id = $id_node)
                AND rgt < (SELECT rgt FROM Baobab_$this->tree_name WHERE id = $id_node)
            ";
        }
        
        $ar_out=array();

        if ($result = $this->conn->query($query,MYSQLI_STORE_RESULT)) {
            while($row = $result->fetch_row()) {
                array_push($ar_out,$row[0]);
            }
            $result->close();

        } else throw new sp_MySQL_Error($this->conn);

        return $ar_out;

    }

    function get_leaves($id_node=NULL){
        if ($id_node!==NULL) $this->check_id($id_node);

        $query="
          SELECT id AS leaf
          FROM Baobab_$this->tree_name
          WHERE lft = (rgt - 1) ";

        if ($id_node!==NULL) {
            // check only leaves of a subtree adding a "where" condition

            $query.=" AND lft > (SELECT lft FROM Baobab_$this->tree_name WHERE id = $id_node) ".
                    " AND rgt < (SELECT rgt FROM Baobab_$this->tree_name WHERE id = $id_node) ";
        }


        $ar_out=array();

        if ($result = $this->conn->query($query,MYSQLI_STORE_RESULT)) {
            while($row = $result->fetch_row()) {
                array_push($ar_out,$row[0]);
            }
            $result->close();

        } else throw new sp_MySQL_Error($this->conn);

        return $ar_out;
    }
    
    function get_levels(){
    
        $query="
          SELECT T2.id as id, (COUNT(T1.id) - 1) AS level
          FROM Baobab_$this->tree_name AS T1, Baobab_$this->tree_name AS T2
          WHERE T2.lft BETWEEN T1.lft AND T1.rgt
          GROUP BY T2.lft
          ORDER BY T2.lft ASC;
        ";

        $ar_out=array();

        if ($result = $this->conn->query($query,MYSQLI_STORE_RESULT)) {
            while($row = $result->fetch_assoc()) {
                array_push($ar_out,array("id"=>$row["id"],"level"=>$row["level"]));
            }
            $result->close();

        } else throw new sp_MySQL_Error($this->conn);

        return $ar_out;
    }

    /*
     *
     */
    function get_path($id_node,$attrName="id"){
        $this->check_id($id_node);

        $query="".
        " SELECT id".sprintf(", `%s`", str_replace("`","``",$attrName)).
        " FROM Baobab_$this->tree_name".
        " WHERE ( SELECT lft FROM Baobab_$this->tree_name WHERE id = $id_node ) BETWEEN lft AND rgt".
        " ORDER BY lft";

        $result = $this->conn->query($query,MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->conn);

        $ar_out=array();
        while($row = $result->fetch_row()) {
            array_push($ar_out,array($row[0],$row[1]));
        }
        $result->close();

        return $ar_out;
    }

    function get_children($id_node) {
        $this->check_id($id_node);

        $query="SELECT child FROM Baobab_AdjTree_$this->tree_name WHERE parent = $id_node";

        $result = $this->conn->query($query,MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->conn);

        $ar_out=array();
        while($row = $result->fetch_row()) {
            array_push($ar_out,$row[0]);
        }
        $result->close();

        return $ar_out;
    }

    function get_first_child($id_node) {
        $this->check_id($id_node);
        
        $query="
          SELECT child
          FROM Baobab_AdjTree_$this->tree_name
          WHERE parent = $id_node AND lft = (SELECT min(lft) FROM Baobab_AdjTree_$this->tree_name WHERE PARENT = $id_node )";
        $result = $this->conn->query($query,MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->conn);

        $row = $result->fetch_row();
        $out=$row[0];

        $result->close();
        return $out;
    }
    
    function get_last_child($id_node) {
        $this->check_id($id_node);

        $query="
          SELECT child
          FROM Baobab_AdjTree_$this->tree_name
          WHERE parent = $id_node AND lft = (SELECT max(lft) FROM Baobab_AdjTree_$this->tree_name WHERE PARENT = $id_node )";
        $result = $this->conn->query($query,MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->conn);

        $row = $result->fetch_row();
        $out=$row[0];

        $result->close();
        return $out;
    }

    // O(n)
    function get_tree($className="BaobabNode") {

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

        if ($result = $this->conn->query($query,MYSQLI_STORE_RESULT)) {
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

        } else throw new sp_MySQL_Error($this->conn);

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
    function delete_subtree($id_node,$update_numbering=True) {
        $this->check_id($id_node);
        
        if ($update_numbering) {
            
            if (!$this->conn->multi_query("CALL Baobab_DropTree_$this->tree_name($id_node)"))
                throw new sp_MySQL_Error($this->conn);

            while($this->conn->more_results()) {
                $result = $this->conn->use_result();
                if ($result) $result->close();
                $this->conn->next_result();
            }
        } else {
            throw new Error("unimplemented"); // XXX TODO Add or remove functionality
        }
    }

    function update_numbering() {

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

        if(!$this->conn->query($query)) {
            throw new sp_MySQL_Error($this->conn);
        }
        

    }

    /*
     * Calculate the height of a subtree.
     * If $id_node is NULL use tree root to start calculating the height
     * 
     */
    function get_tree_height($id_node=NULL){
        if ($id_node!==NULL) $this->check_id($id_node);

        $query="
        SELECT MAX(level)+1 as height
        FROM (SELECT t2.id as id,(COUNT(t1.id)-1) as level
              FROM Baobab_$this->tree_name as t1, Baobab_$this->tree_name as t2
              WHERE t2.lft  BETWEEN t1.lft AND t1.rgt
              GROUP BY t2.id
             ) as ID_LEVELS
        ".($id_node!==NULL ?
           "WHERE id = $id_node" : "");
        
        $result = $this->conn->query($query,MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->conn);

        $row = $result->fetch_row();
        $out=$row[0];

        $result->close();
        return $out;
    }

    /*
     *
     * while usable, $disableCheck is meant for internal use only.
     * it gives the same behaviour of
     *      $tmpValue=$this->isCheckEnabled();
     *      $this->enableCheck(false);
     *      $this->updateNode($foo,$moo);
     *      $this->enableCheck($tmpValue);
     *
     */
    function updateNode($id_node,$attrs,$disableCheck=False){
        if (!$disableCheck) $this->check_id($id_node);

        if (!$attrs) throw new sp_Error("\$attrs must be a non empty array");

        $query="".
         " UPDATE Baobab_$this->tree_name".
         " SET ".( Baobab::assoc_to_sql_assignments($attrs) ).
         " WHERE id = @new_id";
        
        $result = $this->conn->query($query,MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->conn);
    }
    
    /*
     * .. method:: appendChild([$id_parent,[$attrs]])
     *    
     *    Create and append a node as last child of a parent node.
     *
     *    :param $id_parent: id of the parent node
     *    :type $id_parent: int or NULL
     *    :param $attrs: array fields=>values to assign to the new node
     *    :type $attrs: array or NULL
     *    :return: id of the root, or NULL if empty
     *    :rtype:  int or NULL
     *
     */
    function appendChild($id_parent=NULL,$attrs=NULL){

        if ($id_parent===NULL) $id_parent=0;
        else $this->check_id($id_parent);

        if (!$this->conn->multi_query("
                CALL Baobab_AppendChild_$this->tree_name($id_parent,@new_id);
                SELECT @new_id as id"))
                throw new sp_MySQL_Error($this->conn);

        // reach the last result and read it
        while($this->conn->more_results()) $this->conn->next_result();
        $result = $this->conn->use_result();
        $new_id=array_pop($result->fetch_row());
        $result->close();

        //update the node if needed
        if ($attrs!==NULL) $this->updateNode($new_id,$attrs,TRUE);

        return $new_id;
    }
    
    function insertNodeAfter($id_sibling,$attrs=NULL) {
        $this->check_id($id_sibling);

        if (!$this->conn->multi_query("
                CALL Baobab_InsertNodeAfter_$this->tree_name($id_sibling,@new_id);
                SELECT @new_id as id"))
                throw new sp_MySQL_Error($this->conn);

        $this->conn->next_result();
        $result = $this->conn->use_result();
        $new_id=array_pop($result->fetch_row());
        $result->close();
        
        if ($new_id===0) return new sp_Error("Can't add to root");

        //update the node if needed
        if ($attrs!==NULL) $this->updateNode($new_id,$attrs,TRUE);

        return $new_id;
    }

    function insertNodeBefore($id_sibling,$attrs=NULL) {
        $this->check_id($id_sibling);

        if (!$this->conn->multi_query("
                CALL Baobab_InsertNodeBefore_$this->tree_name($id_sibling,@new_id);
                SELECT @new_id as id"))
                throw new sp_MySQL_Error($this->conn);

        $this->conn->next_result();
        $result = $this->conn->use_result();
        $new_id=array_pop($result->fetch_row());
        $result->close();
        
        if ($new_id===0) return new sp_Error("Can't add to root");

        //update the node if needed
        if ($attrs!==NULL) $this->updateNode($new_id,$attrs,TRUE);

        return $new_id;
    }

    function insertChildAtIndex($id_parent,$index) {
        $this->check_id($id_parent);

        if (!$this->conn->multi_query("
                CALL Baobab_InsertChildAtIndex_$this->tree_name($id_parent,$index,@new_id);
                SELECT @new_id as id"))
            throw new sp_MySQL_Error($this->conn);

        $this->conn->next_result();
        $result = $this->conn->use_result();
        $new_id=array_pop($result->fetch_row());
        $result->close();

        if ($new_id===0) throw new sp_Error("Index out of range (parent[$id_parent],index[$index])");

        return $new_id;
    }
    
    function moveSubTreeAfter($id_to_move,$reference_node) {
        $this->check_id($id_to_move);
        $this->check_id($reference_node);

        if (!$this->conn->multi_query("
                CALL Baobab_MoveSubtreeAfter_$this->tree_name($id_to_move,$reference_node)"))
            throw new sp_MySQL_Error($this->conn);
    }

    function moveSubTreeBefore($id_to_move,$reference_node) {
        $this->check_id($id_to_move);
        $this->check_id($reference_node);

        if (!$this->conn->multi_query("
                CALL Baobab_MoveSubtreeBefore_$this->tree_name($id_to_move,$reference_node)"))
            throw new sp_MySQL_Error($this->conn);
    }

    function moveSubTreeAtIndex($id_to_move,$id_parent,$index) {
        $this->check_id($id_to_move);
        $this->check_id($id_parent);
        
        if (!$this->conn->multi_query("
                CALL Baobab_MoveSubtreeAtIndex_$this->tree_name($id_to_move,$id_parent,$index,@error_code);
                SELECT @error_code as error_id"))
            throw new sp_MySQL_Error($this->conn);

        $this->conn->next_result();
        $result = $this->conn->use_result();
        $error_code=intVal(array_pop($result->fetch_row()));
        $result->close();
        
        if ($error_code!==0) throw new sp_Error("Index out of range (parent[$id_parent],index[$index])");
    }


    /*
     * Return a JSON dump of the tree
     * 
     */
    function export() {

        $ar_out=array("columns"=>array(),"values"=>array());

        // retrieve the column names

        $result=$this->conn->query("SHOW COLUMNS FROM Baobab_GENERIC;",MYSQLI_STORE_RESULT);
        if (!$result)  throw new sp_MySQL_Error($this->conn);

        while($row = $result->fetch_array(MYSQLI_ASSOC)) {
            array_push($ar_out["columns"],$row["Field"]);
        }
        $result->close();

        // retrieve the data

        $result=$this->conn->query("SELECT * FROM Baobab_$this->tree_name ORDER BY lft ASC",MYSQLI_STORE_RESULT);
        if (!$result)  throw new sp_MySQL_Error($this->conn);
        
        while($row = $result->fetch_array(MYSQLI_NUM)) {
            array_push($ar_out["values"],$row);
        }
        $result->close();
        
        return strtolower(json_encode($ar_out));
    }

    /*
     * Load data previously exported via the export method
     *
     * $data can be either a json string or the decoded associative array
     *
     * Note: associative array format is
     *
     *    array(
     *      "columns" => array("column1","column2", ... ),
     *      "data" => array(
     *          array("value1","value2","value3", ... ),
     *          array("value1","value2","value3", ... ),
     *          ...
     *      )
     *    )
     */
    function import($data){
        if (is_string($data)) $data=json_decode($data,true);
        if (!$data) return;
        
        $result=$this->conn->query(
                "INSERT INTO Baobab_$this->tree_name VALUES ".
                join(", ",array_map("Baobab::vector_to_sql_tuple",$data["values"]))
            ,MYSQLI_STORE_RESULT);
        if (!$result)  throw new sp_MySQL_Error($this->conn);

    }


}


class BaobabNamed extends Baobab {

    public function build() {
        parent::build();

        $result = $this->conn->query("ALTER TABLE Baobab_$this->tree_name ADD COLUMN label TEXT DEFAULT '' NOT NULL",MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->conn);
    }
    

}

?>