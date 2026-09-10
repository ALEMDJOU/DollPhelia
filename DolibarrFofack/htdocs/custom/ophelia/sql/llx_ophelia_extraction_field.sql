-- Copyright (C) 2026 Ophelia
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_ophelia_extraction_field(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
	fk_result INTEGER NOT NULL,
	fk_template_field INTEGER DEFAULT NULL,
	field_name VARCHAR(128) NOT NULL,
	extracted_value TEXT,
	corrected_value TEXT,
	confidence_ocr DECIMAL(5,4) DEFAULT 0,
	confidence_spatial DECIMAL(5,4) DEFAULT 0,
	confidence_valid DECIMAL(5,4) DEFAULT 0,
	confidence_total DECIMAL(5,4) DEFAULT 0,
	bbox_x1 INTEGER,
	bbox_y1 INTEGER,
	bbox_x2 INTEGER,
	bbox_y2 INTEGER,
	page_num INTEGER DEFAULT 1,
	is_validated TINYINT DEFAULT 0 NOT NULL
) ENGINE=innodb;
