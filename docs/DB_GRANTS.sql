-- Activity log privileges for the application database account.
-- Do not run this file on the development root account.
-- The design does not name the production account. Replace dpps_app and
-- localhost with that account before running.
-- Do not also grant ALL PRIVILEGES on the whole database to this account.
-- A database-wide grant would include UPDATE and DELETE on activity_logs.

REVOKE ALL PRIVILEGES ON `dpp_management_system`.`activity_logs` FROM 'dpps_app'@'localhost';

GRANT SELECT, INSERT ON `dpp_management_system`.`activity_logs` TO 'dpps_app'@'localhost';
