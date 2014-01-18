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

/* ################################### */
/* ###### INSERT CHILD AT INDEX ###### */
/* ################################### */

/* Add a new child to parent 'parent_id' at index 'index'.
   index is the new child position, 0 will put the new node as first.
   index can be negative, where -1 will put the new node before the last one
 */

DROP PROCEDURE IF EXISTS Baobab_GENERIC_InsertChildAtIndex;
CREATE PROCEDURE Baobab_GENERIC_InsertChildAtIndex(
            IN parent_id INTEGER UNSIGNED,
            IN idx INTEGER,
            OUT new_id INTEGER UNSIGNED,
            OUT error_code INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC

  BEGIN
    
    DECLARE nth_child INTEGER UNSIGNED;
    DECLARE cur_tree_id INTEGER UNSIGNED;
    
    SET error_code=0;
    SET new_id=0;

    CALL Baobab_GENERIC_getNthChild(parent_id,idx,nth_child,error_code);
    
    IF NOT error_code THEN
        CALL Baobab_GENERIC_insertBefore(nth_child,new_id,error_code);
    ELSE IF idx = 0 AND error_code = (SELECT Baobab_getErrCode('INDEX_OUT_OF_RANGE')) THEN
        BEGIN
          SET error_code = 0;
          CALL Baobab_GENERIC_AppendChild((SELECT tree_id FROM GENERIC WHERE id = parent_id),
                                           parent_id,
                                           new_id,
                                           cur_tree_id);
        END;
      END IF;
    END IF;
    
  END;

UPDATE Baobab_Errors SET msg="1.2.0" WHERE code=1000;