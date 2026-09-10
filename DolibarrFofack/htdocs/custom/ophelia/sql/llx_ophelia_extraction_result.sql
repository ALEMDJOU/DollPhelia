-- Copyright (C) 2026 Ophelia
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_ophelia_extraction_result(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
	fk_document INTEGER NOT NULL,
	fk_template INTEGER DEFAULT NULL,
	strategy VARCHAR(50),
	global_confidence DECIMAL(5,4) DEFAULT 0,
	status SMALLINT DEFAULT 0 NOT NULL,
	fk_user_validator INTEGER DEFAULT NULL,
	date_extraction DATETIME NOT NULL,
	date_validation DATETIME DEFAULT NULL,
	tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
