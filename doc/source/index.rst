Baobab ( a library applying the *nested set model* )
====================================================

.. toctree::
   :hidden:
   :maxdepth: 2
   
   api.rst
   sql_api.rst
   faq.rst

.. role:: raw-html(raw)
   :format: html


..
    Indices and tables
    ==================
    
    * :ref:`genindex`
    * :ref:`search`

Baobab is a library to save tree structured data in a relational database.

Currently there is only a PHP implementation working with MySQL, but it shouldn't
be too difficult to port to other languages or databases (the hard work is
done via pure SQL queries and the code is mostly a support to them).

The technique used is [#joe_celko]_ Joe Celko's [#nested_set_model]_ *nested set model*,
modified so that a table can hold more than one tree, to help with cases such as
storing threads of a forum (each thread is a tree and they all have an identical
structure).

In fact when the library often asks you for a "forest name", because each table
created can hold more than one tree.

We have more than one hundred tests to ensure the library is doing The Right Thing (™),
a straightforward thread safe :ref:`API <api>` and a clean documentation.


The *nested set model* in brief
-------------------------------


.. container:: tree-example
    
    .. image:: images/animals.png
        :width: 400 px
        :height: 210 px
        :alt: alternate text
        :class: animals
    
    
    .. rst-class:: animals
    
    =====  =======  =====
    lft    rgt      label
    =====  =======  =====
    1      18       animals
    2      9        vertebrates
    3      4        mollusks
    5      8        insects
    6      7        mantis
    10     17       invertebrates
    11     16       mammals
    12     13       tiger
    14     15       horse
    =====  =======  =====

    .. raw:: html
        
        <div class="clearLeft"></div>
    

Each node in the graph has two numbers (left and right) assigned
to it during a [#dfs]_ depth-first search of the tree: we assign the left (*lft*) value
the first time we pass by and the right (*rgt*) value the following time.
Well, the *nested set model* is
all about assigning this numbers and maintain them coherent whenever a node is
inserted, moved or deleted. :raw-html:`<br />`
With this numbers in place we gain various benefits. The **tree structure** of the
data is maintained in a relational database and we're able to do some
really fast searches. Normally slow operations like finding the path between two
nodes, knowing all the descendants of a node or discover if a node is ancestor of
another are blazing fast.
Too, **the horizontal order is preserved** without the need of others attributes.

Some simple properties of this data structure ...

* root node has halfways lft = 1
* the number of a node's descendants is ⌊(rgt-lft)/2⌋
* the ancestors of a node have both lft < nodeLft and rgt > nodeRgt
* a leaf has always rgt = lft+1

This is just an introduction, if you want to know more about *nested set models* I suggest you to read 
[#joe_celko_trees]_ **Trees and hierarchies in SQL for smarties** and/or
[#joe_celko_sql_for_smarties]_ **SQL for smarties**, both written by Joe Celko.
Online you could read a couple [#more]_ more resources.

How can Baobab help ?
---------------------

Baobab leverages the works of administering such table. In particular moving or
inserting after a particular node can be pretty complicated, and Baobab does the
hard work for you. :raw-html:`<br />`
If you feel like so, you can use Baobab for all the tree changing tasks (the most
tedious queries) and write your own queries to search what you want in the most
optimized way for your schema. :raw-html:`<br />`
However Baobab provides functions for the laziest programmers.

Here are the functions the :class:`Baobab <Baobab>` class provide

.. container:: funcList
   
    .. container:: half
        
        * create
          
          * :class:`build() <Baobab.build>`
          * :class:`destroy() <Baobab.destroy>`
          * :class:`upgrade() <Baobab.upgrade>`
        
        * retrieve
          
          * :class:`getRoot() <Baobab.getRoot>`
          * :class:`getParent() <Baobab.getParent>`
          * :class:`getDescendants() <Baobab.getDescendants>`
          * :class:`getLeaves() <Baobab.getLeaves>`
          * :class:`getLevels() <Baobab.getLevels>`
          * :class:`getPath() <Baobab.getPath>`
          * :class:`getChildren() <Baobab.getChildren>`
          * :class:`getFirstNChildren() <Baobab.getFirstNChildren>`
          * :class:`getFirstChild() <Baobab.getFirstChild>`
          * :class:`getLastChild() <Baobab.getLastChild>`
          * :class:`getChildAtIndex() <Baobab.getChildAtIndex>`
          * :class:`getTree() <Baobab.getTree>`
          * :class:`getSize() <Baobab.getSize>`
          * :class:`getTreeHeight() <Baobab.getTreeHeight>`
          * :class:`getNodeData() <Baobab.getNodeData>`
    
    .. container:: half
        
        * insert
          
          * :class:`appendChild() <Baobab.appendChild>`
          * :class:`insertAfter() <Baobab.insertAfter>`
          * :class:`insertBefore() <Baobab.insertBefore>`
          * :class:`insertChildAtIndex() <Baobab.insertChildAtIndex>`
        
        * edit
        
          * :class:`updateNode() <Baobab.updateNode>`
        
        * move
          
          * :class:`movefter() <Baobab.moveAfter>`
          * :class:`moveBefore() <Baobab.moveBefore>`
          * :class:`moveNodeAtIndex() <Baobab.moveNodeAtIndex>`
        
        * delete
          
          * :class:`deleteNode() <Baobab.deleteNode>`
          * :class:`clean() <Baobab.clean>`
          * :class:`cleanAll() <Baobab.cleanAll>`
          * :class:`closeGaps() <Baobab.closeGaps>`
        
        * data liberation
          
          * :class:`import() <Baobab.import>`
          * :class:`export() <Baobab.export>`
    
    .. raw:: html
        
        <div class="clearLeft"></div>


All of the \*_index() functions accept negative numbers too, and all the functions
that modify the tree preserve the lft/rgt consistency.



Dependencies
------------

* PHP >= 5.2 with *mysqli* module (tested on PHP 5.2 -> 5.5)
* MySQL >= 5.0 with *innodb* tables available (tested on MySQL 5.1, 5.5)

.. rubric:: Footnotes
    
.. [#joe_celko] `Joe Celko <http://www.simple-talk.com/author/joe-celko/>`_

.. [#nested_set_model] `Wikipedia on Nested set model <http://en.wikipedia.org/wiki/Nested_set_model>`_

.. [#dfs] `Depth-first search <http://en.wikipedia.org/wiki/Depth-first_search>`_

.. [#joe_celko_trees] `Joe Celko's Trees and hierarchies in SQL for smarties <http://books.google.com/books?id=uw2lq2o4VbUC&lpg=PP1&ots=DrPX6ljhOC&dq=Trees%20and%20Hierarchies%20in%20SQL%20for%20Smarties&pg=PP1#v=onepage&q&f=false>`_

.. [#joe_celko_sql_for_smarties] `Joe Celko's SQL for smarties: advanced SQL programming <http://books.google.com/books?id=Hi9fMnOoRtAC&lpg=PP1&dq=joe%20celko's%20sql%20for%20smarties&pg=PP1#v=onepage&q&f=false>`_

.. [#more] `Managing Hierarchical Data in MySQL <http://dev.mysql.com/tech-resources/articles/hierarchical-data.html>`_
