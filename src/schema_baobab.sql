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


/* ########################### */
/* ###### ERRORS CONTROL ##### */
/* ########################### */

CREATE TABLE IF NOT EXISTS Baobab_Errors_GENERIC (
    code   INTEGER UNSIGNED NOT NULL PRIMARY KEY,
    name   VARCHAR(50)      NOT NULL,
    msg    TINYTEXT         NOT NULL,
    CONSTRAINT unique_codename UNIQUE (name)
) ENGINE INNODB;

INSERT INTO Baobab_Errors_GENERIC(code,name,msg)
VALUES
  (1100,'ROOT_ERROR','Cannot add or move a node next to root'),
  (1200,'CHILD_OF_YOURSELF_ERROR','Cannot move a node inside his own subtree'),
  (1300,'INDEX_OUT_OF_RANGE','The index is out of range'),
  (1400,'NODE_DOES_NOT_EXIST',"Node doesn't exist");

CREATE FUNCTION Baobab_getErrCode_GENERIC(x TINYTEXT) RETURNS INT
DETERMINISTIC
    RETURN (SELECT code from Baobab_Errors_GENERIC WHERE name=x);

/* ########################## */
/* ######## DROP TREE ####### */
/* ########################## */

CREATE PROCEDURE Baobab_DropTree_GENERIC (
                    IN node INTEGER UNSIGNED,
                    IN update_numbers INTEGER)
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
    
    IF update_numbers = 1 THEN
        /* close up the gap left by the subtree */
        
        UPDATE Baobab_GENERIC
        SET lft = CASE WHEN lft > drop_lft
                THEN lft - (drop_rgt - drop_lft + 1)
                ELSE lft END,
          rgt = CASE WHEN rgt > drop_lft
                THEN rgt - (drop_rgt - drop_lft + 1)
                ELSE rgt END
        WHERE lft > drop_lft OR rgt > drop_lft;
        
    END IF;

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


