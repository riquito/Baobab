<?php

class MenuBaobab extends Baobab {

    public function build() {
        if (parent::build()) { // the table wasn't existing and has been built
            
            $result = $this->db->query("
                ALTER TABLE {$this->tree_name}
                ADD COLUMN name VARCHAR(50) NOT NULL,
                ADD COLUMN urlPart VARCHAR(1000)
                "
            );
            if (!$result) throw new sp_MySQL_Error($this->db);
        }
    }
}

function main($conn){ // $conn is a mysqli connection object
    
    $treeName="menu";
    
    // Create a tree in the forest. It will get automatically his id
    //  (you could read it via the public member "tree_id" ) and we will leave
    //  it alone. A table called $treeName will be created if not yet exists
    $tree=new MenuBaobab($conn,$treeName,-1);
    
    // If you wrongly altered the table and screwed up everything exit once with
    // return $tree->destroy(TRUE);
    
    // remove existing data so that we can run this example more than once 
    $tree->clean();
    
    /*
    
    We could import some well constructed data structure, but we're doing
    it the long and clumsy way to show some functions.
    
    Say we want to map this menu ...
    
    |- Home
    |- Articles
    |---|-Foo
    |---|-Bar
    |      |---abc
    |      |---xyz
    |- Info
    |- About us
    
    .. to these urls ...
    
    /index.html
    /projects/foo.html
    /projects/bar/abc.html
    /projects/bar/xyz.html
    /info.html
    /about-us.html
    
    We consider each leaf (ending node) an html page for the sake of simplicity.
    
    */
    
    // utility function to have something more readable later
    function makeFields($name,$url){
        return array("name"=>$name,"urlPart"=>$url);
    }
    
    // Append the tree children, each node asks the parent id first, 
    // and the values later
    $root_id=$tree->appendChild(NULL,makeFields("root","root"));
    
    $tree->appendChild($root_id,makeFields("Home","index"));
    $tree->appendChild($root_id,makeFields("Info","info"));
    $tree->appendChild($root_id,makeFields("About us","about-us"));
    
    // Add a project menu after 'home'. We could have done it earlier with an
    // appendChild but you're learning here. Indexes start from 0.
    $projects_id=$tree->insertChildAtIndex($root_id,1,
                                           makeFields("Projects","projects"));
    
    $tree->appendChild($projects_id,makeFields("Foo","foo"));
    
    $bar_id=$tree->appendChild($projects_id,makeFields("Bar","bar"));
    $tree->appendChild($bar_id,makeFields("Project x","project-x"));
    $proj_y_id=$tree->appendChild($bar_id,makeFields("Project y","project-y"));
    
    /*
    Now the content of the 'menu' table is
    
    =======  ==  ===  ===  =========  =========
    tree_id  id  lft  rgt  name       urlPart
    =======  ==  ===  ===  =========  =========
          1   1    1   18  root       root
          1   2    2    3  Home       index
          1   3   14   15  Info       info
          1   4   16   17  About us   about-us
          1   5    4   13  Projects   projects
          1   6    5    6  Foo        foo
          1   7    7   12  Bar        bar
          1   8    8    9  Project x  project-x
          1   9   10   11  Project y  project-y
    =======  ==  ===  ===  =========  =========
    
    */
    
    // Say we are navigating inside project y, to construct a breadcumb ...
    $breadcrumb_parts=$tree->getPath($proj_y_id,array('name'),TRUE);
    print_r(join(" » ",array_slice($breadcrumb_parts,1))); // skip 'root'
    // output: Projects >> Bar >> Project y
    echo "\n";
    
    // Generate a url to 'project_y'
    $url_parts=$tree->getPath($proj_y_id,array('urlPart'),TRUE);
    print_r("/".join("/",array_slice($url_parts,1)).".html");
    // output: /projects/bar/project-y.html
    echo "\n";
    
    // Get the crystalized state of the tree, either as a class instance ...
    $rootTreeState=$tree->getTree();
    print_r($rootTreeState->children);
    echo "\n";
    
    // ... or as a JSON string
    print_r($tree->export($conn,$treeName));
    echo "\n";
}
