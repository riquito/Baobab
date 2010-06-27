<?php
$data=array(
  "insertChildAtIndex"=>array(
    
    array(
        // insert an element before the only and last leaf
        "from"=>array(
            array(1,1,4),
                array(2,2,3)
            
        ),
        "params"=>array(1,0),
        "to"=>array(
            array(1,1,6),
                array(3,2,3),
                array(2,4,5)
        )
    ),
    
    array(
        // insert an element as leaf before the most left side node
        // (future siblings have not children)
        "from"=>array(
            array(1,1,8),
                array(2,2,3),
                array(3,4,5),
                array(4,6,7)
          ),
        "params"=>array(1,0),
        "to"=>array(
            array(1,1,10),
                array(5,2,3),
                array(2,4,5),
                array(3,6,7),
                array(4,8,9)
        )
    ),
    array(
        // insert an element as leaf before an inner node
        // (future siblings have not children)
        "from"=>array(
            array(1,1,8),
                array(2,2,3),
                array(3,4,5),
                array(4,6,7)
        ),
        "params"=>array(1,1),
        "to"=>array(
            array(1,1,10),
                array(2,2,3),
                array(5,4,5),
                array(3,6,7),
                array(4,8,9)
        )
    ),array(
        // insert an element as leaf after an inner node
        // (future siblings have not children)
        "from"=>array(
            array(1,1,8),
                array(2,2,3),
                array(3,4,5),
                array(4,6,7)
        ),
        "params"=>array(1,2),
        "to"=>array(
            array(1,1,10),
                array(2,2,3),
                array(3,4,5),
                array(5,6,7),
                array(4,8,9)
        )
    ),
    
    
    array(
        // insert an element before the most left side node
        // (future siblings have children)
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
        "params"=>array(1,0),
        "to"=>array(
            array(1,1,22),
                array(11,2,3),
                array(2,4,9),
                    array(5,5,6),
                    array(6,7,8),
                array(3,10,15),
                    array(7,11,12),
                    array(8,13,14),
                array(4,16,21),
                    array(9,17,18),
                    array(10,19,20)
        )
    ),
    array(
        // insert an element before an inner node
        // (future siblings have children)
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
        "params"=>array(1,1),
        "to"=>array(
            array(1,1,22),
                array(2,2,7),
                    array(5,3,4),
                    array(6,5,6),
                array(11,8,9),
                array(3,10,15),
                    array(7,11,12),
                    array(8,13,14),
                array(4,16,21),
                    array(9,17,18),
                    array(10,19,20)
        )
    ),
    array(
        // insert an element after an inner node
        // (future siblings have children)
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
        "params"=>array(1,2),
        "to"=>array(
            array(1,1,22),
                array(2,2,7),
                    array(5,3,4),
                    array(6,5,6),
                array(3,8,13),
                    array(7,9,10),
                    array(8,11,12),
                array(11,14,15),
                array(4,16,21),
                    array(9,17,18),
                    array(10,19,20)
        )
    ),
  )
);

?>