-- Copyright (C) 2026 Ophelia
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_ophelia_template(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
	ref VARCHAR(128) NOT NULL,
	label VARCHAR(255) NOT NULL,
	description TEXT,
	doc_type VARCHAR(50) NOT NULL,
	version INTEGER DEFAULT 1 NOT NULL,
	active TINYINT DEFAULT 1 NOT NULL,
	fk_user_author INTEGER,
	date_creation DATETIME NOT NULL,
	tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
