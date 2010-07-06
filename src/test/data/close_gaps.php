<?php
$data=array(
  "close_gaps"=>array(
    
    array(
        // drop tree without closing gaps
        "from"=>array(
            array(1,1,20),
                array(2,2,7),
                    array(5,3,4),
                    array(6,5,6),
                array(4,14,19),
                    array(9,15,16),
                    array(10,17,18)
            
        ),
        "params"=>array(),
        "to"=>array(
            array(1,1,14),
                array(2,2,7),
                    array(5,3,4),
                    array(6,5,6),
                array(4,8,13),
                    array(9,9,10),
                    array(10,11,12)
        )
    )
  )
);

?>