CREATE PROCEDURE Baobab_InsertNodeAfter_GENERIC(
            IN sibling_id INTEGER UNSIGNED,
            OUT new_id INTEGER UNSIGNED,
            OUT error_code INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC

  main:BEGIN
    
    IF 1 = (SELECT lft FROM Baobab_GENERIC WHERE id = sibling_id) THEN
        BEGIN
            SELECT Baobab_getErrCode_GENERIC('ROOT_ERROR') INTO error_code;
            LEAVE main;
        END;
    ELSE
        BEGIN

          DECLARE lft_sibling INTEGER UNSIGNED;

          START TRANSACTION;

          SET lft_sibling = (SELECT rgt
                             FROM Baobab_GENERIC
                             WHERE id = sibling_id);
          
          IF ISNULL(lft_sibling) THEN
              BEGIN
                SELECT Baobab_getErrCode_GENERIC('NODE_DOES_NOT_EXIST') INTO error_code;
                LEAVE main;
              END;
          END IF;

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


CREATE PROCEDURE Baobab_InsertNodeBefore_GENERIC(
            IN sibling_id INTEGER UNSIGNED,
            OUT new_id INTEGER UNSIGNED,
            OUT error_code INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC
  main:BEGIN

    IF 1 = (SELECT lft FROM Baobab_GENERIC WHERE id = sibling_id) THEN
        BEGIN
            SELECT Baobab_getErrCode_GENERIC('ROOT_ERROR') INTO error_code;
            LEAVE main;
        END;
    ELSE
      BEGIN

        DECLARE rgt_sibling INTEGER UNSIGNED;

        START TRANSACTION;

        SET rgt_sibling = (SELECT lft
                         FROM Baobab_GENERIC
                         WHERE id = sibling_id);
        
        IF ISNULL(rgt_sibling) THEN
            BEGIN
                SELECT Baobab_getErrCode_GENERIC('NODE_DOES_NOT_EXIST') INTO error_code;
                LEAVE main;
            END;
        END IF;

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
            OUT new_id INTEGER UNSIGNED,
            OUT error_code INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC

  BEGIN
    
    DECLARE nth_child INTEGER UNSIGNED;
    
    SET error_code=0;
    SET new_id=0;

    CALL Baobab_getNthChild_GENERIC(parent_id,idx,nth_child,error_code);
    
    IF NOT error_code THEN
        CALL Baobab_InsertNodeBefore_GENERIC(nth_child,new_id,error_code);
    END IF;

  END;

/* ########################### */
/* ###### GET NTH CHILD ###### */
/* ########################### */

CREATE PROCEDURE Baobab_getNthChild_GENERIC(
            IN parent_id INTEGER UNSIGNED,
            IN idx INTEGER,
            OUT nth_child INTEGER UNSIGNED,
            OUT error_code INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC

  main:BEGIN

    DECLARE num_children INTEGER;
    
    SET error_code=0;

    SELECT COUNT(*)
    INTO num_children
    FROM Baobab_AdjTree_GENERIC WHERE parent = parent_id;

    IF num_children = 0 OR IF(idx<0,(-idx)-1,idx) >= num_children THEN
        /* idx is out of range */
        BEGIN
            SELECT Baobab_getErrCode_GENERIC('INDEX_OUT_OF_RANGE') INTO error_code;
            LEAVE main;
        END;
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
        IN reference_node INTEGER UNSIGNED,
        OUT error_code INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC

  main:BEGIN
  
    DECLARE node_revised INTEGER UNSIGNED;
    DECLARE is_first_child BOOLEAN;
    DECLARE ref_left INTEGER UNSIGNED;
    
    SET error_code=0; /* 0 means no error */
    SET is_first_child = TRUE;
    
    SET ref_left=(SELECT lft FROM Baobab_GENERIC WHERE id = reference_node);
    
    IF ref_left = 1 THEN
        BEGIN
            /* cannot move a parent node before or after root */
            SELECT Baobab_getErrCode_GENERIC('ROOT_ERROR') INTO error_code;
            LEAVE main;
        END;
    END IF;
    
    /* if node_id_to_move is the first child of his parent, set node_revised
       to the parent id, else set node_revised to NULL */
    SET node_revised = ( SELECT id FROM Baobab_GENERIC WHERE lft = -1+ ref_left);
    
    
    IF ISNULL(node_revised) THEN    /* if node_revised is NULL we must find the previous sibling */
      BEGIN
        SET node_revised= (SELECT id FROM Baobab_GENERIC
                           WHERE rgt = -1 + ref_left );
        SET is_first_child = FALSE;
      END;
    END IF;

    CALL Baobab_MoveSubtree_real_GENERIC(
        node_id_to_move, node_revised , is_first_child,error_code
    );

  END;



/* ##################################### */
/* ###### MOVE SUBTREE AFTER NODE ###### */
/* ##################################### */

CREATE PROCEDURE Baobab_MoveSubtreeAfter_GENERIC(
        IN node_id_to_move INTEGER UNSIGNED,
        IN reference_node INTEGER UNSIGNED,
        OUT error_code INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC

  BEGIN
    
    SELECT 0 INTO error_code; /* 0 means no error */
    
    CALL Baobab_MoveSubtree_real_GENERIC(
        node_id_to_move,reference_node,FALSE,error_code
    );

  END;



/* ##################################### */
/* ####### MOVE SUBTREE AT INDEX ####### */
/* ##################################### */

CREATE PROCEDURE Baobab_MoveSubtreeAtIndex_GENERIC(
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
    FROM Baobab_AdjTree_GENERIC WHERE parent = parent_id;
    
    IF num_children = 0 THEN
      BEGIN
        SELECT Baobab_getErrCode_GENERIC('INDEX_OUT_OF_RANGE') INTO error_code;
        LEAVE main;
      END;
    END IF;
    
    SET idx = IF(idx<0,num_children+idx,idx);
    
    IF idx = 0 THEN /* moving as first child, special case */
        CALL Baobab_MoveSubtree_real_GENERIC(node_id_to_move,parent_id,TRUE,error_code);
    ELSE
      BEGIN
        /* search the node before idx, and we wil move our node after that */
        CALL Baobab_getNthChild_GENERIC(parent_id,idx-1,nth_child,error_code);

        IF NOT error_code THEN
            CALL Baobab_MoveSubtree_real_GENERIC(node_id_to_move,nth_child,FALSE,error_code);
        END IF;
      END;
    END IF;

  END; 

/* ####################################### */
/* ####### MOVE SUBTREE REAL LOGIC #######*/
/* ####################################### */

/* If move_as_first_sibling is FALSE, move node_id_to_move after reference_node,
     else reference_node is the new father of node_id_to_move */

CREATE PROCEDURE Baobab_MoveSubtree_real_GENERIC(
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
    
    SET error_code=0;
    
    START TRANSACTION;

    /* select left and right of the node to move */
    SELECT lft, rgt
    INTO s_lft, s_rgt
    FROM Baobab_GENERIC
    WHERE id = node_id_to_move;
    
    /* select left and right of the reference node
        
       If moving as first, sibling, ref_lft will become the new lft value of node_id_to_move,
         (and ref_rgt is unused), else we're saving left and right value of soon to be
         previous sibling
    
    */
    SELECT IF(move_as_first_sibling,lft+1,lft), rgt
    INTO ref_lft, ref_rgt
    FROM Baobab_GENERIC
    WHERE id = reference_node;
    
    IF move_as_first_sibling = TRUE THEN
        
        IF s_lft <= ref_lft AND s_rgt >= ref_rgt THEN
            /* cannot move a parent node inside his own subtree */
            BEGIN
                SELECT Baobab_getErrCode_GENERIC('CHILD_OF_YOURSELF_ERROR') INTO error_code;
                LEAVE main;
            END;
        
        ELSEIF s_lft > ref_lft THEN
            
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
        
        IF ref_lft = 1 THEN /* cannot move a node before or after root */
            BEGIN
                SELECT Baobab_getErrCode_GENERIC('ROOT_ERROR') INTO error_code;
                LEAVE main;
            END;
        ELSEIF s_lft < ref_lft AND s_rgt > ref_rgt THEN
            /* cannot move a parent node inside his own subtree */
            BEGIN
                SELECT Baobab_getErrCode_GENERIC('CHILD_OF_YOURSELF_ERROR') INTO error_code;
                LEAVE main;
            END;
        ELSEIF s_lft > ref_lft AND s_rgt < ref_rgt THEN
            /* we're moving a subtree as next sibling of an ancestor*/

            UPDATE Baobab_GENERIC
            SET lft =
                lft + CASE

                  WHEN lft BETWEEN s_lft AND s_rgt
                  THEN ref_rgt-s_rgt
                  WHEN lft BETWEEN s_rgt+1 AND ref_rgt-1
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
                  THEN  -(s_lft-ref_rgt-1)
                  WHEN lft BETWEEN ref_rgt+1 AND s_lft-1
                  THEN s_rgt-s_lft+1
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
    
  END;


/* ########################## */
/* ####### CLOSE GAPS ####### */
/* ########################## */


CREATE PROCEDURE Baobab_Close_Gaps_GENERIC()
LANGUAGE SQL
DETERMINISTIC

  BEGIN
  
    UPDATE Baobab_GENERIC
    SET lft = (SELECT COUNT(*)
               FROM (
                     SELECT lft as seq_nbr FROM Baobab_GENERIC
                     UNION ALL
                     SELECT rgt FROM Baobab_GENERIC
                    ) AS LftRgt
               WHERE seq_nbr <= lft
              ),
        rgt = (SELECT COUNT(*)
               FROM (
                     SELECT lft as seq_nbr FROM Baobab_GENERIC
                     UNION ALL
                     SELECT rgt FROM Baobab_GENERIC
                    ) AS LftRgt
               WHERE seq_nbr <= rgt
              );
  END
