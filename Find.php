<?php

class Find extends Record {
    private array $where = [];
    private array $having = [];
    private array $join = [];
    private string $group = '';
    private array $order = [];
    private string $table;
    private bool $isHaving = false;
    private bool $isJoin = false;
    private string $limit = '';
    private string $groupCondition = 'AND';
    private ?string $className = null;

    public function __construct(string $table, string $className = null) {
        $this->table = $table;
        $this->className = $className;
        parent::__construct();
    }

    public function getClass(): ?string {
        return $this->className;
    }

    public function or(): self {
        $this->groupCondition();
        $this->groupCondition = 'OR';
        return $this;
    }

    public function and(): self {
        $this->groupCondition();
        $this->groupCondition = 'AND';
        return $this;
    }

    public function eq(string $field, $value): self {
        return $this->addCondition($field, '=', $value);
    }

    public function lt(string $field, $value): self {
        return $this->addCondition($field, '<', $value);
    }

    public function lte(string $field, $value): self {
        return $this->addCondition($field, '<=', $value);
    }

    public function gt(string $field, $value): self {
        return $this->addCondition($field, '>', $value);
    }

    public function gte(string $field, $value): self {
        return $this->addCondition($field, '>=', $value);
    }

    public function llike(string $field, $value): self {
        return $this->addCondition($field, ' LIKE ', '%' . $value);
    }

    public function rlike(string $field, $value): self {
        return $this->addCondition($field, ' LIKE ', $value . '%');
    }

    public function like(string $field, $value): self {
        return $this->addCondition($field, ' LIKE ', '%' . $value . '%');
    }

    public function limit(int $limit, int $offset = 0): self {
        $this->limit = ' LIMIT ' . $offset . ', ' . $limit;
        return $this;
    }

    public function having(): self {
        if (!$this->isHaving) {
            $this->groupCondition();
            $this->isHaving = true;
        }
        return $this;
    }

    public function join(): self {
        if (!$this->isJoin) {
            $this->groupCondition();
            $this->isJoin = true;
        }
        return $this;
    }

    public function findAndReplaceTableName($value) {
        return preg_replace('/\*\.[`]?([A-Za-z_0-9]+)/', "`{$this->table}`.`$1`", $value);
    }

    public function group(array $groups): self {
        if ($groups) {
            $res = [];
            foreach($groups as $group) {
                $res[] = $this->findAndReplaceTableName($group);
            }
            $this->group = ' GROUP BY '.implode(', ', $res) .' ';
        }
        return $this;
    }

    /**
     * @param string $field
     * @return $this
     */
    public function asc(string $field): self {
        $this->order[] = $this->findAndReplaceTableName($field) . ' ASC';
        return $this;
    }

    /**
     * @param string $field
     * @return $this
     */
    public function desc(string $field): self {
        $this->order[] = $this->findAndReplaceTableName($field) . ' ASC';
        return $this;
    }

    public function __toString(): string
    {
        $this->groupCondition();
        return ($this->join[0] ?? '')
            . (isset($this->where[0]) ? " WHERE {$this->where[0]}" : '')
            . $this->group
            . ($this->having[0] ?? '')
            . ($this->order ? ' ORDER BY ' . implode(', ', $this->order) : '')
            . $this->limit;
    }

    private function addCondition(string $field, string $formula, $value): self {
        $cond = $this->findAndReplaceTableName($field)
            . $formula
            . (is_numeric($value) ? $value : '"' . Record::escape($value) . '"');
        if ($this->isHaving) {
            $this->having[] = $cond;
        } else if ($this->isJoin) {
            $this->join[] = $cond;
        } else {
            $this->where[] = $cond;
        }
        return $this;
    }

    private function groupCondition(): void {
        if ($this->isHaving && $this->having) {
            $this->having = [ '(' . implode(" $this->groupCondition ", $this->having) . ')'];
        } else if ($this->isJoin && $this->join) {
            $this->join = [ '(' . implode(" $this->groupCondition ", $this->join) . ')'];
        } else if ($this->where) {
            $this->where = [ '(' . implode(" $this->groupCondition ", $this->where) . ')'];
        }
    }
}
