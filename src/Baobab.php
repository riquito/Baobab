<?php
namespace Baobab;
use Baobab\Exception;

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
 * Baobab
 * ------
 */

/**!
 * .. class:: Baobab($forest_name[,$tree_id=NULL])
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
class Baobab
{
    private $_refresh_fields;

    protected $pdo;
    protected $forest_name;
    protected $sql_utils;
    protected $fields;
    protected $tree_marks = NULL;

    public $tree_id;

    public function __construct(\PDO $pdo, $forest_name, $tree_id)
    {
        $this->pdo = $pdo;
        $this->sql_utils = new SqlUtils( $pdo );
        $this->forest_name = $forest_name;
        $this->fields = array();
        $this->_refresh_fields = TRUE;
        $this->tree_id = intval($tree_id);
    }

    /**
     * .. method:: _sql_check_fields($fields)
     *
     *    Check that the supplied fields exists in this Baobab table.
     *    Throws an BaobabError if something is wrong.
     *
     *    :param $fields: names of the fields to check for
     *    :type $fields:  array
     *
     *    :note: it is public but for internal use only (see static methods import/export )
     *
     */
    public function _sql_check_fields(&$fields)
    {
        $real_fields = $this->_get_fields();
        // check that the requested fields exist
        foreach ($fields as $fieldName) {
            if (!isset($real_fields[$fieldName])) throw new BaobabException("`{$fieldName}` wrong field name for table `{$this->forest_name}`");
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
    public function _get_fields() 
    {

        if ($this->_refresh_fields) {
            $fields = $this->sql_utils->get_table_fields($this->forest_name);
            $this->fields = array();
            for ($i=0,$il=count($fields);$i<$il;$i++) {
                $this->fields[$fields[$i]] = TRUE;
            }
            $this->_refresh_fields = FALSE;
        }

        return $this->fields;
    }

    /**!
     * .. method:: clean()
     *
     *    Delete all the records about this tree.
     *    Other trees in the same table will be unaffected.
     *
     */
    public function clean()
    {
        try {
            $stmt = $this->pdo->prepare("
                        DELETE FROM {$this->forest_name}
                        WHERE tree_id=:tree_id");
            $stmt->execute(array(':tree_id' => $this->tree_id));
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S02') { // do not count "missing table" as an error
                throw $e;
            }
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
    public function getRoot()
    {
        $query = "
          SELECT id AS root
          FROM {$this->forest_name}
          WHERE tree_id=:tree_id AND lft = 1;
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array(
            ':tree_id' => $this->tree_id
            ));
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return intval($row[0]);
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
    public function getParent($id_node)
    {
        $id_node = intval($id_node);

        $query = "
          SELECT parent
          FROM {$this->forest_name}_AdjTree
          WHERE tree_id= :tree_id AND child = :id_node;
        ";

        $out = NULL;

        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array(
            ':tree_id' => $this->tree_id,
            ':id_node' => $id_node
            ));

        $row = $stmt->fetch();

        if ($row === false) throw new Exception\NodeNotFound;

        if ($row[0] === NULL) $out = NULL;
        else $out = intval($row[0]);
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
     *    :return: the number of nodes in the selected subtree (counting in root of subtree)
     *    :rtype:  int
     */
    public function getSize($id_node=NULL)
    {
        $query = "
          SELECT (rgt-lft+1) DIV 2
          FROM {$this->forest_name}
          WHERE ". ($id_node !== NULL ? "id = :id_node" : "lft = 1").
                " AND tree_id= :tree_id";

        $stmt = $this->pdo->prepare($query);

        $prepareArray = array(
            ':tree_id' => $this->tree_id,
            );

        if ($id_node !== NULL) {
            $prepareArray[':id_node'] = intval($id_node);
        }

        $stmt->execute($prepareArray);

        $row = $stmt->fetch();

        return intval($row[0]);
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

        $prepareArray = array(
                    ":tree_id" => $this->tree_id,
                    );

        if ($id_node === NULL) {
            // we search for descendants of root
            $query = "SELECT id FROM {$this->forest_name}
                    WHERE tree_id=:tree_id AND lft <> 1 ORDER BY id";
        } else {
            // we search for a node descendants
            $id_node = intval($id_node);
            $prepareArray[":id_node"] = $id_node;
            $query = "
              SELECT id
              FROM {$this->forest_name}
              WHERE tree_id = :tree_id
                AND lft > (SELECT lft FROM {$this->forest_name} WHERE id = :id_node)
                AND rgt < (SELECT rgt FROM {$this->forest_name} WHERE id = :id_node)
              ORDER BY id
            ";
        }

        $ar_out = array();

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($prepareArray);
        while ($id = $stmt->fetch()) {
            $ar_out[] = intval($id[0]);
        }

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
    public function &getLeaves($id_node=NULL) {

        $prepareArray = array(
                    ":tree_id" => $this->tree_id,
                    );

        $query = "
          SELECT id AS leaf
          FROM {$this->forest_name}
          WHERE tree_id=:tree_id AND lft = (rgt - 1)";

        if ($id_node !== NULL) {
            // check only leaves of a subtree adding a "where" condition

            $id_node = intval($id_node);

            $prepareArray[":id_node"] = $id_node;

            $query .= " AND lft > (SELECT lft FROM {$this->forest_name} WHERE id = :id_node) ".
                      " AND rgt < (SELECT rgt FROM {$this->forest_name} WHERE id = :id_node) ";
        }

        $query .= " ORDER BY lft";

        $ar_out = array();

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($prepareArray);
        while ($id = $stmt->fetch()) {
            $ar_out[] = intval($id[0]);
        }

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
    public function &getLevels() {

        $query = "
          SELECT T2.id as id, (COUNT(T1.id) - 1) AS level
          FROM {$this->forest_name} AS T1 JOIN {$this->forest_name} AS T2
               on T1.tree_id=:tree_id AND T1.tree_id = T2.tree_id
          WHERE T2.lft BETWEEN T1.lft AND T1.rgt
          GROUP BY T2.lft
          ORDER BY T2.lft ASC;
        ";

        $ar_out = array();

        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array(
                    ":tree_id" => $this->tree_id,
                    ));

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $ar_out[] = array("id" => intval($row["id"]), "level" => intval($row["level"]));
        }

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
    public function &getPath($id_node, $fields=NULL, $squash=FALSE) {
        $id_node = intval($id_node);

        if (empty($fields)) {
            if ($squash) $fields = array("id");
            else $fields = array(); // ensure it is not NULL
        } elseif (is_string($fields)) $fields = array($fields);

        // append the field "id" if missing
        if (FALSE === array_search("id", $fields)) $fields[] = "id";

        $this->_sql_check_fields($fields);

        $fields_escaped = array();
        foreach ($fields as $fieldName) {
            $fields_escaped[] = sprintf("`%s`", str_replace("`", "``", $fieldName));
        }

        $query = "".
        " SELECT ".join(",", $fields_escaped).
        " FROM {$this->forest_name}".
        " WHERE tree_id=:tree_id AND ( SELECT lft FROM {$this->forest_name} WHERE id = :id_node ) BETWEEN lft AND rgt".
        " ORDER BY lft";

        $prepareArray = array(
            ':tree_id' => $this->tree_id,
            ':id_node' => $id_node
            );

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($prepareArray);

        $ar_out = array();
        if ($squash) {
            reset($fields);
            $fieldName = current($fields);
            while($rowAssoc = $stmt->fetch(\PDO::FETCH_ASSOC)) $ar_out[] = $rowAssoc[$fieldName];

        } else {
            while ($rowAssoc = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $tmp_ar = array();
                foreach ($fields as $fieldName) {
                    $tmp_ar[$fieldName] = $rowAssoc[$fieldName];
                }
                $ar_out[] = $tmp_ar;
            }
        }

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
    public function &getFirstNChildren($id_parent, $howMany=NULL, $fromLeftToRight=TRUE) {
        $id_parent = intval($id_parent);
        $howMany = intval($howMany);

        $query = " SELECT child FROM {$this->forest_name}_AdjTree ".
                 " WHERE parent = :id_parent ".
                 " ORDER BY lft ".($fromLeftToRight ? 'ASC' : 'DESC').
                 ($howMany ? " LIMIT $howMany" : ""); //LIMIT cannot be prepared

        $stmt = $this->pdo->prepare($query);

        $stmt->execute(array(
            ':id_parent' => $id_parent
            ));
        $rows = $stmt->fetchAll();

        $ar_out = array();
        foreach ($rows as $row) {
            $ar_out[] = intval($row[0]);
        }

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
    public function getFirstChild($id_parent)
    {
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
    public function getLastChild($id_parent)
    {
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
    public function getChildAtIndex($id_parent, $index)
    {
        $id_parent = intval($id_parent);
        $index = intval($index);

        $query = "CALL Baobab_{$this->forest_name}_getNthChild(:id_parent, :index, @child_id, @error_code);";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array(
            ':id_parent' => $id_parent,
            ':index' => $index
            ));
        $stmt->closeCursor();

        $output = $this->pdo->query("select @child_id as child_id");
        $output = $output->fetch(\PDO::FETCH_ASSOC);

        if ($output['child_id'] === null) throw new Exception\NodeNotFound;
        return intval($output['child_id']);
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
    public function &getTree($className="Baobab\BaobabNode", $appendChild="appendChild") {

        // this is a specialized version of the query found in getLevel()
        //   (the difference lying in the fact that here we retrieve all the
        //    fields of the table)
        $query = "
          SELECT (COUNT(T1.id) - 1) AS level ,T2.*
          FROM {$this->forest_name} AS T1 JOIN {$this->forest_name} AS T2
                on T1.tree_id=:tree_id AND T1.tree_id = T2.tree_id
          WHERE T2.lft BETWEEN T1.lft AND T1.rgt
          GROUP BY T2.lft
          ORDER BY T2.lft ASC;
        ";

        $root = NULL;
        $parents = array();

        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array(
                    ":tree_id" => $this->tree_id,
                    ));

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {

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
            } elseif (!empty($parents) && $rgt+1 == $parents[$numParents-1]->rgt) {

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
    public function deleteNode($id_node, $close_gaps=TRUE)
    {
        $id_node = intval($id_node);
        $close_gaps = $close_gaps ? 1 : 0;

        $stmt = $this->pdo->prepare("CALL Baobab_{$this->forest_name}_DropTree(:id_node, :close_gaps)");
        $stmt->execute(array(
            ':id_node' => $id_node,
            ':close_gaps' => $close_gaps
            ));
        $stmt->closeCursor();

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
    public function closeGaps() { //TODO: UnitTest

        $stmt = $this->pdo->prepare("CALL Baobab_{$this->forest_name}_Close_Gaps(:tree_id)");
        $stmt->execute(array(
            ':tree_id' => $this->tree_id
            ));
        $stmt->closeCursor();

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
    public function getTreeHeight()
    {
        $query = "
        SELECT MAX(level)+1 as height
        FROM (SELECT T2.id as id,(COUNT(T1.id)-1) as level
              FROM {$this->forest_name} as T1 JOIN {$this->forest_name} as T2
                   on T1.tree_id={$this->tree_id} AND T1.tree_id = T2.tree_id
              WHERE T2.lft  BETWEEN T1.lft AND T1.rgt
              GROUP BY T2.id
             ) as ID_LEVELS";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array(
                    ":tree_id" => $this->tree_id,
                    ));

        $row = $stmt->fetch();

        return intval($row[0]);;
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
    public function updateNode($id_node, $fields_values)
    {
        $id_node = intval($id_node);

        if (empty($fields_values)) throw new BaobabException("\$fields_values cannot be empty");

        $fields = array_keys($fields_values);
        $this->_sql_check_fields($fields);

        $prepareFields = '';
        foreach ($fields as $field) {
            $prepareFields[] = "$field = :$field";
            $prepareFieldsValues[':'.$field] = $fields_values[$field];
        }

        $prepareFields = join($prepareFields, ', ');

        $query = "".
         " UPDATE {$this->forest_name}".
         " SET $prepareFields".
         " WHERE id = :id_node";

        $stmt = $this->pdo->prepare($query);

        $prepareFieldsValues[':id_node'] = $id_node;

        $stmt->execute($prepareFieldsValues);
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
    public function getNodeData($id_node, $fields=NULL)
    {
        $id_node = intval($id_node);

        if (!empty($fields)) $this->_sql_check_fields($fields);

        $query = "".
        " SELECT ".($fields === NULL ? "*" : join(",", $fields)).
        " FROM {$this->forest_name} ".
        " WHERE id = :id_node";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array(
            ':id_node' => $id_node
            ));

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);;

        return $row;
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
    public function appendChild($id_parent=NULL, $fields_values=NULL)
    {
        // add tree_marks (if not yet added) as fields values
        $fields_values = (array) $fields_values + (array) $this->tree_marks;

        $id_parent = intval($id_parent);

        $query = "CALL Baobab_{$this->forest_name}_AppendChild(:tree_id, :id_parent, @new_id, @cur_tree_id);";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array(
            ':tree_id' => $this->tree_id,
            ':id_parent' => $id_parent
            ));
        $stmt->closeCursor();

        $output = $this->pdo->query("SELECT @new_id as new_id, @cur_tree_id as tree_id");
        $output = $output->fetch(\PDO::FETCH_ASSOC);
        $id = intval($output['new_id']);
        $this->tree_id = intval($output['tree_id']);

        //update the node if needed
        if (!empty($fields_values)) $this->updateNode($id, $fields_values);
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
    public function insertAfter($id_sibling, $fields_values=NULL)
    {
        $id_sibling = intval($id_sibling);

        //this will break if node does not exist
        $query = "CALL Baobab_{$this->forest_name}_insertAfter(:id_sibling, @new_id, @error_code);";

        $prepareArray = array(
            ':id_sibling' => $id_sibling,
            );

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($prepareArray);
        $stmt->closeCursor();

        $output = $this->pdo->query("SELECT @new_id as new_id, @error_code as error_code");
        $output = $output->fetch(\PDO::FETCH_ASSOC);

        $this->_checkForErrors($output['error_code']);

        //update the node if needed
        if ($fields_values !== NULL) $this->updateNode($output['new_id'], $fields_values);
        return intval($output['new_id']);
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
    public function insertBefore($id_sibling, $fields_values=NULL)
    {
        $id_sibling = intval($id_sibling);

        //this will break if node does not exist
        $query = "CALL Baobab_{$this->forest_name}_insertBefore(:id_sibling, @new_id, @error_code);";

        $prepareArray = array(
            ':id_sibling' => $id_sibling,
            );

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($prepareArray);
        $stmt->closeCursor();

        $output = $this->pdo->query("SELECT @new_id as new_id, @error_code as error_code");
        $output = $output->fetch(\PDO::FETCH_ASSOC);

        $this->_checkForErrors($output['error_code']);

        //update the node if needed
        if ($fields_values !== NULL) $this->updateNode($output['new_id'], $fields_values);
        return intval($output['new_id']);
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
    public function insertChildAtIndex($id_parent, $index, $fields_values=NULL)
    {
        $id_parent = intval($id_parent);
        $index = intval($index);

        $query = "CALL Baobab_{$this->forest_name}_InsertChildAtIndex(:id_parent, :index, @new_id, @error_code);";

        $prepareArray = array(
            ':id_parent' => $id_parent,
            ':index' => $index
            );

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($prepareArray);
        $stmt->closeCursor();

        $output = $this->pdo->query("SELECT @new_id as new_id, @error_code as error_code");
        $output = $output->fetch(\PDO::FETCH_ASSOC);

        $this->_checkForErrors($output['error_code']);

        //update the node if needed
        if ($fields_values !== NULL) $this->updateNode($output['new_id'], $fields_values);
        return intval($output['new_id']);
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
     *         throw a BaobabException exception
     *
     */
    public function moveAfter($id_to_move, $reference_node)
    {
        $id_to_move = intval($id_to_move);
        $reference_node = intval($reference_node);

        //this will break if node does not exist
        $query = "CALL Baobab_{$this->forest_name}_MoveSubtreeAfter(:id_to_move, :reference_node, @error_code);";

        $prepareArray = array(
            ':id_to_move' => $id_to_move,
            ':reference_node' => $reference_node
            );

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($prepareArray);
        $stmt->closeCursor();

        $this->_checkForErrors();
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
     *         throw a BaobabError exception
     *
     */
    public function moveBefore($id_to_move,$reference_node)
    {
        $id_to_move = intval($id_to_move);
        $reference_node = intval($reference_node);

        //this will break if node does not exist
        $query = "CALL Baobab_{$this->forest_name}_MoveSubtreeBefore(:id_to_move, :reference_node, @error_code);";

        $prepareArray = array(
            ':id_to_move' => $id_to_move,
            ':reference_node' => $reference_node
            );

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($prepareArray);
        $stmt->closeCursor();

        $this->_checkForErrors();
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
     *         throw a BaobabError exception
     *
     */
    public function moveNodeAtIndex($id_to_move,$id_parent,$index)
    {
        $id_to_move = intval($id_to_move);
        $id_parent = intval($id_parent);
        $index = intval($index);

        //this will break if node does not exist
        $query = "CALL Baobab_{$this->forest_name}_MoveSubtreeAtIndex(:id_to_move, :id_parent, :index, @error_code);";

        $prepareArray = array(
            ':id_to_move' => $id_to_move,
            ':id_parent' => $id_parent,
            ':index' => $index
            );

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($prepareArray);
        $stmt->closeCursor();

        $this->_checkForErrors();
    }

    /**
     * TODO documentation
     */
    private function _checkForErrors($error_code = NULL)
    {
        if ($error_code === NULL) {
            $output = $this->pdo->query("SELECT @error_code as error_code");
            $output = $output->fetch(\PDO::FETCH_ASSOC);

            if (!isset($output['error_code'])) {
                return;
            } else {
                $error_code = $output['error_code'];
            }
        }

        switch ($error_code) {
            case 'ROOT_ERROR':
                throw new Exception\InsertOutsideRoot('Cannot add or move a node next to root');
            case 'INDEX_OUT_OF_RANGE':
                throw new Exception\IndexOutOfRange('The index is out of range');
            case 'NODE_DOES_NOT_EXIST':
                throw new Exception\NodeNotFound('Node doesn\'t exist');
            case 'CHILD_OF_YOURSELF_ERROR':
                throw new Exception\ChildOfYourself('Cannot move a node inside his own subtree');
            default:
                throw new Exception\BaobabException('Error code: '.$error_code);
            break;
        }
    }

}
