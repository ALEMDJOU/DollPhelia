-- Copyright (C) 2026 Ophelia
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_ophelia_template_field(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
	fk_template INTEGER NOT NULL,
	field_name VARCHAR(128) NOT NULL,
	field_label VARCHAR(255) NOT NULL,
	field_type VARCHAR(50) NOT NULL,
	key_text VARCHAR(255),
	delta_x DOUBLE DEFAULT 0,
	delta_y DOUBLE DEFAULT 0,
	theta DOUBLE DEFAULT 0,
	distance DOUBLE DEFAULT 0,
	relation_type VARCHAR(20) DEFAULT 'right_of',
	extraction_method VARCHAR(50) DEFAULT 'spatial',
	required TINYINT DEFAULT 1 NOT NULL,
	rang INTEGER DEFAULT 0 NOT NULL
) ENGINE=innodb;
