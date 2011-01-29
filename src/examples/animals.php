<?php

class AnimalsBaobab extends Baobab {
    
    // override build function to create the table the way we want it
    public function build() {
        if (parent::build()) { // the table wasn't existing and has been built
            
            $result = $this->db->query("
                ALTER TABLE {$this->forest_name}
                ADD COLUMN name VARCHAR(50) NOT NULL
                "
            );
            if (!$result) throw new sp_MySQL_Error($this->db);
        }
    }
}

function start_over($conn,$forestName){
    $tree=new AnimalsBaobab($conn,$forestName);
    $tree->destroy(TRUE);
    $tree->build();
}

function main($conn){ // $conn is a mysqli connection object
    
    $forestName="animals";
    start_over($conn,$forestName);
    
    // Create a tree in the forest. It will get automatically his id
    //  (you could read it via the public member "tree_id" ) and we will leave
    //  it alone. A table called $forestName will be created if not yet exists
    $tree=new AnimalsBaobab($conn,$forestName);
    
    /*
    
    We could import some well constructed data structure, but we're doing
    it the long and clumsy way to show some functions.
    
    Say we want to map this tree ...
    
    Animals
        ┣━━ Vertebrates
        ┃    ┣━━━ Mollusks
        ┃    ┗━━━ Insects
        ┃             ┗━━━ Mantis
        ┗━━ Invertebrates
              ┗━━━ Mammals
                      ┣━━━ Tiger
                      ┗━━━  Horse
    
    */
    
    // utility function to have something more readable later
    
    // Append the tree children, each node asks the parent id first, 
    // and the values later
    $root_id=$tree->appendChild(NULL,array("name"=>'Animals'));
    
    $vertebrates_id   = $tree->appendChild($root_id,array("name"=>"Vertebrates"));
    $invertebrates_id = $tree->appendChild($root_id,array("name"=>"Invertebrates"));
    
    // add vertebrates first ...
    $mollusks_id = $tree->appendChild($vertebrates_id,array("name"=>"Mollusks"));
    $insects_id  = $tree->appendChild($vertebrates_id,array("name"=>"Insects"));
    
    $mantis_id = $tree->appendChild($insects_id,array("name"=>"Mantis"));
    
    // and invertebrates next ...
    $mammals_id = $tree->appendChild($root_id,array("name"=>"Mammals"));
    
    // (we skip intentionally 'tiger')
    $horse_id = $tree->appendChild($mammals_id,array("name"=>"Horse"));
    
    // Add tiger before 'horse'. We could have done it earlier with an
    // appendChild but you're learning here. Indexes start from 0.
    $tiger_id = $tree->insertBefore($horse_id,array("name"=>"Tiger"));
    
    /*
    Now the content of the 'animals' table is
    
    =======  ==  ===  ===  =============
    tree_id  id  lft  rgt  name     
    =======  ==  ===  ===  =============
          1   1    1   18  Animals
          1   2    2    9  Vertebrates
          1   3   10   11  Invertebrates     
          1   4    3    4  Mollusks
          1   5    5    8  Insects 
          1   6    6    7  Mantis      
          1   7   12   17  Mammals      
          1   8   15   16  Horse
          1   9   13   14  Tiger
    =======  ==  ===  ===  =============
    
    */
    
    // To obtain all the elements beetween root and a node ...
    $parts=$tree->getPath($insects_id);
    print_r(json_encode($parts));
    // output: [{"id":"1"},{"id":"2"},{"id":"5"}]
    echo "\n";
    
    // To obtain all the elements beetween root and a node filtering by a field...
    $filtered_parts=$tree->getPath($insects_id,'name',TRUE);
    print_r(join(" » ",$filtered_parts));
    // output: Animals » Vertebrates » Insects
    echo "\n";
    
    // Get the crystalized state of the tree, either as a class instance ...
    $rootTreeState=$tree->getTree();
    print_r($rootTreeState->children);
    echo "\n";
    
    // ... or as a JSON string
    print_r($tree->export($conn,$forestName));
    echo "\n";
    
    // Next time you want to access this tree, if you're using a table to store
    // a single tree then pass -1 as tree id
    $treeLoadedAgain=new AnimalsBaobab($conn,$forestName,-1);
    // (otherwise you would need to know his tree id [check the documentation])
}
