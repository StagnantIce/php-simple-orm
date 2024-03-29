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

And add require_once('init.php'); to your code.

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

// Create Product

```

$product = new Product([
    Product::props()->name => 'Test',
]);

$product->save();

```


```
// Get product by id = 1

Product::find()
    ->eq(Product::props()->id, 1)
    ->one();

```

```

// Get products by price < 1000, desc by price, offset 20 and limit 20.

$product = Product::find()
      ->lt(
          Product::props()->price,
          1000
      )
      ->limit(20, 20)
      ->desc(Product::props()->price)
      ->all();
```

```
// Delete row with id = 12

Product::find()
    ->eq(Product::props()->id, 12)
    ->remove();

```

```
// Update row with id = 12

Product::find()
    ->eq(Product::props()->id, 12)
    ->save([
        Product::props()->price => 20000
    ]);

```

## Files

### Main
1) Record - main active record.
2) Find - sql builder for where, join, group, order, limit and having.
3) MySqlException - exception class.

### Additional
7) Product.php - example of active record.
   
## Record
 
#### Record::select(string $sql = '', array $fields = [], bool $serialize = false) - Make select query and return object, null or json.
- $sql - you can use Product:sql()
- $fields - you can use Product::props()->..
- $serialize - return array of objects or json.

#### Record::selectAll(string $sql = '', array $fields = [], bool $serialize = false) - Make select query and return objects or json.
- $sql - you can use Product:sql()
- $fields - you can use Product::props()->..
- $serialize - return array of objects or json.

#### Record::count(string $sql = '') - return count of rows.
- $sql - you can use Product:find()

#### Record::delete(string $sql = null) - delete rows and return number of affected rows.
- $sql - you can use Product:find()

#### Record::insert(array $fields): int - insert row,
#### Record::update(array $fields, string $sql = null): int - update rows and return number of affected rows.
#### Product::props() - Easy way to get names for your props.
#### Product::find() - Sql class. See below.

## Find

Find::eq(string $field, $value) - Add condition for Where, Join or Having.
- $field - column name, use Record::props().
- $value - value for compare.
- 
Find::lt()
Find::lte()
Find::gt()
Find::gte()
Find::asc()
Find::desc()
Find::group()
Find::having()
Find::join()
Find::or()
Find::and()
