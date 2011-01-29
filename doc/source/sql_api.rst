SQL API
=======
It could happen that you want to do some SQL queries by yourself. It must not
become a nightmare. That's why here you can find some informations about the
model used by Baobab and the functions or procedures that you could call
if you need them.

Please note that each tree has his own set of tables and functions, and
whenever you find the term "GENERIC" you must replace it with your forest name.
(tables or functions without "GENERIC" in it are shared between forests).

.. note::
   Each table holds indeed a forest, with zero, one or many trees.

TABLE GENERIC
-------------

This is in fact the main Baobab table, holding the nodes' informations.
Unless you altered it to suit your needs, this is the structure.

.. code-block:: sql
   
   TABLE GENERIC (
      tree_id INTEGER UNSIGNED NOT NULL,
      id      INTEGER UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      lft     INTEGER NOT NULL CHECK (lft > 0),
      rgt     INTEGER NOT NULL CHECK (rgt > 1),
      INDEX(tree_id),
      INDEX(lft)
   )

.. note::
   You can alter it as much as you want as long there are these 4 fields in any order.


VIEW GENERIC_AdjTree
--------------------

This view holds the id of each parent of GENERIC nodes. Root has parent NULL.

.. code-block:: sql
   
   VIEW GENERIC_AdjTree (tree_id,parent,child,lft)


TABLE Baobab_ForestsNames
----------------------

This table store the set of names in use from Baobab. If you construct a new "GENERIC"
table, you must insert his name here, and remove it if that table is dropped.

.. code-block:: sql

   TABLE Baobab_ForestsNames (
       name VARCHAR(200) PRIMARY KEY
   )


TABLE Baobab_Errors
-------------------

This table contains general errors that could occur in some situations.
"code" is the error number and it's associated to a unique codename "name" and
to a human understandable message "msg".

.. code-block:: sql

   TABLE Baobab_Errors (
      code   INTEGER UNSIGNED NOT NULL PRIMARY KEY,
      name   VARCHAR(50)      NOT NULL,
      msg    TINYTEXT         NOT NULL,
      CONSTRAINT unique_codename UNIQUE (name)
   )


FUNCTION Baobab_getErrCode
--------------------------

Return the error id associated to an error codename.

.. code-block:: sql
   
   FUNCTION Baobab_getErrCode(x TINYTEXT) RETURNS INT
  

PROCEDURE Baobab_GENERIC_DropTree
---------------------------------

The name of this function is unfortunate. It destroys a node and all of the nodes in
his subtree. If update_numbers is 1 then close the gap created.
  
.. code-block:: sql
   
   Baobab_GENERIC_DropTree (
            IN node INTEGER UNSIGNED,
            IN update_numbers INTEGER)
  
  

PROCEDURE Baobab_GENERIC_AppendChild
------------------------------------

Add a child as last right sibling in a choosen tree. Returns the id of the new
node created and the id of his tree. If choosen_tree is 0 a new tree will be created.

.. code-block:: sql
   
   Baobab_GENERIC_AppendChild(
            IN choosen_tree INTEGER UNSIGNED,
            IN parent_id INTEGER UNSIGNED,
            OUT new_id INTEGER UNSIGNED,
            OUT cur_tree_id INTEGER UNSIGNED)

  
PROCEDURE Baobab_GENERIC_insertAfter
------------------------------------

Insert a new node at the right side of a given id.

.. code-block:: sql
   
   Baobab_GENERIC_insertAfter(
            IN sibling_id INTEGER UNSIGNED,
            OUT new_id INTEGER UNSIGNED,
            OUT error_code INTEGER UNSIGNED) 

.. note::
   You cannot insert a node before or after root node
  
  
  
PROCEDURE Baobab_GENERIC_insertBefore
-------------------------------------

Insert a new node at the left side of a given id.

.. code-block:: sql
   
   Baobab_GENERIC_insertBefore(
            IN sibling_id INTEGER UNSIGNED,
            OUT new_id INTEGER UNSIGNED,
            OUT error_code INTEGER UNSIGNED)

.. note::
   You cannot insert a node before or after root node

   
PROCEDURE Baobab_GENERIC_InsertChildAtIndex
-------------------------------------------

Insert a new node as nth child of an existing node. You can use indexes
starting from 0 or -1 (will start counting from the right side).

.. code-block:: sql
   
   Baobab_GENERIC_InsertChildAtIndex(
            IN parent_id INTEGER UNSIGNED,
            IN idx INTEGER,
            OUT new_id INTEGER UNSIGNED,
            OUT error_code INTEGER UNSIGNED)
   

.. note::
   The new parent must have at least an existing child

   
PROCEDURE Baobab_GENERIC_getNthChild
------------------------------------

Retrieve the id of the nth child of a given parent. Negative indexes are allowed.

.. code-block:: sql
   
   Baobab_GENERIC_getNthChild(
            IN parent_id INTEGER UNSIGNED,
            IN idx INTEGER,
            OUT nth_child INTEGER UNSIGNED,
            OUT error_code INTEGER UNSIGNED)  


PROCEDURE Baobab_GENERIC_MoveSubtreeBefore
------------------------------------------

Move a node and his subtree to the left of another node.

.. code-block:: sql
   
   Baobab_GENERIC_MoveSubtreeBefore(
        IN node_id_to_move INTEGER UNSIGNED,
        IN reference_node INTEGER UNSIGNED,
        OUT error_code INTEGER UNSIGNED)
   
   
PROCEDURE Baobab_GENERIC_MoveSubtreeAfter
-----------------------------------------

Move a node and his subtree to the right of another node.

.. code-block:: sql
   
   Baobab_GENERIC_MoveSubtreeAfter(
            IN node_id_to_move INTEGER UNSIGNED,
            IN reference_node INTEGER UNSIGNED,
            OUT error_code INTEGER UNSIGNED)
   
   
PROCEDURE Baobab_GENERIC_MoveSubtreeAtIndex
-------------------------------------------

Move a node and his subtree as the nth child of a given node.
Negative indexes are allowed.

.. code-block:: sql
   
   Baobab_GENERIC_MoveSubtreeAtIndex(
            IN node_id_to_move INTEGER UNSIGNED,
            IN parent_id INTEGER UNSIGNED,
            IN idx INTEGER,
            OUT error_code INTEGER)

   
PROCEDURE Baobab_GENERIC_Close_Gaps
-----------------------------------

Close gaps caused by removing a node or a subtree.

.. code-block:: sql
   
   Baobab_GENERIC_Close_Gaps(
            IN choosen_tree INTEGER UNSIGNED)
    