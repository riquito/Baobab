<?php

class ForumBaobab extends Baobab {
    
    // override build function to create the table the way we want it
    public function build() {
        if (parent::build()) { // the table wasn't existing and has been built
            
            $result = $this->db->query("
                ALTER TABLE {$this->forest_name}
                ADD COLUMN username VARCHAR(100) NOT NULL,
                ADD COLUMN body MEDIUMTEXT
                "
            );
            if (!$result) throw new sp_MySQL_Error($this->db);
        }
    }
}

function start_over($conn,$forestName){
    $tree=new ForumBaobab($conn,$forestName);
    $tree->destroy(TRUE);
    $tree->build();
}

function main($conn){ // $conn is a mysqli connection object
    
    $forestName="forum";
    start_over($conn,$forestName);
    
    // utility function to have something more readable later
    function makeFields($username,$body){
        return array("username"=>$username,"body"=>$body);
    }
    
    // Create two trees in the forest and populate them with some children.
    
    $thread1=new ForumBaobab($conn,$forestName);
    $thread2=new ForumBaobab($conn,$forestName);
    
    $a=$thread1->appendChild(NULL,makeFields("joe","I like flowers"));
    $b=$thread1->appendChild($a,makeFields("max","mee too"));
    $c=$thread1->appendChild($a,makeFields("carol","I don't"));
    $d=$thread1->appendChild($c,makeFields("joe","why not?"));
    
    $x=$thread2->appendChild(NULL,makeFields("carol","why should do this?"));
    $y=$thread2->appendChild($x,makeFields("joe","because we can"));
    $z=$thread2->appendChild($y,makeFields("max","isn't it just lame?"));
    $w=$thread2->appendChild($z,makeFields("joe","uh?"));
    
    
    // we can freely move the nodes around the same forest (any tree could ask
    // for the movements beeing the node ids unique in the forest)
    $thread1->moveAfter($z,$c);
    
    
    /*
    Now the content of the 'forum' table is
    
    =======  ==  ===  ===  ========  ===================
    tree_id  id  lft  rgt  username  body
    =======  ==  ===  ===  ========  ===================
          1   1    1   12  joe       I like flowers
          1   2    2    3  max       mee too
          1   3    4    7  carol     I don't
          1   4    5    6  joe       why not?
          2   5    1    4  carol     why should do this?
          2   6    2    3  joe       because we can
          1   7    8   11  max       isn't it just lame?
          1   8    9   10  joe       uh?
    =======  ==  ===  ===  ========  ===================
    
    */
    
    // to visualize simply our trees...
    print_r($thread1->getTree()->stringify());
    echo "\n---\n";
    print_r($thread2->getTree()->stringify());
    echo "\n";
    
    // Next time you want to access a particular tree, you have to know
    // his forest name and his tree id
    $tread1LoadedAgain=new AnimalsBaobab($conn,$forestName,$tread1->id);
}
