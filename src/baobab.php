<?php

/**!
 * .. _api:
 * 
 * ==========
 * Baobab API
 * ==========
 *
 * Copyright and License
 * ---------------------
 * 
 * Copyright 2010-2011 Riccardo Attilio Galli <riccardo@sideralis.org> http://www.sideralis.org
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

// define BAOBAB_SQL_DIR as the absolute path with no leading / pointing to
// the root directory holding the sql files
if (!defined("BAOBAB_SQL_DIR")) define("BAOBAB_SQL_DIR", dirname(__FILE__).DIRECTORY_SEPARATOR."sql");

/**!
 * Exceptions
 * ----------
 */

/**!
 * .. class:: sp_Error
 *    
 *    Root exception. Each exception thrown in a Sideralis Programs library
 *    derive from this class
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
            $err_str = $conn_or_msg->error;
            $err_code = $conn_or_msg->errno;
        } else {
            $err_str = $conn_or_msg;
        }
        
        parent::__construct($err_str, $err_code);
    }
}


/**@
 * Utils
 * -----
 */

/**@
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
        $this->conn = $conn;
    }
    
    /**@
     * .. method:: vector_to_sql_tuple($ar)
     *    
     *    Transform an array in a valid SQL tuple. The array can contain only
     *    values of type int, float, boolean, string.
     *
     *    :param $ar: an array to convert (only array values are used)
     *    :type $ar:  array
     *
     *    :return: the generated SQL snippet
     *    :rtype:  string
     * 
     *    Example:
     *    
     *    .. code-block:: none
     *       
     *       php> $conn = new mysqli(  connection data )
     *       php> $sql_utils = new sp_SQLUtils($conn)
     *       php> echo $sql_utils->vector_to_sql_tuple(array("i'm a string", 28, NULL, FALSE));
     *       ( 'i\'m a string', '28', NULL, FALSE )
     * 
     */
    public function vector_to_sql_tuple($ar) {
        $tmp = array();
        foreach($ar as $value) {
            if ($value === NULL) $tmp[] = "NULL";
            else if (is_bool($value)) $tmp[] = ($value ? "TRUE" : "FALSE");
            else $tmp[] = "'".($this->conn->real_escape_string($value))."'";
        }
        return sprintf("( %s )", join(",", $tmp));
    }
    
    /**@
     * .. method:: array_to_sql_assignments($ar[,$sep=","])
     *    
     *    Convert an associative array in a series of "columnName = value" expressions
     *    as valid SQL.
     *    The expressions are separated using the parameter $sep (defaults to ",").
     *    The array can contain only values of type int, float, boolean, string.
     *
     *    :param $ar: an associative array to convert
     *    :type $ar:  array
     *    :param $sep: expression separator
     *    :type $sep:  string
     *
     *    :return: the generated SQL snippet
     *    :rtype:  string
     *
     *    Example:
     *    
     *    .. code-block:: none
     *       
     *       php> $conn = new mysqli( connection data )
     *       php> $sql_utils = new sp_SQLUtils($conn)
     *       php> $myArray = array("city address" => "main street", "married" => FALSE);
     *       php> echo $sql_utils->array_to_sql_assignments($myArray);
     *        `city address` = 'main street' , `married` = FALSE
     *       php> echo $sql_utils->array_to_sql_assignments($myArray, "AND");
     *        `city address` = 'main street' AND `married` = FALSE 
     */
    public function array_to_sql_assignments($ar,$sep=",") {
        $tmp = array();
        foreach($ar as $key => $value) {
            if ($value === NULL) $value = "NULL";
            else if (is_bool($value)) $value = ($value ? "TRUE" : "FALSE");
            else $value =  "'".($this->conn->real_escape_string($value))."'";
            
            $tmp[] = sprintf(" `%s` = %s ", str_replace("`", "``", $key), $value);
        }
        return join($sep, $tmp);
    }
    
    /**@
     * .. method:: flush_results()
     *    
     *    Empty connection results of the last single or multi query.
     *    If the last query generated an error, a sp_MySQL_Error exception
     *    is raised.
     *    Mostly useful after calling sql functions or procedures.
     */
    public function flush_results(){
        $conn = $this->conn;
        while($conn->more_results()) {
            if ($result = $conn->use_result()) $result->close();
            $conn->next_result();
        }
        
        if ($conn->errno) throw new sp_MySQL_Error($conn);
    }
    
    /**@
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
        $table_name = str_replace('`', '``', $table_name);
        
        $result = $this->conn->query("SHOW COLUMNS FROM `{$table_name}`;", MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->conn);
        
        $fields = array();
        while($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $fields[] = $row["Field"];
        }
        $result->close();
        return $fields;
    }
    
    /**@
     * .. method:: table_exists($table_name[,$db_name=NULL])
     *    
     *    Check if a table exists.
     *    
     *    :param $table_name: name of the table
     *    :type $table_name:  string
     *    :param $db_name: name of the database (if different from the current in use)
     *    :type $db_name:  string
     *    
     *    :return: TRUE if exists, FALSE otherwise
     *    :rtype:  boolean
     */
    public function table_exists($table_name,$db_name=NULL){
        if (!$db_name) {
            $result = $this->conn->query("SELECT DATABASE()", MYSQLI_STORE_RESULT);
            if (!$result) throw new sp_MySQL_Error($this->conn);
            $row = $result->fetch_row();
            $db_name = $row[0];
            $result->close();
        }
        
        $result = $this->conn->query("
            SELECT COUNT(*)
            FROM information_schema.tables 
            WHERE table_schema = '{$db_name}' 
            AND ".($this->array_to_sql_assignments(array(
                 "table_name" => $table_name)))
        );
        
        if (!$result) throw new sp_MySQL_Error($this->conn);
        $row = $result->fetch_row();
        $result->close();
        
        return $row[0] != 0;
    }
}

/**@
 * .. class:: sp_Lib
 *    
 *    Generic utilities
 */
class sp_Lib {
    
