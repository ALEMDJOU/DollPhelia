-- Copyright (C) 2026 Ophelia
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_ophelia_strategy(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
	code VARCHAR(50) NOT NULL,
	label VARCHAR(255) NOT NULL,
	description TEXT,
	priority INTEGER DEFAULT 0 NOT NULL,
	active TINYINT DEFAULT 1 NOT NULL,
	params_json TEXT
) ENGINE=innodb;
