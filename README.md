# php-simple-orm

PHP 7.4 Required.

Create simple and fast ORM in just 2 steps.

1) Create MySql database and write login and password in config.php

```
<?php

return [
    'host' => 'localhost',
    'login' => 'root',
    'password' => 'root',
    'name' => 'tests',
    'encode' => 'utf8'
];
```

2) Write class name for your table (Product.php example):

```
<?php

class Product extends Record {
    public ?int $id = null;
    public float $price;
    public ?string $name = null;
    public ?string $text = null;
}

// Product::createTable(); - for first run.

```
## Examples

```
// Get product by id = 1

$product = Product::select(
  Product::sql()->eq(
      Product::columns()->id,
      1
  )
);
```

```

// Get products by price < 1000, desc by price, offset 20 and limit 20.

$product = Product::selectAll(
  Product::sql()
      ->lt(
          Product::columns()->price,
          1000
      )
      ->limit(20, 20)
      ->desc(Product::columns()->price)
);
```

```
// Delete row with id = 12

Product::delete(
    Product::sql()->eq(Product::columns()->id, 12)
);

```

```
// Update row with id = 12

Product::update(
    [
        Product::columns()->price => 20000
    ],
    Product::sql()->eq(Product::columns()->id, 12)
);

```

## Files

### Main
1) Record - main active record.
2) Sql - sql builder for where, join, group, order, limit and having.
3) MySqlException - exception class.

### Additional   
5) config.php - config for db connection.
6) load.php - entry point.
7) Product.php - example of active record.
   
## Record
 
#### Record::select(string $sql = '', array $fields = [], bool $serialize = false) - Make select query and return object, null or json.
- $sql - you can use Product:sql()
- $fields - you can use Product::columns()->..
- $serialize - return array of objects or json.

#### Record::selectAll(string $sql = '', array $fields = [], bool $serialize = false) - Make select query and return objects or json.
- $sql - you can use Product:sql()
- $fields - you can use Product::columns()->..
- $serialize - return array of objects or json.

### Record::count(string $sql = '') - return count of rows.
- $sql - you can use Product:sql()

#### Record::delete(string $sql = null) - delete rows and return number of affected rows.
- $sql - you can use Product:sql()

#### Record::insert(array $fields): int - insert row,
#### Record::update(array $fields, string $sql = null): int - update rows and return number of affected rows.
#### Product::columns() - Easy way to get names for your columns.
#### Product::sql() - Sql class. See below.

## Sql

Sql::eq(string $field, $value) - Add condition for Where, Join or Having.
- $field - column name, use Record::columns().
- $value - value for compare.
- 
Sql::lt()
Sql::lte()
Sql::gt()
Sql::gte()
Sql::asc()
Sql::desc()
Sql::group()
Sql::having()
Sql::join()
Sql::or()
Sql::and()
