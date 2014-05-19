<?php
namespace Queasy\Sql;
use Queasy\Query\RawQuery;

class SqlBuilderException extends \RuntimeException {};

class SqlBuilder {

	public function build($query) {
		switch(get_class($query)) {
			case 'Queasy\Query\SelectQuery':
				return $this->buildSelect($query);
				break;

			case 'Queasy\Sql\Union':
				return $this->buildUnion($query);
				break;
		}
	}

	private function buildUnion($query) {
		$queries = array();

		foreach($query->queries as $query) {
			if(is_object($query)) {
				$query = $query->toSql();
			}

			$queries[] = "\t" . str_replace("\n", "\n\t", trim($query));
		}

		return new RawQuery("(\n" . implode("\n) UNION (\n", $queries) . "\n)\n");
	}

	private function buildSelect($query) {
		$sql = new RawQuery("SELECT" . ($query->calc_rows ? " SQL_CALC_FOUND_ROWS" : "") . "\n");

		$fields = array();

		$query_fields = $query->getFields();

		// Default to SELECT *
		if(empty($query_fields)) {
			$fields = array("\t*");

		// Extract queries
		} else {
			foreach($query_fields as $alias => $field) {
				if($this->isExpression($field['field'])) {
					$expr = $this->getExpression($field['field']);

					if(is_int($alias)) {
						$fields[] = "\t" . $expr;

					} else {
						$fields[] = "\t" . $expr . " AS " . $alias;
					}

				} elseif($this->isField($field['field'])) {
					if(is_int($alias)) {
						$fields[] = "\t" . $this->getField($field['table'] . '.' . $field['field']);

					} else {
						$fields[] = "\t" . $this->getField($field['table'] . '.' . $field['field']) . " AS " . $alias;
					}

				} else {
					$fields[] = "\t" . $this->getField($field['field']) . " AS " . $alias;
				}
			}
		}

		$sql->append(implode(",\n", $fields) . "\n");

		$sql->append("FROM " . $this->getSource($query->derived, $query->from));

		foreach($query->joins as $join) {
			$join_conditions = $join['on'];
			array_unshift($join_conditions, 'AND');
			$sql->append($join['type'] . " JOIN " . $this->getSource($join['table'], $join['alias']) . " " . $this->getConditions($sql, $join_conditions, 'JOIN') . "\n");
		}

		$sql->append($this->getConditions($sql, $query->where, 'WHERE'));
		$sql->append($this->getGrouping($sql, $query->group_by));
		$sql->append($this->getOrder($sql, $query->order));
		$sql->append($this->getLimit($sql, $query->offset, $query->limit));
		return $sql;
	}

	private function isField($str) {
		return is_string($str) && preg_match('#^[a-z][a-z0-9_]*$#', $str);
	}

	private function isExpression($expr) {
		return is_a($expr, 'Queasy\Expression');
	}

	private function getSource($source, $alias) {
		if(is_object($source)) {
			$derived = is_object($source) ? $source->toSql() : $source;
			return "(\n\t" . str_replace("\n", "\n\t", trim($derived)) . "\n) " . $alias . "\n";

		} else {
			return $source . " " . $alias . "\n";
		}
	}

	private function getConditions($sql, $conditions, $type = NULL, $is_child = FALSE) {
		if(empty($conditions)) {
			return '';
		}

		if(!is_array($conditions) || !in_array($conditions[0], array('AND', 'OR'))) {
			throw new SqlBuilderException('Invalid where conditions: ' . print_r($conditions, TRUE));
		}

		// WHERE field = (value|expr)
		$prefix = NULL;
		if($type == 'WHERE') {
			if(!$is_child) {
				$prefix = 'WHERE';
			}

			$wrap_fn = function($val) use($sql) {
				return $sql->bind($val);
			};

		// JOIN... ON field = (field|expr)
		} elseif($type == 'JOIN') {
			if(!$is_child) {
				$prefix = 'ON';
			}

			$self = $this;
			$wrap_fn = function($val) {
				$class = __CLASS__;
				return $class::getField($val);
			};

		} else {
			throw new SqlBuilderException('Invalid condition type: ' . $type);
		}

		// Get condition join type (AND or OR)
		$join = array_shift($conditions);

		$clauses = array();
		foreach($conditions as $key => $condition) {
			if($this->isExpression($condition)) {
				if(is_string($key)) {
					$clauses[] = $this->getField($key) . ' = ' . $this->getExpression($condition);

				} else {
					$clauses[] = $this->getExpression($condition);
				}

			} elseif(is_string($key)) {

				// IN condition
				// Need to add error handling for if we're JOINing? Can't IN() using field names can we?
				if(is_array($condition)) {
					$clauses[] = $this->getField($key) . ' IN (' . implode(', ', array_map($wrap_fn, $condition)) . ')';
					
				} else {
					$clauses[] = $this->getField($key) . ' = ' . $wrap_fn($condition);
				}

			} elseif(is_array($condition)) {
				if(in_array($condition[0], array('AND', 'OR'))) {
					$clauses[] = "(\n\t" . str_replace("\n", "\n\t", trim($this->getConditions($sql, $condition, $type, TRUE))) . "\n)";

				} elseif(count($condition) == 3) {
					$clauses[] = $condition[0] . ' ' . $condition[1] . ' ' . $wrap_fn($condition[2]);

				} else {
					throw new SqlBuilderException('Invalid condition: ' . print_r($condition, TRUE));
				}

			} else {
				throw new SqlBuilderException('Invalid condition: ' . print_r($condition, TRUE));
			}
		}

		return ($prefix ? ($prefix . ' ') : '') . implode("\n" . $join . ' ', $clauses) . "\n";
	}

	private function getGrouping($sql, $group_by) {
		if(empty($group_by)) {
			return '';
		}

		return "GROUP BY " . implode(", ", array_map(array(get_class(), 'getField'), $group_by)) . "\n";
	}

	private function getOrder($sql, $order) {
		if(empty($order)) {
			return '';
		}

		$clauses = array();

		foreach($order as $field => $dir) {
			$clauses[] = $this->getField($field) . ' ' . $dir;
		}

		return "ORDER BY " . implode("\n\t", $clauses) . "\n";
	}

	private function getLimit($sql, $offs = NULL, $limit = NULL) {
		if(!$offs && !$limit) {
			return '';
		}

		if($offs && $limit) {
			return "LIMIT " . $sql->bindInt($offs) . ", " . $sql->bindInt($limit);

		} elseif($limit) {
			return "LIMIT " . $sql->bindInt($limit);
		}
	}

	private function getExpression($expr) {
		return (string)$expr;
	}

	static function getField($field) {
		return '`' . str_replace('.', '`.`', $field) . '`';
	}

};