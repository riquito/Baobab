
/* ####################################### */
/* ####### MOVE SUBTREE REAL LOGIC #######*/
/* ####################################### */

/* If move_as_first_sibling is FALSE, move node_id_to_move after reference_node,
     else reference_node is the new father of node_id_to_move */

SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_UNSIGNED_SUBTRACTION';

DROP PROCEDURE IF EXISTS Baobab_GENERIC_MoveSubtree_real;
CREATE PROCEDURE Baobab_GENERIC_MoveSubtree_real(
        IN node_id_to_move INTEGER UNSIGNED,
        IN reference_node INTEGER UNSIGNED,
        IN move_as_first_sibling BOOLEAN,
        OUT error_code INTEGER
        )
LANGUAGE SQL
DETERMINISTIC

  main:BEGIN

    DECLARE s_lft INTEGER UNSIGNED;
    DECLARE s_rgt INTEGER UNSIGNED;
    DECLARE ref_lft INTEGER UNSIGNED;
    DECLARE ref_rgt INTEGER UNSIGNED;
    
    DECLARE source_node_tree INTEGER UNSIGNED;
    DECLARE ref_node_tree INTEGER UNSIGNED;
    
    DECLARE diff_when_inside_sourcetree BIGINT SIGNED;
    DECLARE diff_when_next_sourcetree BIGINT SIGNED;
    DECLARE ext_bound_1 INTEGER UNSIGNED;
    DECLARE ext_bound_2 INTEGER UNSIGNED;
    
    SET error_code=0;
    
    START TRANSACTION;

    /* select tree, left and right of the node to move */
    SELECT tree_id,lft, rgt
    INTO source_node_tree, s_lft, s_rgt
    FROM GENERIC
    WHERE id = node_id_to_move;
    
    /* select left and right of the reference node
        
        If moving as first sibling, ref_lft will become the new lft value of node_id_to_move,
         (and ref_rgt is unused), else we're saving left and right value of soon to be
         previous sibling
    
    */
    SELECT tree_id, IF(move_as_first_sibling,lft+1,lft), rgt
    INTO ref_node_tree, ref_lft, ref_rgt
    FROM GENERIC
    WHERE id = reference_node;
    
    
    IF move_as_first_sibling = TRUE THEN
        
        IF s_lft <= ref_lft AND s_rgt >= ref_rgt AND source_node_tree=ref_node_tree THEN
            /* cannot move a parent node inside his own subtree */
            BEGIN
                SELECT Baobab_getErrCode('CHILD_OF_YOURSELF_ERROR') INTO error_code;
                LEAVE main;
            END;
        ELSE
                
            IF s_lft > ref_lft THEN BEGIN
                SET diff_when_inside_sourcetree = -(s_lft-ref_lft);
                SET diff_when_next_sourcetree = s_rgt-s_lft+1;
                SET ext_bound_1 = ref_lft;
                SET ext_bound_2 = s_lft-1;
                
                END;
            ELSEIF s_lft = ref_lft THEN BEGIN
                /* we have been asked to move a node to his same position */
                LEAVE main;
                END;
            ELSE BEGIN
                SET diff_when_inside_sourcetree = ref_lft-s_rgt-1;
                SET diff_when_next_sourcetree = -(s_rgt-s_lft+1);
                SET ext_bound_1 = s_rgt+1;
                SET ext_bound_2 = ref_lft-1;
               
                END;
            END IF;
            
        END IF;
    ELSE    /* moving after an existing child */
        
        IF ref_lft = 1 THEN /* cannot move a node before or after root */
            BEGIN
                SELECT Baobab_getErrCode('ROOT_ERROR') INTO error_code;
                LEAVE main;
            END;
        ELSEIF s_lft < ref_lft AND s_rgt > ref_rgt AND source_node_tree=ref_node_tree THEN
            /* cannot move a parent node inside his own subtree */
            BEGIN
                SELECT Baobab_getErrCode('CHILD_OF_YOURSELF_ERROR') INTO error_code;
                LEAVE main;
            END;
        ELSE
            
            IF s_lft > ref_rgt THEN BEGIN
                SET diff_when_inside_sourcetree = -(s_lft-ref_rgt-1);
                SET diff_when_next_sourcetree = s_rgt-s_lft+1;
                SET ext_bound_1 = ref_rgt+1;
                SET ext_bound_2 = s_lft-1;
               
                END;
            ELSE BEGIN
                SET diff_when_inside_sourcetree = ref_rgt-s_rgt;
                SET diff_when_next_sourcetree = -(s_rgt-s_lft+1);
                SET ext_bound_1 = s_rgt+1;
                SET ext_bound_2 = ref_rgt;
               
                END;
            END IF;
            
        END IF;

    END IF;
    
    
    IF source_node_tree <> ref_node_tree THEN
        BEGIN
            CALL Baobab_GENERIC_MoveSubtree_Different_Trees(
                node_id_to_move,reference_node,move_as_first_sibling);
            LEAVE main;
        END;
    END IF;
    
    UPDATE GENERIC
    SET lft =
        lft + CASE
          WHEN lft BETWEEN s_lft AND s_rgt
          THEN diff_when_inside_sourcetree
          WHEN lft BETWEEN ext_bound_1 AND ext_bound_2
          THEN diff_when_next_sourcetree
          ELSE 0 END
        ,
        rgt =
        rgt + CASE
          
          WHEN rgt BETWEEN s_lft AND s_rgt
          THEN diff_when_inside_sourcetree
          WHEN rgt BETWEEN ext_bound_1 AND ext_bound_2
          THEN diff_when_next_sourcetree
          ELSE 0 END
    WHERE tree_id=source_node_tree;

    COMMIT;
    
  END;

SET sql_mode=@OLD_SQL_MODE;

UPDATE Baobab_Errors SET msg="1.3.0" WHERE code=1000;