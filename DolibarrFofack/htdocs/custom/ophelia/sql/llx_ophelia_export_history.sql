-- Copyright (C) 2026 Ophelia
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_ophelia_export_history(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
	fk_result INTEGER NOT NULL,
	fk_user INTEGER,
	export_format VARCHAR(10) NOT NULL,
	filepath VARCHAR(512) NOT NULL,
	date_export DATETIME NOT NULL
) ENGINE=innodb;
