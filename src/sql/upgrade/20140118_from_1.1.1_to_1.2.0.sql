/* ##################################### */
/* ####### MOVE SUBTREE AT INDEX ####### */
/* ##################################### */

DROP PROCEDURE IF EXISTS Baobab_GENERIC_MoveSubtreeAtIndex;
CREATE PROCEDURE Baobab_GENERIC_MoveSubtreeAtIndex(
        IN node_id_to_move INTEGER UNSIGNED,
        IN parent_id INTEGER UNSIGNED,
        IN idx INTEGER,
        OUT error_code INTEGER)
LANGUAGE SQL
DETERMINISTIC

  main:BEGIN

    DECLARE nth_child INTEGER UNSIGNED;
    DECLARE num_children INTEGER;
    
    SET error_code=0;

    SELECT COUNT(*)
    INTO num_children
    FROM GENERIC_AdjTree WHERE parent = parent_id;
    
    SET idx = IF(idx<0,num_children+idx,idx);
    
    IF idx = 0 THEN /* moving as first child, special case */
        CALL Baobab_GENERIC_MoveSubtree_real(node_id_to_move,parent_id,TRUE,error_code);
    ELSE
      BEGIN
        /* search the node before idx, and we wil move our node after that */
        CALL Baobab_GENERIC_getNthChild(parent_id,idx-1,nth_child,error_code);

        IF NOT error_code THEN
            CALL Baobab_GENERIC_MoveSubtree_real(node_id_to_move,nth_child,FALSE,error_code);
        END IF;
      END;
    END IF;

  END; 

UPDATE Baobab_Errors SET msg="1.2.0" WHERE code=1000;