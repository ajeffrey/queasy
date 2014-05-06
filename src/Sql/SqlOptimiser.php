<?php
namespace Queasy\Sql;
use Queasy\Sql\Union;
use Queasy\Expression;

class SqlOptimiser {
	private $builder;
	$enabled = array();

	public function __construct($builder) {
		$this->builder = $builder;
	}

	public function build($query) {
		return $this->builder->build($this->optimise($query));
	}

	public function enable($optimisation) {
		$this->enabled[$optimisation] = TRUE;
	}

	public function disable($optimisation) {
		$this->enabled[$optimisation] = FALSE;
	}

	private function isEnabled($optimisation) {
		return isset($this->enabled[$optimisation]) && $this->enabled[$optimisation];
	}

	private function optimise($query) {

		/* Optimisation: turn OR into union */
		if($this->isEnabled('OR conditions')) {
			$query = $this->optimiseOrCondition($query);
		}
		
		/* TODO Optimisation: move table-relevant WHERE conditions into JOIN */
		// $query = $this->optimiseJoinConditions($query);

		/* TODO Optimisation: move WHERE conditions into derived tables */
		// $query = $this->optimiseDerivedConditions($query);

		/* TODO Optimisation: copy LIMIT, GROUP BY, ORDER BY into derived tables */
		// $query = $this->optimiseDerivedQueries($query);

		return $query;
	}

	private function optimiseOrCondition($query, $ptr = array()) {
		$conditions = $this->accessConditionByPointer($query, $ptr);
		$type = array_shift($conditions);

		// If this condition is an OR, we want to:
		// - duplicate the whole query for each condition in the OR
		// - for each duplicate, replace the set of conditions 
		if($type == 'OR') {
			$union = array();

			// Plan:
			// For each option in the OR, make a clone of the query.
			// Then for each clone, replace the OR condition with an AND of the condition
			// at the same index in the OR as the clone is in the clones.
			for($i = 0; $i < count($conditions); $i++) {
				$clone = clone $query;

				// Get condition at same index of OR
				$new_condition = array_slice($conditions, $i, 1, TRUE);
				$condition_key = array_pop(array_keys($new_condition));

				// If the condition is keyed by ints - therefore, can be indexed to 0
				// Replace OR with array(AND, cond)
				if(is_int($condition_key)) {
					$this->setConditionByPointer($clone, $ptr, array('AND', reset($new_condition)));

				// The condition is keyed by string
				// Replace OR with array(AND, key => cond)
				} else {
					$this->setConditionByPointer($clone, $ptr, array('AND', $condition_key => reset($new_condition)));
				}

				$union[] = $clone;
			}

			// Blank off clauses that are not relevant in the outer query
			$query->setFrom(new Union($union), 'dt');
			$query->setFields(new Expression('*'));
			$query->setWhere(array());
			$query->setJoins(array());

			// TODO TODO TODO
			// Add ORDER, LIMIT, GROUP BY from original query in here
			// Or we could just use the original query and use set_from :)

			return $query;

		// WHERE is using AND - recurse into child conditions
		} elseif($type == 'AND') {
			foreach($conditions as $k => $condition) {
				if(is_array($condition) && !empty($condition) && in_array($condition[0], array('AND', 'OR'))) {
					$query = $this->optimiseOrCondition($query, array_merge($ptr, array($k)));
				}
			}
		}

		return $query;
	}

	private function optimiseDerivedQueries($query, $root_query = NULL) {
		if($root_query) {
			$selected_fields = array();
			foreach($root_query->fields as $alias => $field) {
				$selected_fields[] = is_int($alias) ? $field['field'] : $alias;
			}

			// Pass through group_by statements
			if($query->group_by) {
				if(!array_diff($query->group_by, $selected_fields)) {
					$query->group_by($query->group_by);
				}
			}

		} else {

			// If we don't need to optimise the query, skip
			if(!$query->group_by && !$query->order && !$query->offset && !$query->limit) {
				return $query;
			}

			$root_query = $query;
		}

		if(!is_string($query->derived)) {
			optimiseDerivedQueries($query->derived, $root_query);
		}

		foreach($query->joins as &$join) {
			if(!is_string($join['table'])) {
				optimiseDerivedQueries($join['table'], $root_query);
			}
		}

	}


	// Replace one condition set with another, at position $ptr
	private function setConditionByPointer($query, $ptr, $value) {
		if($ptr == array()) {
			$query->setWhere($value);
		}

		$conditions = $query->where;
		$conductor = &$conditions;

		while(!empty($ptr)) {
			$index = array_shift($ptr);
			$conductor = &$conductor[$index + 1];
		}

		$conductor = $value;
		$query->setWhere($conditions);
	}

	// Read a condition set from the query, using pointer $ptr
	private function accessConditionByPointer($query, $ptr) {
		$conditions = $query->where;

		while(!empty($ptr)) {
			$index = array_shift($ptr);
			$conditions = $conditions[$index + 1];
		}

		return $conditions;
	}
	
};