    /**@
     * .. staticmethod:: map_method($array,$obj,$methodName)
     *    
     *    Call an object method on each item in an array and return an array
     *    with the results.
     *    
     *    :param $array: values to pass to the method
     *    :type $array:  array
     *    :param $obj: an object instance
     *    :type $obj:  object
     *    :param $methodName: a callable method of $obj
     *    :type $methodName:  string
     *    
     *    :return: the computed results
     *    :rtype:  array
     */
    public static function &map_method(&$array, $obj, $methodName) {
        $tmp = array();
        foreach ($array as $item){
            $tmp[] = $obj->$methodName($item);
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
 *    :param $fields_values: additional fields of the node (mapping fieldName => value)
 *    :type $fields_values: array or NULL
 *
 *    **Attributes**:
 *       **id** int, node id
 *       
 *       **lft**  int, left value
 *       
 *       **rgt**  int, right value
 *       
 *       **parentNode**  int, the parent node id
 *       
 *       **fields_values**  array, additional fields of the node
 *       
 *       **children** array, instances of BaobabNode children of the current node 
 *
 *    .. note::
 *       this class doesn't have database interaction, its purpose is
 *       just to have a runtime representation of a Baobab tree. The data
 *       inserted is supposed to be valid in his tree (e.g. $this->lft cant'
 *       be -1 or major of any node right value)
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
        $this->id = $id;
        $this->lft = $lft;
        $this->rgt = $rgt;
        $this->parentNode = $parentNode;
        $this->fields_values = $fields_values;
        
        $this->children = array();
    }
    
    /**!
     * .. method:: appendChild($child)
     *
     *    Add a node as last sibling of the node's children.
     *
     *    :param $child: append a node to the list of this node children
     *    :type $child: :class:`BaobabNode`
     *
     */
    public function appendChild($child) {
        $this->children[] = $child;
    }
    
    /**!
     * .. method:: stringify([$fields=NULL[,$diveInto=TRUE[,$indentChar=" "[,$indentLevel=0]]]])
     *
     *    Return a representation of the tree as a string.
     *
     *    :param $fields: what node fields include in the output. id, lft and rgt
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
        $out = str_repeat($indentChar, $indentLevel*4)."({$this->id}) [{$this->lft}, {$this->rgt}]";
        if (!$diveInto) return $out;
        foreach ($this->children as $child) {
            $out .= "\n".$child->stringify($fields, TRUE, $indentChar, $indentLevel+1);
        }
        return $out;
    }
    
    /**!
     * .. method:: isRightmost()
     *    
     *    Check if the node is rightmost between his siblings.
     *
     *    :return: whether if the node is the rightmost or not
     *    :rtype:  boolean
     *
     *    .. note:
     *       root node is considered to be rightmost
     */
    public function isRightmost(){
        if (!$this->parentNode) return TRUE;
        
        return $this->parentNode->children[count($this->parentNode->children)-1]->id === $this->id;
    }
    
    /**!
     * .. method:: isLeftmost()
     *    
     *    Check if the node is leftmost between his siblings.
     *
     *    :return: whether if the node is the leftmost or not
     *    :rtype:  boolean
     *    
     *    .. note:
     *       root node is considered to be leftmost
     */
    public function isLeftmost(){
        if (!$this->parentNode) return TRUE;
        
        return $this->parentNode->children[0]->id === $this->id;
    }
}

/**!
 * Baobab
 * ------
 */

/**!
 * .. class:: Baobab($db,$forest_name[,$tree_id=NULL])
 *    
 *    This class lets you create, populate search and destroy a tree stored
 *    using the Nested Set Model described by Joe Celko.
 *
 *    :param $db: mysqli database connection in object oriented style
 *    :type $db:  an instance of mysqli_connect
 *    :param $forest_name: name of the forest, will be used to create an homonymous
 *                       table that will hold the data.
 *    :type $forest_name: string
 *    :param $tree_id: id of the tree (to create a new tree it must be NULL or an unused tree_id number).
 *                      If there is only a tree in the table, you can load it with -1;
 *                      A new tree created using NULL has $tree_id = 0 until an appendChild occurs.
 *    :type $tree_id: int or NULL
 *
 *    **Attributes**:
 *       **tree_id** int, id of the tree (0 means it's new with no nodes added yet)
 */
class Baobab  {
    private $_refresh_fields;
    private $_errors;
    private static $_version = "1.3.1";
    
    protected $db;
    protected $forest_name;
    protected $sql_utils;
    protected $fields;
    
    public $tree_id;
    
    public function __construct($db,$forest_name,$tree_id=NULL) {
        $this->db = $db;
        $this->sql_utils = new sp_SQLUtils($db);
        $this->forest_name = $forest_name;
        $this->fields = array();
        $this->_refresh_fields = TRUE;
        
        // load error's information from db (if tables were created)
        try { $this->_load_errors();
        } catch (sp_Error $e) {}
        
        // if $tree_id is -1 we suppose there is one and only one tree yet in
        //   the table, and we automatically retrieve his id
        if ($tree_id== -1 ) {
            $query = "SELECT DISTINCT tree_id FROM {$this->forest_name} LIMIT 2";
            if ($result = $this->db->query($query, MYSQLI_STORE_RESULT)) {
                $num_trees = $result->num_rows;
                
                if ($num_trees == 1) {
                    // one and only, let's use it
                    $row = $result->fetch_row();
                    $tree_id = intval($row[0]);
                }
                else if ($num_trees == 0) {
                    // no trees ? it will be the first
                    $tree_id = 0;
                }
                $result->close();
                
                if ($num_trees>1) {
                    throw new sp_Error("Too many trees found");
                }
                
            } else {
                if ($this->db->errno == 1146) {
                    // table does not exist (skip), so it will be the first tree
                } else throw new sp_MySQL_Error($this->db);
            }
        }
        
        $this->tree_id = intval($tree_id);
        
        $this->build();
    }
    
    /**
     * .. method:: _load_errors()
     *
     *    Fill the member $_errors with informations about error codes and 
     *      messages
     */
    private function _load_errors(){
        $this->_errors = array("by_code" => array(), "by_name" => array());
        if ($result = $this->db->query("SELECT code,name,msg FROM Baobab_Errors")) {
            
            while($row = $result->fetch_assoc()) {
                $this->_errors["by_code"][$row["code"]] = $row;
                $this->_errors["by_name"][$row["name"]] = &$this->_errors["by_code"][$row["code"]];
            }
            $result->close();
            
        } else throw new sp_Error("Cannot read info about errors (d'oh!)");
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
        
        $real_fields = $this->_get_fields();
        // check that the requested fields exist
        foreach($fields as $fieldName) {
            if (!isset($real_fields[$fieldName])) throw new sp_Error("`{$fieldName}` wrong field name for table `{$this->forest_name}`");
        }
    }
    
    /**
     * .. method:: _get_fields()
     *
     *    Return the fields' names of the Baobab main table.
     *    It mantains the fields array in memory, and refresh it if the
     *    private variable _refresh_fields is TRUE
     *
     *    :return: associative array fieldName => TRUE
     *    :rtype:  array
     *
     *    .. note::
     *       An associative array is being returned because it's quicker to
     *       check for fields existence inside it.
     *     
     *    .. note::
     *       it is public but for internal use only (see static methods import/export )
     */
    public function &_get_fields(){
        
        if ($this->_refresh_fields) {
            $fields = $this->sql_utils->get_table_fields($this->forest_name);
            $this->fields = array();
            for($i=0,$il=count($fields);$i<$il;$i++) {
                $this->fields[$fields[$i]] = TRUE;
            }
            $this->_refresh_fields = FALSE;
        }
        
        return $this->fields;
    }
    
    /**!
     * .. method:: build()
     *
     *    Apply the database schema
     *    
     *    :return: TRUE if any table was added, FALSE if the build was skipped
     *             (e.g. if the tree yet exists calling build will do nothing
     *             and return FALSE).
     *    :rtype:  boolean
     *
     *    .. note::
     *       This is automatically called while instantiating the class.
     *       If the version of the library didn't change in the meantime, nothing
     *       happens, otherwise it will throw an exception to inform you to
     *       use :class:`Baobab:upgrade()`
     */
    public function build() {
        
        // get the current sql version from the loaded db (if any)
        $sql_version = NULL;
        if (isset($this->_errors["by_name"]["VERSION"]))
            $sql_version = $this->_errors["by_name"]["VERSION"]["msg"];
        
        // check if the tree was yet built
        $treeExists = FALSE;
        if ($result = $this->db->query("SELECT name FROM Baobab_ForestsNames WHERE name='{$this->forest_name}'")) {
            $treeExists = $result->num_rows > 0;
            $result->close();
        } else {
             if ($this->db->errno !== 1146) { // do not count "missing table" as an error
                throw new sp_MySQL_Error($this->db);
            }
        }
        
        // if there are tables at an older version, ask to upgrade
        if ($sql_version && $sql_version != self::$_version) {
            // old schema found at a lower version, ensure user upgrade the database willingly
            $codename = "VERSION_NOT_MATCH";
            $errno = $this->_errors["by_name"][$codename]["code"];
            throw new sp_Error(sprintf(
                "[%s] %s", $codename, $this->_errors["by_code"][$errno]["msg"]).
                " (database tables v. ".$sql_version.", library v. ".self::$_version."). Please use Baobab::upgrade().",
                $errno);
        }
        
        // build the tree only if the database schema not yet exists
        if (!$treeExists) {
            $sql = file_get_contents(BAOBAB_SQL_DIR.DIRECTORY_SEPARATOR."schema_baobab.sql");
            if (!$this->db->multi_query(str_replace("GENERIC", $this->forest_name, $sql))) {
                throw new sp_MySQL_Error($this->db);
            }
            
            $this->sql_utils->flush_results();
            
            // load or reload the errors table
            $this->_load_errors();
        }
        
        return ! $treeExists;
    }
    
