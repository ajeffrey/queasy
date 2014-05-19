Queasy [![Build Status](https://travis-ci.org/ajeffrey/queasy.svg?branch=master)](https://travis-ci.org/ajeffrey/queasy)
======

Queasy is a library for writing complex SQL queries in an expressive PHP syntax.

### Creating a Connection
In order to crate a new query, you first need to make a connection to your database. Currently only MySQL is supported. To create a new connection:

    $conn = new Connection(new SqlBuilder, 'mysql', array(
      'username' => 'root',
      'password' => '',
      'database' => 'my_db',
      'host' => 'localhost',
    ));
    
### Writing a Query
Once your connection is established, you can begin your first query:

    // create the new query object:
    // - $table is the table to select from
    // - $alias is the alias to give the table, used when referencing the table name later on
    // - $class is the class to instantiate when the query is executed with first() or all()
    $q = $connection->query($table, $alias = 'dt', $class = 'stdClass');

    // SELECT `id`, `name`
    $q->withFields(['id', 'name');
    
    // WHERE `id` IN (1, 2, 3)...
    $q->where([
        'id' => [1, 2, 3]
    ]);
    
    // ... OR `name` LIKE '%test%'
    $q->or_where([
        ['name', 'LIKE', '%test%']
    ]);
