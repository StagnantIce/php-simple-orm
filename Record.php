<?php

class Record implements \JsonSerializable, \ArrayAccess {
    /** @var Record[] */
    private static array $props = [];
    protected static ?mysqli $conn = null;
    protected static string $last = '';
    public static function setConnection(?mysqli $conn) {
        self::$conn = $conn;
    }

    public function __construct(array $dbRowData = [])
    {
        foreach ($dbRowData as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public static function table(): string {
        $array = explode('\\', static::class);
        if ($array[0] === 'Find') {
            throw new \Exception('Error');
        }
        return strtolower(end($array));
    }

    public static function primaryKey(): string {
        return 'id';
    }

    /**
     * @return static
     */
    public static function props(): self
    {
        $className = static::class;

        if (isset(self::$props[$className])) {
            return self::$props[$className];
        }

        $props = new class () extends Record
        {
            /**
             * @param string $property
             *
             * @return string
             */
            public function __get(string $property): string
            {
                return $property;
            }
        };

        return self::$props[$className] = $props;
    }

    /**
     * @param string $query
     * @return mysqli_result|bool
     * @throws MySqlException
     */
    public static function q(string $query) {
        self::$last = $query;
        if (self::$conn->connect_errno) {
            throw new MySqlException('Connection error: '. self::$conn->connect_error);
        }
        $res = self::$conn->query($query);
        if (!$res) {
            throw new MySqlException($query . ' error' . self::$conn->error);
        }
        return $res;
    }

    public static function escape(string $string): string {
        return self::$conn->escape_string($string);
    }

    /**
     * @throws MySqlException
     */
    public static function transaction(): void {
        self::q('START TRANSACTION');
    }

    /**
     * @throws MySqlException
     */
    public static function commit(): void {
        self::q('COMMIT');
    }

    /**
     * @throws MySqlException
     */
    public static function rollback() {
        self::q('ROLLBACK');
    }

    /**
     * @param array<string, string|int> $fields
     * @throws MySqlException|Exception
     */
    public static function insert(array $fields): int {
        $table = self::table();
        $fields = self::prepare($fields);
        self::q('INSERT INTO '.$table.' ('. implode(', ', array_keys($fields)). ') VALUES ('
            . implode(', ', array_values($fields)) .')');
        return self::$conn->insert_id;
    }

    /**
     * @param array<string, string|int> $fields
     * @param string|Find|null $sql
     * @return int
     * @throws Exception
     */
    public static function update(array $fields, string $sql = null): int {
        $table = self::table();
        $fields = self::prepare($fields);
        $q = "UPDATE `$table` SET ";
        foreach($fields as $k => &$v) $v = $k.' = '.$v;
        $q .= implode(',', $fields);
        $q .= $sql;
        self::q($q);
        return self::$conn->affected_rows;
    }

    /**
     * @param string|Find|null $sql
     * @return int
     * @throws MySqlException|Exception
     */
    public static function delete(string $sql = null): int {
        $table = self::table();
        self::q("DELETE FROM `$table` $sql");
        return self::$conn->affected_rows;
    }

    /**
     * @param string|Find|null $sql
     * @param array<string, string|int> $fields
     * @param bool $serialize
     * @return static[]|array[]
     * @throws MySqlException|Exception
     */
    public static function selectAll(string $sql = '', array $fields = [], bool $serialize = false): array {
        return static::classSelectAll(static::class, $sql, $fields, $serialize);
    }

    public static function classSelectAll(string $className, string $sql = '', array $fields = [], bool $serialize = false): array {
        $table = $className::table();
        $fieldsSql = $fields ? implode(', ', $fields) : '*';
        $res = self::q("SELECT $fieldsSql FROM `$table` $sql");
        $result = [];
        while ($row = $res->fetch_assoc()) {
            $obj = new $className($row);
            $result[] = $serialize ? $obj->jsonSerialize() : $obj;
        }
        return $result;
    }

    /**
     * @param string|Find|null $sql
     * @return int
     * @throws MySqlException|Exception
     */
    public static function count(string $sql = ''): int {
        $table = self::table();
        $res = self::q("SELECT COUNT(*) as CNT FROM $table $sql");
        return (int)$res->fetch_assoc()['CNT'];
    }

    public static function getColumnTypes(): array {
        $rc = new \ReflectionClass(static::class);
        $props = $rc->getProperties(\ReflectionProperty::IS_PUBLIC);
        $result = [];

        foreach($props as $p) {
            $type = $p->getType()->getName();
            if ($type === 'string') {
                $type = 'text';
            }
            $result[$p->getName()] = $type;
        }
        return $result;
    }

    /**
     * @return void
     * @throws Exception
     */
    public static function createTable(
        string $options = ' DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci'
    ) {
        $columns = self::getColumnTypes();
        $cols = [];
        foreach($columns as $name => $type) {
            if ($name === self::primaryKey()) {
                $type .= ' NOT NULL AUTO_INCREMENT';
            }
            $cols[]="\t"."`$name`".' ' . $type;
        }
        $table = self::table();

        $sql="CREATE TABLE IF NOT EXISTS `$table` (\n". implode(",\n", $cols)
            . (isset($columns[self::primaryKey()]) ? ', PRIMARY KEY (' . self::primaryKey() . ')' : '')
            . "\n) $options";
        self::q($sql);
    }

    public static function dropTable(): void
    {
        $table = self::table();
        self::q("DROP TABLE `$table`");
    }

    /**
     * @param string|Find $sql
     * @param array<string, string|int> $fields
     * @param bool $serialize
     * @return static|array|null
     * @throws Exception
     */
    public static function select(string $sql = '', array $fields = [], bool $serialize = false): ?self {
        return static::selectAll($sql, $fields, $serialize)[0] ?? null;
    }

    public static function classSelect(
        string $className,
        string $sql = '',
        array $fields = [],
        bool $serialize = false
    ): ?self {
        return static::classSelectAll($className, $sql, $fields, $serialize)[0] ?? null;
    }

    /**
     * @param array<string, string|int> $fields
     * @return static[]
     * @throws MySqlException|Exception
     */
    public function all(array $fields = []): array
    {
        if (!$this instanceof Find) {
            throw new \Exception('find() not called');
        }
        return static::classSelectAll($this->getClass(), $this, $fields);
    }

    /**
     * @param array<string, string|int> $fields
     * @return static|null
     * @throws MySqlException|Exception
     */
    public function one(array $fields = []): ?self
    {
        if (!$this instanceof Find) {
            throw new \Exception('find() not called');
        }
        return static::classSelect($this->getClass(), $this, $fields);
    }

    /**
     * @throws Exception
     */
    public function save(array $fields = null): int {
        $idField = self::primaryKey();
        $res = null;
        if (isset($this->$idField)) {
            $res = static::find()->eq($idField, $this->$idField)->one([$this->$idField]);
        }
        if ($res) {
            return self::update($fields ?? $this->jsonSerialize(), static::find()->eq($idField, $this->$idField));
        } else {
            return self::insert($fields ?? $this->jsonSerialize());
        }
    }

    /**
     * @throws Exception
     */
    public function remove(): int {
        $idField = self::primaryKey();
        return self::delete(self::find()->eq($idField, self::primaryKey()));
    }

    /**
     * @return static|Find
     * @throws Exception
     */
    public static function find(): Find
    {
        return new Find(static::table(), static::class);
    }

    public function jsonSerialize(): array
    {
        $result = [];
        foreach ($this as $key => $value)
        {
            $result[$key] = $value;
        }
        return $result;
    }

    private static array $column_cache = [];
    public static function tableFields($table): array
    {
        if (!array_key_exists($table, self::$column_cache))
        {
            self::$column_cache[$table] = array();
            $res = self::q("SHOW COLUMNS FROM $table");
            while($row = $res->fetch_assoc())
            {
                self::$column_cache[$table][$row["Field"]] = preg_replace('/\(\d+\)/', '', $row['Type']);
            }
        }
        return self::$column_cache[$table];
    }

    private static function prepare(array $fields): array {
        $newFields = [];
        $types = self::tableFields(self::table());

        foreach ($fields as $param => &$value) {
            // not field
            if (!isset($types[$param])){
                continue;
            } else if (is_null($value) || $value === 'NULL') {
                $value = 'NULL';
            } else if (self::isField($value)) {
                if (substr_count($value, '"') % 2 != 0 || substr_count($value, "'") % 2 != 0) {
                    $value = self::escape($value);
                }
            } else {
                if (is_bool($value)) {
                    $value = intval($value);
                }
                // string, int, float, date
                switch($types[$param]) {
                    case 'string':
                    case 'varchar':
                    case 'blob':
                    case 'char':
                    case 'text':
                        if (is_numeric($value)) {
                            $value = (string)$value;
                        }
                        if (is_string($value)) {
                            $value = '"' . self::escape($value) . '"';
                        } else {
                            $value = serialize($value);
                            throw new MySqlException("Error prepare $param field with not string value $value.");
                        }
                        break;
                    case 'real':
                    case 'float':
                    case 'decimal':
                        if (is_numeric($value)){
                            $value = DoubleVal($value);
                        } else if (is_string($value) && !trim($value)){
                            $value = 0;
                        } else {
                            $value = serialize($value);
                            throw new MySqlException("Error prepare $param field with not float value $value.");
                        }
                        break;
                    case 'int':
                    case 'tinyint':
                        if (is_numeric($value)){
                            $value = intval($value);
                        } else if (is_string($value) && !trim($value)){
                            $value = 0;
                        } else {
                            $value = serialize($value);
                            throw new MySqlException("Error prepare $param field with not int value $value.");
                        }
                        break;
                    case 'datetime':
                    case 'timestamp':
                    case 'time':
                    case 'date':
                        if (is_numeric($value)) {
                            $value = intval($value);
                        } else if (is_string($value) && strtotime($value) !== false) {
                            $value = '"' . self::escape($value) . '"';
                        } else if (!trim($value)) {
                            $value = 'NULL';
                        } else {
                            $value = serialize($value);
                            throw new MySqlException("Error prepare $param field with not date/time value $value.");
                        }
                        break;
                    default:
                        throw new MySqlException("Prepare error. Unexpected field type ". $types[$param] .'.');
                }
            }
            $newFields[$param] = $value;
        }
        return $newFields;
    }

    public static function isField($string): bool {
        if (
            strpos($string, 'DATE_ADD(') === 0
            || strpos($string, 'NOW(') === 0
        ) {
            return true;
        }
        return false;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->$offset);
    }

    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    public function offsetSet($offset, $value)
    {
        $this->$offset = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->$offset);
    }
}