    /**!
     * .. staticmethod:: upgrade($db[,$untilVersion=NULL])
     * 
     *    Upgrade the database schema of all the trees to reflect the current
     *    library's version.
     *    
     *    :param $db: mysqli database connection object
     *    :type $db:  mysqli
     *    :param $untilVersion: version at which stop (or NULL to update to last version)
     *    :type $untilVersion:  string or NULL
     *
     *    .. warning::
     *       To avoid any possible data loss you should backup your database's
     *       tables first.
     *
     *    .. warning::
     *       For each release read release notes and check what's going to happen
     *       when you upgrade your database (see sql/upgrade/* files).
     *       Eventually stop with $untilVersion at a certain point to apply
     *       needed tweaks.
     */
    public static function upgrade($db,$untilVersion=NULL){
        
        $db->query("START TRANSACTION");
        try {
            
            $currentDbVersion = NULL;
            
            // get the current database version
            if ($result = $db->query("SELECT msg FROM Baobab_Errors WHERE name='VERSION'")) {
                $row = $result->fetch_assoc();
                $currentDbVersion = $row["msg"];
                $result->close();
            } else throw new sp_Error("You cannot upgrade if you didn't ever built before");
            
            // upgrade only if current db version is not yet the most recent
            //  or we wasn't asked to stop at the current version
            if ($currentDbVersion == self::$_version ||
                $untilVersion == $currentDbVersion) return;
            
            $upgradeList = array(); // name of the files with sql data to run
            $versions = array(); // mapping fileName -> version to which it will update
            
            $startingFileToUpgrade = NULL; // of the files in $upgradeList, start to update from this one
            
            // get all the upgrade files
            $upgradeDir = BAOBAB_SQL_DIR.DIRECTORY_SEPARATOR."upgrade";
            $pattern = "/^\d{8}_from_(?P<from>.+?)_to_(?P<to>.+?)\.sql$/";
            
            if ($handle = opendir($upgradeDir)) {
                while (FALSE !== ($fName = readdir($handle))) {
                    if ($fName == "." || $fName == "..") continue;
                    
                    $matches = array();
                    if (preg_match($pattern, $fName, $matches)) {
                        $upgradeList[] = $fName;
                        $versions[$fName] = $matches["to"];
                        
                        if ($matches["from"] == $currentDbVersion)
                            $startingFileToUpgrade = $fName;
                    }
                }
                closedir($handle);
                
            } else {
                throw new sp_Error(sprintf('Can not read the directory "%s"', $upgradeDir));
            }
            
            if (empty($upgradeList)) return;
            
            sort($upgradeList);
            
            // get the starting version (it wasn't added while scanning the directory)
            $matches = array();
            preg_match($pattern, $upgradeList[0], $matches);
            $baseVersion = $matches["from"];
            
            if ($untilVersion == $baseVersion) return;
            
            $pathToUntilVersion = array_search($untilVersion, $versions);
            
            if ($untilVersion !== NULL && FALSE === $pathToUntilVersion)
                throw new sp_Error("You requested to stop at an unknown version ({$untilVersion})");
            
            else if ($startingFileToUpgrade == NULL)
                throw new sp_Error("Couldn't find a file to upgrade from the current version ({$currentDbVersion})");
            
            // filter the availabe upgrades to remove the older than the current version
            $upgradeList = array_slice($upgradeList, array_search($startingFileToUpgrade, $upgradeList));
            
            // get the names of the existing forests
            $forestNames = array();
            if ($result = $db->query("SELECT name FROM Baobab_ForestsNames", MYSQLI_STORE_RESULT)) {
                if ($result->num_rows) {
                    $row = $result->fetch_row();
                    $forestNames[] = $row[0];
                }
                $result->close();
            
            } else throw new sp_MySQL_Error($db);
            
            $sql_utils = new sp_SQLUtils($db);
            
            // upgrade each tree until the choosen (or last) version
            foreach($upgradeList as $fName){
                $sql = file_get_contents($upgradeDir.DIRECTORY_SEPARATOR.$fName);
                
                foreach($forestNames as $name) {
                    if (!$db->multi_query(str_replace("GENERIC", $name, $sql))) {
                        throw new sp_MySQL_Error($db);
                    }
                    $sql_utils->flush_results();
                }
                
                if ($pathToUntilVersion == $fName) break;
            }
            
        
        } catch (Exception $e) {
            // whatever happens we must rollback
            $db->query("ROLLBACK");
            throw $e;
        }
        
        $result = $db->query("COMMIT");
        if (!$result) throw new sp_MySQL_Error($db);
    }
    
