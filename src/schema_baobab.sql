/**
 * Baobab (an implementation of Nested Set Model)
 * 
 * Copyright 2010 Riccardo Attilio Galli <riccardo@sideralis.org> [http://www.sideralis.org]
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *    http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */ 



/* ############################### */
/* ###### TABLES AND VIEWS ####### */
/* ############################### */

CREATE TABLE IF NOT EXISTS Baobab_GENERIC (
    id INTEGER  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    lft INTEGER NOT NULL  CHECK (lft > 0),
    rgt INTEGER NOT NULL CHECK (rgt > 1),
    CONSTRAINT order_okay CHECK (lft < rgt)
) ENGINE INNODB;


CREATE VIEW Baobab_AdjTree_GENERIC (parent, child, lft)
    AS
    SELECT B.id, E.id, E.lft
    FROM Baobab_GENERIC AS E
         LEFT OUTER JOIN Baobab_GENERIC AS B
           ON B.lft = ( SELECT MAX(lft)
                        FROM Baobab_GENERIC AS S
                        WHERE E.lft > S.lft
                          AND E.lft < S.rgt)
    ORDER BY lft ASC;


/* ########################## */
/* ######## DROP TREE ####### */
/* ########################## */

CREATE PROCEDURE Baobab_DropTree_GENERIC (IN node INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC
MODIFIES SQL DATA

  BEGIN

    DECLARE drop_id INTEGER UNSIGNED;
    DECLARE drop_lft INTEGER UNSIGNED;
    DECLARE drop_rgt INTEGER UNSIGNED;

    /*
    declare exit handler for not found rollback;
    declare exit handler for sqlexception rollback;
    declare exit handler for sqlwarning rollback;
    */

    /* save the dropped subtree data with a singleton SELECT */

    START TRANSACTION;

    /* save the dropped subtree data with a singleton SELECT */

    SELECT id, lft, rgt
    INTO drop_id, drop_lft, drop_rgt
    FROM Baobab_GENERIC
    WHERE id = node;

    /* subtree deletion is easy */

    DELETE FROM Baobab_GENERIC
    WHERE lft BETWEEN drop_lft and drop_rgt;

    /* close up the gap left by the subtree */

    UPDATE Baobab_GENERIC
    SET lft = CASE WHEN lft > drop_lft
            THEN lft - (drop_rgt - drop_lft + 1)
            ELSE lft END,
      rgt = CASE WHEN rgt > drop_lft
            THEN rgt - (drop_rgt - drop_lft + 1)
            ELSE rgt END
    WHERE lft > drop_lft OR rgt > drop_lft;

    COMMIT;

  END;

/* ########################## */
/* ###### APPEND CHILD ###### */
/* ########################## */

/* Add a new child to a parent as last sibling
   If parent_id is 0, insert a new root node, moving the
     previous root (if any) as his child
*/
CREATE PROCEDURE Baobab_AppendChild_GENERIC(
            IN parent_id INTEGER UNSIGNED,
            OUT new_id INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC

  BEGIN

    DECLARE num INTEGER UNSIGNED;

    START TRANSACTION;

    IF parent_id = 0 THEN /* inserting a new root node*/

        UPDATE Baobab_GENERIC
        SET lft = lft+1, rgt = rgt+1 ;

        SET num = IFNULL((SELECT MAX(rgt)+1 FROM Baobab_GENERIC),2);

        INSERT INTO Baobab_GENERIC(id, lft, rgt)
        VALUES (NULL, 1, num);

    ELSE

        SET num = (SELECT rgt
                      FROM Baobab_GENERIC
                      WHERE id = parent_id
                     );

        UPDATE Baobab_GENERIC
        SET lft = CASE WHEN lft > num
                     THEN lft + 2
                     ELSE lft END,
            rgt = CASE WHEN rgt >= num
                     THEN rgt + 2
                     ELSE rgt END
        WHERE rgt >= num;

        INSERT INTO Baobab_GENERIC(id, lft, rgt)
        VALUES (NULL, num, (num + 1));

    END IF;

    SELECT LAST_INSERT_ID() INTO new_id;

    COMMIT;

  END;

/* ############################### */
/* ###### INSERT NODE AFTER ###### */
/* ############################### */

/*
 * If sibling_id is root, return 0 as error value;
 *
 */

CREATE PROCEDURE Baobab_InsertNodeAfter_GENERIC(
            IN sibling_id INTEGER UNSIGNED,
            OUT new_id INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC

  BEGIN
    
    IF (SELECT lft /* the root has no siblings */
        FROM Baobab_GENERIC
        WHERE id = sibling_id) = 1
    THEN
        SELECT 0 INTO new_id;
    ELSE
        BEGIN

          DECLARE lft_sibling INTEGER UNSIGNED;

          START TRANSACTION;

          SET lft_sibling = (SELECT rgt
                             FROM Baobab_GENERIC
                             WHERE id = sibling_id);

          UPDATE Baobab_GENERIC
          SET lft = CASE WHEN lft < lft_sibling
                         THEN lft
                         ELSE lft + 2 END,
              rgt = CASE WHEN rgt < lft_sibling
                         THEN rgt
                         ELSE rgt + 2 END
          WHERE rgt > lft_sibling;

          INSERT INTO Baobab_GENERIC(id,lft,rgt)
          VALUES (NULL, (lft_sibling + 1),(lft_sibling + 2));

          SELECT LAST_INSERT_ID() INTO new_id;

          COMMIT;

        END;
    END IF;

  END;


/* ################################ */
/* ###### INSERT NODE BEFORE ###### */
/* ################################ */

/*
 * If sibling_id is root, return 0 as error value;
 *
 */

CREATE PROCEDURE Baobab_InsertNodeBefore_GENERIC(
            IN sibling_id INTEGER UNSIGNED,
            OUT new_id INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC
  BEGIN

    IF (SELECT lft /* the root has no siblings */
        FROM Baobab_GENERIC
        WHERE id = sibling_id) = 1
    THEN
        SELECT 0 INTO new_id;
    ELSE
      BEGIN

        DECLARE rgt_sibling INTEGER UNSIGNED;

        START TRANSACTION;

        SET rgt_sibling = (SELECT lft
                         FROM Baobab_GENERIC
                         WHERE id = sibling_id);

        UPDATE IGNORE Baobab_GENERIC
        SET lft = CASE WHEN lft < rgt_sibling
                     THEN lft
                     ELSE lft + 2 END,
            rgt = CASE WHEN rgt < rgt_sibling
                     THEN rgt
                     ELSE rgt + 2 END
        WHERE rgt >= rgt_sibling
        ORDER BY lft DESC; /* order by is meant to avoid uniqueness violation on update */

        INSERT INTO Baobab_GENERIC(id,lft,rgt)
        VALUES (NULL, rgt_sibling, rgt_sibling + 1);

        SELECT LAST_INSERT_ID() INTO new_id;

        COMMIT;

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

CREATE PROCEDURE Baobab_InsertChildAtIndex_GENERIC(
            IN parent_id INTEGER UNSIGNED,
            IN idx INTEGER,
            OUT new_id INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC

  BEGIN
    
    DECLARE nth_child INTEGER UNSIGNED;

    CALL Baobab_getNthChild_GENERIC(parent_id,idx,nth_child);
    
    IF nth_child = 0 THEN
        SET new_id = 0;
    ELSE
        CALL Baobab_InsertNodeBefore_GENERIC(nth_child,new_id);
    END IF;

  END;

/* ########################### */
/* ###### GET NTH CHILD ###### */
/* ########################### */

CREATE PROCEDURE Baobab_getNthChild_GENERIC(
            IN parent_id INTEGER UNSIGNED,
            IN idx INTEGER,
            OUT nth_child INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC

  BEGIN

    DECLARE num_children INTEGER;

    SELECT COUNT(*)
    INTO num_children
    FROM  Baobab_AdjTree_GENERIC WHERE parent = parent_id;

    IF num_children = 0 OR IF(idx<0,(-idx)-1,idx) >= num_children THEN
        /* idx is out of range */
        SET nth_child = 0;
    ELSE

        SELECT child
        INTO nth_child
        FROM Baobab_AdjTree_GENERIC as t1
        WHERE (SELECT count(*) FROM Baobab_AdjTree_GENERIC as t2
               WHERE parent = parent_id AND t2.lft<=t1.lft
              )
              = (CASE
                  WHEN idx >= 0
                  THEN idx+1
                  ELSE num_children+1+idx
                 END
                )
        LIMIT 1;
    
    END IF;

  END;



/* ###################################### */
/* ###### MOVE SUBTREE BEFORE NODE ###### */
/* ###################################### */

CREATE PROCEDURE Baobab_MoveSubtreeBefore_GENERIC(
        IN node_id_to_move INTEGER UNSIGNED,
        IN reference_node INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC

  BEGIN
  
    DECLARE node_revised INTEGER UNSIGNED;
    DECLARE is_first_child BOOLEAN;

    SET node_revised = IFNULL(
       /* if reference_node is the first child, get his parent */
        ( SELECT id FROM Baobab_GENERIC
          WHERE lft = -1+(SELECT lft FROM Baobab_GENERIC WHERE id = reference_node)
        )
        , 
        NULL
    );

    IF ISNULL(node_revised) THEN    /* if reference_node is not the first child, get the previous sibling */
        SET node_revised=(SELECT id FROM Baobab_GENERIC
                          WHERE rgt = -1 + (SELECT lft FROM Baobab_GENERIC
                                            WHERE id = reference_node )
                         );
        SET is_first_child = FALSE;
    ELSE
        SET is_first_child = TRUE;
    END IF;

     CALL Baobab_MoveSubtree_real_GENERIC(
        node_id_to_move, node_revised , is_first_child
     );

  END;



/* ##################################### */
/* ###### MOVE SUBTREE AFTER NODE ###### */
/* ##################################### */

CREATE PROCEDURE Baobab_MoveSubtreeAfter_GENERIC(
        IN node_id_to_move INTEGER UNSIGNED,
        IN reference_node INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC

  BEGIN

    CALL Baobab_MoveSubtree_real_GENERIC(
        node_id_to_move,reference_node,FALSE
    );

  END;


/*

DECLARE CONTINUE HANDLER
  FOR SQLSTATE '23000' SET @x2 = 1;

*/

/* ##################################### */
/* ####### MOVE SUBTREE AT INDEX ####### */
/* ##################################### */

/*
 *
 * error_code != 0 means an error occurred
 * error_code == 1 it's an index out of bounds error
 **/

CREATE PROCEDURE Baobab_MoveSubtreeAtIndex_GENERIC(
        IN node_id_to_move INTEGER UNSIGNED,
        IN parent_id INTEGER UNSIGNED,
        IN idx INTEGER,
        OUT error_code INTEGER)
LANGUAGE SQL
DETERMINISTIC

  BEGIN

    DECLARE nth_child INTEGER UNSIGNED;

    SET error_code = 0;

    IF idx = 0 THEN /* moving as first child, special case */
        CALL Baobab_MoveSubtree_real_GENERIC(node_id_to_move,parent_id,TRUE);
    ELSE
        CALL Baobab_getNthChild_GENERIC(parent_id,idx-1,nth_child);

        IF nth_child = 0 THEN SET error_code = 1;
        ELSE
            CALL Baobab_MoveSubtree_real_GENERIC(node_id_to_move,nth_child,FALSE);
        END IF;
    END IF;

  END; 

/* ####################################### */
/* ####### MOVE SUBTREE REAL LOGIC #######*/
/* ####################################### */

/* move node_id_to_move AFTER reference_node, unless move_as_first_sibling is TRUE */

CREATE PROCEDURE Baobab_MoveSubtree_real_GENERIC(
        IN node_id_to_move INTEGER UNSIGNED,
        IN reference_node INTEGER UNSIGNED,
        IN move_as_first_sibling BOOLEAN
        )
LANGUAGE SQL
DETERMINISTIC

  BEGIN

    DECLARE s_lft INTEGER UNSIGNED;
    DECLARE s_rgt INTEGER UNSIGNED;
    DECLARE ref_lft INTEGER UNSIGNED;
    DECLARE ref_rgt INTEGER UNSIGNED;

    START TRANSACTION;

    /* select lft and right of the node to move */
    SELECT lft, rgt      
    INTO s_lft, s_rgt
    FROM Baobab_GENERIC
    WHERE id = node_id_to_move;

    
    IF move_as_first_sibling = TRUE THEN

        /* ref_lft will become the new lft value of node_id_to_move */
        SELECT lft+1 INTO ref_lft
        FROM Baobab_GENERIC
        WHERE id = reference_node;

        IF s_lft > ref_lft THEN
            
            UPDATE Baobab_GENERIC
            SET lft =
                lft + CASE

                  WHEN lft BETWEEN s_lft AND s_rgt
                  THEN  -(s_lft-ref_lft)
                  WHEN lft BETWEEN ref_lft AND s_lft-1
                  THEN s_rgt-s_lft+1
                  ELSE 0 END
                ,
                rgt =
                rgt + CASE

                  WHEN rgt BETWEEN s_lft AND s_rgt
                  THEN -(s_lft-ref_lft)
                  WHEN rgt BETWEEN ref_lft AND s_lft-1
                  THEN s_rgt-s_lft+1
                  ELSE 0 END;


        ELSEIF s_lft < ref_lft THEN

            UPDATE Baobab_GENERIC
            SET lft =
                lft + CASE

                  WHEN lft BETWEEN s_lft AND s_rgt
                  THEN ref_lft-s_rgt-1
                  WHEN lft BETWEEN s_rgt+1 AND ref_lft-1
                  THEN -(s_rgt-s_lft+1)
                  ELSE 0 END
                ,
                rgt =
                rgt + CASE

                  WHEN rgt BETWEEN s_lft AND s_rgt
                  THEN ref_lft-s_rgt-1
                  WHEN rgt BETWEEN s_rgt+1 AND ref_lft-1
                  THEN -(s_rgt-s_lft+1)
                  ELSE 0 END;


        END IF;

    ELSE    /* moving after an existing child */

        /* select lft and right of soon to be previous sibling */
        SELECT lft, rgt
        INTO ref_lft, ref_rgt
        FROM Baobab_GENERIC
        WHERE id = reference_node;

        IF s_lft > ref_lft AND s_rgt < ref_rgt THEN
            /* we're moving a subtree as next sibling of an ancestor*/

            UPDATE Baobab_GENERIC
            SET lft =
                lft + CASE

                  WHEN lft BETWEEN s_lft AND s_rgt
                  THEN ref_rgt-s_rgt
                  WHEN lft BETWEEN s_rgt+1 AND ref_rgt-1 /*lft BETWEEN ref_rgt+1 AND s_lft-1*/
                  THEN -(s_rgt-s_lft+1)
                  ELSE 0 END
                ,
                rgt =
                rgt + CASE

                  WHEN rgt BETWEEN s_lft AND s_rgt
                  THEN ref_rgt-s_rgt
                  WHEN rgt BETWEEN s_rgt+1 AND ref_rgt
                  THEN -(s_rgt-s_lft+1)
                  ELSE 0 END;

        ELSEIF s_lft > ref_lft THEN

            UPDATE Baobab_GENERIC
            SET lft =
                lft + CASE

                  WHEN lft BETWEEN s_lft AND s_rgt
                  THEN  -(s_lft-ref_rgt-1)     /*IF(s_lft>ref_rgt,-(s_lft-ref_rgt-1),ref_rgt-s_lft+1)*/ /*  -(s_lft-ref_rgt-1) */
                  WHEN lft BETWEEN ref_rgt+1 AND s_lft-1 /*lft BETWEEN ref_rgt+1 AND s_lft-1*/
                  THEN s_rgt-s_lft+1 /*(GREATEST(s_rgt,s_lft)-LEAST(s_rgt,s_lft)+1)*/
                  ELSE 0 END
                ,
                rgt =
                rgt + CASE

                  WHEN rgt BETWEEN s_lft AND s_rgt
                  THEN -(s_lft-ref_rgt-1)
                  WHEN rgt BETWEEN ref_rgt+1 AND s_lft-1
                  THEN s_rgt-s_lft+1
                  ELSE 0 END;


        ELSEIF s_lft < ref_lft THEN

            UPDATE Baobab_GENERIC
            SET lft =
                lft + CASE

                  WHEN lft BETWEEN s_lft AND s_rgt
                  THEN ref_rgt-s_rgt
                  WHEN lft BETWEEN s_rgt+1 AND ref_rgt
                  THEN -(s_rgt-s_lft+1)
                  ELSE 0 END
                ,
                rgt =
                rgt + CASE

                  WHEN rgt BETWEEN s_lft AND s_rgt
                  THEN ref_rgt-s_rgt
                  WHEN rgt BETWEEN s_rgt+1 AND ref_rgt
                  THEN -(s_rgt-s_lft+1)
                  ELSE 0 END;
                  

        END IF;


    END IF;

    COMMIT;
    
  END

/*

CREATE PROCEDURE Baobab_Destroy_GENERIC()
LANGUAGE SQL
DETERMINISTIC

  BEGIN

    DROP PROCEDURE IF EXISTS Baobab_getNthChild_GENERIC;
    DROP PROCEDURE IF EXISTS Baobab_MoveSubtreeAfterXXX_GENERIC;
    DROP PROCEDURE IF EXISTS Baobab_MoveSubtree_real_GENERIC;
    DROP PROCEDURE IF EXISTS Baobab_MoveSubtreeAtIndex_GENERIC;
    DROP PROCEDURE IF EXISTS Baobab_MoveSubtreeBefore_GENERIC;
    DROP PROCEDURE IF EXISTS Baobab_MoveSubtreeAfter_GENERIC;
    DROP PROCEDURE IF EXISTS Baobab_InsertChildAtIndex_GENERIC;
    DROP PROCEDURE IF EXISTS Baobab_InsertNodeBefore_GENERIC;
    DROP PROCEDURE IF EXISTS Baobab_InsertNodeAfter_GENERIC;
    DROP PROCEDURE IF EXISTS Baobab_AppendChild_GENERIC;
    DROP PROCEDURE IF EXISTS Baobab_DropTree_GENERIC;
    DROP VIEW IF EXISTS Baobab_AdjTree_GENERIC;
    DROP TABLE IF EXISTS Baobab_GENERIC;

  END

*/