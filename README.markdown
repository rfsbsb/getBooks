Get Book
========

This is a simple PHP script to retreive data of books from *Livraria Cultura* website.
This script relys on PHP DOM classes and was only tested in UNIX enviroment with * PHP >= 5.3 *

There are two classes:

* DomFinder - Wich is an abstraction on PHP DOM class to make it easier to navigate in the document using XPATH
* BookRetreiver - Wich do most of the work. There are methods to search and retreive some especific data from book pages.
