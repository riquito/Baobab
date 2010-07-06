<?php
$data=array(
  "delete_subtree"=>array(
    
    array(
        // drop tree without closing gaps
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
        "params"=>array(3,FALSE),
        "to"=>array(
            array(1,1,20),
                array(2,2,7),
                    array(5,3,4),
                    array(6,5,6),
                array(4,14,19),
                    array(9,15,16),
                    array(10,17,18)
        )
    )
  )
);

?>