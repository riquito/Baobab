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

$pdo->query('SET PROFILING = 1;');

class FoldersTree extends Baobab {
    // override build function to create the table the way we want it
    public function build() {
        if (parent::build()) { // the table wasn't existing and has been built
            
            $result = $this->db->query("
                ALTER TABLE {$this->forest_name}
                ADD COLUMN name VARCHAR(50) NOT NULL,
                ADD COLUMN projectId VARCHAR(45) NOT NULL,
                ADD INDEX select_id (projectId)
                "
            );
        }
    }
}

function start_over($pdo, $forestName){
    $tree=new FoldersTree($pdo, $forestName);
    $tree->destroy(TRUE);
    $tree->build();
}

function main($pdo){ // $conn is a mysqli connection object
    
    $forestName="folders";

    //start_over($mysqli, $pdo, $forestName);

    $projectID = isset($_GET['projectID']) ? $_GET['projectID'] : 1;

    $tree = new FoldersTree($pdo, $forestName, array('projectId' => $projectID));

    $rootID = $tree->getRoot();

    $folderID = isset($_GET['folderID']) ? $_GET['folderID'] : $rootID;

    if ($rootID === null) {
        $rootID = $tree->appendChild(NULL, array('name' => 'Root Folder', 'projectId' => $projectID));
        $folderID = $rootID;
    }

    if (isset($_GET['renameTo'])) {
        $tree->updateNode($folderID, array('name' => $_GET['renameTo']));
    }

    $parentID = $tree->getParent($folderID);

    $folderData = $tree->getNodeData($folderID, array('name'));

    if (isset($_GET['folderToDelete']) && $rootID != $_GET['folderToDelete']) {
        $folderToRemoveData = $tree->getNodeData($_GET['folderToDelete'], array('name'));
        $tree->deleteNode($_GET['folderToDelete'], true);
    }

    $folderChildrens = $tree->getChildren($folderID);

    echo "Project: $projectID";
    $nextProjectID = $projectID + 1;
    echo " <a href='index.php?projectID=$nextProjectID'>Next Project</a> ";
    echo "<br>This is folder: ".$folderData['name'];
    echo '<br>Root folder tree size: '.$tree->getSize();
    echo '<br>Subfolder tree size: '.$tree->getSize($folderID);
    echo "<br><a href='index.php?projectID=$projectID&folderID=$folderID&renameTo=RenamedFolder'>Rename folder to: RenamedFolder</a> ";

    if ($rootID == $folderID) {
        echo '<br>You are at Root level.';
    } else {

        echo "<br><a href='index.php?projectID=$projectID&folderID=$rootID'>Go to Root</a> ";

       if ($rootID != $parentID) {
            echo "<br><a href='index.php?projectID=$projectID&folderID=$parentID'>Go up</a> ";
       }

    }

    if (isset($_GET['folderToDelete']) && $rootID != $_GET['folderToDelete']) {
        echo "<br>";
        echo "You deleted folder: ".$folderToRemoveData['name'];
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
        if($rootID != $folderID){
            echo "<a href='index.php?projectID=$projectID&folderID=$parentID&folderToDelete=$folderID'>You can delete this folder</a>";
        }
    }

    foreach ($folderChildrens as $childrenFolderID) {
        $childrenFolderData = $tree->getNodeData($childrenFolderID, array('name'));
        echo "<a href='index.php?projectID=$projectID&folderID=$childrenFolderID'>Go to: ".$childrenFolderData['name']."</a>";
        echo "<br>";
    }

    $childrenNum = count($folderChildrens);

    echo "<br><a href='index.php?projectID=$projectID&folderID=$folderID&newFolder=NewFolder$childrenNum'>Add new folder</a>";
    
}

main($pdo);
$test = $pdo->query('SHOW PROFILES;');
var_dump($test->fetchAll());