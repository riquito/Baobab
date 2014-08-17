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

/**
 * .. note::
 *    Each occurrence of the word "GENERIC" across this file is meant to be
 *    replaced with the name of the tree (which must be a valid string to use
 *    as SQL table name).
 */

/* ############################### */
/* ###### TABLES AND VIEWS ####### */
/* ############################### */

CREATE TABLE IF NOT EXISTS GENERIC (
    tree_id INTEGER UNSIGNED NOT NULL,
    id      INTEGER UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    lft     INTEGER NOT NULL CHECK (lft > 0),
    rgt     INTEGER NOT NULL CHECK (rgt > 1),
    INDEX(tree_id),
    INDEX(lft),
    CONSTRAINT order_okay CHECK (lft < rgt)
) ENGINE INNODB;

DROP VIEW IF EXISTS GENERIC_AdjTree;
CREATE VIEW GENERIC_AdjTree (tree_id,parent,child,lft)
    AS
    SELECT E.tree_id,B.id, E.id, E.lft
    FROM GENERIC AS E
         LEFT OUTER JOIN GENERIC AS B
           ON B.lft = ( SELECT MAX(lft)
                        FROM GENERIC AS S
                        WHERE E.lft > S.lft
                          AND E.lft < S.rgt
                          AND E.tree_id=S.tree_id)
          AND B.tree_id=E.tree_id
    ORDER BY lft ASC;

/* ##### LIST OF TREE NAMES IN USE ##### */

CREATE TABLE IF NOT EXISTS Baobab_ForestsNames (
    name VARCHAR(200) PRIMARY KEY
) ENGINE INNODB DEFAULT CHARSET=utf8;

INSERT INTO Baobab_ForestsNames(name) VALUES ('GENERIC')
ON DUPLICATE KEY UPDATE name=name;

/* ##################################### */


/* ########################### */
/* ###### ERRORS CONTROL ##### */
/* ########################### */

CREATE TABLE IF NOT EXISTS Baobab_Errors (
    code   INTEGER UNSIGNED NOT NULL PRIMARY KEY,
    name   VARCHAR(50)      NOT NULL,
    msg    TINYTEXT         NOT NULL,
    CONSTRAINT unique_codename UNIQUE (name)
) ENGINE INNODB;

INSERT INTO Baobab_Errors(code,name,msg)
VALUES
  (1000,'VERSION','1.3.1'),
  (1100,'ROOT_ERROR','Cannot add or move a node next to root'),
  (1200,'CHILD_OF_YOURSELF_ERROR','Cannot move a node inside his own subtree'),
  (1300,'INDEX_OUT_OF_RANGE','The index is out of range'),
  (1400,'NODE_DOES_NOT_EXIST',"Node doesn't exist"),
  (1500,'VERSION_NOT_MATCH',"The library and the sql schema have different versions")
ON DUPLICATE KEY UPDATE code=code,name=name,msg=msg;

DROP FUNCTION IF EXISTS Baobab_getErrCode;
CREATE FUNCTION Baobab_getErrCode(x TINYTEXT) RETURNS INT
DETERMINISTIC
    RETURN (SELECT code from Baobab_Errors WHERE name=x);



/* ########################## */
/* ######## DROP TREE ####### */
/* ########################## */

DROP PROCEDURE IF EXISTS Baobab_GENERIC_DropTree;
CREATE PROCEDURE Baobab_GENERIC_DropTree (
                    IN node INTEGER UNSIGNED,
                    IN update_numbers INTEGER)
LANGUAGE SQL
DETERMINISTIC
MODIFIES SQL DATA

  BEGIN
    
    DECLARE drop_tree_id INTEGER UNSIGNED;
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

    SELECT tree_id, id, lft, rgt
    INTO drop_tree_id, drop_id, drop_lft, drop_rgt
    FROM GENERIC
    WHERE id = node;

    /* subtree deletion is easy */

    DELETE FROM GENERIC
    WHERE tree_id=drop_tree_id AND lft BETWEEN drop_lft and drop_rgt;
    
    IF update_numbers = 1 THEN
        /* close up the gap left by the subtree */
        
        UPDATE GENERIC
        SET lft = CASE WHEN lft > drop_lft
                THEN lft - (drop_rgt - drop_lft + 1)
                ELSE lft END,
          rgt = CASE WHEN rgt > drop_lft
                THEN rgt - (drop_rgt - drop_lft + 1)
                ELSE rgt END
        WHERE tree_id=drop_tree_id AND (lft > drop_lft OR rgt > drop_lft);
        
    END IF;

    COMMIT;

  END;

/* ########################## */
/* ###### APPEND CHILD ###### */
/* ########################## */

