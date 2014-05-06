Queasy [![Build Status](https://travis-ci.org/ajeffrey/queasy.svg?branch=master)](https://travis-ci.org/ajeffrey/queasy)
======

Queasy is a library for writing complex SQL queries in an expressive PHP syntax.

### Writing a Query
In order to crate a new query, you first need to make a connection to your database. Currently only MySQL is supported. To create a new connection:

    $connection = new Connection(new SqlBuilder, 'mysql', array(
      'username' => 'root',
      'password' => '',
      'database' => 'my_db',
      'host' => 'localhost',
    ));
    
Once your connection is established, you can begin your first query:

    $q = $connection->query($TABLE, $ALIAS, $CLASS = '');
    
