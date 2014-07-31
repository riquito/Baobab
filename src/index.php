<pre><?php
require '/home/isinlor/Documents/Baobab-PDO/src/baobab.php';

$dsn = 'mysql:host=localhost;dbname=manage_tabs';
$username = 'root';
$password = '';
$options = array(
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
); 

$pdo = new PDO($dsn, $username, $password, $options);

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// $query = 'SELECT child FROM manage_tabs.module_files_tree_AdjTree  WHERE parent = 76  ORDER BY lft ASC;';
// $query2 = 'SELECT name FROM module_files_tree  WHERE id = 76';
// $query3 = 'CALL Baobab_module_files_tree_getNthChild(60, 0, @child_id, @error_code);
//                 SELECT @child_id as child_id, @error_code as error_code;';
// $query4 = 'SHOW COLUMNS FROM `module_files_tree`;';


// /////////////////PDO while($row = $result->fetch_row())


// $result = $this->pdo->query($query);
// $rows = $result->fetchAll();

// if (empty($rows)) throw new sp_MySQL_Error($pdo);

// $ar_out = array();
// foreach ($rows as $row) {
//     $ar_out[] = intval($row[0]);
// }

// var_dump($ar_out);


// //////////////// $result->fetch_assoc()


// $result = $this->pdo->query($query2);
// $row = $result->fetch(PDO::FETCH_ASSOC);
// if (empty($row)) throw new sp_MySQL_Error($pdo);

// var_dump($row);


// ///////////// if (!$db->multi_query($query3))


// $stmt = $this->pdo->prepare($query3);
// $stmt->execute();
// $stmt->closeCursor();

// $output = $this->pdo->query("select @child_id as child_id");
// $output = $output->fetch(PDO::FETCH_ASSOC);
// var_dump($output);


// ///////////////// $result->fetch_array(MYSQLI_ASSOC)


// $result = $this->pdo->query($query4);
// $rows = $result->fetchAll(PDO::FETCH_ASSOC);
// if (empty($rows)) throw new sp_MySQL_Error($db);

// foreach ($rows as $row) {
//     $fields[] = $row["Field"];
// }
// var_dump($fields);






$mysqli = new mysqli("localhost", "root", "", "manage_tabs");

// /////////////////////////MySQLi
// $result = $db->query($query, MYSQLI_STORE_RESULT);
// if (!$result) throw new sp_MySQL_Error($db);

// $ar_out = array();
// while($row = $result->fetch_row()) {
//     $ar_out[] = intval($row[0]);
// }
// $result->close();

// var_dump($ar_out);

// ///////////////////////
// $result = $db->query($query2, MYSQLI_STORE_RESULT);
// if (!$result) throw new sp_MySQL_Error($db);

// var_dump($result->fetch_assoc());

// ///////////////////

// // if (!$db->multi_query($query3))
// //         throw new sp_MySQL_Error($this->db);

// // $res = $this->_readLastResult('child_id');
// // return intval($res['child_id']);

// ////////////////////////

// $result = $db->query($query4, MYSQLI_STORE_RESULT);
// if (!$result) throw new sp_MySQL_Error($db);

// $fields = array();
// while($row = $result->fetch_array(MYSQLI_ASSOC)) {
//     $fields[] = $row["Field"];
// }
// $result->close();
// var_dump($fields);





class AnimalsBaobab extends Baobab {
    // override build function to create the table the way we want it
    public function build() {
        if (parent::build()) { // the table wasn't existing and has been built
            
            $result = $this->db->query("
                ALTER TABLE {$this->forest_name}
                ADD COLUMN name VARCHAR(50) NOT NULL
                "
            );
            //if (!$result) throw new sp_MySQL_Error($this->db);
        }
    }
}

function start_over($conn,$forestName){
    $tree=new AnimalsBaobab($mysqli, $pdo, $forestName);
    $tree->destroy(TRUE);
    $tree->build();
}

function main($mysqli, $pdo){ // $conn is a mysqli connection object
    
    $forestName="animals";

    //start_over($mysqli, $pdo, $forestName);

    $projectID = isset($_GET['projectID']) ? $_GET['projectID'] : 1;

    $tree = new AnimalsBaobab($mysqli, $pdo, $forestName, $projectID);

    $tree->getChildAtIndex(60, 0);

    $rootID = $tree->getRoot();

    $folderID = isset($_GET['folderID']) ? $_GET['folderID'] : $rootID;

    if ($rootID === null) {
        $rootID = $tree->appendChild(NULL, array('name' => 'Root Folder'));
        $folderID = $rootID;
    }

    if (isset($_GET['renameTo'])) {
        $tree->updateNode($folderID, array('name' => $_GET['renameTo']));
    }

    $parentID = $tree->getParent($folderID);

    $folderData = $tree->getNodeData($folderID, array('name'));

    $folderChildrens = $tree->getChildren($folderID);

    echo "Project: $projectID";
    $nextProjectID = $projectID + 1;
    echo " <a href='index.php?projectID=$nextProjectID'>Next Project</a> ";
    echo "<br>This is folder: ".$folderData['name'];
    echo "<br><a href='index.php?projectID=$projectID&folderID=$folderID&renameTo=RenamedFolder'>Rename folder to: RenamedFolder</a> ";

    if ($rootID == $folderID) {
        echo '<br>You are at Root level.';
    } else {

        echo "<br><a href='index.php?projectID=$projectID&folderID=$rootID'>Go to Root</a> ";

       if ($rootID != $parentID) {
            echo "<br><a href='index.php?projectID=$projectID&folderID=$parentID'>Go up</a> ";
       }

    }

    if (isset($_GET['newFolder'])) {
        $newFolder = $tree->appendChild($folderID, array('name' => $_GET['newFolder']));
        $folderChildrens[] = $newFolder;
        echo "<br>";
        echo "New folder added: ".$_GET['newFolder']."</a>";
    }

    if (!empty($folderChildrens)) {
        echo "<br>Folders inside:<br>";
    } else {
        echo '<br>Nothing inside<br>';
    }

    foreach ($folderChildrens as $childrenFolderID) {
        $childrenFolderData = $tree->getNodeData($childrenFolderID, array('name'));
        echo "<a href='index.php?projectID=$projectID&folderID=$childrenFolderID'>Go to: ".$childrenFolderData['name']."</a>";
        echo "<br>";
    }

    $childrenNum = count($folderChildrens);

    echo "<br><a href='index.php?projectID=$projectID&folderID=$folderID&newFolder=NewFolder$childrenNum'>Add new folder</a>";
    
}

main($mysqli, $pdo);