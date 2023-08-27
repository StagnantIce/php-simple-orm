<?php

class Record implements \JsonSerializable {
    /** @var Record[] */
    private static array $props = [];
    protected static ?mysqli $conn = null;
    protected static string $last = '';
    public static function setConnection(?mysqli $conn) {
        self::$conn = $conn;
    }

    public static function table(): string {
        $array = explode('\\', static::class);
        return strtolower(end($array));
    }

    /**
     * @return static
     */
    public static function columns(): self
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
     * @throws MySqlException
     */
    public static function insert(array $fields): int {
        $table = self::table();
        self::q('INSERT INTO '.$table.' ('. implode(', ', array_keys($fields)). ') VALUES ('
            . implode(', ', array_values($fields)) .')');
        return self::$conn->insert_id;
    }

    /**
     * @param array<string, string|int> $fields
     * @param string|Sql|null $sql
     * @return int
     */
    public static function update(array $fields, string $sql = null): int {
        $table = self::table();
        $q = "UPDATE `$table` SET ";
        foreach($fields as $k => &$v) $v = $k.' = '.$v;
        $q .= implode(',', $fields);
        $q .= $sql;
        self::q($q);
        return self::$conn->affected_rows;
    }

    /**
     * @param string|Sql|null $sql
     * @return int
     * @throws MySqlException
     */
    public static function delete(string $sql = null): int {
        $table = self::table();
        self::q("DELETE FROM `$table` $sql");
        return self::$conn->affected_rows;
    }

    /**
     * @param string|Sql|null $sql
     * @param array<string, string|int> $fields
     * @param bool $serialize
     * @throws MySqlException
     * @return static[]
     */
    public static function selectAll(string $sql = '', array $fields = [], bool $serialize = false): array {
        $table = self::table();
        $fieldsSql = $fields ? implode(', ', $fields) : '*';
        $res = self::q("SELECT $fieldsSql FROM `$table` $sql");
        $result = [];
        while ($row = $res->fetch_assoc()) {
            $obj = new static();
            foreach ($row as $key => $value) {
                if (property_exists($obj, $key)) {
                    $obj->$key = $value;
                }
            }
            $result[] = $serialize ? $obj->jsonSerialize() : $obj;
        }
        return $result;
    }

    /**
     * @param string|Sql|null $sql
     * @return int
     * @throws MySqlException
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
            $result[$p->getName()] = $p->getType()->getName();
        }
        return $result;
    }

    /**
     * @return void
     */
    public static function createTable(string $options = ' DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci') {
        $columns = self::getColumnTypes();
        $cols = [];
        foreach($columns as $name => $type) {
            if ($type === 'string') {
                $type = 'text';
            }
            $cols[]="\t"."`$name`".' ' . $type;
        }
        $table = self::table();
        $sql="CREATE TABLE IF NOT EXISTS `$table` (\n". implode(",\n", $cols) . "\n) $options";
        self::q($sql);
    }

    /**
     * @param string|Sql $sql
     * @param array<string, string|int> $fields
     * @param bool $serialize
     * @return static|null
     */
    public static function select(string $sql = '', array $fields = [], bool $serialize = false): ?static {
        return self::selectAll($sql, $fields, $serialize)[0] ?? null;
    }

    public static function sql(): Sql {
        return new Sql(self::table());
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
}
