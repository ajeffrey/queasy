SELECT
	`p`.`name` AS project,
	`dt`.`task_id`
FROM tasks dt
INNER JOIN projects p
	ON `p`.`project_id` = `dt`.`project_id`