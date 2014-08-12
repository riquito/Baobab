<?php
namespace Baobab;
use Baobab\Exception;
use Baobab\Baobab as Baobab;

// define BAOBAB_SQL_DIR as the absolute path with no leading / pointing to
// the root directory holding the sql files
if (!defined("BAOBAB_SQL_DIR")) define("BAOBAB_SQL_DIR", dirname(__FILE__).DIRECTORY_SEPARATOR."sql");

/**
* 
*/
class Forest
{
    // protected $pdo;
    // protected $forestName;

    // function __construct(\PDO $pdo, $forestName)
    // {
    //     $this->pdo = $pdo;
    //     $this->sql_utils = new SqlUtils( $pdo );
    //     $this->forest_name = $forest_name;
    // }

    public static function getTree(\PDO $pdo, $forestName, $tree_id=NULL)
    {

        $tree_marks = $tree_id;
        // if $tree_marks is -1 we suppose there is one and only one tree yet in
        //   the table, and we automatically retrieve his id
        if (!is_array($tree_marks) && $tree_marks == -1 ) {
            // $query = "SELECT DISTINCT tree_id FROM {$this->forest_name} LIMIT 2";
            // if ($result = $this->db->query($query, MYSQLI_STORE_RESULT)) {
            //     $num_trees = $result->num_rows;

            //     if ($num_trees == 1) {
            //         // one and only, let's use it
            //         $row = $result->fetch_row();
            //         $tree_id = intval($row[0]);
            //     }
            //     else if ($num_trees == 0) {
            //         // no trees ? it will be the first
            //         $tree_id = 0;
            //     }
            //     $result->close();

            //     if ($num_trees>1) {
            //         throw new ToManyTreesFound;
            //     }

            // } else {
            //     if ($this->db->errno == 1146) {
            //         // table does not exist (skip), so it will be the first tree
            //     } else throw new BaobabException($this->db);
            // }
        // get tree by select ids array
        } elseif (is_array($tree_marks) && !empty($tree_marks)) { // tree distinction

            $prepareFields = '';
            $rows = array();
            foreach ($tree_marks as $column => $value) {
                $prepareFields[] = "$column = :$column";
                $prepareFieldsValues[':'.$column] = $value;
            }

            $prepareFields = join($prepareFields, ' AND ');

            try {
                $query = "SELECT DISTINCT tree_id FROM {$this->forest_name} WHERE $prepareFields LIMIT 2";

                $stmt = $this->pdo->prepare($query);

                $stmt->execute($prepareFieldsValues);

                $rows = $stmt->fetchAll();
            } catch (\PDOException $e) {
                if ($e->getCode() !== '42S02') { // do not count "missing table" as an error
                    throw $e;
                }
            }

            $num_trees = count($rows);

            if ($num_trees == 1) {
                // one and only, let's use it
                $row = $rows[0];
                $tree_id = intval($row[0]);
            } elseif ($num_trees == 0) {
                // no trees ? it will be the first
                $tree_id = 0;
            } elseif ($num_trees>1) {
                throw new ToManyTreesFound;
            }

            //$this->tree_marks = $tree_marks;

        }

        return new Baobab($pdo, $forestName, intval($tree_id));

    }

