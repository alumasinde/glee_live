SET FOREIGN_KEY_CHECKS = 0;

SELECT CONCAT('TRUNCATE TABLE `', table_name, '`;')
FROM information_schema.tables
WHERE table_schema = 'glee_live';

SET FOREIGN_KEY_CHECKS = 1;