/* Add a new child to a parent as last sibling
   If parent_id is 0, insert a new root node, moving the
     previous root (if any) as his child.
   If choosen_tree is 0, use the first available integer as id.
   If choosen_tree is not present as tree_id in the table, it is used.
*/
DROP PROCEDURE IF EXISTS Baobab_GENERIC_AppendChild;
CREATE PROCEDURE Baobab_GENERIC_AppendChild(
            IN choosen_tree INTEGER UNSIGNED,
            IN parent_id INTEGER UNSIGNED,
            OUT new_id INTEGER UNSIGNED,
            OUT cur_tree_id INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC

  BEGIN

    DECLARE num INTEGER UNSIGNED;

    START TRANSACTION;
    
    SET cur_tree_id = IF(choosen_tree > 0,
                         choosen_tree,
                         IFNULL((SELECT MAX(tree_id)+1 FROM GENERIC),1)
                        );
    
    IF parent_id = 0 THEN /* inserting a new root node*/

        UPDATE GENERIC
        SET lft = lft+1, rgt = rgt+1
        WHERE tree_id=cur_tree_id;

        SET num = IFNULL((SELECT MAX(rgt)+1 FROM GENERIC WHERE tree_id=cur_tree_id),2);

        INSERT INTO GENERIC(tree_id, id, lft, rgt)
        VALUES (cur_tree_id, NULL, 1, num);

    ELSE /* append a new node as last right child of his parent */
        
        SET num = (SELECT rgt
                   FROM GENERIC
                   WHERE id = parent_id
                  );

        UPDATE GENERIC
        SET lft = CASE WHEN lft > num
                     THEN lft + 2
                     ELSE lft END,
            rgt = CASE WHEN rgt >= num
                     THEN rgt + 2
                     ELSE rgt END
        WHERE tree_id=cur_tree_id AND rgt >= num;

        INSERT INTO GENERIC(tree_id, id, lft, rgt)
        VALUES (cur_tree_id,NULL, num, (num + 1));

    END IF;

    SELECT LAST_INSERT_ID() INTO new_id;

    COMMIT;

  END;

/* ############################### */
/* ###### INSERT NODE AFTER ###### */
/* ############################### */

DROP PROCEDURE IF EXISTS Baobab_GENERIC_insertAfter;
CREATE PROCEDURE Baobab_GENERIC_insertAfter(
            IN sibling_id INTEGER UNSIGNED,
            OUT new_id INTEGER UNSIGNED,
            OUT error_code INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC

  main:BEGIN
    
    IF 1 = (SELECT lft FROM GENERIC WHERE id = sibling_id) THEN
        BEGIN
            SELECT Baobab_getErrCode('ROOT_ERROR') INTO error_code;
            LEAVE main;
        END;
    ELSE
        BEGIN

          DECLARE lft_sibling INTEGER UNSIGNED;
          DECLARE choosen_tree INTEGER UNSIGNED;

          START TRANSACTION;

          SELECT tree_id,rgt
          INTO choosen_tree,lft_sibling
          FROM GENERIC
          WHERE id = sibling_id;
          
          IF ISNULL(lft_sibling) THEN
              BEGIN
                SELECT Baobab_getErrCode('NODE_DOES_NOT_EXIST') INTO error_code;
                LEAVE main;
              END;
          END IF;

          UPDATE GENERIC
          SET lft = CASE WHEN lft < lft_sibling
                         THEN lft
                         ELSE lft + 2 END,
              rgt = CASE WHEN rgt < lft_sibling
                         THEN rgt
                         ELSE rgt + 2 END
          WHERE tree_id=choosen_tree AND rgt > lft_sibling;

          INSERT INTO GENERIC(tree_id,id,lft,rgt)
          VALUES (choosen_tree,NULL, (lft_sibling + 1),(lft_sibling + 2));

          SELECT LAST_INSERT_ID() INTO new_id;

          COMMIT;

        END;
    END IF;

  END;


/* ################################ */
/* ###### INSERT NODE BEFORE ###### */
/* ################################ */

DROP PROCEDURE IF EXISTS Baobab_GENERIC_insertBefore;
CREATE PROCEDURE Baobab_GENERIC_insertBefore(
            IN sibling_id INTEGER UNSIGNED,
            OUT new_id INTEGER UNSIGNED,
            OUT error_code INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC
  main:BEGIN

    IF 1 = (SELECT lft FROM GENERIC WHERE id = sibling_id) THEN
        BEGIN
            SELECT Baobab_getErrCode('ROOT_ERROR') INTO error_code;
            LEAVE main;
        END;
    ELSE
      BEGIN

        DECLARE rgt_sibling INTEGER UNSIGNED;
        DECLARE choosen_tree INTEGER UNSIGNED;

        START TRANSACTION;

        SELECT tree_id,lft
        INTO choosen_tree,rgt_sibling
        FROM GENERIC
        WHERE id = sibling_id;
        
        IF ISNULL(rgt_sibling) THEN
            BEGIN
                SELECT Baobab_getErrCode('NODE_DOES_NOT_EXIST') INTO error_code;
                LEAVE main;
            END;
        END IF;

        UPDATE IGNORE GENERIC
        SET lft = CASE WHEN lft < rgt_sibling
                     THEN lft
                     ELSE lft + 2 END,
            rgt = CASE WHEN rgt < rgt_sibling
                     THEN rgt
                     ELSE rgt + 2 END
        WHERE tree_id=choosen_tree AND rgt >= rgt_sibling
        ORDER BY lft DESC; /* order by is meant to avoid uniqueness violation on update */

        INSERT INTO GENERIC(tree_id,id,lft,rgt)
        VALUES (choosen_tree,NULL, rgt_sibling, rgt_sibling + 1);

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

/* ########################### */
/* ###### GET NTH CHILD ###### */
/* ########################### */

DROP PROCEDURE IF EXISTS Baobab_GENERIC_getNthChild;
CREATE PROCEDURE Baobab_GENERIC_getNthChild(
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
    FROM GENERIC_AdjTree WHERE parent = parent_id;

    IF num_children = 0 OR IF(idx<0,(-idx)-1,idx) >= num_children THEN
        /* idx is out of range */
        BEGIN
            SELECT Baobab_getErrCode('INDEX_OUT_OF_RANGE') INTO error_code;
            LEAVE main;
        END;
    ELSE

        SELECT child
        INTO nth_child
        FROM GENERIC_AdjTree as t1
        WHERE (SELECT count(*) FROM GENERIC_AdjTree as t2
               WHERE parent = parent_id AND t2.lft<=t1.lft AND t1.tree_id=t2.tree_id
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

DROP PROCEDURE IF EXISTS Baobab_GENERIC_MoveSubtreeBefore;
CREATE PROCEDURE Baobab_GENERIC_MoveSubtreeBefore(
        IN node_id_to_move INTEGER UNSIGNED,
        IN reference_node INTEGER UNSIGNED,
        OUT error_code INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC

  main:BEGIN
  
    DECLARE node_revised INTEGER UNSIGNED;
    DECLARE move_as_first_sibling BOOLEAN;
    DECLARE ref_left INTEGER UNSIGNED;
    DECLARE ref_node_tree INTEGER UNSIGNED;
    
    SET error_code=0; /* 0 means no error */
    SET move_as_first_sibling = TRUE;
    
    SELECT tree_id,lft
    INTO ref_node_tree,ref_left
    FROM GENERIC WHERE id = reference_node;
    
    IF ref_left = 1 THEN
        BEGIN
            /* cannot move a parent node before or after root */
            SELECT Baobab_getErrCode('ROOT_ERROR') INTO error_code;
            LEAVE main;
        END;
    END IF;
    
    /* if reference_node is the first child of his parent, set node_revised
       to the parent id, else set node_revised to NULL */
    SET node_revised = ( SELECT id FROM GENERIC WHERE tree_id=ref_node_tree AND lft = -1+ ref_left);
    
    IF ISNULL(node_revised) THEN    /* if node_revised is NULL we must find the previous sibling */
      BEGIN
        SET node_revised= (SELECT id FROM GENERIC
                           WHERE tree_id=ref_node_tree AND rgt = -1 + ref_left);
        SET move_as_first_sibling = FALSE;
      END;
    END IF;
    
    CALL Baobab_GENERIC_MoveSubtree_real(
        node_id_to_move, node_revised , move_as_first_sibling, error_code
    );

  END;



/* ##################################### */
/* ###### MOVE SUBTREE AFTER NODE ###### */
/* ##################################### */

DROP PROCEDURE IF EXISTS Baobab_GENERIC_MoveSubtreeAfter;
CREATE PROCEDURE Baobab_GENERIC_MoveSubtreeAfter(
        IN node_id_to_move INTEGER UNSIGNED,
        IN reference_node INTEGER UNSIGNED,
        OUT error_code INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC

  BEGIN
    
    SELECT 0 INTO error_code; /* 0 means no error */
    
    CALL Baobab_GENERIC_MoveSubtree_real(
        node_id_to_move,reference_node,FALSE,error_code
    );

  END;



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
    DECLARE parent_of_node_to_move INTEGER UNSIGNED;
    DECLARE s_lft INTEGER UNSIGNED;
    DECLARE current_idx INTEGER;
    
    SET error_code=0;

    SELECT COUNT(*)
    INTO num_children
    FROM GENERIC_AdjTree WHERE parent = parent_id;

    IF idx < 0 THEN
        SET idx = num_children + idx;
    ELSEIF idx > 0 THEN BEGIN

        SELECT parent, lft
        INTO parent_of_node_to_move, s_lft
        FROM GENERIC_AdjTree WHERE child = node_id_to_move;

        IF parent_of_node_to_move = parent_id THEN BEGIN
            SELECT count(*)
            INTO current_idx
            FROM GENERIC_AdjTree
            WHERE parent = parent_id AND lft < s_lft;

            IF idx > current_idx THEN
                SET idx = idx + 1;
            END IF;
          END;
        END IF;

      END;
    END IF;
    
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
            ELSEIF s_lft = ref_lft and source_node_tree = ref_node_tree THEN BEGIN
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

DROP PROCEDURE IF EXISTS Baobab_GENERIC_MoveSubtree_Different_Trees;
CREATE PROCEDURE Baobab_GENERIC_MoveSubtree_Different_Trees(
        IN node_id_to_move INTEGER UNSIGNED,
        IN reference_node INTEGER UNSIGNED,
        IN move_as_first_sibling BOOLEAN
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
    
    START TRANSACTION;

    /* select tree, left and right of the node to move */
    SELECT tree_id,lft, rgt
    INTO source_node_tree, s_lft, s_rgt
    FROM GENERIC
    WHERE id = node_id_to_move;
    
    /* The current select will behave differently whether we're moving
       the node as first sibling or not.
        
       If move_as_first_sibling,
         ref_lft will have the value of the "lft" field of node_id_to_move at end
            of move (ref_rgt here is discarded)
       else
         ref_lft and ref_rgt will have the values of the node before node_id_to_move
            at end of move
    */
    SELECT tree_id, IF(move_as_first_sibling,lft+1,lft), rgt
    INTO ref_node_tree, ref_lft, ref_rgt
    FROM GENERIC
    WHERE id = reference_node;
    
    IF (move_as_first_sibling) THEN BEGIN
        
        /* create a gap in the destination tree to hold the subtree */
        UPDATE GENERIC
        SET lft = CASE WHEN lft < ref_lft
                       THEN lft
                       ELSE lft + s_rgt-s_lft+1 END,
            rgt = CASE WHEN rgt < ref_lft
                       THEN rgt
                       ELSE rgt + s_rgt-s_lft+1 END
        WHERE tree_id=ref_node_tree AND rgt >= ref_lft;
        
        /* move the subtree to the new tree */
        UPDATE GENERIC
        SET lft = ref_lft + (lft-s_lft),
            rgt = ref_lft + (rgt-s_lft),
            tree_id = ref_node_tree
        WHERE tree_id = source_node_tree AND lft >= s_lft AND rgt <= s_rgt;
        
        END;
    ELSE BEGIN
        
        /* create a gap in the destination tree to hold the subtree */
        UPDATE GENERIC
        SET lft = CASE WHEN lft < ref_rgt
                       THEN lft
                       ELSE lft + s_rgt-s_lft+1 END,
            rgt = CASE WHEN rgt <= ref_rgt
                       THEN rgt
                       ELSE rgt + s_rgt-s_lft+1 END
        WHERE tree_id=ref_node_tree AND rgt > ref_rgt;
        
        /* move the subtree to the new tree */
        UPDATE GENERIC
        SET lft = ref_rgt+1 + (lft-s_lft),
            rgt = ref_rgt+1 + (rgt-s_lft),
            tree_id = ref_node_tree
        WHERE tree_id = source_node_tree AND lft >= s_lft AND rgt <= s_rgt;
    
        END;
    
    END IF;
    
    /* close the gap in the source tree */
    CALL Baobab_GENERIC_Close_Gaps(source_node_tree);
    
    COMMIT;
  
  END;

/* ########################## */
/* ####### CLOSE GAPS ####### */
/* ########################## */

DROP PROCEDURE IF EXISTS Baobab_GENERIC_Close_Gaps;
CREATE PROCEDURE Baobab_GENERIC_Close_Gaps(
    IN choosen_tree INTEGER UNSIGNED)
LANGUAGE SQL
DETERMINISTIC

  BEGIN
  
    UPDATE GENERIC
    SET lft = (SELECT COUNT(*)
               FROM (
                     SELECT lft as seq_nbr FROM GENERIC WHERE tree_id=choosen_tree
                     UNION ALL
                     SELECT rgt FROM GENERIC WHERE tree_id=choosen_tree
                    ) AS LftRgt
               WHERE tree_id=choosen_tree AND seq_nbr <= lft
              ),
        rgt = (SELECT COUNT(*)
               FROM (
                     SELECT lft as seq_nbr FROM GENERIC WHERE tree_id=choosen_tree
                     UNION ALL
                     SELECT rgt FROM GENERIC WHERE tree_id=choosen_tree
                    ) AS LftRgt
               WHERE tree_id=choosen_tree AND seq_nbr <= rgt
              )
    WHERE tree_id=choosen_tree;
  END
