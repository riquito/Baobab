Frequently Asked Questions
==========================
This is a list of Frequently Asked Questions about Baobab. Feel free to suggest new entries!

... Is this open source ?
   Yes it is. Baobab is licensed under the `Apache License, Version 2.0. <http://www.apache.org/licenses/LICENSE-2.0>`_

... How fast is it?
   We haven't yet made any benchmarks, so we can't really say. The theory tell us
   that we are really fast in about all kind of searches, and really bad in
   insertions. If your application reads way more than writing, you'll surely
   get benefits. Anyway, we'll do some benchmarks sooner or later, or link them
   if made by third parties.

... How did you build the documentation ?
   We used the `Sphinx documentation generator <http://sphinx.pocoo.org/>`_

... What's next ?
   Current plans are to have :class:`export() <Baobab.export>` start from a particular node, a cache
   system, refactoring of some not so clean functions, allowing
   :class:`insertChildAtIndex() <Baobab.insertChildAtIndex>`
   to insert a 0th child to a leaf node.
   We're also planning to provide more examples and pictures in documentation.
   Later we will develop a more object oriented interface.