    /**@
     * .. method:: forestExists($table_name[,$db_name=NULL])
     *
     *    Check if a table exists.
     *
     *    :return: TRUE if exists, FALSE otherwise
     *    :rtype:  boolean
     */
    public static function forestExists(\PDO $pdo, $forestName)
    {
        try {
            $pdo->query("SELECT 1 FROM $forestName LIMIT 1");
            return true;
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S02') { // do not count "missing table" as an error
                throw $e;
            }

            return false;
        }
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
     */
    public static function build(\PDO $pdo, $forestName)
    {
        $forestExists = self::forestExists($pdo, $forestName);

        // build the forest only if the database schema not yet exists
        if (!$forestExists) {
            $sql = file_get_contents(BAOBAB_SQL_DIR.DIRECTORY_SEPARATOR."schema_baobab.sql");
            $pdo->exec(str_replace("GENERIC", $forestName, $sql));
        }

        return ! $forestExists;
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
    public static function destroy(\PDO $pdo, $forestName, $removeDataTable=FALSE)
    {
            try {
                $pdo->beginTransaction();
                //$pdo->query("UNLOCK TABLES;");
                if ($removeDataTable) {
                    $removeDataTable = self::forestExists($pdo, $forestName);
                }
                $query = str_replace("GENERIC", $forestName,"
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
                        ");

                $pdo->query($query);
                if ($removeDataTable) {
                    $query = str_replace("GENERIC", $forestName, "DROP TABLE GENERIC;");
                    $pdo->query($query);
                }
                $pdo->commit();

            } catch (\PDOException $e) {
                $pdo->rollback();
                throw $e;
            }

    }

    /**!
     * .. staticmethod:: cleanAll($forest_name)
     *
     *    Delete all the records in $forest_name
     *
     *    :param $pdo: pdo connection
     *    :type $pdo:  an instance of pdo
     *    :param $forest_name: name of the forest, equals to the name of the table
     *                       holding the data
     *    :type $forest_name:  string
     */
    public static function cleanAll(\PDO $pdo, $forest_name) // TODO: Unit Test 
    {
        // delete all records, ignoring "missing table" error if happening
        try {
            $pdo->query("DELETE FROM {$forest_name}");
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S02') {
                throw $e;
            }
        }
    }

    /**!
     * .. staticmethod:: import($forest_name,$data)
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
    public static function &import($pdo, $forest_name, $data) {
        //$db = $pdo;
        if (is_string($data)) $data = json_decode($data, TRUE);
        if (!$data || empty($data)) {$x = NULL;return $x;} // nothing to import

        // check if the table exists before doing anything else
        if ( ! self::forestExists($pdo, $forest_name)) {
            throw new BaobabException("Table `{$forest_name}` does not exist");
        }

        $ar_out = array();

        $pdo->beginTransaction();

        try {

            foreach ($data as $tmp_data) {

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
                    $result = $pdo->query($query);
                    if (!$result) throw new BaobabError($db);
                    $row = $result->fetch();
                    $tree_id = intval($row[0]);

                } else {
                    // ensure the tree_id isn't in use
                    $query = "SELECT DISTINCT tree_id FROM {$forest_name} WHERE tree_id={$tree_id}";
                    $result = $pdo->query($query);
                    if (!$result) throw new BaobabError($db);
                    $tree_exists = $result->fetch();

                    if ($tree_exists) throw new BaobabException("ImportError: tree_id {$tree_id} yet in use");
                }

                $tree = new Baobab( $pdo, $forest_name, $tree_id);

                // if there are only standard fields we can also try to build the table
                //   (otherwise it must yet exist because we can't guess field types)

                $standard_fields = array("id", "lft", "rgt", "tree_id");
                if (count($tmp_data["fields"]) <= 4) {
                    $is_standard = TRUE;
                    foreach ($tmp_data["fields"] as $fieldName) {
                        if (FALSE === array_search($fieldName, $standard_fields)) {
                            $is_standard = FALSE;
                            break;
                        }
                    }
                    if ($is_standard) {
                        try {
                            self::build($pdo, $forest_name); // if yet exists is harmless
                        } catch (BaobabError $e) {
                            if ($e->getCode() != '42S01') throw $e; // 1050 is "table exists"
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
                    foreach ($values as &$row) {
                        $row[] = $tree_id;
                    }
                }

                // make unique prepare field ID for each value in rows
                // group prepare field ID in row, and then combine them like that:
                // ((:fieldRow1, :fieldRow1), (:fieldRow2, :fieldRow2))
                $prepareFields = '(';
                foreach ($values as $rowKey => $valuesRow) {
                    $prepareFieldsRow = array();
                    foreach ($tmp_data["fields"] as $fieldKey => $field) {
                        $prepareFieldsRow[] = ":$field$rowKey";
                        $prepareFieldsValues[':'.$field.$rowKey] = $valuesRow[$fieldKey];
                    }
                    $prepareFieldsRows[] = join($prepareFieldsRow, ', ');
                }
                $prepareFields .= join($prepareFieldsRows, '), (');
                $prepareFields .= ')';

                $query = "INSERT INTO {$forest_name}(".join(",", $tmp_data["fields"]).") VALUES $prepareFields";
                $stmt = $pdo->prepare($query);
                $stmt->execute($prepareFieldsValues);

                $ar_out[] = $tree;

            } // end foreach $data
            $pdo->commit();

        } catch (Exception $e) {
            // whatever happens we must rollback
            $pdo->rollback();
            throw $e;
        }

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
    private static function _traverse_tree_to_export_data($node,&$data,&$fieldsFlags,&$fieldsOrder)
    {
        $len_data = count($data);

        $tmp_ar = array();

        //$i = 0;

        // get fields and values in the correct order
        foreach ($fieldsOrder as $fieldName) {
            if ($fieldName == 'id') $value = $node->id;
            else if ($fieldName == 'lft') $value = $node->lft;
            else if ($fieldName == 'rgt') $value = $node->rgt;
            else $value = $node->fields_values[$fieldName];

            //if ($fieldsFlags[$i++] & MYSQLI_NUM_FLAG) $value = floatval($value);

            $tmp_ar[] = $value;
        }

        $tmp_ar[] = array(); // the last element holds the children

        // append the child data to parent data
        $data[$len_data-1][] = &$tmp_ar;

        foreach ($node->children as $childNode) {
            self::_traverse_tree_to_export_data($childNode, $tmp_ar, $fieldsFlags, $fieldsOrder);
        }

    }

    /**!
     * .. staticmethod:: export($forest_name[,$fields=NULL[,$tree_id=NULL]])
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
    public static function export($pdo, $forest_name,$fields=NULL,$tree_id=NULL)
    {
        // check if the table exists before doing anything else
        if ( ! self::forestExists($pdo, $forest_name)) {
            throw new BaobabException("Table `Baobab_{$forest_name}` does not exist");
        }

        // get all the fields to export or check if the passed fields are valid
        $tree = new Baobab( $pdo, $forest_name, NULL); // use a unexistent tree in the right table
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
        } else {
            $query = "SELECT DISTINCT tree_id FROM {$forest_name}";
            $result = $pdo->query($query);
            if (!$result) throw new BaobabException;
            while ($row = $result->fetch()) $ar_tree_id[] = intval($row[0]);
        }

        // get the type of the columns mainly to write numbers as ... numbers
        $fieldsFlags = array(); // each index will have the field flag, to know his type
        //$result = $db->query(
        //    "SELECT ".join(",", $fields)." FROM {$forest_name} LIMIT 1", MYSQLI_STORE_RESULT);
        //if (!$result)  throw new BaobabException;
        // retrieve the column names and their types
        //while ($finfo = $result->fetch_field()) {
        //    $fieldsFlags[] = $finfo->flags;
        //}
        //$result->close();

        // parse each tree and build an array to jsonize later
        $ar_out = array();
        foreach ($ar_tree_id as $tree_id) {
            $tmp_ar = array("tree_id" => $tree_id, "fields" => $fields, "values" => NULL);

            // retrieve the data
            $tree = new Baobab( $pdo, $forest_name, $tree_id);
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