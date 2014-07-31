CREATE DATABASE  IF NOT EXISTS `manage_tabs` /*!40100 DEFAULT CHARACTER SET latin1 */;
USE `manage_tabs`;
-- MySQL dump 10.13  Distrib 5.5.38, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: manage_tabs
-- ------------------------------------------------------
-- Server version	5.5.38-0ubuntu0.14.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `Baobab_Errors`
--

DROP TABLE IF EXISTS `Baobab_Errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Baobab_Errors` (
  `code` int(10) unsigned NOT NULL,
  `name` varchar(50) NOT NULL,
  `msg` tinytext NOT NULL,
  PRIMARY KEY (`code`),
  UNIQUE KEY `unique_codename` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Baobab_Errors`
--

LOCK TABLES `Baobab_Errors` WRITE;
/*!40000 ALTER TABLE `Baobab_Errors` DISABLE KEYS */;
INSERT INTO `Baobab_Errors` VALUES (1000,'VERSION','1.3.0'),(1100,'ROOT_ERROR','Cannot add or move a node next to root'),(1200,'CHILD_OF_YOURSELF_ERROR','Cannot move a node inside his own subtree'),(1300,'INDEX_OUT_OF_RANGE','The index is out of range'),(1400,'NODE_DOES_NOT_EXIST','Node doesn\'t exist'),(1500,'VERSION_NOT_MATCH','The library and the sql schema have different versions');
/*!40000 ALTER TABLE `Baobab_Errors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Baobab_ForestsNames`
--

DROP TABLE IF EXISTS `Baobab_ForestsNames`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Baobab_ForestsNames` (
  `name` varchar(200) NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Baobab_ForestsNames`
--

LOCK TABLES `Baobab_ForestsNames` WRITE;
/*!40000 ALTER TABLE `Baobab_ForestsNames` DISABLE KEYS */;
INSERT INTO `Baobab_ForestsNames` VALUES ('animals');
/*!40000 ALTER TABLE `Baobab_ForestsNames` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `animals`
--

DROP TABLE IF EXISTS `animals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `animals` (
  `tree_id` int(10) unsigned NOT NULL,
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lft` int(11) NOT NULL,
  `rgt` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tree_id` (`tree_id`),
  KEY `lft` (`lft`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `animals`
--

LOCK TABLES `animals` WRITE;
/*!40000 ALTER TABLE `animals` DISABLE KEYS */;
INSERT INTO `animals` VALUES (1,1,1,18,'Animals'),(1,2,2,9,'Vertebrates'),(1,3,10,11,'Invertebrates'),(1,4,3,4,'Mollusks'),(1,5,5,8,'Insects'),(1,6,6,7,'Mantis'),(1,7,12,17,'Mammals'),(1,8,15,16,'Horse'),(1,9,13,14,'Tiger');
/*!40000 ALTER TABLE `animals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `animals_AdjTree`
--

DROP TABLE IF EXISTS `animals_AdjTree`;
/*!50001 DROP VIEW IF EXISTS `animals_AdjTree`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE TABLE `animals_AdjTree` (
  `tree_id` tinyint NOT NULL,
  `parent` tinyint NOT NULL,
  `child` tinyint NOT NULL,
  `lft` tinyint NOT NULL
) ENGINE=MyISAM */;
SET character_set_client = @saved_cs_client;

--
-- Dumping routines for database 'manage_tabs'
--
/*!50003 DROP FUNCTION IF EXISTS `Baobab_getErrCode` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `Baobab_getErrCode`(x TINYTEXT) RETURNS int(11)
    DETERMINISTIC
RETURN (SELECT code from Baobab_Errors WHERE name=x); ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `Baobab_animals_AppendChild` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `Baobab_animals_AppendChild`(
            IN choosen_tree INTEGER UNSIGNED,
            IN parent_id INTEGER UNSIGNED,
            OUT new_id INTEGER UNSIGNED,
            OUT cur_tree_id INTEGER UNSIGNED)
    DETERMINISTIC
BEGIN

    DECLARE num INTEGER UNSIGNED;

    START TRANSACTION;
    
    SET cur_tree_id = IF(choosen_tree > 0,
                         choosen_tree,
                         IFNULL((SELECT MAX(tree_id)+1 FROM animals),1)
                        );
    
    IF parent_id = 0 THEN /* inserting a new root node*/

        UPDATE animals
        SET lft = lft+1, rgt = rgt+1
        WHERE tree_id=cur_tree_id;

        SET num = IFNULL((SELECT MAX(rgt)+1 FROM animals WHERE tree_id=cur_tree_id),2);

        INSERT INTO animals(tree_id, id, lft, rgt)
        VALUES (cur_tree_id, NULL, 1, num);

    ELSE /* append a new node as last right child of his parent */
        
        SET num = (SELECT rgt
                   FROM animals
                   WHERE id = parent_id
                  );

        UPDATE animals
        SET lft = CASE WHEN lft > num
                     THEN lft + 2
                     ELSE lft END,
            rgt = CASE WHEN rgt >= num
                     THEN rgt + 2
                     ELSE rgt END
        WHERE tree_id=cur_tree_id AND rgt >= num;

        INSERT INTO animals(tree_id, id, lft, rgt)
        VALUES (cur_tree_id,NULL, num, (num + 1));

    END IF;

    SELECT LAST_INSERT_ID() INTO new_id;

    COMMIT;

  END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `Baobab_animals_Close_Gaps` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `Baobab_animals_Close_Gaps`(
    IN choosen_tree INTEGER UNSIGNED)
    DETERMINISTIC
BEGIN
  
    UPDATE animals
    SET lft = (SELECT COUNT(*)
               FROM (
                     SELECT lft as seq_nbr FROM animals WHERE tree_id=choosen_tree
                     UNION ALL
                     SELECT rgt FROM animals WHERE tree_id=choosen_tree
                    ) AS LftRgt
               WHERE tree_id=choosen_tree AND seq_nbr <= lft
              ),
        rgt = (SELECT COUNT(*)
               FROM (
                     SELECT lft as seq_nbr FROM animals WHERE tree_id=choosen_tree
                     UNION ALL
                     SELECT rgt FROM animals WHERE tree_id=choosen_tree
                    ) AS LftRgt
               WHERE tree_id=choosen_tree AND seq_nbr <= rgt
              )
    WHERE tree_id=choosen_tree;
  END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `Baobab_animals_DropTree` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `Baobab_animals_DropTree`(
                    IN node INTEGER UNSIGNED,
                    IN update_numbers INTEGER)
    MODIFIES SQL DATA
    DETERMINISTIC
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
    FROM animals
    WHERE id = node;

    /* subtree deletion is easy */

    DELETE FROM animals
    WHERE tree_id=drop_tree_id AND lft BETWEEN drop_lft and drop_rgt;
    
    IF update_numbers = 1 THEN
        /* close up the gap left by the subtree */
        
        UPDATE animals
        SET lft = CASE WHEN lft > drop_lft
                THEN lft - (drop_rgt - drop_lft + 1)
                ELSE lft END,
          rgt = CASE WHEN rgt > drop_lft
                THEN rgt - (drop_rgt - drop_lft + 1)
                ELSE rgt END
        WHERE tree_id=drop_tree_id AND lft > drop_lft OR rgt > drop_lft;
        
    END IF;

    COMMIT;

  END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `Baobab_animals_getNthChild` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `Baobab_animals_getNthChild`(
            IN parent_id INTEGER UNSIGNED,
            IN idx INTEGER,
            OUT nth_child INTEGER UNSIGNED,
            OUT error_code INTEGER UNSIGNED)
    DETERMINISTIC
main:BEGIN

    DECLARE num_children INTEGER;
    
    SET error_code=0;

    SELECT COUNT(*)
    INTO num_children
    FROM animals_AdjTree WHERE parent = parent_id;

    IF num_children = 0 OR IF(idx<0,(-idx)-1,idx) >= num_children THEN
        /* idx is out of range */
        BEGIN
            SELECT Baobab_getErrCode('INDEX_OUT_OF_RANGE') INTO error_code;
            LEAVE main;
        END;
    ELSE

        SELECT child
        INTO nth_child
        FROM animals_AdjTree as t1
        WHERE (SELECT count(*) FROM animals_AdjTree as t2
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

  END; ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `Baobab_animals_insertAfter` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `Baobab_animals_insertAfter`(
            IN sibling_id INTEGER UNSIGNED,
            OUT new_id INTEGER UNSIGNED,
            OUT error_code INTEGER UNSIGNED)
    DETERMINISTIC
main:BEGIN
    
    IF 1 = (SELECT lft FROM animals WHERE id = sibling_id) THEN
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
          FROM animals
          WHERE id = sibling_id;
          
          IF ISNULL(lft_sibling) THEN
              BEGIN
                SELECT Baobab_getErrCode('NODE_DOES_NOT_EXIST') INTO error_code;
                LEAVE main;
              END;
          END IF;

          UPDATE animals
          SET lft = CASE WHEN lft < lft_sibling
                         THEN lft
                         ELSE lft + 2 END,
              rgt = CASE WHEN rgt < lft_sibling
                         THEN rgt
                         ELSE rgt + 2 END
          WHERE tree_id=choosen_tree AND rgt > lft_sibling;

          INSERT INTO animals(tree_id,id,lft,rgt)
          VALUES (choosen_tree,NULL, (lft_sibling + 1),(lft_sibling + 2));

          SELECT LAST_INSERT_ID() INTO new_id;

          COMMIT;

        END;
    END IF;

  END; ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `Baobab_animals_insertBefore` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `Baobab_animals_insertBefore`(
            IN sibling_id INTEGER UNSIGNED,
            OUT new_id INTEGER UNSIGNED,
            OUT error_code INTEGER UNSIGNED)
    DETERMINISTIC
main:BEGIN

    IF 1 = (SELECT lft FROM animals WHERE id = sibling_id) THEN
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
        FROM animals
        WHERE id = sibling_id;
        
        IF ISNULL(rgt_sibling) THEN
            BEGIN
                SELECT Baobab_getErrCode('NODE_DOES_NOT_EXIST') INTO error_code;
                LEAVE main;
            END;
        END IF;

        UPDATE IGNORE animals
        SET lft = CASE WHEN lft < rgt_sibling
                     THEN lft
                     ELSE lft + 2 END,
            rgt = CASE WHEN rgt < rgt_sibling
                     THEN rgt
                     ELSE rgt + 2 END
        WHERE tree_id=choosen_tree AND rgt >= rgt_sibling
        ORDER BY lft DESC; /* order by is meant to avoid uniqueness violation on update */

        INSERT INTO animals(tree_id,id,lft,rgt)
        VALUES (choosen_tree,NULL, rgt_sibling, rgt_sibling + 1);

        SELECT LAST_INSERT_ID() INTO new_id;

        COMMIT;

      END;
    END IF;

END; ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `Baobab_animals_InsertChildAtIndex` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `Baobab_animals_InsertChildAtIndex`(
            IN parent_id INTEGER UNSIGNED,
            IN idx INTEGER,
            OUT new_id INTEGER UNSIGNED,
            OUT error_code INTEGER UNSIGNED)
    DETERMINISTIC
BEGIN
    
    DECLARE nth_child INTEGER UNSIGNED;
    DECLARE cur_tree_id INTEGER UNSIGNED;
    
    SET error_code=0;
    SET new_id=0;

    CALL Baobab_animals_getNthChild(parent_id,idx,nth_child,error_code);
    
    IF NOT error_code THEN
        CALL Baobab_animals_insertBefore(nth_child,new_id,error_code);
    ELSE IF idx = 0 AND error_code = (SELECT Baobab_getErrCode('INDEX_OUT_OF_RANGE')) THEN
        BEGIN
          SET error_code = 0;
          CALL Baobab_animals_AppendChild((SELECT tree_id FROM animals WHERE id = parent_id),
                                           parent_id,
                                           new_id,
                                           cur_tree_id);
        END;
      END IF;
    END IF;
    
  END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `Baobab_animals_MoveSubtreeAfter` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `Baobab_animals_MoveSubtreeAfter`(
        IN node_id_to_move INTEGER UNSIGNED,
        IN reference_node INTEGER UNSIGNED,
        OUT error_code INTEGER UNSIGNED)
    DETERMINISTIC
BEGIN
    
    SELECT 0 INTO error_code; /* 0 means no error */
    
    CALL Baobab_animals_MoveSubtree_real(
        node_id_to_move,reference_node,FALSE,error_code
    );

  END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `Baobab_animals_MoveSubtreeAtIndex` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `Baobab_animals_MoveSubtreeAtIndex`(
        IN node_id_to_move INTEGER UNSIGNED,
        IN parent_id INTEGER UNSIGNED,
        IN idx INTEGER,
        OUT error_code INTEGER)
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
    FROM animals_AdjTree WHERE parent = parent_id;

    IF idx < 0 THEN
        SET idx = num_children + idx;
    ELSEIF idx > 0 THEN BEGIN

        SELECT parent, lft
        INTO parent_of_node_to_move, s_lft
        FROM animals_AdjTree WHERE child = node_id_to_move;

        IF parent_of_node_to_move = parent_id THEN BEGIN
            SELECT count(*)
            INTO current_idx
            FROM animals_AdjTree
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
        CALL Baobab_animals_MoveSubtree_real(node_id_to_move,parent_id,TRUE,error_code);
    ELSE
      BEGIN
        /* search the node before idx, and we wil move our node after that */
        CALL Baobab_animals_getNthChild(parent_id,idx-1,nth_child,error_code);

        IF NOT error_code THEN
            CALL Baobab_animals_MoveSubtree_real(node_id_to_move,nth_child,FALSE,error_code);
        END IF;
      END;
    END IF;

  END; ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `Baobab_animals_MoveSubtreeBefore` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `Baobab_animals_MoveSubtreeBefore`(
        IN node_id_to_move INTEGER UNSIGNED,
        IN reference_node INTEGER UNSIGNED,
        OUT error_code INTEGER UNSIGNED)
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
    FROM animals WHERE id = reference_node;
    
    IF ref_left = 1 THEN
        BEGIN
            /* cannot move a parent node before or after root */
            SELECT Baobab_getErrCode('ROOT_ERROR') INTO error_code;
            LEAVE main;
        END;
    END IF;
    
    /* if reference_node is the first child of his parent, set node_revised
       to the parent id, else set node_revised to NULL */
    SET node_revised = ( SELECT id FROM animals WHERE tree_id=ref_node_tree AND lft = -1+ ref_left);
    
    IF ISNULL(node_revised) THEN    /* if node_revised is NULL we must find the previous sibling */
      BEGIN
        SET node_revised= (SELECT id FROM animals
                           WHERE tree_id=ref_node_tree AND rgt = -1 + ref_left);
        SET move_as_first_sibling = FALSE;
      END;
    END IF;
    
    CALL Baobab_animals_MoveSubtree_real(
        node_id_to_move, node_revised , move_as_first_sibling, error_code
    );

  END; ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `Baobab_animals_MoveSubtree_Different_Trees` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `Baobab_animals_MoveSubtree_Different_Trees`(
        IN node_id_to_move INTEGER UNSIGNED,
        IN reference_node INTEGER UNSIGNED,
        IN move_as_first_sibling BOOLEAN
        )
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
    FROM animals
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
    FROM animals
    WHERE id = reference_node;
    
    IF (move_as_first_sibling) THEN BEGIN
        
        /* create a gap in the destination tree to hold the subtree */
        UPDATE animals
        SET lft = CASE WHEN lft < ref_lft
                       THEN lft
                       ELSE lft + s_rgt-s_lft+1 END,
            rgt = CASE WHEN rgt < ref_lft
                       THEN rgt
                       ELSE rgt + s_rgt-s_lft+1 END
        WHERE tree_id=ref_node_tree AND rgt >= ref_lft;
        
        /* move the subtree to the new tree */
        UPDATE animals
        SET lft = ref_lft + (lft-s_lft),
            rgt = ref_lft + (rgt-s_lft),
            tree_id = ref_node_tree
        WHERE tree_id = source_node_tree AND lft >= s_lft AND rgt <= s_rgt;
        
        END;
    ELSE BEGIN
        
        /* create a gap in the destination tree to hold the subtree */
        UPDATE animals
        SET lft = CASE WHEN lft < ref_rgt
                       THEN lft
                       ELSE lft + s_rgt-s_lft+1 END,
            rgt = CASE WHEN rgt <= ref_rgt
                       THEN rgt
                       ELSE rgt + s_rgt-s_lft+1 END
        WHERE tree_id=ref_node_tree AND rgt > ref_rgt;
        
        /* move the subtree to the new tree */
        UPDATE animals
        SET lft = ref_rgt+1 + (lft-s_lft),
            rgt = ref_rgt+1 + (rgt-s_lft),
            tree_id = ref_node_tree
        WHERE tree_id = source_node_tree AND lft >= s_lft AND rgt <= s_rgt;
    
        END;
    
    END IF;
    
    /* close the gap in the source tree */
    CALL Baobab_animals_Close_Gaps(source_node_tree);
    
    COMMIT;
  
  END; ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `Baobab_animals_MoveSubtree_real` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_UNSIGNED_SUBTRACTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `Baobab_animals_MoveSubtree_real`(
        IN node_id_to_move INTEGER UNSIGNED,
        IN reference_node INTEGER UNSIGNED,
        IN move_as_first_sibling BOOLEAN,
        OUT error_code INTEGER
        )
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
    FROM animals
    WHERE id = node_id_to_move;
    
    /* select left and right of the reference node
        
        If moving as first sibling, ref_lft will become the new lft value of node_id_to_move,
         (and ref_rgt is unused), else we're saving left and right value of soon to be
         previous sibling
    
    */
    SELECT tree_id, IF(move_as_first_sibling,lft+1,lft), rgt
    INTO ref_node_tree, ref_lft, ref_rgt
    FROM animals
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
            CALL Baobab_animals_MoveSubtree_Different_Trees(
                node_id_to_move,reference_node,move_as_first_sibling);
            LEAVE main;
        END;
    END IF;
    
    UPDATE animals
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
    
  END; ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Final view structure for view `animals_AdjTree`
--

/*!50001 DROP TABLE IF EXISTS `animals_AdjTree`*/;
/*!50001 DROP VIEW IF EXISTS `animals_AdjTree`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = latin1 */;
/*!50001 SET character_set_results     = latin1 */;
/*!50001 SET collation_connection      = latin1_swedish_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `animals_AdjTree` AS select `E`.`tree_id` AS `tree_id`,`B`.`id` AS `parent`,`E`.`id` AS `child`,`E`.`lft` AS `lft` from (`animals` `E` left join `animals` `B` on(((`B`.`lft` = (select max(`S`.`lft`) from `animals` `S` where ((`E`.`lft` > `S`.`lft`) and (`E`.`lft` < `S`.`rgt`) and (`E`.`tree_id` = `S`.`tree_id`)))) and (`B`.`tree_id` = `E`.`tree_id`)))) order by `E`.`lft` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2014-07-31 10:40:36
