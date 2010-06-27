<?php
$data=array(
  "appendChild"=>array(
    
    array(
        // append an element at root in empty tree
        "from"=>array(
        ),
        "params"=>array(),
        "to"=>array(
            array(1,1,2)
        )
    ),
    array(
        // append an element as unique child of root
        "from"=>array(
            array(1,1,2)
        ),
        "params"=>array(1),
        "to"=>array(
            array(1,1,4),
                array(2,2,3)
        )
    ),
    array(
        // append an element when parent has one child
        "from"=>array(
            array(1,1,4),
                array(2,2,3)
            
        ),
        "params"=>array(1),
        "to"=>array(
            array(1,1,6),
                array(2,2,3),
                array(3,4,5)
        )
    ),
    array(
        // append an element when parent has two children
        "from"=>array(
            array(1,1,6),
                array(2,2,3),
                array(3,4,5)
        ),
        "params"=>array(1),
        "to"=>array(
            array(1,1,8),
                array(2,2,3),
                array(3,4,5),
                array(4,6,7)
        )
    ),
    
    array(
        // insert an element at root position
        "from"=>array(
            array(1,1,8),
                array(2,2,3),
                array(3,4,5),
                array(4,6,7)
        ),
        "params"=>array(),
        "to"=>array(
            array(5,1,10),
                array(1,2,9),
                    array(2,3,4),
                    array(3,5,6),
                    array(4,7,8)
        )
    ),
    array(
        // append an element as leaf at most left side
        // (parent and uncles have not children)
        "from"=>array(
            array(1,1,8),
                array(2,2,3),
                array(3,4,5),
                array(4,6,7)
          ),
        "params"=>array(2),
        "to"=>array(
            array(1,1,10),
                array(2,2,5),
                    array(5,3,4),
                array(3,6,7),
                array(4,8,9)
        )
    ),
    array(
        // append an element as leaf at center
        // (parent and uncles have not children)
        "from"=>array(
            array(1,1,8),
                array(2,2,3),
                array(3,4,5),
                array(4,6,7)
        ),
        "params"=>array(3),
        "to"=>array(
            array(1,1,10),
                array(2,2,3),
                array(3,4,7),
                    array(5,5,6),
                array(4,8,9)
        )
    ),
    array(
        // append an element as leaf at most right side
        // (parent and uncles have not children)
        "from"=>array(
            array(1,1,8),
                array(2,2,3),
                array(3,4,5),
                array(4,6,7)
        ),
        "params"=>array(4),
        "to"=>array(
            array(1,1,10),
                array(2,2,3),
                array(3,4,5),
                array(4,6,9),
                    array(5,7,8)
        )
    ),
    
    
    array(
        // append an element as leaf at most left side (not on leaf)
        // (parent and uncles have children)
        "from"=>array(
            array(1,1,20),
                array(2,2,7),
                    array(5,3,4),
                    array(6,5,6),
                array(3,8,13),
                    array(7,9,10),
                    array(8,11,12),
                array(4,14,19),
                    array(9,15,16),
                    array(10,17,18)
        ),
        "params"=>array(2),
        "to"=>array(
            array(1,1,22),
                array(2,2,9),
                    array(5,3,4),
                    array(6,5,6),
                    array(11,7,8),
                array(3,10,15),
                    array(7,11,12),
                    array(8,13,14),
                array(4,16,21),
                    array(9,17,18),
                    array(10,19,20)
        )
    ),
    array(
        // append an element as leaf at center (not on leaf)
        // (parent and uncles have children)
        "from"=>array(
            array(1,1,20),
                array(2,2,7),
                    array(5,3,4),
                    array(6,5,6),
                array(3,8,13),
                    array(7,9,10),
                    array(8,11,12),
                array(4,14,19),
                    array(9,15,16),
                    array(10,17,18)
        ),
        "params"=>array(3),
        "to"=>array(
            array(1,1,22),
                array(2,2,7),
                    array(5,3,4),
                    array(6,5,6),
                array(3,8,15),
                    array(7,9,10),
                    array(8,11,12),
                    array(11,13,14),
                array(4,16,21),
                    array(9,17,18),
                    array(10,19,20)
        )
    ),
    array(
        // append an element as leaf at most right side (not on leaf)
        // (parent and uncles have children)
        "from"=>array(
            array(1,1,20),
                array(2,2,7),
                    array(5,3,4),
                    array(6,5,6),
                array(3,8,13),
                    array(7,9,10),
                    array(8,11,12),
                array(4,14,19),
                    array(9,15,16),
                    array(10,17,18)
        ),
        "params"=>array(4),
        "to"=>array(
            array(1,1,22),
                array(2,2,7),
                    array(5,3,4),
                    array(6,5,6),
                array(3,8,13),
                    array(7,9,10),
                    array(8,11,12),
                array(4,14,21),
                    array(9,15,16),
                    array(10,17,18),
                    array(11,19,20)
        )
    ),
  )
);

?>