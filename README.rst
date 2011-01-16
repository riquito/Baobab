Baobab ( a library applying the *nested set model* )
====================================================

Baobab is a library to save tree structured data in a relational database.

Currently there is only a PHP implementation working with MySQL, but it shouldn't
be too difficult to port to other languages or databases (most of the work is
done via SQL queries and the code is mostly a support to them).

The technique used is Joe Celko's *nested set model*,
modified so that a table can hold more than one tree, to help with cases such as
storing threads of a forum (each thread is a tree and they all have an identical
structure).

In fact when the library asks you for a "tree name", it's really asking for a
forest name, because each table created can hold more than one tree.

We have more than one hundred tests to ensure the library is doing The Right Thing (™),
a straightforward API and a clean documentation.

You can find the library's documentation at `<http://baobab.sideralis.org>`_
