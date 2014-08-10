<?php
namespace Baobab;
/**@
 * .. class:: SqlUtils
 *
 *    Class with helpers to work with SQL
 *
 *    :param $conn: database connection object
 *    :type $conn:  mysqli
 */
class SqlUtils
{
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
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
    public function &get_table_fields($table_name) {
        $table_name = str_replace('`', '``', $table_name);

        $result = $this->pdo->query("SHOW COLUMNS FROM `{$table_name}`;");
        $rows = $result->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($rows)) throw new BaobabException;

        foreach ($rows as $row) {
            $fields[] = $row["Field"];
        }

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
    public function table_exists($table_name,$db_name=NULL)
    {
        // if (!$db_name) {
        //     $result = $this->pdo->query("SELECT DATABASE()");
        //     $rows = $result->fetch();
        //     $db_name = $rows[0];
        // }

        // $query = 'SELECT COUNT(*)
        //           FROM information_schema.tables
        //           WHERE table_schema = :db_name
        //           AND table_name = :table_name';

        // $stmt = $this->pdo->prepare($query);
        // $stmt->execute(array(
        //     ":db_name" => $db_name,
        //     ":table_name" => $table_name
        // ));

        // $rows = $stmt->fetch();

        // return $rows[0] == 1;
        try {
            $this->pdo->query("SELECT 1 FROM $table_name LIMIT 1");

            return true;
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S02') { // do not count "missing table" as an error
                throw $e;
            }

            return false;
        }
    }
}