    /**!
     * .. method:: destroy()
     *
     *    Remove every table, procedure or view that were created via
     *      :class:`Baobab.build` for the current tree name
     *
     *    :param $removeDataTable: unless this value is TRUE, avoid to delete 
     *                             the table that holds the tree data
     *    :type $removeDataTable:  boolean
     *    
     */
    public function destroy($removeDataTable=FALSE) {
        
        $this->db->query("START TRANSACTION");
        
        try {
            $forestsExist = TRUE;
            
            if (!$this->db->multi_query(str_replace("GENERIC", $this->forest_name,"
                    DROP PROCEDURE IF EXISTS Baobab_GENERIC_getNthChild;
                    DROP PROCEDURE IF EXISTS Baobab_GENERIC_MoveSubtree_real;
                    DROP PROCEDURE IF EXISTS Baobab_GENERIC_MoveSubtreeAtIndex;
                    DROP PROCEDURE IF EXISTS Baobab_GENERIC_MoveSubtreeBefore;
                    DROP PROCEDURE IF EXISTS Baobab_GENERIC_MoveSubtreeAfter;
                    DROP PROCEDURE IF EXISTS Baobab_GENERIC_MoveSubtree_Different_Trees;
                    DROP PROCEDURE IF EXISTS Baobab_GENERIC_InsertChildAtIndex;
                    DROP PROCEDURE IF EXISTS Baobab_GENERIC_insertBefore;
                    DROP PROCEDURE IF EXISTS Baobab_GENERIC_insertAfter;
                    DROP PROCEDURE IF EXISTS Baobab_GENERIC_AppendChild;
                    DROP PROCEDURE IF EXISTS Baobab_GENERIC_DropTree;
                    DROP PROCEDURE IF EXISTS Baobab_GENERIC_Close_Gaps;
                    DROP VIEW IF EXISTS GENERIC_AdjTree;
                    ".
                    ($removeDataTable ? "DROP TABLE IF EXISTS GENERIC;" : "").
                    "DELETE FROM Baobab_ForestsNames WHERE name='GENERIC';"
                    ))) {
                
                throw new sp_MySQL_Error($this->db);
            }
            
            try { $this->sql_utils->flush_results(); }
            catch (Exception $e) {
                
                if ($this->db->errno === 1146) { // do not count "missing table" as an error
                    // died because BaobabTreeNames was missing (other drop are IF EXISTS)
                    $forestsExist = FALSE;
                }
                else throw $e;
            }
            
            if ($forestsExist) {
                
                if ($result = $this->db->query("SELECT COUNT(*) FROM Baobab_ForestsNames", MYSQLI_STORE_RESULT)) {
                    $dropIt = FALSE;
                    if ($result->num_rows) {
                        $row = $result->fetch_row();
                        $dropIt = ($row[0] == 0);
                    }
                    
                    $result->close();
                    
                    if ($dropIt) {
                        // there aren't anymore trees, lets remove the common tables too
                        if (!$this->db->multi_query("
                            DROP TABLE Baobab_ForestsNames;
                            DROP TABLE Baobab_Errors;
                            DROP FUNCTION Baobab_getErrCode;
                            ")) {
                            throw new sp_MySQL_Error($this->db);
                        } else {
                            $this->sql_utils->flush_results();
                        }
                    }
                
                } else throw new sp_MySQL_Error($this->db);
            }
            
        } catch (Exception $e) {
            // whatever happens we must rollback
            $this->db->query("ROLLBACK");
            throw $e;
        }
        
        $result = $this->db->query("COMMIT");
        if (!$result) throw new sp_MySQL_Error($this->db);
        
        // reset errors data
        try { $this->_load_errors();
        } catch (sp_Error $e) {}
    }
    
    /**!
     * .. method:: clean()
     *    
     *    Delete all the records about this tree.
     *    Other trees in the same table will be unaffected.
     *
     */
    public function clean() {
        if (!$this->db->query("
            DELETE FROM {$this->forest_name}
            WHERE tree_id={$this->tree_id}"
            ) && $this->db->errno !== 1146) { // do not count "missing table" as an error
            
            throw new sp_MySQL_Error($this->db);
        }
    }
    
    /**!
     * .. staticmethod:: cleanAll($db,$forest_name)
     *    
     *    Delete all the records in $forest_name
     *    
     *    :param $db: mysqli database connection in object oriented style
     *    :type $db:  an instance of mysqli_connect
     *    :param $forest_name: name of the forest, equals to the name of the table
     *                       holding the data
     *    :type $forest_name:  string
     */
    public static function cleanAll($db, $forest_name){
        // delete all records, ignoring "missing table" error if happening
        if (!$db->query("DELETE FROM {$forest_name}") && $db->errno !== 1146) {
            throw new sp_MySQL_Error($db);
        }
    }

    /**!
     * .. method:: getRoot()
     *    
     *    Return the id of the first node of the tree.
     *
     *    :return: id of the root, or NULL if empty
     *    :rtype:  int or NULL
     *
     */
    public function getRoot(){

        $query = "
          SELECT id AS root
          FROM {$this->forest_name}
          WHERE tree_id={$this->tree_id} AND lft = 1;
        ";
        
        $out = NULL;
        
        if ($result = $this->db->query($query, MYSQLI_STORE_RESULT)) {
            if ($result->num_rows) {
                $row = $result->fetch_row();
                $out = intval($row[0]);
            }
            $result->close();
        
        } else throw new sp_MySQL_Error($this->db);
        
        return $out;
    }
    
    /**!
     * .. method:: getParent($id_node)
     *    
     *    Return the id of the node's parent.
     *
     *    :return: id of the parent, or NULL if node is root
     *    :rtype:  int or NULL
     *
     */
    public function getParent($id_node){
        $id_node = intval($id_node);
        
        $query = "
          SELECT parent
          FROM {$this->forest_name}_AdjTree
          WHERE tree_id={$this->tree_id} AND child = {$id_node};
        ";

        $out = NULL;

        if ($result = $this->db->query($query, MYSQLI_STORE_RESULT)) {
            $row = NULL;
            if ($result->num_rows) $row = $result->fetch_row();
            $result->close();
            
            if ($row) {
                if ($row[0] === NULL) $out = NULL;
                else $out = intval($row[0]);
            }
            else {
                throw new sp_Error(sprintf("[%s] %s",
                    $this->_errors["by_code"][1400]["name"],
                    $this->_errors["by_code"][1400]["msg"]), 1400);
            }
        
        } else throw new sp_MySQL_Error($this->db);
        
        return $out;
    }

    /**!
     * .. method:: getSize([$id_node=NULL])
     *    
     *    Retrieve the number of nodes of the subtree starting at $id_node (or
     *    at tree root if $id_node is NULL).
     *    
     *    :param $id_node: id of the node to count from (or NULL to count from root)
     *    :type $id_node:  int or NULL
     *    
     *    :return: the number of nodes in the selected subtree
     *    :rtype:  int
     */
    public function getSize($id_node=NULL) {

        $query = "
          SELECT (rgt-lft+1) DIV 2
          FROM {$this->forest_name}
          WHERE ". ($id_node !== NULL ? "id = ".intval($id_node) : "lft = 1").
                " AND tree_id={$this->tree_id}";
        
        $out = 0;

        if ($result = $this->db->query($query, MYSQLI_STORE_RESULT)) {
            $row = $result->fetch_row();
            $out = intval($row[0]);
            $result->close();

        } else throw new sp_MySQL_Error($this->db);

        return $out;

    }
    
    /**!
     * .. method:: getDescendants([$id_node=NULL])
     *    
     *    Retrieve all the descendants of a node
     *    
     *    :param $id_node: id of the node whose descendants we're searching for,
     *                     or NULL to start from the tree root.
     *    :type $id_node:  int or NULL
     *    
     *    :return: the ids of node's descendants, in ascending order
     *    :rtype:  array
     *
     */
    public function &getDescendants($id_node=NULL) {

        if ($id_node === NULL) {
            // we search for descendants of root
            $query = "SELECT id FROM {$this->forest_name}
                    WHERE tree_id={$this->tree_id} AND lft <> 1 ORDER BY id";
        } else {
            // we search for a node descendants
            $id_node = intval($id_node);
            
            $query = "
              SELECT id
              FROM {$this->forest_name}
              WHERE tree_id = {$this->tree_id}
                AND lft > (SELECT lft FROM {$this->forest_name} WHERE id = {$id_node})
                AND rgt < (SELECT rgt FROM {$this->forest_name} WHERE id = {$id_node})
              ORDER BY id
            ";
        }
        
        $ar_out = array();

        if ($result = $this->db->query($query, MYSQLI_STORE_RESULT)) {
            while($row = $result->fetch_row()) {
                array_push($ar_out, intval($row[0]));
            }
            $result->close();

        } else throw new sp_MySQL_Error($this->db);

        return $ar_out;

    }
    
    /**!
     * .. method:: getLeaves([$id_node=NULL])
     *
     *    Find the leaves of a subtree.
     *    
     *    :param $id_node: id of a node or NULL to start from the tree root
     *    :type $id_node:  int or NULL
     *
     *    :return: the ids of the leaves, ordered from left to right
     *    :rtype:  array
     */
    public function &getLeaves($id_node=NULL){
        
        $query = "
          SELECT id AS leaf
          FROM {$this->forest_name}
          WHERE tree_id={$this->tree_id} AND lft = (rgt - 1)";
        
        if ($id_node !== NULL) {
            // check only leaves of a subtree adding a "where" condition
            
            $id_node = intval($id_node);
        
            $query .= " AND lft > (SELECT lft FROM {$this->forest_name} WHERE id = {$id_node}) ".
                      " AND rgt < (SELECT rgt FROM {$this->forest_name} WHERE id = {$id_node}) ";
        }
        
        $query .= " ORDER BY lft";
        
        $ar_out = array();
        
        if ($result = $this->db->query($query, MYSQLI_STORE_RESULT)) {
            while($row = $result->fetch_row()) {
                array_push($ar_out, intval($row[0]));
            }
            $result->close();
            
        } else throw new sp_MySQL_Error($this->db);

        return $ar_out;
    }
    
    /**!
     * .. method:: getLevels()
     *
     *    Find at what level of the tree each node is.
     *    
     *    :param $id_node: id of a node or NULL to start from the tree root
     *    :type $id_node:  int or NULL
     *
     *    :return: associative arrays with id => number, level => number, unordered
     *    :rtype:  array
     *
     *    .. note::
     *       tree root is at level 0
     *
     */
    public function &getLevels(){
    
        $query = "
          SELECT T2.id as id, (COUNT(T1.id) - 1) AS level
          FROM {$this->forest_name} AS T1 JOIN {$this->forest_name} AS T2
               on T1.tree_id={$this->tree_id} AND T1.tree_id = T2.tree_id
          WHERE T2.lft BETWEEN T1.lft AND T1.rgt
          GROUP BY T2.lft
          ORDER BY T2.lft ASC;
        ";

        $ar_out = array();

        if ($result = $this->db->query($query, MYSQLI_STORE_RESULT)) {
            while($row = $result->fetch_assoc()) {
                array_push($ar_out, array("id" => intval($row["id"]), "level" => intval($row["level"])));
            }
            $result->close();

        } else throw new sp_MySQL_Error($this->db);

        return $ar_out;
    }

    /**!
     * .. method:: getPath($id_node[,$fields=NULL[,$squash=FALSE]])
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
     *               fieldName => value, where field names are the one present
     *               in $fields plus the field "id" (unless $squash was set),
     *               ordered from root to $id_node
     *    :rtype:  array
     *    
     *    Example (considering a tree with two nodes with a field 'name'):
     *    
     *    .. code-block:: none
     *       
     *       php> $tree->getPath(2, array("name"))
     *       array([0] => array([id] => 1, [name] => 'rootName'), array([id] => 2, [name] => 'secondNodeName']))
     *       php> join("/", $tree->getPath(2, "name", TRUE))
     *       "rootName/secondNodeName"
     * 
     */
    public function &getPath($id_node,$fields=NULL,$squash=FALSE){
        $id_node = intval($id_node);
        
        if (empty($fields)) {
            if ($squash) $fields = array("id");
            else $fields = array(); // ensure it is not NULL
        }
        else if (is_string($fields)) $fields = array($fields);
        
        // append the field "id" if missing
        if (FALSE === array_search("id", $fields)) $fields[] = "id";
        
        $this->_sql_check_fields($fields);
        
        $fields_escaped = array();
        foreach($fields as $fieldName) {
            $fields_escaped[] = sprintf("`%s`", str_replace("`", "``", $fieldName));
        }
        
        $query = "".
        " SELECT ".join(",", $fields_escaped).
        " FROM {$this->forest_name}".
        " WHERE tree_id={$this->tree_id} AND ( SELECT lft FROM {$this->forest_name} WHERE id = {$id_node} ) BETWEEN lft AND rgt".
        " ORDER BY lft";

        $result = $this->db->query($query, MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->db);

        $ar_out = array();
        if ($squash) {
            reset($fields);
            $fieldName = current($fields);
            while($rowAssoc = $result->fetch_assoc()) $ar_out[] = $rowAssoc[$fieldName];
            
        } else {
            while($rowAssoc = $result->fetch_assoc()) {
                $tmp_ar = array();
                foreach($fields as $fieldName) {
                    $tmp_ar[$fieldName] = $rowAssoc[$fieldName];
                }
                $ar_out[] = $tmp_ar;
            }
        }
        
        $result->close();

        return $ar_out;
    }
    
    /**!
     * .. method:: getFirstNChildren($id_parent[,$howMany=NULL[,$fromLeftToRight=TRUE]])
     *
     *    Find the first n node's children starting from left or right.
     *
     *    :param $id_parent: id of the parent node
     *    :type $id_parent:  int
     *    :param $howMany: maximum number of children to retrieve (NULL means all)
     *    :type $howMany:  int or NULL
     *    :param $fromLeftToRight: what order the children must follow
     *    :type $fromLeftToRight:  boolean
     *    
     *    :return: ids of the children nodes, ordered from left to right
     *    :rtype:  array
     *
     */
    public function &getFirstNChildren($id_parent,$howMany=NULL,$fromLeftToRight=TRUE){
        $id_parent = intval($id_parent);
        $howMany = intval($howMany);
        
        $query = " SELECT child FROM {$this->forest_name}_AdjTree ".
                 " WHERE parent = {$id_parent} ".
                 " ORDER BY lft ".($fromLeftToRight ? 'ASC' : 'DESC').
                 ($howMany ? " LIMIT $howMany" : "");
        
        $result = $this->db->query($query, MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->db);
        
        $ar_out = array();
        while($row = $result->fetch_row()) {
            $ar_out[] = intval($row[0]);
        }
        $result->close();
        
        return $ar_out;
    }
    
    /**!
     * .. method:: getChildren($id_parent)
     *
     *    Find all node's children
     *
     *    :param $id_parent: id of the parent node
     *    :type $id_parent:  int
     *    
     *    :return: ids of the children nodes, ordered from left to right
     *    :rtype:  array
     *
     */
    public function &getChildren($id_parent) {
        return $this->getFirstNChildren($id_parent);
    }
    
    /**!
     * .. method:: getFirstChild($id_parent)
     *
     *    Find the leftmost child of a node
     *
     *    :param $id_parent: id of the parent node
     *    :type $id_parent:  int
     *    
     *    :return: id of the leftmost child node, or 0 if not found
     *    :rtype:  int
     *
     */
    public function getFirstChild($id_parent) {
        $res = $this->getFirstNChildren($id_parent, 1, TRUE);
        return empty($res) ? 0 : current($res);
    }
    
    /**!
     * .. method:: getLastChild($id_parent)
     *
     *    Find the rightmost child of a node
     *
     *    :param $id_parent: id of the parent node
     *    :type $id_parent:  int
     *    
     *    :return: id of the rightmost child node, or 0 if not found
     *    :rtype:  int
     *
     */
    public function getLastChild($id_parent) {
        $res = $this->getFirstNChildren($id_parent, 1, FALSE);
        return empty($res) ? 0 : current($res);
    }
    
    /**!
     * .. method:: getChildAtIndex($id_parent, $index)
     *
     *    Find the nth child of a parent node
     *
     *    :param $id_parent: id of the parent node
     *    :type $id_parent:  int
     *    :param $index: position between his siblings (0 is first).
     *                   Negative indexes are allowed (-1 is the last sibling).
     *    :type $index:  int
     *    
     *    :return: id of the nth child node
     *    :rtype:  int
     *
     */
    public function getChildAtIndex($id_parent, $index){
        $id_parent = intval($id_parent);
        $index = intval($index);
        
        if (!$this->db->multi_query("
                CALL Baobab_{$this->forest_name}_getNthChild({$id_parent}, {$index}, @child_id, @error_code);
                SELECT @child_id as child_id, @error_code as error_code"))
                throw new sp_MySQL_Error($this->db);

        $res = $this->_readLastResult('child_id');
        return intval($res['child_id']);
    }
    
    
    /**!
     * .. method:: getTree([$className="BaobabNode"[,$appendChild="appendChild"]])
     *
     *    Create a tree from the database data.
     *    It's possible to use a default tree or use custom classes/functions
     *    (it must extends the class :class:`BaobabNode`)
     *
     *    :param $className: name of the class holding a node's information
     *    :type $className:  string
     *    :param $appendChild: method of $className to call to append a node
     *    :type $appendChild:  string
     *
     *    :return: a node instance
     *    :rtype:  instance of $className
     *
     */
    public function &getTree($className="BaobabNode", $appendChild="appendChild") {
        
        // this is a specialized version of the query found in getLevel()
        //   (the difference lying in the fact that here we retrieve all the
        //    fields of the table)
        $query = "
          SELECT (COUNT(T1.id) - 1) AS level ,T2.*
          FROM {$this->forest_name} AS T1 JOIN {$this->forest_name} AS T2
                on T1.tree_id={$this->tree_id} AND T1.tree_id = T2.tree_id
          WHERE T2.lft BETWEEN T1.lft AND T1.rgt
          GROUP BY T2.lft
          ORDER BY T2.lft ASC;
        ";
        
        $root = NULL;
        $parents = array();
        
        if ($result = $this->db->query($query, MYSQLI_STORE_RESULT)) {
            
            while($row = $result->fetch_assoc()) {
                
                $numParents = count($parents);
                
                $id = $row["id"];
                $lft = $row["lft"];
                $rgt = $row["rgt"];
                $level = $row["level"];
                $parentNode = count($parents) ? $parents[$numParents-1] : NULL;
                
                unset($row["id"]);
                unset($row["lft"]);
                unset($row["rgt"]);
                unset($row["level"]);
                
                $node = new $className($id, $lft, $rgt, $parentNode, $row);
                
                if (!$root) $root = $node;
                else $parents[$numParents-1]->$appendChild($node);
                
                if ($rgt-$lft != 1) { // not a leaf
                    $parents[$numParents] = $node;
                }
                else if (!empty($parents) && $rgt+1 == $parents[$numParents-1]->rgt) {
                    
                    $k = $numParents-1;
                    $me = $node;
                    while ($k>-1 && $me->rgt+1 == $parents[$k]->rgt) {
                        $me = $parents[$k];
                        unset($parents[$k--]);
                    }
                    
                    /*
                    // alternative way using levels ($parents would have both the parent node and his level)
                    
                    // previous parent is the first one with a level minor than ours
                    if ($parents[count($parents)-1][1] >= $level) {
                        // remove all the previous subtree "parents" until our real parent
                        for($i=count($parents)-1;$parents[$i--][1] >= $level;)
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
     * .. method:: deleteNode($id_node[,$close_gaps=True])
     *
     *    Delete a node and all of his children. If $close_gaps is TRUE, mantains
     *    the Modified Preorder Tree consistent closing gaps.
     *
     *    :param $id_node: id of the node to drop
     *    :type $id_node:  int
     *    :param $close_gaps: whether to close the gaps in the tree or not (default TRUE)
     *    :type $close_gaps:  boolean
     *
     *    .. warning::
     *       If the gaps are not closed, you can't use most of the API. Usually
     *       you want to avoid closing gaps when you're deleting different
     *       subtrees and want to update the numbering just once
     *       (see :class:`Baobab.closeGaps`)
     */
    public function deleteNode($id_node,$close_gaps=TRUE) {
        $id_node = intval($id_node);
        $close_gaps = $close_gaps ? 1 : 0;
        
        if (!$this->db->multi_query("CALL Baobab_{$this->forest_name}_DropTree({$id_node}, {$close_gaps})"))
            throw new sp_MySQL_Error($this->db);
        
        $this->sql_utils->flush_results();
        
    }
    
    /**!
     * .. method:: closeGaps
     *    
     *    Update right and left values of each node to ensure there are no
     *    gaps in the tree.
     *
     *    .. warning::
     *       This is a really slow function, use it only if needed (e.g.
     *       to delete multiple subtrees and close gaps just once)
     */
    public function closeGaps() {
        if (!$this->db->multi_query("CALL Baobab_{$this->forest_name}_Close_Gaps({$this->tree_id})"))
            throw new sp_MySQL_Error($this->db);
        
        $this->sql_utils->flush_results();

    }

    /**!
     * .. method:: getTreeHeight()
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
    public function getTreeHeight(){
        
        $query = "
        SELECT MAX(level)+1 as height
        FROM (SELECT T2.id as id,(COUNT(T1.id)-1) as level
              FROM {$this->forest_name} as T1 JOIN {$this->forest_name} as T2
                   on T1.tree_id={$this->tree_id} AND T1.tree_id = T2.tree_id
              WHERE T2.lft  BETWEEN T1.lft AND T1.rgt
              GROUP BY T2.id
             ) as ID_LEVELS";
        
        $result = $this->db->query($query, MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->db);

        $row = $result->fetch_row();
        $out = intval($row[0]);
        
        $result->close();
        return $out;
    }

    /**!
     * .. method:: updateNode($id_node,$fields_values)
     *    
     *    Update data associeted to a node
     *
     *    :param $id_node: id of the node to update
     *    :type $id_node:  int
     *    :param $fields_values: mapping fields => values to update
     *                     (only supported types are string, int, float, boolean)
     *    :type $fields_values:  array
     * 
     */
    public function updateNode($id_node,$fields_values){
        $id_node = intval($id_node);
        
        if (empty($fields_values)) throw new sp_Error("\$fields_values cannot be empty");
        
        $fields = array_keys($fields_values);
        $this->_sql_check_fields($fields);
        
        $query = "".
         " UPDATE {$this->forest_name}".
         " SET ".( $this->sql_utils->array_to_sql_assignments($fields_values) ).
         " WHERE id = $id_node";
        
        $result = $this->db->query($query, MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->db);
    }
    
    /**!
     * .. method:: getNodeData($id_node[,$fields=NULL])
     *    
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
        $id_node = intval($id_node);
        
        if (!empty($fields)) $this->_sql_check_fields($fields);
        
        $query = "".
        " SELECT ".($fields === NULL ? "*" : join(",", $fields)).
        " FROM {$this->forest_name} ".
        " WHERE id = $id_node";
        
        $result = $this->db->query($query, MYSQLI_STORE_RESULT);
        if (!$result) throw new sp_MySQL_Error($this->db);
        
        return $result->fetch_assoc();
    }
    
    /**!
     * .. method:: appendChild([$id_parent=NULL[,$fields_values=NULL]])
     *    
     *    Create and append a node as last child of a parent node. If no
     *    parent is given, the new node will become the root node.
     *
     *    :param $id_parent: id of the parent node
     *    :type $id_parent: int or NULL
     *    :param $fields_values: mapping fields => values to assign to the new node
     *    :type $fields_values: array or NULL
     *    
     *    :return: id of new node
     *    :rtype:  int
     *
     */
    public function appendChild($id_parent=NULL,$fields_values=NULL){
        
        $id_parent = intval($id_parent);
        
        if (!$this->db->multi_query("
                CALL Baobab_{$this->forest_name}_AppendChild({$this->tree_id}, {$id_parent}, @new_id, @cur_tree_id);
                SELECT @new_id as new_id, @cur_tree_id as tree_id"))
                throw new sp_MySQL_Error($this->db);
        
        $res = $this->_readLastResult(array('new_id', 'tree_id'));
        $id = intval($res['new_id']);
        $this->tree_id = intval($res['tree_id']);
        
        //update the node if needed
        if ($fields_values !== NULL) $this->updateNode($id, $fields_values);
        
        return $id;
    }
    
    /**!
     * .. method:: insertAfter($id_sibling[,$fields_values=NULL])
     *
     *    Create a new node and insert it as the next sibling of the node
     *    chosen (which can not be root)
     *
     *    :param $id_sibling: id of a node in the tree (can not be root)
     *    :type $id_sibling:  int
     *    :param $fields_values: mapping fields => values to assign to the new node
     *    :type $fields_values: array or NULL
     *
     *    :return: id of the new node
     *    :rtype:  int
     * 
     */
    public function insertAfter($id_sibling,$fields_values=NULL) {
        $id_sibling = intval($id_sibling);

        if (!$this->db->multi_query("
                CALL Baobab_{$this->forest_name}_insertAfter({$id_sibling}, @new_id, @error_code);
                SELECT @new_id as new_id, @error_code as error_code"))
                throw new sp_MySQL_Error($this->db);

        $res = $this->_readLastResult('new_id');
        
        //update the node if needed
        if ($fields_values !== NULL) $this->updateNode($res['new_id'], $fields_values);
        
        return intval($res['new_id']);
    }

    /**!
     * .. method:: insertBefore($id_sibling[,$fields_values=NULL])
     *
     *    Create a new node and insert it as the previous sibling of the node
     *    chosen (which can not be root)
     *
     *    :param $id_sibling: id of a node in the tree (can not be root)
     *    :type $id_sibling:  int
     *    :param $fields_values: mapping fields => values to assign to the new node
     *    :type $fields_values: array or NULL
     *
     *    :return: id of the new node
     *    :rtype:  int
     * 
     */
    public function insertBefore($id_sibling,$fields_values=NULL) {
        $id_sibling = intval($id_sibling);

        if (!$this->db->multi_query("
                CALL Baobab_{$this->forest_name}_insertBefore({$id_sibling}, @new_id, @error_code);
                SELECT @new_id as new_id, @error_code as error_code"))
                throw new sp_MySQL_Error($this->db);

        $res = $this->_readLastResult('new_id');
        
        //update the node if needed
        if ($fields_values !== NULL) $this->updateNode($res['new_id'], $fields_values);
        
        return intval($res['new_id']);
    }

    /**!
     * .. method:: insertChildAtIndex($id_parent,$index[,$fields_values=NULL])
     *
     *    Create a new node and insert it as the nth child of the parent node
     *    chosen
     *
     *    :param $id_parent: id of a node in the tree
     *    :type $id_parent:  int
     *    :param $index: new child position between his siblings (0 is first).
     *                   You cannot insert a child as last sibling.
     *                   Negative indexes are allowed (-1 is the position before
     *                   the last sibling).
     *    :type $index:  int
     *    :param $fields_values: mapping fields => values to assign to the new node
     *    :type $fields_values:  array or NULL
     *    
     *    :return: id of the new node
     *    :rtype:  int
     */
    public function insertChildAtIndex($id_parent,$index,$fields_values=NULL) {
        $id_parent = intval($id_parent);
        $index = intval($index);

        if (!$this->db->multi_query("
                CALL Baobab_{$this->forest_name}_InsertChildAtIndex({$id_parent}, {$index}, @new_id, @error_code);
                SELECT @new_id as new_id, @error_code as error_code"))
            throw new sp_MySQL_Error($this->db);
        
        $res = $this->_readLastResult('new_id');
        
        //update the node if needed
        if ($fields_values !== NULL) $this->updateNode($res['new_id'], $fields_values);
        
        return intval($res['new_id']);
    }
    
    /**!
     * .. method:: moveAfter($id_to_move,$reference_node)
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
    public function moveAfter($id_to_move,$reference_node) {
        $id_to_move = intval($id_to_move);
        $reference_node = intval($reference_node);

        if (!$this->db->multi_query("
                CALL Baobab_{$this->forest_name}_MoveSubtreeAfter({$id_to_move}, {$reference_node}, @error_code);
                SELECT @error_code as error_code"))
            throw new sp_MySQL_Error($this->db);
        
        $this->_readLastResult();
    }
    
    /**!
     * .. method:: moveBefore($id_to_move,$reference_node)
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
    public function moveBefore($id_to_move,$reference_node) {
        $id_to_move = intval($id_to_move);
        $reference_node = intval($reference_node);

        if (!$this->db->multi_query("
                CALL Baobab_{$this->forest_name}_MoveSubtreeBefore({$id_to_move}, {$reference_node}, @error_code);
                SELECT @error_code as error_code"))
            throw new sp_MySQL_Error($this->db);
        
        $this->_readLastResult();
    }
    
    /**!
     * .. method:: moveNodeAtIndex($id_to_move,$id_parent,$index)
     *
     *    Move a node as nth child of another node.
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
    public function moveNodeAtIndex($id_to_move,$id_parent,$index) {
        $id_to_move = intval($id_to_move);
        $id_parent = intval($id_parent);
        $index = intval($index);
        
        if (!$this->db->multi_query("
                CALL Baobab_{$this->forest_name}_MoveSubtreeAtIndex({$id_to_move}, {$id_parent}, {$index}, @error_code);
                SELECT @error_code as error_code"))
            throw new sp_MySQL_Error($this->db);
        
        $this->_readLastResult();
    }
    
    /**
     * .. method:: _readLastResult([$fields=NULL[,$error_field="error_code"[,$numResults=2]]])
     *    
     *    Read $numResults query results and return the values mapped to $fields.
     *    If a field named as the value of $error_field is found throw an 
     *    exception using that error informations.
     *
     *    :param $fields: name of a field to read or an array of fields names
     *    :type $fields:  string or array
     *    :param $error_field: name of the field that could contain an error code
     *    :type $error_field:  string
     *    :param $numResults: number of expected mysql results
     *    :type $numResults:  int
     *    
     *    :return: array, mapping $fields => values found in the last result
     *    :rtype:  int
     */
    private function &_readLastResult($fields=NULL,$error_field="error_code",$numResults=2){
        if (is_string($fields)) $fields = array($fields);
        else if ($fields === NULL) $fields = array();
        
        $k = 1;
        while($k++ < $numResults) {
            if ($result = $this->db->use_result()) { $result->close(); }
            if ($this->db->errno) throw new sp_MySQL_Error($this->db);
            $this->db->next_result();
        }
        
        $result = $this->db->use_result();
        $record = $result->fetch_assoc();
        
        if (isset($record[$error_field]) && $record[$error_field] != 0) {
            $error_code = intval($record[$error_field]);
            $result->close();
            throw new sp_Error(sprintf("[%s] %s",
                $this->_errors["by_code"][$error_code]["name"],
                $this->_errors["by_code"][$error_code]["msg"]), $error_code);
        }
        
        $ar_out = array();
        foreach($fields as $fieldName) {
            $ar_out[$fieldName] = $record[$fieldName];
        }
        $result->close();
        
        return $ar_out;
    }
    
    /**!
     * .. staticmethod:: import($db,$forest_name,$data)
     *    
     *    Load data about a single tree (as generated by the export method).
     *    
     *    :param $db: mysqli database connection in object oriented style
     *    :type $db:  an instance of mysqli_connect
     *    :param $forest_name: name of the forest, equals to the name of the table
     *                       holding the data
     *    :type $forest_name:  string
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
     *          "fields" : ["id", "lft", "rgt"],
     *          "values" : 
     *              [1,1,4,[
     *                  [2,2,3,[]]
     *              ]]
     *        }
     *        // more could follow ...
     *       ]
     *    
     *    .. note::
     *       If "id" field is used and the nodes values are not NULL, mustn't
     *       exist in the table a record with that same value.
     *      
     *    .. note::
     *       If "tree_id" is used (as attribute or field) and not NULL, mustn't
     *       exist in the table records belonging to that same tree.
     */
    public static function &import($db,$forest_name,$data){
        if (is_string($data)) $data = json_decode($data, TRUE);
        if (!$data || empty($data)) {$x = NULL;return $x;} // nothing to import
        
        // check if the table exists before doing anything else
        $sql_utils = new sp_SQLUtils($db);
        if ( ! $sql_utils->table_exists($forest_name)){
            throw new sp_Error("Table `{$forest_name}` does not exist");
        }
        
        $ar_out = array();
        
        $db->query("START TRANSACTION");
        
        try {
            
            foreach($data as $tmp_data) {
                
                if (empty($tmp_data["values"])) continue;
                
                // get the tree id, if any
                $tree_id = NULL;
                if (isset($tmp_data["tree_id"])) $tree_id = intval($tmp_data["tree_id"]);
                else {
                    $idx = array_search("tree_id", $tmp_data["fields"]);
                    if (FALSE !== $idx) {
                        // all the tree ids are equals, get the first
                        $tree_id = intval($tmp_data["values"][0][$idx]);
                    }
                }
                
                if (!$tree_id) { // check both NULL and 0
                    // there isn't a tree_id, we must get one
                    
                    // find a new tree_id
                    $query = "SELECT IFNULL(MAX(tree_id),0)+1 as new_id FROM {$forest_name}";
                    $result = $db->query($query, MYSQLI_STORE_RESULT);
                    if (!$result) throw new sp_MySQL_Error($db);
                    $row = $result->fetch_row();
                    $tree_id = intval($row[0]);
                    $result->close();
                    
                } else {
                    // ensure the tree_id isn't in use
                    $query = "SELECT DISTINCT tree_id FROM {$forest_name} WHERE tree_id={$tree_id}";
                    $result = $db->query($query, MYSQLI_STORE_RESULT);
                    if (!$result) throw new sp_MySQL_Error($db);
                    $tree_exists = $result->num_rows != 0;
                    $result->close();
                    
                    if ($tree_exists) throw new sp_Error("ImportError: tree_id {$tree_id} yet in use");
                }
                
                $tree = new Baobab($db, $forest_name, $tree_id);
                
                // if there are only standard fields we can also try to build the table
                //   (otherwise it must yet exist because we can't guess field types)
                
                $standard_fields = array("id", "lft", "rgt", "tree_id");
                if (count($tmp_data["fields"]) <= 4) {
                    $is_standard = TRUE;
                    foreach($tmp_data["fields"] as $fieldName) {
                        if (FALSE === array_search($fieldName, $standard_fields)) {
                            $is_standard = FALSE;
                            break;
                        }
                    }
                    if ($is_standard) {
                        try{
                            $tree->build(); // if yet exists is harmless
                        } catch (sp_MySQL_Error $e) {
                            if ($db->errno != 1050) throw $e; // 1050 is "table exists"
                        }
                    }
                }
                
                // check that the table has the involved fields
                $tree->_sql_check_fields($tmp_data["fields"]);
                
                $values = array();
                
                $nodes = array($tmp_data["values"]);
                
                while (!empty($nodes)) {
                    // get data of current node
                    $last_node = array_pop($nodes);
                     //save his children's array
                    $children = array_pop($last_node);
                    // append the children to the nodes to iterate over
                    if (count($children)) $nodes = array_merge($nodes, $children);
                    
                    // keep the data
                    $values[] = $last_node;
                }
                
                // get the tree_id to use
                
                if (FALSE === array_search("tree_id", $tmp_data["fields"])) {
                    // add tree_id to fields
                    $tmp_data["fields"][] = "tree_id";
                    
                    // add tree_id to the each row
                    foreach($values as &$row){
                        $row[] = $tree_id;
                    }
                }
                
                // add the values
                $result = $db->query(
                        "INSERT INTO {$forest_name}(".join(",", $tmp_data["fields"]).") VALUES ".
                        join(", ", sp_Lib::map_method($values, $sql_utils, "vector_to_sql_tuple"))
                    , MYSQLI_STORE_RESULT);
                if (!$result)  throw new sp_MySQL_Error($db);
                
                $ar_out[] = $tree;
                
            } // end foreach $data
        
        } catch (Exception $e) {
            // whatever happens we must rollback
            $db->query("ROLLBACK");
            throw $e;
        }
        
        $result = $db->query("COMMIT");
        if (!$result) throw new sp_MySQL_Error($db);
        
        
        return $ar_out;
    }
    
    /**
     * .. staticmethod:: _traverse_tree_to_export_data($node,&$data,&$fieldsFlags,&$fieldsOrder)
     *
     *   Traverse a baobab tree and create an array holding the data about each node.
     *   Each resulting node is represented as an array holding his values ordered as
     *   $fieldsOrder, with an array as most right element holding children nodes
     *   (in the same format).
     *
     *   :param $node: current node to retrieve values from
     *   :type $node:  BaobabNode
     */
    private static function _traverse_tree_to_export_data($node,&$data,&$fieldsFlags,&$fieldsOrder){
        
        $len_data = count($data);
        
        $tmp_ar = array();
        
        $i = 0;
        
        // get fields and values in the correct order
        foreach($fieldsOrder as $fieldName) {
            if ($fieldName == 'id') $value = $node->id;
            else if ($fieldName == 'lft') $value = $node->lft;
            else if ($fieldName == 'rgt') $value = $node->rgt;
            else $value = $node->fields_values[$fieldName];
            
            if ($fieldsFlags[$i++] & MYSQLI_NUM_FLAG) $value = floatval($value);
            
            $tmp_ar[] = $value;
        }
        
        $tmp_ar[] = array(); // the last element holds the children
        
        // append the child data to parent data
        $data[$len_data-1][] = &$tmp_ar;
        
        foreach($node->children as $childNode) {
            self::_traverse_tree_to_export_data($childNode, $tmp_ar, $fieldsFlags, $fieldsOrder);
        }
        
    }
    
    /**!
     * .. staticmethod:: export($db,$forest_name[,$fields=NULL[,$tree_id=NULL]])
     *    
     *    Create a JSON dump of one or more trees
     *    
     *    :param $db: mysqli database connection in object oriented style
     *    :type $db:  an instance of mysqli_connect
     *    :param $forest_name: name of the forest, equals to the name of the table
     *                       holding the data
     *    :type $forest_name:  string
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
     *       if 'tree_id' is passed as field, it will not appear in the field
     *       list because redundat (it will be present once in the tree_id attribute)
     *    
     *    Example of an exported tree
     *    
     *    .. code-block:: json
     *
     *       [
     *        {
     *          "tree_id": 6, 
     *          "fields" : ["id", "lft", "rgt"], // tree_id is stripped if requested via fields because redundant
     *          "values" : 
     *              [1,1,4,[
     *                  [2,2,3,[]]
     *              ]]
     *        }
     *        // more could follow ...
     *       ]
     * 
     */
    public static function export($db,$forest_name,$fields=NULL,$tree_id=NULL){
        
        // check if the table exists before doing anything else
        $sql_utils = new sp_SQLUtils($db);
        if ( ! $sql_utils->table_exists($forest_name)){
            throw new sp_Error("Table `Baobab_{$forest_name}` does not exist");
        }
        
        // get all the fields to export or check if the passed fields are valid
        $tree = new Baobab($db, $forest_name, NULL); // use a unexistent tree in the right table
        if ($fields !== NULL) $tree->_sql_check_fields($fields);
        else $fields = array_keys($tree->_get_fields());
        
        // remove tree_id from $fields to avoid writing it n times
        //   ( we give him a single property in the json format )
        $idx_treeId_field = array_search("tree_id", $fields);
        if (FALSE !== $idx_treeId_field) {
            unset($fields[$idx_treeId_field]);
            $fields = array_values($fields); // I want to mantain a correct index sequence
        }
        
        // get the ids of the trees to export
        $ar_tree_id = array();
        if ($tree_id) {
            if (!is_array($tree_id)) $tree_id = array($tree_id);
            // ensure $tree_id contains numbers
            foreach($tree_id as $tmp_id) $ar_tree_id[] = intval($tmp_id);
        }
        else {
            $query = "SELECT DISTINCT tree_id FROM {$forest_name}";
            $result = $db->query($query, MYSQLI_STORE_RESULT);
            if (!$result) throw new sp_MySQL_Error($db);
            while ($row = $result->fetch_row()) $ar_tree_id[] = intval($row[0]);
            $result->close();
        }
        
        // get the type of the columns mainly to write numbers as ... numbers
        $fieldsFlags = array(); // each index will have the field flag, to know his type
        $result = $db->query(
            "SELECT ".join(",", $fields)." FROM {$forest_name} LIMIT 1", MYSQLI_STORE_RESULT);
        if (!$result)  throw new sp_MySQL_Error($db);
        // retrieve the column names and their types
        while ($finfo = $result->fetch_field()) {
            $fieldsFlags[] = $finfo->flags;
        }
        $result->close();
        
        // parse each tree and build an array to jsonize later
        $ar_out = array();
        foreach ($ar_tree_id as $tree_id) {
            $tmp_ar = array("tree_id" => $tree_id, "fields" => $fields, "values" => NULL);
            
            // retrieve the data
            $tree = new Baobab($db, $forest_name, $tree_id);
            $root = $tree->getTree();
            
            if ($root !== NULL) {
                $data = array(array()); // the inner array emulate a node to gain root as child
                self::_traverse_tree_to_export_data($root, $data, $fieldsFlags, $tmp_ar["fields"]);
                if (!empty($data[0][0])) $tmp_ar["values"] = &$data[0][0];
                
                $ar_out[] = $tmp_ar;
            }
        }
        
        return json_encode($ar_out);
    }


}
